<?php
/*
Plugin Name: Global Author Comments Feed
Plugin URI: http://premium.wpmudev.org/project/recent-global-author-comments-feed
Description: Provides a global feed of comments from a single author made across multiple blogs on the one Multisite network.
Author: WPMU DEV
Author URI: http://premium.wpmudev.org/
Version: 1.0.3.3
Network: true
WDP ID: 88
*/

/*
Copyright 2007-2011 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Support for WPMU DEV Dashboard plugin
//include_once( dirname(__FILE__) . '/lib/dash-notices/wpmudev-dash-notification.php');

/**
 * Create author comments feed
 */
function recent_global_author_comments_feed() {
    global $wpdb, $current_site;

    $number = ( empty( $_GET['number'] ) ) ? '25' : intval($_GET['number']);
    $author = ( empty( $_GET['uid'] ) ) ? '0'  : intval($_GET['uid']);

    $query = $wpdb->prepare("SELECT * FROM " . $wpdb->base_prefix . "site_comments WHERE site_id = '" . $current_site->id . "' AND comment_author_user_id = %d AND blog_public = '1' AND comment_approved = '1' AND comment_type != 'pingback' ORDER BY comment_date_stamp DESC LIMIT %d", $author, $number);
    $comments = $wpdb->get_results( $query, ARRAY_A );

    if ( count( $comments ) > 0 ) {
        $last_published_post_date_time = $wpdb->get_var($wpdb->prepare("SELECT comment_date_gmt FROM " . $wpdb->base_prefix . "site_comments WHERE site_id = %d AND comment_author_user_id = %d AND blog_public = '1' AND comment_approved = '1' AND comment_type != 'pingback' ORDER BY comment_date_stamp DESC LIMIT 1", $current_site->id, $author));
    } else {
        $last_published_post_date_time = time();
    }

    if ( $author > 0 ) {
        $author_user_login = $wpdb->get_var($wpdb->prepare("SELECT user_login FROM " . $wpdb->base_prefix . "users WHERE ID = %d", $author));
    }

    header( 'HTTP/1.0 200 OK' );
    header( 'Content-Type: ' . feed_content_type('rss-http') . '; charset=' . get_option('blog_charset'), true );
    $more = 1;

    echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>'; ?>

    <rss version="2.0"
        xmlns:content="http://purl.org/rss/1.0/modules/content/"
        xmlns:wfw="http://wellformedweb.org/CommentAPI/"
        xmlns:dc="http://purl.org/dc/elements/1.1/"
        xmlns:atom="http://www.w3.org/2005/Atom"
        xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
        xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
        <?php do_action('rss2_ns'); ?>
    >

    <channel>
        <title><?php bloginfo_rss('name'); ?> <?php echo $author_user_login . ' '; ?><?php _e('Comments'); ?></title>
        <atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
        <link><?php bloginfo_rss('url') ?></link>
        <description><?php bloginfo_rss("description") ?></description>
        <pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', $last_published_post_date_time, false); ?></pubDate>
        <?php the_generator( 'rss2' ); ?>
        <language><?php bloginfo_rss( 'language' ); ?></language>
        <?php
        if ( count( $comments ) > 0 ) {
            foreach ($comments as $comment) {
				if (intval($comment['blog_id']) < 2)
					$table_name = $wpdb->base_prefix . "posts";
				else
					$table_name = $wpdb->base_prefix . $comment['blog_id'] . "_posts";

				$sql_str = $wpdb->prepare("SELECT post_title FROM " . $table_name ." WHERE ID = %d", $comment['comment_post_id']);

                $post_title = $wpdb->get_var( $sql_str );
                if ( !empty( $comment['comment_author_user_id'] ) && $comment['comment_author_user_id'] > 0 ) {
                    $author_display_name = $wpdb->get_var($wpdb->prepare("SELECT display_name FROM " . $wpdb->base_prefix . "users WHERE ID = %d", $comment['comment_author_user_id']));
                }
                if ( !empty( $author_user_login ) ) {
                    $comment_author = $author_display_name;
                } else {
                    $comment_author = $comment['comment_author_email'];
                }
                ?>
                <item>
                    <title><?php _e('Comments on'); ?>: <?php echo stripslashes( $post_title ); ?></title>
                    <link><?php echo $comment['comment_post_permalink']; ?>#comment-<?php echo $comment['comment_id']; ?></link>

                    <dc:creator><?php echo $comment['comment_author']; ?></dc:creator>
                    <pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', $comment['comment_date_gmt'], false); ?></pubDate>

                    <guid isPermaLink="false"><?php echo $comment['comment_post_permalink']; ?>#comment-<?php echo $comment['comment_id']; ?></guid>
                    <description><![CDATA[<?php echo stripslashes( strip_tags( $comment['comment_content'] ) ); ?>]]></description>
                </item>
                <?php
            }
        }
        ?>
    </channel>
    </rss>
    <?php
}
add_action( 'do_feed_recent-global-author-comments', 'recent_global_author_comments_feed' );

/**
 * Custom rewrite rules for the feed
 *
 * @param <type> $wp_rewrite
 * @return void
 */
function recent_global_author_comments_feed_rewrite( $wp_rewrite ) {
    $feed_rules = array(
        'feed/(.+)' => 'index.php?feed=' . $wp_rewrite->preg_index(1),
        '(.+).xml'  => 'index.php?feed='. $wp_rewrite->preg_index(1),
    );
    $wp_rewrite->rules = $feed_rules + $wp_rewrite->rules;
}
add_filter( 'generate_rewrite_rules', 'recent_global_author_comments_feed_rewrite' );