var redis = require('redis');

// write pid
require('fs').writeFileSync('/home/bbs/var/readstat.pid', process.pid);

var client;
restart();

var c2;
restart2();

function restart() {
    client = redis.createClient();

    client.on('ready', function () {
        var i = client.subscribe('event:read', 'event:delete');
        console.log('subscribe ' + i);
    });

    client.on('message', function (channel, message) {
        if (channel == 'event:delete') {
            var items = message.split(':', 3);
            invalidate_key(items[1], items[2]);
        } else if (channel == 'event:read') {
            var items = message.split(':', 3);
            analytic(items[0], items[1], items[2]);
        }
    });

    client.on('error', function (err) {
        console.log('error ' + err);

        client.end();
        restart(client);
    });
}

function restart2() {
    c2 = redis.createClient();
    c2.on('error', function (err) {
        console.log('error ' + err);

        c2.end();
        restart2(c2);
    });
}

function analytic(board, id, ip) {
    var ip_key = 'count:' + board + ':' + id + ':ip';
    var count_key = 'count:' + board + ':' + id;

    c2.exists(ip_key, function(err, exists) {
        c2.sadd(ip_key, ip, function(err, is_in) {
            // expire it when first add ip
            if (exists == 0) {
                console.log('Create key ' + ip_key);
                c2.expire(ip_key, 3600 * 3);
            }

            // increment when not in set
            if (is_in == 1) {
                console.log('New read ' + board + ' ' + id + ' by ' + ip);
                c2.incr(count_key);
                c2.incr('stat:count:board:' + board);
                c2.incr('stat:day:count:board:' + board);
                c2.incr('stat:week:count:board:' + board);
            }
        });
    });
}

function invalidate_key(board, id) {
    var ip_key = 'count:' + board + ':' + id + ':ip';
    var count_key = 'count:' + board + ':' + id;
    console.log('Delete key: ' + ip_key + ' ' + count_key);

    c2.del(ip_key, count_key);
}
