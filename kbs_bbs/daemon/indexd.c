// Save bbs events to queue for later indexing
// fool <zcbenz@gmail.com>

#include "common.h"

// debug mode
const int debug = 0;

// Another redis connection
struct redis_t redis2;

// Read reply and translate
static int reply_read(redisReply *reply);

int main(int argc, char *argv[])
{
    im_sysop();

    if (!debug && dodaemon("indexd", true, true)) {
        bbslog("3error", "indexd had already been started!");
        return 0;
    }

    // Redis!
    while (1) {
        redis_open_forever(&redis);
        redis_open_forever(&redis2);

        // SUBSCRIBE
        if (REDIS_OK != redis_simple_command(&redis, "SUBSCRIBE event:post"))
            continue;
        if (REDIS_OK != redis_simple_command(&redis, "SUBSCRIBE event:delete"))
            continue;
        if (REDIS_OK != redis_simple_command(&redis, "SUBSCRIBE event:update"))
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
        reply->element[1]->type != REDIS_REPLY_STRING ||
        reply->element[2]->type != REDIS_REPLY_STRING)
    {
        fprintf (stderr, "Invalid reply encountered\n");
        return 0;
    }

    const char *type = reply->element[1]->str;
    const char *info = reply->element[2]->str;
    char queue_cmd[512];
    sprintf(queue_cmd, "RPUSH queue:index %s:%s", type, info);

    if (REDIS_OK != redis_simple_command(&redis2, queue_cmd))
        return 1;

    return 0;
}
