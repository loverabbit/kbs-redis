#include "common.h"
#include <unistd.h>

void im_sysop() {
    if (init_all()) {
        fprintf (stderr, "init_all failed\n");
        return;
    }

    // I'm SYSOP
    struct userec *user;
    int usernum = getuser("SYSOP", &user);
    setCurrentUser(user);
    getSession()->currentuid = usernum;

    setgid(BBSGID);
    setuid(BBSUID);
}

int redis_open(struct redis_t *redis) {
    struct timeval timeout = { 1, 500000 }; // 1.5 seconds

    redis->con = redisConnectWithTimeout((char*)"127.0.0.1", 6379, timeout);

    if (redis->con->err) {
        fprintf (stderr, "Redis connection error: %s\n", redis->con->errstr);

        return redis->con->err;
    }

    return REDIS_OK;
}

void redis_open_forever(struct redis_t *redis) {
    int retries = 0;
    while (REDIS_OK != redis_open(redis)) {
        fprintf (stderr, "Trying to connect to redis: %d\n", retries);
        ++retries;

        redis_close(redis);
        sleep(10);
    }
}

void redis_close(struct redis_t *redis) {
    if (redis->con == NULL) return;

    redisFree(redis->con);
    redis->con = NULL;
}

int redis_simple_command(struct redis_t *redis, const char *cmd) {
    if (!redis->con) {
        fprintf (stderr, "No redis connection\n");
        return REDIS_ERR;
    }

    redisReply *reply = redisCommand(redis->con, cmd);
    if (NULL == reply) {
        fprintf (stderr, "redisCommand failed %s : %d : %s\n", cmd, redis->con->err, redis->con->errstr);

        redis_close(redis);
        return REDIS_ERR;
    }

    if (reply->type == REDIS_REPLY_ERROR) {
        fprintf (stderr, "redisCommand error %s : %d : %s\n", cmd, redis->con->err, redis->con->errstr);
        freeReplyObject(reply);

        redis_close(redis);
        return REDIS_ERR;
    }

    freeReplyObject(reply);
    return REDIS_OK;
}

int redis_loop(struct redis_t *redis, int (*call)(redisReply*)) {
    redisReply *reply;
    while(redisGetReply(redis->con, (void**)&reply) == REDIS_OK) {
        int ret = call(reply);
        freeReplyObject(reply);

        if (0 != ret) return ret;
    }

    return 0;
}

int get_article(const char *board, unsigned long id, struct fileheader *fh) {
    const struct boardheader *brd = getbcache(board);
    if (!brd) {
        return -1;
    }

    int ent = get_ent_from_id_ext(DIR_MODE_NORMAL, id, brd->filename, fh);
    if (ent < 0) {
        return -2;
    }

    return 0;
}
