// Push bbs events to APNS
// loverabbit <tengfei.yang@huawei.com>

#include "bbs.h"
#include "common.h"

#include <stdio.h>
#include <string.h>
#include <errno.h>
#include <netdb.h>
#include <unistd.h>
#include <iconv.h> 

#include <sys/types.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
 
#include <openssl/crypto.h>
#include <openssl/ssl.h>
#include <openssl/err.h>

#define CA_CERT_PATH        "Certs"
#define RSA_CLIENT_CERT     "/home/bbs/etc/apn-cert.pem"
#define RSA_CLIENT_KEY      "/home/bbs/etc/apn-key.pem"
#define APPLE_HOST          "gateway.push.apple.com"   //"gateway.sandbox.push.apple.com"
#define APPLE_PORT          2195
#define APPLE_FEEDBACK_HOST "feedback.push.apple.com" //"feedback.sandbox.push.apple.com"
#define APPLE_FEEDBACK_PORT 2196
#define DEVICE_BINARY_SIZE  32
#define MAXPAYLOAD_SIZE     256
#define OUTLEN              256

// debug mode
const int debug = 0;

// Read reply and translate
static int reply_read(redisReply *reply);

// Push to APNS
static void push_to_apple(const char *token, const char *user, const char *type, const char *board, struct fileheader *fh);

typedef struct {
    /* The struct for alert*/
    char *type;
    char *body;
    char *user;
    char *board;
    char *id;

    /* The name of the Sound which will be played back */
    char *soundName;

    /* The Number which is plastered over the icon, 0 disables it */
    int badgeNumber;
} Payload;

typedef struct {
    /* SSL Vars */
    SSL_CTX         *ctx;
    SSL             *ssl;
    SSL_METHOD      *meth;
    X509            *server_cert;
    EVP_PKEY        *pkey;

    /* Socket Communications */
    struct sockaddr_in   server_addr;
    struct hostent      *host_info;
    int                  sock;
} SSL_Connection;

/* Prototypes */
SSL_Connection *ssl_connect(const char *host, int port, const char *cerfile, const char *keyfile, const char *capath);
void ssl_disconnect(SSL_Connection *sslcon);

/* Initialize the payload with zero values */
void init_payload(Payload *payload);

/* Send a Notification to a specified iPhone */
int send_remote_notification(const char *deviceTokenHex, Payload *payload);

