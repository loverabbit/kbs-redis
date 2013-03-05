var faye = require('faye');
var redis = require('redis');

// write pid
require('fs').writeFileSync('/home/bbs/var/pub-notify.pid', process.pid);

var bayeux = new faye.NodeAdapter({mount: '/faye', timeout: 45});
bayeux.listen(8259);

var client;
set_client(client);

function set_client(client) {
    client = redis.createClient();

    client.on('ready', function () {
        client.subscribe('event:notify');
    });

    client.on('message', function (channel, message) {
        console.log('/notify/' + message);
        bayeux.getClient().publish('/notify/' + message, 'notify');
    });

    client.on('error', function () {
        client.end();
        set_client(client);
    });
}
