create table `postlog` (
 `id` int unsigned NOT NULL auto_increment,
 `userid` char(15) NOT NULL default '',
 `bname` char(31) NOT NULL default '',
 `title` char(81) NOT NULL default '',
 `time` timestamp NOT NULL,
 `threadid` int unsigned NOT NULL default '0',
 `articleid` int unsigned NOT NULL default '0',
 PRIMARY KEY (`id`),
 KEY userid(`userid`,`time`),
 KEY bname(`bname`, `threadid`),
 KEY post(`bname`, `articleid`),
 KEY timestamp(`time`)
) TYPE=MyISAM COMMENT='postlog';

create table `toplog` (
 `id` int unsigned NOT NULL auto_increment,
 `userid` char(15) NOT NULL default '',
 `bname` char(31) NOT NULL default '',
 `title` char(81) NOT NULL default '',
 `time` timestamp NOT NULL,
 `date` date NOT NULL,
 `topth` int NOT NULL default '1',
 `count` int NOT NULL default '0',
 `threadid` int unsigned NOT NULL default '0',
 PRIMARY KEY (`id`),
 KEY userid (`userid`),
 KEY bname(`bname`, `threadid`),
 KEY date(`date`),
 UNIQUE top (`date`,`topth`)
) TYPE=MyISAM COMMENT='toplog';

CREATE TABLE bms (
  `id` int(10) unsigned NOT NULL auto_increment,
  board varchar(20) NOT NULL default '',
  `in` timestamp(14) NOT NULL,
  `out` int(11) NOT NULL default '3',
  sysop varchar(15) default NULL,
  memo varchar(255) default NULL,
  userid varchar(15) NOT NULL default '',
  KEY `id` (`id`),
  KEY userid (userid),
  KEY board (board)
) TYPE=MyISAM COMMENT='斑竹任期记录表';
