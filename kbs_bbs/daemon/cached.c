// Catch before:event:post and store it in cache
// and then publish event:post
// fool <zcbenz@gmail.com>

#include "bbs.h"
#include "common.h"
#include <unistd.h>

// debug mode
const int debug = 0;

// Read reply and translate
static int reply_read(redisReply *reply);

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

    return 0;
}
