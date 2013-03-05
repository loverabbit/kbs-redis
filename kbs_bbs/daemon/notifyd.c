// Convert bbs events to notifications
// fool <zcbenz@gmail.com>

#include "common.h"
#include <ctype.h>

// debug mode
const int debug = 0;

// Another redis connection
struct redis_t redis2;

// Read reply and translate
static int reply_read(redisReply *reply);

// Is the notify deserving sending
static int deserve(const char *user, const char *board, unsigned long id);

static int parse_at(const char *board, struct fileheader *fh);
static int notify_at(const char *board, unsigned long id, const char *user);

static int parse_reply(const char *board, struct fileheader *fh);
static int notify_reply(const char *board, unsigned long id, const char *user);

int main(int argc, char *argv[])
{
    im_sysop();

    if (!debug && dodaemon("notifyd", true, true)) {
        bbslog("3error", "notifyd had already been started!");
        return 0;
    }

    // Redis!
    while (1) {
        redis_open_forever(&redis);
        redis_open_forever(&redis2);

        // SUBSCRIBE
        if (REDIS_OK != redis_simple_command(&redis, "SUBSCRIBE event:post"))
            continue;

        // Event loop
        redis_loop(&redis, reply_read);

        redis_close(&redis);
        redis_close(&redis2);
    }

    return 0;
}

int reply_read(redisReply *reply) {
    if (!reply ||
        reply->type != REDIS_REPLY_ARRAY ||
        reply->elements != 3 ||
        reply->element[2]->type != REDIS_REPLY_STRING)
    {
        bbslog("3error", "notifyd(reply_read): Invalid message");
        return 0;
    }

    char board[512];
    unsigned long time;
    unsigned long id;

    // check response
    const char *info = reply->element[2]->str;
    if (3 != sscanf(info, "%lu:%512[^:]:%lu", &time, board, &id)) {
        bbslog("3error", "notifyd(reply_read): Invalid message %s", info);
        return 0;
    }

    if (!strcmp(reply->element[1]->str, "event:post")) {
        // check valid article
        struct fileheader fh;
        if (0 != get_article(board, id, &fh)) {
            bbslog("3error", "notifyd(reply_read): Invalid article %s %lu", board, id);
            return 0;
        }

        return parse_at(board, &fh) | parse_reply(board, &fh);
    }

    return 0;
}

 /* 返回值定义:
  *   -1  目标用户不存在;  1 目标用户无法阅读目标版面;
  *    2  该文章已读。
  *
  */
int deserve(const char *user, const char *board, unsigned long id) {
    struct userec *u;
    if (getuser(user, &u) == 0)
        return -1;
    //getuser(user, &u);
    const struct boardheader *bh;
    getbid(board, &bh);

    // check board permission
    if (0 == check_read_perm(u, bh)) return 1;

    // check article is read
    brc_initial(user, board, getSession());
    int unread = brc_unread(id, getSession());

    // clear brc cache
    munmap((void *)getSession()->brc_cache_entry,BRC_CACHE_NUM*sizeof(struct _brc_cache_entry));
    getSession()->brc_cache_entry = NULL;

    if (!unread) return 2;

    fprintf(stderr, "%s deserve notifying\n", user);

    return 0;
}

int publish_notify(const char *user) {
    char cmd[512];

    snprintf(cmd, 512, "PUBLISH event:notify %s", user);
    if (REDIS_OK != redis_simple_command(&redis2, cmd))
        return REDIS_ERR;

    return REDIS_OK;
}

