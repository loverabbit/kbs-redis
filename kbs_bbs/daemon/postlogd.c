// Write POST logs into MYSQL database
// fool <zcbenz@gmail.com>

#include "bbs.h"
#include "common.h"
#include <unistd.h>

// debug mode
const int debug = 0;

// Read reply and translate
static int reply_read(redisReply *reply);

// Log into MySQL
static void write_to_mysql(const char *board, time_t time, struct fileheader *fh);
static void remove_from_mysql(const char *board, time_t time, unsigned long id);

int main(int argc, char *argv[])
{
    im_sysop();

    if (!debug && dodaemon("postlogd", true, true)) {
        bbslog("3error", "postlogd had already been started!");
        return 0;
    }

    // Redis!
    while (1) {
        redis_open_forever(&redis);

        // SUBSCRIBE
        if (REDIS_OK != redis_simple_command(&redis, "SUBSCRIBE event:post"))
            continue;
        if (REDIS_OK != redis_simple_command(&redis, "SUBSCRIBE event:delete"))
            continue;

        // Event loop
        redis_loop(&redis, reply_read);

        redis_close(&redis);
    }

    return 0;
}

int reply_read(redisReply *reply) {
    if (!reply ||
        reply->type != REDIS_REPLY_ARRAY ||
        reply->elements != 3 ||
        reply->element[2]->type != REDIS_REPLY_STRING)
    {
        fprintf (stderr, "Invalid reply encountered\n");
        return 0;
    }

    char board[512];
    unsigned long time;
    unsigned long id;

    const char *info = reply->element[2]->str;
    if (3 != sscanf(info, "%lu:%512[^:]:%lu", &time, board, &id)) {
        fprintf (stderr, "Invalid event:log: %s\n", info);
        return 0;
    }
    
    if (!strcmp("event:delete", reply->element[1]->str)) {
        remove_from_mysql(board, time, id);
    } else if (!strcmp("event:post", reply->element[1]->str)) {
        struct fileheader fh;
        if (0 != get_article(board, id, &fh)) {
            fprintf (stderr, "Invalid post: %s %lu\n", board, id);
            return 0;
        }

        write_to_mysql(board, time, &fh);
    }

    return 0;
}
 
void write_to_mysql(const char *board, time_t time, struct fileheader *fh) {
    MYSQL s;
    mysql_init (&s);

    if (!my_connect_mysql(&s)) {
        fprintf (stderr, "Mysql connect failed: %s\n", mysql_error(&s));
        return;
    }

    char title[512];
    mysql_escape_string(title, fh->title, strlen(fh->title));
    char sqlbuf[512];
    char newts[24];
    sprintf(sqlbuf, "INSERT INTO postlog(userid, bname, title, time, threadid, postid, replyid) VALUES ('%s', '%s', '%s', '%s', '%d' , '%d');",
                    fh->owner, board, title, tt2timestamp(time, newts), fh->groupid, fh->id);

    if (mysql_real_query(&s, sqlbuf, strlen(sqlbuf))) {
        fprintf (stderr, "Mysql query failed: %s\n", mysql_error(&s));
    }

    mysql_close(&s);
}

void remove_from_mysql(const char *board, time_t time, unsigned long id) {
    MYSQL s;
    mysql_init (&s);

    if (!my_connect_mysql(&s)) {
        fprintf (stderr, "Mysql connect failed: %s\n", mysql_error(&s));
        return;
    }

    char sqlbuf[512];
    sprintf(sqlbuf, "DELETE FROM postlog WHERE postid=%lu", id);

    if (mysql_real_query(&s, sqlbuf, strlen(sqlbuf))) {
        fprintf (stderr, "Mysql query failed: %s\n", mysql_error(&s));
    }

    mysql_close(&s);
}

