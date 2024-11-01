<?php
/*
Plugin Name:  Broadcast Live Video - HTML5 Live Streaming
Plugin URI: https://videowhisper.com/?p=WordPress+Live+Streaming
Description: <strong>Broadcast Live Video / HTML5 Live Streaming : HTML5, WebRTC, HLS, RTSP, RTMP</strong> solution powers a turnkey live streaming channels site including web based webcam broadcasting app and player with chat, support for external apps, 24/7 RTSP ip cameras, WebRTC, video playlist scheduler, video archiving VOD, HLS/MPEG-DASH delivery for mobile including AJAX chat, membership and access control, pay per view channels and tips/gifts for broadcasters. <a href='https://consult.videowhisper.com/?topic=Live-Streaming'>Contact Support</a> | <a href='admin.php?page=live-streaming&tab=setup'>Setup</a>
Version: 6.1.8
Author: VideoWhisper.com
Author URI: https://videowhisper.com/
Contributors: videowhisper, VideoWhisper.com, BroadcastLiveVideo.com
Text Domain: live-streaming
Requires PHP: 7.4
Text Domain: forum-qa-discussion-board
Domain Path: /languages/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once plugin_dir_path( __FILE__ ) . '/inc/options.php';
require_once plugin_dir_path( __FILE__ ) . '/inc/requirements.php';
require_once plugin_dir_path( __FILE__ ) . '/inc/iptv.php';
require_once plugin_dir_path( __FILE__ ) . '/inc/h5videochat.php';

use VideoWhisper\LiveStreaming;


if ( ! class_exists( 'VWliveStreaming' ) ) {
	class VWliveStreaming {

		use VideoWhisper\LiveStreaming\Options;
		use VideoWhisper\LiveStreaming\Requirements;
		use VideoWhisper\LiveStreaming\IPTV;
		use VideoWhisper\LiveStreaming\H5Videochat;

		public function __construct() {         }

		public function VWliveStreaming() {
			// constructor
			self::__construct();

		}

		static function install() {
			// do not generate any output here

			self::channel_post();
			flush_rewrite_rules();
		}

		static function settings_link( $links ) {
			$settings_link = '<a href="admin.php?page=live-streaming">' . __( 'Settings' ) . '</a>';
			array_unshift( $links, $settings_link );
			return $links;
		}

		function init() {
			// setup post
			self::channel_post();

			// prevent wp from adding <p> that breaks JS
			remove_filter( 'the_content', 'wpautop' );

			// move wpautop filter to BEFORE shortcode is processed
			add_filter( 'the_content', 'wpautop', 1 );

			// then clean AFTER shortcode
			add_filter( 'the_content', 'shortcode_unautop', 100 );

			self::setupSchedule();

		}


		function plugins_loaded() {
			// update user active

			// user access update (updates with 10s precision)
			if ( is_user_logged_in() ) {
				$ztime  = time();
				$userID = get_current_user_id();

				// this user's access time
				$accessTime = intval( get_user_meta( $userID, 'accessTime', true ) );
				if ( $ztime - $accessTime > 10 ) {
					update_user_meta( $userID, 'accessTime', $ztime );
				}

				// any user access time
				$userAccessTime = intval( get_option( 'userAccessTime', 0 ) );
				if ( $ztime - $accessTime > 10 ) {
					update_option( 'userAccessTime', $ztime );
				}
			}

			$plugin = plugin_basename( __FILE__ );
			add_filter( "plugin_action_links_$plugin", array( 'VWliveStreaming', 'settings_link' ) );

			// translations
			load_plugin_textdomain( 'live-streaming', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

			// widget
			//wp_register_sidebar_widget( 'liveStreamingWidget', 'VideoWhisper Streaming', array( 'VWliveStreaming', 'widget' ) );

			// channel page
			add_filter( 'the_title', array( 'VWliveStreaming', 'the_title' ) );
			add_filter( 'the_content', array( 'VWliveStreaming', 'channel_page' ) );
			add_filter( 'query_vars', array( 'VWliveStreaming', 'query_vars' ) );
			add_filter( 'pre_get_posts', array( 'VWliveStreaming', 'pre_get_posts' ) );

			// admin channels
			add_filter( 'manage_channel_posts_columns', array( 'VWliveStreaming', 'columns_head_channel' ), 10 );
			add_filter( 'manage_edit-channel_sortable_columns', array( 'VWliveStreaming', 'columns_register_sortable' ) );
			add_action( 'manage_channel_posts_custom_column', array( 'VWliveStreaming', 'columns_content_channel' ), 10, 2 );
			add_filter( 'request', array( 'VWliveStreaming', 'duration_column_orderby' ) );

			// shortcodes

			add_shortcode( 'videowhisper_h5vls_app', array( 'VWliveStreaming', 'videowhisper_h5vls_app' ) );

			add_shortcode( 'videowhisper_categories', array( 'VWliveStreaming', 'videowhisper_categories' ) );

			add_shortcode( 'videowhisper_channel_user', array( 'VWliveStreaming', 'videowhisper_channel_user' ) );

			add_shortcode( 'videowhisper_stream_setup', array( 'VWliveStreaming', 'videowhisper_stream_setup' ) );

			add_shortcode( 'videowhisper_broadcast', array( 'VWliveStreaming', 'videowhisper_broadcast' ) );

			add_shortcode( 'videowhisper_external', array( 'VWliveStreaming', 'videowhisper_external' ) );
			add_shortcode( 'videowhisper_external_broadcast', array( 'VWliveStreaming', 'videowhisper_external_broadcast' ) );
			add_shortcode( 'videowhisper_external_playback', array( 'VWliveStreaming', 'videowhisper_external_playback' ) );

			add_shortcode( 'videowhisper_watch', array( 'VWliveStreaming', 'videowhisper_watch' ) );
			add_shortcode( 'videowhisper_video', array( 'VWliveStreaming', 'videowhisper_video' ) );

			add_shortcode( 'videowhisper_hls', array( 'VWliveStreaming', 'videowhisper_hls' ) );
			add_shortcode( 'videowhisper_mpeg', array( 'VWliveStreaming', 'videowhisper_mpeg' ) );

			add_shortcode( 'videowhisper_channel_manage', array( 'VWliveStreaming', 'videowhisper_channel_manage' ) );
			add_shortcode( 'videowhisper_channels', array( 'VWliveStreaming', 'videowhisper_channels' ) );

			add_shortcode( 'videowhisper_webrtc_broadcast', array( 'VWliveStreaming', 'videowhisper_webrtc_broadcast' ) );
			add_shortcode( 'videowhisper_webrtc_playback', array( 'VWliveStreaming', 'videowhisper_webrtc_playback' ) );

			add_shortcode( 'videowhisper_htmlchat_playback', array( 'VWliveStreaming', 'videowhisper_htmlchat_playback' ) );

			add_action( 'before_delete_post', array( 'VWliveStreaming', 'before_delete_post' ) );

			// notify admin about requirements
			if ( current_user_can( 'administrator' ) ) {
				self::requirements_plugins_loaded();
			}

			// ajax

			//vws server
			add_action( 'wp_ajax_vwls_notify', array( 'VWliveStreaming', 'vwls_notify' ) );
			add_action( 'wp_ajax_nopriv_vwls_notify', array( 'VWliveStreaming', 'vwls_notify' ) );
			add_action( 'wp_ajax_vwls_stream', array( 'VWliveStreaming', 'vwls_stream' ) );
			add_action( 'wp_ajax_nopriv_vwls_stream', array( 'VWliveStreaming', 'vwls_stream' ) );

			//html5 video chat
			add_action( 'wp_ajax_h5vls_app', array( 'VWliveStreaming', 'h5vls_app' ) );
			add_action( 'wp_ajax_nopriv_h5vls_app', array( 'VWliveStreaming', 'h5vls_app' ) );

			// categories
			add_action( 'wp_ajax_vwls_categories', array( 'VWliveStreaming', 'vwls_categories' ) );
			add_action( 'wp_ajax_nopriv_vwls_categories', array( 'VWliveStreaming', 'vwls_categories' ) );

			// ip camera / re-stream setup
			add_action( 'wp_ajax_vwls_stream_setup', array( 'VWliveStreaming', 'vwls_stream_setup' ) );
			add_action( 'wp_ajax_nopriv_vwls_stream_setup', array( 'VWliveStreaming', 'vwls_stream_setup' ) );

			add_action( 'wp_ajax_vwls_playlist', array( 'VWliveStreaming', 'vwls_playlist' ) );
			add_action( 'wp_ajax_nopriv_vwls_playlist', array( 'VWliveStreaming', 'vwls_playlist' ) );

			add_action( 'wp_ajax_vwls_broadcast', array( 'VWliveStreaming', 'vwls_broadcast' ) );

			add_action( 'wp_ajax_vwls', array( 'VWliveStreaming', 'vwls_calls' ) );
			add_action( 'wp_ajax_nopriv_vwls', array( 'VWliveStreaming', 'vwls_calls' ) );

			add_action( 'wp_ajax_vwls_channels', array( 'VWliveStreaming', 'vwls_channels' ) );
			add_action( 'wp_ajax_nopriv_vwls_channels', array( 'VWliveStreaming', 'vwls_channels' ) );

			add_action( 'wp_ajax_vwls_htmlchat', array( 'VWliveStreaming', 'wp_ajax_vwls_htmlchat' ) );
			add_action( 'wp_ajax_nopriv_vwls_htmlchat', array( 'VWliveStreaming', 'wp_ajax_vwls_htmlchat' ) );

			// jquery for ajax
			add_action( 'wp_enqueue_scripts', array( 'VWliveStreaming', 'wp_enqueue_scripts' ) );

			// update page if not exists or deleted
			$page_id  = get_option( 'vwls_page_manage' );
			$page_id2 = get_option( 'vwls_page_channels' );

			// check db and update if necessary
			$vw_db_version = '2023.04.25.2';

			global $wpdb;
			$table_sessions = $wpdb->prefix . 'vw_sessions';
			$table_viewers  = $wpdb->prefix . 'vw_lwsessions';
			$table_channels = $wpdb->prefix . 'vw_lsrooms';

			$table_chatlog = $wpdb->prefix . 'vw_vwls_chatlog';

			$installed_ver = get_option( 'vwls_db_version' );

			if ( $installed_ver != $vw_db_version ) {

				// echo "---$installed_ver != $vw_db_version---";

				$wpdb->flush();

				$sql = "DROP TABLE IF EXISTS `$table_sessions`;
		CREATE TABLE `$table_sessions` (
		  `id` int(11) NOT NULL auto_increment,
		  `session` varchar(64) NOT NULL,
		  `username` varchar(64) NOT NULL,
		  `uid` int(11) NOT NULL,
		  `room` varchar(64) NOT NULL,
		  `rid` int(11) NOT NULL,
		  `message` text NOT NULL,
		  `sdate` int(11) NOT NULL,
		  `edate` int(11) NOT NULL,
		  `status` tinyint(4) NOT NULL,
		  `type` tinyint(4) NOT NULL,
		  `broadcaster` tinyint(4) DEFAULT 1,
		  `roptions` text NOT NULL,
		  `meta` text NOT NULL,
		  PRIMARY KEY  (`id`),
		  KEY `status` (`status`),
		  KEY `type` (`type`),
		  KEY `uid` (`uid`),
		  KEY `room` (`room`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Video Whisper: Broadcaster Sessions 2009-2023@videowhisper.com' AUTO_INCREMENT=1 ;

		DROP TABLE IF EXISTS `$table_viewers`;
		CREATE TABLE `$table_viewers` (
		  `id` int(11) NOT NULL auto_increment,
		  `session` varchar(64) NOT NULL,
		  `username` varchar(64) NOT NULL,
		  `uid` int(11) NOT NULL,
		  `room` varchar(64) NOT NULL,
		  `rid` int(11) NOT NULL,
		  `rsdate` int(11) NOT NULL,
		  `redate` int(11) NOT NULL,
		  `rmode` tinyint(4) NOT NULL,
		  `message` text NOT NULL,
		  `ip` text NOT NULL,
		  `sdate` int(11) NOT NULL,
		  `edate` int(11) NOT NULL,
		  `status` tinyint(4) NOT NULL,
		  `type` tinyint(4) NOT NULL,
		  `broadcaster` tinyint(4) DEFAULT 0,
		  `roptions` text NOT NULL,
		  `meta` text NOT NULL,
		  PRIMARY KEY  (`id`),
		  KEY `status` (`status`),
		  KEY `type` (`type`),
		  KEY `rid` (`rid`),
		  KEY `uid` (`uid`),
		  KEY `rmode` (`rmode`),
		  KEY `rsdate` (`rsdate`),
		  KEY `redate` (`redate`),
		  KEY `sdate` (`sdate`),
		  KEY `edate` (`edate`),
		  KEY `room` (`room`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Video Whisper: Sessions 2015-2023@videowhisper.com' AUTO_INCREMENT=1 ;


		DROP TABLE IF EXISTS `$table_channels`;
		CREATE TABLE `$table_channels` (
		  `id` int(11) NOT NULL auto_increment,
		  `name` varchar(64) NOT NULL,
		  `owner` int(11) NOT NULL,
		  `sdate` int(11) NOT NULL,
		  `edate` int(11) NOT NULL,
		  `btime` int(11) NOT NULL,
		  `wtime` int(11) NOT NULL,
		  `rdate` int(11) NOT NULL,
		  `status` tinyint(4) NOT NULL,
		  `type` tinyint(4) NOT NULL,
		  `options` TEXT,
		  PRIMARY KEY  (`id`),
		  KEY `name` (`name`),
		  KEY `status` (`status`),
		  KEY `type` (`type`),
		  KEY `owner` (`owner`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Video Whisper: Rooms - 2014@videowhisper.com' AUTO_INCREMENT=1 ;

		DROP TABLE IF EXISTS `$table_chatlog`;
		CREATE TABLE `$table_chatlog` (
		  `id` int(11) unsigned NOT NULL auto_increment,
		  `username` varchar(64) NOT NULL,
		  `room` varchar(64) NOT NULL,
		  `room_id` int(11) unsigned NOT NULL,
		  `message` text NOT NULL,
		  `mdate` int(11) NOT NULL,
		  `type` tinyint(4) NOT NULL,
		  `meta` TEXT,
		  `user_id` int(11) unsigned NOT NULL,
		  `private_uid` int(11) unsigned NOT NULL,
		  PRIMARY KEY  (`id`),
		  KEY `room` (`room`),
		  KEY `mdate` (`mdate`),
		  KEY `type` (`type`),
		  KEY `room_id` (`room_id`),
		  KEY `private_uid` (`private_uid`),
		  KEY `user_id` (`user_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Video Whisper: Chat Logs 2018-2023@videowhisper.com' AUTO_INCREMENT=1;

		";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );

				if ( ! $installed_ver ) {
					add_option( 'vwls_db_version', $vw_db_version );
				} else {
					update_option( 'vwls_db_version', $vw_db_version );
				}

				$wpdb->flush();
			}

		}
		/*
		function delTree($dir) {
			$files = array_diff(scandir($dir), array('.','..'));
			foreach ($files as $file) {
				(is_dir("$dir/$file")) ? VWliveStreaming::delTree("$dir/$file") : unlink("$dir/$file");
			}
			return rmdir($dir);
		}
		*/

		static function before_delete_post( $postID ) {
			$options = get_option( 'VWliveStreamingOptions' );
			if ( get_post_type( $postID ) != $options['custom_post'] ) {
				return;
			}

			$post = get_post( $postID );

			// delete from room table
			$room = sanitize_file_name( $post->post_title );

			global $wpdb;
			$table_channels = $wpdb->prefix . 'vw_lsrooms';
			$sql            = "DELETE FROM $table_channels where name='$room'";

			$wpdb->query( $sql );

		}



		static function login_headerurl( $url ) {

			return get_bloginfo( 'url' ) . '/';
		}


		static function login_enqueue_scripts() {

			$options = get_option( 'VWliveStreamingOptions' );

			if ( $options['loginLogo'] ) {
				?>
	<style type="text/css">
		 #login h1 a, .login h1 a  {
			background-image: url(<?php echo esc_url( $options['loginLogo'] ); ?>);
			background-size: 200px 68px;
			width: 200px;
			height: 68px;
		}
	</style>
				<?php
			}
		}



		// ! set fc

		// string contains any term for list (ie. banning)

		static function containsAny( $name, $list ) {
			$items = explode( ',', $list );
			foreach ( $items as $item ) {
				if ( stristr( $name, trim( $item ) ) ) {
					return $item;
				}
			}

				return 0;
		}


		// if any key matches any listing

		static function inList( $keys, $data ) {
			if ( ! $keys ) {
				return 0;
			}
			if ( ! $data ) {
				return 0;
			}
			if ( strtolower( trim( $data ) ) == 'all' ) {
				return 1;
			}
			if ( strtolower( trim( $data ) ) == 'none' ) {
				return 0;
			}

			$list = explode( ',', strtolower( trim( $data ) ) );
			if ( in_array( 'all', $list ) ) {
				return 1;
			}

			foreach ( $keys as $key ) {
				foreach ( $list as $listing ) {
					if ( strtolower( trim( $key ) ) == trim( $listing ) ) {
						return 1;
					}
				}
			}

					return 0;
		}

		// ! room fc
		static function roomURL( $room ) {

			$options = get_option( 'VWliveStreamingOptions' );

			if ( $options['channelUrl'] == 'post' ) {
				global $wpdb;

				$postID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name( $room ) . "' and post_type='channel' LIMIT 0,1" );

				if ( $postID ) {
					return get_post_permalink( $postID );
				}
			}

			if ( $options['channelUrl'] == 'full' ) {
				return site_url( '/fullchannel/' . urlencode( $room ) );
			}

			return plugin_dir_url( __FILE__ ) . 'ls/channel.php?n=' . urlencode( sanitize_file_name( $room ) );

		}


		static function count_user_posts_by_type( $userid, $post_type = 'channel' ) {
			global $wpdb;
			$where = get_posts_by_author_sql( $post_type, true, $userid );
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts $where" );
			return apply_filters( 'get_usernumposts', $count, $userid );
		}


		// ! Channel Validation


		static function fm( $t, $item = null ) {
					$img = '';

					if ( $item ) {
						$options       = get_option( 'VWliveStreamingOptions' );
						$dir           = $options['uploadsPath'] . '/_thumbs';
						$age           = self::format_age( time() - $item->edate );
						$thumbFilename = "$dir/" . $item->name . '.jpg';

						$noCache = '';
						if ( $age == 'LIVE' ) {
							$noCache = '?' . ( ( time() / 10 ) % 100 );
						}

						if ( file_exists( $thumbFilename ) ) {
							$img = '<IMG ALIGN="RIGHT" src="' . self::path2url( $thumbFilename ) . $noCache . '" width="' . $options['thumbWidth'] . 'px" height="' . $options['thumbHeight'] . 'px"><br style="clear:both">';
						}
					}

					// format message
					return '<div class="w-actionbox color_alternate">' . $t . $img . '</div><br>';
				}


		static function channelInvalid( $channel, $broadcast = false ) {
			// check if online channel is invalid for any reason

			$options = self::getOptions();

			if ( ! $channel ) {
				return self::fm( 'No channel name!' );
			}

			global $wpdb;
			$table_channels = $wpdb->prefix . 'vw_lsrooms';

			$sql      = "SELECT * FROM $table_channels where name='$channel'";
			$channelR = $wpdb->get_row( $sql );

			if ( ! $channelR ) {
				if ( $broadcast ) {
					return; // first broadcast
				} else {
					//get post
					$postID = $wpdb->get_var( $sql = 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_title = \'' . $channel . '\' and post_type=\'' . $options['custom_post'] . '\' LIMIT 0,1' );
					if ( $postID ) {
						$post = get_post( $postID );
						if ($post)
						{
							//insert if missing, for stats
							$ztime = time();
							$sql = "INSERT INTO `$table_channels` ( `owner`, `name`, `sdate`, `edate`, `rdate`,`status`, `type`) VALUES ('" . $post->post_author . "', '$channel', $ztime, $ztime, $ztime, 0, 1)";
							$wpdb->query( $sql );
						}
					}

					// return self::fm('Channel was not found! Live channel is only accessible after broadcast.', $channelR);
					// return; // always show
				}
			}

			if ($channelR)
			{

			if ( $channelR->type >= 2 ) {
				$poptions = self::channelOptions( $channelR->type, $options );

				$maximumBroadcastTime = 60 * $poptions['pBroadcastTime'];
				$maximumWatchTime     = 60 * $poptions['pWatchTime'];

				$canWatch  = $poptions['canWatchPremium'];
				$watchList = $poptions['watchListPremium'];
			} else {
				$maximumBroadcastTime = 60 * $options['broadcastTime'];
				$maximumWatchTime     = 60 * $options['watchTime'];

				$canWatch  = $options['canWatch'];
				$watchList = $options['watchList'];
			}

			if ( ! $broadcast ) {
				if ( $maximumWatchTime ) {
					if ( $channelR->wtime >= $maximumWatchTime ) {
						return self::fm( 'Channel watch time exceeded for current period! Higher broadcaster membership is required to stream more.', $channelR );
					}
				}
			} elseif ( $maximumBroadcastTime ) {
				if ( $channelR->btime >= $maximumBroadcastTime ) {
					return self::fm( 'Channel broadcast time exceeded for current period! Higher broadcaster membership is required to stream more.' );
				}
			}

		}
		else
		{
			$canWatch  = $options['canWatch'];
			$watchList = $options['watchList'];
		}

					// user access validation

					$current_user = wp_get_current_user();
					global $wp_roles;

			if ( $current_user->ID != 0 ) {
				// access keys
				$userkeys   = $current_user->roles;
				//also add role names
				foreach ( $current_user->roles as $role ) {
					$userkeys[] = $wp_roles->roles[ $role ]['name'];
				}
				$userkeys[] = $current_user->ID;
				$userkeys[] = $current_user->user_email;
				$userkeys[] = $current_user->user_login;
			} else {
				$userkeys[] = 'Guest';
			}


			// global access settings
			switch ( $canWatch ) {
				case 'members':
					if ( ! $current_user->ID ) {
						return self::fm( 'Only registered members can access this channel!' );
					}
					break;

				case 'list';
					if ( ! $current_user->ID || ! self::inList( $userkeys, $watchList ) ) {
						$keys = implode( ', ', $userkeys );
						return self::fm( 'Access restricted by global access list! (' . $keys . ')' );
					}
				break;
			}

			$postID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_title = '" . $channel . "' and post_type='channel' LIMIT 0,1" );

			if ( $postID ) {
				// accessPassword
				if ( post_password_required( $postID ) ) {
					return self::fm( 'Access to channel is restricted by password!' );
				}

				// channel access list
				$accessList = get_post_meta( $postID, 'vw_accessList', true );
				if ( $accessList ) {
					if ( ! self::inList( $userkeys, $accessList ) ) {
						return self::fm( 'Access restricted by channel access list!' );
					}
				}
					// playlist active or ip camera
					$playlistActive = get_post_meta( $postID, 'vw_playlistActive', true );
				$ipCamera           = get_post_meta( $postID, 'vw_ipCamera', true );
			}

			if ( ! $broadcast ) {
				if ( ! self::userPaidAccess( $current_user->ID, $postID ) ) {
					return self::fm( 'Access restricted: channel access needs to be purchased!' );
				}
			}

			if ( ! $broadcast ) {
				if ( ! $options['alwaysWatch'] ) {
					if ( ! $playlistActive && ! $ipCamera ) {
						if ( time() - $channelR->edate > 45 ) {
							$age = self::format_age( time() - $channelR->edate );

							$htmlCode = 'This channel is currently offline. ';

							$eventCode = self::eventInfo( $postID );

							if ( $eventCode ) {
								$eventCode = 'Come back and reload page when event starts!' . $eventCode;
							} else {
								$eventCode .= ' Try again later! Time offline: ' . $age;
							}

							return self::fm( $htmlCode . $eventCode, $channelR );
						}
					}
				}
			}

						// valid then
						return;

		}


		static function getCurrentURL() {

			global $wp;
			return home_url( add_query_arg( array(), $wp->request ) );
		}


		static function vsvVideoURL( $video_teaser, $options = null ) {
			if ( ! $video_teaser ) {
				return '';
			}

			if ( ! $options ) {
				$options = get_option( 'VWliveStreamingOptions' );
			}
			$streamPath = '';

			// use conversion if available
			$videoAdaptive = get_post_meta( $video_teaser, 'video-adaptive', true );
			if ( $videoAdaptive ) {
				$videoAlts = $videoAdaptive;
			} else {
				$videoAlts = array();
			}

			foreach ( array( 'high', 'mobile' ) as $frm ) {
				if ( array_key_exists( $frm, $videoAlts ) ) {
					if ( $alt = $videoAlts[ $frm ] ) {
						if ( file_exists( $alt['file'] ) ) {
							$ext = pathinfo( $alt['file'], PATHINFO_EXTENSION );
							if ( $options['hls_vod'] ?? false ) {
								$streamPath = self::path2stream( $alt['file'] );
							} else {
								$streamPath = self::path2url( $alt['file'] );
							}
							break;
						}
					}
				}
			};

				// user original
			if ( ! $streamPath ) {
				$videoPath = get_post_meta( $video_teaser, 'video-source-file', true );
				$ext       = pathinfo( $videoPath, PATHINFO_EXTENSION );

				if ( in_array( $ext, array( 'flv', 'mp4', 'm4v' ) ) ) {
					// use source if compatible
					if ( $options['hls_vod'] ) {
						$streamPath = self::path2stream( $videoPath );
					} else {
						$streamPath = self::path2url( $videoPath );
					}
				}
			}

			if ( $options['hls_vod'] ?? false ) {
				$streamURL = $options['hls_vod'] . '_definst_/' . $streamPath . '/manifest.mpd';
			} else {
				$streamURL = $streamPath;
			}

			return $streamURL;
		}

		// ! Shortcodes



		static function loginRequiredWarning() {
			return __( 'Login required: Please login first or register an account if you do not have one!', 'live-streaming' ) .
				'<BR><a class="ui button" href="' . wp_login_url() . '">' . __( 'Login', 'live-streaming' ) . '</a>  <a class="ui button" href="' . wp_registration_url() . '">' . __( 'Register', 'live-streaming' ) . '</a>';
		}

		static function videowhisper_channel_user() {
			// automatically creates a user channel (if missing) and displays broadcasting interface

			// can user create room?
			$options = get_option( 'VWliveStreamingOptions' );

			$canBroadcast  = $options['canBroadcast'];
			$broadcastList = $options['broadcastList'];
			$userName      = $options['userName'];
			if ( ! $userName ) {
				$userName = 'user_nicename';
			}

			$loggedin = 0;

			$current_user = wp_get_current_user();

			if ( $current_user->$userName ) {
				$username = $current_user->$userName;
			}

			// access keys
			$userkeys   = $current_user->roles;
			$userkeys[] = $current_user->user_login;
			$userkeys[] = $current_user->ID;
			$userkeys[] = $current_user->user_email;

			switch ( $canBroadcast ) {
				case 'members':
					if ( $username ) {
						$loggedin = 1;
					} else {
						$htmlCode .= self::loginRequiredWarning();
					}
					break;
				case 'list';
					if ( $username ) {
						if ( self::inList( $userkeys, $broadcastList ) ) {
							$loggedin = 1;
						} else {
							$htmlCode .= "<a href=\"/\">$username, you are not allowed to setup rooms.</a>";
						}
					} else {
						$htmlCode .= self::loginRequiredWarning();
					}
					break;
			}

			if ( ! $loggedin ) {
				$htmlCode .= '<p>' . __( 'This displays a broadcasting channel for registered members that have this feature enabled.', 'live-streaming' ) . '</p>';
				return $htmlCode;
			}

			// channel with same name as $username

			global $wpdb;
			$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE `post_title` = '$username' AND `post_type`='" . $options['custom_post'] . "' LIMIT 0,1" ); // same name

			if ( ! $postID ) {
				$post = array(
					'post_name'   => $username,
					'post_title'  => $username,
					'post_author' => $current_user->ID,
					'post_type'   => $options['custom_post'],
					'post_status' => 'publish',
				);

				$postID = wp_insert_post( $post );
			}

			$channel = get_post( $postID );
			if ( ! $channel ) {
				return "Error: Channel #$postID does not exist!";
			}

			if ( $channel->post_author != $current_user->ID ) {
				return 'Error: Channel ' . $channel->post_title . ' exists but belongs to different user ' . $channel->post_author . '!';
			}

			// display broadcasting channel

			return do_shortcode( '[videowhisper_broadcast channel="' . $channel->post_title . '"]' );

		}

		static function videowhisper_categories( $atts ) {

			$options = get_option( 'VWliveStreamingOptions' );

			$atts = shortcode_atts(
				array(
					'selected' => '',
				),
				$atts,
				'videowhisper_categories'
			);

			$htmlCode = '';

				$selected = intval( $atts['selected'] );
				$parent   = 0;

			if ( $options['subcategory'] == 'WordPress' ) {
				return wp_dropdown_categories( 'show_count=0&echo=0&class=ui+dropdown&name=newcategory&hide_empty=0&hierarchical=1&selected=' . $selected );
			}

			if ( $selected ) {
				$category = get_category( $selected );
				if ( $category ) {
					$parent = $category->parent;
				}

				if ( $category ) {
					$htmlCode .= '<div class="ui label">' . $category->name . '</div>';
				}
			}

			$htmlCode .= ' <div class="two fields">';

			$htmlCode .= '<div class="field">
<div id="mainCategoryDropdown" class="ui fluid search selection dropdown ajax">
  <input type="hidden" name="mainCategory">
  <i class="dropdown icon"></i>
  <div class="default text">Select Main Category</div>
  <div class="menu"></div>
 </div>
 </div>';

			$htmlCode .= '<div class="field">
 <div id="newcategory" class="ui fluid search selection dropdown ajax">
  <input type="hidden" name="newcategory">
  <i class="dropdown icon"></i>
  <div class="default text">Select Category</div>
  <div class="menu"></div>
 </div>
 </div>';

			$htmlCode .= '
 <a id="reloadCategories" class="ui icon button" data-tooltip="' . __( 'Reload', 'live-streaming' ) . '">
  <i class="redo icon"></i>
</a>';

			$htmlCode .= '</div>';

			$admin_ajax = admin_url() . 'admin-ajax.php?action=vwls_categories';

			$htmlCode .= <<<HTMLCODE
<script>

var mainCategoryValue = '$parent';

	jQuery(document).ready(function () {


function loadCategories()
{
jQuery.post( "$admin_ajax&parent=0", function( data )
	{

	 jQuery('#mainCategoryDropdown').dropdown({
		values: JSON.parse(data),
	  	onChange: function(value, text, choice)
	  	{

	mainCategoryValue = value;

	console.log('mainCategoryDropdown action', value, text, choice);
	jQuery.post( "$admin_ajax&sub=1&parent=" + value, function( data )
		{
		 jQuery('#newcategory').dropdown({values: JSON.parse(data)});
		 jQuery('#newcategory').dropdown('set selected', '$selected');
		});

		}
		//action
	  });

	jQuery('#mainCategoryDropdown').dropdown('set selected', '$parent');
	//post
	});
}

jQuery('#reloadCategories').click(
	function()
	{
		mainCategoryValue = '$parent';
		loadCategories();
	}
);


loadCategories();

//ready
});

</script>
HTMLCODE;

			return $htmlCode;
		}

		static function vwls_categories() {
			// list channels
			ob_clean();

			$options = get_option( 'VWliveStreamingOptions' );

			$parent = intval( $_GET['parent'] ?? 0 );
			$sub    = intval( $_GET['sub'] ?? 0 );

			$args = array(
				'orderby'    => 'name',
				'order'      => 'ASC',
				'hide_empty' => false,
				'parent'     => $parent,
			);

			$categories = get_categories( $args );

			$res = array();

			if ( ! $parent && $options['subcategory'] == 'all' && ! $sub ) {
				$res[] = array(
					'name'  => __( 'Main Categories', 'live-streaming' ),
					'value' => 0,
				);
			}

			if ( $parent && $options['subcategory'] == 'all' && $sub ) {
				$category = get_category( $parent );
				$res[]    = array(
					'name'  => $category->name . ' *',
					'value' => $parent,
				);
			}

			foreach ( $categories as $category ) {
				$subcategories = get_categories(
					array(
						'parent'     => $category->term_id,
						'hide_empty' => false,
					)
				);
				if ( $parent || count( $subcategories ) || $options['subcategory'] == 'all' ) {
					$res[] = array(
						'name'  => $category->name . ( $parent ? '' : ' (' . count( $subcategories ) . ')' ),
						'value' => $category->term_id,
					);
				}
			}

			echo json_encode( $res );

			exit;

		}





		static function videowhisper_channel_manage() {
			// can user create room?
			$options = get_option( 'VWliveStreamingOptions' );

			$maxChannels = $options['maxChannels'];

			$canBroadcast  = $options['canBroadcast'];
			$broadcastList = $options['broadcastList'];
			$userName      = $options['userName'];
			if ( ! $userName ) {
				$userName = 'user_nicename';
			}

			$loggedin = 0;
			$username = '_guest';

			$current_user = wp_get_current_user();

			if ( $current_user->$userName ) {
				$username = $current_user->$userName;
			}

			$htmlCode = '';

			// access keys
			$userkeys   = $current_user->roles;
			$userkeys[] = $current_user->user_login;
			$userkeys[] = $current_user->ID;
			$userkeys[] = $current_user->user_email;

			switch ( $canBroadcast ) {
				case 'members':
					if ( $current_user->ID ) {
						$loggedin = 1;
					} else {
						$htmlCode .= self::loginRequiredWarning();
					}
					break;
				case 'list';
					if ( $current_user->ID  ) {
						if ( self::inList( $userkeys, $broadcastList ) ) {
							$loggedin = 1;
						} else {
							$htmlCode .= "<a href=\"/\">$username, you are not allowed to setup rooms.</a>";
						}
					} else {
						$htmlCode .= self::loginRequiredWarning();
					}
					break;
			}

			if ( ! $loggedin ) {
				$htmlCode .= '<p>' . __( 'This pages allows creating and managing broadcasting channels for registered members that have this feature enabled.', 'live-streaming' ) . '</p>';
				return $htmlCode;
			}

			// premium options
			$poptions = self::premiumOptions( $userkeys, $options );
			if ( $poptions['pMaxChannels'] ?? false ) {
				$maxChannels = $poptions['pMaxChannels'];
			}

			$this_page      = self::getCurrentURL();
			$channels_count = self::count_user_posts_by_type( $current_user->ID, $options['custom_post'] );

			$deleteChannel = intval( $_GET['deleteChannel'] ?? 0 );
			if ( $deleteChannel ) {

				$post = get_post( $deleteChannel );

				$htmlCode .= '<div class="ui segment">';
				$htmlCode .= '<div class="ui header">' . $post->post_title . '</div>';

				if ( isset($_GET['confirmDelete']) && sanitize_text_field( $_GET['confirmDelete'] ) ) {
					wp_trash_post( $deleteChannel );
					$htmlCode .= __( 'Channel was removed. Admins may delete this content after review.', 'live-streaming' );
				} else {
					$htmlCode .= __( 'This will remove channel from public access. Only administrator can completely delete channels. Associated data may be needed for report review, moderation purposes.', 'live-streaming' );

					$htmlCode .= '<br><a class="ui button red" href="' . add_query_arg(
						array(
							'deleteChannel' => $deleteChannel,
							'confirmDelete' => '1',
						),
						$this_page
					) . '">' . __( 'Confirm Removal', 'live-streaming' ) . '</a>';
				}

				$htmlCode .= '</div>';

			}

			// ! save channel
			$postID = intval( $_POST['editPost'] ?? 0 ); // -1 for new

			if ( $postID ) {

				$name = sanitize_file_name( $_POST['newname'] );

				global $wpdb;
				$existID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE `ID` <> $postID AND `post_title` = '$name' AND `post_type`='" . $options['custom_post'] . "' LIMIT 0,1" ); // same name, diff than postID

				if ( $postID <= 0 && $channels_count >= $maxChannels ) {
					$htmlCode .= "<div class='error'>" . __( 'Could not create the new channel', 'live-streaming' ) . ': Maximum ' . $options['maxChannels'] . ' channels allowed per user!</div>';
				} elseif ( $existID ) {
					$htmlCode .= "<div class='error'>" . __( 'Could not create the new channel', 'live-streaming' ) . ": A channel post with name '" . $name . "' already exists. Please use a different channel name!</div>";
				} else {
					// $name = preg_replace("/[^\s\w]+/", '', $name);

					if ( isset( $_POST['ipCamera'] ) && sanitize_text_field( $_POST['ipCamera'] ) ) {
						if ( ! strstr( $name, '.stream' ) ) {
							$name .= '.stream';
						}
					}

						$comments = sanitize_file_name( $_POST['newcomments'] );

					// accessPassword
					$accessPassword = '';
					if ( self::inList( $userkeys, $options['accessPassword'] ) ) {
						$accessPassword = sanitize_text_field( $_POST['accessPassword'] );
					}

					$post = array(
						'post_content'   => sanitize_text_field( $_POST['description'] ),
						'post_name'      => $name,
						'post_title'     => $name,
						'post_author'    => $current_user->ID,
						'post_type'      => $options['custom_post'],
						'post_status'    => 'publish',
						'comment_status' => $comments,
						'post_password'  => $accessPassword,
					);

					$category = (int) $_POST['newcategory'];

					if ( $postID > 0 ) {
						$channel = get_post( $postID );
						if ( $channel->post_author == $current_user->ID ) {
							$post['ID'] = $postID; // update
						} else {
							return "<div class='error'>Not allowed!</div>";
						}
						$htmlCode .= "<div class='update'>Channel $name was updated!</div>";
					} else {
						$htmlCode .= "<div class='update'>Channel $name was created!</div>";
					}

					$postID = wp_insert_post( $post );
					if ( $postID ) {
						wp_set_post_categories( $postID, array( $category ) );
					}

					$channels_count = self::count_user_posts_by_type( $current_user->ID, $options['custom_post'] );

					// roomTags
					if ( self::inList( $userkeys, $options['roomTags'] ) ) {
						$roomTags = sanitize_text_field( $_POST['roomTags'] );
						wp_set_post_tags( $postID, $roomTags, false );
					}

					// uploadPicture
					if ( self::inList( $userkeys, $options['uploadPicture'] ) ) {

						if ( $filename =  $_FILES['uploadPicture']['tmp_name']  ) {

							$ext     = strtolower( pathinfo( sanitize_file_name( $_FILES['uploadPicture']['name'] ) , PATHINFO_EXTENSION ) );
							$allowed = array( 'jpg', 'jpeg', 'png', 'gif' );
							if ( ! in_array( $ext, $allowed ) ) {
								return 'Unsupported file extension!';
							}

							if (!file_exists($filename))
							{
								return 'File not found: ' . esc_html($filename) . '';
							}

							list($width, $height) = getimagesize( $filename );

							if ( $width && $height ) {

								// delete previous image(s)
								self::delete_associated_media( $postID, true );

								// $htmlCode .= 'Generating thumb... ';
								$thumbWidth  = $options['thumbWidth'] ;
								$thumbHeight = $options['thumbHeight'] ;

								$src = imagecreatefromstring( file_get_contents( $filename ) );
								$tmp = imagecreatetruecolor( $thumbWidth, $thumbHeight );

								$dir = $options['uploadsPath'] . '/_pictures' ;
								if ( ! file_exists( $dir ) ) {
									mkdir( $dir );
								}

								$room_name     = sanitize_file_name( $channel->post_title );
								$thumbFilename = "$dir/$room_name.jpg";
								imagecopyresampled( $tmp, $src, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height );
								imagejpeg( $tmp, $thumbFilename, 95 );

								// detect tiny images without info
								if ( filesize( $thumbFilename ) > 1000 ) {
									$picType = 1;
								} else {
									$picType = 2;
								}

								// update post meta
								if ( $postID ) {
									update_post_meta( $postID, 'hasPicture', $picType );
									update_post_meta( $postID, 'hasSnapshot', 1 ); // so it gets listed
									update_post_meta( $postID, 'edate', time() - 60 );
								}

								// $htmlCode .= ' Updating picture... ' . $thumbFilename;

								// update post image
								if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
									require ABSPATH . 'wp-admin/includes/image.php';
								}

								$wp_filetype = wp_check_filetype( basename( $thumbFilename ), null );

								$attachment = array(
									'guid'           => $thumbFilename,
									'post_mime_type' => $wp_filetype['type'],
									'post_title'     => $room_name,
									'post_content'   => '',
									'post_status'    => 'inherit',
								);

								$attach_id = wp_insert_attachment( $attachment, $thumbFilename, $postID );
								set_post_thumbnail( $postID, $attach_id );

								// update post imaga data
								$attach_data = wp_generate_attachment_metadata( $attach_id, $thumbFilename );
								wp_update_attachment_metadata( $attach_id, $attach_data );

							}
						}

						$showImage = sanitize_file_name( $_POST['showImage'] );
						update_post_meta( $postID, 'showImage', $showImage );

					}

					if ( self::inList( $userkeys, $options['eventDetails'] ) ) {
						update_post_meta( $postID, 'eventTitle', sanitize_text_field( $_POST['eventTitle'] ) );
						update_post_meta( $postID, 'eventStart', sanitize_text_field( $_POST['eventStart'] ) );
						update_post_meta( $postID, 'eventEnd', sanitize_text_field( $_POST['eventEnd'] ) );
						update_post_meta( $postID, 'eventStartTime', sanitize_text_field( $_POST['eventStartTime'] ) );
						update_post_meta( $postID, 'eventEndTime', sanitize_text_field( $_POST['eventEndTime'] ) );
						update_post_meta( $postID, 'eventDescription', sanitize_text_field( $_POST['eventDescription'] ) );
					}

					// disable sidebar for themes that support this
					update_post_meta( $postID, 'disableSidebar', true );

					// recording
					if ( self::inList( $userkeys, $options['recording'] ) ) {
						$value = intval( $_POST['recording'] );
						update_post_meta( $postID, 'vw_recording', $value );

					} else {
						update_post_meta( $postID, 'vw_recording', $options['recordingFFmpeg'] );
					}

					// transcode
					if ( self::inList( $userkeys, $options['transcode'] ) ) {
						update_post_meta( $postID, 'vw_transcode', '1' );
					} else {
						update_post_meta( $postID, 'vw_transcode', '0' );
					}

					// logoHide
					if ( self::inList( $userkeys, $options['logoHide'] ) ) {
						update_post_meta( $postID, 'vw_logo', 'hide' );
					} else {
						update_post_meta( $postID, 'vw_logo', 'global' );
					}

					// logoCustom
					if ( self::inList( $userkeys, $options['logoCustom'] ) ) {
						$logoImage = sanitize_text_field( $_POST['logoImage'] );
						update_post_meta( $postID, 'vw_logoImage', $logoImage );

						$logoLink = sanitize_text_field( $_POST['logoLink'] );
						update_post_meta( $postID, 'vw_logoLink', $logoLink );

						update_post_meta( $postID, 'vw_logo', 'custom' );
					}

					// adsHide
					if ( self::inList( $userkeys, $options['adsHide'] ) ) {
						update_post_meta( $postID, 'vw_ads', 'hide' );
					} else {
						update_post_meta( $postID, 'vw_ads', 'global' );
					}

					// adsCustom
					if ( self::inList( $userkeys, $options['adsCustom'] ) ) {
						$logoImage = sanitize_text_field( $_POST['adsServer'] );
						update_post_meta( $postID, 'vw_adsServer', $logoImage );

						update_post_meta( $postID, 'vw_ads', 'custom' );
					}

					// ipCameras
					if ( self::inList( $userkeys, $options['ipCameras'] ) ) {
						if ( file_exists( $options['streamsPath'] ) ) {
							$ipCamera = sanitize_text_field( $_POST['ipCamera'] );

							if ( $ipCamera ) {
								list($protocol) = explode( ':', $ipCamera );
								if ( ! in_array( $protocol, array( 'rtsp', 'udp', 'rtmp', 'rtmps', 'wowz', 'wowzs', 'http', 'https' ) ) ) {
									$htmlCode .= "<BR>Address format not supported ($protocol). Address should use one of these protocols: rtsp://, udp://, rtmp://, rtmps://, wowz://, wowzs://, http://, https:// .";
									$ipCamera  = '';

								}
							}

							if ( $ipCamera ) {
								if ( ! strstr( $name, '.stream' ) ) {
									$htmlCode .= '<BR>Channel name must end in .stream when re-streaming!';
									$ipCamera  = '';
								}
							}

							$file = $options['streamsPath'] . '/' . $name;

							if ( $ipCamera ) {

								$myfile = fopen( $file, 'w' );
								if ( $myfile ) {
									fwrite( $myfile, $ipCamera );
									fclose( $myfile );
									$htmlCode .= '<BR>Stream file created/updated:<br>' . $name . ' = ' . $ipCamera;
								} else {
									$htmlCode .= '<BR>Could not write file: ' . $file;
									$ipCamera  = '';
								}
							} else {
								if ( file_exists( $file ) ) {
									unlink( $file );
									$htmlCode .= '<BR>Stream file removed: ' . $file;
								}
							}

							update_post_meta( $postID, 'vw_ipCamera', trim($ipCamera) );
							if ( $ipCamera ) {
								update_post_meta( $postID, 'stream-protocol', $protocol );
								update_post_meta( $postID, 'stream-type', 'restream' );
								update_post_meta( $postID, 'stream-mode', 'stream' );
							}
						} else {
							$htmlCode .= '<BR>Stream file could not be setup. Streams folder not found: ' . $options['streamsPath'];
						}
					} else {
						update_post_meta( $postID, 'vw_ipCamera', '' );
					}

					// schedulePlaylists
					if ( ! $options['playlists'] || ! self::inList( $userkeys, $options['schedulePlaylists'] ) ) {
						update_post_meta( $postID, 'vw_playlistActive', '' );
					}

					// permission lists: access, chat, write, participants, private
					foreach ( array( 'access', 'chat', 'write', 'participants', 'privateChat' ) as $field ) {
						if ( self::inList( $userkeys, $options[ $field . 'List' ] ) ) {
							$value = sanitize_text_field( $_POST[ $field . 'List' ] );
							update_post_meta( $postID, 'vw_' . $field . 'List', $value );
						}
					}

					// accessPrice
					if ( self::inList( $userkeys, $options['accessPrice'] ) ) {
						$accessPrice = round( sanitize_text_field( $_POST['accessPrice'] ), 2 );
						update_post_meta( $postID, 'vw_accessPrice', $accessPrice );

						$mCa = array(
							'status'       => 'enabled',
							'price'        => $accessPrice,
							'button_label' => 'Buy Access Now', // default button label
							'expire'       => 0, // default no expire
						);

						if ( $options['mycred'] && $accessPrice ) {
							update_post_meta( $postID, 'myCRED_sell_content', $mCa );
						} else {
							delete_post_meta( $postID, 'myCRED_sell_content' );
						}
					}
				}
			}

			// ! Playlist Edit
			if ( $editPlaylist = intval( $_GET['editPlaylist'] ?? 0 ) ) {

				$channel = get_post( $editPlaylist );
				if ( ! $channel ) {
					return 'Channel not found!';
				}

				if ( $channel->post_author != $current_user->ID ) {
					return 'Access not permitted (different channel owner)!';
				}

				$stream = sanitize_file_name( $channel->post_title );

				wp_enqueue_script( 'jquery' );
				wp_enqueue_script( 'jquery-ui-core' );
				wp_enqueue_script( 'jquery-ui-widget' );
				wp_enqueue_script( 'jquery-ui-dialog' );

				// wp_enqueue_script( 'jquery-ui-datepicker');

				// css
				wp_enqueue_style( 'jtable-green', plugin_dir_url( __FILE__ ) . '/scripts/jtable/themes/lightcolor/green/jtable.min.css' );

				wp_enqueue_style( 'jtable-flick', plugin_dir_url( __FILE__ ) . '/scripts/jtable/themes/flick/jquery-ui.min.css' );

				// js
				wp_enqueue_script( 'jquery-ui-jtable', plugin_dir_url( __FILE__ ) . '/scripts/jtable/jquery.jtable.min.js', array( 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-dialog' ) );

				// wp_enqueue_script( 'jtable', plugin_dir_url(  __FILE__ ) . '/scripts/jtable/jquery.jtable.js', array('jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-dialog'));

				$ajaxurl = admin_url() . 'admin-ajax.php?action=vwls_playlist&channel=' . $editPlaylist;

				$htmlCode .= '<h3>Playlist Scheduler: ' . $channel->post_title . '</h3>';

				$currentDate = date( 'Y-m-j h:i:s' );

				if ( isset($_POST['updatePlaylist']) ) {
					update_post_meta( $editPlaylist, 'vw_playlistActive', $playlistActive = (int) $_POST['playlistActive'] );
					self::updatePlaylist( $stream, $playlistActive );
					update_post_meta( $editPlaylist, 'vw_playlistUpdated', time() );

					update_post_meta( $editPlaylist, 'stream-type', 'playlist' );
					update_post_meta( $editPlaylist, 'stream-protocol', 'rtmp' );
				}

				// playlistActive
				$value = get_post_meta( $editPlaylist, 'vw_playlistActive', true );

				$activeCode .= '<select id="playlistActive" name="playlistActive">';
				$activeCode .= '<option value="0" ' . ( ! $value ? 'selected' : '' ) . '>Inactive</option>';
				$activeCode .= '<option value="1" ' . ( $value ? 'selected' : '' ) . '>Active</option>';
				$activeCode .= '</select>';

				$value           = get_post_meta( $editPlaylist, 'vw_playlistUpdated', true );
				$playlistUpdated = date( 'Y-m-j h:i:s', (int) $value );

				$value          = get_post_meta( $editPlaylist, 'vw_playlistLoaded', true );
				$playlistLoaded = date( 'Y-m-j h:i:s', (int) $value );

				$playlistPage = add_query_arg( array( 'editPlaylist' => $editPlaylist ), $this_page );

				$videosImg = plugin_dir_url( __FILE__ ) . 'scripts/jtable/themes/lightcolor/edit.png';

				$channelURL = add_query_arg( array( 'flash-view' => '' ), get_permalink( $channel->ID ) );

				// ! jTable
				$htmlCode .= <<<HTMLCODE
<form method="post" action="$playlistPage" name="adminForm" class="w-actionbox">
Playlist Status: $activeCode
<input class="videowhisperButtonLS g-btn type_primary" type="submit" name="button" id="button" value="Update" />
<input type="hidden" name="updatePlaylist" id="updatePlaylist" value="$editPlaylist" />
<BR>After editing playlist contents, update it to apply changes. Last Updated: $playlistUpdated
<BR>Playlist is loaded with web application (on access) and reloaded if necessary when users access <a href='$channelURL'>watch interface</a> (last time reloaded:  $playlistLoaded).
</form>
<BR>
First create a Schedule (Add new record), then Edit Videos (Add new record under Videos):
	<div id="PlaylistTableContainer" style="width: 600px;"></div>
	<script type="text/javascript">

		jQuery(document).ready(function () {

		    //Prepare jTable
			jQuery('#PlaylistTableContainer').jtable({
				title: 'Playlist Contents for Channel',
				defaultSorting: 'Order ASC',
				toolbar: {hoverAnimation: false},
				actions: {
					listAction: '$ajaxurl&task=list',
					createAction: '$ajaxurl&task=create',
					updateAction: '$ajaxurl&task=update',
					deleteAction: '$ajaxurl&task=delete'
				},
				fields: {
					Id: {
						key: true,
						create: false,
						edit: false,
						list: false,
					},
					//CHILD TABLE DEFINITION
					Videos: {
                    title: 'Videos',
                    sorting: false,
                    edit: false,
                    create: false,
                    display: function (playlist) {
                        //Create an image that will be used to open child table
                        var vButton = jQuery('<IMG src="$videosImg" /><I>Edit Videos</I>');
                        //Open child table when user clicks the image
                        vButton.click(function () {
                            jQuery('#PlaylistTableContainer').jtable('openChildTable',
                                    vButton.closest('tr'),
                                    {
                                        title: 'Videos for Schedule ' + playlist.record.Scheduled,
                                        actions: {
                                            listAction: '$ajaxurl&task=videolist&item=' + playlist.record.Id,
                                            deleteAction: '$ajaxurl&task=videoremove&item=' + playlist.record.Id,
                                            updateAction: '$ajaxurl&task=videoupdate',
                                            createAction: '$ajaxurl&task=videoadd'
                                        },
                                        fields: {
                                            ItemId: {
                                                type: 'hidden',
                                                defaultValue: playlist.record.Id
                                            },
                                            Id: {
                                                key: true,
                                                create: false,
                                                edit: false,
                                                list: false
                                            },
											Video: {
												title: 'Video',
												options: '$ajaxurl&task=source',
												sorting: false
											},
											Start: {
												title: 'Start',
												defaultValue: '0',
											},
											Length: {
												title: 'Length',
												defaultValue: '-1',
											},
											Order: {
												title: 'Order',
												defaultValue: '0',
											},
	                                    }
                                    }, function (data) { //opened handler
                                        data.childTable.jtable('load');
                                    });
                        });
                        //Return image to show on the person row
                        return vButton;
                    }

                    },
					Scheduled: {
						title: 'Scheduled',
						defaultValue: '$currentDate',
						sorting: false
					},
					Repeat: {
						title: 'Repeat',
						type: 'checkbox',
						defaultValue: '0',
						values: { '0' : 'Disabled', '1' : 'Enabled' },
						sorting: false
					},
					Order: {
						title: 'Order',
						defaultValue: '0',
					}
				}
			});

			//Load item list from server
			jQuery('#PlaylistTableContainer').jtable('load');
		});
	</script>
	<STYLE>
	.ui-front
	{
		z-index: 1000;
	}
	</STYLE>

HTMLCODE;

				$htmlCode .= '<BR>Schedule playlist items as: Year-Month-Day Hours:Minutes:Seconds. In example, current server time: ' . date( 'Y-m-j h:i:s' );
				if ( date_default_timezone_get() ) {
					$htmlCode .= '<BR>If the schedule time is in the past, each video is loaded in order and immediately replaces the previous video for the stream. Repeat will cause that videos to repeat in loop. Scheduling must be based on server timezone: ' . date_default_timezone_get() . '<br />';
				}
			}

			// ! list channels
			if ( !isset($_GET['editChannel']) && !isset($_GET['editPlaylist']) && !isset($_GET['reStream']) && !isset($_GET['offlineVideo']) ) {

				$args = array(
					'author'         => $current_user->ID,
					'orderby'        => 'post_date',
					'order'          => 'DESC',
					'post_type'      => $options['custom_post'],
					'posts_per_page' => 20,
					'offset'         => 0,
				);

				$channels = get_posts( $args );

				$htmlCode .= apply_filters( 'vw_ls_manage_channels_head', '' );
				$htmlCode .= "<h3>My Channels ($channels_count/$maxChannels)</h3>";

				// New Buttons
				if ( $channels_count < $maxChannels ) {
					$htmlCode .= '<a href="' . add_query_arg( 'editChannel', -1, $this_page ) . '" class="ui primary button"> <i class="icon plus"></i> Setup New Channel</a>';

					if ( self::inList( $userkeys, $options['ipCameras'] ) ) {
						if ( $options['ipcams'] ) {
							$htmlCode .= '<a href="' . add_query_arg( 'reStream', -1, $this_page ) . '" class="ui primary button"> <i class="icon plus"></i> Setup IP Camera / Stream</a>';
						}

						if ( $options['iptv'] ) {
							$htmlCode .= '<a href="' . add_query_arg(
								array(
									'reStream' => '-1',
									'h'        => 'iptv',
								),
								$this_page
							) . '" class="ui primary button"> <i class="icon plus"></i> Setup IPTV Stream</a>';
						}
					}
				}

				if ( count( $channels ) ) {

					// thumb
					require_once ABSPATH . 'wp-admin/includes/image.php';

					// is_plugin_active
					include_once ABSPATH . 'wp-admin/includes/plugin.php';

					global $wpdb;
					$table_channels = $wpdb->prefix . 'vw_lsrooms';

					$htmlCode .= '<table style="overflow:auto;">';

					foreach ( $channels as $channel ) {
						$postID = $channel->ID;

						$stream = sanitize_file_name( get_the_title( $postID ) );

						// update room
						// setup/update channel, premium & time reset

						$room  = $stream;
						$ztime = time();

						if ( $poptions ) {
							$rtype                = 1 + $poptions['level'];
							$maximumBroadcastTime = 60 * $poptions['pBroadcastTime'];
							$maximumWatchTime     = 60 * $poptions['pWatchTime'];

							// $camBandwidth=$options['pCamBandwidth'];
							// $camMaxBandwidth=$options['pCamMaxBandwidth'];
							// if (!$options['pLogo']) $options['overLogo']=$options['overLink']='';

						} else {
							$rtype = 1;
							// $camBandwidth=$options['camBandwidth'];
							// $camMaxBandwidth=$options['camMaxBandwidth'];

							$maximumBroadcastTime = 60 * $options['broadcastTime'];
							$maximumWatchTime     = 60 * $options['watchTime'];
						}

						$sql      = "SELECT * FROM $table_channels where name='$room'";
						$channelR = $wpdb->get_row( $sql );

						if ( ! $channelR ) {
							$sql = "INSERT INTO `$table_channels` ( `owner`, `name`, `sdate`, `edate`, `rdate`,`status`, `type`) VALUES ('" . $current_user->ID . "', '$room', $ztime, $ztime, $ztime, 0, $rtype)";
						} elseif ( $options['timeReset'] && $channelR->rdate < $ztime - $options['timeReset'] * 24 * 3600 ) { // time to reset in days
							$sql = "UPDATE `$table_channels` set type=$rtype, rdate=$ztime, wtime=0, btime=0 where name='$room'";
						} else {
							$sql = "UPDATE `$table_channels` set type=$rtype where name='$room'";
						}

						$wpdb->query( $sql );

						if ( $stream ) {
							if ( self::timeTo( $stream . '/updateThumb', 300, $options ) ) {
								// update thumb
								$dir           = $options['uploadsPath'] . '/_snapshots';
								$thumbFilename = "$dir/$stream.jpg";

								// ip camera or playlist : update snapshot
								if ( get_post_meta( $postID, 'vw_ipCamera', true ) || get_post_meta( $postID, 'vw_playlistActive', true ) ) {
									self::streamSnapshot( $stream, true, $postID );
									// $htmlCode .= 'Updating IP Cam Snapshot: ' . $stream;
								}

								// only if snapshot exists but missing post thumb (not uploaded or generated previously)
								if ( file_exists( $thumbFilename ) && ! get_post_thumbnail_id( $postID ) ) {
									if ( ! get_post_thumbnail_id( $postID ) ) {
										$wp_filetype = wp_check_filetype( basename( $thumbFilename ), null );

										$attachment = array(
											'guid'         => $thumbFilename,
											'post_mime_type' => $wp_filetype['type'],
											'post_title'   => preg_replace( '/\.[^.]+$/', '', basename( $thumbFilename, '.jpg' ) ),
											'post_content' => '',
											'post_status'  => 'inherit',
										);

										$attach_id = wp_insert_attachment( $attachment, $thumbFilename, $postID );
										set_post_thumbnail( $postID, $attach_id );
									} else // update
									{
										$attach_id     = get_post_thumbnail_id( $postID );
										$thumbFilename = get_attached_file( $attach_id );
									}

									// cleanup any relics
									if ( $postID && $attach_id ) {
										self::delete_associated_media( $postID, false, $attach_id );
									}

									// update
									$attach_data = wp_generate_attachment_metadata( $attach_id, $thumbFilename );
									wp_update_attachment_metadata( $attach_id, $attach_data );
								}
							}
						}

						// thumb
						$dir           = $options['uploadsPath'] . '/_thumbs';
						$thumbFilename = "$dir/$stream.jpg";

						$showImage = get_post_meta( $postID, 'showImage', true );

						if ( ! file_exists( $thumbFilename ) || $showImage == 'all' ) {
							$attach_id = get_post_thumbnail_id( $postID );
							if ( $attach_id ) {
								$thumbFilename = get_attached_file( $attach_id );
							}
						}

						$noCache = '';
						if ( isset($age) && $age == 'LIVE' ) {
							$noCache = '?' . ( ( time() / 10 ) % 100 );
						}
						if ( file_exists( $thumbFilename ) && ! strstr( $thumbFilename, '/.jpg' ) ) {
							$thumbCode = '<IMG src="' . self::path2url( $thumbFilename ) . $noCache . '" width="' . $options['thumbWidth'] . 'px" height="' . $options['thumbHeight'] . 'px">';
						} else {
							$thumbCode = '<IMG SRC="' . plugin_dir_url( __FILE__ ) . 'screenshot-3.jpg" width="' . $options['thumbWidth'] . 'px" height="' . $options['thumbHeight'] . 'px">';
						}

						// channel url
						$url = get_permalink( $postID );

						// display channel management
						$htmlCode .= '<tr><td><a href="' . $url . '"><h4>' . $channel->post_title . '</h4><div class="ui bordered rounded image">' . $thumbCode . '</div></a>';

						// Features Info

						// transcode quick update (if settigns changed for role)
						$vw_transcode = get_post_meta( $postID, 'vw_transcode', true );
						if ( self::inList( $userkeys, $options['transcode'] ) ) {
							$new_vw_transcode = 1;
						} else {
							$new_vw_transcode = 0;
						}
						if ( $vw_transcode != $new_vw_transcode ) {
							update_post_meta( $postID, 'vw_transcode', $new_vw_transcode );
						}

						// info under channel snapshot
						$htmlCode .= '
<div class="ui ' . $options['interfaceClass'] . ' message">';

						$edate     = intval( get_post_meta( $postID, 'edate', true ) );
						$htmlCode .= '<br> ' . __( 'Last Broadcast', 'live-streaming' ) . ': ' . ( $edate ? date( DATE_RFC2822, $edate ) : 'Never.' );

						$periodCode = '';
						if ( $options['timeReset'] ) {
							$periodCode = ' ' . sprintf( __( 'each %d days', 'live-streaming' ), $options['timeReset'] );
						}

						if ( $channelR ) {
							$htmlCode .= '<br>' . __( 'Total Broadcast Time', 'live-streaming' ) . ': ' . self::format_time( $channelR->btime ) . ' / ' . ( $maximumBroadcastTime ? self::format_time( $maximumBroadcastTime ) . $periodCode : __( 'Unlimited', 'live-streaming' ) ) . '<br>' . __( 'Total Watch Time', 'live-streaming' ) . ': ' . self::format_time( $channelR->wtime ) . ' / ' . ( $maximumWatchTime ? self::format_time( $maximumWatchTime ) . $periodCode : __( 'Unlimited', 'live-streaming' ) );
						}

						$htmlCode .= '<br> ' . __( 'Level', 'live-treaming' ) . ': ' . ( isset($channelR) && ( $channelR->type) > 1 ? 'Premium ' . ( $channelR->type - 1 ) : __( 'Regular', 'live-streaming' )  );

						if ( self::inList( $userkeys, $options['recording'] ) ) {
							$htmlCode .= '<br> ' . __( 'Recording', 'live-streaming' ) . ': ' . ( get_post_meta( $postID, 'vw_recording', true ) ? 'Enabled' : 'Disabled' );
						}

						if ( $options['transcoding'] ?? false) {
							$htmlCode .= '<br>'  . __( 'Transcoding', 'live-streaming' ) . ': ' . ( $vw_transcode ? 'Enabled' : 'Disabled' );
						}

						$htmlCode .= '<br> ' . __( 'Logo', 'live-streaming' ) . ': ' . get_post_meta( $postID, 'vw_logo', true );
						$htmlCode .= '<br> ' . __( 'Ads', 'live-streaming' ) . ': ' . get_post_meta( $postID, 'vw_ads', true );

						$htmlCode .= '<br>' . __( 'Stream Type', 'live-streaming' ) . ': ' . get_post_meta( $postID, 'stream-type', true );

						if ( $ipcam = get_post_meta( $postID, 'vw_ipCamera', true ) ) {
							$htmlCode .= '<br>' . __( 'IP Camera', 'live-streaming' );
						}

						if ( get_post_meta( $postID, 'restreamPaused', true ) ) {
							$htmlCode .= '<br>' . __( 'ReStream Paused', 'live-streaming' );
						}

						if ( get_post_meta( $postID, 'vw_playlistActive', true ) ) {
							$htmlCode .= '<br>' . __( 'Playlist Scheduled', 'live-streaming' );
						}

						foreach ( array( 'access', 'chat', 'write', 'participants', 'privateChat' ) as $field ) {
							if ( $value = get_post_meta( $postID, 'vw_' . $field . 'List', true ) ) {
								$htmlCode .= '<br>' . ucwords( $field ) . ': ' . $value;
							}
						}
							$htmlCode .= '</div>';

						$htmlCode .= '<div>' . apply_filters( 'vw_ls_manage_channel', '', $channel->ID ) . '</div>';

						$htmlCode .= '</td>';
						$htmlCode .= '<td width="210px; text-align=left">';

						// semantic ui
						self::enqueueUI();

						$htmlCode .= '
<div class="ui ' . $options['interfaceClass'] . ' vertical menu">
      <a class="item" href="' . add_query_arg( 'editChannel', $channel->ID, $this_page ) . '">' . __( 'Setup', 'live-streaming' ) . '</a>
      <a class="item" href="' . add_query_arg( 'deleteChannel', $channel->ID, $this_page ) . '">' . __( 'Delete', 'live-streaming' ) . '</a>
</div>

<div class="ui ' . $options['interfaceClass'] . ' vertical menu">
 <div class="item header">' . __( 'Broadcast', 'live-streaming' ) . '</div>';

if ($options['html5videochat']) $htmlCode .= '<a class="item green" href="' . get_permalink( $channel->ID ) . '">HTML5 Videochat</a>';
else
{
						if ( ! $options['webrtc'] ) {
							$htmlCode .= '
      <a class="ui red item" href="' . add_query_arg( array( 'broadcast' => '' ), get_permalink( $channel->ID ) ) . '">' . __( 'Web Broadcast', 'live-streaming' ) . '</a>
      ';
						}
						if ( $options['webrtc'] ) {
							$htmlCode .= '
      <a class="item" href="' . add_query_arg( array( 'webrtc-broadcast' => '' ), get_permalink( $channel->ID ) ) . '">' . __( 'WebRTC (HTML5)', 'live-streaming' ) . '</a>';

					if ( $options['playlists'] ) {
							if ( self::inList( $userkeys, $options['schedulePlaylists'] ) ) {
								$htmlCode .= '
      <a class="item" href="' . add_query_arg( 'editPlaylist', $channel->ID, $this_page ) . '">' . __( 'Playlist', 'live-streaming' ) . '</a>';
							}
											}
}

	}

						if (  $options['webrtcServer'] ) {
							$htmlCode .= '
      <a class="item" href="' . add_query_arg( array( 'external-broadcast' => '' ), get_permalink( $channel->ID ) ) . '">' . __( 'External Encoders (Apps)', 'live-streaming' ) . '</a>';
						}

						if ( self::inList( $userkeys, $options['ipCameras'] ) ) {
							if ( $options['ipcams'] ) {
								$htmlCode .= '<a href="' . add_query_arg( 'reStream', $channel->ID, $this_page ) . '" class="item">' . __( 'IP Cam / Stream', 'live-streaming' ) . '</a>';
							}
							if ( $options['iptv'] ) {
								$htmlCode .= '<a href="' . add_query_arg(
									array(
										'reStream' => $channel->ID,
										'h'        => 'iptv',
									),
									$this_page
								) . '" class=item">' . __( 'IPTV / Pull', 'live-streaming' ) . '</a>';
							}
						}

						if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'video-share-vod/video-share-vod.php' ) ) {
							$htmlCode .= '<a class="item" href="' . add_query_arg( 'offlineVideo', $channel->ID, $this_page ) . '">' . __( 'Offline Video', 'live-streaming' ) . '</a>';
						}

						$htmlCode .= '
</div>

<div class="ui ' . $options['interfaceClass'] . ' vertical menu">
 <div class="item header">' . __( 'Playback', 'live-streaming' ) . '</div>';

if ($options['html5videochat']) $htmlCode .= '<a class="item green" href="' . get_permalink( $channel->ID ) . '">HTML5 Videochat</a>';
else
{

						if ( ! $options['transcoding'] && ! $options['webrtc'] ) {
							$htmlCode .= '
	  <a class="item green" href="' . get_permalink( $channel->ID ) . '">Web View</a>';
						}

						if ( $options['transcoding'] || $options['webrtc'] ) {
							$htmlCode .= '
      <a class="item" href="' . add_query_arg( array( 'html5-view' => '' ), get_permalink( $channel->ID ) ) . '">' . __( 'Web View', 'live-streaming' ) . '</a>';
						}

}
						if (!$options['html5videochat']) $htmlCode .= '
      <a class="item" href="' . add_query_arg( array( 'video' => '' ), get_permalink( $channel->ID ) ) . '">' . __( 'Only Video', 'live-streaming' ) . '</a> ';

						if ( ! $options['transcoding'] && ! $options['webrtc'] ) {
							$htmlCode .= '';
						}

						if ( $options['transcoding'] || $ipcam ) {
							if ( self::inList( $userkeys, $options['transcode'] ) ) {
								$htmlCode .= '
      <a class="item" href="' . add_query_arg( array( 'hls' => '' ), get_permalink( $channel->ID ) ) . '">' . __( 'Video HLS (iOS/Safari)', 'live-streaming' ) . '</a>
      <a class="item" href="' . add_query_arg( array( 'mpeg' => '' ), get_permalink( $channel->ID ) ) . '">' . __( 'MPEG DASH (Android/Chrome)', 'live-streaming' ) . '</a>';
							}
						}

						if ( $options['webrtc'] ) {
							$htmlCode .= '
      <a class="item" href="' . add_query_arg( array( 'webrtc-playback' => '' ), get_permalink( $channel->ID ) ) . '">' . __( 'Video WebRTC (HTML5)', 'live-streaming' ) . '</a>';
						}

						if ( $options['webrtcServer'] == 'wowza' && !$options['html5videochat'] ) {
							$htmlCode .= '
      <a class="item" href="' . add_query_arg( array( 'external-playback' => '' ), get_permalink( $channel->ID ) ) . '">' . __( 'Other Players, Embed', 'live-streaming' ) . '</a>';
						}

							$htmlCode .= '
</div>

<style>
.ui > .item {
  display: block !important;
}
</style>
';

						/*
						$htmlCode .= '<BR><BR><a class="videowhisperButtonLS g-btn type_red" href="' . add_query_arg(array('broadcast'=>''), get_permalink($channel->ID)) . '">Broadcast</a>';
						if ($options['webrtc']) $htmlCode .= '<BR> <a class="videowhisperButtonLS g-btn type_red" href="' . add_query_arg(array('webrtc-broadcast'=>''), get_permalink($channel->ID)) . '">WebRTC Broadcast</a>';

						if ($options['externalKeys']) $htmlCode .= '<BR> <a class="videowhisperButtonLS g-btn type_pink" href="' . add_query_arg(array('external'=>''), get_permalink($channel->ID)) . '">External Apps</a>';
						$htmlCode .= '<BR> <a class="videowhisperButtonLS g-btn type_green" href="' . get_permalink($channel->ID) . '">Chat &amp; Video</a>';
						$htmlCode .= '<BR> <a class="videowhisperButtonLS g-btn type_green" href="' . add_query_arg(array('video'=>''), get_permalink($channel->ID)) . '">Video Only</a>';
						if ($options['webrtc']) $htmlCode .= '<BR> <a class="videowhisperButtonLS g-btn type_green" href="' . add_query_arg(array('webrtc-playback'=>''), get_permalink($channel->ID)) . '">WebRTC Playback</a>';


						$htmlCode .= '<BR> <a class="videowhisperButtonLS g-btn type_yellow" href="' . add_query_arg( 'editChannel', $channel->ID, $this_page) . '">Setup</a>';

						if ($options['playlists'])
							if (VWliveStreaming::inList($userkeys, $options['schedulePlaylists']))
								$htmlCode .= '<BR> <a class="videowhisperButtonLS g-btn type_yellow" href="' . add_query_arg( 'editPlaylist', $channel->ID, $this_page) . '">Playlist</a>';
						*/

						$htmlCode .= '</td></tr>';
						// filter under channel

					}
					$htmlCode .= '</table>';

				} else {
					$htmlCode .= "<div class='warning'>You don't have any channels, yet!</div>";
				}

				$htmlCode .= apply_filters( 'vw_ls_manage_channels_foot', '' );
			}

			// offlineVideo
			$offlineVideo = intval( $_GET['offlineVideo'] ?? 0 );

			if ( isset( $_GET['assignVideo'] ) && $_GET['assignVideo'] == 'offline_video' ) {
				$offlineVideo = intval( $_GET['postID'] ?? 0 );
			}

			if ( $offlineVideo ) {

				if ( shortcode_exists( 'videowhisper_postvideo_assign' ) ) {
							$htmlCode .= '<div class="ui ' . $options['interfaceClass'] . ' segment">';

							$htmlCode .= '<h3 class="ui header">' . __( 'Offline Video', 'ppv-live-webcams' ) . ' #' . $offlineVideo . '</H3>';
							$htmlCode .= do_shortcode( "[videowhisper_postvideo_assign post_id=\"$offlineVideo\" meta=\"offline_video\"]" );

							$htmlCode .= '<p>' . __( 'Offline video plays in html5 player when channel is offline.', 'ppv-live-webcams' ) . '</p</div>';

				}

				// c

				// if ($offline_video) $addCode .= '<div class="item"><h3 class="ui ' . $options['interfaceClass'] . ' header">' . __('Current Offline Video', 'ppv-live-webcams') . '</h3> <div class="ui ' . $options['interfaceClass'] .' segment" style="min-width:320px">' . do_shortcode('[videowhisper_player video="' .$offline_video. '"]' . '</div></div>');
			}

			// ! Setup IP Camera / Re-Stream
			$reStream = intval( $_GET['reStream'] ?? 0);

			if ( $reStream ) {
				$htmlCode .= do_shortcode( '[videowhisper_stream_setup channel_id="' . $reStream . '"]' );
			}
			// ! Edit Channel Form

			// setup
			$editPost = intval( $_GET['editChannel'] ?? 0 );

			if ( $editPost ) {

				$newCat = -1;

				if ( $editPost > 0 ) {
					$channel = get_post( $editPost );
					if ( $channel->post_author != $current_user->ID ) {
						return "<div class='ui error segment'>Not allowed (different owner)!</div>";
					}

					$newDescription = $channel->post_content;
					$newName        = $channel->post_title;
					$newComments    = $channel->comment_status;

					$cats = wp_get_post_categories( $editPost );
					if ( count( $cats ) ) {
						$newCat = array_pop( $cats );
					}
				}
				else
				{
					$newComments = 'closed';
					$newDescription = '';
				}

				if ( $editPost < 1 ) {
					$editPost = -1;

					$newTitle = 'New';

					$newName = sanitize_file_name( $username );
					if ( $channels_count ) {
						$newName .= '_' . base_convert( time() - 1225000000, 10, 36 );
					}
					$nameField = 'text';
					$newNameL  = '';
				} else {
					$nameField = 'hidden';
					$newNameL  = $newName;
					$channel = get_post( $editPost );
					if ($channel) $newTitle = $channel->post_title; else $newTitle = 'ERROR - Not Found';
				}

				// semantic ui
				self::enqueueUI();

				$commentsCode  = '';
				$commentsCode .= '<select class="ui dropdown" id="newcomments" name="newcomments">';
				$commentsCode .= '<option value="closed" ' . ( $newComments == 'closed' ? 'selected' : '' ) . '>' . __( 'Closed', 'live-streaming' ) . '</option>';
				$commentsCode .= '<option value="open" ' . ( $newComments == 'open' ? 'selected' : '' ) . '>' . __( 'Open', 'live-streaming' ) . '</option>';
				$commentsCode .= '</select>';

				$categories = do_shortcode( '[videowhisper_categories selected="' . $newCat . '"]' );

				// $categories = wp_dropdown_categories('show_count=0&echo=0&class=ui+dropdown&name=newcategory&hide_empty=0&hierarchical=1&selected=' . $newCat);

				// ! channel features
				$extraRows = '';

				// roomTags
				if ( self::inList( $userkeys, $options['roomTags'] ) ) {
					if ( $editPost ) {
						$tags = wp_get_post_tags( $editPost, array( 'fields' => 'names' ) );
					}
					// var_dump($tags);
					$value = '';

					if ( ! empty( $tags ) ) {
						if ( ! is_wp_error( $tags ) ) {
							foreach ( $tags as $tag ) {
								$value .= ( $value ? ', ' : '' ) . $tag;
							}
						}
					}

							$extraRows .= '<tr><td>' . __( 'Tags', 'live-streaming' ) . '</td><td><textarea rows=2 cols="80" name="roomTags" id="roomTags">' . $value . '</textarea><BR>' . __( 'Tags separated by comma.', 'live-streaming' ) . '</td></tr>';
				}

				// accessPassword
				if ( self::inList( $userkeys, $options['accessPassword'] ?? '' ) ) {
					if ( $editPost && isset($channel) ) {
						$value = $channel->post_password;
					} else {
						$value = '';
					}

					$extraRows .= '<tr><td>' . __( 'Access Password', 'live-streaming' ) . '</td><td><input size=16 name="accessPassword" id="accessPassword" value="' . $value . '"><BR>' . __( 'Password to protect channel. Leave blank to not require password.', 'live-streaming' ) . '</td></tr>';
				}

				// permission lists
				$permInfo = array(
					'access'       => __( 'Can access channel.', 'live-streaming' ),
					'chat'         => __( 'Can view public chat.', 'live-streaming' ),
					'write'        => __( 'Can write in public chat.', 'live-streaming' ),
					'participants' => __( 'Can view participants list.', 'live-streaming' ),
					'privateChat'  => __( 'Can initiate private chat with users from participants list.', 'live-streaming' ),
				);

				foreach ( array( 'access', 'chat', 'write', 'participants', 'privateChat' ) as $field ) {
					if ( self::inList( $userkeys, $options[ $field . 'List' ] ) ) {
						if ( $editPost ) {
							$value = get_post_meta( $editPost, 'vw_' . $field . 'List', true );
						} else {
							$value = '';
						}

						$extraRows .= '<tr><td>' . ucwords( $field ) . ' List</td><td><textarea rows=2 cols=60 name="' . $field . 'List" id="' . $field . 'List">' . $value . '</textarea><BR>' . $permInfo[ $field ] . ' ' . __( 'Define user list as roles, logins, emails separated by comma. Leave empty to allow everybody or set None to disable.', 'live-streaming' ) . '</td></tr>';
					}
				}

				// recording
				if ( self::inList( $userkeys, $options['recording'] ) ) {
					if ( $editPost > 0 ) {
						$value = get_post_meta( $editPost, 'vw_recording', true );
					} else {
						$value = $options['recordingFFmpeg'];
					}

					$extraRows .= '<tr><td>' . __( 'Recording', 'live-streaming' ) . '</td><td><select class="ui dropdown" name="recording" id="recording">';
					$extraRows .= '<option value="0" ' . ( $value == '0' ? 'selected' : '' ) . '>' . __( 'Disabled', 'live-streaming' ) . '</option>';
					$extraRows .= '<option value="1" ' . ( $value == '1' ? 'selected' : '' ) . '>' . __( 'Enabled', 'live-streaming' ) . '</option>';
					$extraRows .= '</select><BR>' . __( 'Record broadcast for this channel on server.', 'live-streaming' ) . '</td></tr>';
				}

				// accessPrice
				if ( self::inList( $userkeys, $options['accessPrice'] ) ) {
					if ( $editPost > 0 ) {
						$value = get_post_meta( $editPost, 'vw_accessPrice', true );
					} else {
						$value = '0.00';
					}

					$extraRows .= '<tr><td>' . __( 'Access Price', 'live-streaming' ) . '</td><td><input size=5 name="accessPrice" id="accessPrice" value="' . $value . '"><BR>' . __( 'Channel access price. Leave 0 for free access.', 'live-streaming' ) . '</td></tr>';
				}

				// logoCustom
				if ( self::inList( $userkeys, $options['logoCustom'] ) ) {
					if ( $editPost > 0 ) {
						$value = get_post_meta( $editPost, 'vw_logoImage', true );
					} else {
						$value = $options['overLogo'] ?? '';
					}

					$extraRows .= '<tr><td>' . __( 'Logo Image', 'live-streaming' ) . '</td><td><input size=64 name="logoImage" id="logoImage" value="' . $value . '"><BR>' . __( 'Channel floating logo URL (preferably a transparent PNG image). Leave blank to hide.', 'live-streaming' ) . '</td></tr>';
					if ( $editPost > 0 ) {
						$value = get_post_meta( $editPost, 'vw_logoLink', true );
					} else {
						$value = $options['overLink'];
					}

					$extraRows .= '<tr><td>Logo Link</td><td><input size=64 name="logoLink" id="logoImage" value="' . $value . '"><BR>' . __( 'URL to open on logo click.', 'live-streaming' ) . '</td></tr>';
				}

				// ipCameras
				if ( self::inList( $userkeys, $options['ipCameras'] ) ) {
					if ( $editPost > 0 ) {
						$value = get_post_meta( $editPost, 'vw_ipCamera', true );
					} else {
						$value = '';
					}

					$extraRows .= '<tr><td>' . __( 'IP Camera Stream', 'live-streaming' ) . '</td><td><input size=64 name="ipCamera" id="ipCamera" value="' . $value . '"><BR>Insert address exactly as it works in <a target="_blank" href="http://www.videolan.org/vlc/index.html">VLC</a> or other player. For increased playback support, H264 video with AAC audio encoded streams should be used. Address should use one of these protocols: rtsp://, udp://, rtmp://, rtmps://, wowz://, wowzs://, http://, https:// .</td></tr>';
				}

				// adsCustom
				if ( self::inList( $userkeys, $options['adsCustom'] ) ) {
					if ( $editPost > 0 ) {
						$value = get_post_meta( $editPost, 'vw_adsServer', true );
					} else {
						$value = $options['adServer'];
					}

					$extraRows .= '<tr><td>Ads Server</td><td><input size=64 name="adsServer" id="adsServer" value="' . $value . '"><BR>See <a href="http://www.adinchat.com" target="_blank"><U><b>AD in Chat</b></U></a> compatible ad management server. Leave blank to disable.</td></tr>';
				}

				// uploadPicture
				if ( self::inList( $userkeys, $options['uploadPicture'] ) ) {

					$extraRows .= '<tr><td>' . __( 'Picture', 'live-streaming' ) . '</td><td><input class="ui button" type="file" name="uploadPicture" id="uploadPicture"><BR>' . __( 'Upload a channel picture.', 'live-streaming' ) . '</td></tr>';

					$value = get_post_meta( $editPost, 'showImage', true );

					$extraRows .= '<tr><td>' . __( 'Show Picture', 'live-streaming' ) . '</td><td><select class="ui dropdown" name="showImage" id="showImage">';
					$extraRows .= '<option value="auto" ' . ( ($value == 'auto' || !$value) ? 'selected' : '' ) . '>' . __( 'Auto', 'live-streaming' ) . '</option>';
					$extraRows .= '<option value="event" ' . ( $value == 'event' ? 'selected' : '' ) . '>' . __( 'Event Info', 'live-streaming' ) . '</option>';
					$extraRows .= '<option value="all" ' . ( $value == 'all' ? 'selected' : '' ) . '>' . __( 'Everywhere', 'live-streaming' ) . '</option>';
					$extraRows .= '<option value="teaser" ' . ( $value == 'teaser' ? 'selected' : '' ) . '>' . __( 'Offline Video', 'live-streaming' ) . '</option>';
					$extraRows .= '<option value="no" ' . ( $value == 'no' ? 'selected' : '' ) . '>' . __( 'No', 'live-streaming' ) . '</option>';
					$extraRows .= '</select><BR>' . __( 'Configure when to display picture. All will use the picture also in listings. Offline Video will show thumb of configured offline video in listings. Auto (default) will show snapshot in listings when live, offline video thumb when available, uploaded picture otherwise.', 'live-streaming' ) . '</td></tr>';
				}

				if ( self::inList( $userkeys, $options['eventDetails'] ) ) {
					if ( $editPost > 0 ) {
						$value = get_post_meta( $editPost, 'eventTitle', true );
					}

					$extraRows .= '<tr><td>' . __( 'Event Title', 'live-streaming' ) . '</td><td><input size=64 name="eventTitle" id="eventTitle" value="' . $value . '"></td></tr>';

					if ( $editPost > 0 ) {
						$value = get_post_meta( $editPost, 'eventStart', true );
					}
					if ( $editPost > 0 ) {
						$valueTime = get_post_meta( $editPost, 'eventStartTime', true );
					} else {
						$valueTime = '';
					}

					$extraRows .= '<tr><td>Event Start</td><td>' . __( 'Date', 'live-streaming' ) . ': <input size=32 name="eventStart" id="eventStart" value="' . $value . '"> ' . __( 'Time', 'live-streaming' ) . ': <input size=32 name="eventStartTime" id="eventStartTime" value="' . $valueTime . '"></td></tr>';

					if ( $editPost > 0 ) {
						$value = get_post_meta( $editPost, 'eventEnd', true );
					}
					if ( $editPost > 0 ) {
						$valueTime = get_post_meta( $editPost, 'eventEndTime', true );
					} else {
						$valueTime = '';
					}

					$extraRows .= '<tr><td>' . __( 'Event End', 'live-streaming' ) . '</td><td>' . __( 'Date', 'live-streaming' ) . ': <input size=32 name="eventEnd" id="eventEnd" value="' . $value . '"> ' . __( 'Time', 'live-streaming' ) . ': <input size=32 name="eventEndTime" id="eventEndTime" value="' . $valueTime . '"></td></tr>';

					if ( $editPost > 0 ) {
						$value = get_post_meta( $editPost, 'eventDescription', true );
					}
					$extraRows .= '<tr><td>' . __( 'Event Description', 'live-streaming' ) . '</td><td><textarea rows=3 cols=60 name="eventDescription" id="eventDescription">' . $value . '</textarea><br>' . __( 'Event details also show when channel is offline or inaccessible.', 'live-streaming' ) . '</td></tr>';

				}

				$formTitle = __( 'Setup Channel', 'live-streaming' ) . ' ' . ($newTitle ?? '');
				$formRows  = '<tr><td>' . __( 'Description', 'live-streaming' ) . '</td><td><textarea rows=3 cols=60 name="description" id="description">' . $newDescription . '</textarea></td></tr>
<tr><td>' . __( 'Category', 'live-streaming' ) . '</td><td>' . $categories . '</td></tr>
<tr><td>' . __( 'Comments', 'live-streaming' ) . '</td><td>' . $commentsCode . '</td></tr>';

				$formButton = '<tr><td></td><td><input class="ui button primary" type="submit" name="button" id="button" value="' . __( 'Setup', 'live-streaming' ) . '" /></td></tr>';

				if ( $editPost > 0 || $channels_count < $maxChannels ) {
					$htmlCode .= <<<HTMLCODE
<script language="JavaScript">
		function censorName()
			{
				document.adminForm.room.value = document.adminForm.room.value.replace(/^[\s]+|[\s]+$/g, '');
				document.adminForm.room.value = document.adminForm.room.value.replace(/[^0-9a-zA-Z_\-]+/g, '-')
				document.adminForm.room.value = document.adminForm.room.value.replace(/\-+/g, '-');
				document.adminForm.room.value = document.adminForm.room.value.replace(/^\-+|\-+$/g, '');
				if (document.adminForm.room.value.length>0) return true;
				else
				{
				alert("A channel name is required!");
				return false;
				}
			}
</script>

<div class="ui form">
<form method="post" enctype="multipart/form-data"  action="$this_page" name="adminForm" class="w-actionbox">
<h3>$formTitle</h3>
<table class="ui celled table selectable">
<tr><td>Name</td><td><input name="newname" type="$nameField" id="newname" value="$newName" size="20" maxlength="64" onChange="censorName()"/>$newNameL <input class="ui button small" type="submit" name="button" id="button" value="Setup" /></td></tr>
$formRows
$extraRows
$formButton
</table>
<input type="hidden" name="editPost" id="editPost" value="$editPost" />
</form>
</div>

<script>


jQuery(document).ready(function(){
		jQuery(".ui.dropdown").not(".ajax").dropdown();
});

</script>
HTMLCODE;
				}
			}

			$htmlCode .= '<STYLE>' . html_entity_decode( stripslashes( $options['customCSS'] ) ) . '</STYLE>';

			return $htmlCode;

		}


		static function enqueueUI() {
			// semantic ui
			wp_enqueue_script( 'jquery' );

			// semantic
			wp_enqueue_style( 'semantic', plugin_dir_url( __FILE__ ) . '/scripts/semantic/semantic.min.css' );
			wp_enqueue_script( 'semantic', plugin_dir_url( __FILE__ ) . '/scripts/semantic/semantic.min.js', array( 'jquery' ) );
		}

		static function videowhisper_channels( $atts ) {
			$options = get_option( 'VWliveStreamingOptions' );

			$atts = shortcode_atts(
				array(
					'per_page'        => $options['perPage'],
					'ban'             => '0',
					'perrow'          => '',
					'order_by'        => 'edate',
					'category_id'     => '',
					'tags'            => '',
					'name'            => '',
					'select_category' => '1',
					'select_tags'     => '1',
					'select_name'     => '1',
					'select_order'    => '1',
					'select_page'     => '1',
					'include_css'     => '1',
					'url_vars'        => '1',
					'url_vars_fixed'  => '1',
					'id'              => '',
				),
				$atts,
				'videowhisper_channels'
			);

			$id = $atts['id'];
			if ( ! $id ) {
				$id = uniqid();
			}

			if ( $atts['url_vars'] ) {
				$cid = intval( $_GET['cid'] ?? 0 );
				if ( $cid ) {
					$atts['category_id'] = $cid;
					if ( $atts['url_vars_fixed'] ) {
						$atts['select_category'] = '0';
					}
				}
			}

			// semantic ui : listings
			self::enqueueUI();

			$ajaxurl = admin_url() . 'admin-ajax.php?action=vwls_channels&pp=' . $atts['per_page'] . '&pr=' . $atts['perrow'] . '&ob=' . $atts['order_by'] . '&cat=' . $atts['category_id'] . '&sc=' . $atts['select_category'] . '&sn=' . $atts['select_name'] . '&sg=' . $atts['select_tags'] . '&so=' . $atts['select_order'] . '&sp=' . $atts['select_page'] . '&id=' . $id . '&tags=' . urlencode( $atts['tags'] ) . '&name=' . urlencode( $atts['name'] );

			if ( $atts['ban'] ) {
				$ajaxurl .= '&ban=' . $atts['ban'];
			}

			$htmlCode = <<<HTMLCODE
<script>
var aurl$id = '$ajaxurl';
var \$j = jQuery.noConflict();
var loader$id;

	function loadChannels$id(message){

	if (message)
	if (message.length > 0)
	{
	  \$j("#videowhisperChannels$id").html(message);
	}

		if (loader$id) loader$id.abort();

		loader$id = \$j.ajax({
			url: aurl$id,
			success: function(data) {
				\$j("#videowhisperChannels$id").html(data);
				jQuery(".ui.dropdown").dropdown();
				jQuery(".ui.rating.readonly").rating("disable");
			}
		});
	}

jQuery(document).ready(function(){
	loadChannels$id();
	setInterval("loadChannels$id()", 10000);
});

</script>

<div id="videowhisperChannels$id">

<div class="ui active inline text large loader">Loading channels...</div>

</div>
HTMLCODE;

			$htmlCode .= '<STYLE>' . html_entity_decode( stripslashes( $options['customCSS'] ) ) . '</STYLE>';

			return $htmlCode;
		}

		static function flash_warn() {
			return __( 'Please configure, activate and use latest HTML5 / WebRTC interfaces. <a href="https://www.adobe.com/products/flashplayer/enterprise-end-of-life.html">Adobe stopped supporting Flash Player</a> beginning December 31, 2020 (“EOL Date”). Older Flash apps are no longer included in latest version. ', 'live-streaming' );

			/*
			$htmlCode = <<<HTMLCODE
			<div id="flashWarning"></div>

			<script>
			var hasFlash = ((typeof navigator.plugins != "undefined" && typeof navigator.plugins["Shockwave Flash"] == "object") || (window.ActiveXObject && (new ActiveXObject("ShockwaveFlash.ShockwaveFlash")) != false));

			var flashWarn = '<small>$flashWarning</small>'

			if (!hasFlash) document.getElementById("flashWarning").innerHTML = flashWarn;</script>
			HTMLCODE;

			return $htmlCode;
			*/
		}

		static function flash_watch( $stream, $width = '100%', $height = '100%' ) {
			$htmlCode = '';

			/*
			$stream = sanitize_file_name($stream);

			$streamLabel = preg_replace('/[^A-Za-z0-9\-\_]/', '', $stream);

			$swfurl = plugin_dir_url(__FILE__) . "ls/live_watch.swf?ssl=1&n=" . urlencode($stream);
			$swfurl .= "&prefix=" . urlencode(admin_url() . 'admin-ajax.php?action=vwls&task=');
			$swfurl .= '&extension='.urlencode('_none_');
			$swfurl .= '&ws_res=' . urlencode( plugin_dir_url(__FILE__) . 'ls/');

			$bgcolor="#333333";

			$htmlCode = <<<HTMLCODE
			<div id="videowhisper_container_$streamLabel" style="overflow:auto">
			<object id="videowhisper_watch_$streamLabel" width="$width" height="$height" type="application/x-shockwave-flash" data="$swfurl">
			<param name="movie" value="$swfurl"></param><param bgcolor="$bgcolor"><param name="scale" value="noscale" /> </param><param name="salign" value="lt"></param><param name="allowFullScreen"
			value="true"></param><param name="allowscriptaccess" value="always"></param>
			</object>
			</div>
			HTMLCODE;*/

			$htmlCode .= self::flash_warn();

			return $htmlCode;
		}


		static function videowhisper_watch( $atts ) {
			$stream = '';
			$htmlCode = '';

			$options = get_option( 'VWliveStreamingOptions' );

			if ( is_single() ) {
				if ( get_post_type( get_the_ID() ) == $options['custom_post'] ) {
					$stream = get_the_title( get_the_ID() );
				}
			}

				$atts = shortcode_atts(
					array(
						'channel' => $stream,
						'width'   => '100%',
						'height'  => '100%',
						'flash'   => '0',
						'html5'   => 'auto',
					),
					$atts,
					'videowhisper_watch'
				);

			if ( ! $stream ) {
				$stream = sanitize_file_name( $atts['channel'] ); // parameter channel="name"
			}
			if ( ! $stream ) {
				$stream = sanitize_file_name( $_GET['n'] );
			}
			$stream = sanitize_file_name( $stream );

			if ( ! $stream ) {
				return 'Watch Error: Missing channel name!';
			}

			// used by flash container
			$width = $atts['width'];
			if ( ! $width ) {
				$width = '100%';
			}
			$height = $atts['height'];
			if ( ! $height ) {
				$height = '100%';
			}

			global $wpdb;
			$postID = $wpdb->get_var( $sql = 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_title = \'' . $stream . '\' and post_type=\'' . $options['custom_post'] . '\' LIMIT 0,1' );

			// handle paused restreams
			$restreamPaused = false;
			$vw_ipCamera = get_post_meta( $postID, 'vw_ipCamera', true );
			if ( $vw_ipCamera ) {
				$restreamPaused = get_post_meta( $postID, 'restreamPaused', true );
				$htmlCode .= self::restreamPause( $postID, $stream, $options );
			}

			if ($options['html5videochat'] && !$vw_ipCamera) return do_shortcode("[videowhisper_h5vls_app room=\"$stream\" webcam_id=\"$postID\"]");

			$streamProtocol = get_post_meta( $postID, 'stream-protocol', true ); // rtsp/rtmp
			$streamType     = get_post_meta( $postID, 'stream-type', true ); // stream-type: flash/webrtc/restream/playlist
			$streamMode     = get_post_meta( $postID, 'stream-mode', true ); // direct/safari_pc

			if ( ! $atts['flash'] ) {
				// HLS if iOS/Android detected
				$agent   = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] );
				$Android = stripos( $agent, 'Android' );
				$iOS     = ( strstr( $agent, 'iPhone' ) || strstr( $agent, 'iPod' ) || strstr( $agent, 'iPad' ) );
				$Safari  = ( strstr( $agent, 'Safari' ) && ! strstr( $agent, 'Chrome' ) );
				$Firefox = stripos( $agent, 'Firefox' );

				$htmlCode .= "<!--VideoWhisper-Agent-Watch:$agent|A:$Android|I:$iOS|S:$Safari|F:$Firefox-->";

				$showHTML5 = 1; //new default

				// always
				if ( $atts['html5'] == 'always' ) {
					$showHTML5 = 1;
				}

				// adaptive
				if ( $options['transcoding'] >= 3 && $streamType != 'flash' ) {
					$showHTML5 = 1;
				}
				// if ($options['webrtc']>=3  && $streamType=='webrtc' && $streamMode!='safari_pc') $showHTML5 = 1; //safari_pc does not work directly w. h264
				if ( $options['webrtc'] >= 3 && $streamType == 'webrtc' ) {
					$showHTML5 = 1; // safari_pc does not work directly
				}

				// preferred transcoded playback
				if ( $options['transcoding'] >= 4 ) {
					$showHTML5 = 1;
				}
				if ( $options['webrtc'] >= 4 ) {
					$showHTML5 = 1;
				}

				if ( ( $Android && in_array( $options['detect_mpeg'], array( 'android', 'all' ) ) ) || ( ! $iOS && in_array( $options['detect_mpeg'], array( 'all' ) ) ) || ( ! $iOS && ! $Safari && in_array( $options['detect_mpeg'], array( 'nonsafari' ) ) ) ) {
					$showHTML5 = 1;
				}

				if ( ( ( $Android || $iOS ) && in_array( $options['detect_hls'], array( 'mobile', 'safari', 'all' ) ) ) || ( $iOS && $options['detect_hls'] == 'ios' ) || ( $Safari && in_array( $options['detect_hls'], array( 'safari', 'all' ) ) ) ) {
					$showHTML5 = 1;
				}

				if ( $showHTML5 ) {
					return $htmlCode . '<!--H5-HLS-->' . do_shortcode( "[videowhisper_htmlchat_playback channel=\"$stream\" paused=\"$restreamPaused\"]" );
				}
			} else {
				$htmlCode .= '<!--VideoWhisper-Watch:Flash-->';
			}

			// show flash_watch

			$options    = get_option( 'VWliveStreamingOptions' );
			$watchStyle = html_entity_decode( $options['watchStyle'] );

			$streamLabel = preg_replace( '/[^A-Za-z0-9\-\_]/', '', $stream );

			$afterCode = <<<HTMLCODE
<br style="clear:both" />

<style type="text/css">
<!--

#videowhisper_container_$streamLabel
{
$watchStyle
}

-->
</style>

HTMLCODE;

			// Available HTML5
			if ( $options['transcoding'] >= 2 ) {
				if ( $postID ) {
					$afterCode .= '<p><a class="ui button secondary" href="' . add_query_arg( array( 'html5-view' => '' ), get_permalink( $postID ) ) . '">' . __( 'Try HTML5 View', 'live-streaming' ) . '</a></p>';
				}
			}

			return self::flash_watch( $stream, $width, $height ) . $afterCode;

		}

		static function transcodeStreamWebRTC( $stream, $postID, $options = null, $detect = 2 ) {
			// transcode for WebRTC usage: RTMP/RTSP as necessary

			if ( ! $stream ) {
				return;
			}
			if ( ! $options ) {
				$options = get_option( 'VWliveStreamingOptions' );
			}

			if ( ! $options['webrtc'] ) {
				return;
			}

			if ( !$options['enable_exec'] ) return;

			if ( ! self::timeTo( $stream . '/transcodeCheckWebRTC-Flood', 3, $options ) ) {
				return; // prevent duplicate checks
			}

			// check every 59s
			$tooSoon = 0;
			if ( ! self::timeTo( $stream . '/transcodeCheckWebRTC', 59, $options ) ) {
				$tooSoon = 1;
			}

			$sourceProtocol = get_post_meta( $postID, 'stream-protocol', true ); // rtmp/rtsp
			$sourceType     = get_post_meta( $postID, 'stream-type', true ); // stream-type: flash/webrtc/restream/playlist

			if ( ! $sourceProtocol ) {
				$sourceProtocol = 'rtmp'; // assuming plain wowza stream
			}

			if ( $sourceProtocol == 'rtmp' ) {
				if ( ! $options['transcodeRTC'] ) {
					return $stream; // webrtc transcoding disabled: return original stream
				}

				// RTMP to RTSP (h264/opus)
				$stream_webrtc = $stream . '_webrtc';

				if ( $tooSoon ) {
					return $stream_webrtc;
				}

				// detect transcoding process - cancel if already started
				$cmd = "ps aux | grep '/$stream_webrtc -i rtmp'";
				if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );

				$transcoding = 0;
				foreach ( $output as $line ) {
					if ( strstr( $line, 'ffmpeg' ) ) {
						$transcoding = 1;
						break;
					}
				}

				// rtmp keys
				if ( $options['externalKeysTranscoder'] ) {
					$keyView         = md5( 'vw' . $options['webKey'] . intval( $postID ) );
					$rtmpAddressView = $options['rtmp_server'] . '?' . urlencode( 'ffmpegWebRTC_' . $stream_webrtc ) . '&' . urlencode( $stream ) . '&' . $keyView . '&0&videowhisper';

				} else {
					$rtmpAddress     = $options['rtmp_server'];
					$rtmpAddressView = $options['rtmp_server'];
				}

				$userID = get_post_field( 'post_author', $postID );

				// $keyBroadcast = md5('vw' . $options['webKey'] . $userID . $postID);
				// $streamQuery = $stream_webrtc . '?channel_id=' . $postID . '&userID=' . $userID . '&key=' . urlencode($keyBroadcast) . '&transcoding=1';

				$streamQuery = self::webrtcStreamQuery( $userID, $postID, 1, $stream_webrtc, $options, 1 );

				// paths
				$uploadsPath = $options['uploadsPath'];
				if ( ! file_exists( $uploadsPath ) ) {
					mkdir( $uploadsPath );
				}
				$upath = $uploadsPath . "/$stream/";
				if ( ! file_exists( $upath ) ) {
					mkdir( $upath );
				}

				if ( ! $transcoding ) {

					// start transcoding process
					$log_file = $upath . 'transcode_rtmp-webrtc.log';

					$cmd = $options['ffmpegPath'] . ' ' . $options['ffmpegTranscodeRTC'] .
						' -threads 1 -f rtsp "' . $options['rtsp_server_publish'] . '/' . $streamQuery .
						'" -i "' . $rtmpAddressView . '/' . $stream . "\" >&$log_file & ";

					// echo $cmd;
					if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );
					if ( $options['enable_exec'] ) exec( "echo '$cmd' >> $log_file.cmd", $output, $returnvalue );

					update_post_meta( $postID, 'stream-webrtc', $stream_webrtc );
				} else {
					update_post_meta( $postID, 'transcoding-webrtc', time() );
				}
			}

			if ( $sourceProtocol == 'rtsp' && $sourceType != 'restream' ) {

				if ( ! $options['transcodeFromRTC'] ) {
					return $stream; // from webrtc transcoding disabled: return original stream
				}

				// RTSP to HLS/RTMP (h264/aac)
				$stream_hls = 'i_' . $stream;

				if ( $tooSoon ) {
					return $stream_hls;
				}

				// detect transcoding process - cancel if already started
				$cmd = "ps aux | grep '/$stream_hls -i rtsp'";
				if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );

				$transcoding = 0;
				foreach ( $output as $line ) {
					if ( strstr( $line, 'ffmpeg' ) ) {
						$transcoding = 1;
						break;
					}
				}

				// rtmp keys
				if ( $options['externalKeysTranscoder'] ) {
					$channel = get_post( $postID );
					$userID  = $channel->post_author;

					$key         = md5( 'vw' . $options['webKey'] . intval( $userID ) . intval( $postID ) );
					$rtmpAddress = $options['rtmp_server'] . '?' . urlencode( $stream_hls ) . '&' . urlencode( $stream ) . '&' . $key . '&1&' . intval( $userID ) . '&videowhisper';
				} else {
					$rtmpAddress     = $options['rtmp_server'];
					$rtmpAddressView = $options['rtmp_server'];
				}

				// paths
				$uploadsPath = $options['uploadsPath'];
				if ( ! file_exists( $uploadsPath ) ) {
					mkdir( $uploadsPath );
				}
				$upath = $uploadsPath . "/$stream/";
				if ( ! file_exists( $upath ) ) {
					mkdir( $upath );
				}

				if ( ! $transcoding ) {

					if ( $detect == 2 || ( $detect == 1 && ! $videoCodec ) ) {

						// detect webrtc stream info
						$log_file = $upath . 'streaminfo-webrtc.log';

						$cmd  = 'timeout -s KILL 3 ' . $options['ffmpegPath'] . ' -y -i "' . $options['rtsp_server'] . '/' . $stream . '" 2>&1 ';
						if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );

						$info = implode("\n", $output);

						// video
						if ( ! preg_match( '/Stream #(?:[0-9\.]+)(?:.*)\: Video: (?P<videocodec>.*)/', $info, $matches ) ) {
							preg_match( '/Could not find codec parameters \(Video: (?P<videocodec>.*)/', $info, $matches );
						}
						list($videoCodec) = explode( ' ', $matches[1] );
						if ( $videoCodec && $postID ) {
							update_post_meta( $postID, 'stream-codec-video', strtolower( $videoCodec ) );
						}

						// audio
						$matches = array();
						if ( ! preg_match( '/Stream #(?:[0-9\.]+)(?:.*)\: Audio: (?P<audiocodec>.*)/', $info, $matches ) ) {
							preg_match( '/Could not find codec parameters \(Audio: (?P<audiocodec>.*)/', $info, $matches );
						}

						list($audioCodec) = explode( ' ', $matches[1] );
						$audioCodec       = trim( $audioCodec, " ,.\t\n\r\0\x0B" );
						if ( $audioCodec && $postID ) {
							update_post_meta( $postID, 'stream-codec-audio', strtolower( $audioCodec ) );
						}
						if ( ( $videoCodec || $audioCodec ) && $postID ) {
							update_post_meta( $postID, 'stream-codec-detect', time() );
						}

						if ( $options['enable_exec'] ) exec( "echo '" . "$stream|$stream_hls|$stream_webrtc|$transcodeEnabled|$detect|$videoCodec|$audioCodec" . "' >> $log_file", $output, $returnvalue );
						if ( $options['enable_exec'] ) exec( 'echo "' . addslashes( $info ) . "\" >> $log_file", $output, $returnvalue );

					}

					// start transcoding process
					$log_file = $upath . 'transcode_webrtc-hls.log';

					if ( $videoCodec && $audioCodec ) {
						// convert command
						$cmd = $options['ffmpegPath'] . ' ' . $options['ffmpegTranscode'] . ' -threads 1 -f flv "' .
							$rtmpAddress . '/' . $stream_hls . '" -i "' . $options['rtsp_server'] . '/' . $stream . "\" >&$log_file & ";

						// echo $cmd;
						if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );
						if ( $options['enable_exec'] ) exec( "echo '$cmd' >> $log_file.cmd", $output, $returnvalue );

						update_post_meta( $postID, 'stream-hls', $stream_hls );
					} else {
						if ( $options['enable_exec'] ) exec( "echo 'Stream incomplete. Will check again later... ' >> $log_file", $output, $returnvalue );
					}
				} else {
					update_post_meta( $postID, 'transcoding-hls', time() );
				}
			}

		}

		static function responsiveStream( $default, $postID, $player = 'flash' ) {
			if ( ! $postID ) {
				return $default;
			}

			$sourceProtocol = get_post_meta( $postID, 'stream-protocol', true );

			if ( $player == 'flash' ) {
				if ( $sourceProtocol == 'rtsp' ) {
					$transcode = 0;

					$videoCodec = get_post_meta( $postID, 'stream-codec-video', true );
					$audioCodec = get_post_meta( $postID, 'stream-codec-audio', true );

					if ( ! in_array( $videoCodec, array( 'h264' ) ) ) {
						$transcode = 1;
					}
					if ( ! in_array( $audioCodec, array( 'aac', 'speex' ) ) ) {
						$transcode = 1;
					}

					if ( ! $transcode ) {
						return $default;
					}

					$stream_hls = get_post_meta( $postID, 'stream-hls', true );
					if ( $stream_hls ) {
						return $stream_hls;
					}
				}
			}

			return $default;
		}



		static function transcodeStream( $stream, $required = 0, $detect = 2, $convert = 1 ) {
			// $stream = room name
			// $detect: 0 = no, 1 = auto, 2 = always (update)
			// $convert: 0 = no, 1 = auto , 2 = always

			if ( ! $stream ) {
				return;
			}

			$options = get_option( 'VWliveStreamingOptions' );

			if ( !$options['enable_exec'] ) return $stream;

			// is it a post channel?
			global $wpdb;
			$postID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name( $stream ) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );

			// is feature enabled?
			if ( $postID ) {
				$sourceProtocol = get_post_meta( $postID, 'stream-protocol', true );
				$sourceType     = get_post_meta( $postID, 'stream-type', true ); // stream-type: flash/external/webrtc/restream/playlist
				$stream_hls     = get_post_meta( $postID, 'stream-hls', true );

				$transcodeEnabled = get_post_meta( $postID, 'vw_transcode', true );
				$videoCodec       = get_post_meta( $postID, 'stream-codec-video', true );

				$reStream = get_post_meta( $postID, 'vw_ipCamera', true );

			} else {
				if ( $options['anyChannels'] || $options['userChannels'] ) {
					$transcodeEnabled = 1;
				}
			}

			if ( in_array( $sourceProtocol, array( 'http', 'https' ) ) ) {
				$stream_hls = $stream; // as is for http streams
			}

			if ( ! $options['transcodingAuto'] && $convert != 2 ) {
				return $stream_hls; // disabled
			}

			// direct delivery for restream/external/playlist : do not transcode
			if ( ( $reStream && ! $options['transcodeReStreams'] ) || ( $sourceType == 'external' && ! $options['transcodeExternal'] ) || $sourceType == 'playlist' ) {
				update_post_meta( $postID, 'stream-hls', $stream );

				return $stream;
			}

			if ( ! self::timeTo( $stream . '/transcodeCheckRTMP-Flood', 3, $options ) ) {
				return $stream_hls; // prevent duplicate checks
			}

			// check every 59s
			if ( ! $required ) {
				if ( ! self::timeTo( $stream . '/transcodeCheckRTMP', 59, $options ) ) {
					return $stream_hls;
				}
			}

				// also transcode for webrtc if enabled
			if ( $options['webrtc'] ) {
				self::transcodeStreamWebRTC( $stream, $postID, $options );
			}

				// rtsp is from webrtc or restream (restream are also handled by media server)
			if ( $sourceProtocol == 'rtsp' && $sourceType != 'restream' ) {
				return $stream_hls; // transcoded by transcodeStreamWebRTC() - use that
			}

			if ( in_array( $sourceProtocol, array( 'http', 'https' ) ) ) {
				return $stream; // return http streams as is
			}

				// HLS
				// Doing RTMP to HLS/RTMP (H264/AAC)

				$stream_hls = 'i_' . $stream; // transcoded stream

			// detect transcoding process - cancel if already started
			$cmd = "ps aux | grep '/$stream_hls -i rtmp'";
			if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );
			// var_dump($output);

			$transcoding = 0;
			foreach ( $output as $line ) {
				if ( strstr( $line, 'ffmpeg' ) ) {
					$transcoding = 1;
					break;
				}
			}

			if ( $transcoding ) {
				return $stream_hls; // already transcoding - nothing to do
			}

			// rtmp keys
			if ( $options['externalKeysTranscoder'] ) {
				$userID = get_post_field( 'post_author', $postID );

				$key = md5( 'vw' . $options['webKey'] . intval( $userID ) . intval( $postID ) );

				$keyView = md5( 'vw' . $options['webKey'] . intval( $postID ) );

				// ?session&room&key&broadcaster&broadcasterid
				$rtmpAddress      = $options['rtmp_server'] . '?' . urlencode( $stream_hls ) . '&' . urlencode( $stream ) . '&' . $key . '&1&' . intval( $userID ) . '&videowhisper';
				$rtmpAddressView  = $options['rtmp_server'] . '?' . urlencode( 'ffmpegView_' . $stream ) . '&' . urlencode( $stream ) . '&' . $keyView . '&0&videowhisper';
				$rtmpAddressViewI = $options['rtmp_server'] . '?' . urlencode( 'ffmpegInfo_' . $stream ) . '&' . urlencode( $stream ) . '&' . $keyView . '&0&videowhisper';

			} else {
				$rtmpAddress     = $options['rtmp_server'];
				$rtmpAddressView = $options['rtmp_server'];
			}

			// paths
			$uploadsPath = $options['uploadsPath'];
			if ( ! file_exists( $uploadsPath ) ) {
				mkdir( $uploadsPath );
			}

			$upath = $uploadsPath . "/$stream/";
			if ( ! file_exists( $upath ) ) {
				mkdir( $upath );
			}

			// detect codecs - do transcoding only if necessary
			if ( $detect == 2 || ( $detect == 1 && ! $videoCodec ) ) {

				$log_file = $upath . 'streaminfo-rtmp.log';

				$cmd  = 'timeout -s KILL 3 ' . $options['ffmpegPath'] . ' -y -i "' . $rtmpAddressViewI . '/' . $stream . '" 2>&1 ';
				if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue  );

				$info = implode("\n", $output);

				// video
				if ( ! preg_match( '/Stream #(?:[0-9\.]+)(?:.*)\: Video: (?P<videocodec>.*)/', $info, $matches ) ) {
					preg_match( '/Could not find codec parameters \(Video: (?P<videocodec>.*)/', $info, $matches );
				}

				if (is_array($matches) &&  count($matches)) list($videoCodec) = explode( ' ', $matches[1] );

				if ( $videoCodec && $postID ) {
					update_post_meta( $postID, 'stream-codec-video', strtolower( $videoCodec ) );
				} else $videoCodec = '';

				// audio
				$matches = array();
				if ( ! preg_match( '/Stream #(?:[0-9\.]+)(?:.*)\: Audio: (?P<audiocodec>.*)/', $info, $matches ) ) {
					preg_match( '/Could not find codec parameters \(Audio: (?P<audiocodec>.*)/', $info, $matches );
				}

				if (is_array($matches) && count($matches)) list($audioCodec) = explode( ' ', $matches[1] );

				if (isset($audioCodec))
				{
    $audioCodec       = trim($audioCodec, " ,.\t\n\r\0\x0B");
    if ($audioCodec && $postID)
        update_post_meta($postID, 'stream-codec-audio', strtolower($audioCodec));
				} else $audioCodec = '';


				if ( ( $videoCodec || $audioCodec ) && $postID ) {
					update_post_meta( $postID, 'stream-codec-detect', time() );
				}

				if ( $options['enable_exec'] ) exec( "echo '" . "$stream|$stream_hls|$transcodeEnabled|$required|$detect|$convert|$videoCodec|$audioCodec" . "' >> $log_file", $output, $returnvalue );

				if ( $options['enable_exec'] ) exec( 'echo "' . addslashes( $info ) . "\" >> $log_file", $output, $returnvalue );

				if ( $options['enable_exec'] ) exec( "echo '$cmd' >> $log_file.cmd", $output, $returnvalue );

				$lastLog = $options['uploadsPath'] . '/lastLog-streamInfo.txt';
				self::varSave(
					$lastLog,
					array(
						'file'    => $log_file,
						'cmd'     => $cmd,
						'return'  => $returnvalue,
						'output0' => $output[0] ?? '',
						'time'    => time(),
					)
				);

			}

			// do any conversions after detection
			if ( $convert ) {

				if ( $postID ) {

					if ( ! $videoCodec ) {
						$videoCodec = get_post_meta( $postID, 'stream-codec-video', true );
					}
					if ( ! $audioCodec ) {
						$audioCodec = get_post_meta( $postID, 'stream-codec-audio', true );
					}

					// valid mp4 for html5 playback?
					if ( ( $videoCodec == 'h264' ) && ( $audioCodec == 'aac' ) ) {
						$isMP4 = 1;
					} else {
						$isMP4 = 0;
					}

					if ( $isMP4 && $convert == 1 ) {
						update_post_meta( $postID, 'stream-hls', $stream ); // stream is good for hls (when broadcast directly AAC with OBS)
						return $stream; // present format is fine - no conversion required
					}
				}

				if ( ! $transcodeEnabled ) {
					return ''; // transcoding disabled
				}

				// start transcoding process
				$log_file = $upath . 'transcode-rtmp.log';

				if ( $videoCodec && $audioCodec ) {

					// convert command
					$cmd = $options['ffmpegPath'] . ' ' . $options['ffmpegTranscode'] . ' -threads 1 -f flv "' .
						$rtmpAddress . '/' . $stream_hls . '" -i "' . $rtmpAddressView . '/' . $stream . "\" >&$log_file & ";

					// log and executed cmd
					if ( $options['enable_exec'] ) exec( "echo '" . date( DATE_RFC2822 ) . "|$convert|$transcodeEnabled|$stream|$stream_hls|$postID|$isMP4:: $cmd' >> $log_file.cmd", $output, $returnvalue );
					if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );

					$lastLog = $options['uploadsPath'] . '/lastLog-streamTranscode.txt';
					self::varSave(
						$lastLog,
						array(
							'file'    => $log_file,
							'cmd'     => $cmd,
							'return'  => $returnvalue,
							'output0' => $output[0] ?? '',
							'time'    => time(),
						)
					);

					// $cmd = "ps aux | grep '/i_$stream -i rtmp'";
					// exec($cmd, $output, $returnvalue);

					update_post_meta( $postID, 'stream-hls', $stream_hls );

				} else {
					if ( $options['enable_exec'] ) exec( "echo 'Stream incomplete. Will check again later... ' >> $log_file", $output, $returnvalue );
				}

				return $stream_hls;
			}

		}

		static function videowhisper_hls( $atts ) {
			$stream = '';
			$streamName = '';

			$options = get_option( 'VWliveStreamingOptions' );

			if ( is_single() ) {
				if ( get_post_type( $postID = get_the_ID() ) == $options['custom_post'] ) {
					$stream = get_the_title( $postID );
				}
			}

				$atts = shortcode_atts(
					array(
						'channel' => $stream,
						'width'      => $options['videoWidth'],
						'height'     => $options['videoHeight'],
						'silent'  => '0',
						'paused' => false,
					),
					$atts,
					'videowhisper_hls'
				);

			$restreamPaused = boolval( $atts['paused'] );

			if ( ! $stream ) {
				$stream = sanitize_file_name( $atts['channel'] ); // parameter channel="name"
			}
			if ( ! $stream ) {
				$stream = sanitize_file_name( $_GET['n'] );
			}

			$stream = sanitize_file_name( $stream );

			$width = $atts['width'];
			if ( ! $width ) {
				$width = '480px';
			}
			$height = $atts['height'];
			if ( ! $height ) {
				$height = '360px';
			}

			if ( ! $stream ) {
				return 'Watch HLS Error: Missing channel name!';
			}

			$stream_hls  = $stream ;

			// get channel id $postID
			global $wpdb;
			$postID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name( $stream ) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );

			// auto transcoding (on request)
			if ( $options['transcodingAuto'] ) {
				$streamName = self::transcodeStream( $stream, 1 ); // require transcoding name
			}

			// get compatible stream
			$sourceProtocol = get_post_meta( $postID, 'stream-protocol', true );
			if ( $sourceProtocol == 'rtsp' ) {
				$stream_hls = get_post_meta( $postID, 'stream-hls', true );
				if ( $stream_hls ) {
					$streamName = $stream_hls;
				}
			}

			if ( ! $streamName ) {
				$streamName = $stream;
			}

			if ( $streamName ) {
				$streamURL = $options['httpstreamer'] . $streamName . '/playlist.m3u8';

				$dir           = $options['uploadsPath'] . '/_thumbs';
				$thumbFilename = "$dir/" . $stream . '.jpg';
				$thumbUrl      = self::path2url( $thumbFilename ) . '?nocache=' . ( ( time() / 10 ) % 100 );

				$codecAudio = get_post_meta( $postID, 'stream-codec-audio', true );
				$codecVideo = get_post_meta( $postID, 'stream-codec-video', true );

				$edate = get_post_meta( $postID, 'edate', true );
				if ( time() - $edate > 60 ) {
					$offline_video = get_post_meta( $postID, 'offline_video', true );
					if ( $offline_video ) {
						$videoURL = self::vsvVideoURL( $offline_video, $options );
					}
					if ( isset($videoURL) ) {
						$streamURL = $videoURL;
					}
				}

$streamName = preg_replace("/[\W_]+/u", '', $stream);

$intCode = '';
if ($restreamPaused) $intCode = '<div class="ui label">Activating Stream... <i class="spinner loading icon"></i></div>';

				$htmlCode = <<<HTMLCODE
<!--HLS:$postID:p=$sourceProtocol:s=$stream:sh=$stream_hls:cv=$codecVideo:ca=$codecAudio:e=$edate:vof=$offline_video:paused=$restreamPaused-->
<video id="videowhisper_hls_$streamName" class="videowhisper_htmlvideo" width="$width" height="$height" autobuffer autoplay playsinline controls poster="$thumbUrl">
 <source src="$streamURL" type='video/mp4'>
    <div class="fallback" style="display:none">
        <IMG SRC="$thumbUrl">
	    <p>You must have a HTML5 capable browser with HLS support (Ex. Safari) to open this live stream: $streamURL</p>
	</div>
</video>
<div id="sdpDataTag">$intCode</div>

<script>

var myVideo = document.getElementById("videowhisper_hls_$streamName");
var videoLoaded = false;

myVideo.addEventListener('loadeddata', function() {
	videoLoaded = true;
	if (myVideo.paused)
	{
	   var playPromise = myVideo.play();
	   playPromise.then(function() {
	    // Automatic playback started!
	  }).catch(function(error) {

	  jQuery("#sdpDataTag").html('<br><button class="ui button compact red" onclick="myVideo.play();jQuery(\'#sdpDataTag\').html(\'\')"><i class="play icon"></i> Tap to Play</button> <small><br>' + error + '</small>');
	  console.log('Warning: Could not autoplay $stream:', error);
	  });
    }

}, false);

  setInterval(function()
  {
	  if (!videoLoaded) if (myVideo.paused)
	  {
	   myVideo.load();
	   console.log('Warning: HLS $stream not loaded. Trying to reload...', myVideo.currentTime, myVideo.paused);
      }

   }, 3500);

</script>
HTMLCODE;
			} else {
				$htmlCode = 'HLS format is not available and can not be transcoded for stream: ' . $stream;
			}

			if ( $options['transcodingWarning'] >= 2 & ! $atts['silent'] ) {
				$htmlCode .= '<p class="info"><small>HLS Playback: for iOS and Safari. Enable sound from controlHTML5 Video Stream Playbackbased delivery technology involve high latency and availability delay (may take dozens of seconds for transcoder to start stream to become available, after broadcast starts).</small></p>';
			}

			return $htmlCode;
		}

		static function videowhisper_mpeg( $atts ) {

			$stream = '';
			$streamName = '';
			$videoURL = '';

			$options = get_option( 'VWliveStreamingOptions' );

			if ( is_single() ) {
				if ( get_post_type( $postID = get_the_ID() ) == $options['custom_post'] ) {
					$stream = get_the_title( $postID );
				}
			}

				$atts = shortcode_atts(
					array(
						'channel' => $stream,
						'width'      => $options['videoWidth'],
						'height'     => $options['videoHeight'],
						'silent'  => '0',
						'paused' => false,
					),
					$atts,
					'videowhisper_mpeg'
				);

			$restreamPaused = boolval( $atts['paused'] );

			if ( ! $stream ) {
				$stream = sanitize_file_name( $atts['channel'] ); // parameter channel="name"
			}
			if ( ! $stream ) {
				$stream = sanitize_file_name( $_GET['n'] );
			}

			$width = $atts['width'];
			if ( ! $width ) {
				$width = '480px';
			}
			$height = $atts['height'];
			if ( ! $height ) {
				$height = '360px';
			}

			if ( ! $stream ) {
				return 'Watch MPEG Dash Error: Missing channel name!';
			}

			// get channel id $postID
			global $wpdb;
			$postID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name( $stream ) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );

			// auto transcoding
			if ( $options['transcodingAuto'] ) {
				$streamName = self::transcodeStream( $stream, 1 ); // require transcoding name
			}

			// get compatible stream
			$sourceProtocol = get_post_meta( $postID, 'stream-protocol', true );
			if ( $sourceProtocol == 'rtsp' ) {
				$stream_hls = get_post_meta( $postID, 'stream-hls', true );
				if ( $stream_hls ) {
					$streamName = $stream_hls;
				}
			}

			if ( ! $streamName ) {
				$streamName = $stream;
			}

			if ( $streamName ) {
				$streamURL = $options['httpstreamer'] . $streamName . '/manifest.mpd';

				$dir           = $options['uploadsPath'] . '/_thumbs';
				$thumbFilename = "$dir/" . $stream . '.jpg';
				$thumbUrl      = self::path2url( $thumbFilename ) . '?nocache=' . ( floor( time() / 10 ) % 100 );

				// Shaka Player https://github.com/google/shaka-player
				// scripts/shaka-player/shaka-player.compiled.min.js
				// https://cdnjs.cloudflare.com/ajax/libs/shaka-player/3.0.1/shaka-player.compiled.min.js

				wp_enqueue_script( 'shaka-dash', plugin_dir_url( __FILE__ ) . 'scripts/shaka-player/shaka-player.compiled.min.js' );

				$codecVideo = get_post_meta( $postID, 'stream-codec-video', true );
				$codecAudio = get_post_meta( $postID, 'stream-codec-audio', true );

				$offline = 0;
				$edate   = get_post_meta( $postID, 'edate', true );
				if ( time() - $edate > 60 ) {
					$offline_video = get_post_meta( $postID, 'offline_video', true );
					if ( $offline_video ) {
						$videoURL = self::vsvVideoURL( $offline_video, $options );
						$offline  = 1;

					}

					if ( $videoURL ) {
						$streamURL = $videoURL;
					}
				}

$streamName = preg_replace("/[\W_]+/u", '', $stream);

$intCode = '';
if ($restreamPaused) $intCode = '<div class="ui label">Activating Stream... <i class="spinner loading icon"></i></div>';


				$htmlCode = <<<HTMLCODE
<!--MPEG:$postID:$sourceProtocol:$stream:stream_hls=$stream_hls:$codecVideo:$codecAudio:$streamURL:offlineVideo=$videoURL:paused=$restreamPaused-->
<video id="videowhisper_mpeg_$streamName" class="videowhisper_htmlvideo" width="$width" height="$height" data-dashjs-player autobuffer autoplay playsinline controls="true" poster="$thumbUrl" src="$streamURL">
    <div class="fallback" style="display:none">
    <IMG SRC="$thumbUrl">
	    <p>HTML5 MPEG Dash capable browser (i.e. Chrome) is required to open this live stream: $streamURL</p>
	</div>
</video>
<span id="sdpDataTag">$intCode</span>
<script>
var manifestUri = '$streamURL';
var offline = $offline;

var myVideo = document.getElementById("videowhisper_mpeg_$streamName");

//console.log('shaka', myVideo);

function initApp() {

  if (!offline)
  {
  shaka.polyfill.installAll();

    if (shaka.Player.isBrowserSupported())
    {
    initPlayer();
     } else {
    console.error('MPEG-DASH Shaka Player: Browser not supported!');
  }
  }

//autoplay check/button
var videoLoaded = false;

myVideo.addEventListener('loadeddata', function() {

	console.log('video loadeddata', myVideo.paused, myVideo.currentTime);

	videoLoaded = true;
	if (myVideo.paused)
	{
	   var playPromise = myVideo.play();
	   playPromise.then(function() {
	   // Automatic playback started!
	   console.log('Automatic playback started?!');
	  }).catch(function(error) {
	  jQuery("#sdpDataTag").html('<br><button class="ui button compact red" onclick="myVideo.play();jQuery(\'#sdpDataTag\').html(\'\')"><i class="play icon"></i> Tap to Play</button><br><small>' + error+ '</small>');
	  console.log('Warning: Could not autoplay $stream:', error);
	  });
    }

}, false);

  setInterval(function()
  {
	  if (!videoLoaded) if (myVideo.paused)
	  {
	   myVideo.load();
	   console.log('Warning: MPEG $stream not loaded. Trying to reload...', myVideo.currentTime, myVideo.paused);
      }

   }, 3500);



}

function initPlayer() {
  var video = document.getElementById('videowhisper_mpeg_$streamName');
  var player = new shaka.Player(video);
  window.player = player;
  player.addEventListener('error', onErrorEvent);
  player.load(manifestUri).then(function() {
	  //success
  }).catch(onError);
}

function onErrorEvent(event) {
  onError(event.detail);
}

function onError(error) {
  console.error('MPEG-DASH Player: Error code', error.code, 'object', error);
}

document.addEventListener('DOMContentLoaded', initApp);
</script>
HTMLCODE;
			} else {
				$htmlCode = '<div class="warning">MPEG Dash format is not currently available for this stream: ' . $stream . '</div>';
			}

			if ( $options['transcodingWarning'] >= 2 && ! $atts['silent'] ) {
				$htmlCode .= '<p><small>MPEG-DASH Playback: For Android and Chrome. Autoplay starts muted to prevent pausing by browser: enable sound from controls. Transcoding and HTTP based delivery technology involve high latency and availability delay (may take dozens of seconds for transcoder to start stream to become available, after broadcast starts).</small></p>';
			}

			return $htmlCode;
		}



		static function flash_video( $stream, $width = '100%', $height = '360px', $streamName = '' ) {

			$stream = sanitize_file_name( $stream );

			if ( ! $streamName ) {
				$streamName = $stream;
			}

			$swfurl  = plugin_dir_url( __FILE__ ) . 'ls/live_video.swf?ssl=1&n=' . urlencode( $stream ) . '&s=' . urlencode( $streamName );
			$swfurl .= '&prefix=' . urlencode( admin_url() . 'admin-ajax.php?action=vwls&task=' );
			$swfurl .= '&extension=' . urlencode( '_none_' );
			$swfurl .= '&ws_res=' . urlencode( plugin_dir_url( __FILE__ ) . 'ls/' );

			$bgcolor = '#333333';

			$htmlCode = <<<HTMLCODE
<div id="videowhisper_container_$stream" style="overflow:auto">
<object id="videowhisper_video_$stream" width="$width" height="$height" type="application/x-shockwave-flash" data="$swfurl">
<param name="movie" value="$swfurl"></param><param bgcolor="$bgcolor"><param name="scale" value="noscale" /> </param><param name="salign" value="lt"></param><param name="allowFullScreen"
value="true"></param><param name="allowscriptaccess" value="always"></param>
</object>
</div>
HTMLCODE;

			$htmlCode .= self::flash_warn();

			return $htmlCode;

		}

		static function videowhisper_video( $atts ) {
			$stream = '';
			$htmlCode = '';

			$options = get_option( 'VWliveStreamingOptions' );

			if ( is_single() ) {
				if ( get_post_type( $postID = get_the_ID() ) == $options['custom_post'] ) {
					$stream = get_the_title( $postID );
				}
			}

				$atts = shortcode_atts(
					array(
						'channel' => $stream,
						'width'   => '480px',
						'height'  => '360px',
						'flash'   => '0',
						'html5'   => 'auto',
						'silent'  => '0',
					),
					$atts,
					'videowhisper_video'
				);


			if ( ! $stream ) {
				$stream = sanitize_file_name( $atts['channel'] ); // parameter channel="name"
			}
			if ( ! $stream ) {
				$stream = sanitize_text_field( $_GET['n'] );
			}

			$stream = sanitize_file_name( $stream );

			$width = $atts['width'];
			if ( ! $width ) {
				$width = '100%';
			}
			$height = $atts['height'];
			if ( ! $height ) {
				$height = '640px';
			}

			if ( ! $stream ) {
				return 'Watch Video Error: Missing channel name!';

			}

			//check
			$offline = self::channelInvalid( $stream );
			if ( $offline ) {
				return "<!--VideoWhisper-Video:$stream--><div class='ui $interfaceClass segment'>" . $offline . '</div>';
			} else $htmlCode .= "<!--VideoWhisper-Video:valid:$stream-->";

			// channel post id
			global $wpdb;
			$postID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name( $stream ) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );

			// handle paused restreams
			$restreamPaused = false;
			$vw_ipCamera = get_post_meta( $postID, 'vw_ipCamera', true );
			if ( $vw_ipCamera ) {
				$restreamPaused = get_post_meta( $postID, 'restreamPaused', true );
				$htmlCode .= self::restreamPause( $postID, $stream, $options );
			}

			if ( ! $atts['flash'] ) {

				// source info
				$streamProtocol = get_post_meta( $postID, 'stream-protocol', true ); // rtsp/rtmp
				$streamType     = get_post_meta( $postID, 'stream-type', true ); // stream-type: flash/webrtc/restream/playlist
				$streamMode     = get_post_meta( $postID, 'stream-mode', true ); // direct/safari_pc

				$directWebRTC = 0;
				// if ($streamProtocol == 'rtsp' && $streamMode =='direct') $directWebRTC = 1; //preferred html5 for low latency /safari_pc on h264
				if ( $streamProtocol == 'rtsp' && ! $vw_ipCamera ) {
					$directWebRTC = 1; // preferred html5 for low latency
				}

				// HLS if iOS/Android detected
				$agent   = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] );
				$Android = stripos( $agent, 'Android' );
				$iOS     = ( strstr( $agent, 'iPhone' ) || strstr( $agent, 'iPod' ) || strstr( $agent, 'iPad' ) );
				$Safari  = ( strstr( $agent, 'Safari' ) && ! strstr( $agent, 'Chrome' ) );

				// offline: regular h5 playaback - no webrtc
				$isOffline = 0;
				$edate     = get_post_meta( $postID, 'edate', true );
				if ( time() - $edate > 60 ) {
					$isOffline = 1;
				}

				if ( $options['webStatus'] == 'disabled' ) {
					$isOffline = 0; // without session control can't know when offline
				}

				$htmlCode .= "<!--VideoWhisper-Video:offline=$isOffline:protocol=$streamProtocol|type=$streamType|mode=$streamMode|directWebRTC:$directWebRTC|Agent:$agent|A:$Android|I:$iOS|S:$Safari-->";

				$silent = $atts['silent'];

				$showHTML5 = 0;
				if ( $options['transcoding'] >= 3 || $options['webrtc'] >= 3 ) {
					$showHTML5 = 1; // html5 preferred
				}

				// always
				if ( $atts['html5'] == 'always' || $showHTML5 ) {
					if ( $directWebRTC && ! $isOffline ) {
						return $htmlCode . '<!--H5-WebRTC-->' . do_shortcode( "[videowhisper_webrtc_playback channel=\"$stream\" width=\"$width\" height=\"$height\" silent=\"$silent\" webstatus=\"1\"]" );
					}

					//force manifest.mpd for h265 support; comment to reintroduce HLS support
					//return $htmlCode . '<!--H5-MPEG-->' . do_shortcode( "[videowhisper_mpeg channel=\"$stream\" width=\"$width\" height=\"$height\" silent=\"$silent\" webstatus=\"1\" paused=\"$restreamPaused\"]" );


					if ( $iOS ) {
						//on iOS use HLS
						return $htmlCode . '<!--H5-HLS-->' . do_shortcode( "[videowhisper_hls channel=\"$stream\" width=\"$width\" height=\"$height\" silent=\"$silent\" webstatus=\"1\" paused=\"$restreamPaused\"]" );
					} else {
						return $htmlCode . '<!--H5-MPEG-DASH-->' . do_shortcode( "[videowhisper_mpeg channel=\"$stream\" width=\"$width\" height=\"$height\" silent=\"$silent\" webstatus=\"1\" paused=\"$restreamPaused\"]" );
					}
				}

				if ( $directWebRTC && ! $isOffline ) {
					return $htmlCode . '<!--H5-WebRTC-->' . do_shortcode( "[videowhisper_webrtc_playback channel=\"$stream\" width=\"$width\" height=\"$height\" silent=\"$silent\" webstatus=\"1\"]" );
				}


				// detect delivery mode for video interface
				if ( ( $Android && in_array( $options['detect_mpeg'], array( 'android', 'all' ) ) ) || ( ! $iOS && in_array( $options['detect_mpeg'], array( 'all' ) ) ) || ( ! $iOS && ! $Safari && in_array( $options['detect_mpeg'], array( 'nonsafari' ) ) ) ) {
					return $htmlCode . '<!--MPEG-->' . do_shortcode( "[videowhisper_mpeg channel=\"$stream\" width=\"$width\" height=\"$height\" silent=\"$silent\" webstatus=\"1\"]" );
				}


				if ( $options['detect_hls'] == 'all' || ( ( $Android || $iOS ) && in_array( $options['detect_hls'], array( 'mobile', 'safari', 'all' ) ) ) || ( $iOS && $options['detect_hls'] == 'ios' ) || ( $Safari && in_array( $options['detect_hls'], array( 'safari', 'all' ) ) ) ) {
					return $htmlCode . '<!--HLS-->' . do_shortcode( "[videowhisper_hls channel=\"$stream\" width=\"$width\" height=\"$height\" silent=\"$silent\" webstatus=\"1\"]" );
				}
			}

			//default: HLS
					return $htmlCode . '<!--HLS Default-->' . do_shortcode( "[videowhisper_hls channel=\"$stream\" width=\"$width\" height=\"$height\" silent=\"$silent\" webstatus=\"1\"]" );


		}

		// ! WebRTC


		static function videowhisper_webrtc_broadcast( $atts ) {
			$stream = '';

			if ( ! is_user_logged_in() ) {
				return "<div class='error'>" . __( 'Broadcasting not allowed: Only logged in users can broadcast!', 'live-streaming' ) . '</div>';
			}

			$options = get_option( 'VWliveStreamingOptions' );

			// username used with application
			$userName = $options['userName'];
			if ( ! $userName ) {
				$userName = 'user_nicename';
			}

			$current_user = wp_get_current_user();
			if ( $current_user->$userName ) {
				$username = sanitize_file_name( $current_user->$userName );
			}

			$postID = 0;
			if ( $options['postChannels'] ) {
				if ( is_single() ) {
					$postID = get_the_ID();
					if ( get_post_type( $postID ) == $options['custom_post'] ) {
						$stream = get_the_title( $postID );
					} else {
						$postID = 0;
					}
				}
			}

			$atts = shortcode_atts(
				array(
					'channel'    => $stream,
					'channel_id' => $postID,
				),
				$atts,
				'videowhisper_webrtc_broadcast'
			);

			$postID = $atts['channel_id'];

			if ( $stream && ! $postID ) {
				global $wpdb;
				$postID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name( $stream ) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );
			}

			if ( ! $stream ) {
				$stream = $atts['channel']; // 2. shortcode param
			}

			if ( $options['anyChannels'] ) {
				if ( ! $stream ) {
					$stream = sanitize_file_name( $_GET['n'] ); // 3. GET param
				}
			}

			if ( $options['userChannels'] ) {
				if ( ! $stream ) {
					$stream = $username; // 4. username
				}
			}

					$stream = sanitize_file_name( $stream );

			if ( ! $stream ) {
				return "<div class='error'>Can't load WebRTC broadcasting interface: Missing channel name!</div>";
			}

			if ( $postID > 0 && $options['postChannels'] ) {
				$channel = get_post( $postID );
				if ( $channel->post_author != $current_user->ID ) {
					return "<div class='error'>Only owner can broadcast his channel (#$postID)!</div>";
				}
			}

				// $keyBroadcast = md5('vw' . $options['webKey'] . $current_user->ID  . $postID);
				// $streamQuery = $stream . '?channel_id=' . $postID . '&userID=' . urlencode($current_user->ID ) . '&key=' . urlencode($keyBroadcast);

				$streamQuery = self::webrtcStreamQuery( $current_user->ID, $postID, 1, $stream, $options );

				// $clientIP = VWliveStreaming::get_ip_address();
				// VWliveStreaming::webSessionSave($stream, 1, 'webrtc-broadcast', $clientIP); //pre-approve session for rtmp check

				// detect browser
				$agent   = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] );
				$Android = stripos( $agent, 'Android' );
				$iOS     = ( strstr( $agent, 'iPhone' ) || strstr( $agent, 'iPod' ) || strstr( $agent, 'iPad' ) );
				$Safari  = ( strstr( $agent, 'Safari' ) && ! strstr( $agent, 'Chrome' ) );
				$Firefox = stripos( $agent, 'Firefox' );

				// publishing WebRTC - save info
				update_post_meta( $postID, 'stream-protocol', 'rtsp' );
				update_post_meta( $postID, 'stream-type', 'webrtc' );

			if ( ! $iOS && $Safari ) {
				update_post_meta( $postID, 'stream-mode', 'safari_pc' ); // safari on pc encoding profile issues
			} else {
				update_post_meta( $postID, 'stream-mode', 'direct' );
			}

				self::enqueueUI();

				wp_enqueue_script( 'webrtc-adapter', plugin_dir_url( __FILE__ ) . 'scripts/adapter.js', array( 'jquery' ) );
				wp_enqueue_script( 'videowhisper-webrtc-broadcast', plugin_dir_url( __FILE__ ) . 'scripts/vwrtc-publish.js', array( 'jquery', 'webrtc-adapter' ) );

				$wsURLWebRTC       = $options['wsURLWebRTC'];
				$applicationWebRTC = $options['applicationWebRTC'];

				$videoCodec = $options['webrtcVideoCodec']; // 42e01f
				$audioCodec = $options['webrtcAudioCodec']; // opus

				$videoBitrate = (int) $options['webrtcVideoBitrate'];
			if ( ! $videoBitrate ) {
				$videoBitrate = 500;
			}


				$audioBitrate = (int) $options['webrtcAudioBitrate'];
		if ( ! $audioBitrate ) {
				$audioBitrate = 32;
			}


				$htmlCode .= "<!--WebRTC_Broadcast|$agent|i:$iOS|a:$Android|Sa:$Safari|Ff:$Firefox-->";

				$interfaceClass = $options['interfaceClass'];

				$broadcastCode = <<<HTMLCODE
		<div class="videowhisper-webrtc-camera">
		<video id="localVideo" class="videowhisper_htmlvideo" autoplay playsinline muted style="widht:640px;height:480px;"></video>
		</div>

		<div class="ui segment form $interfaceClass">
			<span id="sdpDataTag">Connecting...</span>

<hr class="divider" />
	 <div class="field">
        <input type=button class="ui button compact $interfaceClass" id="buttonBroadcast" value="Broadcast" /> <span id="buttonDataTag"></span>
    </div>

    <div class="field ">
        <label for="videoSource">Video Source </label><select class="ui dropdown $interfaceClass" id="videoSource"></select>
    </div>

    <div class="field ">
        <label for="videoResolution">Video Quality </label><select class="ui dropdown $interfaceClass" id="videoResolution"></select>
    </div>

	 <div class="field ">
        <label for="audioSource">Audio Source </label><select class="ui dropdown $interfaceClass" id="audioSource"></select>
    </div>

    		</div>


		<script type="text/javascript">

			var userAgent = navigator.userAgent;
		    var wsURL = "$wsURLWebRTC";
			var streamInfo = {applicationName:"$applicationWebRTC", streamName:"$streamQuery", sessionId:"[empty]"};
			var userData = {param1:"value1","videowhisper":"webrtc-broadcast"};
			var videoBitrateMax = $videoBitrate;
			var audioBitrate = $audioBitrate;
			var videoFrameRate = "29.97";
			var videoChoice = "$videoCodec";
			var audioChoice = "$audioCodec";

		jQuery( document ).ready(function() {
 		browserReady();
 		jQuery(".ui.dropdown").dropdown();

});
		</script>
HTMLCODE;

				// AJAX Chat for WebRTC broadcasting

				// htmlchat ui
				// css
				wp_enqueue_style( 'jScrollPane', plugin_dir_url( __FILE__ ) . '/htmlchat/js/jScrollPane/jScrollPane.css' );
				wp_enqueue_style( 'htmlchat', plugin_dir_url( __FILE__ ) . '/htmlchat/css/chat-broadcast.css' );

				// js
				wp_enqueue_script( 'jquery' );
				wp_enqueue_script( 'jScrollPane-mousewheel', plugin_dir_url( __FILE__ ) . '/htmlchat/js/jScrollPane/jquery.mousewheel.js' );
				wp_enqueue_script( 'jScrollPane', plugin_dir_url( __FILE__ ) . '/htmlchat/js/jScrollPane/jScrollPane.min.js' );
				wp_enqueue_script( 'htmlchat', plugin_dir_url( __FILE__ ) . '/htmlchat/js/script.js', array( 'jquery', 'jScrollPane' ) );

				$ajaxurl = admin_url() . 'admin-ajax.php?action=vwls_htmlchat&room=' . urlencode( sanitize_file_name( $stream ) );

				$loginCode = '<a href="' . wp_login_url() . '">Login is required to chat!</a>';
				$buttonSFx = plugin_dir_url( __FILE__ ) . 'htmlchat/message.mp3';
				$tipsSFx   = plugin_dir_url( __FILE__ ) . 'htmlchat/tips/';

				$interfaceClass = $options['interfaceClass'];

			if ( $options['tips'] ) {

				// broacaster: only balance

				$tipbuttonCodes = '<p>Viewers can send you tips. Balance will update shortly after receiving a tip.</p>';

				$tipsCode = <<<TIPSCODE
<div id="tips" class="ui $interfaceClass segment form">
<div class="inline fields">

<div class="ui label olive large $interfaceClass">
  <i class="money bill alternate icon large"></i>Balance: <span id="balanceAmount" class="inline"> - </span>
</div>

$tipbuttonCodes
</div>
</div>
TIPSCODE;
			}

				$htmlCode .= <<<HTMLCODE
<div id="videochatContainer">
<!--Room:$stream-->
<div id="streamContainer">
$broadcastCode
</div>

<div id="chatContainer">

    <div id="chatUsers" class="ui segment $interfaceClass"></div>

    <div id="chatLineHolder"></div>

    <div id="chatBottomBar" class="ui segment $interfaceClass">
    	<div class="tip"></div>

        <form id="loginForm" method="post" action="" class="ui form $interfaceClass">
$loginCode
		</form>

        <form id="submitForm" method="post" action="" class="ui form $interfaceClass">
            <input id="chatText" name="chatText" class="rounded" maxlength="255" />
            <input id="submitButton" type="submit" class="ui button" value="Submit" />
        </form>

    </div>
</div>
</div>
$tipsCode

<script>
var vwChatAjax= '$ajaxurl';
var vwChatButtonSFx =  '$buttonSFx';
var vwChatTipsSFx =  '$tipsSFx';
</script>

HTMLCODE;


$browser  = '';
if (stripos( $_SERVER['HTTP_USER_AGENT'], 'Chrome') !== false) $browser='Chrome';
elseif (stripos( $_SERVER['HTTP_USER_AGENT'], 'Safari') !== false) $browser = 'Safari';

if ($browser == 'Safari' )
{
$htmlCode .=  "<p class='ui segment $interfaceClass'> ⚠️ " . 'In latest Safari you need to disable NSURLSession WebSocket for WebRTC streaming to work:';

if ( strstr( $_SERVER['HTTP_USER_AGENT'], " Mobile/") ) $htmlCode .=  "<br> " . 'On iOS Mobile, open Settings application. Tap Safari, then Advanced, and then Experimental Features, disable NSURLSession WebSocket.</p>';
else $htmlCode .=  "<br> " . 'On PC: From Safari menu > Preferences ... > Advanced tab, enable Show Develop menu. Then from Develop menu > Experimental features disable NSURLSession WebSocket.</p>';
}



			if ( $options['transcodingWarning'] >= 1 ) {
				$htmlCode .= "<p class='ui segment $interfaceClass'><small>⚠️ WebRTC will play directly where possible, depending on settings and viewer device. If transcoding is needed for playback, it may take up to a couple of minutes for transcoder to start and WebRTC published stream to become available for HTML5 WebRTC/HLS/DASH playback.</small></p>";
			}

				return $htmlCode;

		}

		static function videowhisper_webrtc_playback( $atts ) {
			$stream  = '';
			$postID  = 0;
			$options = get_option( 'VWliveStreamingOptions' );

			if ( is_single() ) {
				$postID = get_the_ID();
				if ( get_post_type( $postID ) == $options['custom_post'] ) {
					$stream = get_the_title( $postID );
				} else {
					$postID = 0;
				}
			}

			if ( ! $postID ) {
				global $wpdb;
				$postID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name( $stream ) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );
			}

			$atts = shortcode_atts(
				array(
					'channel'    => $stream,
					'width'      => $options['videoWidth'],
					'height'     => $options['videoHeight'],
					'channel_id' => $postID,
					'silent'     => 0,
				),
				$atts,
				'videowhisper_webrtc_playback'
			);

			if ( ! $stream ) {
				$stream = sanitize_file_name( $atts['channel'] ); // parameter channel="name"
			}
			if ( ! $stream ) {
				$stream = sanitize_file_name( $_GET['n'] );
			}

			$stream = sanitize_file_name( $stream );

			$width = $atts['width'];
			if ( ! $width ) {
				$width = '100%';
			}
			$height = $atts['height'];
			if ( ! $height ) {
				$height = '360px';
			}

			if ( ! $stream ) {
				return 'WebRTC Playback Error: Missing channel name!';
			}

			$userID = 0;
			if ( is_user_logged_in() ) {
				$userName = $options['userName'];
				if ( ! $userName ) {
					$userName = 'user_nicename';
				}
				$current_user = wp_get_current_user();
				if ( $current_user->$userName ) {
					$username = sanitize_file_name( $current_user->$userName );
				}
				$userID = $current_user->ID;
			}

			$postID = $atts['channel_id'];
			if ( ! $postID ) {
				global $wpdb;
				$postID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name( $stream ) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );
			}

			// detect browser
			$agent   = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] );
			$Android = stripos( $agent, 'Android' );
			$iOS     = ( strstr( $agent, 'iPhone' ) || strstr( $agent, 'iPod' ) || strstr( $agent, 'iPad' ) );
			$Safari  = ( strstr( $agent, 'Safari' ) && ! strstr( $agent, 'Chrome' ) );
			$Firefox = stripos( $agent, 'Firefox' );

			$codeMuted = '';
			if ( $Safari ) {
				// $codeMuted = 'muted';
			}

			$htmlCode = '';

			// WebRTC playback: detect source type and transcode if necessary
			$sourceProtocol = get_post_meta( $postID, 'stream-protocol', true );

			if ( $sourceProtocol == 'rtsp' ) {
				$stream_webrtc = $stream;
			} else {
				$stream_webrtc = self::transcodeStreamWebRTC( $stream, $postID, $options );
			}

			// $keyPlayback = md5('vw' . $options['webKey']. $postID . $userID);
			// $streamQuery = $stream_webrtc . '?channel_id=' . $postID . '&userID=' . urlencode($userID) . '&key=' . urlencode($keyPlayback);

			$streamQuery = self::webrtcStreamQuery( isset($current_user) ? $current_user->ID : 0 , $postID, 0, $stream_webrtc, $options );

			// $clientIP = VWliveStreaming::get_ip_address();
			// VWliveStreaming::webSessionSave($stream_webrtc, 0, 'webrtc-playback', $clientIP); //approve session for rtmp check

			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'webrtc-adapter', plugin_dir_url( __FILE__ ) . 'scripts/adapter.js', array( 'jquery' ) );
			wp_enqueue_script( 'videowhisper-webrtc-playback', plugin_dir_url( __FILE__ ) . 'scripts/vwrtc-playback.js', array( 'jquery', 'webrtc-adapter' ) );

			$wsURLWebRTC       = $options['wsURLWebRTC'];
			$applicationWebRTC = $options['applicationWebRTC'];

			$videoURL = '';
			$srcCode = '';

				$edate = get_post_meta($postID, 'edate', true);
				if (time() - $edate > 60) //offline: show offline video if set
				{
					$offline_video = get_post_meta($postID, 'offline_video', true);
					if ($offline_video) $videoURL = self::vsvVideoURL($offline_video, $options);
					if ($videoURL) $streamURL = $videoURL;
					if ($streamURL) $srcCode = 'src="' . $streamURL . '"';
				}


			$htmlCode .= <<<HTMLCODE
		<div class="videowhisper-webrtc-video">
		<!--$postID|vu:$videoURL-->
		<video id="remoteVideo" class="videowhisper_htmlvideo" autoplay playsinline controls $codeMuted style="max-width:100%" $srcCode></video>
		<!--$sourceProtocol:$stream_webrtc-->
		</div>

		<span id="sdpDataTag"></span>

		<script type="text/javascript">

			var videoBitrate = 600;
			var audioBitrate = 64;
			var videoFrameRate = "29.97";

			var userAgent = navigator.userAgent;
		    var wsURL = "$wsURLWebRTC";
			var streamInfo = {applicationName:"$applicationWebRTC", streamName:"$streamQuery", sessionId:"[empty]"};
			var userData = {param1:"value1","videowhisper":"webrtc-playback"};

		jQuery( document ).ready(function() {
 		browserReady();
});

		</script>
HTMLCODE;

			if ( ! $atts['silent'] ) {
				if ( $Safari ) {
					$htmlCode .= 'WebRTC playback is not currently fully supported for Safari (may take longer to start) if not broadcasting with Chrome. If live video does not play or freezes, try opening this URL in Chrome or Firefox!<BR>Additionally playback is muted to allow auto play: enable audio from player controls.';
				}
			}
			return $htmlCode;
		}



		static function videowhisper_htmlchat_playback( $atts ) {
			// ! playback with html5 video and ajax chat

			$stream  = '';
			$options = get_option( 'VWliveStreamingOptions' );

			if ( is_single() ) {
				$postID = get_the_ID();
				if ( get_post_type( $postID ) == $options['custom_post'] ) {
					$stream = get_the_title( $postID );
				} else {
					$postID = 0;
				}
			}

			if ( ! $postID ) {
				global $wpdb;
				$postID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name( $stream ) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );
			}

			$atts = shortcode_atts(
				array(
					'channel'     => $stream,
					'post_id'     => $postID,
					'videowidth'  => $options['watchWidth'],
					'videoheight' => $options['watchHeight'],
					'paused' => false,
				),
				$atts,
				'videowhisper_htmlchat_playback'
			);

			$restreamPaused = boolval($atts['paused']);

			if ( ! $stream ) {
				$stream = sanitize_file_name( $atts['channel'] ); // parameter channel="name"
			}
			if ( ! $stream ) {
				$stream = sanitize_file_name( $_GET['room'] );
			}
			if ( ! $stream ) {
				$stream = sanitize_file_name( $_GET['n'] );
			}

			$stream = sanitize_file_name( $stream );

			$room = $stream;

			if ( $atts['post_id'] ) {
				$postID = intval( $atts['post_id'] );
			}
			/*
			if (!$postID)
			{
				global $wpdb;

				$postID = $wpdb->get_var( $sql = 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_title = \'' . $room . '\' and post_type=\''.$options['custom_post'] . '\' LIMIT 0,1' );

			}
			*/
			$videowidth = $atts['videowidth'];
			if ( ! $videowidth ) {
				$videowidth = '640px';
			}
			$videoheight = $atts['videoheight'];
			if ( ! $videoheight ) {
				$videoheight = '480px';
			}

			if ( ! $room ) {
				return 'HTML AJAX Chat Error: Missing room name!';
			}

			// ui
			self::enqueueUI();

			// AJAS Chat for viewers

			// htmlchat ui
			// css
			wp_enqueue_style( 'jScrollPane', plugin_dir_url( __FILE__ ) . '/htmlchat/js/jScrollPane/jScrollPane.css' );
			wp_enqueue_style( 'htmlchat', plugin_dir_url( __FILE__ ) . '/htmlchat/css/chat-watch.css' );

			// js
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'jScrollPane-mousewheel', plugin_dir_url( __FILE__ ) . '/htmlchat/js/jScrollPane/jquery.mousewheel.js' );
			wp_enqueue_script( 'jScrollPane', plugin_dir_url( __FILE__ ) . '/htmlchat/js/jScrollPane/jScrollPane.min.js' );
			wp_enqueue_script( 'htmlchat', plugin_dir_url( __FILE__ ) . '/htmlchat/js/script.js', array( 'jquery', 'jScrollPane' ) );

			$ajaxurl = admin_url() . 'admin-ajax.php?action=vwls_htmlchat&room=' . urlencode( $room );

			$videoCode = do_shortcode( '[videowhisper_video channel="' . $room . '" width="'.$videowidth.'" height="'.$videoheight.'" silent="1" html5="always" paused="'. $restreamPaused . '"]' );

			$loginCode = '<a class="ui button" href="' . wp_login_url() . '">Login is required to chat!</a>';

			$buttonSFx = plugin_dir_url( __FILE__ ) . 'htmlchat/message.mp3';
			$tipsSFx   = plugin_dir_url( __FILE__ ) . 'htmlchat/tips/';

				$interfaceClass = $options['interfaceClass'];

			if ( $options['tips'] ) {

				// tip options
				$tipOptions = stripslashes( $options['tipOptions'] );
				if ( $tipOptions ) {
					$p = xml_parser_create();
					xml_parse_into_struct( $p, trim( $tipOptions ), $vals, $index );
					$error = xml_get_error_code( $p );
					xml_parser_free( $p );

					if ( is_array( $vals ) ) {
						foreach ( $vals as $tKey => $tip ) {
							if ( $tip['tag'] == 'TIP' ) {
								// var_dump($tip['attributes']);
								$amount = intval( $tip['attributes']['AMOUNT'] );
								if ( ! $amount ) {
									$amount = 1;
								}
								$label = $tip['attributes']['LABEL'];
								if ( ! $label ) {
									$label = '$1 Tip';
								}
								$note = $tip['attributes']['NOTE'];
								if ( ! $note ) {
									$label = 'Tip';
								}
								$sound = $tip['attributes']['SOUND'];
								if ( ! $sound ) {
									$sound = 'coins1.mp3';
								}
								$image = $tip['attributes']['IMAGE'];
								if ( ! $image ) {
									$image = 'gift1.png';
								}

								$imageURL = $tipsSFx . $image;

								$tipbuttonCodes = <<<TBCODE
	<div class="tipButton ui labeled button small $interfaceClass" tabindex="0" amount="$amount" label="$label" note="$note" sound="$sound" image="$image" data-title="Gift $amount!" data-content="Send $amount as gift!">

  <div class="ui button">
    <img class="mini image avatar" src="$imageURL"> $label
  </div>

  <a class="ui basic label small">
    $amount
  </a>

</div>
TBCODE;
							}
						}
					}
				}

								$balanceURL = '#';
				if ( $options['balancePage'] ) {
					$balanceURL = get_permalink( $options['balancePage'] );
				}

				$tipsCode = <<<TIPSCODE
<div id="tips" class="ui $interfaceClass segment form">
<div class="inline fields">

<a href="$balanceURL" target="_balance" class="ui label olive large $interfaceClass">
  <i class="money bill alternate icon large"></i>Balance: <span id="balanceAmount" class="inline"> - </span>
</a>

$tipbuttonCodes
</div>
</div>
TIPSCODE;
			}

			$htmlCode = <<<HTMLCODE
<div id="videochatContainer">
<!--$room-->
<div id="streamContainer">
$videoCode
</div>

<div id="chatContainer">
    <div id="chatUsers" class="ui segment $interfaceClass"></div>

    <div id="chatLineHolder"></div>

    <div id="chatBottomBar" class="ui segment $interfaceClass">

    	<div class="tip"></div>

        <form id="loginForm" method="post" action="" class="ui form $interfaceClass">
$loginCode
		</form>

        <form id="submitForm" method="post" action="" class="ui form $interfaceClass">
            <input id="chatText" name="chatText" class="rounded" maxlength="255" />
            <input id="submitButton" id="submit" type="submit" class="ui button" value="Submit" />
        </form>

    </div>

</div>
</div>
$tipsCode

<script>
var vwChatAjax= '$ajaxurl';
var vwChatButtonSFx =  '$buttonSFx';
var vwChatTipsSFx =  '$tipsSFx';

var \$jQ = jQuery.noConflict();
\$jQ(document).ready(function(){
\$jQ('.tipButton').popup();
});

</script>
HTMLCODE;

			if ( $options['transcodingWarning'] >= 2 ) {
				$htmlCode .= '<p><small>HTML5 Video Stream Playback: <b>Tap to Play</b> as autoplay is not possible in some browsers. Transcoding and HTTP based delivery technology involve extra latency and availability delay related to processing video stream. If stream is live but not showing, please wait or reload page if it does not start in few seconds.</small></p>';
			}

			if ( $options['transcodingWarning'] >= 1 ) {
				if ( $options['transcoding'] ) {
					if ( $options['transcoding'] < 4 ) {
									//$htmlCode .= '<p><a class="ui button secondary" href="' . add_query_arg( array( 'flash-view' => '' ), get_permalink( $postID ) ) . '">Try Flash View (PC)</a></p>';
					}
				}
			}

				return $htmlCode;

		}

		static function rtmp_address( $userID, $postID, $broadcaster, $session, $room ) {


			$options = self::getOptions();

			if ($options['rtmpServer'] == 'videowhisper') {
				return $options['videowhisperRTMP'] . '//' . trim($options['vwsAccount']) . '/' . $session->room  . '?pin=' . self::getPin($postID, 'broadcast', $options); 
			}

			//wowza 
			// ?session&room&key&broadcaster&broadcasterid

			if ( $broadcaster ) {
				$key = md5( 'vw' . $options['webKey'] . intval( $userID ) . intval( $postID ) );
				return $options['rtmp_server'] . '?' . urlencode( $session ) . '&' . urlencode( $room ) . '&' . $key . '&1&' . $userID . '&videowhisper';
			} else {
				$keyView = md5( 'vw' . $options['webKey'] . intval( $postID ) );
				return $options['rtmp_server'] . '?' . urlencode( '-name-' ) . '&' . urlencode( $room ) . '&' . $keyView . '&0' . '&videowhisper';
			}

			return $options['rtmp_server'];

		}

		static function webrtcStreamQuery( $userID, $postID, $broadcaster, $stream_webrtc, $options = null, $transcoding = 0 ) {

			if ( ! $options ) {
				$options = get_option( 'VWliveStreamingOptions' );
			}
			$clientIP = self::get_ip_address();

			if ( $broadcaster ) {
				$key = md5( 'vw' . $options['webKey'] . intval( $userID ) . intval( $postID ) );

			} else {
				$key = md5( 'vw' . $options['webKey'] . intval( $postID ) );
			}

			$streamQuery = $stream_webrtc . '?channel_id=' . intval( $postID ) . '&userID=' . intval( $userID ) . '&key=' . urlencode( $key ) . '&ip=' . urlencode( $clientIP ) . '&transcoding=' . $transcoding;
			return $streamQuery;

		}


		static function videowhisper_external( $atts ) {

			if ( ! is_user_logged_in() ) {
				return "<div class='error'>Only logged in users can broadcast!</div>";
			}

			$options = get_option( 'VWliveStreamingOptions' );

			$userName = $options['userName'];
			if ( ! $userName ) {
				$userName = 'user_nicename';
			}

			// username
			$current_user = wp_get_current_user();

			if ( $current_user->$userName ) {
				$username = sanitize_file_name( $current_user->$userName );
			}

			$postID = 0;
			if ( $options['postChannels'] ) {

				$postID = get_the_ID();
				if ( is_single() ) {
					if ( get_post_type( $postID ) == $options['custom_post'] ) {
						$stream = get_the_title( $postID );
					}
				}
			}

			$atts = shortcode_atts(
				array(
					'channel' => $stream,
				),
				$atts,
				'videowhisper_external'
			);

			if ( ! $stream ) {
				$stream = $atts['channel']; // 2. shortcode param
			}

			if ( $options['anyChannels'] ) {
				if ( ! $stream ) {
					$stream = sanitize_file_name( $_GET['n'] ); // 3. GET param
				}
			}

			if ( $options['userChannels'] ) {
				if ( ! $stream ) {
					$stream = $username; // 4. username
				}
			}

					$stream = sanitize_file_name( $stream );

			if ( ! $stream ) {
				return "<div class='error'>Can't load broadcasting details: Missing channel name!</div>";
			}

			if ( $postID > 0 && $options['postChannels'] ) {
				$channel = get_post( $postID );
				if ( $channel->post_author != $current_user->ID ) {
					return "<div class='error'>Only owner can broadcast (#$postID)!</div>";
				}
			}


			$codeWatch = htmlspecialchars( do_shortcode( "[videowhisper_watch channel=\"$stream\"]" ) );
			$roomLink  = self::roomURL( $stream );

			$hlsURL = self::appUserHLS( $stream, $options );

			if ($options['rtmpServer'] == 'videowhisper') {
				$rtmpAddress =  trim( $options['videowhisperRTMP'] );
				$streamKey = trim($options['vwsAccount']) . '/' . $stream. '?pin=' . self::getPin($postID, 'broadcast', $options); 
				$rtmpURL = $rtmpAddress . '//' . $stream;

				$application = substr( strrchr( $rtmpAddress, '/' ), 1 );

				$adrp1 = explode( '://', $rtmpAddress );
				$adrp2 = explode( '/', $adrp1[1] );
				$adrp3 = explode( ':', $adrp2[0] );

				$server = $adrp3[0];
				$port   = $adrp3[1];
			}
			else 
			{
				$rtmpAddress     = self::rtmp_address( $current_user->ID, $postID, true, $stream, $stream );
				$rtmpAddressView = self::rtmp_address( $current_user->ID, $postID, false, $stream, $stream );

				$application = substr( strrchr( $rtmpAddress, '/' ), 1 );

				$adrp1 = explode( '://', $rtmpAddress );
				$adrp2 = explode( '/', $adrp1[1] );
				$adrp3 = explode( ':', $adrp2[0] );

				$server = $adrp3[0];
				$port   = $adrp3[1];

				$streamKey = $stream;
			if ( ! $port ) {
				$port = 1935;
			}
		}

				$htmlCode = <<<HTMLCODE
<h3>Broadcast Video</h3>
<div class="ui segment">
<P>After reviewing your encoder setting fields, retrieve settings you need from strings below.</P>
<p>RTMP Address / URL (full address, contains server, port if different than default 1935, application, parameters):
<div class="ui action input">
  <input type="text" value="$rtmpAddress">
  <button class="ui teal right labeled icon button">
    <i class="copy icon"></i>
    Copy
  </button>
</div>
</p>
<p>Application (contains application name and parameters):<BR><I>$application</I></p>
<p>Stream Name / Key (name of channel):<BR><I>$streamKey</I></p>
<p>Server:<BR><I>$server</I></p>
<p>Port:<BR><I>$port</I></p>
<p>Stream Address (RTMP Address with Stream Name):<BR><I>$rtmpAddress/$streamKey</I></p>
</div>
<p>Use specs above to broadcast channel '$stream' using external applications (Larix iOS/Android, OBS Open Broadcaster Software, XSplit, Adobe Flash Media Live Encoder, Wirecast).<br>Keep your secret broadcasting rtmp address safe as anyone having it may broadcast to your channel. As external encoders don't comunicate with site scripts, externally broadcast channel shows as online only if RTMP Session Control is enabled.</p>

<p>Copy and paste strings: For mobile encoders send the strings above in an email or notes sharing app. In GoCoder copy and paste each string and save settings before switching between apps to get next string.</p>
<p>Warning: If advanced session control is enabled you can't connect at same time with web broadcasting interface and external encoder (duplicate named session will be refused by server). Connect with external encoder using details above and participate in chat with Watch interface.</p>

<h3>Playback Video</h3>
<div class="ui segment">
<p>Use HLS URL to playback live stream in HTML5 Browser or by embedding in <VIDEO> tag.</p>
<p>HLS URL:<BR><I>$hlsURL</I></p>
</div>
<h3>Chat &amp; Video Embed</h3>
<div class="ui segment">
<p><I>$codeWatch</I></p>
</div>
HTMLCODE;

				return $htmlCode;

		}


		static function videowhisper_external_playback( $atts ) {

			if ( ! is_user_logged_in() ) {
				return "<div class='error'>Only logged in users can access info!</div>";
			}

			$options = get_option( 'VWliveStreamingOptions' );

			$userName = $options['userName'];
			if ( ! $userName ) {
				$userName = 'user_nicename';
			}

			// username
			$current_user = wp_get_current_user();

			if ( $current_user->$userName ) {
				$username = sanitize_file_name( $current_user->$userName );
			}

			$postID = 0;
			if ( $options['postChannels'] ) {

				$postID = get_the_ID();
				if ( is_single() ) {
					if ( get_post_type( $postID ) == $options['custom_post'] ) {
						$stream = get_the_title( $postID );
					}
				}
			}

			$atts = shortcode_atts(
				array(
					'channel' => $stream,
				),
				$atts,
				'videowhisper_external_playback'
			);

			if ( ! $stream ) {
				$stream = $atts['channel']; // 2. shortcode param
			}

			if ( $options['anyChannels'] ) {
				if ( ! $stream ) {
					$stream = sanitize_file_name( $_GET['n'] ); // 3. GET param
				}
			}

			if ( $options['userChannels'] ) {
				if ( ! $stream ) {
					$stream = $username; // 4. username
				}
			}

					$stream = sanitize_file_name( $stream );

			if ( ! $stream ) {
				return "<div class='error'>Can't load channel details: Missing channel name!</div>";
			}

			if ( $postID > 0 && $options['postChannels'] ) {
				$channel = get_post( $postID );
				if ( $channel->post_author != $current_user->ID ) {
					return "<div class='error'>Only owner can access channel (#$postID)!</div>";
				}
			}

				$rtmpAddress = self::rtmp_address( $current_user->ID, $postID, true, $stream, $stream );
			$rtmpAddressView = self::rtmp_address( $current_user->ID, $postID, false, $stream, $stream );

			$codeWatch = htmlspecialchars( do_shortcode( "[videowhisper_watch channel=\"$stream\"]" ) );
			$roomLink  = self::roomURL( $stream );

			$application = substr( strrchr( $rtmpAddress, '/' ), 1 );

			$adrp1 = explode( '://', $rtmpAddress );
			$adrp2 = explode( '/', $adrp1[1] );
			$adrp3 = explode( ':', $adrp2[0] );

			$server = $adrp3[0];
			$port   = $adrp3[1] ?? '';
			if ( ! $port ) {
				$port = 1935;
			}

			$htmlCode = <<<HTMLCODE
<h3>Playback Video</h3>
<div class="ui segment ">
<p>RTMP Address / URL (full address, contains server, port if different than default 1935, application, parameters):<BR><I>$rtmpAddressView</I></p>
<p>Stream Name:<BR><I>$stream</I></p>
<p>Stream Address (RTMP Address with Stream Name, for players that require these settings in 1 string):<BR><I>$rtmpAddressView/$stream</I></p>
</div>
<p>Use specs above to setup playback using 3rd party RTMP players (Strobe, JwPlayer, FlowPlayer), restreaming servers or apps.</p>
<h3>Chat &amp; Video Embed</h3>
<div class="ui segment">
<p><I>$codeWatch</I></p>
</div>
HTMLCODE;

			self::enqueueUI();

			return $htmlCode;

		}

		static function videowhisper_external_broadcast( $atts ) {

			if ( ! is_user_logged_in() ) {
				return '<div class="error"">' . __( 'Only logged in users can broadcast!', 'live-streaming' ) . '</div>';
			}

			$options = get_option( 'VWliveStreamingOptions' );

			$userName = $options['userName'];
			if ( ! $userName ) {
				$userName = 'user_nicename';
			}

			// username
			$current_user = wp_get_current_user();

			if ( $current_user->$userName ) {
				$username = sanitize_file_name( $current_user->$userName );
			}

			$postID = 0;
			if ( $options['postChannels'] ) {

				$postID = get_the_ID();
				if ( is_single() ) {
					if ( get_post_type( $postID ) == $options['custom_post'] ) {
						$stream = get_the_title( $postID );
					}
				}
			}

			$atts = shortcode_atts(
				array(
					'channel' => $stream,
				),
				$atts,
				'videowhisper_external_broadcast'
			);

			if ( ! $stream ) {
				$stream = $atts['channel']; // 2. shortcode param
			}

			if ( $options['anyChannels'] ) {
				if ( ! $stream ) {
					$stream = sanitize_file_name( $_GET['n'] ); // 3. GET param
				}
			}

			if ( $options['userChannels'] ) {
				if ( ! $stream ) {
					$stream = $username; // 4. username
				}
			}

					$stream = sanitize_file_name( $stream );

			if ( ! $stream ) {
				return "<div class='error'>Can't load broadcasting details: Missing channel name!</div>";
			}

			if ( $postID > 0 && $options['postChannels'] ) {
				$channel = get_post( $postID );
				if ( $channel->post_author != $current_user->ID ) {
					return "<div class='error'>Only owner can broadcast (#$postID)!</div>";
				}
			}


			$roomLink = self::roomURL( $stream );


		if ($options['rtmpServer'] == 'videowhisper') {
			$rtmpAddress =  trim( $options['videowhisperRTMP'] );
			$streamKey = trim($options['vwsAccount']) . '/' .$stream  . '?pin=' . self::getPin( $postID, 'broadcast', $options); 
			$rtmpURL = $rtmpAddress . '//' . $stream;

		}
		else 
		{
			$rtmpAddress = self::rtmp_address( $current_user->ID, $postID, true, $stream, $stream );
			$streamKey = $stream;
		}

		$application = substr( strrchr( $rtmpAddress, '/' ), 1 );

		$adrp1 = explode( '://', $rtmpAddress );
		$adrp2 = explode( '/', $adrp1[1] );
		$adrp3 = explode( ':', $adrp2[0] );

		$server = $adrp3[0];
		$port   = $adrp3[1] ?? '';

			if ( ! $port ) {
				$port = 1935;
			}

			// $videoBitrate
			$bitrateCode      = '';
			$videoBitrate = 0;
			$audioBitrate = 0;

				$sessionsVars = self::varLoad( $options['uploadsPath'] . '/sessionsApp' );
			if ( is_array( $sessionsVars ) ) {
				if ( array_key_exists( 'limitClientRateIn', $sessionsVars ) ) {
					$limitClientRateIn = intval( $sessionsVars['limitClientRateIn'] ) * 8 / 1000;

					if ( $limitClientRateIn ) {
							$videoBitrate = $limitClientRateIn - 100;
							$audioBitrate = 96;
					}
				}
			}


			//vws limitations
			if ($options['maxVideoBitrate'] ?? false)  $videoBitrate = $options['maxVideoBitrate'];
			if ($options['maxAudioBitrate'] ?? false)  $audioBitrate = $options['maxAudioBitrate'];

			//also limit to values set by admin if lower
			if ($options['webrtcVideoBitrate']) if ($videoBitrate > $options['webrtcVideoBitrate']) $videoBitrate = $options['webrtcVideoBitrate'];
			if ($options['webrtcAudioBitrate']) if ($audioBitrate > $options['webrtcAudioBitrate']) $audioBitrate = $options['webrtcAudioBitrate'];

		if ($videoBitrate || $audioBitrate)	
		{	
		$bitrateCode .= '
<div class="ui segment">Maximum Video Bitrate<br>
<div class="ui action input">
  <input type="text" class="copyInput" value="' . $videoBitrate . '">
  <button class="ui teal right labeled icon button copyButton">
    <i class="copy icon"></i>
    Copy
  </button>
</div>
<small>
<br>Use this value or lower for video bitrate, depending on resolution. A static background and less motion requires less bitrate than movies, sports, games.
<br>For OBS Settings: Output > Streaming > Video Bitrate.
<br>Warning: Trying to broadcast higher bitrate than allowed by streaming server will result in disconnects/failures.
</small>
</div>
										';

							$bitrateCode .= '
<div class="ui segment">Maximum Audio Bitrate<br>
<div class="ui action input">
  <input type="text" class="copyInput" value="' . $audioBitrate . '">
  <button class="ui teal right labeled icon button copyButton">
    <i class="copy icon"></i>
    Copy
  </button>
</div>
<small>
<br>Use this value or lower for audio bitrate. If you want to use higher Audio Bitrate, lower Video Bitrate to compensate for higher audio.
<br>For OBS Settings: Output > Streaming > Audio Bitrate.
<br>Warning: Trying to broadcast higher bitrate than allowed by streaming server will result in disconnects/failures.
</small>
</div>
';

				}

			$htmlCode = <<<HTMLCODE
<h3>Broadcast Video</h3>
<div class="ui segment">
<P>After reviewing your encoder setting fields, retrieve settings you need from strings below.</P>

<div class="ui segment">RTMP Address / OBS Stream URL (full streaming address, contains: server, port if different than default 1935, application and control parameters, key)
<div class="ui action input fluid">
  <input type="text" class="copyInput" value="$rtmpAddress">
  <button class="ui teal right labeled icon button copyButton">
    <i class="copy icon"></i>
    Copy
  </button>
</div>
</div>

<div class="ui segment">Stream Name / OBS Stream Key (name of channel)<br>
<div class="ui action input fluid">
  <input type="text" class="copyInput" value="$streamKey">
  <button class="ui teal right labeled icon button copyButton">
    <i class="copy icon"></i>
    Copy
  </button>
</div>
</div>

$bitrateCode

Some broadcasting applications may require streaming details separated in different settings:
<div class="ui segment">Server<br>
<div class="ui action input">
  <input type="text" class="copyInput" value="$server">
  <button class="ui teal right labeled icon button copyButton">
    <i class="copy icon"></i>
    Copy
  </button>
</div>
</div>

<div class="ui segment">Port<br>
<div class="ui action input">
  <input type="text" class="copyInput" value="$port">
  <button class="ui teal right labeled icon button copyButton">
    <i class="copy icon"></i>
    Copy
  </button>
</div>
</div>

<div class="ui segment">Application (contains application name and control parameters, key)
<div class="ui action input fluid">
  <input type="text" class="copyInput" value="$application">
  <button class="ui teal right labeled icon button copyButton">
    <i class="copy icon"></i>
    Copy
  </button>
</div>
</div>

<div class="ui segment">Stream Address (contains RTMP Address with Stream Name, everything in one setting)
<div class="ui action input fluid">
  <input type="text" class="copyInput" value="$rtmpAddress//$streamKey">
  <button class="ui teal right labeled icon button copyButton">
    <i class="copy icon"></i>
    Copy
  </button>
</div>
</div>

<p>Use specs above to broadcast channel '<a href="$roomLink">$stream</a>' using external applications (Larix iOS/Android, <a href="https://obsproject.com">OBS Open Broadcaster Software</a>, XSplit, Adobe Flash Media Live Encoder, Wirecast).<br>Keep your secret broadcasting rtmp address safe as anyone having it may broadcast to your channel. As external encoders don't comunicate with site scripts, externally broadcast channel shows as online only if RTMP Session Control / Notify is configured on streaming server.</p>

<p>Copy and paste strings: For mobile encoders send the strings above in an email or notes sharing app. In GoCoder copy and paste each string and save settings before switching between apps to get next string.</p>
<p>Warning: If advanced session control is enabled you can't connect at same time with web broadcasting interface and external encoder (duplicate named session will be refused by server). Connect with external encoder using details above and if you want to participate in chat you can do that from website with Watch interface.</p>

<SCRIPT>
var popupTimer;

function delayPopup(popup) {
    popupTimer = setTimeout(function() { $(popup).popup('hide') }, 4200);
}

jQuery(document).ready(function () {
    jQuery('.copyButton').click(function (){
        clearTimeout(popupTimer);

        var input = jQuery(this).closest('div').find('.copyInput');

        /* Select the text field */
        input.select();

        /* Copy the text inside the text field */
        document.execCommand("copy");

		console.log('Copy');

        $(this)
            .popup({
                title    : 'Successfully copied to clipboard!',
                content  : 'You can now paste this content.',
                on: 'manual',
                exclusive: true
            })
            .popup('show')
        ;

        // Hide popup after 5 seconds
        delayPopup(this);


    });

});
</SCRIPT>
HTMLCODE;

			self::enqueueUI();

			return $htmlCode;

		}




		static function videowhisper_broadcast( $atts ) {
			$stream = '';
			$htmlCode  = '';

			if ( ! is_user_logged_in() ) {
				return "<div class='error'>" . __( 'Broadcasting not allowed: Only logged in users can broadcast!', 'live-streaming' ) . '</div>'
					. '<BR>' . self::loginRequiredWarning();
			}

			$options = get_option( 'VWliveStreamingOptions' );

			// username used with application
			$userName = $options['userName'];
			if ( ! $userName ) {
				$userName = 'user_nicename';
			}

			$current_user = wp_get_current_user();

			if ( $current_user->$userName ) {
				$username = sanitize_file_name( $current_user->$userName );
			}

			$postID = 0;
			if ( $options['postChannels'] ) {
				$postID = get_the_ID();
				if ( is_single() ) {
					if ( get_post_type( $postID ) == $options['custom_post'] ) {
						$stream = get_the_title( $postID );
					}
				}
			}

			$atts = shortcode_atts(
				array(
					'channel' => $stream,
					'flash'   => '0',
				),
				$atts,
				'videowhisper_broadcast'
			);

			if ( ! $stream ) {
				$stream = $atts['channel']; // 2. shortcode param
			}

			if ( $options['anyChannels'] ) {
				if ( ! $stream ) {
					$stream = sanitize_file_name( $_GET['n'] ); // 3. GET param
				}
			}

			if ( $options['userChannels'] ) {
				if ( ! $stream ) {
					$stream = $username; // 4. username
				}
			}

					$stream = sanitize_file_name( $stream );

			if ( ! $stream ) {
				return "<div class='error'>Can't load broadcasting interface: Missing channel name!</div>";
			}

				// get post ID
				global $wpdb;
			$postID = $wpdb->get_var( $sql = 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_title = \'' . $stream . '\' and post_type=\'' . $options['custom_post'] . '\' LIMIT 0,1' );

			if ( $postID > 0 && $options['postChannels'] ) {
				$channel = get_post( $postID );
				if ( $channel->post_author != $current_user->ID ) {
					return "<div class='error'>Only owner can broadcast his channel ($stream #$postID)!</div>";
				}
			}

			if ($options['html5videochat']) return do_shortcode("[videowhisper_h5vls_app room=\"$stream\" webcam_id=\"$postID\"]");

			if ( ! $atts['flash'] ) {

				// HLS if iOS/Android detected
				$agent   = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] );
				$Android = stripos( $agent, 'Android' );
				$iOS     = ( strstr( $agent, 'iPhone' ) || strstr( $agent, 'iPod' ) || strstr( $agent, 'iPad' ) );
				$Safari  = ( strstr( $agent, 'Safari' ) && ! strstr( $agent, 'Chrome' ) );
				$Firefox = stripos( $agent, 'Firefox' );

				$htmlCode .= "<!--VideoWhisper-Broadcast-Agent:$agent|A:$Android|I:$iOS|S:$Safari|F:$Firefox-->";

				$showHTML5 = 0;

				if ( $Android || $iOS ) {
					$showHTML5 = 1;
				}
				if ( $options['webrtc'] >= 4 ) {
					$showHTML5 = 1; // preferred
				}

				if ( $showHTML5 ) {
					$htmlCode .= do_shortcode( '[videowhisper_webrtc_broadcast channel="' . $stream . '"]' );
					return $htmlCode;
				}
			}

			if ( $options['webrtc'] >= 2 ) {
				if ( $postID ) {
					$htmlCode .= '<p><a class="ui button secondary" href="' . add_query_arg( array( 'webrtc-broadcast' => '' ), get_permalink( $postID ) ) . '">' . __( 'Try HTML5 WebRTC Broadcast', 'live-streaming' ) . '</a></p>';
				}
			}

			if ( ! $options['transcoding'] ) {
				return $htmlCode; // done
			}

			// transcoding interface
			if ( $stream ) {

				// access keys
				if ( $current_user ) {
					$userkeys   = $current_user->roles;
					$userkeys[] = $current_user->user_login;
					$userkeys[] = $current_user->ID;
					$userkeys[] = $current_user->user_email;
					$userkeys[] = $current_user->display_name;
				}

				$admin_ajax = admin_url() . 'admin-ajax.php';

				if ( self::inList( $userkeys, $options['transcode'] ) ) { // transcode feature enabled
					if ( $options['transcoding'] ) {
						if ( $options['transcodingManual'] ) {
											$htmlCode .= <<<HTMLCODE
<div id="vwinfo">
Stream Transcoding<BR>
<a href='#' class="button" id="transcoderon">ENABLE</a>
<a href='#' class="button" id="transcoderoff">DISABLE</a>
<div id="videowhisperTranscoder">A stream must be broadcast for transcoder to start. Activate to make stream available for iOS HLS.</div>
<p align="right">(<a href="javascript:void(0)" onClick="vwinfo.style.display='none';">hide</a>)</p>
</div>

<style type="text/css">
<!--

#vwinfo
{
	float: right;
	width: 25%;
	position: absolute;
	bottom: 10px;
	right: 10px;
	text-align:left;
	font-size: 14px;
	padding: 10px;
	margin: 10px;
	background-color: #666;
	border: 1px dotted #AAA;
	z-index: 1;

	filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#999', endColorstr='#666'); /* for IE */
	background: -webkit-gradient(linear, left top, left bottom, from(#999), to(#666)); /* for webkit browsers */
	background: -moz-linear-gradient(top,  #999,  #666); /* for firefox 3.6+ */

	box-shadow: 2px 2px 2px #333;


	-moz-border-radius: 9px;
	border-radius: 9px;
}

#vwinfo > a {
	color: #F77;
	text-decoration: none;
}

#vwinfo > .button {
	-moz-box-shadow:inset 0px 1px 0px 0px #f5978e;
	-webkit-box-shadow:inset 0px 1px 0px 0px #f5978e;
	box-shadow:inset 0px 1px 0px 0px #f5978e;
	background:-webkit-gradient( linear, left top, left bottom, color-stop(0.05, #db4f48), color-stop(1, #944038) );
	background:-moz-linear-gradient( center top, #db4f48 5%, #944038 100% );
	filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#db4f48', endColorstr='#944038');
	background-color:#db4f48;
	border:1px solid #d02718;
	display:inline-block;
	color:#ffffff;
	font-family:Verdana;
	font-size:12px;
	font-weight:normal;
	font-style:normal;
	text-decoration:none;
	text-align:center;
	text-shadow:1px 1px 0px #810e05;
	padding: 5px;
	margin: 2px;
}
#vwinfo > .button:hover {
	background:-webkit-gradient( linear, left top, left bottom, color-stop(0.05, #944038), color-stop(1, #db4f48) );
	background:-moz-linear-gradient( center top, #944038 5%, #db4f48 100% );
	filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#944038', endColorstr='#db4f48');
	background-color:#944038;
}

-->
</style>

<script type="text/javascript">
	var \$j = jQuery.noConflict();
	var loaderTranscoder;
	var transcodingOn = false;


	\$j.ajaxSetup ({
		cache: false
	});
	var ajax_load = "Loading...";

	\$j("#transcoderon").click(function(){
	transcodingOn = true;
	if (loaderTranscoder) if (loaderTranscoder.abort === 'function') loaderTranscoder.abort();
	loaderTranscoder = \$j("#videowhisperTranscoder").html(ajax_load).load("$admin_ajax?action=vwls_trans&task=enable&stream=$stream");
	});

	\$j("#transcoderoff").click(function(){
	transcodingOn = false;
	if (loaderTranscoder) if (loaderTranscoder.abort === 'function') loaderTranscoder.abort();
	loaderTranscoder = \$j("#videowhisperTranscoder").html(ajax_load).load("$admin_ajax?action=vwls_trans&task=close&stream=$stream");
	});
</script>
HTMLCODE;
						}
					}
				}
			}

			return $htmlCode;
		}


static function path2url( $file, $Protocol = 'https://' ) {
			if ( is_ssl() && $Protocol == 'http://' ) {
				$Protocol = 'https://';
			}

			$url = $Protocol . $_SERVER['HTTP_HOST'];

			// on godaddy hosting uploads is in different folder like /var/www/clients/ ..
			$upload_dir = wp_upload_dir();
			if ( strstr( $file, $upload_dir['basedir'] ) ) {
				return $upload_dir['baseurl'] . str_replace( $upload_dir['basedir'], '', $file );
			}

			// folder under WP path
			require_once ABSPATH . 'wp-admin/includes/file.php';
			if ( strstr( $file, get_home_path() ) ) {
				return site_url() . '/' . str_replace( get_home_path(), '', $file );
			}

			// under document root
			if ( strstr( $file, $_SERVER['DOCUMENT_ROOT'] ) ) {
				return $url . str_replace( $_SERVER['DOCUMENT_ROOT'], '', $file );
			}

			return $url . $file;
		}



		static function format_time( $t, $f = ':' ) {
			// t = seconds, f = separator
			return sprintf( '%02d%s%02d%s%02d', floor( $t / 3600 ), $f, floor( $t / 60 ) % 60, $f, $t % 60 );
		}

		static function format_age( $t ) {
			if ( $t < 120 ) {
				return __( 'LIVE', 'live-streaming' ); // 2 min
			}
			if ( $t + 3 > time() ) {
				return __( 'Never', 'live-streaming' );
			}
			return sprintf( '%d%s%d%s%d%s', floor( $t / 86400 ), 'd ', floor ( $t / 3600 ) % 24, 'h ', floor( $t / 60 ) % 60, 'm' );
		}


		// ! Watcher (viewer) Online Status for App + AJAX chat, not Flash
		static function updateOnline( $username, $room, $postID = 0, $type = 2, $current_user = '', $options = '' ) {
			// $type: 1 = flash full, 2 = html5 chat, 3 = flash video, 4 = html5 video, 5 = voyeur flash, 6 = voyeur html5, 7 = html5 videochat

			if ( ! $room && ! $postID ) {
				return; // no room, no update
			}

			$disconnect  = '';
			$s     = $u = $username;
			$r     = $room;
			$ztime = time();

			if ( ! $options ) 				$options = self::getOptions();


			if ( ! $current_user ) {
				$current_user = wp_get_current_user();
			}

			$uid = 0;
			if ( $current_user ) {
				$uid = $current_user->ID;
			}

			global $wpdb;
			$table_sessions = $wpdb->prefix . 'vw_lwsessions';
			$table_channels = $wpdb->prefix . 'vw_lsrooms';

			if ( ! $postID ) {
				$postID = $wpdb->get_var( $sql = 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_title = \'' . $room . '\' and post_type=\'' . $options['custom_post'] . '\' LIMIT 0,1' );
			}

			$redate = intval( get_post_meta( $postID, 'edate', true ) );
			$roptions = '';

			// create or update session
			// status: 0 current, 1 closed, 2 billed

			$sqlS    = "SELECT * FROM `$table_sessions` WHERE session='$s' AND status < 2 AND rid='$postID' LIMIT 1";
			$session = $wpdb->get_row( $sqlS );

			if ( ! $session ) {

				if ( $ztime - $redate > $options['onlineExpiration1'] ) {
					$rsdate = 0; // broadcaster offline
				} else {
					$rsdate = $redate; // broadcaster online: mark room start date
				}

				$clientIP = self::get_ip_address();

				$sql = "INSERT INTO `$table_sessions` ( `session`, `username`, `uid`, `room`, `rid`, `roptions`, `rsdate`, `redate`, `message`, `sdate`, `edate`, `status`, `type`, `ip`) VALUES ('$s', '$u', '$uid', '$r', '$postID', '$roptions', '$rsdate', '$redate', '$m', '$ztime', '$ztime', 0, $type, '$clientIP')";
				$wpdb->query( $sql );
				$session = $wpdb->get_row( $sqlS );
			} else {
				$id = $session->id;

				// broadcaster was offline and came online: update room start time (rsdate)
				if ( $session->rsdate == 0 && $redate > $session->sdate ) {
					$rsdate = $redate;
				} else {
					$rsdate = $session->rsdate; // keep unchanged (0 or start time)
				}

				$sql = "UPDATE `$table_sessions` set edate='$ztime', rsdate='$rsdate', redate='$redate', roptions = '$roptions' WHERE id='$id' LIMIT 1";
				$wpdb->query( $sql );
			}

			// also update view time (based on original session)

			$sqlC    = "SELECT * FROM $table_channels WHERE name='" . $session->room . "' LIMIT 0,1";
			$channel = $wpdb->get_row( $sqlC );

			if ( ! $channel ) {

				//ip camera?
				$vw_ipCamera = get_post_meta( $postID, 'vw_ipCamera', true );

				if ($vw_ipCamera) {
					$sql = "INSERT INTO `$table_channels` ( `owner`, `name`, `sdate`, `edate`, `rdate`,`status`, `type`) VALUES ('$uid', '$r', $ztime, $ztime, $ztime, 0, 1)";
					$wpdb->query( $sql );
					$channel = $wpdb->get_row( $sqlC );
					if ( $channel ) {
						return "Could not add channel to $table_channels: " . $sql;
					}
				}
				else return "Room not found in $table_channels: " . $session->room;
			}

			// calculate time in ms based on previous request
			$lastTime    = $session->edate * 1000;
			$currentTime = $ztime * 1000;

			// update room time
			$expTime = $options['onlineExpiration0'] + 30;

			$dS = floor( ( $currentTime - $lastTime ) / 1000 );
			if ( $dS > $expTime || $dS < 0 ) {
				return "Web server out of sync ($dS > $expTime)!"; // Updates should be faster than 3 minutes; fraud attempt?
			}

			$channel->wtime += $dS;

			// update
			$sql = "UPDATE `$table_channels` set wtime = " . $channel->wtime . " where id = '" . $channel->id . "'";
			$wpdb->query( $sql );

			// update post
			if ( $postID ) {
				update_post_meta( $postID, 'wtime', $channel->wtime );
			}

			// update user watch time, disconnect if exceeded limit

			if ( $current_user ) {
				$user = $current_user;
			} else {
				$user = get_user_by( 'login', $u );
			}

			if ( $user ) {
				if ( self::updateUserWatchtime( $user, $dS, $options ) ) {
					$disconnect = urlencode( 'User watch time limit exceeded!' );
				}
			}

			if ( ! $disconnect ) {
				// update access time
				if ( is_user_logged_in() ) {
					update_post_meta( $postID, 'accessedUser', $ztime );
				}
				update_post_meta( $postID, 'accessed', $ztime );
			}

			return $disconnect;

		}

		// ! AJAX
		static function wp_enqueue_scripts() {
			wp_enqueue_script( 'jquery' );
		}


		// ! AJAX HTML Chat

		static function wp_ajax_vwls_htmlchat() {
			$options = get_option( 'VWliveStreamingOptions' );
			// output clean
			ob_clean();

			// Handling the supported tasks:

			global $wpdb;
			$table_sessions = $wpdb->prefix . 'vw_lwsessions'; // viewers
			$table_chatlog  = $wpdb->prefix . 'vw_vwls_chatlog';

			$room   = sanitize_file_name( $_GET['room'] );
			$postID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_title = '" . $room . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );
			if ( ! $postID ) {
				throw new Exception( 'HTML Chat: Channel not found: ' . $room );
			}

			$post = get_post( $postID );

			// user
			$username      = '';
			$user_id       = 0;
			$isBroadcaster = 0;

			if ( is_user_logged_in() ) {
				$current_user = wp_get_current_user();

				if ( isset( $current_user ) ) {
					$user_id = $current_user->ID;

					$userName = $options['userName'];
					if ( ! $userName ) {
						$userName = 'user_nicename';
					}
					if ( $current_user->$userName ) {
						$username = urlencode( sanitize_file_name( $current_user->$userName ) );
					}

					$isBroadcaster = ( $user_id == $post->post_author );

					if ( $isBroadcaster ) {
						$username = $room;
					}
				}
			} else {
				if ( isset( $_COOKIE['htmlchat_username'] ) ) {
					$username = sanitize_text_field( $_COOKIE['htmlchat_username'] );
				} else {
					$username = 'Guest_' . base_convert( time() % 36 * rand( 0, 36 * 36 ), 10, 36 );
					setcookie( 'htmlchat_username', $username );
				}
			}

			$ztime = time();

			switch ( $_GET['task'] ) {

				// tips
				case 'getBalance':
					$balance = 0;
					if ( $user_id ) {
						$balance = self::balance( $user_id, false, $options );
					}

					$response = array(
						'balance' => $balance,
					);

					break;

				case 'sendTip':
					if ( ! isset( $current_user ) ) {
						if ( ! $options['htmlchatVisitorWrite'] ) {
							throw new Exception( 'You are not logged in!' );
						}
					}

					if ( $options['tipCooldown'] ) {
						$lastTip = get_user_meta( $current_user->ID, 'vwTipLast', true );
						if ( $lastTip + $options['tipCooldown'] > time() ) {
							throw new Exception( 'Already sent tip recently!' );
						}
					}

					$label  = $message = sanitize_text_field( $_POST['label'] );
					$amount = intval( $_POST['amount'] );
					$note   = sanitize_text_field( $_POST['note'] );
					$sound  = sanitize_text_field( $_POST['sound'] );
					$image  = sanitize_text_field( $_POST['image'] );

					$meta          = array();
					$meta['sound'] = $sound;
					$meta['image'] = $image;
					$metaS         = serialize( $meta );

					if ( ! $message ) {
						$error = 'No message!';
					}

					if ( $error ) {
						$response = array(
							'status'   => 0,
							'insertID' => 'error',
							'success'  => 0,
							'error'    => $error,
						);

					} else {
						$message = preg_replace( '/([^\s]{12})(?=[^\s])/', '$1' . '<wbr>', $message ); // break long words <wbr>:Word Break Opportunity
						$message = "<I>$message</I>"; // mark system message for tip

						$sql = "INSERT INTO `$table_chatlog` ( `username`, `room`, `message`, `mdate`, `type`, `meta`, `user_id`) VALUES ('$username', '$room', '$message', $ztime, '2', '$metaS', '$user_id')";
						$wpdb->query( $sql );

						$response = array(
							'status'   => 1,
							'insertID' => $wpdb->insert_id,
						);

						// also update chat log file
						if ( $message ) {

							$message = strip_tags( $message, '<p><a><img><font><b><i><u>' );

							$message = date( 'F j, Y, g:i a', $ztime ) . " <b>$username</b>: $message";

							// generate same private room folder for both users
							if ( $private ) {
								if ( $private > $session ) {
									$proom = $session . '_' . $private;
								} else {
									$proom = $private . '_' . $session;
								}
							}

							$dir = $options['uploadsPath'];
							if ( ! file_exists( $dir ) ) {
								mkdir( $dir );
							}

							$dir .= "/$room";
							if ( ! file_exists( $dir ) ) {
								mkdir( $dir );
							}

							if ( $proom ) {
								$dir .= "/$proom";
								if ( ! file_exists( $dir ) ) {
									mkdir( $dir );
								}
							}

							$day = date( 'y-M-j', time() );

							$dfile = fopen( $dir . "/Log$day.html", 'a' );
							fputs( $dfile, $message . '<BR>' );
							fclose( $dfile );
						}

						// tip

						$balance = self::balance( $current_user->ID );

						$response['success']         = true;
						$response['balancePrevious'] = $balance;
						$response['postID']          = $postID;
						$response['userID']          = $current_user->ID;
						$response['amount']          = $amount;

						if ( $amount > $balance ) {
							$response['success'] = false;
							$response['error']   = 'Tip amount greater than balance!';
							$response['balance'] = $balance;
						} else {

							$ztime = time();

							$tipInfo = "$label: $note";

							// client cost
							$paid = number_format( $amount, 2, '.', '' );
							self::transaction( 'channel_tip', $current_user->ID, - $paid, 'Tip for <a href="' . self::roomURL( $room ) . '">' . $room . '</a>. (' . $tipInfo . ')', $ztime );
							$response['paid'] = $paid;

							// performer earning
							$received = number_format( $amount * $options['tipRatio'], 2, '.', '' );
							self::transaction( 'channel_tip_earning', $post->post_author, $received, 'Tip from ' . $username . ' (' . $tipInfo . ')', $ztime );

							// save last tip time
							update_user_meta( $current_user->ID, 'vwTipLast', time() );

							$response['broadcaster'] = $post->post_author;
							$response['received']    = $received;

							// update balance and report
							$response['balance'] = self::balance( $current_user->ID );

						}
					}

					break;

				// chat
				case 'checkLogged':
					$response = array( 'logged' => false );

					if ( isset( $current_user ) ) {
						$response['logged'] = true;

						$response['loggedAs'] = array(
							'name'   => $username,
							'avatar' => get_avatar_url( $current_user->ID ),
							'userID' => $current_user->ID,
						);

					} elseif ( $options['htmlchatVisitorWrite'] ) {
						$response['logged'] = true;

						$response['loggedAs'] = array(
							'name' => $username,
						);
					}

					$disconnected = self::updateOnline( $username, $room, $postID, 2, $current_user, $options );

					if ( $disconnected ) {
						$response['disconnect'] = $disconnected;
						$response['logged']     = false;
					}

					break;

				case 'submitChat':
					// $response = Chat::submitChat();

					if ( ! isset( $current_user ) ) {
						if ( ! $options['htmlchatVisitorWrite'] ) {
						$response ['error'] = 'Writing in chat is disabled for visitors!';
						break;

						} else {
							// visitor
						}
					}

					$message = sanitize_text_field( $_POST['chatText'] );
					$message = preg_replace( '/([^\s]{12})(?=[^\s])/', '$1' . '<wbr>', $message ); // break long words <wbr>:Word Break Opportunity

					$sql = "INSERT INTO `$table_chatlog` ( `username`, `room`, `message`, `mdate`, `type`, `user_id`) VALUES ('$username', '$room', '$message', $ztime, '2', '$user_id')";
					$wpdb->query( $sql );

					$response = array(
						'status'   => 1,
						'insertID' => $wpdb->insert_id,
					);

					// also update chat log file
					if ( $message ) {

						$message = strip_tags( $message, '<p><a><img><font><b><i><u>' );

						$message = date( 'F j, Y, g:i a', $ztime ) . " <b>$username</b>: $message";

						// generate same private room folder for both users
						if ( $private ) {
							if ( $private > $session ) {
								$proom = $session . '_' . $private;
							} else {
								$proom = $private . '_' . $session;
							}
						}

						$dir = $options['uploadsPath'];
						if ( ! file_exists( $dir ) ) {
							mkdir( $dir );
						}

						$dir .= "/$room";
						if ( ! file_exists( $dir ) ) {
							mkdir( $dir );
						}

						if ( $proom ) {
							$dir .= "/$proom";
							if ( ! file_exists( $dir ) ) {
								mkdir( $dir );
							}
						}

						$day = date( 'y-M-j', time() );

						$dfile = fopen( $dir . "/Log$day.html", 'a' );
						fputs( $dfile, $message . '<BR>' );
						fclose( $dfile );
					}

					break;

				case 'getUsers':
					// old session cleanup

					// close sessions
					$closeTime = time() - $options['onlineExpiration0']; // > client statusInterval
					$sql       = "UPDATE `$table_sessions` SET status = 1 WHERE status = 0 AND edate < $closeTime";
					$wpdb->query( $sql );

					$users = array();

					// type 5,6 voyeur: do not show
					$sql      = "SELECT * FROM `$table_sessions` where room='$room' and status='0' AND type < 5 ORDER by sdate ASC";
					$userRows = $wpdb->get_results( $sql );

					if ( $wpdb->num_rows > 0 ) {
						foreach ( $userRows as $userRow ) {
							$user = array();

							$user_id      = $userRow->uid;
							$user['name'] = $userRow->session;
							$user['id']   = $user_id;

							// avatar
							if ( ! $user_id ) {
								$wpUser  = get_user_by( 'login', $userRow->session );
								$user_id = $wpUser->ID;
							}

							if ( $user_id ) {
								if ( $options['userPicture'] == 'avatar' || ( $options['userPicture'] == 'avatar_broadcaster' && $isBroadcaster ) ) {
									$user['avatar'] = get_avatar_url( $user_id );
								}
							}

							$users [] = $user;
						}
					}
					$response = array(
						'users' => $users,
						'total' => count( $userRows ),
					);
					break;

				case 'getChats':
					$disconnect = self::updateOnline( $username, $room, $postID, 2, $current_user, $options );

					if ( ! $disconnect ) {
						// clean old chat logs
						$closeTime = time() - 900; // only keep for 15min
						$sql       = "DELETE FROM `$table_chatlog` WHERE mdate < $closeTime";
						$wpdb->query( $sql );

						// retrieve only messages since user came online
						$sdate = 0;
						if ( $session ) {
							$sdate = $session->sdate;
						}

						$chats = array();

						$lastID = intval( $_GET['lastID'] );

						$sql = "SELECT * FROM `$table_chatlog` WHERE room='$room' AND id > $lastID AND mdate > $sdate ORDER BY mdate DESC LIMIT 0,20";
						$sql = "SELECT * FROM ($sql) items ORDER BY mdate ASC";

						$chatRows = $wpdb->get_results( $sql );

						if ( $wpdb->num_rows > 0 ) {
							foreach ( $chatRows as $chatRow ) {
														$chat = array();

								if ( $chatRow->meta ) {
									$meta = unserialize( $chatRow->meta );

									if ( $meta['sound'] ) {
										$chat['sound'] = $meta['sound'];
									}
									if ( $meta['image'] ) {
										$chat['image'] = $meta['image'];
									}
								}

								$chat['id']     = $chatRow->id;
								$chat['author'] = $chatRow->username;
								$chat['text']   = $chatRow->message;

								$chat['time'] = array(
									'hours'   => gmdate( 'H', $chatRow->mdate ),
									'minutes' => gmdate( 'i', $chatRow->mdate ),
								);

								$uid = $chatRow->user_id;
								if ( ! $uid ) {
									$wpUser = get_user_by( $userName, $userRow->session );
									if ( ! $wpUser ) {
										$wpUser = get_user_by( 'login', $chatRow->username );
									}
									$uid = $wpUser->ID;
								}

								$chat['avatar'] = get_avatar_url( $uid );

								$chats[] = $chat;
							}
						}

						$response = array( 'chats' => $chats );
					} else {
						$response = array(
							'chats'      => array(),
							'disconnect' => $disconnect,
						);

					}

					break;

				default:
					$response = array( 'error' => 'HTML Chat: Task not defined!' );
			}

			echo json_encode( $response );

			die();
		}


		// ! channels list ajax handler

		static function vwls_channels() {
			// list channels
			// ajax called

			// channel meta:
			// edate s
			// btime s
			// wtime s
			// viewers n
			// maxViewers n
			// maxDate s
			// hasSnapshot 1

			$options = get_option( 'VWliveStreamingOptions' );

			// widget id
			$id = sanitize_text_field( $_GET['id'] );

			// pagination
			$perPage = (int) $_GET['pp'];
			if ( ! $perPage ) {
				$perPage = $options['perPage'];
			}

			$page   = intval( $_GET['p'] ?? 0 );
			$offset = $page * $perPage;

			$perRow = (int) $_GET['pr'];

			// admin side
			$ban = intval( $_GET['ban'] ?? 0 );

						$category = (int) $_GET['cat'];

			// order
			$order_by = sanitize_file_name( $_GET['ob'] );
			if ( ! $order_by ) {
				$order_by = sanitize_text_field( $options['order_by'] );
			}
			if ( ! $order_by ) {
				$order_by = 'edate';
			}

			// options
			$selectCategory = (int) $_GET['sc'];
			$selectOrder    = (int) $_GET['so'];
			$selectPage     = (int) $_GET['sp'];

			$selectName = (int) $_GET['sn'];
			$selectTags = (int) $_GET['sg'];

			// tags,name search
			$tags = sanitize_text_field( $_GET['tags'] );
			$name = sanitize_file_name( $_GET['name'] );
			if ( $name == 'undefined' ) {
				$name = '';
			}
			if ( $tags == 'undefined' ) {
				$tags = '';
			}

			// output clean
			ob_clean();

			// thumbs dir
			$dir = $options['uploadsPath'] . '/_thumbs';

			$ajaxurl = admin_url() . 'admin-ajax.php?action=vwls_channels&pp=' . $perPage . '&pr=' . $perRow . '&sc=' . $selectCategory . '&sn=' . $selectName . '&sg=' . $selectTags . '&so=' . $selectOrder . '&sp=' . $selectPage . '&id=' . $id . '&tags=' . urlencode( $tags ) . '&name=' . urlencode( $name );
			if ( $ban ) {
				$ajaxurl .= '&ban=' . $ban; // admin side
			}

			if ( $options['postChannels'] ) {

				// ! header option controls
				$ajaxurlP  = $ajaxurl . '&p=' . $page;
				$ajaxurlPC = $ajaxurl . '&cat=' . $category;
				$ajaxurlPO = $ajaxurl . '&ob=' . $order_by;
				$ajaxurlCO = $ajaxurl . '&cat=' . $category . '&ob=' . $order_by;

				echo '<div class="ui ' . esc_attr( $options['interfaceClass'] ) . ' small equal width form" style="z-index: 20;"><div class="inline fields">';

				if ( $selectCategory ) {
					echo '<div class="field">' . wp_dropdown_categories( 'echo=0&name=category' . esc_attr( $id ) . '&hide_empty=0&class=ui+dropdown+fluid&hierarchical=1&show_option_all=' . __( 'All', 'live-streaming' ) . '&selected=' . esc_attr( $category ) ) . '</div>';
					echo '<script>var category' . esc_attr( $id ) . ' = document.getElementById("category' . esc_attr( $id ) . '"); 			category' . esc_attr( $id ) . '.onchange = function(){aurl' . esc_attr( $id ) . '=\'' . esc_url( $ajaxurlPO ) . '&cat=\'+ this.value; loadChannels' . esc_attr( $id ) . '(\'<div class="ui active inline text large loader">' . __( 'Loading Category', 'live-streaming' ) . '...</div>\')}
			</script>';
				}

				if ( $selectOrder ) {
					echo ' <div class="field"><select class="ui dropdown fluid" id="order_by' . esc_attr( $id ) . '" name="order_by' . esc_attr( $id ) . '" onchange="aurl' . esc_attr( $id ) . '=\'' . esc_url( $ajaxurlPC ) . '&ob=\'+ this.value; loadChannels' . esc_attr( $id ) . '(\'<div class=\\\'ui active inline text large loader\\\'>' . __( 'Ordering channels', 'live-streaming' ) . '...</div>\')">';
					echo '<option value="">' . __( 'Order By', 'live-streaming' ) . ':</option>';

					echo '<option value="post_date"' . ( $order_by == 'post_date' ? ' selected' : '' ) . '>' . __( 'Creation Date', 'live-streaming' ) . '</option>';

					echo '<option value="edate"' . ( $order_by == 'edate' ? ' selected' : '' ) . '>' . __( 'Broadcast Recently', 'live-streaming' ) . '</option>';

					echo '<option value="viewers"' . ( $order_by == 'viewers' ? ' selected' : '' ) . '>' . __( 'Current Viewers', 'live-streaming' ) . '</option>';

					echo '<option value="maxViewers"' . ( $order_by == 'maxViewers' ? ' selected' : '' ) . '>' . __( 'Maximum Viewers', 'live-streaming' ) . '</option>';

					if ( $options['rateStarReview'] ) {
						echo '<option value="rateStarReview_rating"' . ( $order_by == 'rateStarReview_rating' ? ' selected' : '' ) . '>' . __( 'Rating', 'live-streaming' ) . '</option>';
						echo '<option value="rateStarReview_ratingNumber"' . ( $order_by == 'rateStarReview_ratingNumber' ? ' selected' : '' ) . '>' . __( 'Most Rated', 'live-streaming' ) . '</option>';
						echo '<option value="rateStarReview_ratingPoints"' . ( $order_by == 'rateStarReview_ratingPoints' ? ' selected' : '' ) . '>' . __( 'Rate Popularity', 'live-streaming' ) . '</option>';

					}

					echo '<option value="rand"' . ( $order_by == 'rand' ? ' selected' : '' ) . '>' . __( 'Random', 'live-streaming' ) . '</option>';

					echo '</select></div>';

				}

				if ( $selectTags || $selectName ) {

					echo '<div class="field"></div>'; // separator

					if ( $selectTags ) {
						echo '<div class="field" data-tooltip="Tags, Comma Separated"><div class="ui left icon input"><i class="tags icon"></i><INPUT class="videowhisperInput" type="text" size="12" name="tags" id="tags" placeholder="' . __( 'Tags', 'live-streaming' ) . '" value="' . esc_attr( htmlspecialchars( $tags ) ) . '">
					</div></div>';
					}

					if ( $selectName ) {
						echo '<div class="field"><div class="ui left corner labeled input"><INPUT class="videowhisperInput" type="text" size="12" name="name" id="name" placeholder="' . __( 'Name', 'live-streaming' ) . '" value="' . esc_attr( htmlspecialchars( $name ) ) . '">
  <div class="ui left corner label">
    <i class="asterisk icon"></i>
  </div>
					</div></div>';
					}

					// search button
					echo '<div class="field" data-tooltip="Search by Tags and/or Name"><button class="ui fluid icon button"  type="submit" name="submit" id="submitSearch" value="' . __( 'Search', 'live-streaming' ) . '" onclick="aurl' . esc_attr( $id ) . '=\'' . esc_html( $ajaxurlCO ) . '&tags=\' + document.getElementById(\'tags\').value +\'&name=\' + document.getElementById(\'name\').value; loadChannels' . esc_attr( $id ) . '(\'<div class=\\\'ui active inline text large loader\\\'>Searching Channels...</div>\')"><i class="search icon"></i></button></div>';

				}

				echo '</div></div>';

				// ! query args

				$meta_query = array(
					'relation'    => 'AND',
					'edate'       => array(
						'key'     => 'edate',
						'compare' => 'EXISTS',
					),
					'hasSnapshot'       => array(
							'key'   => 'hasSnapshot',
							'value' => '1',
					),
				);

				// hide private rooms
				$meta_query['room_private'] = array(
					'relation' => 'OR',
					array(
						'key'   => 'room_private',
						'value' => 'false',
					),
					array(
						'key'   => 'room_private',
						'value' => '',
					),
					array(
						'key'     => 'room_private',
						'compare' => 'NOT EXISTS',
					),
				);


				$args = array(
					'post_type'      => 'channel',
					'post_status'    => 'publish',
					'posts_per_page' => $perPage,
					'offset'         => $offset,
					'order'          => 'DESC',
					'meta_query'     => $meta_query,
				);

				switch ( $order_by ) {
					case 'post_date':
						$args['orderby'] = 'post_date';
						break;

					case 'rand':
						$args['orderby'] = 'rand';
						break;

					default:
						$args['orderby']  = 'meta_value_num';
						$args['meta_key'] = $order_by;
						break;
				}

				if ( $category ) {
					$args['category'] = $category;
				}

				if ( $tags ) {
					$tagList = explode( ',', $tags );
					foreach ( $tagList as $key => $value ) {
						$tagList[ $key ] = trim( $tagList[ $key ] );
					}

					$args['tax_query'] = array(
						array(
							'taxonomy' => 'post_tag',
							'field'    => 'slug',
							'operator' => 'AND',
							'terms'    => $tagList,
						),
					);
				}

				if ( $name ) {
					$args['s'] = $name;
				}


				$postslist = get_posts( $args );

				// ! list channels
				if ( count( $postslist ) > 0 ) {
					echo '<div class="videowhisperChannelItems">';

					$k = 0;
					foreach ( $postslist as $item ) {


						if ( $perRow ) {
							if ( $k ) {
								if ( $k % $perRow == 0) {
																echo '<br />';
								}
							}
						}
						$k++;

								$edate = get_post_meta( $item->ID, 'edate', true );
							$age       = self::format_age( time() - $edate );
						$name          = sanitize_file_name( $item->post_title );

						if ( $ban ) {
							$banLink = '<a class = "button" href="admin.php?page=live-streaming-live&ban=' . urlencode( $name ) . '">Ban This Channel</a><br>';
						}

						echo '<div class="videowhisperChannel">';
						echo '<div class="videowhisperTitle">' . esc_html( $name ) . '</div>';
						echo '<div class="videowhisperTime">' . wp_kses_post( $banLink ?? '' ) . esc_html( $age ) . '</div>';

						$ratingCode = '';
						if ( $options['rateStarReview'] ) {
							$rating = get_post_meta( $item->ID, 'rateStarReview_rating', true );
							$max    = 5;
							if ( $rating > 0 ) {
								echo '<div class="videowhisperChannelRating"> <div class="ui star rating readonly yellow" data-rating="' . round( $rating * $max ) . '" data-max-rating="' . esc_attr( $max ) . '"></div> </div>';
							}
						}

						$thumbFilename = "$dir/" . $name . '.jpg';
						$url           = self::roomURL( $name );

						$noCache = '';
						if ( $age == 'LIVE' ) {
							$noCache = '?' . ( floor( time() / 10 ) % 100 );
						}

						$showImage = get_post_meta( $item->ID, 'showImage', true );
						if ($showImage) $showImage = 'auto';

						if ( !file_exists( $thumbFilename ) || $showImage == 'all' || ( $showImage == 'auto' && $age !='LIVE' )) {
							$attach_id = get_post_thumbnail_id( $item->ID );
							if ( $attach_id ) {
								$thumbFilename = get_attached_file( $attach_id );
							}
						}

						//use offline (teaser) video snapshot
						if ( $showImage == 'teaser' || ( $showImage == 'auto' && $age !='LIVE' ) )
						{
							$offline_video = get_post_meta(  $item->ID, 'offline_video', true );
							if ($offline_video)
							{
							//$videoOffline = self::vsvVideoURL( $offline_video, $options );
							$videoSnapshot = get_post_meta( $offline_video, 'video-thumbnail', true );
							if ( $videoSnapshot && file_exists( $videoSnapshot) ) $thumbFilename =  $videoSnapshot;
							}
						}

						if ( file_exists( $thumbFilename ) && ! strstr( $thumbFilename, '/.jpg' ) ) {
							echo '<a href="' . esc_url( $url ) . '"><IMG src="' . esc_url( self::path2url( $thumbFilename ) . $noCache ) . '" width="' . intval( $options['thumbWidth'] ) . 'px" height="' . intval( $options['thumbHeight'] ) . 'px"></a>';
						} else {
							echo '<a href="' . esc_url( $url ) . '"><IMG SRC="' . plugin_dir_url( __FILE__ ) . 'screenshot-3.jpg" width="' . intval( $options['thumbWidth'] ) . 'px" height="' . intval( $options['thumbHeight'] ) . 'px"></a>';
						}

						echo '</div>';

					}

					echo '</div>';

				} else {
					echo __( 'No channels match current selection. Channels get listed after being broadcast or configured as events, with snapshot/picture.', 'live-streaming' );
				}


				// ! pagination
				if ( $selectPage ) {
					echo '<br class="clearfix" />';
					echo '<div class="ui ' . esc_attr( $options['interfaceClass'] ) . ' form"><div class="inline fields">';
					if ( $page > 0 ) {
						echo ' <a class="ui labeled icon button" href="JavaScript: void()" onclick="aurl' . esc_attr( $id ) . '=\'' . esc_url( $ajaxurlCO ) . '&p=' . intval( $page - 1 ) . '\'; loadChannels' . esc_attr( $id ) . '(\'<div class=\\\'ui active inline text large loader\\\'>' . __( 'Loading previous page', 'live-streaming' ) . '...</div>\');"><i class="left arrow icon"></i> ' . __( 'Previous', 'live-streaming' ) . '</a> ';
					}

					if ( count( $postslist ) == $perPage ) {
						echo ' <a class="ui right labeled icon button" href="JavaScript: void()" onclick="aurl' . esc_attr( $id ) . '=\'' . esc_url( $ajaxurlCO ) . '&p=' . intval( $page + 1 ) . '\'; loadChannels' . esc_attr( $id ) . '(\'<div class=\\\'ui active inline text large loader\\\'>' . __( 'Loading next page', 'live-streaming' ) . '...</div>\');"> ' . __( 'Next', 'live-streaming' ) . ' <i class="right arrow icon"></i></a> ';
					}

					echo '</div></div>';

				}
			} else // channel post disabled - check db --
				{
				global $wpdb;
				$table_channels = $wpdb->prefix . 'vw_lsrooms';

				$items = $wpdb->get_results( "SELECT * FROM `$table_channels` WHERE status=1 ORDER BY edate DESC LIMIT $offset, " . $perPage );
				if ( $items ) {
					foreach ( $items as $item ) {
						$age = self::format_age( time() - $item->edate );

						if ( $ban ) {
							$banLink = '<a class = "button" href="admin.php?page=live-streaming-live&ban=' . urlencode( $item->name ) . '">Ban This Channel</a><br>';
						}

						echo '<div class="videowhisperChannel">';
						echo '<div class="videowhisperTitle">' . esc_html( $item->name ) . '</div>';
						echo '<div class="videowhisperTime">' . wp_kses_post( $banLink ) . esc_html( $age ) . '</div>';

						$thumbFilename = "$dir/" . $item->name . '.jpg';

						$url = self::roomURL( $item->name );

						$noCache = '';
						if ( $age == __( 'LIVE', 'live-streaming' ) ) {
							$noCache = '?' . ( ( time() / 10 ) % 100 );
						}

						if ( file_exists( $thumbFilename ) ) {
							echo '<a href="' . esc_url( $url ) . '"><IMG src="' . esc_url( self::path2url( $thumbFilename ) . $noCache ) . '" width="' . intval( $options['thumbWidth'] ) . 'px" height="' . intval( $options['thumbHeight'] ) . 'px"></a>';
						} else {
							echo '<a href="' . esc_url( $url ) . '"><IMG SRC="' . plugin_dir_url( __FILE__ ) . 'screenshot-3.jpg" width="' . intval( $options['thumbWidth'] ) . 'px" height="' . intval( $options['thumbHeight'] ) . 'px"></a>';
						}
						echo '</div>';
					}
				}

				echo '</div>';

				// pagination
				if ( $selectPage ) {
					echo '<div class="clearfix" />---pags--';
					if ( $page > 0 ) {
						echo ' <a class="ui labeled icon button" href="JavaScript: void()" onclick="aurl' . esc_attr( $id ) . '=\'' . esc_url( $ajaxurlCO ) . '&p=' . intval( $page - 1 ) . '\'; loadChannels' . esc_attr( $id ) . '(\'' . __( 'Loading previous page', 'live-streaming' ) . '...\');">><i class="left arrow icon"></i> ' . __( 'Previous', 'live-streaming' ) . '</a> ';
					}

					if ( count( $items ) == $perPage ) {
						echo ' <a class="ui right labeled icon button" href="JavaScript: void()" onclick="aurl' . esc_attr( $id ) . '=\'' . esc_url( $ajaxurlCO ) . '&p=' . intval( $page + 1 ) . '\'; loadChannels' . esc_attr( $id ) . '(\'' . __( 'Loading next page', 'live-streaming' ) . '...\');">' . __( 'Next', 'live-streaming' ) . '  <i class="right arrow icon"></i></a> ';
					}
				}
			}

			die;
		}

		// ! broadcast ajax handler
		static function vwls_broadcast() {
			// dedicated broadcasting page
			ob_clean();
			?>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>VideoWhisper Live Broadcast</title>
</head>
<body bgcolor="<?php echo esc_attr( $bgcolor ); ?>">
<style type="text/css">
<!--
BODY
{
	padding-right: 6px;
	margin: 0px;
	background: #333;
	font-family: Arial, Helvetica, sans-serif;
	font-size: 12px;
	color: #EEE;
}
-->
</style>
			<?php
			// include(plugin_dir_path( __FILE__ ) . "ls/flash_detect.php");

			echo do_shortcode( '[videowhisper_broadcast]' );

			die;
		}

		static function fixPath( $p ) {

			// adds ending slash if missing

			// $p=str_replace('\\','/',trim($p));
			return ( substr( $p, -1 ) != '/' ) ? $p .= '/' : $p;
		}


		static function varSave( $path, $var ) {
			file_put_contents( $path, serialize( $var ) );
		}

		static function varLoad( $path ) {
			if ( ! file_exists( $path ) ) {
				return false;
			}

			return unserialize( file_get_contents( $path ) );
		}

		static function updatePlaylist( $stream, $active = true ) {
			// updates playlist for channel $stream in global playlist
			if ( ! $stream ) {
				return;
			}

			$options = get_option( 'VWliveStreamingOptions' );

			$uploadsPath = $options['uploadsPath'];
			if ( ! file_exists( $uploadsPath ) ) {
				mkdir( $uploadsPath );
			}
			$playlistPathGlobal = $uploadsPath . '/playlist_global.txt';
			if ( ! file_exists( $playlistPathGlobal ) ) {
				self::varSave( $playlistPathGlobal, array() );
			}

			$upath = $uploadsPath . "/$stream/";
			if ( ! file_exists( $upath ) ) {
				mkdir( $upath );
			}
			$playlistPath = $upath . 'playlist.txt';
			if ( ! file_exists( $playlistPath ) ) {
				self::varSave( $playlistPath, array() );
			}

			$playlistGlobal = self::varLoad( $playlistPathGlobal );
			$playlist       = self::varLoad( $playlistPath );

			if ( $active ) {
				$playlistGlobal[ $stream ] = $playlist;
			} else {
				unset( $playlistGlobal[ $stream ] );
			}

			self::varSave( $playlistPathGlobal, $playlistGlobal );

			self::updatePlaylistSMIL();
		}

		static function updatePlaylistSMIL() {
			 $options = get_option( 'VWliveStreamingOptions' );

			// ! update Playlist SMIL
			$streamsPath = self::fixPath( $options['streamsPath'] );
			$smilPath    = $streamsPath . 'playlist.smil';

			$smilCode .= <<<HTMLCODE
<smil>
    <head>
    </head>
    <body>

HTMLCODE;

			if ( $options['playlists'] ) {

				$uploadsPath = $options['uploadsPath'];
				if ( ! file_exists( $uploadsPath ) ) {
					mkdir( $uploadsPath );
				}
				$playlistPathGlobal = $uploadsPath . '/playlist_global.txt';
				if ( ! file_exists( $playlistPathGlobal ) ) {
					self::varSave( $playlistPathGlobal, array() );
				}
				$playlistGlobal = self::varLoad( $playlistPathGlobal );

				$streams = array_keys( $playlistGlobal );
				foreach ( $streams as $stream ) {
					$smilCode .= '<stream name="' . $stream . '"></stream>
				';
				}

				foreach ( $streams as $stream ) {
					foreach ( $playlistGlobal[ $stream ] as $item ) {
						$smilCode .= '
        <playlist name="' . $stream . $item['Id'] . '" playOnStream="' . $stream . '" repeat="' . ( $item['Repeat'] ? 'true' : 'false' ) . '" scheduled="' . $item['Scheduled'] . '">';

						if ( $item['Videos'] ) {
							if ( is_array( $item['Videos'] ) ) {
								foreach ( $item['Videos'] as $video ) {
																$smilCode .= '
		<video src="' . $video['Video'] . '" start="' . $video['Start'] . '" length="' . $video['Length'] . '"/>';
								}
							}
						}

							$smilCode .= '
		</playlist>';
					}
				}
			}
			$smilCode .= <<<HTMLCODE

    </body>
</smil>
HTMLCODE;

			file_put_contents( $smilPath, $smilCode );
		}


		static function path2stream( $path, $withExtension = true, $withPrefix = true ) {
			$options = get_option( 'VWliveStreamingOptions' );

			$stream = substr( $path, strlen( $options['streamsPath'] ) );
			if ( $stream[0] == '/' ) {
				$stream = substr( $stream, 1 );
			}

			if ( $withPrefix ) {
				$ext    = pathinfo( $stream, PATHINFO_EXTENSION );
				$prefix = $ext . ':';
			} else {
				$prefix = '';
			}

			if ( ! file_exists( $options['streamsPath'] . '/' . $stream ) ) {
				return '';
			} elseif ( $withExtension ) {
				return $prefix . $stream;
			} else {
				return $prefix . pathinfo( $stream, PATHINFO_FILENAME );
			}
		}

		// ! Playlist AJAX handler

		static function vwls_playlist() {
			ob_clean();

			$postID = (int) $_GET['channel'];

			if ( ! $postID ) {
				echo 'No channel ID provided!';
				die;
			}

			$channel = get_post( $postID );
			if ( ! $channel ) {
				echo 'Channel not found!';
				die;
			}

			$current_user = wp_get_current_user();

			if ( $channel->post_author != $current_user->ID ) {
				echo 'Access not permitted (different channel owner)!';
				die;
			}

			$stream = sanitize_file_name( $channel->post_title );

			$options = get_option( 'VWliveStreamingOptions' );

			$uploadsPath = $options['uploadsPath'];
			if ( ! file_exists( $uploadsPath ) ) {
				mkdir( $uploadsPath );
			}

			$upath = $uploadsPath . "/$stream/";
			if ( ! file_exists( $upath ) ) {
				mkdir( $upath );
			}

			$playlistPath = $upath . 'playlist.txt';

			if ( ! file_exists( $playlistPath ) ) {
				self::varSave( $playlistPath, array() );
			}

			switch ( $_GET['task'] ) {
				case 'list':
					$rows = self::varLoad( $playlistPath );

					// sort rows by order
					if ( count( $rows ) ) {
						// sort
						function cmp_by_order( $a, $b ) {

							if ( $a['Order'] == $b['Order'] ) {
								return 0;
							}
							return ( $a['Order'] < $b['Order'] ) ? -1 : 1;
						}

						usort( $rows, 'cmp_by_order' ); // sort

						// update Ids to match keys (order)
						$updated = 0;
						foreach ( $rows as $key => $value ) {
							if ( $rows[ $key ]['Id'] != $key ) {
								$rows[ $key ]['Id'] = $key;
								$updated            = 1;
							}
						}
						if ( $updated ) {
							self::varSave( $playlistPath, $rows );
						}
					}

					// Return result to jTable
					$jTableResult            = array();
					$jTableResult['Result']  = 'OK';
					$jTableResult['Records'] = $rows;
					print json_encode( $jTableResult );

					break;

				case 'videolist':
					$ItemId       = (int) $_GET['item'];
					$jTableResult = array();

					$playlist = self::varLoad( $playlistPath );

					if ( $schedule = $playlist[ $ItemId ] ) {
						if ( ! $schedule['Videos'] ) {
							$schedule['Videos'] = array();
						}

						// sort videos

						// sort rows by order
						if ( count( $schedule['Videos'] ) ) {

							// sort
							function cmp_by_order( $a, $b ) {

								if ( $a['Order'] == $b['Order'] ) {
									return 0;
								}
								return ( $a['Order'] < $b['Order'] ) ? -1 : 1;
							}

							usort( $schedule['Videos'], 'cmp_by_order' ); // sort

							// update Ids to match keys (order)
							$updated = 0;
							foreach ( $schedule['Videos'] as $key => $value ) {
								if ( $schedule['Videos'][ $key ]['Id'] != $key ) {
									$schedule['Videos'][ $key ]['Id'] = $key;
									$updated                          = 1;
								}
							}

							$playlist[ $ItemId ] = $schedule;
							if ( $updated ) {
								self::varSave( $playlistPath, $playlist );
							}
						}

						$jTableResult['Records'] = $schedule['Videos'];
						$jTableResult['Result']  = 'OK';
					} else {
						$jTableResult['Result']  = 'ERROR';
						$jTableResult['Message'] = "Schedule $ItemId not found!";
					}

					print json_encode( $jTableResult );
					break;

				case 'videoupdate':
					// delete then add new

					$playlist = self::varLoad( $playlistPath );
					$ItemId   = (int) $_POST['ItemId'];
					$Id       = (int) $_POST['Id'];

					$jTableResult = array();
					if ( $playlist[ $ItemId ] ) {

						// find and remove record with that Id
						foreach ( $playlist[ $ItemId ]['Videos'] as $key => $value ) {
							if ( $value['Id'] == $Id ) {
								unset( $playlist[ $ItemId ]['Videos'][ $key ] );
								break;
							}
						}

						self::varSave( $playlistPath, $playlist );
					}

				case 'videoadd':
					$playlist = self::varLoad( $playlistPath );
					$ItemId   = (int) $_POST['ItemId'];

					$jTableResult = array();
					if ( $schedule = $playlist[ $ItemId ] ) {
						if ( ! $schedule['Videos'] ) {
							$schedule['Videos'] = array();
						}

						$maxOrder = 0;
						$maxId    = 0;
						foreach ( $schedule['Videos'] as $item ) {
							if ( $item['Order'] > $maxOrder ) {
								$maxOrder = $item['Order'];
							}
							if ( $item['Id'] > $maxId ) {
								$maxId = $item['Id'];
							}
						}

						$item           = array();
						$item['Video']  = sanitize_text_field( $_POST['Video'] );
						$item['Id']     = (int) $_POST['Id'];
						$item['Order']  = (int) $_POST['Order'];
						$item['Start']  = (int) $_POST['Start'];
						$item['Length'] = (int) $_POST['Length'];

						if ( ! $item['Order'] ) {
							$item['Order'] = $maxOrder + 1;
						}
						if ( ! $item['Id'] ) {
							$item['Id'] = $maxId + 1;
						}

						$playlist[ $ItemId ]['Videos'][] = $item;

						self::varSave( $playlistPath, $playlist );

						$jTableResult['Result'] = 'OK';
						$jTableResult['Record'] = $item;
					} else {
						$jTableResult['Result']  = 'ERROR';
						$jTableResult['Message'] = "Schedule $ItemId not found!";
					}

					// Return result to jTable
					print json_encode( $jTableResult );

					break;

				case 'videoremove':
					$playlist = self::varLoad( $playlistPath );
					$ItemId   = (int) $_GET['item'];
					$Id       = (int) $_POST['Id'];

					$jTableResult = array();
					if ( $schedule = $playlist[ $ItemId ] ) {

						// find and remove record with that Id
						foreach ( $playlist[ $ItemId ]['Videos'] as $key => $value ) {
							if ( $value['Id'] == $Id ) {
								unset( $playlist[ $ItemId ]['Videos'][ $key ] );
								break;
							}
						}

						self::varSave( $playlistPath, $playlist );

						$jTableResult['Result']    = 'OK';
						$jTableResult['Remaining'] = $playlist[ $ItemId ]['Videos'];
					} else {
						$jTableResult['Result']  = 'ERROR';
						$jTableResult['Message'] = "Schedule $ItemId not found!";
					}

					// Return result to jTable
					print json_encode( $jTableResult );

					break;

				case 'source':
					// retrieve videos owned by user (from all channels)

					// query
					$args = array(
						'post_type' => $options['custom_post_video'],
						'author'    => $current_user->ID,
						'orderby'   => 'post_date',
						'order'     => 'DESC',
					);

					$postslist = get_posts( $args );
					$rows      = array();

					if ( count( $postslist ) > 0 ) {
						foreach ( $postslist as $item ) {
							$row                = array();
							$row['DisplayText'] = $item->post_title;

							$video_id = $item->ID;

							// retrieve video stream
							$streamPath = '';
							$videoPath  = get_post_meta( $video_id, 'video-source-file', true );
							$ext        = pathinfo( $videoPath, PATHINFO_EXTENSION );

							// use conversion if available
							$videoAdaptive = get_post_meta( $video_id, 'video-adaptive', true );
							if ( $videoAdaptive ) {
								$videoAlts = $videoAdaptive;
							} else {
								$videoAlts = array();
							}

							foreach ( array( 'high', 'mobile' ) as $frm ) {
								if ( $alt = $videoAlts[ $frm ] ) {
									if ( file_exists( $alt['file'] ) ) {
										$ext        = pathinfo( $alt['file'], PATHINFO_EXTENSION );
										$streamPath = self::path2stream( $alt['file'] );
										break;
									}
								}
							};

							// user original
							if ( ! $streamPath ) {
								if ( in_array( $ext, array( 'flv', 'mp4', 'm4v' ) ) ) {
									// use source if compatible
									$streamPath = self::path2stream( $videoPath );
								}
							}

							$row['Value'] = $streamPath;
							$rows[]       = $row;
						}
					}
					// Return result to jTable
					$jTableResult            = array();
					$jTableResult['Result']  = 'OK';
					$jTableResult['Options'] = $rows;
					print json_encode( $jTableResult );

					break;

				case 'update':
					// delete then create new
					$Id = (int) $_POST['Id'];

					$playlist = self::varLoad( $playlistPath );
					if ( ! is_array( $playlist ) ) {
						$playlist = array();
					}

					foreach ( $playlist as $key => $value ) {
						if ( $value['Id'] == $Id ) {
							unset( $playlist[ $key ] );
							break;
						}
					}

					self::varSave( $playlistPath, $playlist );

				case 'create':
					$playlist = self::varLoad( $playlistPath );
					if ( ! is_array( $playlist ) ) {
						$playlist = array();
					}

					$maxOrder = 0;
					$maxId    = 0;
					foreach ( $playlist as $item ) {
						if ( $item['Order'] > $maxOrder ) {
							$maxOrder = $item['Order'];
						}
						if ( $item['Id'] > $maxId ) {
							$maxId = $item['Id'];
						}
					}

					$item              = array();
					$item['Id']        = (int) $_POST['Id'];
					$item['Video']     = sanitize_text_field( $_POST['Video'] );
					$item['Repeat']    = (int) $_POST['Repeat'];
					$item['Scheduled'] = sanitize_text_field( $_POST['Scheduled'] );
					$item['Order']     = (int) $_POST['Order'];
					if ( ! $item['Order'] ) {
						$item['Order'] = $maxOrder + 1;
					}
					if ( ! $item['Id'] ) {
						$item['Id'] = $maxId + 1;
					}
					if ( ! $item['Scheduled'] ) {
						$item['Scheduled'] = date( 'Y-m-j h:i:s' );
					}

					$playlist[ $item['Id'] ] = $item;

					self::varSave( $playlistPath, $playlist );

					// Return result to jTable
					$jTableResult           = array();
					$jTableResult['Result'] = 'OK';
					$jTableResult['Record'] = $item;
					print json_encode( $jTableResult );
					break;

				case 'delete':
					$Id = (int) $_POST['Id'];

					$playlist = self::varLoad( $playlistPath );
					if ( ! is_array( $playlist ) ) {
						$playlist = array();
					}

					foreach ( $playlist as $key => $value ) {
						if ( $value['Id'] == $Id ) {
							unset( $playlist[ $key ] );
							break;
						}
					}

					self::varSave( $playlistPath, $playlist );

					// Return result to jTable
					$jTableResult           = array();
					$jTableResult['Result'] = 'OK';
					print json_encode( $jTableResult );
					break;

				default:
					echo 'Action not supported!';
			}

			die;

		}

		// ! Widget

		static function widget( $args ) {
			extract( $args );
			echo wp_kses_post( $before_widget );
			echo wp_kses_post( $before_title );
			?>
			Live Streaming
			<?php
			echo wp_kses_post( $after_title );
			self::widgetContent();
			echo wp_kses_post( $after_widget );
		}

		static function widgetContent() {
			global $wpdb;
			$table_sessions = $wpdb->prefix . 'vw_sessions';
			$table_viewers  = $wpdb->prefix . 'vw_lwsessions';

			$root_url = get_bloginfo( 'url' ) . '/';

			// clean recordings
			self::cleanSessions( 0 );
			self::cleanSessions( 1 );

			$items = $wpdb->get_results( "SELECT * FROM `$table_sessions` where status='1'" );

			echo '<ul>';
			if ( $items ) {
				foreach ( $items as $item ) {
					$count = $wpdb->get_results( "SELECT count(id) as no FROM `$table_viewers` where status='1' and room='" . $item->room . "'" );

					$postID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_title = '" . $item->room . "' and post_type='channel' LIMIT 0,1" );
					if ( $postID ) {
						$url = get_post_permalink( $postID );
					} else {
						$url = plugin_dir_url( __FILE__ ) . 'ls/channel.php?n=' . urlencode( $item->name );
					}

					echo "<li><a href='" . esc_url( $url ) . "'><B>" . esc_html( $item->room  ). '</B>
(' . intval( $count[0]->no + 1 ) . ') ' . ( esc_html( $item->message ) ? ': ' . esc_html( $item->message ) : '' ) . '</a></li>';
				}
			} else {
				echo '<li>No broadcasters online.</li>';
			}
				echo '</ul>';

				$options = get_option( 'VWliveStreamingOptions' );

			if ( $options['userChannels'] || $options['anyChannels'] ) {
				if ( is_user_logged_in() ) {
					$userName = $options['userName'];
					if ( ! $userName ) {
									$userName = 'user_nicename';
					}

					$current_user = wp_get_current_user();

					if ( $current_user->$userName ) {
						$username = $current_user->$userName;
					}
					$username = sanitize_file_name( $username );
					?>
					<a href="<?php echo plugin_dir_url( __FILE__ ); ?>ls/?n=<?php echo esc_attr( $username ); ?>"><img src="
										<?php
										echo plugin_dir_url( __FILE__ );
										?>
					ls/templates/live/i_webcam.png" align="absmiddle" border="0">Video Broadcast</a>
					<?php
				}
			}

				$state = 'block';
			if ( ! $options['videowhisper'] ) {
				$state = 'none';
			}
				echo '<div id="VideoWhisper" style="display: ' . esc_attr( $state ) . ';"><p>Powered by VideoWhisper <a href="https://broadcastlivevideo.com">Broadcast Live Video - HTML5 Live Streaming
Turnkey Site Platform</a>.</p></div>';
		}


		static function delete_associated_media( $id, $unlink = false, $except = 0 ) {

			$htmlCode = 'Removing... ';

			$media = get_children(
				array(
					'post_parent' => $id,
					'post_type'   => 'attachment',
				)
			);
			if ( empty( $media ) ) {
				return $htmlCode;
			}

			foreach ( $media as $file ) {

				if ( $except ) {
					if ( $file->ID == $except ) {
						break;
					}
				}

				if ( $unlink ) {
					$filename  = get_attached_file( $file->ID );
					$htmlCode .= " Removing $filename #" . $file->ID;
					if ( file_exists( $filename ) ) {
						unlink( $filename );
					}
				}

				wp_delete_attachment( $file->ID );
			}

			return $htmlCode;
		}


		// ! Channel Post

		static function the_title( $title ) {
			$title       = esc_attr( $title );
			$findthese   = array(
				'#Protected:#',
				'#Private:#',
			);
			$replacewith = array(
				'', // What to replace "Protected:" with
				'', // What to replace "Private:" with
			);
			$title       = preg_replace( $findthese, $replacewith, $title );
			return $title;
		}


		static function channel_page( $content ) {

			$options = get_option( 'VWliveStreamingOptions' );

			if ( ! $options['postChannels'] ) {
				return $content;
			}

			if ( ! is_single() ) {
				return $content;
			}
			$postID = get_the_ID();

			if ( get_post_type( $postID ) != $options['custom_post'] ) {
				return $content;
			}

			$addCode = '';
			$aftercode = '';

			// global $wpdb;
			// $stream = $wpdb->get_var( "SELECT post_name FROM $wpdb->posts WHERE ID = '" . $postID . "' and post_type='channel' LIMIT 0,1" );

			$stream = sanitize_file_name( get_the_title( $postID ) );

			global $wp_query;

			$showBroadcastInterface = 0;
			if ( $options['broadcasterRedirect']
				&& ! array_key_exists( 'broadcast', $wp_query->query_vars )
				&& ! array_key_exists( 'flash-broadcast', $wp_query->query_vars )
				&& ! array_key_exists( 'flash-view', $wp_query->query_vars )
				&& ! array_key_exists( 'flash-video', $wp_query->query_vars )
				&& ! array_key_exists( 'webrtc-broadcast', $wp_query->query_vars )
				&& ! array_key_exists( 'webrtc-playback', $wp_query->query_vars )
				&& ! array_key_exists( 'external', $wp_query->query_vars )
				&& ! array_key_exists( 'external-broadcast', $wp_query->query_vars )
				&& ! array_key_exists( 'external-playback', $wp_query->query_vars )
				&& ! array_key_exists( 'hls', $wp_query->query_vars )
				&& ! array_key_exists( 'mpeg', $wp_query->query_vars )
				&& ! array_key_exists( 'html5-view', $wp_query->query_vars ) ) {
				$user = wp_get_current_user();
				if ( $user->exists() ) {
					$post = get_post( $postID );

					if ( $user->ID == $post->post_author ) {
						if ( $options['broadcasterRedirect'] == 'broadcast' ) {
							$showBroadcastInterface = 1;
						}

						if ( $options['broadcasterRedirect'] == 'dashboard' ) {
							$url     = get_permalink( get_option( 'vwls_page_manage' ) );
							$string  = '<script type="text/javascript">';
							$string .= 'window.location = "' . $url . '"';
							$string .= '</script>';

							return $string;
						}
					}
				}
			}

			$offline = '';

			if ( array_key_exists( 'broadcast', $wp_query->query_vars ) || $showBroadcastInterface ) {
				if ( ! $addCode = $offline = self::channelInvalid( $stream, true ) ) {
					$addCode = '[videowhisper_broadcast]';
				}
				$showBroadcastInterface = 1;
			} elseif ( array_key_exists( 'webrtc-broadcast', $wp_query->query_vars ) ) {
				if ( ! $addCode = $offline = self::channelInvalid( $stream, true ) ) {

					if ($options['html5videochat']) $addCode = "[videowhisper_h5vls_app room=\"$stream\" webcam_id=\"$postID\"]";
					else $addCode = '[videowhisper_webrtc_broadcast]';
				}
				$showBroadcastInterface = 1;
			} elseif ( array_key_exists( 'flash-broadcast', $wp_query->query_vars ) ) {
				if ( ! $addCode = $offline = self::channelInvalid( $stream, true ) ) {
					$addCode = '[videowhisper_broadcast flash="1"]';
				}
				$showBroadcastInterface = 1;
			} elseif ( array_key_exists( 'flash-view', $wp_query->query_vars ) ) {
				if ( ! $addCode = $offline = self::channelInvalid( $stream ) ) {
					$addCode = '[videowhisper_watch flash="1" width="' . $options['watchWidth']. '" height="' . $options['watchHeight'] .'"]';
				}
			} elseif ( array_key_exists( 'video', $wp_query->query_vars ) ) {
				if ( ! $addCode = $offline = self::channelInvalid( $stream ) ) {
					$addCode = '[videowhisper_video width="' . $options['videoWidth']. '" height="' . $options['videoHeight'] .'"]';
				}
			} elseif ( array_key_exists( 'flash-video', $wp_query->query_vars ) ) {
				if ( ! $addCode = $offline = self::channelInvalid( $stream ) ) {
					$addCode = '[videowhisper_video flash="1" width="' . $options['videoWidth']. '" height="' . $options['videoHeight'] .'"]';
				}
			} elseif ( array_key_exists( 'hls', $wp_query->query_vars ) ) {
				if ( ! $addCode = $offline = self::channelInvalid( $stream ) ) {
					$addCode = '[videowhisper_hls width="' . $options['videoWidth']. '" height="' . $options['videoHeight'] .'"]';
				}
			} elseif ( array_key_exists( 'mpeg', $wp_query->query_vars ) ) {
				if ( ! $addCode = $offline = self::channelInvalid( $stream ) ) {
					$addCode = '[videowhisper_mpeg width="' . $options['videoWidth']. '" height="' . $options['videoHeight'] .'"]';
				}
			} elseif ( array_key_exists( 'webrtc-playback', $wp_query->query_vars ) ) {
				if ( ! $addCode = $offline = self::channelInvalid( $stream ) ) {
					if ($options['html5videochat']) $addCode = "[videowhisper_h5vls_app room=\"$stream\" webcam_id=\"$postID\"]";
					else $addCode = '[videowhisper_webrtc_playback width="' . $options['videoWidth']. '" height="' . $options['videoHeight'] .'"]';
				}
			} elseif ( array_key_exists( 'html5-view', $wp_query->query_vars ) ) {
				if ( ! $addCode = $offline = self::channelInvalid( $stream ) ) {
					$addCode = '[videowhisper_htmlchat_playback width="' . $options['watchWidth']. '" height="' . $options['watchHeight'] .'"]';
				}
			} elseif ( array_key_exists( 'external', $wp_query->query_vars ) ) {
				$addCode = '[videowhisper_external]';
				$content = '';
			} elseif ( array_key_exists( 'external-broadcast', $wp_query->query_vars ) ) {
				$addCode = '[videowhisper_external_broadcast]';
				$content = '';
			} elseif ( array_key_exists( 'external-playback', $wp_query->query_vars ) ) {
				$addCode = '[videowhisper_external_playback]';
				$content = '';
			} else { // default
				if ( ! $addCode = $offline = self::channelInvalid( $stream ) ) {
					if ( $options['viewerInterface'] == 'video' ) {
						$addCode = '[videowhisper_video width="' . $options['videoWidth']. '" height="' . $options['videoHeight'] .'"]';
					} else {
						$addCode = '[videowhisper_watch]';
					}
				}
			}

			// ip camera or playlist: update snapshot on access
			$vw_ipCamera = get_post_meta( $postID, 'vw_ipCamera', true );

			if ( $vw_ipCamera || get_post_meta( $postID, 'vw_playlistActive', true ) ) {
				self::streamSnapshot( $stream, true, $postID );

			}

			// handle paused restreams
			if ( $vw_ipCamera ) {
				$addCode .= self::restreamPause( $postID, $stream, $options );
			}

			// other data
			if ( ! array_key_exists( 'external', $wp_query->query_vars )
				&& ! array_key_exists( 'external-broadcast', $wp_query->query_vars )
				&& ! array_key_exists( 'external-playback', $wp_query->query_vars )
			) {

				if ( $stream ) {
					if ( self::timeTo( $stream . '/updateThumb', 300, $options ) ) {
						// set thumb
						$dir           = $options['uploadsPath'] . '/_snapshots';
						$thumbFilename = "$dir/$stream.jpg";

						$attach_id = get_post_thumbnail_id( $postID );

						// update post thumb  if file exists and missing post thumb
						if ( file_exists( $thumbFilename ) && ! get_post_thumbnail_id( $postID ) ) {
							$wp_filetype = wp_check_filetype( basename( $thumbFilename ), null );

							$attachment = array(
								'guid'           => $thumbFilename,
								'post_mime_type' => $wp_filetype['type'],
								'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $thumbFilename, '.jpg' ) ),
								'post_content'   => '',
								'post_status'    => 'inherit',
							);

							$attach_id = wp_insert_attachment( $attachment, $thumbFilename, $postID );
							set_post_thumbnail( $postID, $attach_id );

							require_once ABSPATH . 'wp-admin/includes/image.php';
							$attach_data = wp_generate_attachment_metadata( $attach_id, $thumbFilename );
							wp_update_attachment_metadata( $attach_id, $attach_data );
						}

						// clean other media
						if ( $postID && $attach_id ) {
							self::delete_associated_media( $postID, false, $attach_id );
						}
					}
				}

				// update access time for visitor/user

				$ztime = time();

				// user access update (updates with 10s precision): last time when a registered user accessed this content
				if ( is_user_logged_in() ) {
					$accessedUser = intval( get_post_meta( $postID, 'accessedUser', true ) );
					if ( $ztime - $accessedUser > 10 ) {
						update_post_meta( $postID, 'accessedUser', $ztime );
					}
				}

				// anybody accessed including visitors, 20s precision
				$accessed = intval( get_post_meta( $postID, 'accessed', true ) );
				if ( $ztime - $accessed > 20 ) {
					update_post_meta( $postID, 'accessed', $ztime );
				}

				// meta info
				$metaCode = '';
				$afterCode = '';

				$edate = get_post_meta( $postID, 'edate', true );
				if ( $edate ) {
					$metaCode .= '<div class="item">' . __( 'Last Broadcast', 'live-streaming' ) . ': ' . self::format_age( $ztime - $edate ) . '</div>';
				}

				// viewers
				$maxViewers = get_post_meta( $postID, 'maxViewers', true );
				if ( ! is_array( $maxViewers ) ) {
					if ( $maxViewers > 0 ) {
						$metaCode .= '<div class="item">';

						$maxDate   = (int) get_post_meta( $postID, 'maxDate', true );
						$metaCode .= ' ' . __( 'Maximum viewers', 'live-streaming' ) . ': ' . $maxViewers;
						if ( $maxDate ) {
							$metaCode .= ' on ' . date( 'F j, Y, g:i a', $maxDate );
						}

						$metaCode .= '</div>';
					}
				}

				// watch time
				$wtime = get_post_meta( $postID, 'wtime', true );
				if ( $wtime ) {
					$metaCode .= '<div class="item">' . __( 'Total Watch Time', 'live-streaming' ) . ': ' . self::format_time( $wtime ) . '</div>';
				}

				if ( $metaCode ) {
					$addCode .= '<div class="ui ' . $options['interfaceClass'] . ' segment">' . $metaCode . '</div>';
				}

				if ( ! $offline ) {
					$addCode .= self::eventInfo( $postID );
				}

				// ! show reviews
				$aftercode = '';
				if ( $options['rateStarReview'] ) {
					// tab : reviews
					if ( shortcode_exists( 'videowhisper_review' ) ) {
						$aftercode .= '<h3>' . __( 'My Review', 'live-streaming' ) . '</h3>' . do_shortcode( '[videowhisper_review content_type="channel" post_id="' . $postID . '" content_id="' . $postID . '"]' );
					} else {
						$aftercode .= 'Warning: shortcodes missing. Plugin <a target="_plugin" href="https://wordpress.org/plugins/rate-star-review/">Rate Star Review</a> should be installed and enabled or feature disabled.';
					}

					if ( shortcode_exists( 'videowhisper_reviews' ) ) {
						$aftercode .= '<h3>' . __( 'Reviews', 'live-streaming' ) . '</h3>' . do_shortcode( '[videowhisper_reviews post_id="' . $postID . '"]' );
					}
				}
			}

			return $addCode . $content . $aftercode;
		}

		static function eventInfo( $postID ) {
			$eventTitle = get_post_meta( $postID, 'eventTitle', true );
			if ( $eventTitle ) {
				$eventStart     = get_post_meta( $postID, 'eventStart', true );
				$eventEnd       = get_post_meta( $postID, 'eventEnd', true );
				$eventStartTime = get_post_meta( $postID, 'eventStartTime', true );
				$eventEndTime   = get_post_meta( $postID, 'eventEndTime', true );

				$eventDescription = get_post_meta( $postID, 'eventDescription', true );

				$showImage = get_post_meta( $postID, 'showImage', true );
				if ( $showImage == 'event' || $showImage == 'all' ) {
					// get post thumb
					$attach_id = get_post_thumbnail_id( $postID );
					if ( $attach_id ) {
						$thumbFilename = get_attached_file( $attach_id );
					}

					if ( file_exists( $thumbFilename ) ) {
						$snapshot = self::path2url( $thumbFilename );
					}

					$eventCode .= '<IMG style="padding: 10px" SRC="' . $snapshot . '" ALIGN="LEFT">';

				}
				$eventCode .= '<BR>';
				$eventCode .= '<H3>' . $eventTitle . '</H3>';
				if ( $eventStart || $eventStartTime ) {
					$eventCode .= 'Starts: ' . $eventStart . ' ' . $eventStartTime;
				}
				if ( $eventEnd || $eventEndTime ) {
					$eventCode .= '<BR>Ends: ' . $eventEnd . ' ' . $eventEndTime;
				}
				if ( $eventDescription ) {
					$eventCode .= '<p>' . $eventDescription . '</p>';
				}
				$eventCode .= '<BR style="clear:both">';
			} else {
				return '';
			}

			return $eventCode;

		}
		public static function pre_get_posts( $query ) {

			// add channels to post listings
			if ( is_category() || is_tag() ) {
				$query_type = get_query_var( 'post_type' );

				if ( $query_type ) {
					if ( is_array( $query_type ) ) {
						if ( in_array( 'post', $query_type ) && ! in_array( 'channel', $query_type ) ) {
							$query_type[] = 'channel';
						}
						$query->set( 'post_type', $query_type );
					}
				}
				// else  //default
				// $query_type = array('post', 'channel');

			}

			return $query;
		}

		static function columns_head_channel( $defaults ) {
			$defaults['featured_image'] = 'Snapshot';
			$defaults['edate']          = 'Last Online';

			return $defaults;
		}

		static function columns_register_sortable( $columns ) {
			$columns['edate'] = 'edate';

			return $columns;
		}


		static function columns_content_channel( $column_name, $post_id ) {

			if ( $column_name == 'featured_image' ) {

				global $wpdb;
				$postName = $wpdb->get_var( "SELECT post_title FROM $wpdb->posts WHERE ID = '" . $post_id . "' and post_type='channel' LIMIT 0,1" );

				if ( $postName ) {
					$options       = get_option( 'VWliveStreamingOptions' );
					$dir           = $options['uploadsPath'] . '/_thumbs';
					$thumbFilename = "$dir/" . $postName . '.jpg';

					$url = self::roomURL( $postName );

					if ( file_exists( $thumbFilename ) ) {
						echo '<a href="' . esc_url( $url ) . '"><IMG src="' . esc_url( self::path2url( $thumbFilename ) ) . '" width="' . intval( $options['thumbWidth'] ) . 'px" height="' . intval( $options['thumbHeight'] ) . 'px"></a>';
					}
				}
			}

			if ( $column_name == 'edate' ) {
				$edate = get_post_meta( $post_id, 'edate', true );
				if ( $edate ) {
					echo ' ' . self::format_age( time() - $edate );

				}
			}

		}

		public static function duration_column_orderby( $vars ) {
			if ( isset( $vars['orderby'] ) && 'edate' == $vars['orderby'] ) {
				$vars = array_merge(
					$vars,
					array(
						'meta_key' => 'edate',
						'orderby'  => 'meta_value_num',
					)
				);
			}

			return $vars;
		}


		public static function query_vars( $query_vars ) {
			// array of recognized query vars
			$query_vars[] = 'broadcast';
			$query_vars[] = 'flash-broadcast';
			$query_vars[] = 'flash-view';
			$query_vars[] = 'flash-video';
			$query_vars[] = 'video';
			$query_vars[] = 'hls';
			$query_vars[] = 'mpeg';
			$query_vars[] = 'webrtc-broadcast';
			$query_vars[] = 'webrtc-playback';
			$query_vars[] = 'html5-view';
			$query_vars[] = 'external';
			$query_vars[] = 'external-broadcast';
			$query_vars[] = 'external-playback';
			$query_vars[] = 'vwls_eula';
			$query_vars[] = 'vwls_crossdomain';
			$query_vars[] = 'vwls_fullchannel';

			return $query_vars;
		}

		static function parse_request( &$wp ) {


			if ( array_key_exists( 'vwls_fullchannel', $wp->query_vars ) ) {

				$stream = sanitize_file_name( $wp->query_vars['vwls_fullchannel'] );

				if ( ! $stream ) {
					echo 'No channel name provided!';
					exit;

				}

				echo '<title>' . esc_html( $stream ) . '</title>
<body style="margin:0; padding:0; width:100%; height:100%">
';
				echo self::flash_watch( $stream );

				exit();
			}

		}

		// Register Custom Post Type
		static function channel_post() {

			$options = get_option( 'VWliveStreamingOptions' );
			if ( !isset( $options['postChannels']) || !$options['postChannels'] ) {
				return;
			}

			// only if missing
			if ( post_type_exists( $options['custom_post'] ?? 'channel' )  ) {
				return;
			}

			$labels = array(
				'name'               => _x( 'Channels', 'Post Type General Name', 'live-streaming' ),
				'singular_name'      => _x( 'Channel', 'Post Type Singular Name', 'live-streaming' ),
				'menu_name'          => __( 'Channels', 'live-streaming' ),
				'parent_item_colon'  => __( 'Parent Channel', 'live-streaming' ) . ':',
				'all_items'          => __( 'All Channels', 'live-streaming' ),
				'view_item'          => __( 'View Channel', 'live-streaming' ),
				'add_new_item'       => __( 'Add New Channel', 'live-streaming' ),
				'add_new'            => __( 'New Channel', 'live-streaming' ),
				'edit_item'          => __( 'Edit Channel', 'live-streaming' ),
				'update_item'        => __( 'Update Channel', 'live-streaming' ),
				'search_items'       => __( 'Search Channels', 'live-streaming' ),
				'not_found'          => __( 'No Channels found', 'live-streaming' ),
				'not_found_in_trash' => __( 'No Channels found in Trash', 'live-streaming' ),
			);
			$args   = array(
				'label'               => __( 'channel', 'live-streaming' ),
				'description'         => __( 'Video Channels', 'live-streaming' ),
				'labels'              => $labels,
				'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'comments', 'custom-fields', 'page-attributes' ),
				'taxonomies'          => array( 'category', 'post_tag' ),
				'hierarchical'        => false,
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => true,
				'show_in_admin_bar'   => true,
				'menu_position'       => 5,
				'can_export'          => true,
				'has_archive'         => true,
				'exclude_from_search' => false,
				'publicly_queryable'  => true,
				'menu_icon'           => 'dashicons-video-alt',
				'capability_type'     => 'post',
				'capabilities'        => array(
					'create_posts'     => 'do_not_allow', // false < WP 4.5
					'edit_posts'       => 'edit_posts',
					'edit_post'        => 'edit_post',
					'edit_other_posts' => 'edit_other_posts',
					'delete_post'      => 'delete_post',

				),
				'map_meta_cap'        => true, // Set to `false`, if users are not allowed to edit/delete existing posts
			);
			register_post_type( $options['custom_post'], $args );

			add_rewrite_endpoint( 'broadcast', EP_ALL );
			add_rewrite_endpoint( 'flash-broadcast', EP_ALL );
			add_rewrite_endpoint( 'flash-view', EP_ALL );
			add_rewrite_endpoint( 'flash-video', EP_ALL );
			add_rewrite_endpoint( 'video', EP_ALL );
			add_rewrite_endpoint( 'hls', EP_ALL );
			add_rewrite_endpoint( 'mpeg', EP_ALL );
			add_rewrite_endpoint( 'external', EP_ALL );
			add_rewrite_endpoint( 'external-broadcast', EP_ALL );
			add_rewrite_endpoint( 'external-playback', EP_ALL );
			add_rewrite_endpoint( 'webrtc-broadcast', EP_ALL );
			add_rewrite_endpoint( 'webrtc-playback', EP_ALL );
			add_rewrite_endpoint( 'html5-view', EP_ALL );

			add_rewrite_rule( 'eula.txt$', 'index.php?vwls_eula=1', 'top' );
			add_rewrite_rule( 'crossdomain.xml$', 'index.php?vwls_crossdomain=1', 'top' );
			add_rewrite_rule( '^fullchannel/([\w]*)?', 'index.php?vwls_fullchannel=$matches[1]', 'top' );

			// flush_rewrite_rules();
		}



		// ! Billing Integration: MyCred, TeraWallet (WooWallet)

		static function balances( $userID, $options = null ) {
			// get html code listing balances
			if ( ! $options ) {
				$options = get_option( 'VWliveStreamingOptions' );
			}
			if ( ! $options['walletMulti'] ) {
				return ''; // disabled
			}

			$balances = self::walletBalances( $userID, '', $options );

			$walletTransfer = sanitize_text_field( $_GET['walletTransfer'] );

			global $wp;
			foreach ( $balances as $key => $value ) {
				$htmlCode .= '<br>' . $key . ': ' . $value;

				if ( $options['walletMulti'] == 2 && $walletTransfer != $key && $options['wallet'] != $key && $value > 0 ) {
					$htmlCode .= ' <a class="ui button compact tiny" href=' . add_query_arg( array( 'walletTransfer' => $key ), $wp->request ) . ' data-tooltip="Transfer to Active Balance">Transfer</a>';
				}

				if ( $walletTransfer == $key || ( $value > 0 && $options['walletMulti'] == 3 && $options['wallet'] != $key ) ) {
					self::walletTransfer( $key, $options['wallet'], get_current_user_id(), $options );
					$htmlCode .= ' Transferred to active balance.';
				}
			}

			return $htmlCode;
		}

		static function walletBalances( $userID, $view = 'view', $options = null ) {
			$balances = array();
			if ( ! $userID ) {
				return $balances;
			}

			// woowallet
			if (isset($GLOBALS['woo_wallet']) ) {
				$wooWallet             = $GLOBALS['woo_wallet'];
				$balances['WooWallet'] = $wooWallet->wallet->get_wallet_balance( $userID, $view );
			}

			// mycred
			if ( function_exists( 'mycred_get_users_balance' ) ) {
				$balances['MyCred'] = mycred_get_users_balance( $userID );
			}

			return $balances;
		}


		static function walletTransfer( $source, $destination, $userID, $options = null ) {
			// transfer balance from a wallet to another wallet

			if ( $source == $destination ) {
				return;
			}

			if ( ! $options ) {
				$options = get_option( 'VWliveStreamingOptions' );
			}

			$balances = self::walletBalances( $userID, '', $options );

			if ( $balances[ $source ] > 0 ) {
				self::walletTransaction( $destination, $balances[ $source ], $userID, "Wallet balance transfer from $source to $destination.", 'wallet_transfer' );
				self::walletTransaction( $source, - $balances[ $source ], $userID, "Wallet balance transfer from $source to $destination.", 'wallet_transfer' );
			}

		}

		static function walletTransaction( $wallet, $amount, $user_id, $entry, $ref, $ref_id = null, $data = null ) {
			// transactions on all supported wallets
			// $wallet : MyCred/WooWallet

			if ( $amount == 0 ) {
				return; // no transaction
			}

			// mycred
			if ( $wallet == 'MyCred' ) {
				if ( $amount > 0 ) {
					if ( function_exists( 'mycred_add' ) ) {
						mycred_add( $ref, $user_id, $amount, $entry, $ref_id, $data );
					}
				} else {
					if ( function_exists( 'mycred_subtract' ) ) {
						mycred_subtract( $ref, $user_id, $amount, $entry, $ref_id, $data );
					}
				}
			}

			// woowallet
			if ( $wallet == 'WooWallet' ) {
				if ( $GLOBALS['woo_wallet'] ) {
					$wooWallet = $GLOBALS['woo_wallet'];

					if ( $amount > 0 ) {
						$wooWallet->wallet->credit( $user_id, $amount, $entry );
					} else {
						$wooWallet->wallet->debit( $user_id, -$amount, $entry );
					}
				}
			}

		}

		static function balance( $userID, $live = false, $options = null ) {
			// get current user balance (as value)
			// $live also estimates active (incomplete) session costs for client

			if ( ! $userID ) {
				return 0;
			}

			if ( ! $options ) {
				$options = get_option( 'VWliveStreamingOptions' );
			}

			$balance = 0;

			$balances = self::walletBalances( $userID, '', $options );

			if ( $options['wallet'] ) {
				if ( array_key_exists( $options['wallet'], $balances ) ) {
					$balance = $balances[ $options['wallet'] ];
				}
			}

			$temp = 0;
			if ( $live ) {
				$updated = intval( get_user_meta( $userID, 'vw_ppv_tempt', true ) );

				if ( time() - $updated < 15 ) { // updated recently: use that estimation
					$temp = get_user_meta( $userID, 'vw_ppv_temp', true );
				} else {
					//use PaidVideochat.com for billing features
				}

				$balance = $balance - $temp; // deduct temporary charge
			}

			return $balance;
		}

		static function transaction( $ref = 'live_streaming', $user_id = 1, $amount = 0, $entry = 'Live Streaming transaction.', $ref_id = null, $data = null, $options = null ) {
			// ref = explanation ex. ppv_client_payment
			// entry = explanation ex. PPV client payment in room.
			// utils: ref_id (int|string|array) , data (int|string|array|object)

			if ( $amount == 0 ) {
				return; // nothing
			}

			if ( ! $options ) {
				$options = get_option( 'VWliveStreamingOptions' );
			}

			// active wallet
			if ( $options['wallet'] ) {
				$wallet = $options['wallet'];
			}
			if ( ! $wallet ) {
				$wallet = 'MyCred';
			}
			if ( ! function_exists( 'mycred_add' ) ) {
				if ( $GLOBALS['woo_wallet'] ) {
					$wallet = 'WooWallet';
				}
			}

				self::walletTransaction( $wallet, $amount, $user_id, $entry, $ref, $ref_id, $data );
		}



		static function userPaidAccess( $userID, $postID ) {
			// checks if user has access to content that may be fore sale

			if ( ! class_exists( 'myCRED_Sell_Content_Module' ) ) {
				return true; // sell content disabled
			}

			$meta = get_post_meta( $postID, 'myCRED_sell_content', true );

			if ( ! $meta ) {
				return true; // not for sale
			}
			if ( ! $meta['price'] ) {
				return true; // or no price
			}

			if ( ! $userID ) {
				return false; // not logged in: did not purchase
			}

			// check transaction log
			global $wpdb;

			$table_sessionsC = $wpdb->prefix . 'myCRED_log';
			$isBuyer         = $wpdb->get_col( $sql = "SELECT user_id FROM {$table_sessionsC} WHERE user_id={$userID} AND ref = 'buy_content' AND ref_id = {$postID} AND creds < 0" );
			if ( ! $isBuyer ) {
				return false; // did not purchase
			} else {
				return true;
			}
		}

		// ! Admin


		static function admin_init() {
			add_meta_box(
				'vwls-nav-menus',
				'Channel Categories',
				array( 'VWliveStreaming', 'nav_menus' ),
				'nav-menus',
				'side',
				'default'
			);
		}

		static function nav_menus() {
			// $object, $taxonomy

			global $nav_menu_selected_id;
			$taxonomy_name = 'category';

			// Paginate browsing for large numbers of objects.
			$per_page = 50;
			$pagenum  = isset( $_REQUEST[ $taxonomy_name . '-tab' ] ) && isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1;
			$offset   = 0 < $pagenum ? $per_page * ( $pagenum - 1 ) : 0;

			$args = array(
				'child_of'     => 0,
				'exclude'      => '',
				'hide_empty'   => false,
				'hierarchical' => 1,
				'include'      => '',
				'number'       => $per_page,
				'offset'       => $offset,
				'order'        => 'ASC',
				'orderby'      => 'name',
				'pad_counts'   => false,
			);

			$terms = get_terms( $taxonomy_name, $args );

			if ( ! $terms || is_wp_error( $terms ) ) {
				echo '<p>' . __( 'No items.' ) . '</p>';
				return;
			}

			$num_pages = ceil(
				wp_count_terms(
					$taxonomy_name,
					array_merge(
						$args,
						array(
							'number' => '',
							'offset' => '',
						)
					)
				) / $per_page
			);

			$page_links = paginate_links(
				array(
					'base'      => add_query_arg(
						array(
							$taxonomy_name . '-tab' => 'all',
							'paged'                 => '%#%',
							'item-type'             => 'taxonomy',
							'item-object'           => $taxonomy_name,
						)
					),
					'format'    => '',
					'prev_text' => __( '&laquo;' ),
					'next_text' => __( '&raquo;' ),
					'total'     => $num_pages,
					'current'   => $pagenum,
				)
			);

			$db_fields = false;
			if ( is_taxonomy_hierarchical( $taxonomy_name ) ) {
				$db_fields = array(
					'parent' => 'parent',
					'id'     => 'term_id',
				);
			}

			$walker = new Walker_Nav_Menu_Checklist( $db_fields );

			$current_tab = 'most-used';
			if ( isset( $_REQUEST[ $taxonomy_name . '-tab' ] ) && in_array( sanitize_text_field( $_REQUEST[ $taxonomy_name . '-tab' ] ), array( 'all', 'most-used', 'search' ) ) ) {
				$current_tab = sanitize_text_field( $_REQUEST[ $taxonomy_name . '-tab' ] );
			}

			if ( ! empty( $_REQUEST[ 'quick-search-taxonomy-' . $taxonomy_name ] ) ) {
				$current_tab = 'search';
			}

			$removed_args = array(
				'action',
				'customlink-tab',
				'edit-menu-item',
				'menu-item',
				'page-tab',
				'_wpnonce',
			);

			?>
	<div id="taxonomy-<?php echo esc_attr( $taxonomy_name ); ?>" class="taxonomydiv">
		<ul id="taxonomy-<?php echo esc_attr( $taxonomy_name ); ?>-tabs" class="taxonomy-tabs add-menu-item-tabs">
			<li <?php echo ( 'most-used' == $current_tab ? ' class="tabs"' : '' ); ?>>
				<a class="nav-tab-link" data-type="tabs-panel-<?php echo esc_attr( $taxonomy_name ); ?>-pop" href="
																		 <?php
																			if ( $nav_menu_selected_id ) {
																				echo esc_url( add_query_arg( $taxonomy_name . '-tab', 'most-used', remove_query_arg( $removed_args ) ) );}
																			?>
				#tabs-panel-<?php echo esc_attr( $taxonomy_name ); ?>-pop">
					<?php _e( 'Most Used' ); ?>
				</a>
			</li>
			<li <?php echo ( 'all' == $current_tab ? ' class="tabs"' : '' ); ?>>
				<a class="nav-tab-link" data-type="tabs-panel-<?php echo esc_attr( $taxonomy_name ); ?>-all" href="
																		 <?php
																			if ( $nav_menu_selected_id ) {
																				echo esc_url( add_query_arg( $taxonomy_name . '-tab', 'all', remove_query_arg( $removed_args ) ) );}
																			?>
				#tabs-panel-<?php echo esc_attr( $taxonomy_name ); ?>-all">
					<?php _e( 'View All' ); ?>
				</a>
			</li>
			<li <?php echo ( 'search' == $current_tab ? ' class="tabs"' : '' ); ?>>
				<a class="nav-tab-link" data-type="tabs-panel-search-taxonomy-<?php echo esc_attr( $taxonomy_name ); ?>" href="
																						 <?php
																							if ( $nav_menu_selected_id ) {
																								echo esc_url( add_query_arg( $taxonomy_name . '-tab', 'search', remove_query_arg( $removed_args ) ) );}
																							?>
				#tabs-panel-search-taxonomy-<?php echo esc_attr( $taxonomy_name ); ?>">
					<?php _e( 'Search' ); ?>
				</a>
			</li>
		</ul><!-- .taxonomy-tabs -->

		<div id="tabs-panel-<?php echo esc_attr( $taxonomy_name ); ?>-pop" class="tabs-panel
									   <?php
										echo ( 'most-used' == $current_tab ? 'tabs-panel-active' : 'tabs-panel-inactive' );
										?>
			">
			<ul id="<?php echo esc_attr( $taxonomy_name ); ?>checklist-pop" class="categorychecklist form-no-clear" >
				<?php
				$popular_terms  = get_terms(
					$taxonomy_name,
					array(
						'orderby'      => 'count',
						'order'        => 'DESC',
						'number'       => 10,
						'hierarchical' => false,
					)
				);
				$args['walker'] = $walker;
				echo walk_nav_menu_tree( array_map( array( 'VWliveStreaming', 'nav_menu_item' ), $popular_terms ), 0, (object) $args );
				?>
			</ul>
		</div><!-- /.tabs-panel -->

		<div id="tabs-panel-<?php echo esc_attr( $taxonomy_name ); ?>-all" class="tabs-panel tabs-panel-view-all
									   <?php
										echo ( 'all' == $current_tab ? 'tabs-panel-active' : 'tabs-panel-inactive' );
										?>
			">
			<?php if ( ! empty( $page_links ) ) : ?>
				<div class="add-menu-item-pagelinks">
					<?php echo wp_kses_post( $page_links ); ?>
				</div>
			<?php endif; ?>
			<ul id="<?php echo esc_attr( $taxonomy_name ); ?>checklist" data-wp-lists="list:<?php echo esc_attr( $taxonomy_name ); ?>" class="categorychecklist form-no-clear">
				<?php
				$args['walker'] = $walker;
				echo walk_nav_menu_tree( array_map( array( 'VWliveStreaming', 'nav_menu_item' ), $terms ), 0, (object) $args );
				?>
			</ul>
			<?php if ( ! empty( $page_links ) ) : ?>
				<div class="add-menu-item-pagelinks">
					<?php echo wp_kses_post( $page_links ); ?>
				</div>
			<?php endif; ?>
		</div><!-- /.tabs-panel -->

		<div class="tabs-panel
			<?php
			echo ( 'search' == $current_tab ? 'tabs-panel-active' : 'tabs-panel-inactive' );
			?>
			" id="tabs-panel-search-taxonomy-<?php echo esc_attr( $taxonomy_name ); ?>">
			<?php
			if ( isset( $_REQUEST[ 'quick-search-taxonomy-' . $taxonomy_name ] ) ) {
				$searched       = sanitize_text_field( $_REQUEST[ 'quick-search-taxonomy-' . $taxonomy_name ] );
				$search_results = get_terms(
					$taxonomy_name,
					array(
						'name__like'   => $searched,
						'fields'       => 'all',
						'orderby'      => 'count',
						'order'        => 'DESC',
						'hierarchical' => false,
					)
				);
			} else {
				$searched       = '';
				$search_results = array();
			}
			?>
			<p class="quick-search-wrap">
				<input type="search" class="quick-search input-with-default-title" title="<?php esc_attr_e( 'Search' ); ?>" value="<?php echo esc_attr( $searched ); ?>" name="quick-search-taxonomy-<?php echo esc_attr( $taxonomy_name ); ?>" />
				<span class="spinner"></span>
				<?php submit_button( __( 'Search' ), 'button-small quick-search-submit button-secondary hide-if-js', 'submit', false, array( 'id' => 'submit-quick-search-taxonomy-' . $taxonomy_name ) ); ?>
			</p>

			<ul id="<?php echo esc_attr( $taxonomy_name ); ?>-search-checklist" data-wp-lists="list:<?php echo esc_attr( $taxonomy_name ); ?>" class="categorychecklist form-no-clear">
			<?php if ( ! empty( $search_results ) && ! is_wp_error( $search_results ) ) : ?>
				<?php
				$args['walker'] = $walker;
				echo walk_nav_menu_tree( array_map( array( 'VWliveStreaming', 'nav_menu_item' ), $search_results ), 0, (object) $args );
				?>
			<?php elseif ( is_wp_error( $search_results ) ) : ?>
				<li><?php echo esc_html( $search_results->get_error_message() ); ?></li>
			<?php elseif ( ! empty( $searched ) ) : ?>
				<li><?php _e( 'No results found.' ); ?></li>
			<?php endif; ?>
			</ul>
		</div><!-- /.tabs-panel -->

		<p class="button-controls">
			<span class="list-controls">
				<a href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							$taxonomy_name . '-tab' => 'all',
							'selectall'             => 1,
						),
						remove_query_arg( $removed_args )
					)
				);
				?>
			#taxonomy-<?php echo esc_attr( $taxonomy_name ); ?>" class="select-all"><?php _e( 'Select All' ); ?></a>
			</span>

			<span class="add-to-menu">
				<input type="submit"<?php wp_nav_menu_disabled_check( $nav_menu_selected_id ); ?> class="button-secondary submit-add-to-menu right" value="<?php esc_attr_e( 'Add to Menu' ); ?>" name="add-taxonomy-menu-item" id="<?php echo esc_attr( 'submit-taxonomy-' . esc_attr( $taxonomy_name ) ); ?>" />
				<span class="spinner"></span>
			</span>
		</p>

	</div><!-- /.taxonomydiv -->
			<?php
		}


		static function single_template( $single_template ) {

			if ( ! is_single() ) {
				return $single_template;
			}

			$options = get_option( 'VWliveStreamingOptions' );
			// if (!$options['custom_post']) $options['custom_post'] = 'channel';

			$postID = get_the_ID();

			if ( get_post_type( $postID ) != $options['custom_post'] ) {
				return $single_template;
			}

			if ( $options['postTemplate'] == '+plugin' ) {
				$single_template_new = dirname( __FILE__ ) . '/template-channel.php';
				if ( file_exists( $single_template_new ) ) {
					return $single_template_new;
				}
			}

			$single_template_new = get_stylesheet_directory() . '/' . $options['postTemplate'];

			if ( file_exists( $single_template_new ) ) {
				return $single_template_new;
			} else {
				return $single_template;
			}
		}

		static function nav_menu_item( $menu_item ) {
			$menu_item->ID               = $menu_item->term_id;
			$menu_item->db_id            = 0;
			$menu_item->menu_item_parent = 0;
			$menu_item->object_id        = (int) $menu_item->term_id;
			$menu_item->post_parent      = (int) $menu_item->parent;
			$menu_item->type             = 'custom';

			$object                = get_taxonomy( $menu_item->taxonomy );
			$menu_item->object     = $object->name;
			$menu_item->type_label = $object->labels->singular_name;

			$menu_item->title = $menu_item->name;

			$options = get_option( 'VWliveStreamingOptions' );
			if ( $options['disablePageC'] == '0' ) {
				$page_id        = get_option( 'vwls_page_channels' );
				$permalink      = get_permalink( $page_id );
				$menu_item->url = add_query_arg(
					array(
						'cid'      => $menu_item->object_id,
						'category' => $menu_item->name,
					),
					$permalink
				);
			} else {
				$menu_item->url = get_term_link( $menu_item, $menu_item->taxonomy ) . '?channels=1';
			}

			$menu_item->target      = '';
			$menu_item->attr_title  = '';
			$menu_item->description = get_term_field( 'description', $menu_item->term_id, $menu_item->taxonomy );
			$menu_item->classes     = array();
			$menu_item->xfn         = '';

			/**
			 * @param object $menu_item The menu item object.
			 */
			return $menu_item;
		}


		static function getDirectorySize( $path ) {
			$totalsize  = 0;
			$totalcount = 0;
			$dircount   = 0;

			if ( ! file_exists( $path ) ) {
				$total['size']     = $totalsize;
				$total['count']    = $totalcount;
				$total['dircount'] = $dircount;
				return $total;
			}

			if ( $handle = opendir( $path ) ) {
				while ( false !== ( $file = readdir( $handle ) ) ) {
					$nextpath = $path . '/' . $file;
					if ( $file != '.' && $file != '..' && ! is_link( $nextpath ) ) {
						if ( is_dir( $nextpath ) ) {
							$dircount++;
							$result      = self::getDirectorySize( $nextpath );
							$totalsize  += $result['size'];
							$totalcount += $result['count'];
							$dircount   += $result['dircount'];
						} elseif ( is_file( $nextpath ) ) {
							$totalsize += filesize( $nextpath );
							$totalcount++;
						}
					}
				}
			}
			closedir( $handle );
			$total['size']     = $totalsize;
			$total['count']    = $totalcount;
			$total['dircount'] = $dircount;
			return $total;
		}

		static function sizeFormat( $size ) {
			// echo $size;
			if ( $size < 1024 ) {
				return $size . ' bytes';
			} elseif ( $size < ( 1024 * 1024 ) ) {
					$size = round( $size / 1024, 2 );
					return $size . ' KB';
			} elseif ( $size < ( 1024 * 1024 * 1024 ) ) {
					$size = round( $size / ( 1024 * 1024 ), 2 );
					return $size . ' MB';
			} else {
				$size = round( $size / ( 1024 * 1024 * 1024 ), 2 );
				return $size . ' GB';
			}

		}

		// if any element from array1 in array2
		static function any_in_array( $array1, $array2 ) {
			foreach ( $array1 as $value ) {
				if ( in_array( $value, $array2 ) ) {
					return true;
				}
			}
				return false;
		}

		// ! cron
		static function cron_schedules( $schedules ) {
			$schedules['min10'] = array(
				'interval' => 600,
				'display'  => __( 'Once every 10 minutes' ),
			);
			return $schedules;
		}


		static function setupSchedule() {
			if ( ! wp_next_scheduled( 'cron_10min_event' ) ) {
				wp_schedule_event( time(), 'min10', 'cron_10min_event' );
			}

		}

		static function cron_10min_event() {
			// called each 10 min or more

			$options = get_option( 'VWliveStreamingOptions' );

			if ( ! $options['restreamPause'] ) {
				return;
			}

			// if (!self::timeTo('/cron_10min', 600, $options)) return; //too fast

			// ip camera or re-streams

			$meta_query = array(
				'relation' => 'AND', // Optional, defaults to "AND"
				array(
					'key'     => 'vw_ipCamera',
					'value'   => '',
					'compare' => '!=',
				),
				array(
					'key'     => 'vw_ipCamera',
					'compare' => 'EXISTS',
				),
			);

			$args = array(
				'post_type'   => $options['custom_post'],
				'numberposts' => -1,
				'orderby'     => 'post_date',
				'order'       => 'DESC',
				'meta_query'  => $meta_query,

			);

			$posts = get_posts( $args );

			if ( is_array( $posts ) ) {
				if ( count( $posts ) ) {
					foreach ( $posts as $post ) {
						self::restreamPause( $post->ID, $post->post_title, $options );

						$restreamPaused = get_post_meta( $post->ID, 'restreamPaused', true );
						if ( ! $restreamPaused ) {
							self::streamSnapshot( $post->post_title, true, $post->ID );
						}
					}
				}
			}

		}



		static function admin_head() {
			if ( get_post_type() != 'channel' ) {
				return;
			}

			// hide add button
			echo '<style type="text/css">
    #favorite-actions {display:none;}
    .add-new-h2{display:none;}
    .tablenav{display:none;}
    </style>';
		}


		static function humanSize( $value ) {
			if ( $value > 1000000000000 ) {
				return number_format( $value / 1000000000000, 2 ) . 't';
			}
			if ( $value > 1000000000 ) {
				return number_format( $value / 1000000000, 2 ) . 'g';
			}
			if ( $value > 1000000 ) {
				return number_format( $value / 1000000, 2 ) . 'm';
			}
			if ( $value > 1000 ) {
				return number_format( $value / 1000, 2 ) . 'k';
			}
			return $value;
		}


		static function adminStats() {
			 $options = get_option( 'VWliveStreamingOptions' );

			?>
	<h3>Channel Status, Statistics</h3>
			<?php

			if ( isset($_GET['regenerateThumbs']) ) {
				$dir  = $options['uploadsPath'];
				$dir .= '/_snapshots';
				echo '<div class="info">Regenerating thumbs for listed channels.</div>';
			}

			// RTMP Session Control
			if ( in_array( $options['webStatus'], array( 'enabled', 'strict', 'auto' ) ) ) {
				if ( file_exists( $path = $options['uploadsPath'] . '/_rtmpStatus.txt' ) ) {
					$url = self::path2url( $path );
					echo '+ RTMP Session Info Detected: <a target=_blank href="' . esc_url( $url ) . '">last status request</a> ' . date( 'D M j G:i:s T Y', filemtime( $path ) );

					echo '<h4>Last App Instance Info</h4>';
					$sessionsVars = self::varLoad( $options['uploadsPath'] . '/sessionsApp' );
					if ( is_array( $sessionsVars ) ) {
						if ( array_key_exists( 'appInstanceInfo', $sessionsVars ) ) {
							echo 'Last App Instance: ' . esc_html( $sessionsVars['appInstanceInfo'] );
						}

						ksort( $sessionsVars );

						echo '<h5>Streaming Host Limits</h5>';
						foreach ( $sessionsVars as $key => $value ) {
							if ( substr( $key, 0, 5 ) == 'limit' ) {
								echo wp_kses_post( "$key: $value" . ( strstr( strtolower( $key ), 'rate' ) && ! strstr( strtolower( $key ), 'disconnect' ) ? 'bytes = ' . self::humanSize( 8 * $value ) . 'bits' : '' ) . '<br>' );
							}
						}

						echo '<h5>All Parameters</h5><small>';
						foreach ( $sessionsVars as $key => $value ) {
							echo wp_kses_post( "$key: $value" . ( strstr( strtolower( $key ), 'rate' ) && ! strstr( strtolower( $key ), 'disconnect' ) ? ' = ' . self::humanSize( 8 * $value ) : '' ) . '; ' );
						}
						echo '</small>';
					}
				} else {
					echo '+ Warning: RTMP Session Control info was not detected. Without this broadcasts external to VideoWhisper apps will not show online and will not generate snapshots. Also all transcoding and thumb generation processes will have longer latency.';
				}
			}

			if ( $options['transcoding'] ) {
				$processUser = get_current_user();
				$processUid  = getmyuid();

				echo wp_kses_post( "<h4>FFmpeg</h4> + FFMPEG transcoding and snapshot retrieval processes currently run by account '$processUser' (#$processUid). Transcoding starts some time after stream is published for VideoWhisper web apps or when RTMP Session Control is enabled.<BR>" );

				$cmd = "ps aux | grep 'ffmpeg'";
				if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );
				// var_dump($output);

				$transcoders = 0;
				foreach ( $output as $line ) {
					if ( strstr( $line, 'ffmpeg' ) ) {
						$columns = preg_split( '/\s+/', $line );
						if ( ( $processUser == $columns[0] || $processUid == $columns[0] ) && ( ! in_array( $columns[10], array( 'sh', 'grep' ) ) ) ) {

							echo esc_html( ' - Process #' . $columns[1] . ' CPU: ' . $columns[2] . ' Mem: ' . $columns[3] . ' Start: ' . $columns[8] . ' CPU Time: ' . $columns[9] . ' Cmd: ' );
							for ( $n = 10; $n < 24; $n++ ) {
								echo esc_html( $columns[ $n ] ) . ' ';
							}

							if ( $_GET['kill'] == $columns[1] ) {
								$kcmd = 'kill -KILL ' . $columns[1];
								if ( $options['enable_exec'] ) exec( $kcmd, $koutput, $kreturnvalue );
								echo ' <B>Killing process...</B>';
							} else {
								echo ' <a href="admin.php?page=live-streaming&tab=hls&kill=' . esc_attr( $columns[1] ) . '">Kill</a>';
							}

							echo '<br>';
							$transcoders++;
						}
					}
				}

				if ( ! $transcoders ) {
					echo 'No live transcoding/snapshot processes detected.';
				} else {
					echo '<BR>Total processes for transcoding/snapshot: ' . esc_html( $transcoders );
				}
			}

			// start with a cleanup for viewers and broadcasters
			self::cleanSessions( 0 );
			self::cleanSessions( 1 );

			// list channels

			$typeLabels = array(
				1 => 'Flash',
				2 => 'External',
				3 => 'WebRTC',
			);

			global $wpdb;
			$table_sessions = $wpdb->prefix . 'vw_sessions';
			$table_viewers  = $wpdb->prefix . 'vw_lwsessions';
			$table_channels = $wpdb->prefix . 'vw_lsrooms';

			$items = $wpdb->get_results( "SELECT * FROM `$table_channels` ORDER BY edate DESC LIMIT 0, 200" );

			echo "<h4>Channel Activity</h4> <table class='wp-list-table widefat'><thead><tr><th>Channel</th><th>Last Access</th><th>Broadcast Time</th><th>Watch Time</th><th>Last Reset</th><th>Type</th><th>Logs</th></tr></thead>";

			if ( $items ) {
				foreach ( $items as $item ) {
					echo "<tr class='alternate'><th>" . esc_html( $item->name );

					if ( isset($_GET['regenerateThumbs']) ) {
						$stream   = $item->name;
						$filename = "$dir/$stream.jpg";

						if ( file_exists( $filename ) ) {
							// generate thumb
							$thumbWidth  = $options['thumbWidth'];
							$thumbHeight = $options['thumbHeight'];

							$src                  = imagecreatefromjpeg( $filename );
							list($width, $height) = getimagesize( $filename );
							$tmp                  = imagecreatetruecolor( $thumbWidth, $thumbHeight );

							$dir = $options['uploadsPath'] . '/_thumbs';
							if ( ! file_exists( $dir ) ) {
								mkdir( $dir );
							}

							$thumbFilename = "$dir/$stream.jpg";
							imagecopyresampled( $tmp, $src, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height );
							imagejpeg( $tmp, $thumbFilename, 95 );

							$sql = "UPDATE `$table_channels` set status='1' WHERE name ='$stream'";
							$wpdb->query( $sql );

						} else {
							echo "<div class='warning'>Snapshot missing!</div>";
							$sql = "UPDATE `$table_channels` set status='0' WHERE name ='$stream'";
							$wpdb->query( $sql );

						}
					}

					global $wpdb;
					$postID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_title = '" . $item->name . "' and post_type='channel' LIMIT 0,1" );

					if ( ! $options['anyChannels'] && ! $options['userChannels'] ) {
						if ( ! $postID ) {
							$wpdb->query( "DELETE FROM `$table_channels` WHERE name ='" . $item->name . "'" );
							echo '<br>DELETED: No channel post.';
						}
					}

					if ( $postID ) {
						echo ' <A target="_viewchannel" href="' . get_permalink( $postID ) . '">View</A>';
					}

					if ( $item->type >= 2 ) {
						$poptions = self::channelOptions( $item->type, $options );

						$maximumBroadcastTime = 60 * $poptions['pBroadcastTime'];
						$maximumWatchTime     = 60 * $poptions['pWatchTime'];

						$canWatch  = $poptions['canWatchPremium'];
						$watchList = $poptions['watchListPremium'];
					} else {
						$maximumBroadcastTime = 60 * $options['broadcastTime'];
						$maximumWatchTime     = 60 * $options['watchTime'];

						$canWatch  = $options['canWatch'];
						$watchList = $options['watchList'];
					}

					if ( ( $item->wtime > $maximumWatchTime && $maximumWatchTime ) || ( $item->btime > $maximumBroadcastTime && $maximumBroadcastTime ) ) {
						$warnCode = 'Warning: Channel ' . $item->name . ' consumed allocated time!';
					} else {
						$warnCode = '';
					}

					echo '</th><td>' . self::format_age( time() - intval( $item->edate ) ) . '</td><td>' . self::format_time( intval( $item->btime ) ) . ' / ' . ( intval( $maximumBroadcastTime ) ? self::format_time( intval( $maximumBroadcastTime ) ) : 'unlimited' ) . '</td><td>' . self::format_time( intval( $item->wtime ) ) . ' / ' . ( intval( $maximumWatchTime ) ? self::format_time( intval( $maximumWatchTime ) ) : 'unlimited' ) . '</td><td>' . self::format_age( time() - intval( $item->rdate ) ) . '</td><td>' . ( intval( $item->type ) > 1 ? 'Premium ' . intval( $item->type - 1 ) : 'Standard' ) . '</td>';

					// channel text logs
					$upload_c    = self::getDirectorySize( $options['uploadsPath'] . '/' . $item->name );
					$upload_size = self::sizeFormat( $upload_c['size'] );
					$logsurl     = self::path2url( $options['uploadsPath'] . '/' . $item->name );

					echo '<td>' . wp_kses_post( "<a target='_blank' href='$logsurl'>$upload_size ($upload_c[count] files)</a>" ) . '</td></tr>';
					if ( $warnCode ) {
						echo '<tr><td colspan="7">' . wp_kses_post( $warnCode ) . '</td></tr>';
					}

					$broadcasting = $wpdb->get_results( "SELECT * FROM `$table_sessions` WHERE room = '" . $item->name . "' ORDER BY edate DESC LIMIT 0, 100" );
					if ( $broadcasting ) {
						foreach ( $broadcasting as $broadcaster ) {
							$typeLabel = $broadcaster->type;
							if ( array_key_exists( $broadcaster->type, $typeLabels ) ) {
								$typeLabel = $typeLabels[ $broadcaster->type ];
							}

							echo "<tr><td colspan='7'> - " . esc_html( $broadcaster->username ) . ' Session Type: ' . esc_html( $typeLabel ) . ' Status: ' . esc_html( $broadcaster->status ) . ' Started: ' . self::format_age( time() - $broadcaster->sdate ) . '  Broadcaster updated: ' . self::format_age( time() - $broadcaster->edate ) . '</td></tr>';
						}
					}

					if ( $postID ) {
						$videoCodec = get_post_meta( $postID, 'stream-codec-video', true );
						if ( ! $videoCodec ) {
							$videoCodec = '';
						}

						$streamProtocol = get_post_meta( $postID, 'stream-protocol', true );
						if ( ! $streamProtocol ) {
							$streamProtocol = '';
						}

						$codecDetection = get_post_meta( $postID, 'stream-codec-detect', true );
						$codecAge       = 'Never';
						if ( $codecDetection ) {
							$codecAge = self::format_age( time() - $codecDetection );
						}

						$updatedAge = 'Never';
						$streamUpdated = get_post_meta( $postID, 'stream-updated', true );
						if ( $streamUpdated ) {
							$updatedAge = self::format_age( time() - $streamUpdated );
						}

						if ( $videoCodec || $streamProtocol ) {
							echo '<tr><td colspan="7">';
							if ( $videoCodec ) {
								echo 'Video Codec: ' . esc_html( $videoCodec ) . ' Audio Codec: ' . get_post_meta( $postID, 'stream-codec-audio', true ) . ' Detected: ' . esc_html( $codecAge ) . ' HLS: ' . get_post_meta( $postID, 'stream-hls', true );
							}
							if ( $webRTCmode = get_post_meta( $postID, 'stream-mode', true ) ) {
								echo ' WebRTC: ' . esc_html( $webRTCmode );
							}

							if ( $streamProtocol ) {
								echo ' Protocol: ' . esc_html( $streamProtocol ) . ' Type: ' . get_post_meta( $postID, 'stream-type', true ) . ' Broadcast session updated: ' . esc_html( $updatedAge );
							}
							echo ' </td></tr>';
						}
					}
				}
			}
			echo '</table>';
			?>
<p>This page shows latest accessed channels (maximum 200).</p>

<p>+ External players and encoders (if enabled) are not monitored or controlled by this plugin, unless special <a href="https://videowhisper.com/?p=RTMP-Session-Control">rtmp side session control</a> is available.</p>

<p>+  Configure streaming time limitations from these sections:
	<br> - <a href="admin.php?page=live-streaming&tab=broadcaster">Broadcaster Settings</a>
	<br> - <a href="admin.php?page=live-streaming&tab=premium">Membership Levels</a>
</p>

				<?php

				// channel text logs
				$upload_c    = self::getDirectorySize( $options['uploadsPath'] );
				$upload_size = self::sizeFormat( $upload_c['size'] );
				$logsurl     = self::path2url( $options['uploadsPath'] );

				echo wp_kses_post( '<p> + Total temporary file usage (logs, snapshots, session info): ' . " <a target='_blank' href='$logsurl'>$upload_size (in $upload_c[count] files and $upload_c[dircount] folders)</a>" . '</p>' );

		}

		static function adminLive() {
			$options = get_option( 'VWliveStreamingOptions' );

			$ban = sanitize_file_name( $_GET['ban'] );

			if ( $ban ) {
				?>
<h3>Banning Channel</h3>
				<?php
				global $wpdb;

				// delete post
				$postID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_title = '" . $ban . "' and post_type='channel' LIMIT 0,1" );
				if ( ! $postID ) {
					echo wp_kses_post( "<br>Channel post '$ban' not found!" );
				} else {
					wp_delete_post( $postID, true );
					echo wp_kses_post( "<br>Channel post '$ban' was deleted." );
				}

				// delete room
				$table_sessions = $wpdb->prefix . 'vw_lsrooms';
				$sql            = "DELETE FROM `$table_sessions` WHERE name = '$ban'";
				$wpdb->query( $sql );
				echo wp_kses_post( "<br>Channel room '$ban' was deleted." );

				// ban
				$options['bannedNames'] .= ( $options['bannedNames'] ? ',' : '' ) . $ban;
				update_option( 'VWliveStreamingOptions', $options );
				echo '<br>Current ban list: ' . esc_html( $options['bannedNames'] ) . ' <a href="admin.php?page=live-streaming&tab=broadcaster" class="button button-primary">Edit</a>';
			}

			// broadcast link if allowed by settings
			if ( $options['userChannels'] || $options['anyChannels'] ) {

				$root_url = get_bloginfo( 'url' ) . '/';
				$userName = $options['userName'];
				if ( ! $userName ) {
					$userName = 'user_nicename';
				}

				$current_user = wp_get_current_user();

				if ( $current_user->$userName ) {
					$username = $current_user->$userName;
				}
				$username = sanitize_file_name( $username );

				$broadcast_url = admin_url() . 'admin-ajax.php?action=vwls_broadcast&n=';

				?>

<h3>Channel '<?php echo esc_html( $username ); ?>': Go Live</h3>
<ul>
<li>
<a href="<?php echo esc_url( $broadcast_url ) . esc_attr( urlencode( $username ) ); ?>"><img src="<?php echo esc_url( $root_url ); ?>wp-content/plugins/videowhisper-live-streaming-integration/ls/templates/live/i_webcam.png"
align="absmiddle" border="0">Start Broadcasting</a>
</li>
<li>
<a href="<?php echo esc_url( $root_url ); ?>wp-content/plugins/videowhisper-live-streaming-integration/ls/channel.php?n=<?php echo esc_attr( $username ); ?>"><img src="
					<?php
					echo esc_url( $root_url );
					?>
				wp-content/plugins/videowhisper-live-streaming-integration/ls/templates/live/i_uvideo.png" align="absmiddle" border="0">View Channel</a>
</li>
</ul>
<p>To allow users to broadcast from frontend (as configured in settings), <a href='widgets.php'>enable the widget</a> and/or channel posts and frontend management page.
<br>On some templates/setups you also need to add the page to site menu.
</p>
				<?php
			}
			?>
<h3>Recent Channels</h3>
			<?php

			echo do_shortcode( '[videowhisper_channels ban="1" per_page="24"]' );

		}


		// ! Channel Features List

		static function roomFeatures() {
			return array(
				'recording'         => array(
					'name'        => 'Recording',
					'description' => 'Toggle on demand FFmpeg recording, per channel.',
					'installed'   => 1,
					'default'     => 'All',
				),
				'roomTags'          => array(
					'name'        => 'Room Tags',
					'description' => 'Can specify room tags.',
					'installed'   => 1,
					'default'     => 'All',
				),
				'accessPassword'    => array(
					'name'        => 'Access Password',
					'description' => 'Can specify a password to protect channel access.',
					'installed'   => 1,
					'default'     => 'Super Admin, Administrator, Editor',
				),
				'accessList'        => array(
					'name'        => 'Access List',
					'description' => 'Channel owner can specify list of user logins, roles, emails that can access the channel.',
					'installed'   => 1,
					'default'     => 'None',
				),
				'accessPrice'       => array(
					'name'        => 'Access Price',
					'description' => 'Can setup a price per channel. Requires myCRED plugin installed and integration enabled from Billing.',
					'type'        => 'number',
					'installed'   => 1,
					'default'     => 'None',
				),
				'chatList'          => array(
					'name'        => 'Chat List',
					'description' => 'Channel owner can specify list of user logins, roles, emails that can access the public chat.',
					'installed'   => 1,
					'default'     => 'None',
				),
				'writeList'         => array(
					'name'        => 'Chat Write List',
					'description' => 'Channel owner can specify list of user logins, roles, emails that can write in public chat.',
					'installed'   => 1,
					'default'     => 'None',
				),
				'participantsList'  => array(
					'name'        => 'Participants List',
					'description' => 'Channel owner can specify list of user logins, roles, emails that can view participants list.',
					'installed'   => 1,
					'default'     => 'None',
				),
				'privateChatList'   => array(
					'name'        => 'Private Chat List',
					'description' => 'Channel owner can specify list of user logins, roles, emails that can initiate private chat.',
					'installed'   => 1,
					'default'     => 'None',
				),
				'uploadPicture'     => array(
					'name'        => 'Upload Picture',
					'description' => 'Upload channel picture.',
					'installed'   => 1,
					'default'     => 'Super Admin, Administrator, Editor, Subscriber',
				),
				'eventDetails'      => array(
					'name'        => 'Event Details',
					'description' => 'Specify event title, start, end, description to show when show is offline.',
					'installed'   => 1,
					'default'     => 'Super Admin, Administrator, Editor, Subscriber',
				),
				'logoHide'          => array(
					'name'        => 'Hide Logo',
					'description' => 'Hides logo from channel.',
					'installed'   => 1,
					'default'     => 'Super Admin, Administrator, Editor',
				),
				'logoCustom'        => array(
					'name'        => 'Custom Logo',
					'description' => 'Can setup a custom logo. Overrides hide logo feature.',
					'installed'   => 1,
					'default'     => 'Super Admin, Administrator',
				),
				'adsHide'           => array(
					'name'        => 'Hide Ads',
					'description' => 'Hides ads from channel.',
					'installed'   => 1,
					'default'     => 'Super Admin, Administrator, Editor',
				),
				'ipCameras'         => array(
					'name'        => 'IP Cameras',
					'description' => 'Can configure re-streaming, including for IP cameras.',
					'installed'   => 1,
					'default'     => 'None',
				),
				'schedulePlaylists' => array(
					'name'        => 'Playlist Scheduler',
					'description' => 'Can schedule channel playlist from VideoShareVOD videos.',
					'installed'   => 1,
					'default'     => 'None',
				),
				'adsCustom'         => array(
					'name'        => 'Custom Ads',
					'description' => 'Can setup a custom ad server. Overrides hide ads feature.',
					'installed'   => 1,
					'default'     => 'None',
				),
				'transcode'         => array(
					'name'        => 'Transcode',
					'description' => 'Enable transcoding for user channels.',
					'installed'   => 1,
					'default'     => 'Super Admin, Administrator, Editor',
				),
				'privateList'       => array(
					'name'        => 'Private Channels',
					'description' => 'Hide channels from public listings. Can be accessed by channel links.',
					'installed'   => 0,
				),
				'privateChat'       => array(
					'name'        => 'Private Chat',
					'description' => 'Disable chat from site watch interface.',
					'installed'   => 0,
				),
				'privateVideos'     => array(
					'name'        => 'Private Videos',
					'description' => 'Channel videos do not show in public listings. Only show on channel page.',
					'installed'   => 0,
				),
				'hiddenVideos'      => array(
					'name'        => 'Hidden Videos',
					'description' => 'Channel videos do not show in public or channel listings. Only owner can browse.',
					'installed'   => 0,
				),
			);
		}


		// ! App Calls / integration, auxiliary

		static function editParameters( $default = '', $update = array(), $remove = array() ) {
			// adjust parameters string by update(add)/remove

			parse_str( substr( $default, 1 ), $params );

			// remove

			if ( count( $update ) ) {
				foreach ( $params as $key => $value ) {
					if ( in_array( $key, $update ) ) {
						unset( $params[ $key ] );
					}
				}
			}

			if ( count( $remove ) ) {
				foreach ( $params as $key => $value ) {
					if ( in_array( $key, $remove ) ) {
						unset( $params[ $key ] );
					}
				}
			}

							// add updated
			if ( count( $update ) ) {
				foreach ( $update as $key => $value ) {
							$params[ $key ] = $value;
				}
			}

								return '&' . http_build_query( $params );
		}



		static function webSessionSave( $username, $canKick = 0, $debug = '0', $ip = '' ) {
			// generates a session file record for rtmp login check

			$username = sanitize_file_name( $username );

			if ( $username ) {
				$options = get_option( 'VWliveStreamingOptions' );
				$webKey  = $options['webKey'];
				$ztime   = time();

				$ztime = time();
				$info  = 'VideoWhisper=1&login=1&webKey=' . urlencode( $webKey ) . '&start=' . $ztime . '&ip=' . urlencode( $ip ) . '&canKick=' . $canKick . '&debug=' . urlencode( $debug );

				$dir = $options['uploadsPath'];
				if ( ! file_exists( $dir ) ) {
					mkdir( $dir );
				}
				@chmod( $dir, 0777 );
				$dir .= '/_sessions';
				if ( ! file_exists( $dir ) ) {
					mkdir( $dir );
				}
				@chmod( $dir, 0777 );

				$dfile = fopen( $dir . "/$username", 'w' );
				fputs( $dfile, $info );
				fclose( $dfile );
			}

		}

		static function sessionUpdate( $username = '', $room = '', $broadcaster = 0, $type = 1, $strict = 1, $updated = 1, $userID = 0, $postID = 0, $options = null ) {
			// update session in mysql

			// type 1=http, 2=rtmp, 3=webrtc , 11 = HTML5 Videochat
			// strict = create new if not that type
			// updated = return updated session unless missing (otherwise return old for delta calculations)

			if ( ! $username ) {
				return;
			}
			$ztime = time();

			if (!$userID) if ( is_user_logged_in() ) $userID = get_current_user_id();

			global $wpdb;
			if (!$options) $options = self::getOptions();

			if (!$postID)
			{
				$postID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_title = '" . $room . "' and post_type='" . $options['custom_post']. "' LIMIT 0,1" );
			}

			if ( $broadcaster ) {
				$table_sessions = $wpdb->prefix . 'vw_sessions';
			} else {
				$table_sessions = $wpdb->prefix . 'vw_lwsessions';
			}

			$cnd = '';
			if ( $strict ) {
				$cnd = " AND `type`='$type'";
			}

			// online broadcasting session, status: 0=pending 1=active 2=ended
			$sqlS    = "SELECT * FROM $table_sessions where session='$username' and status < 2 $cnd ORDER BY edate DESC LIMIT 0,1";
			$session = $wpdb->get_row( $sqlS );

			if ( ! $session ) {

				$meta = serialize(['created_by' => 'sessionUpdate', 'postID' => $postID, 'userID' => $userID ]);
				$sql = "INSERT INTO `$table_sessions` ( `session`, `username`, `room`, `message`, `sdate`, `edate`, `status`, `type`, `uid`, `rid`, `meta`) VALUES ('$username', '$username', '$room', '', $ztime, $ztime, 1, $type, $userID, $postID, '$meta')";
			} else {

				//more updates, if needed
				$more = '';
				if ( $userID && $session->uid != $userID ) $more .= ", uid=$userID";
				if ( $postID && $session->rid != $postID ) $more .= ", rid=$postID";
				if ( $session->status != 1 ) $more .= ", status=1"; //0: pending

				$sql = "UPDATE `$table_sessions` set edate=$ztime $more where id ='" . $session->id . "'";
			}
			$wpdb->query( $sql );

			if ( $broadcaster ) if ( $postID ) {
					update_post_meta( $postID, 'edate', $ztime );
				}

			//clean old sessions
			self::cleanSessions($broadcaster);

			if ( $updated || ! $session ) {
				$session = $wpdb->get_row( $sqlS );
			}

			return $session;
		}

		static function cleanSessions( $broadcaster = 0 ) {

			$options = get_option( 'VWliveStreamingOptions' );

			if ( ! self::timeTo( 'cleanSessions' . $broadcaster, 25, $options ) ) {
				return;
			}

			$ztime = time();
			global $wpdb;

			if ( $broadcaster ) {
				$table_sessions = $wpdb->prefix . 'vw_sessions';
			} else {
				$table_sessions = $wpdb->prefix . 'vw_lwsessions';
			}

			//close timed out sessions
			$webStatusInterval = $options['webStatusInterval'] ?? 60;
			if ( $webStatusInterval < 10 ) {
				$webStatusInterval = 60;
			}
			$timed = $ztime - $webStatusInterval - 10;
			$sql     = "UPDATE `$table_sessions` SET status = 2 WHERE status < 2 AND edate < $timed";
			$wpdb->query( $sql );

			//delete expired sessions
			if ( ! $options[ 'onlineExpiration' . $broadcaster ] ) {
				$options[ 'onlineExpiration' . $broadcaster ] = 310;
			}
			$exptime = $ztime - $options[ 'onlineExpiration' . $broadcaster ];
			$sql     = "DELETE FROM `$table_sessions` WHERE edate < $exptime";
			$wpdb->query( $sql );
		}

		static function streamSnapshot( $stream, $ipcam = false, $postID = 0 ) {


			$stream = sanitize_file_name( $stream );
			if ( strstr( $stream, '.php' ) ) {
				return;
			}
			if ( ! $stream ) {
				return;
			}

			$options = get_option( 'VWliveStreamingOptions' );
			self::log( 'streamSnapshot: ' . $stream . ' ' . $ipcam . ' #' . $postID , 5, $options);

			$dir = $options['uploadsPath'];
			if ( ! file_exists( $dir ) ) {
				mkdir( $dir );
			}
			$dir .= '/_snapshots';
			if ( ! file_exists( $dir ) ) {
				mkdir( $dir );
			}

			if ( ! file_exists( $dir ) ) {
				$error = error_get_last();
				echo 'Error - Folder does not exist and could not be created: ' . esc_html( $dir ) . ' - ' . esc_html( $error['message'] );
			}

			$filename = "$dir/$stream.jpg";
			if ( file_exists( $filename ) ) {
				if ( time() - filemtime( $filename ) < 15 ) {
					return; // do not update if fresh (15s)
				}
			}

				// get channel id $postID
				global $wpdb;
			if ( ! $postID ) {
				$postID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name( $stream ) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );
			}

			//get $userID as author from $postID
			$userID = get_post_field( 'post_author', $postID );

			$streamAddress  = trim( get_post_meta( $postID, 'vw_ipCamera', true ) );

			$restreamPaused = get_post_meta( $postID, 'restreamPaused', true );
			if ( $restreamPaused && $streamAddress ) {
				return; // no snapshot while paused for ip cameras
			}

			// get primary stream source (rtmp/rtsp)
			$sourceProtocol = get_post_meta( $postID, 'stream-protocol', true );
			$streamType     = get_post_meta( $postID, 'stream-type', true );

			$log_file = $filename . '.txt';
			$lastLog  = $options['uploadsPath'] . '/lastLog-streamSnapshot.txt';


			if ( $streamType == 'restream' && $streamAddress ) {
				// retrieve from main source
				$cmdP = '';

				$cmdT = '';

				// movie streams start with blank screens
				if ( strstr( $streamAddress, '.mp4' ) || strstr( $streamAddress, '.mov' ) || strstr( $streamAddress, 'mp4:' ) ) {
					$cmdT = '-ss 00:00:02';
				}

				if ( $sourceProtocol == 'rtsp' ) {
					$cmdP = '-rtsp_transport tcp'; // use tcp for rtsp
				}
				$cmd = $options['ffmpegSnapshotTimeout'] . ' ' . $options['ffmpegPath'] . " -y -frames 1 \"$filename\" $cmdP $cmdT -i \"" . $streamAddress . "\" >& $log_file  " . $options['ffmpegSnapshotBackground'];

			} elseif ( $sourceProtocol == 'rtsp' ) {
				$streamQuery = self::webrtcStreamQuery( $userID, $postID, 0, $stream, $options, 1 );

				// usually webrtc
				$cmd = $options['ffmpegSnapshotTimeout'] . ' ' . $options['ffmpegPath'] . " -y  -f image2 -vframes 1 \"$filename\" -i \"" . $options['rtsp_server'] . '/' . $streamQuery . "\" >& $log_file " . $options['ffmpegSnapshotBackground'];
			} else {

				if ($options['rtmpServer'] == 'videowhisper')
			{
				//hls more reliable for videowhisper rtmp/hls
				$rtmpAddressView = trim($options['videowhisperHLS']) .'/' . trim($options['vwsAccount']). '/'.  $stream. '/index.m3u8?pin=' . self::getPin($postID, 'playback', $options) . '&token=' . $options['vwsToken'];  
			}elseif ( $options['externalKeysTranscoder'] ) {
					$keyView         = md5( 'vw' . $options['webKey'] . intval( $postID ) );
					$rtmpAddressView = $options['rtmp_server'] . '?' . urlencode( 'ffmpegSnap_' . $stream ) . '&' . urlencode( $stream ) . '&' . $keyView . '&0&videowhisper';
				} else {
					$rtmpAddressView = $options['rtmp_server'];
				}

				$cmd = $options['ffmpegSnapshotTimeout'] . ' ' . $options['ffmpegPath'] . " -y -f image2 -vframes 1 \"$filename\" -i \"" . $rtmpAddressView . '/' . $stream . "\" >& $log_file " . $options['ffmpegSnapshotBackground'];
			}

			// escape
			// $cmd = escapeshellcmd($cmd);

			// echo $cmd;
			if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );
			if ( $options['enable_exec'] ) exec( "echo 'Command: $cmd Return: $returnvalue Output[0]: " . ($output[0] ?? ''). "'  >> $log_file.cmd", $output, $returnvalue );

			self::varSave(
				$lastLog,
				array(
					'file'    => $log_file,
					'cmd'     => $cmd,
					'return'  => $returnvalue,
					'output0' => $output[0] ?? '',
					'time'    => time(),
				)
			);

			self::log( 'streamSnapshot: $stream=' . $stream . ' $cmd=' . $cmd . ' $returnvalue=' . $returnvalue . ' $output0=' . ( $output[0] ?? '')  . ' $log_file=' . $log_file, 4, $options);

			// failed
			if ( ! file_exists( $filename ) ) {
				self::log( 'streamSnapshot: failed (missing ' . $filename . ') $stream=' . $stream . ' $cmd=' . $cmd . ' $returnvalue=' . $returnvalue . ' $output0=' . ( $output[0] ?? '' ) . ' $log_file=' . $log_file, 3, $options);
				return;
			}

			// may be old snapshot!!! maybe compare date with edate or store thumb date / check later
			$thumbTime = get_post_meta( $postID, 'thumbTime', true );
			$fileTime  = filemtime( $filename );
			if ( $fileTime <= $thumbTime ) {
				return; // old file, already processed
			}

			// if snapshot successful (from stream) update edate
			$ztime = time();
			update_post_meta( $postID, 'edate', $ztime );
			update_post_meta( $postID, 'vw_lastSnapshot', $filename );

			if ( $ipcam ) {
				// also update current number of viewers
				self::updateViewers( $postID, $stream, $options );
			}

			// generate thumb
			$thumbWidth  = $options['thumbWidth'];
			$thumbHeight = $options['thumbHeight'];

			$src                  = imagecreatefromjpeg( $filename );
			list($width, $height) = getimagesize( $filename );
			$tmp                  = imagecreatetruecolor( $thumbWidth, $thumbHeight );

			$dir = $options['uploadsPath'] . '/_thumbs';
			if ( ! file_exists( $dir ) ) {
				mkdir( $dir );
			}

			$thumbFilename = "$dir/$stream.jpg";
			imagecopyresampled( $tmp, $src, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height );
			imagejpeg( $tmp, $thumbFilename, 95 );

			// update room status to 1 or 2
			$table_channels = $wpdb->prefix . 'vw_lsrooms';

			// detect tiny images without info
			if ( filesize( $thumbFilename ) > 1000 ) {
				$picType = 1;
			} else {
				$picType = 2;
			}

			// table
			$sql = "UPDATE `$table_channels` set status='$picType', edate='$ztime' where name ='$stream'";
			$wpdb->query( $sql );

			// update post meta
			update_post_meta( $postID, 'hasPicture', $picType );
			update_post_meta( $postID, 'hasSnapshot', 1 );
			update_post_meta( $postID, 'thumbTime', $ztime );

		}

		static function rtmpSnapshot( $session ) {

			self::log( 'rtmpSnapshot: ' . $session->session, 4);
			self::streamSnapshot( $session->session );
		}

		static function premiumOptions( $userkeys, $options ) {

			$premiumLev = unserialize( $options['premiumLevels'] );

			if ( $options['premiumLevelsNumber'] ) {
				for ( $i = ( $options['premiumLevelsNumber'] - 1 ); $i >= 0; $i-- ) {
					if ( isset($premiumLev[ $i ])) if ( $premiumLev[ $i ]['premiumList'] ) {
						if ( self::inList( $userkeys, $premiumLev[ $i ]['premiumList'] ) ) {
							return $premiumLev[ $i ];
						}
					}
				}
			}

					// not found
					return false;
		}

		static function channelOptions( $type, $options ) {
			$premiumLev = unserialize( $options['premiumLevels'] );

			$i = $type - 2;
			if ( $premiumLev[ $i ] ) {
				return $premiumLev[ $i ];
			}

			// regular channel
			return $options;
		}

		/*
		static function premiumLevel($userkeys, $options)
		{

			$premiumLev = unserialize($options['premiumLevels']);

			if ($options['premiumLevelsNumber'])
				for ($i=$options['premiumLevelsNumber'] - 1 ; $i >= 0 ; $i--)
				if ($premiumLev[$i]['premiumList'])
					if (!VWliveStreaming::inList($userkeys, $premiumLev[$i]['premiumList'])) return ($i+1);

			return 0;
		}
		*/

		// ! Online user functions
		static function updateViewers( $postID, $room, $options ) {

			if ( ! $room ) {
				$room = 'room_' . $postID;
			}
			if ( ! self::timeTo( $room . '/updateViewers', 59, $options ) ) {
				return;
			}

			if ( ! $options ) {
				$options = get_option( 'VWliveStreamingOptions' );
			}

			self::cleanSessions( 1 );

			// update viewers
			$ztime = time();

			global $wpdb;
			$table_viewers = $wpdb->prefix . 'vw_lwsessions';
			$viewers       = $wpdb->get_var( $sql = "SELECT count(id) AS no FROM `$table_viewers` WHERE status='1' AND rid='" . $postID . "'" );

			update_post_meta( $postID, 'viewers', $viewers );
			update_post_meta( $postID, 'viewersUpdate', $ztime );

			$maxViewers = get_post_meta( $postID, 'maxViewers', true );
			if ( $viewers >= $maxViewers ) {
				update_post_meta( $postID, 'maxViewers', $viewers );
				update_post_meta( $postID, 'maxDate', $ztime );
			}

			$lastLog = $options['uploadsPath'] . '/lastLog-updateViewers.txt';
			self::varSave(
				$lastLog,
				array(
					'sql'        => $sql,
					'viewers'    => $viewers,
					'maxViewers' => $maxViewers,
					'date'       => $ztime,
					'postID'     => $postID,
					'room'       => $room,
				)
			);
		}

		static function timeTo( $action, $expire = 60, $options = '' ) {
			// if $action was already done in last $expire, return false

			if ( ! $options ) {
				$options = get_option( 'VWliveStreamingOptions' );
			}

			$cleanNow = false;

			$ztime = time();

			$lastClean     = 0;
			$lastCleanFile = $options['uploadsPath'] . '/' . $action . '.txt';

			if ( ! file_exists( $options['uploadsPath'] ) ) {
				mkdir( $options['uploadsPath'] );
			}

			if ( ! file_exists( $dir = dirname( $lastCleanFile ) ) ) {
				mkdir( $dir );
			} elseif ( file_exists( $lastCleanFile ) ) {
				$lastClean = intval( file_get_contents( $lastCleanFile ) );
			}

			if ( ! $lastClean ) {
				$cleanNow = true;
			} elseif ( $ztime - $lastClean > $expire ) {
				$cleanNow = true;
			}

			if ( $cleanNow ) {
				file_put_contents( $lastCleanFile, $ztime );
			}

				return $cleanNow;

		}



		static function userWatchLimit( $user, $options ) {

			$userLimit = $options['userWatchLimitDefault'];

			if ( is_array( $options['userWatchLimits'] ) ) {
				foreach ( $options['userWatchLimits'] as $role => $limit ) {
					if ( in_array( strtolower( $role ), $user->roles ) ) {
						if ( ! $limit ) {
							$userLimit = 0;
							break; // no more search
						}

						if ( $limit > $userLimit ) {
							$userLimit = $limit; // upgrade limit (best applies)
						}
					}
				}
			}

			return intval( $userLimit );
		}

		static function updateUserWatchtime( $user, $dS, $options ) {
			if ( ! $user ) {
				return;
			}
			if ( ! $user->ID ) {
				return;
			}

			if ( ! $options ) {
				$options = get_option( 'VWliveStreamingOptions' );
			}

			// update watch time
			// check if new interval
			$lastUpdate = get_user_meta( $user->ID, 'vwls_watch_update', true );

			if ( $lastUpdate < time() - $options['userWatchInterval'] ) {
				update_user_meta( $user->ID, 'vwls_watch_update', time() );
				update_user_meta( $user->ID, 'vwls_watch', $dS );
				$currentWatch = $dS;

			} else {
				$currentWatch  = get_user_meta( $user->ID, 'vwls_watch', true );
				$currentWatch += $dS;
				update_user_meta( $user->ID, 'vwls_watch', $currentWatch );
			}

			$userLimit = self::userWatchLimit( $user, $options );

			if ( ! $userLimit ) {
				return; // unlimited
			}

			if ( $currentWatch > $userLimit ) {
				return 1; // return 1 if exceeded
			}

			return; // limit not reached

		}


		static function userParameters( $user, $config ) {
			if ( ! $user ) {
				return;
			}
			if ( ! $user->ID ) {
				return;
			}

			$parameters = array();

			if ( is_array( $config ) ) {
				foreach ( $config as $parameter => $roleValue ) {
					foreach ( $roleValue as $role => $value ) {
						if ( in_array( strtolower( $role ), $user->roles ) ) {
							$parameters[ $parameter ] = $value;
						}
					}
				}
			}

						return $parameters;

		}

		static function rexit( $output ) {
			$output = wp_kses_post( $output );
			self::log( 'rexit: ' . $output , 3);
			exit;
		}

		/**
		 * Retrieves the best guess of the client's actual IP address.
		 * Takes into account numerous HTTP proxy headers due to variations
		 * in how different ISPs handle IP addresses in headers between hops.
		 */
		static function get_ip_address() {
			$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' );
			foreach ( $ip_keys as $key ) {
				if ( array_key_exists( $key, $_SERVER ) === true ) {
					foreach ( explode( ',', sanitize_text_field( $_SERVER[ $key ] ) ) as $ip ) {
						// trim for safety measures
						$ip = trim( $ip );
						// attempt to validate IP
						if ( self::validate_ip( $ip ) ) {
							return $ip;
						}
					}
				}
			}
			return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : false;
		}

		/**
		 * Ensures an ip address is both a valid IP and does not fall within
		 * a private network range.
		 */
		static function validate_ip( $ip ) {
			if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
				return false;
			}
			return true;
		}


		static function currentUserSession( $room ) {

			if ( ! is_user_logged_in() ) {
				return 0;
			}
			if ( ! $room ) {
				return 0;
			}
			if ( $room == 'null' ) {
				return 0;
			}

			global $current_user;
			get_currentuserinfo();

			$options = get_option( 'VWliveStreamingOptions' );

			$userfield = $options['userName'];
			$username1 = $current_user->$userfield;

			global $wpdb;
			$table_sessions = $wpdb->prefix . 'vw_vwls_sessions';

			$sql     = "SELECT * FROM `$table_sessions` WHERE session='$username1' AND room='$room' AND status='1' LIMIT 1";
			$session = $wpdb->get_row( $sql );

			return $session;

		}

		// ! Ajax App Calls

		static function vwls_calls() {
			// Handles calls from Wowza SE - VideoWhisper module, Flash calls removed

			global $wpdb;
			global $current_user;

			if (ob_get_length()) ob_clean();

			$options = get_option( 'VWliveStreamingOptions' );


			switch ( $_GET['task'] ) {

				// ! rtmp_status
				case 'rtmp_status':

					// allow such requests only if feature is enabled (by default is not)
					if ( ! in_array( $options['webStatus'], array( 'enabled', 'strict' ) ) ) {
						self::log('rtmp_status/denied=webStatusNotEnabled-' . $options['webStatus'], 2);
						self::rexit( 'denied=webStatusNotEnabled-' . $options['webStatus'] );
					}

					// allow only status updates from configured server IP
					if ( $options['rtmp_restrict_ip'] ) {
						$allowedIPs = explode( ',', $options['rtmp_restrict_ip'] );
						$requestIP  = self::get_ip_address();

						$found = 0;
						foreach ( $allowedIPs as $allowedIP ) {
							if ( $requestIP == trim( $allowedIP ) ) {
								$found = 1;
							}
						}

						if ( ! $found ) {
							self::log('rtmp_status/denied=NotFromAllowedIP-' . $requestIP, 2);
							self::rexit( 'denied=NotFromAllowedIP-' . $requestIP );
						}
					} else {
						self::log('rtmp_status/denied=StatusServerIPnotConfigured-' . $requestIP, 2);
						self::rexit( 'denied=StatusServerIPnotConfigured' );
					}

					self::log( 'vwls_calls()/rtmp_status: $_POST=' . json_encode( $_POST ), 5, $options);

					// self::requirementUpdate('rtmp_status', 1);
					self::requirementMet( 'rtmp_status' );

					// start logging
					$dir       = $options['uploadsPath'];
					$filename1 = $dir . '/_rtmpStatus.txt';
					$dfile     = fopen( $filename1, 'w' );

					fputs( $dfile, 'VideoWhisper Log for RTMP Session Control' . "\r\n" );
					fputs( $dfile, 'Server Date: ' . "\r\n" . date( 'D M j G:i:s T Y' ) . "\r\n" );
					fputs( $dfile, '$_POST:' . "\r\n" . json_encode( $_POST  ) );

					// start with a cleanup for viewers and broadcasters
					self::cleanSessions( 0 );
					self::cleanSessions( 1 );

					// sessions table
					global $wpdb;
					$table_channels = $wpdb->prefix . 'vw_lsrooms';

					$wpdb->flush();
					$ztime = time();

					$controlUsers    = array();
					$controlSessions = array();

					// rtpsessions - WebRTC
					$rtpsessiondata = sanitize_textarea_field( stripslashes( $_POST['rtpsessions'] ) );

					if ( version_compare( phpversion(), '7.0', '<' ) ) {
						$rtpsessions = unserialize( $rtpsessiondata );  // request is from trusted server
					} else {
						$rtpsessions = unserialize( $rtpsessiondata, array() );
					}

					$webrtc_test = 0;
					if ( is_array( $rtpsessions ) ) {
						foreach ( $rtpsessions as $rtpsession ) {

							$disconnect = '';

							if ( ! $options['webrtc'] ) {
								$disconnect = 'WebRTC is disabled.';
							}

							$stream      = $rtpsession['streamName'];
							$streamQuery = array();

							if ( $rtpsession['streamQuery'] ) {

								parse_str( $rtpsession['streamQuery'], $streamQuery );

								if ( $userID = (int) $streamQuery['userID'] ) {
									$user = get_userdata( $userID );

									$userName = $options['userName'];
									if ( ! $userName ) {
																$userName = 'user_nicename';
									}
									if ( $user->$userName ) {
										$username = urlencode( $user->$userName );
									}
								}
							}

							if ( $channel_id = (int) $streamQuery['channel_id'] ) {
								$post = get_post( $channel_id );

							} else {
								$disconnect = 'No channel ID.';
							}

							$transcoding = $streamQuery['transcoding']; // just a transcoding

							// WebRTC session vars

							$r = $stream;
							$u = $username;

							if ( $rtpsession['streamPublish'] == 'true' && $userID && ! $disconnect && ! $transcoding ) {
								$s = $stream;
								$m = 'WebRTC Broadcaster';

								// webrtc broadcast test
								if ( ! $webrtc_test ) {
									self::requirementMet( 'webrtc_test' );
									$webrtc_test = 1;
								}

								$keyBroadcast = md5( 'vw' . $options['webKey'] . intval( $userID ) . intval( $channel_id ) );
								if ( $streamQuery['key'] != $keyBroadcast ) {
									$disconnect = 'WebRTC broadcast key mismatch.';
								}

								if ( ! $post ) {
									$disconnect = 'Channel post not found.';
								} elseif ( $post->post_author != $userID ) {
									$disconnect = 'Only channel owner can broadcast.';
								}

								if ( ! $disconnect ) {

									// sessionUpdate($username='', $room='', $broadcaster=0, $type=1, $strict=1);
									$session = self::sessionUpdate( $s, $r, 1, 3, 1, 0 );

									/*
									//user online
									$table_sessions = $wpdb->prefix . "vw_sessions";

									$sqlS = "SELECT * FROM $table_sessions WHERE session='$s' AND status='1' ORDER BY type DESC, edate DESC LIMIT 0,1";
									$session = $wpdb->get_row($sqlS);

									if (!$session)
									{
									$sql="INSERT INTO `$table_sessions` ( `session`, `username`, `room`, `message`, `sdate`, `edate`, `status`, `type`) VALUES ('$s', '$u', '$r', '$m', $ztime, $ztime, 1, 2)";
									$wpdb->query($sql);
									$session = $wpdb->get_row($sqlS);
									}

									//update session
									$sql="UPDATE `$table_sessions` set edate=$ztime where id='".$session->id."'";
									$wpdb->query($sql);
									*/

									// generate external snapshot for external WebRTC broadcaster if enabled
									if ( $options['rtpSnapshots'] && floatval( $rtpsession['runSeconds'] ) > 5 ) self::rtmpSnapshot( $session ); //calls streamSnapshot

									//self::streamSnapshot( $stream, false, $channel_id );

									$sqlC    = "SELECT * FROM $table_channels WHERE name='" . $r . "' LIMIT 0,1";
									$channel = $wpdb->get_row( $sqlC );

									if ( $ban = self::containsAny( $channel->name, $options['bannedNames'] ) ) {
										$disconnect = "Room banned ($ban)!";
									}

									// calculate time in ms based on previous request
									$lastTime    = $session->edate * 1000;
									$currentTime = $ztime * 1000;
									$btime       = intval( $channel->btime );

									// update time
									$expTime = $options['onlineExpiration1'] + 60;
									$dS      = floor( ( $currentTime - $lastTime ) / 1000 );
									// if ($dS > $expTime || $dS<0) $disconnect = "Web server out of sync for webrtc broadcaster ($dS > $expTime) !"; //Updates should be faster; fraud attempt?

									$btime += $dS;

									// update room
									$sql = "UPDATE `$table_channels` set edate=$ztime, btime = " . $btime . " where id = '" . $channel->id . "'";
									$wpdb->query( $sql );

									// update post
									$postID = $channel_id;

									if ( $postID ) {
										update_post_meta( $postID, 'edate', $ztime );
										update_post_meta( $postID, 'btime', $btime );

										update_post_meta( $postID, 'stream-protocol', 'rtsp' );
										update_post_meta( $postID, 'stream-type', 'webrtc' );

										self::updateViewers( $postID, $r, $options );
									}

									// transcode stream (from RTSP)
									if ( ! $disconnect ) {
										if ( $options['transcodingAuto'] >= 2 ) {
																		self::transcodeStreamWebRTC( $session->room, $postID, $options );
										}
									}

									// handle recording
									if ( ! $disconnect ) {
										self::streamRecord( $session, $session->room, 'rtsp', $postID, $options );
									}

									// room usage
									// options in minutes
									// mysql in s
									// flash in ms (minimise latency errors)

									if ( $channel->type >= 2 ) {
										$poptions = self::channelOptions( $channel->type, $options );

										$maximumBroadcastTime = 60 * $poptions['pBroadcastTime'];
										$maximumWatchTime     = 60 * $poptions['pWatchTime'];
									} else {
										$maximumBroadcastTime = 60 * $options['broadcastTime'];
										$maximumWatchTime     = 60 * $options['watchTime'];
									}

									$maximumSessionTime = $maximumBroadcastTime; // broadcaster

									$timeUsed = $channel->btime * 1000;

									if ( $maximumBroadcastTime && $maximumBroadcastTime < $btime ) {
										$disconnect = 'Allocated broadcasting time ended!';
									}
									if ( $maximumWatchTime && $maximumWatchTime < $channel->wtime ) {
										$disconnect = 'Allocated watch time ended!';
									}

									$maximumSessionTime *= 1000;
								}

								// end WebRTC broadcaster session
							}

							if ( $rtpsession['streamPlay'] == 'true' && ! $disconnect ) {

								$s = $username . '_' . $stream;

								// sessionUpdate($username='', $room='', $broadcaster=0, $type=1,  $strict=1, $updated=1);
								$session = self::sessionUpdate( $s, $r, 0, 3, 1, 0 );

								/*
								$table_sessions = $wpdb->prefix . "vw_lwsessions";

								//update viewer online
								$sqlS = "SELECT * FROM $table_sessions WHERE session='$s' AND status='1' ORDER BY type DESC, edate DESC LIMIT 0,1";

								$session = $wpdb->get_row($sqlS);
								if (!$session) //insert external viewer type=2
								{
								$sql="INSERT INTO `$table_sessions` ( `session`, `username`, `room`, `message`, `sdate`, `edate`, `status`, `type`) VALUES ('$s', '$u', '$r', '', $ztime, $ztime, 1, 2)";
								$wpdb->query($sql);
								$session = $wpdb->get_row($sqlS);
								};

								$sql="UPDATE `$table_sessions` set edate=$ztime where id='".$session->id."'";
								$wpdb->query($sql);
								*/

								if ( $session->type >= '2' ) {

									$sqlC    = "SELECT * FROM $table_channels WHERE name='" . $session->room . "' LIMIT 0,1";
									$channel = $wpdb->get_row( $sqlC );

									// calculate time in ms based on previous request
									$lastTime    = $session->edate * 1000;
									$currentTime = $ztime * 1000;
									$wtime       = intval( $channel->wtime );

									// update room time
									$expTime = $options['onlineExpiration0'] + 40;

									$dS = floor( ( $currentTime - $lastTime ) / 1000 );
									if ( $dS > $expTime || $dS < 0 ) {
										$disconnect = "Web server out of sync ($dS > $expTime)!"; // Updates should be faster than 3 minutes; disconnected and returned on same session?
									}

									$wtime += $dS;

									// update
									$sql = "UPDATE `$table_channels` set wtime = " . $wtime . " where id = '" . $channel->id . "'";
									$wpdb->query( $sql );

									// update post
									$postID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_title = '" . $r . "' and post_type='channel' LIMIT 0,1" );
									if ( $postID ) {
										update_post_meta( $postID, 'wtime', $wtime );
									}

									// update user watch time, disconnect if exceeded limit
									$user = get_user_by( 'login', $u );
									if ( $user ) {
										if ( self::updateUserWatchtime( $user, $dS, $options ) ) {
											$disconnect = urlencode( 'User watch time limit exceeded!' );
										}
									}
								}
								// room usage
								// options in minutes
								// mysql in s
								// flash in ms (minimise latency errors)

								if ( $channel->type >= 2 ) {
									$poptions = self::channelOptions( $channel->type, $options );

									$maximumBroadcastTime = 60 * $poptions['pBroadcastTime'];
									$maximumWatchTime     = 60 * $poptions['pWatchTime'];
								} else {
									$maximumBroadcastTime = 60 * $options['broadcastTime'];
									$maximumWatchTime     = 60 * $options['watchTime'];
								}

								$maximumSessionTime = $maximumWatchTime;

								$timeUsed = $channel->wtime * 1000;

								if ( $maximumBroadcastTime && $maximumBroadcastTime < $channel->btime ) {
									$disconnect = 'Allocated broadcasting time ended!';
								}
								if ( $maximumWatchTime && $maximumWatchTime < $wtime ) {
									$disconnect = 'Allocated watch time ended!';
								}

								$maximumSessionTime *= 1000;

								// end WebRTC playback
							}

							$controlSession['disconnect'] = $disconnect;

							$controlSession['session']  = $s;
							$controlSession['dS']       = intval( $dS );
							$controlSession['type']     = $session->type;
							$controlSession['room']     = $r;
							$controlSession['username'] = $u;

							// $controlSession['query'] = $rtpsession['streamQuery'];

							$controlSessions[ $rtpsession['sessionId'] ] = $controlSession;

							// end  foreach ($rtpsessions as $rtpsession)
						}
					}

					$controlSessionsS = serialize( $controlSessions );

					// debug update
					fputs( $dfile, "\r\nControl RTP Sessions: " . "\r\n" . $controlSessionsS );

					// users - RTMP clients
					$userdata = sanitize_textarea_field( stripslashes( $_POST['users'] ) );

					if ( version_compare( phpversion(), '7.0', '<' ) ) {
						$users = unserialize( $userdata );  // request is from trusted server
					} else {
						$users = unserialize( $userdata, array() );
					}

					$rtmp_test = 0;

					if ( is_array( $users ) ) {
						foreach ( $users as $user ) {

							// $rooms = explode(',',$user['rooms']); $r = $rooms[0];
							$r = $user['rooms'];
							$s = $user['session'];
							$u = $user['username'];

							$ztime      = time();
							$disconnect = '';

							if ( $ban = self::containsAny( $s, $options['bannedNames'] ) ) {
								$disconnect = "Name banned ($s,$ban)!";
							}


							$isFfmpeg = 0;
							if ( in_array( substr( $user['session'], 0, 11 ), array( 'ffmpegSnap_', 'ffmpegInfo_', 'ffmpegView_', 'ffmpegSave_', 'ffmpegPush_') ) ) {
								$isFfmpeg = 1;
							}

							// kill snap/info sessions //+ , 'ffmpegView_'
							if ( in_array( substr( $user['session'], 0, 11 ), array( 'ffmpegSnap_', 'ffmpegInfo_') ) ) {
								if ( $options['ffmpegTimeout'] ) {
									if ( $user['runSeconds'] ) {
										if ( $user['runSeconds'] > $options['ffmpegTimeout'] ) {
																			$disconnect = 'FFMPEG timeout.';
										}
									}
								}
							}


							if ( ! $isFfmpeg )  //FFmpeg = system sessions, not user sessions
							{

							//broadcaster
							if ( $user['role'] == '1' ) {

								// an user is connected on rtmp: works
								if ( ! $rtmp_test ) {
									self::requirementMet( 'rtmp_test' );
									$rtmp_test = 1;
								}

								if ( ! $r ) {
									$r = $s; // use session as room if missing in older rtmp side
								}

								$userID = 0;
								$postID = 0;

								$postRow = $wpdb->get_row( "SELECT ID, post_author FROM $wpdb->posts WHERE post_title = '" . $r . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );
								if ( $postRow )
								{
									$userID = intval($postRow->post_author);
									$postID = intval($postRow->ID);
								}

								// sessionUpdate($username='', $room='', $broadcaster=0, $type=1, $strict=1, $updated=1);
								$session = self::sessionUpdate( $s, $r, 1, 2, 0, 0, $userID, $postID, $options); // not strict in case this is existing flash user

								/*
								//user online
								$table_sessions = $wpdb->prefix . "vw_sessions";
								$sqlS = "SELECT * FROM $table_sessions WHERE session='$s' AND status='1' ORDER BY type DESC, edate DESC LIMIT 0,1";
								$session = $wpdb->get_row($sqlS);

								if (!$session) //insert as external type=2
								{
								$sql="INSERT INTO `$table_sessions` ( `session`, `username`, `room`, `message`, `sdate`, `edate`, `status`, `type`) VALUES ('$s', '$u', '$r', '$m', $ztime, $ztime, 1, 2)";
								$wpdb->query($sql);
								$session = $wpdb->get_row($sqlS);
								}
								//

								//update session
								$sql="UPDATE `$table_sessions` set edate=$ztime where id='".$session->id."'";
								$wpdb->query($sql);
								*/

								if ( $session->type >= 2 ) {
									// generate external snapshot for external broadcaster
									self::rtmpSnapshot( $session );

									// update broadcaster session meta
									$table_sessions = $wpdb->prefix . 'vw_sessions';

									if ( $session->meta ) {
										$userMeta = unserialize( $session->meta );
									} else $userMeta = [];

									if ( ! is_array( $userMeta ) ) {
										$userMeta = array();
									}

									$ztime = time();
									$userMeta['external']       = true;
									$userMeta['externalUpdate'] = $ztime;
									$userMetaS                  = serialize( $userMeta );

									$sql = "UPDATE `$table_sessions` set meta='$userMetaS' WHERE id ='" . $session->id . "'";
									$wpdb->query( $sql );

									//update channel
									$sqlC    = "SELECT * FROM $table_channels WHERE name='" . $session->room . "' LIMIT 0,1";
									$channel = $wpdb->get_row( $sqlC );

									if ( $ban = self::containsAny( $channel->name, $options['bannedNames'] ) ) {
										$disconnect = "Room banned ($ban)!";
									}

									// calculate time in ms based on previous request
									$lastTime    = $session->edate * 1000;
									$currentTime = $ztime * 1000;

									// update time
									$expTime = $options['onlineExpiration1'] + 60;
									$dS      = floor( ( $currentTime - $lastTime ) / 1000 );
									// if ($dS > $expTime || $dS<0) $disconnect = "Web server out of sync for rtmp broadcaster ($dS > $expTime) !"; //Updates should be faster; fraud attempt?

									$channel->btime += $dS;

									// update room
									$sql = "UPDATE `$table_channels` set edate=$ztime, btime = " . $channel->btime . " where id = '" . $channel->id . "'";
									$wpdb->query( $sql );

									// update post
									$postID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_title = '" . $session->room . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );

									// detect transcoding to avoid altering source info
									$transcoding   = 0;
									$stream_webrtc = $session->room . '_webrtc';
									$stream_hls    = 'i_' . $session->room;
									if ( $s == $stream_hls || $s == $stream_webrtc ) {
										$transcoding = 1;
									}

									if ( $postID && ! $transcoding ) {
										update_post_meta( $postID, 'edate', $ztime );
										update_post_meta( $postID, 'btime', $channel->btime );

										update_post_meta( $postID, 'stream-protocol', 'rtmp' );
										update_post_meta( $postID, 'stream-type', 'external' );
										update_post_meta( $postID, 'stream-updated', $ztime );

										self::updateViewers( $postID, $session->room, $options );
									}

									// transcode stream (from RTMP)
									if ( ! $disconnect ) {
										if ( $options['transcodingAuto'] >= 2 ) {
											self::transcodeStream( $session->room );
										}
										self::streamRecord( $session, $session->room, 'rtmp', $postID, $options );

									}
								}

								// room usage
								// options in minutes
								// mysql in s
								// flash in ms (minimise latency errors)

								if ( $channel->type >= 2 ) {
									$poptions = self::channelOptions( $channel->type, $options );

									$maximumBroadcastTime = 60 * $poptions['pBroadcastTime'];
									$maximumWatchTime     = 60 * $poptions['pWatchTime'];
								} else {
									$maximumBroadcastTime = 60 * $options['broadcastTime'];
									$maximumWatchTime     = 60 * $options['watchTime'];
								}

								$maximumSessionTime = $maximumBroadcastTime; // broadcaster

								$timeUsed = $channel->btime * 1000;

								if ( $maximumBroadcastTime && $maximumBroadcastTime < $channel->btime ) {
									$disconnect = 'Allocated broadcasting time ended!';
								}
								if ( $maximumWatchTime && $maximumWatchTime < $channel->wtime ) {
									$disconnect = 'Allocated watch time ended!';
								}

								$maximumSessionTime *= 1000;

							} else // subscriber viewer
								{

								// sessionUpdate($username='', $room='', $broadcaster=0, $type=1,  $strict=1, $updated=1);
								$session = self::sessionUpdate( $s, $r, 0, 2, 0, 0 ); // not strict in case this is existing flash user

								/*
								$table_sessions = $wpdb->prefix . "vw_lwsessions";

								//update viewer online
								$sqlS = "SELECT * FROM $table_sessions WHERE session='$s' AND status='1' ORDER BY type DESC, edate DESC LIMIT 0,1";

								$session = $wpdb->get_row($sqlS);
								if (!$session) //insert external viewer type=2
								{
								$sql="INSERT INTO `$table_sessions` ( `session`, `username`, `room`, `message`, `sdate`, `edate`, `status`, `type`) VALUES ('$s', '$u', '$r', '', $ztime, $ztime, 1, 2)";
								$wpdb->query($sql);
								$session = $wpdb->get_row($sqlS);
								};

								$sql="UPDATE `$table_sessions` set edate=$ztime where id='".$session->id."'";
								$wpdb->query($sql);
								*/

								if ( $session->type >= '2' ) {

									$sqlC    = "SELECT * FROM $table_channels WHERE name='" . $session->room . "' LIMIT 0,1";
									$channel = $wpdb->get_row( $sqlC );

									// calculate time in ms based on previous request
									$lastTime    = $session->edate * 1000;
									$currentTime = $ztime * 1000;

									// update room time
									$expTime = $options['onlineExpiration0'] + 30;

									$dS = floor( ( $currentTime - $lastTime ) / 1000 );
									if ( $dS > $expTime || $dS < 0 ) {
										$disconnect = "Web server out of sync ($dS > $expTime)!"; // Updates should be faster than 3 minutes; fraud attempt?
									}

									$channel->wtime += $dS;

									// update
									$sql = "UPDATE `$table_channels` set wtime = " . $channel->wtime . " where id = '" . $channel->id . "'";
									$wpdb->query( $sql );

									// update post
									$postID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_title = '" . $r . "' and post_type='channel' LIMIT 0,1" );
									if ( $postID ) {
										update_post_meta( $postID, 'wtime', $channel->wtime );
									}

									// update user watch time, disconnect if exceeded limit
									$user = get_user_by( 'login', $u );
									if ( $user ) {
										if ( self::updateUserWatchtime( $user, $dS, $options ) ) {
											$disconnect = urlencode( 'User watch time limit exceeded!' );
										}
									}
								}
								// room usage
								// options in minutes
								// mysql in s
								// flash in ms (minimise latency errors)

								if ( $channel->type >= 2 ) {
									$poptions = self::channelOptions( $channel->type, $options );

									$maximumBroadcastTime = 60 * $poptions['pBroadcastTime'];
									$maximumWatchTime     = 60 * $poptions['pWatchTime'];
								} else {
									$maximumBroadcastTime = 60 * $options['broadcastTime'];
									$maximumWatchTime     = 60 * $options['watchTime'];
								}

								$maximumSessionTime = $maximumWatchTime;

								$timeUsed = $channel->wtime * 1000;

								if ( $maximumBroadcastTime && $maximumBroadcastTime < $channel->btime ) {
									$disconnect = 'Allocated broadcasting time ended!';
								}
								if ( $maximumWatchTime && $maximumWatchTime < $channel->wtime ) {
									$disconnect = 'Allocated watch time ended!';
								}

								$maximumSessionTime *= 1000;

							}
						}//end !isFFmpeg


							$controlUser['disconnect'] = $disconnect;

							$controlUser['session']  = $s;
							$controlUser['dS']       = intval( $dS );
							$controlUser['type']     = $session->type;
							$controlUser['room']     = $session->room;
							$controlUser['username'] = $session->username;

							$controlUsers[ $user['session'] ] = $controlUser;

						}
					}



					$controlUsersS = serialize( $controlUsers );

					// fputs($dfile,"\r\n rtpsessiondata: " . $rtpsessiondata );
					// fputs($dfile,"\r\n rtpsessions rebuild 3: " . serialize(unserialize($rtpsessiondata, array())) );

					// fputs($dfile,"\r\n rtpsessions: " . serialize($rtpsessions) );

					fputs( $dfile, "\r\nControl RTMP Users: " . "\r\n" . $controlUsersS );
					fclose( $dfile );

					$appStats = sanitize_textarea_field( stripslashes( $_POST['aS'] ) );
					file_put_contents( $options['uploadsPath'] . '/sessionsApp', $appStats );

					echo 'VideoWhisper=1&usersCount=' . count( $users ) . wp_kses_post( "&controlUsers=$controlUsersS&controlSessions=$controlSessionsS" );
					// rtmp_status end
					break;

				// ! rtmp_logout
				case 'rtmp_logout':
					// rtmp server notifies client disconnect here
					$session = sanitize_text_field( $_GET['s'] );
					if ( ! $session ) {
						exit;
					}

					self::log( 'vwls_calls/rtmp_logout: $_GET=' . json_encode( $_GET ), 4, $options );

					$dir     = $options['uploadsPath'];

					echo 'logout=';
					$filename1 = $dir . "/_sessions/$session";

					if ( file_exists( $filename1 ) ) {
						echo unlink( $filename1 ); // remove session file
					}
					?>

					<?php
					break;
				// ! rtmp_login
				case 'rtmp_login':
					// when external app connects to streaming server, it will call this to confirm and then accept/reject
					// rtmp server should check login like rtmp_login.php?s=$session&p[]=$username&p[]=$room&p[]=$key&p[]=$broadcaster&p[]=$broadcasterID&p[]=$IP
					// p[] = params sent with rtmp address (key, channel)

					$session = sanitize_text_field( $_GET['s'] );
					if ( ! $session ) {
						echo 'missingSession';
						exit;
					}

					self::log( 'vwls_calls/rtmp_login: $_GET=' . json_encode( $_GET ), 4, $options);

					$p = isset( $_GET['p'] ) ? (array) $_GET['p'] : array(); //array elements in use sanitized individually based on type

					if ( count( $p ) ) {
						$username      = sanitize_text_field( $p[0] ); // or sessionID
						$room          = $channel = sanitize_text_field( $p[1] );
						$key           = sanitize_text_field( $p[2] );
						$broadcaster   = ( $p[3] === 'true' || $p[3] === '1' );
						$broadcasterID = intval( $p[4] );
					} else
					{
						echo 'missingParameterArray';
						exit;
					}

					$ip = '';
					if ( count( $p ) >= 5 ) {
						$ip = sanitize_text_field( $p[5] ); // ip detected from streaming server
					}

					$postID = 0;
					$ztime  = time();

					global $wpdb;
					$wpdb->flush();
					$postID = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name( $channel ) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );

					// verify owner
					$invalid = 0;

					if ( $broadcaster ) {
						$post_author_id = get_post_field( 'post_author', $postID );
						if ( $broadcasterID != $post_author_id ) {
							$invalid = 1; // owner mismatch
						}
					}

					$verified = 0;
					$wrongKey = 0;

					// rtmp key login for external apps: only for external apps is validated based on secret key, local app sessions should be already validated
					if ( ! $invalid ) {
						if ( $broadcaster == '1' ) {
							$validKey = md5( 'vw' . $options['webKey'] . intval( $broadcasterID ) . intval( $postID ) );

							if ( $key == $validKey ) {
								$verified = 1;

								self::webSessionSave( $session, 1, 'rtmp_login_broadcaster', $ip );

								// setup/update channel in sql
								global $wpdb;
								$table_channels = $wpdb->prefix . 'vw_lsrooms';
								$wpdb->flush();

								$sql      = "SELECT * FROM $table_channels where name='$room'";
								$channelR = $wpdb->get_row( $sql );

								if ( ! $channelR ) {
									$sql = "INSERT INTO `$table_channels` ( `owner`, `name`, `sdate`, `edate`, `rdate`,`status`, `type`) VALUES ('$broadcasterID', '$room', $ztime, $ztime, $ztime, 0, 1)";
								} elseif ( $options['timeReset'] && $channelR->rdate < $ztime - $options['timeReset'] * 24 * 3600 ) { // time to reset in days
									$sql = "UPDATE `$table_channels` set edate=$ztime, type=1, rdate=$ztime, wtime=0, btime=0 where name='$room'";
								} else {
									$sql = "UPDATE `$table_channels` set edate=$ztime where name='$room'";
								}

								$wpdb->query( $sql );

								// VWliveStreaming::sessionUpdate($username, $room, 1, 2, 1);

								// detect transcoding to not alter source info
								$transcoding   = 0;
								$stream_webrtc = $room . '_webrtc';
								$stream_hls    = 'i_' . $room;
								if ( $username == $stream_hls || $username == $stream_webrtc ) {
									$transcoding = 1;
								}

								if ( $postID && ! $transcoding ) {
									update_post_meta( $postID, 'stream-protocol', 'rtmp' );
									update_post_meta( $postID, 'stream-type', 'external' );
									update_post_meta( $postID, 'stream-updated', $ztime );
								}
							} else {
												$wrongKey = 1;
							}
						} elseif ( $broadcaster == '0' ) {
												$validKeyView = md5( 'vw' . $options['webKey'] . intval( $postID ) );
							if ( $key == $validKeyView ) {
								$verified = 1;

								self::webSessionSave( $session, 0, 'rtmp_login_viewer', $ip );
								// VWliveStreaming::sessionUpdate($username, $room, 0, 2, 1);
							}
							// VWliveStreaming::webSessionSave('error-'.$session, 0, "$channel-$session-$key-$postID-$validKeyView-".sanitize_file_name($channel) );

						}
					}

					// after previously validaded session (above or by local apps login), returning result saved

					// validate web login to streaming server
					$dir       = $options['uploadsPath'];
					$filename1 = $dir . "/_sessions/$session";
					if ( file_exists( $filename1 ) ) {
						echo sanitize_text_field( implode( '', file( $filename1 ) ) );
						if ( $broadcaster ) {
							echo '&role=' . wp_kses_post( $broadcaster );
						}
					} else {
						echo sanitize_text_field( 'VideoWhisper=1&login=0&missingSession=' . sanitize_file_name( $session ) . '&invalid=' . $invalid . '&verified=' . $verified . '&wrongKey=' . $wrongKey . '&k=' . sanitize_text_field( $key ) . '&p=' . count( $p ) );
					}

					// also update RTMP server IP in settings after authentication
					if ( $verified ) {

						if ( in_array( $options['webStatus'], array( 'auto', 'enabled' ) ) ) {
							$ip = self::get_ip_address();

							if ( ! strstr( $options['rtmp_restrict_ip'], $ip ) ) {
								$options['rtmp_restrict_ip'] .= ( $options['rtmp_restrict_ip'] ? ',' : '' ) . $ip;
								$updateOptions                = 1;
								echo '&rtmp_restrict_ip=' . esc_attr( $options['rtmp_restrict_ip'] );
							}
						}

						// also enable webStatus if on auto (now secure with IP restriction enabled)
						if ( $options['webStatus'] == 'auto' ) {
							$options['webStatus'] = 'enabled';
							$updateOptions        = 1;
							echo sanitize_text_field( '&webStatus=' . $options['webStatus'] );
						}

						if ( $updateOptions ) {
							update_option( 'VWliveStreamingOptions', $options );
						}
					}

					?>
					<?php
					break;

			} //end case
			die();
		}
	}

}

// instantiate
if ( class_exists( 'VWliveStreaming' ) ) {
	$liveStreaming = new VWliveStreaming();
}

// Actions and Filters
if ( isset( $liveStreaming ) ) {

	register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
	register_activation_hook( __FILE__, array( &$liveStreaming, 'install' ) );

	add_action( 'init', array( &$liveStreaming, 'init' ) );
	add_action( 'parse_request', array( &$liveStreaming, 'parse_request' ) );

	add_action( 'plugins_loaded', array( &$liveStreaming, 'plugins_loaded' ) );
	add_action( 'admin_menu', array( &$liveStreaming, 'admin_menu' ) );

	add_action( 'admin_bar_menu', array( &$liveStreaming, 'admin_bar_menu' ), 100 );

	add_action( 'admin_head', array( &$liveStreaming, 'admin_head' ) );
	add_action( 'admin_init', array( &$liveStreaming, 'admin_init' ) );

	add_action( 'login_enqueue_scripts', array( 'VWliveStreaming', 'login_enqueue_scripts' ) );
	add_filter( 'login_headerurl', array( 'VWliveStreaming', 'login_headerurl' ) );

	// cron
	add_filter( 'cron_schedules', array( &$liveStreaming, 'cron_schedules' ) );
	add_action( 'cron_10min_event', array( &$liveStreaming, 'cron_10min_event' ) );

	/* Only load code that needs BuddyPress to run once BP is loaded and initialized. */
	function liveStreamingBP_init() {
		if ( class_exists( 'BP_Group_Extension' ) ) {
			require dirname( __FILE__ ) . '/bp.php';
		}
	}

	add_action( 'bp_init', 'liveStreamingBP_init' );

	add_filter( 'single_template', array( &$liveStreaming, 'single_template' ) );

}
?>
