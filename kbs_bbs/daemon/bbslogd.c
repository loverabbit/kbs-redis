// Receive logs from bbsd and write them to files
// and push logs as events into redis
// fool <zcbenz@gmail.com>

#include "bbs.h"
#include "common.h"
#include <sys/ipc.h>
#include <sys/msg.h>
#include <signal.h>
#include <unistd.h>

// debug mode
const int debug = 0;

// flush interval
const int flush_interval = 60 * 10;

struct record_t {
    const char *filename;
    size_t bufsize;             /* 缓存大小，如果是 0，不缓存 */

    /*
     * 运行时参数 
     */
    int fd;                     /* 文件句柄 */
    char *buf;                  /* 缓存 */
    size_t cur;                 /* 使用缓存位置 */
};

enum RECORD_RESULT {
    RECORD_ERROR,
    RECORD_SUCCESS,
    RECORD_FULL
};

static struct record_t log_records[] = {
     { "usies"          , 100 * 1024 , 0 , NULL , 0 } ,
     { "user.log"       , 100 * 1024 , 0 , NULL , 0 } ,
     { "boardusage.log" , 100 * 1024 , 0 , NULL , 0 } ,
     { "sms.log"        , 10 * 1024  , 0 , NULL , 0 } ,
     { "debug.log"      , 10 * 1024  , 0 , NULL , 0 } ,
     { "realcheck.log"  , 10 * 1024  , 0 , NULL , 0 }
};
#define LOG_RECORDS_SIZE sizeof(log_records) / sizeof(struct record_t)

// init all records
static void log_init();
static void log_clear();

// flush all records
static void log_flush();

// receive msg from message queue, and forward it to the 
// public message queue
static struct bbs_msgbuf* log_rcv(int msqid);

// write msg to file
static void log_write(struct bbs_msgbuf *msg);

// open fd
static enum RECORD_RESULT record_open(struct record_t *record);

// close fd
static enum RECORD_RESULT record_close(struct record_t *record);

// flush record to disk
static enum RECORD_RESULT record_flush(struct record_t *record);

// append content to record's buffer
static enum RECORD_RESULT record_append(struct record_t *record, const char *content, size_t size);

// write content to disk
static enum RECORD_RESULT record_write(struct record_t *record, const char *content, size_t size);

// print header of BBSLOG_POST
static void print_post_header(struct bbs_msgbuf *msg, char *header, size_t size);

// flush log at 10m
static void on_log_alarm(int signo)
{
    log_flush();
    alarm(flush_interval);
}

// ends
static void on_log_exit(int signo)
{
    log_flush();
    log_clear();
    redis_close(&redis);
    exit(0);
}

int main(int argc, char *argv[])
{
    umask(027);

    chdir(BBSHOME);
    setuid(BBSUID);
    setreuid(BBSUID, BBSUID);
    setgid(BBSGID);
    setregid(BBSGID, BBSGID);

    if (!debug && dodaemon("bbslogd", true, true)) {
        bbslog("3error", "bbslogd had already been started!");
        return 0;
    }

    signal(SIGALRM , on_log_alarm );
    signal(SIGTERM , on_log_exit  );
    signal(SIGINT  , on_log_exit  );
    signal(SIGABRT , on_log_exit  );
    signal(SIGHUP  , on_log_exit  );
    signal(SIGPIPE , SIG_IGN      );

    int msqid = init_bbslog();
    if (msqid < 0) {
        fprintf (stderr, "init_bbslog: %d\n", msqid);
        return -1;
    }
    fprintf (stderr, "message queue of bbsd is ok: %d\n", msqid);

    // init redis
    if (redis_open(&redis) == REDIS_OK) {
        fprintf (stderr, "Redis opened\n");
    }

    // init logs
    log_init();
    alarm(flush_interval);

    // enter event loop
    struct bbs_msgbuf *msg;
    while ((msg = log_rcv(msqid)) != NULL) {
        if (debug) {
            fprintf (stderr, "msg: %d %s\n", (int)msg->mtype, msg->mtext);
        }

        log_write(msg);
    }

    // clean everything
    on_log_exit(0);

    return 0;
}

void log_init()
{
    int i;
    for (i = 0; i < LOG_RECORDS_SIZE; i++) {
        struct record_t *record = log_records + i;
        record->buf = malloc(record->bufsize);
    }
}

