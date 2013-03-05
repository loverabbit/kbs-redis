bin_PROGRAMS = miscd newpop3d chatd bbslogd postlogd notifyd indexd cached pushd
EXTRA_DIST = hiredis pub-notif

miscd_SOURCES = miscd.c
newpop3d_SOURCES = newpop3.c
chatd_SOURCES = station.c
bbslogd_SOURCES = bbslogd.c common.c common.h
postlogd_SOURCES = postlogd.c common.c common.h
notifyd_SOURCES = notifyd.c common.c common.h
indexd_SOURCES = indexd.c common.c common.h
cached_SOURCES = cached.c common.c common.h
pushd_SOURCES = pushd.c common.c common.h

CC="@LIBTOOL@"
CCLD="@CC@"
LDADD=../libBBS/.libs/libBBS.a ../libsystem/.libs/libsystem.a -liconv 
AM_CFLAGS= -I$(top_srcdir)/src -g
CLEANFILES=@EXTRA_CLEAN@
newpop3d_CFLAGS=$(AM_CFLAGS) @OPENSSL_INC@
newpop3d_LDADD=$(LDADD) @OPENSSL_LIBS@

bbslogd_LDADD=$(LDADD)  $(top_builddir)/deps/hiredis/libhiredis.a
postlogd_LDADD=$(LDADD) $(top_builddir)/deps/hiredis/libhiredis.a
notifyd_LDADD=$(LDADD)  $(top_builddir)/deps/hiredis/libhiredis.a
indexd_LDADD=$(LDADD)   $(top_builddir)/deps/hiredis/libhiredis.a
cached_LDADD=$(LDADD)   $(top_builddir)/deps/hiredis/libhiredis.a
pushd_LDADD=$(LDADD)    @OPENSSL_LIBS@ $(top_builddir)/deps/hiredis/libhiredis.a