int parse_at(const char *board, struct fileheader *fh) {
    int err = 0;

    // 禁止合集内的at
    int heji_len = strlen("[合集]");
    if (!strncmp("[合集]", fh->title, heji_len))
        return 0;

    char path[512];
    snprintf(path, 512, "boards/%s/%s", board, fh->filename);

    FILE *post = fopen(path, "r");
    if (post == NULL) return 0;

    int count = 0;
    int keep = '\n';
    int ch = fgetc(post);
    #define NEXT(ch) {\
        keep = ch;\
        ++count;\
        ch = count < 5000 ? fgetc(post) : EOF;\
    }

    // skip first tree lines
    int i;
    for (i = 0; i < 3; ++i) {
        while (ch != '\n' && ch != EOF) NEXT(ch);

        if (ch == '\n') { NEXT(ch); }
        else break;
    }

    while (ch != EOF) {
        // skip quote like
        // : bla bla
        if (keep == '\n' && ch == ':') {
            while (ch != '\n' && ch != EOF) NEXT(ch);

            if (ch == '\n') { NEXT(ch); continue; }
            else break;
        }

        if (ch != '@') {
            NEXT(ch);
            continue;
        }

        // forbid 123@id and $@id
        if (isdigit(keep) || keep == '$') {
            NEXT(ch);
            continue;
        }

        // @id
        size_t len = 0;
        char user[32] = { 0 };

        NEXT(ch);
        if (!isalpha(ch)) continue;

        do {
            user[len++] = tolower(ch);
            NEXT(ch);
        } while (isalpha(ch));

        // @id must not be followed by dot(.)
        if (ch == '.') continue;

        // do we need to notify?
        if (0 != deserve(user, board, fh->id)) continue;

        err = notify_at(board, fh->id, user);
        if (err != 0) break;
    }
    #undef NEXT

    fclose(post);
    return err;
}

int notify_at(const char *board, unsigned long id, const char *user) {
    fprintf(stderr, "@%s in %s %lu\n", user, board, id);

    char cmd[512];

    snprintf(cmd, 512, "SADD notify:%s:at %s:%lu", user, board, id);
    if (REDIS_OK != redis_simple_command(&redis2, cmd))
        return REDIS_ERR;

    //广播给客户端推送
    snprintf(cmd, 512, "PUBLISH event:push %s:at:%s:%lu", user, board, id);
    if (REDIS_OK != redis_simple_command(&redis2, cmd))
        return REDIS_ERR;

    // automatically clean accumulated notifications
    snprintf(cmd, 512, "EXPIRE notify:%s:at %d", user, 3600 * 24 * 7 /* keep 7 days */);
    if (REDIS_OK != redis_simple_command(&redis2, cmd))
        return REDIS_ERR;

    return publish_notify(user);
}

int parse_reply(const char *board, struct fileheader *fh) {
    if (fh->id != fh->reid) {
        struct fileheader fh2;
        // parent does not exist
        if (0 != get_article(board, fh->reid, &fh2)) return 0;

        // self reply
        if (!strcmp(fh->owner, fh2.owner)) return 0;

        // do we need to notify?
        if (0 != deserve(fh2.owner, board, fh->id)) {
            return 0;
        }

        return notify_reply(board, fh->id, fh2.owner);
    }

    return 0;
}

int notify_reply(const char *board, unsigned long id, const char *ouser)
{
    char cmd[512];

    // user = lower(ouser);
    char user[IDLEN + 1] = { 0 };
    int i;
    for (i = 0; i < IDLEN; ++i) {
        user[i] = tolower(ouser[i]);
    }

    // check if the post is in user's ats
    redisReply *reply = redisCommand(redis2.con, 
                        "SISMEMBER notify:%s:at %s:%lu", user, board, id);
    if (NULL == reply || reply->type != REDIS_REPLY_INTEGER) {
        return REDIS_ERR;
    } else if (reply->integer == 1) {
        freeReplyObject(reply);
        return 0;
    }
    freeReplyObject(reply);

    fprintf(stderr, "%s's post %lu in %s is replied\n", user, id, board);

    snprintf(cmd, 512, "SADD notify:%s:reply %s:%lu", user, board, id);
    if (REDIS_OK != redis_simple_command(&redis2, cmd))
        return REDIS_ERR;

    //广播给客户端推送
    snprintf(cmd, 512, "PUBLISH event:push %s:reply:%s:%lu", user, board, id);
    if (REDIS_OK != redis_simple_command(&redis2, cmd))
        return REDIS_ERR;

    // automatically clean accumulated notifications
    snprintf(cmd, 512, "EXPIRE notify:%s:reply %d", user, 3600 * 24 * 7 /* keep 7 days */);
    if (REDIS_OK != redis_simple_command(&redis2, cmd))
        return REDIS_ERR;

    return publish_notify(user);
}