void log_clear()
{
    int i;
    for (i = 0; i < LOG_RECORDS_SIZE; i++) {
        struct record_t *record = log_records + i;
        free(record->buf);
    }
}

void log_flush()
{
    int i;
    for (i = 0; i < LOG_RECORDS_SIZE; i++) {
        struct record_t *record = log_records + i;
        record_flush(record);
    }
}

struct bbs_msgbuf* log_rcv(int msqid)
{
    static char buf[1024];
    struct bbs_msgbuf *msg = (struct bbs_msgbuf *) buf;

    // grab memory from message queue
    int retv = msgrcv(msqid, msg, sizeof(buf) - sizeof(msg->mtype) - 2, 0, MSG_NOERROR);
    while (retv < 0) {
        fprintf (stderr, "msgrcv failed: %s\n", strerror(errno));

        // restart
        if (errno == EINTR) {
            retv = msgrcv(msqid, msg, sizeof(buf) - sizeof(msg->mtype) - 2, 0, MSG_NOERROR);
        } else {
            bbslog("3error", "bbslogd(rcvlog):%s", strerror(errno));
            return NULL;
        }
    }

    if (debug) fprintf (stderr, "msgrcv returned: %d\n", retv);

    retv -= (char*)msg->mtext - (char*)&msg->msgtime;

    // Add new line for real logs
    switch (msg->mtype) {
    case BBSLOG_POST:
    case BBSLOG_DELETE:
    case BBSLOG_UPDATE:
    case BBSLOG_READ:
        break;
    default:
        while (retv > 0 && msg->mtext[retv - 1] == 0) retv--;
        if (retv == 0) return NULL;
        if (msg->mtext[retv - 1] != '\n') {
            msg->mtext[retv] = '\n';
            retv++;
        }
        msg->mtext[retv] = 0;

        return msg;
    }

    // guard
    if (msg->mtype == BBSLOG_POST && retv <= sizeof(struct _new_postlog))
        return NULL;

    // forward and publish to redis
    int retries = 0;
    while (retries < 4) {
        // check connection condition
        if (!redis.con || redis.con->err) {
            fprintf (stderr, "Trying to reconnect to redis: %d\n", retries);
            ++retries;

            redis_close(&redis);
            redis_open(&redis);
            continue;
        }

        const int message_len = 64;
        char message[message_len];
        const char *argv[3] = { "PUBLISH", NULL, message };

        struct _new_postlog *ppl;

        // set command
        switch (msg->mtype) {
        case BBSLOG_POST:
            ppl = (struct _new_postlog*)(&msg->mtext[1]) ;
            argv[1] = "event:post";
            snprintf(message, message_len, "%lu:%s:%lu",
                    (unsigned long)msg->msgtime,
                    ppl->boardname,
                    (unsigned long)ppl->articleid);
            break;
        case BBSLOG_DELETE:
            argv[1] = "event:delete";
            snprintf(message, message_len, "%lu:%s:%lu",
                    (unsigned long)msg->msgtime,
                    msg->mtext,
                    (unsigned long)msg->pid);
            break;
        case BBSLOG_UPDATE:
            argv[1] = "event:update";
            snprintf(message, message_len, "%lu:%s:%lu",
                    (unsigned long)msg->msgtime,
                    msg->mtext,
                    (unsigned long)msg->pid);
            break;
        case BBSLOG_READ:
            argv[1] = "event:read";
            argv[2] = msg->mtext;
            break;
        }

        // send command
        redisReply *reply = redisCommandArgv(redis.con, 3, argv, NULL);

        // check result of redisCommand
        if (reply == NULL) {
            bbslog("3error", "bbslogd(log_rcv) redisCommand %s", redis.con->errstr);
            continue;
        }

        if (debug) fprintf (stderr, "published\n");

        if (reply->type == REDIS_REPLY_ERROR) {
            bbslog("3error", "bbslogd(log_rcv) redisCommand error %s", reply->str);

            redis_close(&redis);
            freeReplyObject(reply);
            continue;
        }

        if (reply->type == REDIS_REPLY_INTEGER)
            fprintf (stderr, "redisCommand: %d\n", (int)reply->integer);

        freeReplyObject(reply);

        return msg;
    }