int main(int argc, char *argv[])
{
    im_sysop();

    if (!debug && dodaemon("pushd", true, true)) {
        bbslog("3error", "pushd had already been started!");
        return 0;
    }

    // Redis!
    while (1) {
        redis_open_forever(&redis);

        // SUBSCRIBE
        if (REDIS_OK != redis_simple_command(&redis, "SUBSCRIBE event:push"))
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

    char user[512];
    char type[5];
    char board[512];
    unsigned long id;
    char cmd[512];
    char *element = NULL;
    int i;

    const char *info = reply->element[2]->str;
    //event:push %s:at:%s:%lu", user, board, id);
    if (4 != sscanf(info, "%512[^:]:%5[^:]:%512[^:]:%lu", user, type, board, &id)) {
        fprintf (stderr, "Invalid event:push: %s\n", info);
        return 0;
    }

    struct fileheader fh;
    if (0 != get_article(board, id, &fh)) {
        fprintf (stderr, "Invalid post: %s %lu\n", board, id);
        return 0;
    }

    snprintf(cmd, 512, "SMEMBERS itoken:%s", user);

    redisContext* conn = redisConnect("127.0.0.1",6379);
    if(conn->err) printf("connection error:%s\n",conn->errstr);
    redisReply* ret = redisCommand(conn, cmd);  

    for (i = 0; i < ret->elements; i++) {
        element = ret->element[i]->str;
        fprintf (stderr, "token: %s\n", element);
        push_to_apple(element, user, type, board, &fh);
    }
    freeReplyObject(ret);
    redisFree(conn);

    return 0;
}

int code_convert(char *from_charset,char *to_charset,char *inbuf,int inlen,char *outbuf,int outlen) {
    iconv_t cd;
    int rc;
    char **pin = &inbuf;
    char **pout = &outbuf;

    cd = iconv_open(to_charset,from_charset);
    if (cd==0) return -1;
    memset(outbuf,0,outlen);
    if (iconv(cd,pin,&inlen,pout,&outlen)==-1) return -1;
    iconv_close(cd);
    return 0;
}

/* convert GB2312 to UTF-8. */
int g2u(char *inbuf,size_t inlen,char *outbuf,size_t outlen) {
     return code_convert("gb2312","utf-8",inbuf,inlen,outbuf,outlen);
}

void push_to_apple(const char *token, const char *user, const char *type, const char *board, struct fileheader *fh) {

    char body[512];
    char id[512];
    char cmd[512];
    int i=0;
    char buf[256];
    char out[OUTLEN];
    int rc;
    //char title[512];
    //mysql_escape_string(title, fh->title, strlen(fh->title));

    snprintf(cmd, 512, "SUNION notify:%s:at notify:%s:reply", user, user);

    redisContext* conn = redisConnect("127.0.0.1",6379);
    if(conn->err) printf("connection error:%s\n",conn->errstr);
    redisReply* ret = redisCommand(conn, cmd);  
    i = ret->elements;
    freeReplyObject(ret);
    redisFree(conn);

    /* Phone specific Payload message as well as hex formated device token */
    const char     *deviceTokenHex = NULL;
    deviceTokenHex = token;

    if(strlen(deviceTokenHex) < 64 || strlen(deviceTokenHex) > 74) {
        printf("Device Token is to short or to long. Length without spaces should be 64 chars...\n");
        return 0;
    }

    Payload *payload = (Payload*)malloc(sizeof(Payload));
    init_payload(payload);

    // This is the alert array
    int at=0;
    if (!strcmp(type, "at")) at = 1;
    snprintf(body, 512, at == 1 ? "新爱特 %s:%s" : "新回复 %s:%s", fh->owner, fh->title);
    rc = g2u(body, strlen(body),out,OUTLEN);
    printf("gb2312-utf8 out=%s",out); 
    snprintf(id, 512, "%lu", fh->id);
    payload->type = type;
    payload->body = out;
    payload->user = fh->owner;
    payload->board = board;
    payload->id = id;

    // This is the red numbered badge that appears over the Icon
    payload->badgeNumber = i;

    payload->soundName = "myNotification.m4a";

    /* Send the payload to the phone */
    printf("Sending APN to Device with UDID: %s\n", deviceTokenHex);
    send_remote_notification(deviceTokenHex, payload);

    return 0;
}

SSL_Connection *ssl_connect(const char *host, int port, const char *certfile, const char *keyfile, const char *capath) 
{
    int err;

    SSL_Connection *sslcon = NULL;
    sslcon = (SSL_Connection *)malloc(sizeof(SSL_Connection));
    if(sslcon == NULL) {
        printf("Could not allocate memory for SSL Connection");
        exit(1);
    }

    /* Load encryption & hashing algorithms for the SSL program */
    SSL_library_init();

    /* Load the error strings for SSL & CRYPTO APIs */
    SSL_load_error_strings();

    /* Create an SSL_METHOD structure (choose an SSL/TLS protocol version) */
    sslcon->meth = SSLv3_method();
 
    /* Create an SSL_CTX structure */
    sslcon->ctx = SSL_CTX_new(sslcon->meth);
    if(!sslcon->ctx) {
        printf("Could not get SSL Context\n");
        exit(1);
    }

    /* Load the CA from the Path */
    if(SSL_CTX_load_verify_locations(sslcon->ctx, NULL, capath) <= 0) {
        /* Handle failed load here */
        printf("Failed to set CA location...\n");
        ERR_print_errors_fp(stderr);
        exit(1);
    }

    /* Load the client certificate into the SSL_CTX structure */
    if (SSL_CTX_use_certificate_file(sslcon->ctx, certfile, SSL_FILETYPE_PEM) <= 0) {
        printf("Cannot use Certificate File\n");
        ERR_print_errors_fp(stderr);
        exit(1);
    }

    /* Load the private-key corresponding to the client certificate */
    if (SSL_CTX_use_PrivateKey_file(sslcon->ctx, keyfile, SSL_FILETYPE_PEM) <= 0) {
        printf("Cannot use Private Key\n");
        ERR_print_errors_fp(stderr);
        exit(1);
    }

    /* Check if the client certificate and private-key matches */
    if (!SSL_CTX_check_private_key(sslcon->ctx)) {
        printf("Private key does not match the certificate public key\n");
        exit(1);
    }

    /* Set up a TCP socket */
    sslcon->sock = socket (PF_INET, SOCK_STREAM, IPPROTO_TCP);
    if(sslcon->sock == -1) {
        printf("Could not get Socket\n");
        exit(1);
    }

    memset (&sslcon->server_addr, '\0', sizeof(sslcon->server_addr));
    sslcon->server_addr.sin_family      = AF_INET;
    sslcon->server_addr.sin_port        = htons(port);       /* Server Port number */
    sslcon->host_info = gethostbyname(host);
    if(sslcon->host_info) {
        /* Take the first IP */
        struct in_addr *address = (struct in_addr*)sslcon->host_info->h_addr_list[0];
        sslcon->server_addr.sin_addr.s_addr = inet_addr(inet_ntoa(*address)); /* Server IP */
    } else {
        printf("Could not resolve hostname %s\n", host);
        return NULL;
    }

    /* Establish a TCP/IP connection to the SSL client */
    err = connect(sslcon->sock, (struct sockaddr*) &sslcon->server_addr, sizeof(sslcon->server_addr)); 
    if(err == -1) {
        printf("Could not connect\n");
        exit(1);
    }

    /* An SSL structure is created */
    sslcon->ssl = SSL_new(sslcon->ctx);
    if(!sslcon->ssl) {
        printf("Could not get SSL Socket\n");
        exit(1);
    }
 
    /* Assign the socket into the SSL structure (SSL and socket without BIO) */
    SSL_set_fd(sslcon->ssl, sslcon->sock);
 
    /* Perform SSL Handshake on the SSL client */
    err = SSL_connect(sslcon->ssl);
    if(err <= 0) {
        printf("Could not connect to SSL Server\n");
        exit(1);
    }

    return sslcon;
}

void ssl_disconnect(SSL_Connection *sslcon) {
    int err;

    if(sslcon == NULL) {
        return;
    }

    /* Shutdown the client side of the SSL connection */
    err = SSL_shutdown(sslcon->ssl);
    if(err == -1) {
        printf("Could not shutdown SSL\n");
        exit(1);
    }
 
    /* Terminate communication on a socket */
    err = close(sslcon->sock);
    if(err == -1) {
        printf("Could not close socket\n");
        exit(1);
    }
 
    /* Free the SSL structure */
    SSL_free(sslcon->ssl);
 
    /* Free the SSL_CTX structure */
    SSL_CTX_free(sslcon->ctx);

    /* Free the sslcon */
    if(sslcon != NULL) {
        free(sslcon);
        sslcon = NULL;
    }
}

/* Used internally to send the payload */
int send_payload(const char *deviceTokenHex, const char *payloadBuff, size_t payloadLength);

/* Initialize the Payload with zero values */
void init_payload(Payload *payload) {
    bzero(payload, sizeof(Payload));
}

/* Function for sending the Payload */
int send_remote_notification(const char *deviceTokenHex, Payload *payload) {
    char messageBuff[MAXPAYLOAD_SIZE];
    char tmpBuff[MAXPAYLOAD_SIZE];
    char badgenumBuff[3];

    strcpy(messageBuff, "{\"aps\":{");

    if(payload->body != NULL) {
        strcat(messageBuff, "\"alert\":");
        sprintf(tmpBuff, "{\"type\":\"%s\",\"body\":\"%s\",\"user\":\"%s\",\"board\":\"%s\",\"id\":\"%s\"},", payload->type, payload->body, payload->user, payload->board, payload->id);
        sprintf(tmpBuff, "{\"type\":\"%s\",\"body\":\"%s\"},", payload->type, payload->body);
        strcat(messageBuff, tmpBuff);
    }

    if(payload->badgeNumber > 99 || payload->badgeNumber < 0)
        payload->badgeNumber = 1;

    sprintf(badgenumBuff, "%d", payload->badgeNumber);
    strcat(messageBuff, "\"badge\":");
    strcat(messageBuff, badgenumBuff);

    strcat(messageBuff, ",\"sound\":\"");
    strcat(messageBuff, payload->soundName == NULL ? "default" : payload->soundName);

    strcat(messageBuff, "\"}");
    strcat(messageBuff, "}");
    printf("Sending %s\n", messageBuff);

    send_payload(deviceTokenHex, messageBuff, strlen(messageBuff));
}

int send_payload(const char *deviceTokenHex, const char *payloadBuff, size_t payloadLength) {
    int rtn = 0;

    SSL_Connection *sslcon = ssl_connect(APPLE_HOST, APPLE_PORT, RSA_CLIENT_CERT, RSA_CLIENT_KEY, CA_CERT_PATH);
    if(sslcon == NULL) {
        printf("Could not allocate memory for SSL Connection");
        exit(1);
    }

    if (sslcon && deviceTokenHex && payloadBuff && payloadLength) {
        uint8_t command = 0; /* command number */
        char binaryMessageBuff[sizeof(uint8_t) + sizeof(uint16_t) + DEVICE_BINARY_SIZE + sizeof(uint16_t) + MAXPAYLOAD_SIZE];

        /* message format is, |COMMAND|TOKENLEN|TOKEN|PAYLOADLEN|PAYLOAD| */
        char *binaryMessagePt = binaryMessageBuff;
        uint16_t networkOrderTokenLength = htons(DEVICE_BINARY_SIZE);
        uint16_t networkOrderPayloadLength = htons(payloadLength);

        /* command */
        *binaryMessagePt++ = command;

        /* token length network order */
        memcpy(binaryMessagePt, &networkOrderTokenLength, sizeof(uint16_t));
        binaryMessagePt += sizeof(uint16_t);

        /* Convert the Device Token */
        int i = 0;
        int j = 0;
        int tmpi;
        char tmp[3];
        char deviceTokenBinary[DEVICE_BINARY_SIZE];
        while(i < strlen(deviceTokenHex)) {
            if(deviceTokenHex[i] == ' ') {
                i++;
            } else {
                tmp[0] = deviceTokenHex[i];
                tmp[1] = deviceTokenHex[i + 1];
                tmp[2] = '\0';

                sscanf(tmp, "%x", &tmpi);
                deviceTokenBinary[j] = tmpi;

                i += 2;
                j++;
            }
        }

        /* device token */
        memcpy(binaryMessagePt, deviceTokenBinary, DEVICE_BINARY_SIZE);
        binaryMessagePt += DEVICE_BINARY_SIZE;

        /* payload length network order */
        memcpy(binaryMessagePt, &networkOrderPayloadLength, sizeof(uint16_t));
        binaryMessagePt += sizeof(uint16_t);
 
        /* payload */
        memcpy(binaryMessagePt, payloadBuff, payloadLength);
        binaryMessagePt += payloadLength;
        if (SSL_write(sslcon->ssl, binaryMessageBuff, (binaryMessagePt - binaryMessageBuff)) > 0)
            rtn = 1;
    }

    ssl_disconnect(sslcon);

    return rtn;
}
