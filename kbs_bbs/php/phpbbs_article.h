#ifndef PHP_BBS_ARTICLE_H
#define PHP_BBS_ARTICLE_H

/* 文章索引读取和查询 */

#ifdef SBBSAPI
PHP_FUNCTION(bbs2_record_is_unread);
#endif
PHP_FUNCTION(bbs_get_records_from_id);
PHP_FUNCTION(bbs_get_records_from_num);
PHP_FUNCTION(bbs_get_filename_from_num);
PHP_FUNCTION(bbs_get_threads_from_id);
PHP_FUNCTION(bbs_get_threads_from_gid);

PHP_FUNCTION(bbs_countarticles);
PHP_FUNCTION(bbs_getarticles);
PHP_FUNCTION(bbs_search_articles);

PHP_FUNCTION(bbs_get_article_gid);

#define PHP_BBS_ARTICLE_EXPORT_FUNCTIONS \
    PHP_FE(bbs2_record_is_unread, NULL) \
    PHP_FE(bbs_get_records_from_id, NULL) \
    PHP_FE(bbs_get_records_from_num, NULL) \
    PHP_FE(bbs_get_filename_from_num, NULL) \
    PHP_FE(bbs_get_threads_from_id, NULL) \
    PHP_FE(bbs_get_threads_from_gid, fifth_arg_force_ref_00011) \
    PHP_FE(bbs_countarticles, NULL) \
    PHP_FE(bbs_getarticles, NULL) \
    PHP_FE(bbs_search_articles, NULL) \
    PHP_FE(bbs_get_article_gid, NULL)

#endif //PHP_BBS_ARTICLE_H