    return msg;
}

void log_write(struct bbs_msgbuf *msg)
{
    int index = -1;

    // print BBSLOG_POST logs as BBSLOG_USER
    index = msg->mtype == BBSLOG_POST ? BBSLOG_USER : msg->mtype;

    // log range
    if (index < 1 || index > LOG_RECORDS_SIZE)
        return;

    struct record_t *record = log_records + (index - 1);

    char ch = msg->mtext[0];
    msg->mtext[0] = 0;

    // print header
    char header[256];
    if (msg->mtype == BBSLOG_POST) {
        print_post_header(msg, header, 256);
    } else {
        struct tm *n;
        n = localtime(&msg->msgtime);

        snprintf(header, 256,
                 "[%02u/%02u %02u:%02u:%02u %5lu %lu] %s %c%s",
                 /* mon/day */ n->tm_mon + 1, n->tm_mday,
                 /* h:m:s */   n->tm_hour, n->tm_min, n->tm_sec,
                 (long int) msg->pid,
                 msg->mtype,
                 msg->userid,
                 ch,
                 &msg->mtext[1]);
    }
    int header_len = strlen(header);

    // write to buffer
    if (RECORD_FULL == record_append(record, header, header_len)) {
        // or flush and then write to disk
        record_flush(record);
        record_write(record, header, header_len);
    }
}

enum RECORD_RESULT record_open(struct record_t *record)
{
    record->fd = open(record->filename, O_RDWR | O_CREAT, 0644);
    if (record->fd < 0) {
        bbslog("3error", "can't open log file:%s.%s", record->filename, strerror(errno));
        return RECORD_ERROR;
    }

    return RECORD_SUCCESS;
}

enum RECORD_RESULT record_close(struct record_t *record)
{
    close(record->fd);

    return RECORD_SUCCESS;
}

enum RECORD_RESULT record_flush(struct record_t *record)
{
    if (NULL == record) {
        fprintf (stderr, "Invalid record in record_flush\n");
        return RECORD_ERROR;
    }

    if (debug) fprintf (stderr, "record_flush: %s\n", record->filename);

    // flush
    if (record->buf && record->cur > 0) {
        record_write(record, record->buf, record->cur);
        record->cur = 0;
    }

    return RECORD_SUCCESS;
}

enum RECORD_RESULT record_append(struct record_t *record, const char *content, size_t size)
{
    if (NULL == record) {
        fprintf (stderr, "Invalid record in record_flush\n");
        return RECORD_ERROR;
    }

    if (record->buf && (record->cur + size <= record->bufsize)) {
        memcpy(record->buf + record->cur, content, size);
        record->cur += size;

        if (debug) fprintf (stderr, "record_append: %s %s\n", record->filename, content);
        return RECORD_SUCCESS;
    }

    fprintf (stderr, "record full: %s\n", record->filename);
    return RECORD_FULL;
}

enum RECORD_RESULT record_write(struct record_t *record, const char *content, size_t size)
{
    if (NULL == record) {
        fprintf (stderr, "Invalid record in record_flush\n");
        return RECORD_ERROR;
    }

    record_open(record);

    writew_lock(record->fd, 0, SEEK_SET, 0);
    lseek(record->fd, 0, SEEK_END);

    int ret = write(record->fd, content, size);

    un_lock(record->fd, 0, SEEK_SET, 0);

    record_close(record);

    if (-1 == ret) {
        fprintf (stderr, "record_write failed\n");
        return RECORD_ERROR;
    }

    if (debug) fprintf (stderr, "record_write: %s %s\n", record->filename, content);
    return RECORD_SUCCESS;
}

void print_post_header(struct bbs_msgbuf *msg, char *header, size_t size)
{
    struct _new_postlog *ppl = (struct _new_postlog*)(&msg->mtext[1]) ;
    struct tm *n = localtime(&msg->msgtime);

    snprintf(header, size, "[%02u/%02u %02u:%02u:%02u %5lu %lu] %s post '%s' on '%s'\n", n->tm_mon + 1, n->tm_mday, n->tm_hour, n->tm_min, n->tm_sec, (long int) msg->pid, msg->mtype, msg->userid, ppl->title, ppl->boardname);
}
