kbs-redis
=========

该项目的目的是将Redis引进到通用的KBS系统中。KBS是目前大陆最流行的telnet BBS系统之一。

通过Redis的引进，可以实现诸如全站索引、即时消息提醒等功能。

# 如何安装

1.首先需要安装 Redis。最新的版本是2.6.10，现在安装包后，直接 make && make install，配置文件请自己搞定。
2.其次请安装node.js，http://nodejs.org/去下载最新的，安装也是标准安装法。
3.其他的就很简单了，和编译KBS没啥区别，自己折腾一下吧。
