#ifndef COMMON_H
#define COMMON_H

#include "bbs.h"
#include "../deps/hiredis/hiredis.h"

struct redis_t {
    redisContext *con;
} redis;

// Setup bbs stuff and set user as SYSOP
void im_sysop();

// Open/close redis connection
int redis_open(struct redis_t *redis);
void redis_open_forever(struct redis_t *redis);
void redis_close(struct redis_t *redis);

// send a simple command
int redis_simple_command(struct redis_t *redis, const char *cmd);

// loop in getting replys
// return no-zero in callback will brea of loop
int redis_loop(struct redis_t *redis, int (*)(redisReply*));

// get article
int get_article(const char *board, unsigned long id, struct fileheader *fh);

#endif /* end of COMMON_H */
