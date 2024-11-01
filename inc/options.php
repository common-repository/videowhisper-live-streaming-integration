<?php

namespace VideoWhisper\LiveStreaming;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

trait Options {

//admin menus
static function admin_bar_menu( $wp_admin_bar ) {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$options = get_option( 'VWliveStreamingOptions' );

	if ( current_user_can( 'editor' ) || current_user_can( 'administrator' ) ) {

		// find VideoWhisper menu
		$nodes = $wp_admin_bar->get_nodes();
		if ( ! $nodes ) {
			$nodes = array();
		}
		$found = 0;
		foreach ( $nodes as $node ) {
			if ( $node->title == 'VideoWhisper' ) {
				$found = 1;
			}
		}

		if ( ! $found ) {
			$wp_admin_bar->add_node(
				array(
					'id'    => 'videowhisper',
					'title' => 'VideoWhisper',
					'href'  => admin_url( 'plugin-install.php?s=videowhisper&tab=search&type=term' ),
				)
			);

			// more VideoWhisper menus

			$wp_admin_bar->add_node(
				array(
					'parent' => 'videowhisper',
					'id'     => 'videowhisper-add',
					'title'  => __( 'Add Plugins', 'paid-membership' ),
					'href'   => admin_url( 'plugin-install.php?s=videowhisper&tab=search&type=term' ),
				)
			);

			$wp_admin_bar->add_node(
				array(
					'parent' => 'videowhisper',
					'id'     => 'videowhisper-contact',
					'title'  => __( 'Contact Support', 'paid-membership' ),
					'href'   => 'https://consult.videowhisper.com/?department=Sales&topic=WordPress+Plugins+' . urlencode( sanitize_text_field( $_SERVER['HTTP_HOST'] ) ),
				)
			);
		}

		$menu_id = 'videowhisper-livestreaming';

		$wp_admin_bar->add_node(
			array(
				'parent' => 'videowhisper',
				'id'     => $menu_id,
				'title'  => '📡 ' . 'BroadcastLiveVideo',
				'href'   => admin_url( 'admin.php?page=live-streaming' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'parent' => $menu_id,
				'id'     => $menu_id . '-live',
				'title'  => __( 'Live & Ban', 'live-streaming' ),
				'href'   => admin_url( 'admin.php?page=live-streaming-live' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'parent' => $menu_id,
				'id'     => $menu_id . '-posts',
				'title'  => __( 'Channel Posts', 'live-streaming' ),
				'href'   => admin_url( 'edit.php?post_type=' . ( $options['custom_post'] ?? 'channel' ) ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'parent' => $menu_id,
				'id'     => $menu_id . '-statistics',
				'title'  => __( 'Statistics', 'live-streaming' ),
				'href'   => admin_url( 'admin.php?page=live-streaming-stats' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'parent' => $menu_id,
				'id'     => $menu_id . '-settings',
				'title'  => __( 'Settings', 'live-streaming' ),
				'href'   => admin_url( 'admin.php?page=live-streaming' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'parent' => $menu_id,
				'id'     => $menu_id . '-docs',
				'title'  => __( 'Documentation', 'live-streaming' ),
				'href'   => admin_url( 'admin.php?page=live-streaming-docs' ),
			)
		);


			$wp_admin_bar->add_node(
				array(
					'parent' => $menu_id,
					'id'     => $menu_id . '-turnkey',
					'title'  => __( 'Turnkey Plans', 'live-streaming' ),
					'href'   => 'https://broadcastlivevideo.com/order/',
				)
			);
	}

	$user_id      = get_current_user_id();
	$current_user = wp_get_current_user();

	if ( $vwls_page_manage = get_option( 'vwls_page_manage' ) ) {
		if ( get_post_status( $vwls_page_manage ) ) { // exists
			if ( $options['canBroadcast'] == 'members' || self::any_in_array( array( $options['broadcastList'], 'administrator', 'super admin' ), $current_user->roles ) ) {
				$wp_admin_bar->add_node(
					array(
						'parent' => 'my-account',
						'id'     => 'vwls_page_manage',
						'title'  => '📡 ' . __( 'Broadcast Live', 'live-streaming' ),
						'href'   => get_permalink( $vwls_page_manage ),
					)
				);
			}
		}
	}

	if ( $vwls_page_channels = get_option( 'vwls_page_channels' ) ) {
		if ( get_post_status( $vwls_page_channels ) ) { // exists
			if ( $options['canWatch'] == 'members' || $options['canWatch'] == 'all' || self::any_in_array( array( $options['watchList'], 'administrator', 'super admin' ), $current_user->roles ) ) {
				$wp_admin_bar->add_node(
					array(
						'parent' => 'my-account',
						'id'     => 'vwls_page_channels',
						'title'  => '📺 ' . __( 'Browse Channels', 'live-streaming' ),
						'href'   => get_permalink( $vwls_page_channels ),
					)
				);
			}
		}
	}

					// broadcast channels
					$args = array(
						'author'         => $user_id,
						'orderby'        => 'post_date',
						'order'          => 'DESC',
						'post_type'      => $options['custom_post'] ?? 'channel', 
						'posts_per_page' => 20,
						'offset'         => 0,
					);

					$channels = get_posts( $args );

					if ( ! count( $channels ) ) {
						return;
					}

					foreach ( $channels as $channel ) {
						$args = array(
							'parent' => 'my-account',
							'id'     => 'videowhisper_' . $channel->post_name,
							'title'  => '📡 ' . __( 'Broadcast', 'live-streaming' ) . ' ' . $channel->post_title,
							'href'   => add_query_arg( array( 'broadcast' => '' ), get_permalink( $channel->ID ) ),
						);
						$wp_admin_bar->add_node( $args );
					}

}

static function admin_menu() {

	add_menu_page( 'Live Streaming', 'Live Streaming', 'manage_options', 'live-streaming', array( 'VWliveStreaming', 'settingsPage' ), 'dashicons-video-alt', 82 );
	add_submenu_page( 'live-streaming', 'Live Streaming', 'Settings', 'manage_options', 'live-streaming', array( 'VWliveStreaming', 'settingsPage' ) );
	add_submenu_page( 'live-streaming', 'Live Streaming', 'Statistics', 'manage_options', 'live-streaming-stats', array( 'VWliveStreaming', 'adminStats' ) );
	add_submenu_page( 'live-streaming', 'Live Streaming', 'Live & Ban', 'manage_options', 'live-streaming-live', array( 'VWliveStreaming', 'adminLive' ) );
	add_submenu_page( 'live-streaming', 'Live Streaming', 'Documentation', 'manage_options', 'live-streaming-docs', array( 'VWliveStreaming', 'adminDocs' ) );

	// hide add submenu
	global $submenu;
	unset( $submenu['edit.php?post_type=channel'][10] );
}

	// define and edit settings
	static function getOptions() {
		$options = get_option( 'VWliveStreamingOptions' );
		if ( ! $options ) {
			$options = self::adminOptionsDefault();
		}

		return $options;
	}

		// ! Settings

	static function adminOptionsDefault() {
		 $root_url  = get_bloginfo( 'url' ) . '/';
		$upload_dir = wp_upload_dir();

		return array(

			'rtmpServer' => 'videowhisper', //videowhisper/wowza 
			'videowhisperRTMP' =>'',
			'videowhisperHLS' =>'',
			'broadcastPin' => '',
			'playbackPin' => '',
			'videowhisperStream' => '0', //stream validation

			'modeVersion' => '',
			'rtpSnapshots' => 0, //no longer needed as uploaded by h5v app
			'saveSnapshots' => 1,
			'appLogo'                        => dirname( plugin_dir_url( __FILE__ ) ) . '/images/logo.png',

			'logLevel'                        => 0,
			'logDays'                         => 7,
			'html5videochat' => 1,
			'roleRestricted' => 'performer',
			'roleModerators' => 'editor, moderator',
			'corsACLO'                        => '',
			'whitelabel'                      => 0,

			'appOptionsReset'                 => 0,
			'appOptions'                      => 1,

			'appComplexity'                   => 1,
			'appSiteMenu'                     => -1,
			'templateTypes'                   => 'page, post, channel, webcam, conference, presentation, videochat, video, picture, download',
			'timeIntervalVisitor' => 15000,
			'webStatusInterval'               => '60', // seconds between status calls
			
			'lovense' => 0,
			'lovensePlatform' => '',
			'lovenseTipParams' => 4,
			'debugMode' => 0,

			'languageDefault' => 'en-us',
			'multilanguage' => 1,
			'deepLkey' => '',
			'translations' => 'all',

			'rolesDonate'       => 'administrator, editor, author, contributor, subscriber, performer, creator, studio, client, fan',
			'goals'                           => 1,
			'goalsConfig'                     => ';Define default goals that can be achieved with donations/gifts/crowdfunding. Completing a goal moves room to next one if available or repeats.

[1]
name=Break the Ice
description="Break the ice with a small gift."
amount=5 ; required to complete goal
current=3 ; starting amount on this goal (fake gifts)
cumulated=3 ; total amount on all goals (fake gifts)
reset=1 ; days to reset

[2]
name=Getting Started
description=Get things started.
amount=10
reset=0 ; does not reset

[3]
name=Heat It Up
description=Heat things up.
amount=50

[4]
name=Independent
description=Independent goals can also receive donations anytime from Goals panel.
independent=true
current=5
amount=50

[5]
name=Bonus
description=Bonus. Final goal repeats if completed.
independent=true
amount=100
',
			'goalsDefault'                    => unserialize( 'a:5:{i:1;a:6:{s:4:"name";s:13:"Break the Ice";s:11:"description";s:34:"\Break the ice with a small gift.\";s:6:"amount";s:1:"5";s:7:"current";s:1:"3";s:9:"cumulated";s:1:"3";s:5:"reset";s:1:"1";}i:2;a:4:{s:4:"name";s:15:"Getting Started";s:11:"description";s:19:"Get things started.";s:6:"amount";s:2:"10";s:5:"reset";s:1:"0";}i:3;a:3:{s:4:"name";s:10:"Heat It Up";s:11:"description";s:15:"Heat things up.";s:6:"amount";s:2:"50";}i:4;a:5:{s:4:"name";s:11:"Independent";s:11:"description";s:70:"Independent goals can also receive donations anytime from Goals panel.";s:11:"independent";s:1:"1";s:7:"current";s:1:"5";s:6:"amount";s:2:"50";}i:5;a:4:{s:4:"name";s:5:"Bonus";s:11:"description";s:39:"Bonus. Final goal repeats if completed.";s:11:"independent";s:1:"1";s:6:"amount";s:3:"100";}}' ),

			'appSetup'                        => unserialize( 'a:3:{s:6:"Config";a:22:{s:8:"darkMode";s:0:"";s:7:"pipMode";s:0:"";s:7:"minMode";s:0:"";s:7:"tabMenu";s:4:"icon";s:19:"cameraAutoBroadcast";s:1:"1";s:14:"cameraControls";s:1:"1";s:16:"snapshotInterval";s:3:"240";s:15:"snapshotDisable";s:0:"";s:13:"videoAutoPlay";s:0:"";s:16:"resolutionHeight";s:3:"360";s:7:"bitrate";s:3:"500";s:9:"frameRate";s:2:"15";s:12:"audioBitrate";s:2:"32";s:19:"maxResolutionHeight";s:4:"1080";s:10:"maxBitrate";s:4:"3500";s:12:"timeInterval";s:4:"5000";s:15:"recorderMaxTime";s:3:"300";s:15:"recorderDisable";s:0:"";s:11:"goals_label";s:5:"Goals";s:10:"goals_icon";s:4:"gift";s:12:"recordAction";s:1:"1";s:14:"longTextLength";s:2:"20";}s:4:"Room";a:8:{s:12:"room_private";s:0:"";s:13:"external_rtmp";s:1:"1";s:10:"room_audio";s:0:"";s:9:"room_text";s:0:"";s:13:"stream_record";s:0:"";s:11:"goals_panel";s:1:"1";s:10:"goals_sort";s:0:"";s:5:"gifts";s:1:"1";}s:4:"User";a:7:{s:12:"h5v_language";s:5:"en-us";s:8:"h5v_flag";s:2:"us";s:7:"h5v_sfx";s:0:"";s:8:"h5v_dark";s:0:"";s:7:"h5v_pip";s:0:"";s:7:"h5v_min";s:0:"";s:9:"h5v_audio";s:0:"";}}' ),

			'appSetupConfig'                  => '
; This configures HTML5 Videochat application and other apps that use same API.

[Config]						; Application settings
darkMode = false 			 	; true/false : start app in dark mode
pipMode = false 			 	; true/false : picture in picture with camera over video
minMode = false 			 	; true/false : minimalist mode with less buttons and interface elements
tabMenu = icon 				    ; icon/text/full : menu type for tabs (in advanced/collaboration mode), use icon to fit tabs for setups with many features
cameraAutoBroadcast = true		; true/false : start broadcast automatically for broadcasgter
cameraControls = true 			; true/false : broadcast control panel
snapshotInterval = 240			; camera snapshot interval in seconds, to upload camera snapshot, min 10s (lower defaults to 180)
snapshotDisable = false			; disable uploading camera snapshots by videochat applicaiton
videoAutoPlay = false 			; true/false : try to play video without broadcaster notification
resolutionHeight = 360			; streaming resolution, maximum 360 in free mode
bitrate = 500					; streaming bitrate in kbps, maximum 750kbps in free mode
frameRate = 15					; streaming frame rate in fps, maximum 15fps in free mode
audioBitrate = 32				; streaming audio bitrate in kbps, maximum 32kbps in free mode
maxResolutionHeight = 1080 		; maximum selectable resolution height, maximum 480p in free mode
maxBitrate = 3500				; maximum selectable streaming bitrate in kbps, maximum 750kbps in free mode, also limited by hosting
timeInterval = 5000				; chat and interaction update in milliseconds, if no action taken by user, min 2000ms
recorderMaxTime = 300			; maximum recording time in seconds, limited in free mode
recorderDisable= false			; disable inserting recordings in text chat
goals_label = Goals 			; Goals panel label
goals_icon = gift 				; https://semantic-ui.com/elements/icon.html
recordAction = true				; shows record button in actions bar for performer to quickly toggle recording
longTextLength = 20				; number of characters: show a big textarea dialog for long text

[Room]						; Defaults for room options, editable by room owner in Options tab
room_private = false		; true/false : Hide room from public listings. Can be accessed by room link.
external_rtmp = true		; Enabled Broadcast tab with settings to configure external RTMP encoder, for perfomers
room_audio = false      	; true/false : Audio only mode. Only microphone, no webcam video.
room_text = false      		; true/false : Text only mode. No microphone, no webcam.
stream_record = false			; Record performer stream. Requires FFmpeg with involved codecs.
goals_panel = true			; Panel with all Goals. Users can donate to Independent goals anytime.
goals_sort = false			; Display goals in contribution order.
gifts = true				; Enable Gifts button in Actions bar. Applies to current room goal if enabled. Disable to hide current room goal from text chat.

[User]						; Defaults for user preferences, editable by user in Options tab
h5v_language = en-us		; default language (for DeepL API translation)
h5v_flag = us				; flag associated to language
h5v_sfx = false     	 	; true/false : User sound effects preference
h5v_dark = false     	 	; true/false : User dark mode preference
h5v_pip = false     	 	; true/false : User picture in picture preference
h5v_min = false     	 	; true/false : User minimalist mode preference
h5v_audio = false   	   	; true/false : User audio only mode (no webcam)
				',
				'appCSS'                          => '

				/* elementor-icons-ekiticons-css  conflict */
				i.icon {
				font-family: Icons  !important;
				}
				
				.ui.button
				{
				width: auto !important;
				height: auto !important;
				}
				
				.ui .item
				{
				 margin-top: 0px !important;
				}
				
				.ui.modal>.content
				{
				margin: 0px !important;
				}
				.ui.header .content
				{
				background-color: inherit !important;
				}
				
				.site-inner
				{
				max-width: 100%;
				}
				
				.panel
				{
				padding: 0px !important;
				margin: 0px !important;
				}
							',
							'appRoles'                        => unserialize( 'a:3:{s:27:"conferenceParticipantCamera";a:3:{s:5:"roles";s:30:"administrator,performer,client";s:5:"value";s:1:"1";s:5:"other";s:0:"";}s:8:"banUsers";a:3:{s:5:"roles";s:13:"administrator";s:5:"value";s:1:"1";s:5:"other";s:0:"";}s:11:"filesUpload";a:3:{s:5:"roles";s:30:"administrator,performer,client";s:5:"value";s:1:"1";s:5:"other";s:0:"";}}' ),
							'appRolesConfig'                  => '
				; This configures features per role			
				[banUsers] ; enable other participants to kick or ban
				roles = administrator, moderator, editor
				value = true
				other = false
							',							

			'watchWidth'	=>'800px',
			'watchHeight'	=>'600px',
			'videoWidth'	=>'100%',
			'videoHeight'	=>'640px',
					
			'enable_exec'               => 0, // disabled by default for security confirmation
			
			'recordingFFmpeg'           => 0,
			'processTimeout'            => '90',

			'restreamClean'             => 1,
			'subcategory'               => 'all',

			'interfaceClass'            => '',
			'wallet'                    => 'MyCred',
			'walletMulti'               => '2',

			'balancePage'               => '',
			'rateStarReview'            => '1',

			'viewerInterface'           => 'chat', // video/chat
			'htmlchatVisitorWrite'      => '0',

			'userName'                  => 'user_nicename',
			'userPicture'               => 'avatar',
			'profilePrefix'             => $root_url . 'author/',
			'profilePrefixChannel'      => $root_url . 'channel/',
			'loginLogo'                 => dirname( plugin_dir_url( __FILE__ ) ) . '/login-logo.png',

			'postChannels'              => '1',
			'userChannels'              => '0',
			'anyChannels'               => '0',

			'custom_post'               => 'channel',
			'custom_post_video'         => 'video',

			'postTemplate'              => '+plugin',
			'channelUrl'                => 'post',

			'disablePage'               => '0',
			'disablePageC'              => '0',
			'thumbWidth'                => '240',
			'thumbHeight'               => '180',
			'perPage'                   => '6',

			'postName'                  => 'custom',

			'rtmp_server'               => 'rtmp://localhost/videowhisper',

			'rtmp_restrict_ip'          => '',
			'webStatus'                 => 'auto',

			'rtmp_amf'                  => 'AMF3',
			'httpstreamer'              => 'https://[your-server]:1935/videowhisper-x/',
			'rtsp_server'               => 'rtsp://[your-server]/videowhisper-x', // access WebRTC stream with sound from here
			'rtsp_server_publish'       => 'rtsp://[user:password@][your-server]/videowhisper-x', // publish WebRTC stream here
			'ffmpegPath'                => '/usr/local/bin/ffmpeg',
			'ffmpegSnapshotTimeout'     => 'timeout -s KILL 5 ',
			'ffmpegSnapshotBackground'  => '& ',
			'ffmpegConfiguration'       => '1',
			'ffmpegTranscode'           => '-c:v copy -c:a libfdk_aac -b:a 96',

			'transcodeRTC'              => '0',
			'transcodeFromRTC'          => '0',
			'ffmpegTranscodeRTC'        => '-c:v copy -c:a libopus', // transcode for RTC like ffmpeg -re -i source -acodec opus -vcodec libx264 -vprofile baseline -f rtsp rtsp://<wowza-instance>/rtsp-to-webrtc/my-stream

			'ffmpegTimeout'             => '60',

			'streamsPath'               => '/home/account/public_html/streams',
			'ipcamera_registration'     => '0', // allows frontend registration for IP camera streams
			'transcodeReStreams'        => '0',

			'restreamPause'             => 1,
			'restreamTimeout'           => 900,
			'restreamAccessedUser'      => 1,
			'restreamAccessed'          => 0,
			'restreamActiveOwner'       => 1,
			'restreamActiveUser'        => 0,

			'webrtc'                    => '1', // enable webrtc

			'webrtcServer'                    => 'wowza', // wowza/videowhisper
			'vwsSocket'                 	  => '', // videowhisper nodejs server
			'vwsToken'                		  => '', // videowhisper nodejs server token
			'vwsAccount'                	  => '', // videowhisper nodejs server account

			'wsURLWebRTC'               => 'wss://[wowza-server-with-ssl]:[port]/webrtc-session.json', // Wowza WebRTC WebSocket URL (wss with SSL certificate)
			'applicationWebRTC'         => '[application-name]', // Wowza Application Name (configured or WebRTC usage)

			'webrtcVideoCodec'          => 'VP8',
			'webrtcAudioCodec'          => 'opus',

			'webrtcVideoBitrate'        => 1000,
			'webrtcAudioBitrate'        => 96,

			'iptv'                      => '0',
			'ipcams'                    => '0',

			'playlists'                 => '0',

			'canBroadcast'              => 'members',
			'broadcastList'             => 'Super Admin, Administrator, Editor, Author',
			'maxChannels'               => '3',

			'externalKeys'              => '1',
			'externalKeysTranscoder'    => '1',
			'transcodeExternal'         => '0',

			'rtmpStatus'                => '0',

			'canWatch'                  => 'all',
			'watchList'                 => 'Super Admin, Administrator, Editor, Author, Contributor, Subscriber',
			'onlyVideo'                 => '0',
			'noEmbeds'                  => '0',

			'userWatchLimit'            => '0',
			'userWatchInterval'         => '2592000',
			'userWatchLimitDefault'     => '108000',
			'userWatchLimits'           => '',
			'userWatchLimitsConfig'     => 'Administrator = 0
Super Admin = 0
Editor = 72000
Subscriber = 36000',
			'watchRoleParameters'       => '',
			'watchRoleParametersConfig' => '[disableChat]
Administrator = 0
Editor = 0

[disableUsers]
Administrator = 0
Editor = 0

[disableVideo]
Administrator = 0
Editor = 0

[writeText]
Administrator = 1
Editor = 1

[privateTextchat]
Administrator = 1
Editor = 1
				',

			'broadcasterRedirect'       => '0',

			'premiumList'               => 'Super Admin, Administrator, Editor, Author',
			'canWatchPremium'           => 'all',
			'watchListPremium'          => 'Super Admin, Administrator, Editor, Author, Contributor, Subscriber',

			'premiumLevelsNumber'       => '1',
			'premiumLevels'             => '',

			// 'pLogo' => '1',
			'broadcastTime'             => '600',
			'watchTime'                 => '3000',
			'pBroadcastTime'            => '0',
			'pWatchTime'                => '0',
			'timeReset'                 => '30',
			'bannedNames'               => 'bann1, bann2',

			'camResolution'             => '640x480',
			'camFPS'                    => '15',

			'camBandwidth'              => '75000',
			'camMaxBandwidth'           => '75000',
			'pCamBandwidth'             => '100000',
			'pCamMaxBandwidth'          => '125000',

			'transcoding'               => '0',
			'transcodingAuto'           => '2',
			'transcodingManual'         => '0',
			'transcodingWarning'        => '2',

			'detect_hls'                => 'ios',
			'detect_mpeg'               => 'android',

			'videoCodec'                => 'H264',
			'codecProfile'              => 'baseline',
			'codecLevel'                => '3.1',

			'soundCodec'                => 'Nellymoser',
			'soundQuality'              => '9',
			'micRate'                   => '22',

			// ! mobile settings
			'camResolutionMobile'       => '480x360',
			'camFPSMobile'              => '15',

			'camBandwidthMobile'        => '40000',

			'videoCodecMobile'          => 'H263',
			'codecProfileMobile'        => 'baseline',
			'codecLevelMobile'          => '3.1',

			'soundCodecMobile'          => 'Speex',
			'soundQualityMobile'        => '9',
			'micRateMobile'             => '22',
			// mobile:end

			'onlineExpiration0'         => '310',
			'onlineExpiration1'         => '40',
			'parameters'                => '&bufferLive=1&bufferFull=1&showCredit=1&disconnectOnTimeout=1&offlineMessage=Channel+Offline&disableVideo=0&fillWindow=0&adsTimeout=15000&externalInterval=17000&statusInterval=59000&loaderProgress=1',
			'parametersBroadcaster'     => '&bufferLive=2&bufferFull=2&showCamSettings=1&advancedCamSettings=1&configureSource=1&generateSnapshots=1&snapshotsTime=60000&room_limit=500&showTimer=1&showCredit=1&disconnectOnTimeout=1&externalInterval=11000&statusInterval=29000&loaderProgress=1&selectCam=1&selectMic=1',
			'layoutCode'                => 'id=0&label=Video&x=10&y=45&width=325&height=298&resize=true&move=true; id=1&label=Chat&x=340&y=45&width=293&height=298&resize=true&move=true; id=2&label=Users&x=638&y=45&width=172&height=298&resize=true&move=true',
			'layoutCodeBroadcaster'     => 'id=0&label=Webcam&x=10&y=40&width=242&height=235&resize=true&move=true; id=1&label=Chat&x=260&y=40&width=340&height=235&resize=true&move=true; id=2&label=Users&x=610&y=40&width=180&height=235&resize=true&move=true',
			'watchStyle'                => 'width: 100%;
height: 400px;
border: solid 3px #999;',

			'loaderImage'               => '',

			'overLink'                  => 'https://videowhisper.com',
			'adServer'                  => 'ads',
			'adsInterval'               => '20000',
			'adsCode'                   => '<B>Sample Ad</B><BR>Edit ads from plugin settings. Also edit  Ads Interval in milliseconds (0 to disable ad calls).  Also see <a href="http://www.adinchat.com" target="_blank"><U><B>AD in Chat</B></U></a> compatible ad management server for setting up ad rotation. Ads do not show on premium channels.',

			'cssCode'                   => 'title {
    font-family: Arial, Helvetica, _sans;
    font-size: 11;
    font-weight: bold;
    color: #FFFFFF;
    letter-spacing: 1;
    text-decoration: none;
}

story {
    font-family: Verdana, Arial, Helvetica, _sans;
    font-size: 14;
    font-weight: normal;
    color: #FFFFFF;
}',
			'translationCode'           => '<t text="Video is Disabled" translation="Video is Disabled"/>
<t text="Bold" translation="Bold"/>
<t text="Sound is Enabled" translation="Sound is Enabled"/>
<t text="Publish a video stream using the settings below without any spaces." translation="Publish a video stream using the settings below without any spaces."/>
<t text="Click Preview for Streaming Settings" translation="Click Preview for Streaming Settings"/>
<t text="DVD NTSC" translation="DVD NTSC"/>
<t text="DVD PAL" translation="DVD PAL"/>
<t text="Video Source" translation="Video Source"/>
<t text="Send" translation="Send"/>
<t text="Cinema" translation="Cinema"/>
<t text="Update Show Title" translation="Update Show Title"/>
<t text="Public Channel: Click to Copy" translation="Public Channel: Click to Copy"/>
<t text="Channel Link" translation="Channel Link"/>
<t text="Kick" translation="Kick"/>
<t text="Embed Channel HTML Code" translation="Embed Channel HTML Code"/>
<t text="Open In Browser" translation="Open In Browser"/>
<t text="Embed Video HTML Code" translation="Embed Video HTML Code"/>
<t text="Snapshot Image Link" translation="Snapshot Image Link"/>
<t text="SD" translation="SD"/>
<t text="External Encoder" translation="External Encoder"/>
<t text="Source" translation="Source"/>
<t text="Very Low" translation="Very Low"/>
<t text="Low" translation="Low"/>
<t text="HDTV" translation="HDTV"/>
<t text="Webcam" translation="Webcam"/>
<t text="Resolution" translation="Resolution"/>
<t text="Emoticons" translation="Emoticons"/>
<t text="HDCAM" translation="HDCAM"/>
<t text="FullHD" translation="FullHD"/>
<t text="Preview Shows as Compressed" translation="Preview Shows as Compressed"/>
<t text="Rate" translation="Rate"/>
<t text="Very Good" translation="Very Good"/>
<t text="Preview Shows as Captured" translation="Preview Shows as Captured"/>
<t text="Framerate" translation="Framerate"/>
<t text="High" translation="High"/>
<t text="Toggle Preview Compression" translation="Toggle Preview Compression"/>
<t text="Latency" translation="Latency"/>
<t text="CD" translation="CD"/>
<t text="Your connection performance:" translation="Your connection performance:"/>
<t text="Small Delay" translation="Small Delay"/>
<t text="Sound Effects" translation="Sound Effects"/>
<t text="Username" translation="Nickname"/>
<t text="Medium Delay" translation="Medium Delay"/>
<t text="Toggle Microphone" translation="Toggle Microphone"/>
<t text="Video is Enabled" translation="Video is Enabled"/>
<t text="Radio" translation="Radio"/>
<t text="Talk" translation="Talk"/>
<t text="Viewers" translation="Viewers"/>
<t text="Toggle External Encoder" translation="Toggle External Encoder"/>
<t text="Sound is Disabled" translation="Sound is Disabled"/>
<t text="Sound Fx" translation="Sound Effects"/>
<t text="Good" translation="Good"/>
<t text="Toggle Webcam" translation="Toggle Webcam"/>
<t text="Bandwidth" translation="Bandwidth"/>
<t text="Underline" translation="Underline"/>
<t text="Select Microphone Device" translation="Select Microphone Device"/>
<t text="Italic" translation="Italic"/>
<t text="Select Webcam Device" translation="Select Webcam Device"/>
<t text="Big Delay" translation="Big Delay"/>
<t text="Excellent" translation="Excellent"/>
<t text="Apply Settings" translation="Apply Settings"/>
<t text="Very High" translation="Very High"/>',

			'customCSS'                 => <<<HTMLCODE
/* Theme Fixes */
.site-inner {
max-width: 100%;
}

.ui > .item, .ui.form {
  display: block !important;
}

.ui.button
{
display: block !important;
width: auto !important;
}

/* Listings */
.videowhisperChannel
{
position: relative;
display:inline-block;

	border:1px solid #aaa;
	background-color:#777;
	padding: 0px;
	margin: 2px;

	width: 240px;
    height: 180px;
	overflow: hidden;
}

.videowhisperChannel:hover {
	border:1px solid #fff;
}

.videowhisperChannel IMG
{
padding: 0px;
margin: 0px;
border: 0px;
}

.videowhisperTitle
{
position: absolute;
top:5px;
left:5px;
font-size: 20px;
color: #FFF;
text-shadow:1px 1px 1px #333;
}

.videowhisperTime
{
position: absolute;
bottom:5px;
left:5px;
font-size: 15px;
color: #FFF;
text-shadow:1px 1px 1px #333;
}

.videowhisperChannelRating
{
position: absolute;
bottom: 5px;
right:5px;
font-size: 15px;
color: #FFF;
text-shadow:1px 1px 1px #333;
z-index: 10;
}

HTMLCODE
				,
			'uploadsPath'               => $upload_dir['basedir'] . '/vwls',

			'tokenKey'                  => 'VideoWhisper',
			'webKey'                    => 'VideoWhisper',
			'manualArchiving'           => '',

			'serverRTMFP'               => 'rtmfp://stratus.adobe.com/f1533cc06e4de4b56399b10d-1a624022ff71/',
			'p2pGroup'                  => 'VideoWhisper',
			'supportRTMP'               => '1',
			'supportP2P'                => '0',
			'alwaysRTMP'                => '1',
			'alwaysP2P'                 => '0',
			'alwaysWatch'               => '1',
			'disableBandwidthDetection' => '1',
			'mycred'                    => '1',
			'tips'                      => 1,
			'tipRatio'                  => '0.90',
			'tipOptions'                => '<tips>
<tip amount="1" label="1$ Tip" note="Like!" sound="coins1.mp3" image="gift1.png"/>
<tip amount="2" label="2$ Tip" note="Big Like!" sound="coins2.mp3" image="gift2.png"/>
<tip amount="5" label="5$ Gift" note="Great!" sound="coins2.mp3" image="gift3.png"/>
<tip amount="10" label="10$ Gift" note="Excellent!" sound="register.mp3" image="gift4.png"/>
<tip amount="20" label="20$ Gift" note="Ultimate!" sound="register.mp3" image="gift5.png"/>
</tips>',
			'tipCooldown'               => '15',

			'eula_txt'                  => 'The following Terms of Use (the "Terms") is a binding agreement between you, either an individual subscriber, customer, member, or user of at least 18 years of age or a single entity ("you", or collectively "Users") and owners of this application, service site and networks that allow for the distribution and reception of video, audio, chat and other content (the "Service").

By accessing the Service and/or by clicking "I agree", you agree to be bound by these Terms of Use. You hereby represent and warrant to us that you are at least eighteen (18) years of age or and otherwise capable of entering into and performing legal agreements, and that you agree to be bound by the following Terms and Conditions. If you use the Service on behalf of a business, you hereby represent to us that you have the authority to bind that business and your acceptance of these Terms of Use will be treated as acceptance by that business. In that event, "you" and "your" will refer to that business in these Terms of Use.

Prohibited Conduct

The Services may include interactive areas or services (" Interactive Areas ") in which you or other users may create, post or store content, messages, materials, data, information, text, music, sound, photos, video, graphics, applications, code or other items or materials on the Services ("User Content" and collectively with Broadcaster Content, " Content "). You are solely responsible for your use of such Interactive Areas and use them at your own risk. BY USING THE SERVICE, INCLUDING THE INTERACTIVE AREAS, YOU AGREE NOT TO violate any law, contract, intellectual property or other third-party right or commit a tort, and that you are solely responsible for your conduct while on the Service. You agree that you will abide by these Terms of Service and will not:

use the Service for any purposes other than to disseminate or receive original or appropriately licensed content and/or to access the Service as such services are offered by us;

rent, lease, loan, sell, resell, sublicense, distribute or otherwise transfer the licenses granted herein;

post, upload, or distribute any defamatory, libelous, or inaccurate Content;

impersonate any person or entity, falsely claim an affiliation with any person or entity, or access the Service accounts of others without permission, forge another persons digital signature, misrepresent the source, identity, or content of information transmitted via the Service, or perform any other similar fraudulent activity;

delete the copyright or other proprietary rights notices on the Service or Content;

make unsolicited offers, advertisements, proposals, or send junk mail or spam to other Users of the Service, including, without limitation, unsolicited advertising, promotional materials, or other solicitation material, bulk mailing of commercial advertising, chain mail, informational announcements, charity requests, petitions for signatures, or any of the foregoing related to promotional giveaways (such as raffles and contests), and other similar activities;

harvest or collect the email addresses or other contact information of other users from the Service for the purpose of sending spam or other commercial messages;

use the Service for any illegal purpose, or in violation of any local, state, national, or international law, including, without limitation, laws governing intellectual property and other proprietary rights, and data protection and privacy;

defame, harass, abuse, threaten or defraud Users of the Service, or collect, or attempt to collect, personal information about Users or third parties without their consent;

remove, circumvent, disable, damage or otherwise interfere with security-related features of the Service or Content, features that prevent or restrict use or copying of any content accessible through the Service, or features that enforce limitations on the use of the Service or Content;

reverse engineer, decompile, disassemble or otherwise attempt to discover the source code of the Service or any part thereof, except and only to the extent that such activity is expressly permitted by applicable law notwithstanding this limitation;

modify, adapt, translate or create derivative works based upon the Service or any part thereof, except and only to the extent that such activity is expressly permitted by applicable law notwithstanding this limitation;

intentionally interfere with or damage operation of the Service or any user enjoyment of them, by any means, including uploading or otherwise disseminating viruses, adware, spyware, worms, or other malicious code;

relay email from a third party mail servers without the permission of that third party;

use any robot, spider, scraper, crawler or other automated means to access the Service for any purpose or bypass any measures we may use to prevent or restrict access to the Service;

manipulate identifiers in order to disguise the origin of any Content transmitted through the Service;

interfere with or disrupt the Service or servers or networks connected to the Service, or disobey any requirements, procedures, policies or regulations of networks connected to the Service;use the Service in any manner that could interfere with, disrupt, negatively affect or inhibit other users from fully enjoying the Service, or that could damage, disable, overburden or impair the functioning of the Service in any manner;

use or attempt to use another user account without authorization from such user and us;

attempt to circumvent any content filtering techniques we employ, or attempt to access any service or area of the Service that you are not authorized to access; or

attempt to indicate in any manner that you have a relationship with us or that we have endorsed you or any products or services for any purpose.

Further, BY USING THE SERVICE, INCLUDING THE INTERACTIVE AREAS YOU AGREE NOT TO post, upload to, transmit, distribute, store, create or otherwise publish through the Service any of the following:

Content that would constitute, encourage or provide instructions for a criminal offense, violate the rights of any party, or that would otherwise create liability or violate any local, state, national or international law or regulation;

Content that may infringe any patent, trademark, trade secret, copyright or other intellectual or proprietary right of any party. By posting any Content, you represent and warrant that you have the lawful right to distribute and reproduce such Content;

Content that is unlawful, libelous, defamatory, obscene, pornographic, indecent, lewd, suggestive, harassing, threatening, invasive of privacy or publicity rights, abusive, inflammatory, fraudulent or otherwise objectionable;

Content that impersonates any person or entity or otherwise misrepresents your affiliation with a person or entity;

private information of any third party, including, without limitation, addresses, phone numbers, email addresses, Social Security numbers and credit card numbers;

viruses, corrupted data or other harmful, disruptive or destructive files; and

Content that, in the sole judgment of Service moderators, is objectionable or which restricts or inhibits any other person from using or enjoying the Interactive Areas or the Service, or which may expose us or our users to any harm or liability of any type.

Service takes no responsibility and assumes no liability for any Content posted, stored or uploaded by you or any third party, or for any loss or damage thereto, nor is liable for any mistakes, defamation, slander, libel, omissions, falsehoods, obscenity, pornography or profanity you may encounter. Your use of the Service is at your own risk. Enforcement of the user content or conduct rules set forth in these Terms of Service is solely at Service discretion, and failure to enforce such rules in some instances does not constitute a waiver of our right to enforce such rules in other instances. In addition, these rules do not create any private right of action on the part of any third party or any reasonable expectation that the Service will not contain any content that is prohibited by such rules. As a provider of interactive services, Service is not liable for any statements, representations or Content provided by our users in any public forum, personal home page or other Interactive Area. Service does not endorse any Content or any opinion, recommendation or advice expressed therein, and Service expressly disclaims any and all liability in connection with Content. Although Service has no obligation to screen, edit or monitor any of the Content posted in any Interactive Area, Service reserves the right, and has absolute discretion, to remove, screen or edit any Content posted or stored on the Service at any time and for any reason without notice, and you are solely responsible for creating backup copies of and replacing any Content you post or store on the Service at your sole cost and expense. Any use of the Interactive Areas or other portions of the Service in violation of the foregoing violates these Terms and may result in, among other things, termination or suspension of your rights to use the Interactive Areas and/or the Service.
',
			'crossdomain_xml'           => '<cross-domain-policy>
<allow-access-from domain="*"/>
<site-control permitted-cross-domain-policies="master-only"/>
</cross-domain-policy>',
			'videowhisper'              => 0,
		);

	}

	static function setupOptions() {
		$adminOptions = self::adminOptionsDefault();

		$features = self::roomFeatures();
		foreach ( $features as $key => $feature ) {
			if ( $feature['installed'] ) {
				$adminOptions[ $key ] = $feature['default'];
			}
		}

			$options = get_option( 'VWliveStreamingOptions' );
		if ( ! empty( $options ) ) {
			foreach ( $options as $key => $option ) {
				$adminOptions[ $key ] = $option;
			}
		}
		update_option( 'VWliveStreamingOptions', $adminOptions );

		return $adminOptions;
	}


	static function settingsPage() {
		$options        = self::setupOptions();
		$optionsDefault = self::adminOptionsDefault();

		if ( isset( $_POST ) ) {
			if ( ! empty( $_POST ) ) {

				$nonce = $_REQUEST['_wpnonce'];
				if ( ! wp_verify_nonce( $nonce, 'vwsec' ) ) {
					echo 'Invalid nonce!';
					exit;
				}

				foreach ( $options as $key => $value ) {
					if ( isset( $_POST[ $key ] ) ) {
						$options[ $key ] = trim( sanitize_textarea_field( $_POST[ $key ] ) ) ;
					}
				}

				// config parsing
				if ( isset( $_POST['appSetupConfig'] ) )
				{
					$options['appSetup'] = parse_ini_string( sanitize_textarea_field( $_POST['appSetupConfig'] ), true );
				}

				if ( isset( $_POST['appRolesConfig'] ) )
				{
					$options['appRoles'] = parse_ini_string( sanitize_textarea_field( $_POST['appRolesConfig'] ), true );
				}

				if ( isset( $_POST['userWatchLimitsConfig'] ) ) {
					$options['userWatchLimits'] = parse_ini_string( sanitize_textarea_field( $_POST['userWatchLimitsConfig'] ) );
				}

				if ( isset( $_POST['watchRoleParametersConfig'] ) ) {
					$options['watchRoleParameters'] = parse_ini_string( sanitize_textarea_field( $_POST['watchRoleParametersConfig'] ), true );
				}

						update_option( 'VWliveStreamingOptions', $options );
			}
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'setup';
		?>


<div class="wrap">
<h2>Broadcast Live Video - Live Streaming by VideoWhisper.com</h2>

<h2 class="nav-tab-wrapper">
	<a href="admin.php?page=live-streaming&tab=server" class="nav-tab <?php echo $active_tab == 'server' ? 'nav-tab-active' : ''; ?>">RTMP / HLS</a>
	<a href="admin.php?page=live-streaming&tab=webrtc" class="nav-tab <?php echo $active_tab == 'webrtc' ? 'nav-tab-active' : ''; ?>">WebRTC</a>
	<a href="admin.php?page=live-streaming&tab=app" class="nav-tab <?php echo $active_tab == 'app' ? 'nav-tab-active' : ''; ?>">HTML5 Videochat</a>

	<a href="admin.php?page=live-streaming&tab=hls" class="nav-tab <?php echo $active_tab == 'hls' ? 'nav-tab-active' : ''; ?>">FFMPEG / Transcoding</a>
		
		<a href="admin.php?page=live-streaming&tab=pages" class="nav-tab <?php echo $active_tab == 'pages' ? 'nav-tab-active' : ''; ?>">Pages</a>

	<a href="admin.php?page=live-streaming&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">Integration</a>
		<a href="admin.php?page=live-streaming&tab=appearance" class="nav-tab <?php echo $active_tab == 'appearance' ? 'nav-tab-active' : ''; ?>">Appearance</a>

	<a href="admin.php?page=live-streaming&tab=broadcaster" class="nav-tab <?php echo $active_tab == 'broadcaster' ? 'nav-tab-active' : ''; ?>">Broadcaster</a>
	<a href="admin.php?page=live-streaming&tab=premium" class="nav-tab <?php echo $active_tab == 'premium' ? 'nav-tab-active' : ''; ?>">Membership Levels</a>
	<a href="admin.php?page=live-streaming&tab=features" class="nav-tab <?php echo $active_tab == 'features' ? 'nav-tab-active' : ''; ?>">Channel Features</a>
  
	 <a href="admin.php?page=live-streaming&tab=external" class="nav-tab <?php echo $active_tab == 'external' ? 'nav-tab-active' : ''; ?>">External Encoders</a>
	 <a href="admin.php?page=live-streaming&tab=iptv" class="nav-tab <?php echo $active_tab == 'iptv' ? 'nav-tab-active' : ''; ?>">IPTV / Pull</a>  
	 <a href="admin.php?page=live-streaming&tab=stream" class="nav-tab <?php echo $active_tab == 'stream' ? 'nav-tab-active' : ''; ?>">IP Cam / Streams</a>
	<a href="admin.php?page=live-streaming&tab=playlists" class="nav-tab <?php echo $active_tab == 'playlists' ? 'nav-tab-active' : ''; ?>">Playlists Scheduler</a>
	
	<a href="admin.php?page=live-streaming&tab=watcher" class="nav-tab <?php echo $active_tab == 'watcher' ? 'nav-tab-active' : ''; ?>">Watch Players</a>
	<a href="admin.php?page=live-streaming&tab=watch-limit" class="nav-tab <?php echo $active_tab == 'watch-limit' ? 'nav-tab-active' : ''; ?>">Watch Limit</a>
	<a href="admin.php?page=live-streaming&tab=watch-params" class="nav-tab <?php echo $active_tab == 'watch-params' ? 'nav-tab-active' : ''; ?>">Watch Params</a>
	<a href="admin.php?page=live-streaming&tab=billing" class="nav-tab <?php echo $active_tab == 'billing' ? 'nav-tab-active' : ''; ?>">Billing</a>
	<a href="admin.php?page=live-streaming&tab=tips" class="nav-tab <?php echo $active_tab == 'tips' ? 'nav-tab-active' : ''; ?>">Tips</a>

	<a href="admin.php?page=live-streaming&tab=lovense" class="nav-tab <?php echo $active_tab == 'lovense' ? 'nav-tab-active' : ''; ?>">H5V Lovense</a>
	 <a href="admin.php?page=live-streaming&tab=translate" class="nav-tab <?php echo $active_tab == 'translate' ? 'nav-tab-active' : ''; ?>">Multilanguage / Translations</a>

	<a href="admin.php?page=live-streaming&tab=import" class="nav-tab <?php echo $active_tab == 'import' ? 'nav-tab-active' : ''; ?>">Import Settings</a>
	  <a href="admin.php?page=live-streaming&tab=reset" class="nav-tab <?php echo $active_tab == 'reset' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Resets', 'live-streaming' ); ?></a>
	<a href="admin.php?page=live-streaming&tab=troubleshooting" class="nav-tab <?php echo $active_tab == 'troubleshooting' ? 'nav-tab-active' : ''; ?>">Requirements & Troubleshooting</a>
 	<a href="admin.php?page=live-streaming&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>">Logs</a>

   
	<a href="admin.php?page=live-streaming&tab=support" class="nav-tab <?php echo $active_tab == 'support' ? 'nav-tab-active' : ''; ?>">Support</a>
	<a href="admin.php?page=live-streaming&tab=setup" class="nav-tab <?php echo $active_tab == 'setup' ? 'nav-tab-active' : ''; ?>">Setup</a>

</h2>

<form method="post" action="<?php echo wp_nonce_url( $_SERVER['REQUEST_URI'], 'vwsec' ); ?>">

		<?php

		switch ( $active_tab ) {

			case 'logs':
				?>
				<h3>Logs</h3>

				<h4>Log Level</h4>
				<select name="logLevel" id="logLevel">
					<option value="0" <?php if ( $options['logLevel'] == 0 ) echo 'selected'; ?>>None</option>
					<option value="1" <?php if ( $options['logLevel'] == 1 ) echo 'selected'; ?>>Errors</option>
					<option value="2" <?php if ( $options['logLevel'] == 2 ) echo 'selected'; ?>>Warnings</option>
					<option value="3" <?php if ( $options['logLevel'] == 3 ) echo 'selected'; ?>>Notice</option>
					<option value="4" <?php if ( $options['logLevel'] == 4 ) echo 'selected'; ?>>Info</option>
					<option value="5" <?php if ( $options['logLevel'] == 5 ) echo 'selected'; ?>>Debug</option>
				</select>
				<br>Each level includes previous levels.

				<h4>Log Days</h4>
				<input type="text" name="logDays" id="logDays" value="<?php echo esc_attr( $options['logDays'] ); ?>" size="5" />
				<br>Number of days to keep log files.
				<?php
				submit_button();
				?>
				<h4>Today's Log</h4>
				<?php
				$logFile = $options['uploadsPath'];
				if ( ! file_exists( $logFile ) )mkdir( $logFile, 0777, true );
				$logFile .= '/logs';
				if ( ! file_exists( $logFile ) )mkdir( $logFile, 0777, true );
				$logFile .= '/' . date( 'Y-m-d' ) . '.txt';

				if ( file_exists( $logFile ) )
				{
					$log = file_get_contents( $logFile );
					echo '<textarea rows="20" cols="100" readonly>' . esc_html($log) . '</textarea>';
				}
				else
				{
					echo 'No log file found.';
				}

				//read log cleanup time from a file logCleanup.txt
				$logCleanupFile = $options['uploadsPath'] . '/logCleanup.txt';
				$logCleanupTime = 0;
				if ( file_exists( $logCleanupFile ) )
				{
					$logCleanupTime = file_get_contents( $logCleanupFile );
				}	

				//cleanup logs if not done in the last hour
				if ( $logCleanupTime < time() - 60 * 60 )
				{
				//delete files in the log directory older than logDays days ago
				$files = glob( $options['uploadsPath'] . '/logs/*' );
				$count = 0;
				$countDeleted = 0;
				foreach ( $files as $file )
				{
					if ( is_file( $file ) )
					{
						$count ++; 
						if ( filemtime( $file ) < time() - 60 * 60 * 24 * $options['logDays'] )
						{
							unlink( $file );
							$countDeleted++;

						}
					}
				}
				echo '<p>Cleanup: Found ' . $count . ' log files.</p>';
				if ($countDeleted) echo '<p>Deleted ' . $countDeleted . ' log files out of ' . $count . '.</p>';

				//update log cleanup time in a file logCleanup.txt
				file_put_contents( $logCleanupFile, time() );
				}

				echo '<p>Find all logs at ' . $options['uploadsPath'] . '/logs </p>';
				break;

			case 'app':
				$options['appSetupConfig'] = stripslashes( $options['appSetupConfig'] );
				$options['appCSS']         = stripslashes( $options['appCSS'] );
				$options['appRolesConfig'] = stripslashes( $options['appRolesConfig'] ) ;
	
	?>
	<h3>Apps</h3>
	This section configures HTML5 Videochat app and external access (by external apps) using same API. Required when building external apps to work with solution.
	<br>For live streaming features, HTML5 Videochat app requires either Wowza SE as relay or P2P using VideoWhisper WebRTC signaling, configured for secure WebRTC live streaming:  <a href="admin.php?page=live-streaming&tab=webrtc">Configure HTML5 WebRTC</A>. 
	<br>This is a recent integration and some features may not work. For reporting issuse or assistance, <a href="https://consult.videowhisper.com">Consult VideoWhisper</a>.
	
	<h4>Use HTML5 Videochat Interface</h4>
	<select name="html5videochat" id="html5videochat">
	<option value="1" <?php selected( $options['html5videochat'], 1 ); ?>>Enabled</option>
	</select>
	<br>Enable using HTML5 Videochat, the latest most reliable and advanced interface for live streaming. Support both Wowza SE and P2P VideoWhisper WebRTC for live streaming. When disabled solution will use the old static live streaming interfaces, with limited capabilities and reliability. Recommended: Enabled.

	<h4>Logo URL</h4>
<input type="text" name="appLogo" id="appLogo" value="<?php echo esc_attr( trim( $options['appLogo'] ) ); ?>" size="120" />
<BR>URL to logo image to be displayed in app, floating over videos. Set blank to remove. It's a HTML element that can be styled with CSS for class videowhisperAppLogo.
<?php
if ( $options['appLogo'] )
{	
?>
<BR><img src="<?php echo esc_attr( $options['appLogo'] ); ?>" style="max-width:100px;" />
<?php
}
?>
	<h4>App Configuration</h4>
	<textarea name="appSetupConfig" id="appSetupConfig" cols="120" rows="12"><?php echo esc_textarea( $options['appSetupConfig'] ); ?></textarea>
	<BR>Application setup parameters are delivered to app when connecting to server. Config section refers to application parameters. Room section refers to default room options (configurable from app at runtime). User section refers to default room options configurable from app at runtime and setup on access.
	<br>Bitrate limitations also affect maximum resolution: app will hide resolutions if necessary bitrate is not available. In addition to limitation set in app, the <a href="admin.php?page=live-webcams&tab=webrtc">general host bitrate limitations</a> also apply.
	
	Default:<br><textarea readonly cols="120" rows="6"><?php echo esc_textarea( $optionsDefault['appSetupConfig'] ); ?></textarea>
	
	<BR>Parsed configuration (should be an array or arrays):<BR>
					<?php
	
				var_dump( $options['appSetup'] );
	?>
	<BR>Serialized:<BR>
					<?php
	
				echo esc_html( serialize( $options['appSetup'] ) );
	?>
	
	<h4>Reset Room & User Options</h4>
	<select name="appOptionsReset" id="appOptionsReset">
		<option value="0" <?php echo ! $options['appOptionsReset'] ? 'selected' : ''; ?>>No</option>
		<option value="1" <?php echo $options['appOptionsReset'] == '1' ? 'selected' : ''; ?>>Yes</option>
	</select>
	<br>Resets room options on each performer session start and user options when entering application, forcing defaults.
	Disable to allow options configured at runtime to persist.
	
	
	<h4>Show Options</h4>
	<select name="appOptions" id="appOptions">
		<option value="0" <?php echo ! $options['appOptions'] ? 'selected' : ''; ?>>No</option>
		<option value="1" <?php echo $options['appOptions'] == '1' ? 'selected' : ''; ?>>Yes</option>
	</select>
	<br>Show Options tab in Advanced interface, for broadcaster to edit owned room options and user preferences live.
	
	<h4>App Interface Complexity</h4>
	<select name="appComplexity" id="appComplexity">
		<option value="0" <?php echo ! $options['appComplexity'] ? 'selected' : ''; ?>>Simple</option>
		<option value="1" <?php echo $options['appComplexity'] == '1' ? 'selected' : ''; ?>>Advanced</option>
		<option value="2" <?php echo $options['appComplexity'] == '2' ? 'selected' : ''; ?>>Advanced for Broadcaster</option>
	</select>
	<br>Simple interface shows minimal panels (webrtc video, text chat, actions).
	<br>-Audio Only mode involves special layouts for Broadcast, Playback: Chat uses most space and audio controls for broadcast/playback minimized in a bar.
	<br>-Audio Only mode is only available in simple interfaces.
	<br>Advanced shows tabs with users list, options, RTMP to HLS broadcasting.
	<br>-Broadcaster has both camera tab and playback (preview) from server in advanced mode, unless in Text only mode.
	<br>-Text Only mode is available both for Simple and Advanced interface.
	<br>Advanced features like external OBS broadcast, ReStreams require advanced interface to switch between Webcam other source types tabs played as HLS.
	
	<h4>App Roles Configuration</h4>
	<textarea name="appRolesConfig" id="appRolesConfig" cols="120" rows="5"><?php echo esc_textarea( $options['appRolesConfig'] ); ?></textarea>
	<BR>Certain parameters can be configured per role, depending on usage scenario. Special values for "roles": ALL, MEMBERS, NONE.
	<BR>Current WordPress roles:
					<?php
				global $wp_roles;
				foreach ( $wp_roles->roles as $role_slug => $role )
				{
					echo esc_html( $role_slug ) . '= "' . esc_html( $role['name'] ) . '" ';
				}
	?>
	Current user roles:
					<?php
				$current_user = wp_get_current_user();
				foreach ( $current_user->roles as $role )
				{
					echo esc_html( $role ) . ' ';
				}
	?>
	Default configuration:<br><textarea readonly cols="120" rows="3"><?php echo esc_textarea( $optionsDefault['appRolesConfig'] ); ?></textarea>
	
	<BR>Parsed configuration (should be an array or arrays):<BR>
					<?php
	
				esc_html( var_dump( $options['appRoles'] ) );
	?>
	<BR>Serialized:<BR>
					<?php
	
				echo esc_html( serialize( $options['appRoles'] ) );
	?>
	<BR>Test for current user:<BR>
					<?php
	
				$userID = get_current_user_id();
				echo esc_html( '#' . $userID . ' ' );
				if ( array_key_exists( 'appRoles', $options ) )
				{
					foreach ( $options['appRoles'] as $parameter => $values )
					{
						echo esc_html( $parameter ) . ': ' . esc_html( self::appRole( $userID, $parameter, '-', $options ) ) . ', ';
					}
				}
	?>
	
	<h4>Visitor Update Interval</h4>
	
	<input name="timeIntervalVisitor" type="text" id="timeIntervalVisitor" size="10" maxlength="256" value="<?php echo esc_attr( $options['timeIntervalVisitor'] ); ?>"/>ms
	<br>Time between update web requests for visitors, in milliseconds. That's the time between updates (like chat), unless user does something (when user send a message an update also occurs). To reduce load on web server from visitors, increase interval between update web requests. Ex: <?php echo esc_attr( $optionsDefault['timeIntervalVisitor'] ); ?>
	
	
	<h4>Wallet Page</h4>
	<select name="balancePage" id="balancePage">
	<option value='-1'
					<?php
				if ( $options['balancePage'] == -1 )
				{
					echo 'selected';}
	?>
		>None</option>
					<?php
	
				$args   = array(
					'sort_order'   => 'asc',
					'sort_column'  => 'post_title',
					'hierarchical' => 1,
					'post_type'    => 'page',
					'post_status'  => 'publish',
				);
				$sPages = get_pages( $args );
				foreach ( $sPages as $sPage )
				{
					echo '<option value="' . esc_attr( $sPage->ID ) . '" ' . ( $options['balancePage'] == ( $sPage->ID ) || ( $options['balancePage'] == 0 && $sPage->post_title == 'My Wallet' ) ? 'selected' : '' ) . '>' . esc_html( $sPage->post_title ) . '</option>' . "\r\n";
				}
	?>
	</select>
	<br>Page linked from balance section, usually a page where registered users can buy credits. Recommended: My Wallet (setup with <a href="https://wordpress.org/plugins/paid-membership/">Paid Membership & Content</a> plugin).
		
	<h4>Site Menu in App</h4>
	<select name="appSiteMenu" id="appSiteMenu">
		<option value="0" <?php echo ! $options['appSiteMenu'] ? 'selected' : ''; ?>>None</option>
					<?php
				$menus = get_terms( 'nav_menu', array( 'hide_empty' => true ) );
	
				foreach ( $menus as $menu )
				{
					echo '<option value="' . esc_attr( $menu->term_id ) . '" ' . ( $options['appSiteMenu'] == ( $menu->term_id ) || ( $options['appSiteMenu'] == -1 && $menu->name == 'VideoWhisper' ) ? 'selected' : '' ) . '>' . esc_html( $menu->name ) . '</option>' . "\r\n";
				}
	
	?>
	</select>
	<br>A site menu is useful for chat users to access site features, especially when running app in full page. Warning: Broken menu data can cause errors in videochat application.
	
	<h4>Save All Snapshots</h4>
<select name="saveSnapshots" id="saveSnapshots">
	<option value="0" <?php echo ! $options['saveSnapshots'] ? 'selected' : ''; ?>>No</option>
	<option value="1" <?php echo $options['saveSnapshots'] == '1' ? 'selected' : ''; ?>>Yes</option>
</select>
<br>When enabled each camera snapshot will be saved with own timestamp, otherwise it will be overwritten. This can be used to save space or keep all snapshots for logging purposes.

	<h4>App CSS</h4>
	<textarea name="appCSS" id="appCSS" cols="100" rows="6"><?php echo esc_textarea( $options['appCSS'] ); ?></textarea>
	<br>
	CSS code to adjust or fix application styling if altered by site theme. Multiple interface elements are implemented by <a href="https://fomantic-ui.com">Fomantic UI</a> (a fork of <a href="https://semantic-ui.com">Semantic UI</a>). Editing interface and layout usually involves advanced CSS skills. For reference also see <a href="https://paidvideochat.com/html5-videochat/css/">Layout CSS</a>. Default:<br><textarea readonly cols="100" rows="3"><?php echo esc_textarea( $optionsDefault['appCSS'] ); ?></textarea>
	
	
	<h4>Mode</h4>
	<input name="modeVersion" type="text" id="modeVersion" size="80" maxlength="25" value="<?php echo esc_attr( $options['modeVersion'] ); ?>"/>
	<br>Unless configured otherwise, application runs in demo mode (with some limitations and notices), for testing by site visitors without consuming lots of server resources. If you want to disable demo mode, confirm by filling application version. <a href="https://consult.videowhisper.com">Contact</a> if you need assistance. 

	<h4>Remove Author Attribution Notices (Explicit Permission Required)</h4>
	<select name="whitelabel" id="whitelabel">
		<option value="0" <?php echo ! $options['whitelabel'] ? 'selected' : ''; ?>>Disabled</option>
		<option value="1" <?php echo $options['whitelabel'] == '1' ? 'selected' : ''; ?>>Enabled</option>
	</select>
	<br>Embedded HTML5 Videochat application is branded with subtle attribution references to authors, similar to most software solutions in the world. Removing the default author attributions can be permitted by authors  <a href="https://consult.videowhisper.com">on request</a>.
	
	<h4>CORS Access-Control-Allow-Origin</h4>
	
	<input name="corsACLO" type="text" id="corsACLO" size="80" maxlength="256" value="<?php echo esc_attr( $options['corsACLO'] ); ?>"/>
	<br>Enable external web access from these domains (CSV). Ex: http://localhost:3000
	
	<h4>Debug Mode / Dev Mode</h4>
<select name="debugMode" id="debugMode">
  <option value="1" <?php echo $options['debugMode'] == '1' ? 'selected' : ''; ?>>On</option>
  <option value="0" <?php echo $options['debugMode'] == '0' ? 'selected' : ''; ?>>Off</option>
</select>
<BR>Outputs various debugging info. Recommended: Off.

	<h4>More Documentation</h4>
	 - <a href="https://videochat-scripts.com/troubleshoot-html5-and-webrtc-streaming-in-videowhisper/">Troubleshoot HTML5 Streaming</a>: Tutorials, suggestions for troubleshooting streaming reliability and quality
	<br> - <a href="https://paidvideochat.com/html5-videochat/css/">HTML5 Videochat Layout CSS</a>
	<br> - <a href="https://fomantic-ui.com">Fomantic UI</a>: Review interface element names for applying CSS
	<br> - <a href="https://react.semantic-ui.com">Semantic UI React</a>: Review interface element names for applying CSS
	
					<?php
				break;
				case 'lovense';

				?>
				<h3>Lovense Integration</h3>
				HTML5 Videochat can notify Lovense API of tips, for toy reactions. When performer receives a tip, videochat will notify Lovense browser/extension and show an extra notification in chat.
				After <a href="https://www.lovense.com/signup">registering with Lovense</a>, configure your site from <a href="https://www.lovense.com/user/developer/info">Lovense developer dashboard</a>.
				<h4>Lovense Integration</h4>
				<select name="lovense" id="lovense">
				  <option value="0" <?php echo $options['lovense'] ? '' : 'selected'; ?>>Disabled</option>
				  <option value="1" <?php echo $options['lovense'] ? 'selected' : ''; ?>>Enabled</option>
				</select>
				<br>Load Lovense broadcaster API for performer and videochat app notifies on tips. Performer needs to access the HTML5 Videochat page with the <a href="https://www.lovense.com/r/sytsk1">Lovense Browser or Extension</a> to integrate with own toy. If performer uses Lovense browser or extension, should see version in text chat (ex: Lovense 30.4.5).
				
				<h4>Lovense Platform Name</h4>
				<input name="lovensePlatform" type="text" id="lovensePlatform" size="32" maxlength="64" value="<?php echo esc_attr( $options['lovensePlatform'] ); ?>"/>
				<br>As configured in <a href="https://www.lovense.com/signin">Lovense Dashboard</a> per <a href="https://www.lovense.com/sextoys/developer/doc#step-1-configure-your-dashboard">integration instructions</a>.
				<br>Website URL: <?php echo get_site_url() ?>
				<br>Model Broadcasting Page: <?php echo get_site_url( null, $options['custom_post']) . '/*' ?>
				
				<h4>Lovense Tip Parameters</h4>
				<select name="lovenseTipParams" id="lovenseTipParams">
				  <option value="4" <?php echo $options['lovenseTipParams'] != 3 ? 'selected' : ''; ?>>4: camExtension.receiveTip(amount, modelName, tipperName, cParameter) </option>
				  <option value="3" <?php echo $options['lovenseTipParams'] == 3 ? 'selected' : ''; ?>>3: camExtension.receiveTip(amount, tipperName, cParameter) </option>
				</select>
				<br>When the model receives a tip, integration will call receiveTip to tell the Cam Extension. The Cam Extension will trigger a response in the toy.
				<br><a href="https://www.lovense.com/sextoys/developer/doc#step-2-integration">Lovense documentation</a> mentions 4 parameters but integration feedback suggests 3. Use what works.
				
				<p>After configuring you will need to contact Lovense as described in <a href="https://www.lovense.com/user/developer/info">Lovense Developers dashboard</a> to get setup tested and approved: "Status：pending - When your integration is complete contact us to start testing."
				<br>If there's any changes required for using the latest API (like different parameters) provide feedback to VideoWhisper to apply such changes to the function calls.</p>
				
				<?php
							break;

				
			case 'import':
				?>
<h3><?php _e( 'Import Options', 'live-streaming' ); ?></h3>
Import/Export plugin settings and options.
				<?php


$importURL = sanitize_text_field( $_POST['importURL'] ?? '' );
if ($importURL) 
{
	echo '<br>Importing settings from URL: ' . esc_html( $importURL );
	$optionsImport = parse_ini_string( file_get_contents( $importURL ), false );

	//display parse error if any
	if ( $optionsImport === false )
	{
		echo '<br>Parse Error: ' . esc_html( error_get_last()['message'] );
	}

	if ($optionsImport ) foreach ( $optionsImport as $key => $value )
	{
		echo '<br>' . esc_html( " - $key = $value" );
		$options[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
	}
	update_option( 'VWliveStreamingOptions', $options );
}

				if ( $importConfig = sanitize_textarea_field( $_POST['importConfig'] ?? '' ) ) {
					echo '<br>Importing: ';
					$optionsImport = parse_ini_string( stripslashes( $importConfig ), false );
					// var_dump($optionsImport);

					foreach ( $optionsImport as $key => $value ) {
						echo wp_kses_post( "<br> - $key = $value" );
						$options[ $key ] = $value;
					}
					update_option( 'VWliveStreamingOptions', $options );
				}
				?>

<h4>Settings Import URL</h4>
<input name="importURL" type="text" id="importURL" size="120" maxlength="256" value=""/>
<br/>If you have an account with VideoWhisper go to <a href="https://consult.videowhisper.com/my-accounts/">My Accounts</a> and use Configure Apps button for the account you want to use. Copy and paste the Settings Import URL here. 
<br/>If you change your plan, import settings again as this also includes streaming plan limitations to avoid streams from being rejected.
<?php 
submit_button( "Import");
?>

<h4>Import Plugin Settings</h4>
<textarea name="importConfig" id="importConfig" cols="120" rows="12"></textarea>
<br>Quick fill settings as option = "value".
<?php 
submit_button( "Import");
?>

<h4>Export Current Plugin Settings</h4>
<textarea readonly cols="120" rows="10">[Plugin Settings]
				<?php
				foreach ( $options as $key => $value ) {
					$key = esc_attr($key);
					if (is_string($value)) {
						$value = (stripslashes($value));
						echo esc_textarea("\n$key = \"$value\"");
					} elseif (is_array($value)) {
						$value = (serialize($value));
						echo esc_textarea("\n$key = \"$value\"");
					} else {
						$value = (strval($value));
						echo esc_textarea("\n$key = \"$value\"");
					}
				}
				?>
</textarea>

<h4>Export Default Plugin Settings</h4>
<textarea readonly cols="120" rows="10">[Plugin Settings]
				<?php
				foreach ( $optionsDefault as $key => $value ) {
					$key = esc_attr($key);
					if (is_string($value)) {
						$value = (stripslashes($value));
						echo esc_textarea("\n$key = \"$value\"");
					} elseif (is_array($value)) {
						$value = (serialize($value));
						echo esc_textarea("\n$key = \"$value\"");
					} else {
						$value = (strval($value));
						echo esc_textarea("\n$key = \"$value\"");
					}
				}
				?>
</textarea>

<h5>Warning: Saving will set settings provided in Import Plugin Settings box.</h5>
				<?php

				break;

			case 'pages';
				?>
<h3><?php _e( 'Setup Pages', 'live-streaming' ); ?></h3>

				<?php
				if ( isset($_POST['submit']) ) {
					echo 'Saving pages setup...';
					$page_id = get_option( 'vwls_page_manage' );
					if ( $page_id != '-1' && $options['disablePage'] != '0' ) {
						self::deletePages();
					}

					$page_idC = get_option( 'vwls_page_channels' );
					if ( $page_idC != '-1' && $options['disablePageC'] != '0' ) {
						self::deletePages();
					}

					self::updatePages();
				}

				submit_button( __( 'Setup Pages', 'live-streaming' ) );
				?>
Use this to setup pages on your site. Pages with main feature shortcodes are required to: broadcast live channels, access channels. After setting up these pages you should add the feature pages to site menus for users to access.
A sample VideoWhisper menu will also be added when adding pages: can be configured to show in a menu section depending on theme.
<br>You can manage these anytime from backend: <a href="edit.php?post_type=page">pages</a> and <a href="nav-menus.php">menus</a>.
<BR><?php echo self::requirementRender( 'setup_pages' ); ?>

<h4>Page for Management</h4>
<p>Add channel management page (Page ID <a href='post.php?post=<?php echo get_option( 'vwls_page_manage' ); ?>&action=edit'><?php echo get_option( 'vwls_page_manage' ); ?></a>) with shortcode [videowhisper_channel_manage]</p>
<select name="disablePage" id="disablePage">
  <option value="0" <?php echo $options['disablePage'] == '0' ? 'selected' : ''; ?>>Yes</option>
  <option value="1" <?php echo $options['disablePage'] == '1' ? 'selected' : ''; ?>>No</option>
</select>


<h4>Page for Channels</h4>
<p>Add channel list page (Page ID <a href='post.php?post=<?php echo get_option( 'vwls_page_channels' ); ?>&action=edit'><?php echo get_option( 'vwls_page_channels' ); ?></a>) with shortcode [videowhisper_channels]</p>
<select name="disablePageC" id="disablePageC">
  <option value="0" <?php echo $options['disablePageC'] == '0' ? 'selected' : ''; ?>>Yes</option>
  <option value="1" <?php echo $options['disablePageC'] == '1' ? 'selected' : ''; ?>>No</option>
</select>


<h4>Manage Balance Page</h4>
<select name="balancePage" id="balancePage">
				<?php

				$args   = array(
					'sort_order'   => 'asc',
					'sort_column'  => 'post_title',
					'hierarchical' => 1,
					'post_type'    => 'page',
					'post_status'  => 'publish',
				);
				$sPages = get_pages( $args );
				foreach ( $sPages as $sPage ) {
					echo '<option value="' . intval( $sPage->ID ) . '" ' . ( $options['balancePage'] == ( $sPage->ID ) || (!$options['balancePage'] && $sPage->post_title == 'My Wallet' ) ? 'selected' : '' ) . '>' . esc_html( $sPage->post_title ) . '</option>' . "\r\n";
				}
				?>
</select>
<br>Page linked from balance section, usually a page where registered users can buy credits. Recommended: My Wallet (created by Paid Membership & Content Plugin)
				<?php

			break;

			case 'setup':
				?>
<h3><?php _e( 'Setup Overview', 'live-streaming' ); ?></h3>


 + Before setting up, make sure you have necessary <b>hosting requirements, for live video streaming</b>. This plugin has <a href="https://videowhisper.com/?p=Requirements" title="Live Streaming Requirements" target="_requirements">requirements</a> beyond regular WordPress hosting specifications and needs specific live streaming services and video tools. Recommended hosting with all streaming capabilities and video tools required for this solution: <a href="https://webrtchost.com/hosting-plans/#Complete-Hosting">Turnkey Complete Streaming & Web Hosting</a> from WebRTC Host by Videowhisper.
<br> + This plugin is designed to setup a turnkey live streaming site, changing major WP blog features. Set it up on a development environment as it can alter functionality of existing sites. To be able to revert changes, before setting up, make a recovering backup using hosting control panel or other backup tool.
<br> + To setup this plugin see <a href="admin.php?page=live-streaming-docs">Backend Documentation</a>, the project page <a href="https://broadcastlivevideo.com/setup-tutorial/" target="_documentation">BroadcastLiveVideo Setup Tutorial</a> and requirements checkpoints list on this page.
<br>If not sure about how to proceed or need clarifications, <a href="https://videowhisper.com/tickets_submit.php">contact plugin developers</a>. 

<p><a class="button primary" href="admin.php?page=live-streaming-docs">Backend Setup Tutorial & Documentation</a></p>

<h3><?php _e( 'Setup Checkpoints', 'live-streaming' ); ?></h3>

This section lists main requirements and checkpoints for setting up and using this solution. 
				<?php

				// self::requirementUpdate('setup', '1');

				// handle item skips
				$unskip = sanitize_file_name( $_GET['unskip'] ?? '' );
				if ( $unskip ) {
					self::requirementUpdate( $unskip, 0, 'skip' );
				}

				$skip = sanitize_file_name( $_GET['skip'] ?? '' );
				if ( $skip ) {
					self::requirementUpdate( $skip, 1, 'skip' );
				}

				$check = sanitize_file_name( $_GET['check'] ?? '' );
				if ( $check ) {
					self::requirementUpdate( $check, 0 );
				}

				$done = sanitize_file_name( $_GET['done'] ?? '' );
				if ( $done ) {
					self::requirementUpdate( $done, 1 );
				}

				// accessed setup page: easy
				self::requirementMet( 'setup' );

				// list requirements
				$requirements = self::requirementsGet();

				$rDone = 0;
				$htmlDone = '';
				$htmlPending = '';
				$htmlSkip = '';


				foreach ( $requirements as $label => $requirement ) {
					$html = self::requirementRender( $label, 'overview', $requirement );

					$status = self::requirementStatus( $requirement );
					$skip   = self::requirementStatus( $requirement, 'skip' );

					if ( $status ) {
						$htmlDone .= $html;
						$rDone++;
					} elseif ( $skip ) {
						$htmlSkip .= $html;
					} else {
						$htmlPending .= $html;
					}
				}

				if ( $htmlPending ) {
					echo '<h4>To Do:</h4>' . wp_kses_post( $htmlPending );
				}
				if ( $htmlSkip ) {
					echo '<h4>Skipped:</h4>' . wp_kses_post( $htmlSkip );
				}
				if ( $htmlDone ) {
					echo '<h4>Done (' . esc_html( $rDone ) . '):</h4>' . wp_kses_post( $htmlDone );
				}
				?>
* These requirements are updated with checks and checkpoints from certain pages, sections, scripts. Certain requirements may take longer to update (in example session control updates when there are live streams and streaming server calls the web server to notify). When plugin upgrades include more checks to assist in reviewing setup, these will initially show as required until checkpoint.
				<?php
				// var_dump($requirements);
				break;

			case 'translate':
				?>

<h3>Multilanguage / DeepL</h3>
HTML5 Videochat integrates <a href="https://www.deepl.com/en/whydeepl" target="_vw">DeepL</a> API for live chat text translations. Static texts can be translated as rest of WP plugins.

<h4>Multilanguage Chat</h4>
<select name="multilanguage" id="multilanguage">
  <option value="0" <?php echo $options['multilanguage'] ? '' : 'selected'; ?>>Disabled</option>
  <option value="1" <?php echo $options['multilanguage'] ? 'selected' : ''; ?>>Enabled</option>
</select>
<br>Enable users to specify language they are using in text chat.

<H4>DeepL API Key</H4>
<input name="deepLkey" type="text" id="deepLkey" size="100" maxlength="256" value="<?php echo esc_attr( $options['deepLkey'] ); ?>"/>
<br>Register a <a href="https://www.deepl.com/pro-checkout/account?productId=1200&yearly=false&trial=false">free DeepL developer account</a> to get a key. After login, you can retrieve your key from <a href="https://www.deepl.com/account/summary" target="_vw">DeepL account > Authentication Key for DeepL API</a>. For high activity sites, a paid account may be required depending on translation volume. Keep your key secret to prevent unauthorized usage.

<h4>Chat Translations</h4>
<select name="translations" id="translations">
  <option value="0" <?php echo $options['translations'] ? '' : 'selected'; ?>>Disabled</option>
  <option value="registered" <?php echo $options['translations'] == 'registered' ? 'selected' : ''; ?>>Registered</option>
  <option value="all" <?php echo $options['translations'] == 'all' ? 'selected' : ''; ?>>All Users</option>
</select>
<br>Enable translations for everybody or just registered users.

<h4>Default Language</h4>
<select name="languageDefault" id="languageDefault">
<?php
$languages = get_option( 'VWdeepLlangs' );
//list languages as options	
if ( !$languages ) echo '<option value="en-us" selected>English (American)</option>';
else foreach ( $languages as $key => $value )
{
	echo '<option value="'.$key.'" '.( $options['languageDefault'] == $key ? 'selected' : '' ).'>'.$value.'</option>';
}
?>
</select>
<br>Default language of site users. This will be used for translations if user does not specify a language.

<?php submit_button(); ?>

<H4>Supported Languages</H4>
<?php 
if ( !$languages )
{
		echo 'First runs. Setting default languages. ';
	    update_option( 'VWdeepLlangs', unserialize( 'a:31:{s:2:"bg";s:9:"Bulgarian";s:2:"cs";s:5:"Czech";s:2:"da";s:6:"Danish";s:2:"de";s:6:"German";s:2:"el";s:5:"Greek";s:5:"en-gb";s:17:"English (British)";s:5:"en-us";s:18:"English (American)";s:2:"es";s:7:"Spanish";s:2:"et";s:8:"Estonian";s:2:"fi";s:7:"Finnish";s:2:"fr";s:6:"French";s:2:"hu";s:9:"Hungarian";s:2:"id";s:10:"Indonesian";s:2:"it";s:7:"Italian";s:2:"ja";s:8:"Japanese";s:2:"ko";s:6:"Korean";s:2:"lt";s:10:"Lithuanian";s:2:"lv";s:7:"Latvian";s:2:"nb";s:9:"Norwegian";s:2:"nl";s:5:"Dutch";s:2:"pl";s:6:"Polish";s:5:"pt-br";s:22:"Portuguese (Brazilian)";s:5:"pt-pt";s:21:"Portuguese (European)";s:2:"ro";s:8:"Romanian";s:2:"ru";s:7:"Russian";s:2:"sk";s:6:"Slovak";s:2:"sl";s:9:"Slovenian";s:2:"sv";s:7:"Swedish";s:2:"tr";s:7:"Turkish";s:2:"uk";s:9:"Ukrainian";s:2:"zh";s:20:"Chinese (simplified)";}' ) );
		
		$languages = get_option( 'VWdeepLlangs', true);
		
}
var_dump( $languages );
?>
<br><a class="button secondary" target="_vw" href="<?php echo plugins_url('videowhisper-live-streaming-integration/server/translate.php?update_languages=videowhisper'); ?>">Update Supported Languages</a>
<br>This will retrieve latest list of supported languages from DeepL API, if a valid key is available.

<h3>Plugin Translations</h3>
Plugin can be translated to other languages. Some translations for plugin are available in "languages" plugin folder and you can edit/adjust or add new languages using a translation plugin like <a href="https://wordpress.org/plugins/loco-translate/">Loco Translate</a> : From Loco Translate > Plugins > Broadcast Live Video - Live Streaming you can edit existing languages or add new languages.
<br>You can also start with an automated translator application like Poedit, translate more texts with Google Translate and at the end have a human translator make final adjustments. You can contact VideoWhisper support and provide links to new translation files if you want these included in future plugin updates.

<BR>Some customizable labels and features can be translated from plugin settings.


				<?php
				break;

			case 'troubleshooting':
				?>
<h3><?php _e( 'Troubleshooting Requirements', 'live-streaming' ); ?></h3>
This section includes some tests, reporting and logs for troubleshooting various requirements.
				<?php

				// $pluginInfo = get_plugin_data(__FILE__);
				// echo "<BR>Plugin Name: " . $pluginInfo['Name'];
				// echo "<BR>Plugin Version: " . $pluginInfo['Version'];

				echo '<h4>Web Host</h4>';
				echo 'Web Name: ' . esc_html( $_SERVER['SERVER_NAME'] );
				echo '<br>Web IP: ' . esc_html( $_SERVER['SERVER_ADDR'] );
				echo '<br>Site Path: ' . esc_html( $_SERVER['DOCUMENT_ROOT'] );
				echo '<br>Server Hostname: ' . gethostname();
				echo '<br>Server OS: ' . php_uname();
				echo '<br>Web Server: ' . esc_html( $_SERVER['SERVER_SOFTWARE'] );
				echo '<br>Connection: ' . esc_html( $_SERVER['HTTP_CONNECTION'] );
				echo '<br>Client IP: ' . esc_html( $_SERVER['REMOTE_ADDR'] );
				echo '<br>Client Browser: ' . esc_html( $_SERVER['HTTP_USER_AGENT'] );

				echo '<h4>FFMPEG</h4>';

if (!$options['enable_exec']) echo 'Executing server comments is currently disabled. Enable from Server settings tab to allow advanced features.';
else
{

				echo 'exec: ';
				if ( function_exists( 'exec' ) ) {
					echo 'function is enabled';

					if ( exec( 'echo EXEC' ) == 'EXEC' ) {
						echo ' and works';
						$fexec = 1;
					} else {
						echo ' <b>but does not work</b>';
					}
				} else {
					echo '<b>function is not enabled</b><BR>PHP function "exec" is required to run FFMPEG. Current hosting settings are not compatible with this functionality.';
				}

				echo wp_kses_post( '<br>PHP script owner: ' . get_current_user() . ' #' . getmyuid() );
				echo wp_kses_post( '<br>Process effective owner: ' . posix_getpwuid( posix_geteuid() )['name'] . ' #' . posix_geteuid() );

				echo '<br>exec("whoami"): ';
				$cmd    = 'whoami';
				$output = '';
				if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );
				foreach ( $output as $outp ) {
					echo esc_html( $outp );
				}

				$cmd = $options['ffmpegPath'] . ' -version 2>&1';
				// $cmd ='timeout -s KILL 3 ' . $options['ffmpegPath'] . ' -version';

				echo wp_kses_post( "<br><BR>FFMPEG ($cmd): " );

				$output = '';
				if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );
				$ffmpeg = 0;
				if ( $returnvalue == 127 ) {
					echo wp_kses_post( "<b>Warning: not detected: $cmd</b>" );
				} else {
					echo 'found';

					if ( $returnvalue != 126 ) {
						echo ' / Output:<br><textarea readonly cols="120" rows="4">';
						echo esc_textarea( join( "\n", $output ) );
						echo '</textarea>';
						$ffmpeg = 1;
					} else {
						echo ' but is NOT executable by current user: ' . esc_html( $processUser );
					}
				}

				echo '<br>FFMPEG is a video tool required on web hosting for video stream snapshots, analysis (detecting codecs), transcoding. Usually not available on budget web hosting and available on premium video hosting.';

				if ( $ffmpeg ) {
								$cmd = $options['ffmpegPath'] . ' -codecs 2>&1';
								if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );

						echo wp_kses_post( "<br><br> + Codec libraries ($cmd):" );
						echo ' / Output:<br><textarea readonly cols="120" rows="4">';
						echo esc_textarea( join( "\n", $output ) );
						echo '</textarea>';

								// detect codecs
								$hlsAudioCodec = ''; // hlsAudioCodec
					if ( $output ) {
						if ( count( $output ) ) {
							foreach ( array( 'h264', 'vp6', 'speex', 'nellymoser', 'aacplus', 'vo_aacenc', 'faac', 'fdk_aac', 'vp8', 'vp9', 'opus' ) as $cod ) {
								$det  = 0;
								$outd = '';
								echo esc_html( "<BR>$cod : " );
								foreach ( $output as $outp ) {
									if ( strstr( $outp, $cod ) ) {
										$det  = 1;
										$outd = $outp;
									}
								};

								if ( $det ) {
									echo esc_html( "detected ($outd)" );
								} elseif ( in_array( $cod, array( 'aacplus', 'vo_aacenc', 'faac', 'fdk_aac' ) ) ) {
									echo esc_html( "lib$cod is missing but other aac codec may be available" );
								} else {
									echo wp_kses_post( "<b>missing: configure and install FFMPEG with lib$cod if you don't have another library for that codec</b>" );
								}

								if ( $det && in_array( $cod, array( 'aacplus', 'vo_aacenc', 'faac', 'fdk_aac' ) ) ) {
									$hlsAudioCodec = 'lib' . $cod;
								}
							}
						}
					}
					?>
<BR>You need only 1 AAC codec for transcoding to AAC. Depending on <a href="https://trac.ffmpeg.org/wiki/Encode/AAC#libfaac">AAC library available on your system</a> you may need to update transcoding parameters. Latest FFMPEG also includes a native encoder (aac).
					<?php

					$cmd = $options['ffmpegPath'] . ' -protocols 2>&1';
					if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );

						echo wp_kses_post( "<br><br> + Codecs & Protocols ($cmd):" );
						echo ' / Output:<br><textarea readonly cols="120" rows="4">';
						echo esc_textarea( join( "\n", $output ) );
						echo '</textarea>';

					// image handling test

					$src  = plugin_dir_path( dirname( __FILE__ ) ) . 'screenshot-5.png';
					$dest = $options['uploadsPath'] . '/ffmpeg-test.png';

					$cmd = $options['ffmpegPath'] . " -y -i '$src' -vf scale=320:-1 '$dest' 2>&1";

					flush();

					echo wp_kses_post( "<br><BR> + FFMPEG Image Resize Test ($cmd): " );

					$output = '';
					if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );

					if ( $returnvalue == 127 ) {
						echo wp_kses_post( "<br>Warning: not detected ($returnvalue): $cmd :" . $output[0] );
					} else {
						if ( $returnvalue != 126 ) {
							echo 'Output:<br><textarea readonly cols="120" rows="4">';
							echo esc_textarea( join( "\n", $output ) );
							echo '</textarea>';
						} else {
							echo ' but is NOT executable by current user. ';
						}
					}

					echo wp_kses_post( "<br>Output ($dest):" );
					if ( file_exists( $dest ) ) {
						echo wp_kses_post( 'found <a href=' . self::path2url( $dest ) . ' target="_blank">Open</a>' );
					} else {
						echo 'not found (Failed): review ffmpeg configuration and process/file ownership/permissions';
					}
				}

				echo '<h4>FFMPEG Logs</h4>Logs from last operations attempted, for troubleshooting. Make sure FFMPEG is functional and scripts can write the log files. Then try features that should call this functionality.';

				$lastLog = $options['uploadsPath'] . '/lastLog-streamSnapshot.txt';
				echo wp_kses_post( "<h5>FFMPEG Stream Snapshot</h5>  $lastLog : " );
				if ( ! file_exists( $lastLog ) ) {
					echo 'Not found, yet!';
				} else {
					$log = self::varLoad( $lastLog );
					echo '<br>Time: ' . date( DATE_RFC2822, $log['time'] );
					echo '<br>Command: ' . esc_html( $log['cmd'] );
					echo '<br>Return: ' . esc_html( $log['return'] );
					echo '<br>Output[0]: ' . esc_html( $log['output0'] );
					echo '<br>File: ' . esc_html( $log['file'] );
					if ( ! file_exists( $log['file'] ) ) {
						echo ' Log file not found!';
					} else {
						echo '<br><textarea readonly cols="100" rows="4">' . esc_textarea( file_get_contents( $log['file'] ) ) . '</textarea>';
					}
				}

				$lastLog = $options['uploadsPath'] . '/lastLog-streamInfo.txt';
				echo wp_kses_post( "<h5>FFMPEG Stream Info</h5>  $lastLog : " );
				if ( ! file_exists( $lastLog ) ) {
					echo 'Not found, yet!';
				} else {
					$log = self::varLoad( $lastLog );
					echo '<br>Time: ' . date( DATE_RFC2822, $log['time'] );
					echo '<br>Command: ' . esc_html( $log['cmd'] );
					echo '<br>Return: ' . esc_html( $log['return'] );
					echo '<br>Output[0]: ' . esc_html( $log['output0'] );
					echo '<br>File: ' . esc_html( $log['file'] );
					if ( ! file_exists( $log['file'] ) ) {
						echo ' Log file not found!';
					} else {
						echo '<br><textarea readonly cols="100" rows="4">' . esc_textarea( file_get_contents( $log['file'] ) ) . '</textarea>';
					}
				}

				$lastLog = $options['uploadsPath'] . '/lastLog-iptvStart.txt';
				echo wp_kses_post( "<h5>IPTV Stream Start</h5>  $lastLog : " );
				if ( ! file_exists( $lastLog ) ) {
					echo 'Not found, yet!';
				} else {
					$log = self::varLoad( $lastLog );
					echo '<br>Time: ' . date( DATE_RFC2822, $log['time'] );
					echo '<br>Command: ' . esc_html( $log['cmd'] );
					echo '<br>Return: ' . esc_html( $log['return'] );
					echo '<br>Output[0]: ' . esc_html( $log['output0'] );
					echo '<br>File: ' . esc_html( $log['file'] );
					if ( ! file_exists( $log['file'] ) ) {
						echo ' Log file not found!';
					} else {
						echo '<br><textarea readonly cols="100" rows="4">' . esc_textarea( file_get_contents( $log['file'] ) ) . '</textarea>';
					}
				}

				$lastLog = $options['uploadsPath'] . '/lastLog-streamTranscode.txt';
				echo wp_kses_post( "<h5>FFMPEG Stream Transcode</h5>  $lastLog : " );
				if ( ! file_exists( $lastLog ) ) {
					echo 'Not found, yet!';
				} else {
					$log = self::varLoad( $lastLog );
					echo '<br>Time: ' . date( DATE_RFC2822, $log['time'] );
					echo '<br>Command: ' . esc_html( $log['cmd'] );
					echo '<br>Return: ' . esc_html( $log['return'] );
					echo '<br>Output[0]: ' . esc_html( $log['output0'] );
					echo '<br>File: ' . esc_html( $log['file'] );
					if ( ! file_exists( $log['file'] ) ) {
						echo ' Log file not found!';
					} else {
						echo '<br><textarea readonly cols="100" rows="4">' . esc_textarea( file_get_contents( $log['file'] ) ) . '</textarea>';
					}
				}

				$lastLog = $options['uploadsPath'] . '/lastLog-streamSetup.txt';
				echo wp_kses_post( "<h5>FFMPEG Stream Setup</h5>  $lastLog : " );
				if ( ! file_exists( $lastLog ) ) {
					echo 'Not found, yet!';
				} else {
					$log = self::varLoad( $lastLog );
					echo '<br>Time: ' . date( DATE_RFC2822, $log['time'] );
					echo '<br>Command: ' . esc_html( $log['cmd'] );
					echo '<br>Return: ' . esc_html( $log['return'] );
					echo '<br>Output[0]: ' . esc_html( $log['output0'] );
					echo '<br>File: ' . esc_html( $log['file'] );
					if ( ! file_exists( $log['file'] ) ) {
						echo ' Log file not found!';
					} else {
						echo '<br><textarea readonly cols="100" rows="4">' . esc_textarea( file_get_contents( $log['file'] ) ) . '</textarea>';
					}
				}
} //end $options['enable_exec']

				break;
			case 'reset':
				?>
<h3><?php _e( 'Reset Options', 'live-streaming' ); ?></h3>
This resets some options to defaults. Useful when upgrading plugin and new defaults are available for new features and for fixing broken installations.
				<?php

				$confirm = ( isset( $_GET['confirm'] ) && $_GET['confirm'] == '1' );

				if ( $confirm ) {
					echo '<h4>Resetting...</h4>';
				} else {
					echo '<p><A class="button" href="' . get_permalink() . 'admin.php?page=live-streaming&tab=reset&confirm=1">Yes, Reset These Settings!</A></p>';
				}

				$resetOptions = array( 'customCSS', 'custom_post', 'supportP2P', 'alwaysP2P' );

				foreach ( $resetOptions as $opt ) {
					echo '<BR> - ' . esc_html( $opt );
					if ( $confirm ) {
						$options[ $opt ] = $optionsDefault[ $opt ];
					}
				}

				if ( $confirm ) {
					update_option( 'VWliveStreamingOptions', $options );
				}

				$installed_ver = get_option( 'vwls_db_version' );

				echo '<h4>DB Version</h4>' . esc_html( $installed_ver );
				break;

			case 'watch-params':
				$options['watchRoleParametersConfig'] = htmlentities( stripslashes( $options['watchRoleParametersConfig'] ) );

				?>
<h3>Watch Parameters: Advanced Configuration by Role</h3>
This permits advanced configuration for watch interface parameters based on user role.
<br>For more details about available parameters and possible values see <a href="https://videowhisper.com/?p=php+live+streaming#integrate">PHP Live Streaming documentation</a>.

<h4>Watch Role Parameters Configuration</h4>
<textarea name="watchRoleParametersConfig" id="watchRoleParametersConfig" cols="100" rows="5"><?php echo esc_textarea( $options['watchRoleParametersConfig'] ); ?></textarea>
<BR>This overwrites parameters defined as permissions by channel owner.
Default:<br><textarea readonly cols="100" rows="4"><?php echo esc_textarea( $optionsDefault['watchRoleParametersConfig'] ); ?></textarea>

<BR>Parsed configuration (should be an array of arrays):
				<?php

				echo '<br><textarea readonly cols="100" rows="4">';
				var_dump( $options['watchRoleParameters'] );
				echo '</textarea>';

				$current_user = wp_get_current_user();
				
				echo '<h4>Testing</h4>Your role(s): ';
				var_dump( $current_user->roles );

				echo '<BR>Role Parameters: ';
				var_dump( self::userParameters( $current_user, $options['watchRoleParameters'] ) );

				break;

			case 'watch-limit':
				$options['userWatchLimitsConfig'] = htmlentities( stripslashes( $options['userWatchLimitsConfig'] ) );

				?>
<h3>Watch Limit</h3>
Limit watch time per user (by keeping track for each user of total watch time on site).
<br>Stream Session Control also enables for external RTMP apps and HTML5 WebRTC sessions. Does not monitor HTTP based mobile streaming (HLS / MPEG DASH).
<br>Only works for registered users as this records info as user metas. Does not work for site visitors: you should disable visitor access when using this.
<br>User watch time in Flash app is updated based on <a href="admin.php?page=live-streaming&tab=watcher">statusInterval parameter</a>. If configured at 60000 (ms), will update once per minute. Warning: A low value can highly impact web server load when multiple users are online. Recommended interval is 1-5 minutes depending on average content length and acceptable grace time.

<h4>Enable Watch Limit per User</h4>
<select name="userWatchLimit" id="userWatchLimit">
  <option value="1" <?php echo $options['userWatchLimit'] ? 'selected' : ''; ?>>Yes</option>
  <option value="0" <?php echo $options['userWatchLimit'] ? '' : 'selected'; ?>>No</option>
</select>

<h4>Limit Interval</h4>
<input name="userWatchInterval" type="text" id="userWatchInterval" size="12" maxlength="32" value="<?php echo esc_attr( $options['userWatchInterval'] ); ?>"/>s
<BR>Specify interval for limits in seconds (in example 2592000 = 1 month, Default: <?php echo esc_html( $optionsDefault['userWatchInterval'] ); ?>).

<h4>Default Limit</h4>
<input name="userWatchLimitDefault" type="text" id="userWatchLimitDefault" size="12" maxlength="32" value="<?php echo esc_attr( $options['userWatchLimitDefault'] ); ?>"/>s
<br>Default limit for user in seconds (Ex: 108000 = 30h, Default: <?php echo esc_html( $optionsDefault['userWatchLimitDefault'] ); ?>).

<h4>User Watch Limits Configuration</h4>
<textarea name="userWatchLimitsConfig" id="userWatchLimitsConfig" cols="100" rows="5"><?php echo esc_textarea( $options['userWatchLimitsConfig'] ); ?></textarea>
<BR>Assign limit in hours, by role, one per line. Set 0 for unlimited.
Default:<br><textarea readonly cols="100" rows="4"><?php echo esc_textarea( $optionsDefault['userWatchLimitsConfig'] ); ?></textarea>

<BR>Parsed configuration (should be an array):<BR>
				<?php

				var_dump( $options['userWatchLimits'] );

				$current_user = wp_get_current_user();
				
				echo '<h4>Testing</h4>Your role(s): ';
				var_dump( $current_user->roles );
				echo '<BR>Your Watch Time: ' . intval ( get_user_meta( $current_user->ID, 'vwls_watch', true ) ) . 's';
				echo '<BR>Since: ' . date( 'F j, Y, g:i a', intval( get_user_meta( $current_user->ID, 'vwls_watch_update', true ) ));
				echo '<BR>Your Limit: ' . $limit = self::userWatchLimit( $current_user, $options ) ? esc_html( $limit ?? 0 ) . 's' : 'unlimited';

				break;

			case 'iptv':
				?>
<h3>IPTV / Pull Streams - Under Development</h3>
The IPTV system can be used to publish external (existing) streams as channels on this system. Source streams can be pulled from IPTV, IP Cameras, other streaming servers or platforms.
<h4>Active IPTV Streams</h4>
				<?php

				$iptvActive = $options['uploadsPath'] . '/iptvActive.txt';

				$streamsActive = self::varLoad( $iptvActive );
				if ( ! is_array( $streamsActive ) ) {
					$streamsActive = array();
				}

				if ( count( $streamsActive ) ) {
					foreach ( $streamsActive as $postID => $pid ) {
						echo ' - ';

						$post = get_post( $postID );
						if ( $post ) {
							echo esc_html( $post->post_title );
							echo esc_html( " ($pid)" );
						} else {
							echo esc_html( "Post #$postID not found. Deleted? Removing..." );

							// clean and update
							unset( $streamsActive[ $postID ] );
							self::varSave( $iptvActive, $streamsActive );
						}

						echo '<br>';
					}
				} else {
					echo 'No IPTV active streams.';
				}
				?>

<h4>IPTV</h4>
<select name="iptv" id="iptv">
  <option value="0" <?php echo $options['iptv'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['iptv'] == '1' ? 'selected' : ''; ?>>Yes</option>
</select>

<h4>Registration on Cam/Stream Setup</h4>
<select name="ipcamera_registration" id="ipcamera_registration">
  <option value="0" <?php echo $options['ipcamera_registration'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['ipcamera_registration'] == '1' ? 'selected' : ''; ?>>Yes</option>
</select>
<br>Allows visitors to quickly register after testing a streaming address. Not recommended.


				<?php
				break;
			case 'stream':
				?>

<h3>Stream Files / Re-Streaming / IP Camera Streams</h3>
This functionality requires web and streaming services on same server (host) when using stream configuration files for Wowza SE. 
<br> - Server administrator must add stream monitoring for your app <?php echo esc_html( $options['applicationWebRTC'] ); ?> in startupStreamsMonitorApplicationList from Server.xml for Wowza SE
<br> - In Application.xml set StreamType: rtp-live & LiveStreamPacketizers: cupertinostreamingpacketizer, mpegdashstreamingpacketizer & MediaCaster/RTP/RTSP/RTPTransportMode: interleave.
<br> - RTSP streams should be for H264/H265 video (often &subtype=0). Audio track can be AAC or missing.
<br> - IP Cameras feature needs to be enabled from <a href="admin.php?page=live-streaming&tab=features">Channel Features</a> for broadcaster user roles that need to use this
				<?php
				if ( $removeStream = intval( $_GET['removeStream'] ?? 0 ) ) {
					echo '<h4>Removing Stream</h4>';

					$roomPost = get_post( $removeStream );
					if ( ! $roomPost ) {
						echo 'Channel not found: #' . esc_html( $removeStream );
					} else {
						$stream = $roomPost->post_title;
						echo 'Room: ' . esc_html( $stream );

						$streamFile = $options['streamsPath'] . '/' . $stream;

						if ( file_exists( $streamFile ) ) {
							$ftime = filemtime( $streamFile );
							echo '<br>Found file date: ' . date( DATE_RFC2822, $ftime );
							unlink( $streamFile );
							echo '<br>Removed: ' . esc_html( $streamFile );
						} else {
							echo '<br>Stream file not found: ' . esc_html( $streamFile );
						}

						update_post_meta( $roomPost->ID, 'vw_ipCamera', '' );
						echo '<br>Removed channel re-streaming configuration.';

					}
				}
				
				
				if ( $tryStream = intval( $_GET['tryStream'] ?? 0 ) )
				{
					
					echo '<h4>Trying Stream</h4>';
					flush();

					$roomPost = get_post( $tryStream );
					if ( ! $roomPost ) {
						echo 'Channel not found: #' . esc_html( $tryStream );
					} else {
						$stream = $roomPost->post_title;
						$address = get_post_meta( $roomPost->ID, 'vw_ipCamera', true);
						
						$streamFile = $options['streamsPath'] . '/' . $stream;
						if ( file_exists($streamFile) ) $addressFile = file_get_contents( $streamFile );
						else $addressFile = 'Not active';

						echo 'Trying ' . esc_html( $stream ) . ' / ' . esc_html( $address );
						echo '<br>Configuration file: ' . esc_html( $addressFile );

						echo '<br><a class="button" href="' . get_permalink($tryStream).'">Open Channel Page</a>';
						echo '<a class="button" href="post.php?action=edit&post=' . $tryStream .'">Edit Channel</a>';

						echo '<br>Analyzing stream could take some time, until enough data is received..<br>';
						flush();


if ($address)
{			
	
			list($addressProtocol) = explode( ':', strtolower( $address ) );

				
			// try to retrieve a snapshot
			$dir = $options['uploadsPath'];
			if ( ! file_exists( $dir ) ) {
				mkdir( $dir );
			}
			$dir .= '/_setup';
			if ( ! file_exists( $dir ) ) {
				mkdir( $dir );
			}
			if ( ! file_exists( $dir ) ) {
				$error = error_get_last();
				echo 'Error - Folder does not exist and could not be created: ' . $dir . ' - ' . $error['message'];
			}

			$filename     = "$dir/$stream.jpg";
			$log_file     = $filename . '.txt';
			$log_file_cmd = $filename . '-cmd.txt';

			$cmdP = '';
			$cmdT = '';

			// movie streams start with blank screens
			if ( strstr( $address, '.mp4' ) || strstr( $address, '.mov' ) || strstr( $address, 'mp4:' ) ) {
				$cmdT = '-ss 00:00:02';
			}

			if ( $addressProtocol == 'rtsp' ) {
				$cmdP = '-rtsp_transport tcp'; // use tcp for rtsp
			}

			$cmd = $options['ffmpegSnapshotTimeout'] . ' ' . $options['ffmpegPath'] . " -y -frames 1 \"$filename\" $cmdP $cmdT -i \"" . $address . "\" >&$log_file  ";

			// echo $cmd;
			$output = '';
			$outputC ='';
			if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );
			if ( $options['enable_exec'] ) exec( "echo '$cmd' >> $log_file_cmd", $outputC, $returnvalueC );

			$lastLog = $options['uploadsPath'] . '/lastLog-streamTryTCP.txt';
			self::varSave(
				$lastLog,
				array(
					'file'    => $log_file,
					'cmd'     => $cmd,
					'return'  => $returnvalue,
					'output0' => $output[0],
					'time'    => time(),
				)
			);

			$devInfo = '';
			$devInfo = "[RTSP-TCP:$cmd]";
			
			// try also try over udp without $cmdP
			if ( ! file_exists( $filename ) ) {
				$cmd = $options['ffmpegSnapshotTimeout'] . ' ' . $options['ffmpegPath'] . " -y -frames 1 \"$filename\" $cmdT -i \"" . $address . "\" >&$log_file  ";

				// echo $cmd;
				$output = '';
				if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );
				$outputC = '';
				if ( $options['enable_exec'] ) exec( "echo '$cmd' >> $log_file_cmd", $outputC, $returnvalueC );

				$lastLog = $options['uploadsPath'] . '/lastLog-streamTryUDP.txt';
				self::varSave(
					$lastLog,
					array(
						'file'    => $log_file,
						'cmd'     => $cmd,
						'return'  => $returnvalue,
						'output0' => $output[0],
						'time'    => time(),
					)
				);

					$devInfo .= " [RTSP-UDP:$cmd]";
			}

			// failed
			if ( ! file_exists( $filename ) ) {
				
				echo 'Snapshot could not be retrieved from ' . $addressProtocol . ': ' . $address . $devInfo  ;
				echo '<br><pre>' . esc_html( implode("\n", $output) ) . '</pre>';
			}
			else
			{
				
			$previewSrc = self::path2url( $filename );
			echo '<br>A snapshot was retrieved: <br> <IMG class="ui rounded image big" SRC="' . $previewSrc . '"><br>' . $devInfo;

			echo '<br>Analyzing stream. Please wait... ';

			flush();

				//retrieve info
				$command = $options['ffmpegPath'] . ' -probesize 5M -analyzeduration 5M -t 5 ' . $cmdP . ' -i "'. $address . '" -f null -hide_banner -y /dev/null 2>&1';
				$output = shell_exec($command);

				echo '<br>Command: ' . $command;
				echo '<br><pre>' . $output . '</pre>';


				$videoCodecPattern = '/Stream #[0-9]+:[0-9]+: Video: ([^,]+),/';
				$audioCodecPattern = '/Stream #[0-9]+:[0-9]+: Audio: ([^,]+),/';
				$bitratePattern = '/bitrate: ([0-9]+) kb\/s/';
				$dataProcessedPattern = '/video:([0-9]+)kB/';
				$resolutionPattern = '/, ([0-9]+x[0-9]+)[, ]/';  // Pattern to capture resolution

				$videoCodec = null;
				$audioCodec = null;
				$totalBitrate = null;
				$resolution = null;  // Variable to store resolution

				
				if (preg_match($videoCodecPattern, $output, $matches)) {
					$videoCodec = $matches[1];
				}
				
				if (preg_match($audioCodecPattern, $output, $matches)) {
					$audioCodec = $matches[1];
				}
				
				if (preg_match($bitratePattern, $output, $matches)) {
					$totalBitrate = $matches[1];
				} elseif (preg_match($dataProcessedPattern, $output, $matches)) {
					$dataInKB = $matches[1];
					$totalBitrate = ($dataInKB * 8 * 1024) / 5; // Given the 5-second duration
					$totalBitrate = round($totalBitrate / 1024); // Convert to kbps
				}

				if (preg_match($resolutionPattern, $output, $matches)) {
					$resolution = $matches[1];
				}				
				
				echo "<br>Video Codec: " . ($videoCodec ?: 'n/a') . "\n";
				if ( $videoCodec) update_post_meta( $tryStream, 'stream-codec-video', strtolower( $videoCodec ) );

				echo "<br>Audio Codec: " . ($audioCodec ?: 'n/a') . "\n";
				if ( $audioCodec ) update_post_meta( $tryStream, 'stream-codec-audio', strtolower( $audioCodec ) );

				echo "<br>Total Bitrate: " . ($totalBitrate ?: 'n/a') . " kb/s\n";
				if ( $totalBitrate ) update_post_meta( $tryStream, 'stream-bitrate', $totalBitrate );
				
				echo "<br>Resolution: " . ($resolution ?: "Not found") . "\n";
				if ( $resolution ) update_post_meta( $tryStream, 'stream-resolution', $resolution );

				update_post_meta( $tryStream, 'stream-codec-detect', time() );

			}

		}
				
				//
						}

					
				} 
				?>

<h4>Stream Channels</h4>
Channels configured for re-streaming:
				<?php

				$addresses = array();

				$ztime = time();

				// query
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

				echo '<table class="wp-list-table widefat striped"><tr><th>Channel</th><th>Owner</th><th>Action</th><th>Address</th><th><small>Accessed/User/Owner</small></th><th><small>Paused/Live/Thumb</small></th><th>Detected</th></tr>';

				if ( is_array( $posts ) ) {
					if ( count( $posts ) ) {
						foreach ( $posts as $post ) {
							echo '<tr ' . esc_attr( ++$k % 2 ? 'class="alternate"' : '' ) . '>';
							// update status
							self::restreamPause( $post->ID, $post->post_title, $options );

							$edate     = intval( get_post_meta( $post->ID, 'edate', true ) );
							$thumbTime = intval( get_post_meta( $post->ID, 'thumbTime', true ) );

							$vw_ipCamera    = get_post_meta( $post->ID, 'vw_ipCamera', true );
							$restreamPaused = get_post_meta( $post->ID, 'restreamPaused', true );

							// access time
							$accessedUser = intval( get_post_meta( $post->ID, 'accessedUser', true ) );
							$accessed     = intval( get_post_meta( $post->ID, 'accessed', true ) );

							// author site access time
							$userID = get_post_field( 'post_author', $post->ID );
							$user   = get_userdata( $userID );

							$accessTime = intval( get_user_meta( $userID, 'accessTime', true ) );

							$codec = get_post_meta( $post->ID, 'stream-codec-video', true );
							$bitrate = get_post_meta( $post->ID, 'stream-bitrate', true );
							$resolution = get_post_meta( $post->ID, 'stream-resolution', true );

							echo '<TH><a href="' . get_permalink( $post->ID ) . '" target="_channel">' . esc_html( $post->post_title ) . '</a></TH>';
							echo '<td>' . esc_html( $user->user_login ) . '</td>';
							echo '<TD><a class="secondary button" target="_vwtry" href="admin.php?page=live-streaming&tab=stream&tryStream=' . intval( $post->ID ) . '">Try / Detect</a> <a class="secondary button" href="admin.php?page=live-streaming&tab=stream&removeStream=' . intval( $post->ID ) . '">Remove</a></TD><TD><small>' . esc_html( htmlspecialchars( $vw_ipCamera ) ) . '</small></TD>';

							echo '<td><small>' . self::format_age( $ztime - $accessed ) . '<br>'. self::format_age( $ztime - $accessedUser ) . '<br>' . self::format_age( $ztime - $accessTime ) . '</small></td>';

							echo '<td><small>' . ( $restreamPaused ? 'Yes' : 'No' ) . '<br>' . self::format_age( $ztime - $edate ) . '<br>' . self::format_age( $ztime - $thumbTime ) . '</small></td>';

							echo '<td><small>' . $codec . ' ' . $bitrate . 'k ' . $resolution . '</small></td>';

							echo '</tr>';

							$addresses[$post->ID] = $vw_ipCamera;

						}
					} else {
						echo '<tr><td colspan=6>No channels with streams.<td></tr>';
					}
				}

							echo '</table>';
				?>
<h4>Stream Files (Active Configurations)</h4>
Stream files in configured streams folder:
				<?php
					echo esc_html( $options['streamsPath'] );

				$removeFile =  base64_decode( sanitize_text_field( $_GET['removeFile'] ?? '' ) );
				if ( $removeFile ) {
					echo '<br>Remove: ' . esc_html( $removeFile );

					if ( substr( $removeFile, 0, strlen( $options['streamsPath'] ) ) == $options['streamsPath'] ) {
						if ( file_exists( $removeFile ) ) {
							unlink( $removeFile );
						} else {
							echo ' NOT FOUND!';
						}
					} else {
						echo ' BAD PATH!';
					}
				}
				$files = array();
				foreach ( glob( $options['streamsPath'] . '/*.stream' ) as $file ) {
					$files[] = $file;

				}

				if ( count( $files ) ) {
					foreach ( $files as $file ) {
						$address = file_get_contents( $file );
						echo '<BR>' . esc_html( $file . ' : ' . htmlspecialchars( $address ) );
						echo ' <a class="secondary button" href="admin.php?page=live-streaming&tab=stream&removeFile=' . esc_attr( urlencode( base64_encode( $file ) ) ) . '">Remove</a>';

						$found = array_search($address, $addresses);
						
						if ( ! $found ) {
							echo ' * NOT assigned to any channel!';
							if ( $options['restreamClean'] ) {
								unlink( $file );
								echo ' - CLEANED';
							}
						}else {
							echo '<a href="' . get_permalink( $found ). '">#' . $found . '</a>';
							};
					}
				} else {
					echo '<br>No stream files detected in configured folder.';
				}

				?>
<h4>IP Cams</h4>
<select name="ipcams" id="ipcams">
  <option value="0" <?php echo $options['ipcams'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['ipcams'] == '1' ? 'selected' : ''; ?>>Yes</option>
</select>
<br>Enable users to setup IP cams / re-streams from broadcast live interface (depending on permissions).


<h4>Auto Pause</h4>
<select name="restreamPause" id="restreamPause">
  <option value="0" <?php echo $options['restreamPause'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['restreamPause'] == '1' ? 'selected' : ''; ?>>Yes</option>
</select>
<br>Pause re-streaming while not needed, to reduce bandwidth usage & server load. This may cause issues with resuming streams depending on stream source.


<h4>Resume</h4>
Restreaming updates done by WP cron that also updates snapshots.
				<?php
					echo '<BR>Next automated check (WP Cron, 10 min or more depending on site activity): in ' . ( wp_next_scheduled( 'cron_10min_event' ) - time() ) . 's';
				?>

<h5>Activity Timeout</h5>
<input name="restreamTimeout" type="text" id="restreamTimeout" size="16" maxlength="32" value="<?php echo esc_attr( $options['restreamTimeout'] ); ?>"/>s

<br>Resume if any of these occurred during timeout period:

<h5>Resume On Channel Access</h5>
<select name="restreamAccessed" id="restreamAccessed">
  <option value="0" <?php echo $options['restreamAccessed'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['restreamAccessed'] == '1' ? 'selected' : ''; ?>>Yes</option>
</select> Any access (visitor or registered user) will resume stream. When streams should be accessible by anybody. Warning: This can be triggered often by crawlers, bots.

<h5>Resume On Channel Access by Registered User</h5>
<select name="restreamAccessedUser" id="restreamAccessedUser">
  <option value="0" <?php echo $options['restreamAccessedUser'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['restreamAccessedUser'] == '1' ? 'selected' : ''; ?>>Yes</option>
</select> Registered user access will resume stream. When service is used by site members (IPTV site).

<h5>Resume On Owner Active</h5>
<select name="restreamActiveOwner" id="restreamActiveOwner">
  <option value="0" <?php echo $options['restreamActiveOwner'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['restreamActiveOwner'] == '1' ? 'selected' : ''; ?>>Yes</option>
</select> Channel owner active on site will resume streams. When service is used by owner (IP camera monitoring site). Not recommended when many streams are setup under same account, as that can deplete server / account streaming capacity for running all streams at same time.

<h5>Resume On Any User Active</h5>
<select name="restreamActiveUser" id="restreamActiveUser">
  <option value="0" <?php echo $options['restreamActiveUser'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['restreamActiveUser'] == '1' ? 'selected' : ''; ?>>Yes</option>
</select>ANY registered user active on site will resume ALL streams. When there are few streams and site is used by few users that can check all streams.

<h4>Maximum Number of Broadcasting Channels (per User)</h4>
<input name="maxChannels" type="text" id="maxChannels" size="2" maxlength="4" value="<?php echo esc_attr( $options['maxChannels'] ); ?>"/>
<BR>Maximum channels users are allowed to create from frontend if channel posts are enabled. You may need a higher value if you manage multiple IP cameras from same account. 


<h4>Transcode Re-Streams</h4>
<select name="transcodeReStreams" id="transcodeReStreams">
  <option value="0" <?php echo $options['transcodeReStreams'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['transcodeReStreams'] == '1' ? 'selected' : ''; ?>>Yes</option>
</select>
<br>Incoming streams should be encoded with H264 & AAC for playback without transcoding. Default: No
<br>Warning: Transcoding involves extra latency, extra delay for stream to become available in new version and high server processing load (cpu & memory).


<h4>Registration on Cam/Stream Setup</h4>
<select name="ipcamera_registration" id="ipcamera_registration">
  <option value="0" <?php echo $options['ipcamera_registration'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['ipcamera_registration'] == '1' ? 'selected' : ''; ?>>Yes</option>
</select>
<br>Allows visitors to quickly register after testing a streaming address. Not recommended.


<h4>Remove Orphan Stream Files</h4>
<select name="restreamClean" id="restreamClean">
  <option value="0" <?php echo $options['restreamClean'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['restreamClean'] == '1' ? 'selected' : ''; ?>>Yes</option>
</select>
<br>Remove .stream files not assigned to any channel.

<h4>Streams Path</h4>
<input name="streamsPath" type="text" id="streamsPath" size="100" maxlength="256" value="<?php echo esc_attr( $options['streamsPath'] ); ?>"/>

<BR>Path to .stream files monitored by streaming server for restreaming.
<BR>Such functionality requires a server with latest Wowza Streaming Engine, web and rtmp on same sever, <a href='https://www.wowza.com/forums/content.php?39-How-to-re-stream-video-from-an-IP-camera-(RTSP-RTP-re-streaming)#config_xml'>specific setup</a>. Streaming server loads configuration from web files, connects to IP camera stream or video file, loads stream and delivers in format suitable for web publishing.
<BR>This functionality is available with <a href="https://webrtchost.com/hosting-plans/#Complete-Hosting" target="_vwhost">VideoWhisper Complete Hosting plans</a> and servers, when hosting both web and rtmp on same plan/server so web scripts can access streaming configuration files.
If custom ports are used, server firewall must be configured to allow connections.

<BR> 
				<?php
				echo esc_html( $options['streamsPath'] ) . ' : ';
				if ( file_exists( $options['streamsPath'] ) ) {
					echo 'Found. ';
					if ( is_writable( $options['streamsPath'] ) ) {
						echo 'Writable. (OK)';
					} else {
						echo 'NOT writable.';
					}
				} else {
					echo '<b>NOT found!</b>';
				}

				submit_button();
?>
<h4>Increase Compatibility</h4>
For increased compatibility these settings could be configured in Application.xml for Wowza SE:
<?php 
echo '<pre>' . htmlspecialchars('
	<!-- VideoWhisper.com :  IP camera RTSP Application.xml / Application -->	
	<StreamType>rtp-live</StreamType>

	<!-- VideoWhisper.com :  IP camera RTSP Application.xml / Application/Streams -->	
	<LiveStreamPacketizers>cupertinostreamingpacketizer,mpegdashstreamingpacketizer</LiveStreamPacketizers>

	<!-- VideoWhisper.com :  IP camera RTSP Application.xml / Application/MediaCaster/Properties -->			
	<Property>
	    <Name>rtspValidationFrequency</Name>
	    <Value>0</Value>
	    <Type>Integer</Type>
	</Property>
	<Property>
	    <Name>rtspFilterUnknownTracks</Name>
	    <Value>true</Value>
	    <Type>Boolean</Type>
	</Property>
	<Property>
	    <Name>rtspStreamAudioTrack</Name>
	    <Value>false</Value>
	    <Type>Boolean</Type>
	</Property>

	<!-- VideoWhisper.com :  IP camera RTSP Application.xml / Application/MediaCaster/RTP -->		

				<RTSP>
					<!-- udp, interleave -->
					<RTPTransportMode>interleave</RTPTransportMode>
					</RTSP>

	') . '</pre>';

				break;

			case 'playlists':
				?>

<h3>Playlist Scheduler Settings</h3>
This section is for configuring settings related to SMIL playlists. Playlist can be used to schedule videos to play as a live stream (on a channel).
Playlist support can be configured on <a href='https://www.wowza.com/forums/content.php?145-How-to-schedule-streaming-with-Wowza-Streaming-Engine-(StreamPublisher)#installation'>Wowza Streaming Engine</a> and requires web and rtmp on same servers (so web scripts can write playlists).

<h4>Video Share VOD</h4>
				<?php
				if ( is_plugin_active( 'video-share-vod/video-share-vod.php' ) ) {
					echo 'Detected.';
					$optionsVSV        = get_option( 'VWvideoShareOptions' );
					$custom_post_video = $optionsVSV['custom_post'];

					echo ' Post type name: ' . esc_html( $optionsVSV['custom_post'] );

				} else {
					echo 'Not detected. Please install, activate and configure <a target="_blank" href="https://wordpress.org/plugins/video-share-vod/">Video Share VOD</a>!';
				}

				?>

<h4>Video Post Type Name</h4>
<input name="custom_post_video" type="text" id="custom_post_video" size="16" maxlength="32" value="<?php echo esc_attr( $options['custom_post_video'] ); ?>"/>
<br>Should be same as Video Share VOD post type name. Ex: video


<h4>Enable Playlists</h4>
<select name="playlists" id="playlists">
  <option value="1" <?php echo $options['playlists'] ? 'selected' : ''; ?>>Yes</option>
  <option value="0" <?php echo $options['playlists'] ? '' : 'selected'; ?>>No</option>
</select>
<BR>Allows users to schedule playlists. Feature also needs to be enabled for channels owners from <a href='admin.php?page=live-streaming&tab=features'>Channel Features</a> : Playlist Scheduler .
<BR>This feature requires Wowza Streaming Engine and <a href="https://www.wowza.com/forums/content.php?145-How-to-schedule-streaming-with-Wowza-Streaming-Engine-(StreamPublisher)#installation">specific setup</a>: for VideoWhisper managed <a href="https://webrtchost.com/hosting-plans/#Complete-Hosting">hosting plans</a> and <a href="https://videowhisper.com/?p=Dedicated+Servers">servers</a> submit a support request for setting this up.

				<?php
				if ( $disablePlaylist = intval( $_GET['disablePlaylist'] ) ) {
					echo '<h4>Disabling Playlists</h4>';

					$roomPost = get_post( $disablePlaylist );
					if ( ! $roomPost ) {
						echo 'Not found: ' . esc_html( $disablePlaylist );
					} else {
						$stream = $roomPost->post_title;
						self::updatePlaylist( $stream, 0 );
						update_post_meta( $roomPost->ID, 'vw_playlistUpdated', time() );
						update_post_meta( $roomPost->ID, 'vw_playlistActive', '0' );

						echo 'Room: ' . esc_html( $roomPost->post_title ) . ' Performer Stream: ' . esc_html( $stream );
					}
				}
				?>


<h4>Streams Path</h4>
<input name="streamsPath" type="text" id="streamsPath" size="100" maxlength="256" value="<?php echo esc_attr( $options['streamsPath'] ); ?>"/>
<BR>Used for .smil playlists (should be same as streams path configured in VideoShareVOD for RTMP delivery).
<BR> 
				<?php
				echo esc_attr( $options['streamsPath'] ) . ' : ';
				if ( file_exists( $options['streamsPath'] ) ) {
					echo 'Found. ';
					if ( is_writable( $options['streamsPath'] ) ) {
						echo 'Writable. (OK)';
					} else {
						echo 'NOT writable.';
					}
				} else {
					echo '<b>NOT found!</b>';
				}

				// update when saving
				if ( isset( $_POST['playlists'] ) ) {
					echo '<BR><BR>SMIL updated on settings save.';
					self::updatePlaylistSMIL();
				}

				$streamsPath = self::fixPath( $options['streamsPath'] );
				$smilPath    = $streamsPath . 'playlist.smil';

				if ( file_exists( $smilPath ) ) {
					echo '<br><br>Playlist found: ' . esc_html( $smilPath );
					$smil = file_get_contents( $smilPath );
					echo '<br><textarea readonly cols="100" rows="10">' . esc_textarea( htmlentities( $smil ) )  . '</textarea>';
				}

				?>
<h4>Active Playlists</h4>
Currently scheduled playlists:
				<?php
				// query
				$args = array(
					'post_type'  => $options['custom_post'],
					'orderby'    => 'post_date',
					'order'      => 'DESC',
					'meta_key'   => 'vw_playlistActive',
					'meta_value' => '1',
				);

				$posts = get_posts( $args );

				if ( is_array( $posts ) ) {
					if ( count( $posts ) ) {
						foreach ( $posts as $post ) {
							echo '<br> - ' . esc_html( $post->post_title ) . ' <a href="admin.php?page=live-streaming&tab=playlists&disablePlaylist=' . intval( $post->ID ) . '">Disable</a>';
						}
					} else {
						echo 'No active playlists scheduled.';
					}
				}

				break;

			case 'support':
				// ! Support

				self::requirementMet( 'resources' );

				?>
<h3>Support Resources</h3>
This section contains links to multiple support resources, including hosting requirements, software documentation, developer contact, addon plugin suggestions.

<p><a href="https://videowhisper.com/tickets_submit.php" class="button primary" >Contact VideoWhisper</a></p>


<h3>Hosting Requirements</h3>
<UL>
<LI><a href="https://videowhisper.com/?p=Requirements">Hosting Requirements</a> This advanced software requires web hosting and streaming hosting.</LI>
<LI><a href="admin.php?page=live-streaming&tab=setup">Setup & Requirements Overview</a> Local setup overview.</LI>
<LI><a href="admin.php?page=live-streaming&tab=troubleshooting">Requirements Troubleshooting</a> Local troubleshooting.</LI>
</UL>
<h3>Software Documentation</h3>
<UL>
<LI><a href="admin.php?page=live-streaming-docs">Backend Documentation</a> Local backend page, includes tutorial with local links to configure main features, menus, pages.</LI>
<LI><a href="http://broadcastlivevideo.com/setup-tutorial/">BroadcastLiveVideo Tutorial</a> Setup a turnkey live video broadcasting site.</LI>
<LI><a href="https://videowhisper.com/?p=wordpress+live+streaming">VideoWhisper Plugin Homepage</a> Plugin and application documentation.</LI>
</UL>

<a name="plugins"></a>

<h3>Available Integrations and Recommended Plugins</h3>
<ul>
<li><a href="https://wordpress.org/plugins/video-share-vod/" title="Video Share / Video On Demand">Video Share VOD</a> plugin, integrated for video archive support, publishing HTML5 videos. For more details see <a href="https://videosharevod.com" title="Video Share / Video On Demand">Video Share VOD</a> turnkey solution homepage.</li>
<li> <a href="https://wordpress.org/plugins/rate-star-review/" title="Rate Star Review - AJAX Reviews for Content with Star Ratings">Rate Star Review – AJAX Reviews for Content with Star Ratings</a> plugin, integrated for channel reviews and ratings.</li>
<li><a href="https://wordpress.org/plugins/paid-membership/" title="Paid Membership">Paid Membership & Content</a> plugin, for managing membership with tokens, control access to pages by membership, selling content.</li>
<li><a href="https://wordpress.org/plugins/mycred/">myCRED</a> and/or <a href="https://wordpress.org/plugins/woo-wallet/">WooCommerce TeraWallet</a>, integrated for tips.  Configure as described in Tips settings tab.</li>
<li><a href="https://wordpress.org/plugins/wp-super-cache/">WP Super Cache</a> (configured to not cache for known users or GET parameters, great for protecting against bot or crawlers eating up site resources)</li>
<li><a href="https://wordpress.org/plugins/wordfence/">WordFence</a> plugin with firewall. Configure to protect by limiting failed login attempts, bot attacks / flood request, scan for malware or vulnerabilities.</li>
<li>HTTPS redirection plugin like <a href="https://wordpress.org/plugins/really-simple-ssl/">Really Simple SSL</a>&nbsp;, if you have a SSL certificate and HTTPS configured (as on VideoWhisper plans). HTTPS is required to broadcast webcam, in latest browsers like Chrome. If you also use HTTP urls (not recommended), disable “Auto replace mixed content” option to avoid breaking external HTTP urls (like HLS).</li>
<li>A SMTP mailing plugin like <a href="https://wordpress.org/plugins/easy-wp-smtp/">Easy WP SMTP</a> and setup a real email account from your hosting backend (setup an email from CPanel) or external (Gmail or other provider), to send emails using SSL and all verifications. This should reduce incidents where users don’t find registration emails due to spam filter triggering. Also instruct users to check their spam folders if they don’t find registration emails. To prevent spam, an <a href="https://wordpress.org/plugins/search/user-verification/">user verification plugin</a> can be used.</li>
	<li>For basic search engine indexing, make sure your site does not discourage search engine bots from Settings &gt; Reading   (discourage search bots box should not be checked).
Then install a plugin like <a href="https://wordpress.org/plugins/google-sitemap-generator/">Google XML Sitemaps</a> for search engines to quickly find main site pages.</li>
	 <li>For sites with adult content, an <a href="https://wordpress.org/plugins/tags/age-verification/">age verification / confirmation plugin</a> should be deployed. Such sites should also include a page with details for 18 U.S.C. 2257 compliance. For other suggestions related to adult sites, see <a href="https://paidvideochat.com/adult-videochat-business-setup/">Adult Videochat Business Setup</a>.</li>
<li><a href="https://updraftplus.com/?afref=924">Updraft Plus</a> – Automated WordPress backup plugin. Free for local storage.

<h3>Premium Plugins / Addons</h3>
<ul>
	<LI><a href="http://themeforest.net/popular_item/by_category?category=wordpress&ref=videowhisper">Premium Themes</a> Professional WordPress themes.</LI>
	<LI><a href="https://woocommerce.com/?aff=18336&cid=1980980">WooCommerce</a> Free shopping cart plugin, supports multiple free and premium gateways with TeraWallet/WooWallet plugin and various premium eCommerce plugins.</LI>

	<LI><a href="https://woocommerce.com/products/woocommerce-memberships/?aff=18336&cid=1980980">WooCommerce Memberships</a> Setup paid membership as products. Leveraged with Subscriptions plugin allows membership subscriptions.</LI>

	<LI><a href="https://woocommerce.com/products/woocommerce-subscriptions/?aff=18336&cid=1980980">WooCommerce Subscriptions</a> Setup subscription products, content. Leverages Membership plugin to setup membership subscriptions.</LI>

	<LI><a href="https://woocommerce.com/products/woocommerce-bookings/?aff=18336&cid=1980980">WooCommerce Bookings</a> Let your customers book reservations, appointments on their own.</LI>

	<LI><a href="https://woocommerce.com/products/follow-up-emails/?aff=18336&cid=1980980">WooCommerce Follow Up</a> Follow Up by emails and twitter automatically, drip campaigns.</LI>

	<LI><a href="https://updraftplus.com/?afref=924">Updraft Plus</a> Automated WordPress backup plugin. Free for local storage. For production sites external backups are recommended (premium).</LI>
</ul>


<h3>Contact and Feedback</h3>
<a href="https://consult.videowhisper.com">Open a conversation</a> with your questions, inquiries and VideoWhisper support staff will try to address these as soon as possible.

<h3>Review and Discuss</h3>
You can publicly <a href="https://wordpress.org/support/view/plugin-reviews/videowhisper-live-streaming-integration">review this WP plugin</a> on the official WordPress site (after <a href="https://wordpress.org/support/register.php">registering</a>). You can describe how you use it and mention your site for visibility. You can also post on the <a href="https://wordpress.org/support/plugin/videowhisper-live-streaming-integration">WP support forums</a> - these are not monitored by support so use a <a href="https://consult.videowhisper.com">conversation</a> if you want to contact VideoWhisper.

				<?php
				break;
			case 'appearance':
				$options['customCSS'] = stripslashes( $options['customCSS'] ) ;
				$options['cssCode']   = stripslashes( $options['cssCode'] ) ;

				self::requirementMet( 'appearance' );
				?>
<h3>Appearance</h3>
Customize appearance, styling, listings.

<h4>Registration and Login Logo</h4>
<input name="loginLogo" type="text" id="loginLogo" size="100" maxlength="200" value="<?php echo esc_url( $options['loginLogo'] ); ?>"/>
<br>Logo image to show on registration & login form, replacing default WordPress logo for a turnkey site. Leave blank to disable. Recommended size: 200x68.
				<?php echo esc_url( $options['loginLogo'] ) ? "<BR><img src='" . esc_url( $options['loginLogo'] ) . "'>" : ''; ?>

<h4>Interface Class(es)</h4>
<input name="interfaceClass" type="text" id="interfaceClass" size="30" maxlength="128" value="<?php echo esc_attr( $options['interfaceClass'] ); ?>"/>
<br>Extra class to apply to interface (using Semantic UI). Use inverted when theme uses a dark mode (a dark background with white text) or for contrast. Ex: inverted
<br>Some common Semantic UI classes: inverted = dark mode or contrast, basic = no formatting, secondary/tertiary = greys, red/orange/yellow/olive/green/teal/blue/violet/purple/pink/brown/grey/black = colors . Multiple classes can be combined, divided by space. Ex: inverted, basic pink, secondary green, secondary basic


<h4>Custom CSS</h4>
<textarea name="customCSS" id="customCSS" cols="100" rows="5"><?php echo esc_textarea( $options['customCSS'] ); ?></textarea>
<BR>Used in elements added by this plugin. 
Default:<br><textarea readonly cols="100" rows="4"><?php echo esc_textarea( $optionsDefault['customCSS'] ); ?></textarea>

<h4>Channel Thumb Width</h4>
<input name="thumbWidth" type="text" id="thumbWidth" size="4" maxlength="4" value="<?php echo intval( $options['thumbWidth'] ); ?>"/>px

<h4>Channel Thumb Height</h4>
<input name="thumbHeight" type="text" id="thumbHeight" size="4" maxlength="4" value="<?php echo intval( $options['thumbHeight'] ); ?>"/>px
<BR><a href="admin.php?page=live-streaming&tab=stats&regenerateThumbs=1">Regenerate Thumbs</a>

<h4>Default Channels Per Page</h4>
<input name="perPage" type="text" id="perPage" size="3" maxlength="3" value="<?php echo esc_attr( $options['perPage'] ); ?>"/>
<br>You can configure more options on listing page with shortcode parameters as <a href="admin.php?page=live-streaming-docs">documented</a>.

				<?php submit_button(); ?>

<p> + <strong>Theme</strong>: Get a <a href="http://themeforest.net/popular_item/by_category?category=wordpress&amp;ref=videowhisper">professional WordPress theme</a> to skin site, change design.<br>
A theme with wide content area (preferably full page width) should be used so videochat interface can use most of the space.<br>
Also plugin hooks into WP registration to implement a role selector: a theme that manages registration in a different custom page should be compatible with WP hooks to show the role option, unless you manage roles in a different way.<br>
Tutorial: <a href="https://en.support.wordpress.com/themes/uploading-setting-up-custom-themes/">Upload and Setup Custom WP Theme</a><br>
Sample themes: <a href="http://themeforest.net/item/jupiter-multipurpose-responsive-theme/5177775?ref=videowhisper">Jupiter</a>, <a href="http://themeforest.net/item/impreza-retina-responsive-wordpress-theme/6434280?ref=videowhisper">Impreza</a>, <a href="http://themeforest.net/item/elision-retina-multipurpose-wordpress-theme/6382990?ref=videowhisper">Elision</a>, <a href="http://themeforest.net/item/sweet-date-more-than-a-wordpress-dating-theme/4994573?ref=videowhisper">Sweet Date 4U</a>, <a href="https://themeforest.net/item/aeroland-responsive-app-landing-and-website-wordpress-theme/23314522?ref=videowhisper">AeroLand </a>. Most premium themes should work fine, these are just some we deployed in some projects.</p>

<p> + <strong>Logo</strong>: You can start from a <a href="http://graphicriver.net/search?utf8=%E2%9C%93&amp;order_by=sales&amp;term=video&amp;page=1&amp;category=logo-templates&amp;ref=videowhisper">professional logo template</a>. Logos can be configured from plugin settings, Integration tab and by default load from images in own installation.</p>

<p> + <strong>Design/Interface adjustments</strong>:
After selecting a theme to start from, that can be customized by a web designer experienced with WP themes. A WP designer can also create a custom theme (that meets WP coding requirements and standards).
Solution specific CSS (like for listings and user dashboards) can be edited in plugin backend.
Content on videochat page is generated by shortcodes from multiple plugins: videochat, profile fields, videos, pictures, ratings. There are multiple settings and CSS. Shortcodes are documented in plugin backend and can be added to pages, posts, templates.
HTML5 interface elements can customized by extra CSS. A lot of core styling is done with Semantic UI.
VideoWhisper developers can add additional options, settings to ease up customizations, for additional fees depending on exact customization requirements.
</p>
				<?php
				break;
			case 'general':
				$broadcast_url = admin_url() . 'admin-ajax.php?action=vwls_broadcast&n=';
				$root_url      = get_bloginfo( 'url' ) . '/';

				$current_user = wp_get_current_user();
				$userName     = $options['userName'];
				if ( ! $userName ) {
					$userName = 'user_nicename';
				}

				if ( $current_user->$userName ) {
					$username = $current_user->$userName;
				}
				$username = sanitize_file_name( $username );

				$options['translationCode'] = htmlentities( stripslashes( $options['translationCode'] ) );
				$options['adsCode']         = htmlentities( stripslashes( $options['adsCode'] ) );

				$current_user = wp_get_current_user();

				?>
<h3>General Integration Settings</h3>
Settings for integration with WordPress framework and other plugins, services.

<h4>Channel Category Mode</h4>
<select name="subcategory" id="subcategory">
  <option value="all" <?php echo $options['subcategory'] == 'all' ? 'selected' : ''; ?>>2 Selectors: All</option>
  <option value="subcategories" <?php echo $options['subcategory'] == 'subcategories' ? 'selected' : ''; ?>>2 Selectors: Only Subcategories</option>  
  <option value="wordpress" <?php echo $options['subcategory'] == 'WordPress' ? 'selected' : ''; ?>>1 Selector: All</option>
</select>
<br>Enable only subcategories to disable channels from being assigned to main categories. There must be categories with subcategories defined. 
Using 2 selectors allows users to select main category and then subcategory in 2 steps.

<h4>Username</h4>
<select name="userName" id="userName">
  <option value="display_name" <?php echo $options['userName'] == 'display_name' ? 'selected' : ''; ?>>Display Name (<?php echo esc_html( $current_user->display_name ); ?>)</option>
  <option value="user_login" <?php echo $options['userName'] == 'user_login' ? 'selected' : ''; ?>>Login (<?php echo esc_html( $current_user->user_login ); ?>)</option>
  <option value="user_nicename" <?php echo $options['userName'] == 'user_nicename' ? 'selected' : ''; ?>>Nicename (<?php echo esc_html( $current_user->user_nicename ); ?>)</option>
  <option value="ID" <?php echo $options['userName'] == 'ID' ? 'selected' : ''; ?>>ID (<?php echo intval( $current_user->ID ); ?>)</option>
</select>
<br>Your username with current settings:
				<?php
				$userName = $options['userName'];
				if ( ! $userName ) {
					$userName = 'user_nicename';
				}
				echo esc_html( $username = $current_user->$userName );

				?>

<h4>User Profile Link</h4>
<input name="profilePrefix" type="text" id="profilePrefix" size="100" maxlength="200" value="<?php echo esc_attr( $options['profilePrefix'] ); ?>"/>
<BR>Specify a url prefix for listing user profile.
 Default: <?php echo esc_html( $optionsDefault['profilePrefix'] ); ?>

<h4>Channel Profile Link</h4>
<input name="profilePrefixChannel" type="text" id="profilePrefixChannel" size="100" maxlength="200" value="<?php echo esc_attr( $options['profilePrefixChannel'] ); ?>"/>
<BR>Specify a url prefix for listing channel profile (a broadcaster can have multiple channels). If blank will link to default channel page.
 Default: <?php echo esc_html( $optionsDefault['profilePrefixChannel'] ); ?>

<h4>User Picture</h4>
<select name="userPicture" id="userPicture">
  <option value="0" <?php echo ! $options['userPicture'] ? 'selected' : ''; ?>>Disabled</option>
  <option value="avatar" <?php echo $options['userPicture'] == 'avatar' ? 'selected' : ''; ?>>WordPress Avatar</option>
   <option value="avatar_broadcaster" <?php echo $options['userPicture'] == 'avatar_broadcaster' ? 'selected' : ''; ?>>WP Avatar Broadcaster Only</option>
</select>
<BR>In advanced app broadcaster will have channel thumbnail (snapshot) as avatar. WP Avatar Broadcaster only shows broadcaster avatar in HTML chat and no avatars for viewers.

<br>Test: Your avatar as provided by get_avatar_url() WP function:
<br><IMG SRC="<?php echo get_avatar_url( get_current_user_id() ); ?>" />



<h4>Channel Page Layout URL</h4>
<select name="channelUrl" id="channelUrl">
  <option value="post" <?php echo $options['channelUrl'] == 'post' ? 'selected' : ''; ?>>Post (Theme)</option>
  <option value="full" <?php echo $options['channelUrl'] == 'full' ? 'selected' : ''; ?>>Full Page</option>
</select>
<br>URL where to show channels from listings (implemented in listings).

<h4>Post Channels</h4>
<select name="postChannels" id="postChannels">
  <option value="1" <?php echo $options['postChannels'] ? 'selected' : ''; ?>>Yes</option>
  <option value="0" <?php echo $options['postChannels'] ? '' : 'selected'; ?>>No</option>
</select>
<BR>Enables special post types (channels) and static urls for easy access to broadcast, watch and preview video.
<BR>This is required by other features like frontend channel management.
<BR><?php echo esc_url( $root_url ); ?>channel/chanel-name/broadcast
<BR><?php echo esc_url( $root_url ); ?>channel/chanel-name/
<BR><?php echo esc_url( $root_url ); ?>channel/chanel-name/video
<BR><?php echo esc_url( $root_url ); ?>channel/chanel-name/hls - Video must be transcoded to HLS format for iOS or published directly in such format with external encoder.
<BR><?php echo esc_url( $root_url ); ?>channel/chanel-name/external - Shows rtmp settings to use with external applications (if supported).

<h4>Post Template Filename</h4>
<input name="postTemplate" type="text" id="postTemplate" size="20" maxlength="64" value="<?php echo esc_attr( $options['postTemplate'] ); ?>"/>
<br>Template file located in current theme folder, that should be used to render channel post page. Ex: page.php, single.php
<br>
				<?php
				if ( $options['postTemplate'] != '+plugin' ) {
					$single_template = get_stylesheet_directory() . '/' . $options['postTemplate'];
					echo esc_html( $single_template ) . ' : ';
					if ( file_exists( $single_template ) ) {
						echo 'Found.';
					} else {
						echo 'Not Found! Use another theme file!';
					}
				}
				?>
<br>Set "+plugin" to use a template provided by this plugin, instead of theme templates.

<h4><a target="_plugin" href="https://wordpress.org/plugins/rate-star-review/">Rate Star Review</a> - Enable Star Reviews</h4>
				<?php
				if ( is_plugin_active( 'rate-star-review/rate-star-review.php' ) ) {
					echo 'Detected:  <a href="admin.php?page=rate-star-review">Configure</a>';
				} else {
					echo 'Not detected. Please install and activate Rate Star Review by VideoWhisper.com from <a href="plugin-install.php?s=videowhisper+rate+star+review&tab=search&type=term">Plugins > Add New</a>!';
				}
				?>
<BR><select name="rateStarReview" id="rateStarReview">
  <option value="0" <?php echo $options['rateStarReview'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['rateStarReview'] ? 'selected' : ''; ?>>Yes</option>
</select>
<br>Enables Rate Star Review integration. Shows star ratings on listings and review form, reviews on item pages.

<h4>Show VideoWhisper Powered by</h4>
<select name="videowhisper" id="videowhisper">
  <option value="0" <?php echo $options['videowhisper'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['videowhisper'] ? 'selected' : ''; ?>>Yes</option>
</select>

				<?php
				break;

			case 'webrtc':
				/*
				//?? profile-level-id=64C029

				"avc1.66.30": {profile:"Baseline", level:3.0, max_bit_rate:10000}
				//iOS friendly variation (iOS 3.0-3.1.2)
				"avc1.42001e": {profile:"Baseline", level:3.0, max_bit_rate:10000} ,
				"avc1.42001f": {profile:"Baseline", level:3.1, max_bit_rate:14000}
				//other variations ,
				"avc1.77.30": {profile:"Main", level:3.0, max_bit_rate:10000}
				//iOS friendly variation (iOS 3.0-3.1.2) ,
				"avc1.4d001e": {profile:"Main", level:3.0, max_bit_rate:10000} ,
				"avc1.4d001f": {profile:"Main", level:3.1, max_bit_rate:14000} ,
				"avc1.4d0028": {profile:"Main", level:4.0, max_bit_rate:20000} ,
				"avc1.64001f": {profile:"High", level:3.1, max_bit_rate:17500} ,
				"avc1.640028": {profile:"High", level:4.0, max_bit_rate:25000} ,
				"avc1.640029": {profile:"High", level:4.1, max_bit_rate:62500}
				*/
			//	$webrtcDisabled = self::requirementDisabled( 'wsURLWebRTC_configure' );
				?>
<h3>WebRTC</h3>
WebRTC live streaming requires configuring a specific server for signaling and relaying video streaming depending on network conditions.
WebRTC can be used to broadcast and playback live video streaming in HTML5 browsers with low latency. Latency can be under 1s depending on network conditions, compared to HLS from RTMP which can be have up to 10s latency because of delivery tehnology. WebRTC is recommended for low latency scenarios and easy broadcasting from browser, without downloanding and configuring broadcaster apps.

<br/>If you have a <a href="https://site2stream.com/html5/">turnkey</a> or <a href="https://webrtchost.com/hosting-plans/">streaming</a> plan (free or commercial) from VideoWhisper, go to <a href="admin.php?page=live-streaming&tab=import">Import Settings</a> to automatically fill streaming settings.

<h4>WebRTC Streaming Server</h4>
<select name="webrtcServer" id="webrtcServer">
<option value="wowza" <?php echo ( $options['webrtcServer'] == 'wowza' ) ? 'selected' : ''; ?>>Wowza Streaming Engine</option>
<option value="videowhisper" <?php echo ( $options['webrtcServer'] == 'videowhisper' ) ? 'selected' : ''; ?>>VideoWhisper WebRTC</option>
</select>
<br/>At least one of the specific servers is required to live stream with this solution: VideoWhisper P2P WebRTC (recommended) or Wowza SE WebRTC relay.
<br/>VideoWhisper WebRTC currently provides WebRTC signaling for P2P streaming and supports STUN/TURN for relaying.

<?php submit_button('Save & Show'); ?>
Save to show new settings after changing server type.

<?php 
if (in_array($options['webrtcServer'], array('videowhisper', 'auto'))) {
?>

<h3>VideoWhisper Server</h3>
The new <a href="https://github.com/videowhisper/videowhisper-webrtc">VideoWhisper WebRTC server</a> is a NodeJS based server that provides WebRTC signaling and can be used in combination with TURN/STUN servers. It's a great option for low latency P2P live streaming between 2 or few users but not recommended for 1 to many scenarios. In P2P streaming, broadcaster streams to each viewer which is optimal for latency, but requires a high speed connection and encoding power to handle all viewers. 
It's a new server that is still in development and is not yet recommended for production. Can be used for testing, development.
<br>Warning: When using P2P, server does not generate snapshots for channels. Broadcasters need to upload channel picture from channel setup to get listed.

<p>Get a <b>Free</b> Developers, or a paid account from <a href="https://webrtchost.com/hosting-plans/#WebRTC-Only">WebRTC Host: P2P</a>.</p>

<h4>Address / VideoWhisper WebRTC</h4>
<input name="vwsSocket" type="text" id="vwsSocket" size="100" maxlength="256" value="<?php echo esc_attr( $options['vwsSocket'] ); ?>"/>
<BR>VideoWhisper NodeJS server address. Formatted as wss://[socket-server]:[port] . Example: wss://videowhisper.yourwebsite.com:3000

<h4>Token / VideoWhisper WebRTC </h4>
<input name="vwsToken" type="text" id="vwsToken" size="100" maxlength="256" value="<?php echo esc_attr( $options['vwsToken'] ); ?>"/>
<BR>Token (account token) for VideoWhisper WebRTC server. 

<BR>
<?php
 //echo self::requirementRender( 'vwsSocket' ); 
}

if (in_array($options['webrtcServer'], array('wowza', 'auto'))) {
?>

<h3>Wowza Streaming Engine</h3>

<h4>Wowza SE WebRTC WebSocket URL</h4>
<input name="wsURLWebRTC" type="text" id="wsURLWebRTC" size="100" maxlength="256" value="<?php echo esc_attr( $options['wsURLWebRTC'] ); ?>"/>
<BR><?php 
//echo self::requirementRender( 'wsURLWebRTC_configure' ); 
?>
<BR>Relay WebRTC WebSocket URL (wss with SSL certificate). Formatted as wss://[server-with-ssl]:[port]/webrtc-session.json .
<BR>Requires latest Wowza Streaming Engine server configured for WebRTC support and with a SSL certificate. Such setup is available with <a href="https://webrtchost.com/hosting-plans/#Complete-Hosting" target="_vwhost">VideoWhisper Streaming Hosting</a>.

<h4>Wowza SE WebRTC Application</h4>
<input name="applicationWebRTC" type="text" id="applicationWebRTC" size="100" maxlength="256" value="<?php echo esc_attr( $options['applicationWebRTC'] ); ?>"/>
<BR>Relay Application Name (configured or WebRTC usage). Ex: videowhisper-webrtc
<BR>Server and application must match RTMP server settings, for streams to be available across protocols. Streams published with WebRTC can be played directly in HTML5 browsers.

<h4>Wowza SE RTSP Playback Address</h4>
<input name="rtsp_server" type="text" id="rtsp_server" size="100" maxlength="256" value="<?php echo esc_attr( $options['rtsp_server'] ); ?>"/>
<BR>For retrieving WebRTC streams. Ex: rtsp://[your-server]/videowhisper-x
<BR>Access WebRTC (RTSP) stream for snapshots, transcoding for RTMP/HLS/MPEGDASH playback.

<h4>Wowza SE RTSP Publish Address</h4>
<input name="rtsp_server_publish" type="text" id="rtsp_server_publish" size="100" maxlength="256" value="<?php echo esc_attr( $options['rtsp_server_publish'] ); ?>"/>
<BR>For publishing WebRTC streams. Usually requires publishing credentials (for Wowza configured in conf/publish.password). Ex: rtsp://[user:password@][your-server]/videowhisper-x

<h4>WebRTC</h4>
<select name="webrtc" id="webrtc">
<option value="1" <?php echo ( $options['webrtc'] ? 'selected' : '' ) ; ?>>Enabled</option>
<option value="0" <?php echo ( $options['webrtc'] ? '' : 'selected' ) ; ?>>Disabled</option>
</select>
<br>Legacy setting: WebRTC should always be Enabled for live broadcasting to work in latest HTML5 web browsers because other options like Flash plugins are no longer available.

<h4>Video Codec</h4>
<select name="webrtcVideoCodec" id="webrtcVideoCodec">
  <option value="42e01f" <?php echo $options['webrtcVideoCodec'] == '42e01f' ? 'selected' : ''; ?>>H.264 Profile 42e01f</option>
  <option value="VP8" <?php echo $options['webrtcVideoCodec'] == 'VP8' ? 'selected' : ''; ?>>VP8</option>
</select>
<br>Safari supports VP8 from version 12.1 for iOS & PC and H264 in older versions. Because Safari uses hardware encoding for H264, profile may not be suitable for playback without transcoding, depending on device: VP8 is recommended when broadcasting with latest Safari. H264 can also playback directly in HLS, MPEG, Flash without additional transcoding (only audio is transcoded). Using hardware encoding (when functional) involves lower device resource usage and longer battery life.

				<?php
						$sessionsVars = self::varLoad( $options['uploadsPath'] . '/sessionsApp' );
				if ( is_array( $sessionsVars ) ) {
					if ( array_key_exists( 'limitClientRateIn', $sessionsVars ) ) {
						$limitClientRateIn = intval( $sessionsVars['limitClientRateIn'] ) * 8 / 1000;

						echo 'Detected hosting client upload limit: ' . ( intval( $limitClientRateIn ) ? esc_html( $limitClientRateIn ) . 'kbps' : 'unlimited' ) . '<br>';

						$maxVideoBitrate = $limitClientRateIn - 100;
						if ( $options['webrtcAudioBitrate'] > 96 ) {
							$maxVideoBitrate = $limitClientRateIn - $options['webrtcAudioBitrate'] - 10;
						}

						if ( $limitClientRateIn ) {
							if ( $options['webrtcVideoBitrate'] > $maxVideoBitrate ) {
								echo '<b>Warning: Adjust bitrate to prevent disconnect / failure.<br>Video bitrate should be 100kbps lower than total upload so it fits with audio and data added. Save to apply!</b><br>';
								$options['webrtcVideoBitrate'] = $maxVideoBitrate;
							}
						}
					}
				}
				?>

<h4>Audio Codec</h4>
<select name="webrtcAudioCodec" id="webrtcAudioCodec">
  <option value="opus" <?php echo $options['webrtcAudioCodec'] == 'opus' ? 'selected' : ''; ?>>Opus</option>
  <option value="vorbis" <?php echo $options['webrtcAudioCodec'] == 'vorbis' ? 'selected' : ''; ?>>Vorbis</option>
</select>

<h4>Transcode streams to WebRTC</h4>
<select name="transcodeRTC" id="transcodeRTC">
  <option value="0" <?php echo $options['transcodeRTC'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['transcodeRTC'] == '1' ? 'selected' : ''; ?>>Yes</option>
</select>
<br>Make streams from other sources available for WebRTC playback. Involves processing resources (high CPU & memory load) and latency. Optimal way to deliver streams is WebRTC to WebRTC and RTMP to HLS, without transcoding. 

<h4>FFMPEG Transcoding Parameters for WebRTC Playback (H264 + Opus)</h4>
<input name="ffmpegTranscodeRTC" type="text" id="ffmpegTranscodeRTC" size="100" maxlength="256" value="<?php echo esc_attr( $options['ffmpegTranscodeRTC'] ); ?>"/>
<BR>This should convert RTMP stream to H264 baseline restricted video and Opus audio, compatible with most WebRTC supporting browsers.
<br>For most browsers including Chrome, Safari, Firefox: -c:v libx264 -profile:v baseline -level 3.0 -c:a libopus -tune zerolatency
<br>For some browsers like Chrome, Firefox, not Safari, when broadcasting H264 baseline from flash client video can play as is: -c:v copy -c:a libopus
<br>Default: <?php echo esc_html( $optionsDefault['ffmpegTranscodeRTC'] ); ?>

<h4>Transcode streams From WebRTC</h4>
<select name="transcodeFromRTC" id="transcodeFromRTC">
  <option value="0" <?php echo $options['transcodeFromRTC'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['transcodeFromRTC'] == '1' ? 'selected' : ''; ?>>Yes</option>
</select>
<br>Make streams from WebRTC available for HLS/MPEG/RTMP playback. Involves processing resources (high CPU & memory load). 
<br>Transcoding is required for archiving WebRTC streams (H264&AAC streams starting with "i_" can be imported).

<h4>WebRTC Implementation</h4>
WebRTC streaming is done trough media server, as relay, for reliability and scalability needed for these solutions.
Conventional out-of-the-box WebRTC solutions require each client to establish and maintain separate connections with every other participant in a complicated network where the bandwidth load increases exponentially as each additional participant is added. For P2P, streaming broadcasters need server grade connections to live stream to multiple users and using a regular home ADSL connection (that has has higher download and bigger upload) causes real issues. These solutions use the powerful streaming server as WebRTC node to overcome scalability and reliability limitations. Solution combines WebRTC HTML5 streaming with relay server streaming for a production ready setup.

<h4>Current Implementation Support and Limitations</h4>
As WebRTC is a new technology under development, implementation support varies depending on browsers and settings. These may change with solution development and technology improvements. Here is current status:
<UL>
	<LI>Chrome: Functional on Android and PC. Supports broadcast and playback. Stream broadcast with Chrome is available in most HTML5 browsers, including Safari as direct WebRTC.</LI>
	<LI>Firefox: Functional, supports broadcasting and playback over UDP. </LI>
	<LI>Other supported browsers: Brave, Tor.</LI>
	<LI>Safari: Functional on iOS. On PC, stream broadcast from Safari may be encoded with high profile setting so transcoding is required.<LI>
	<LI>Transcoding from WebRTC: Video and audio published with WebRTC is available in RTMP/HLS/MPEGDASH after transcoding, with some latency and availability delay.</LI>
	<LI>Transcoding from RTMP: Video and audio published with RTMP is available for WebRTC playback after transcoding, with some latency and availability delay.</LI>
</UL>
Implementation Limitations:
<UL>
	<LI>Advanced interactions specific to VideoWhisper Flash apps (like kick, tips) are not available, yet.</LI>
	<LI>Different chat system show messages with some external update delays between flash and html chat. Users list do not sync (external htmlchat users don't show in flash application).</LI>
</UL>

<?php
//end Wowza settings
}
?>
<h4>Maximum Video Bitrate</h4>
<input name="webrtcVideoBitrate" type="text" id="webrtcVideoBitrate" size="10" maxlength="16" value="<?php echo esc_attr( $options['webrtcVideoBitrate'] ); ?>"/>
<BR>Maximum video bitrate. Ex: 800. Max 400 if server is configured for restrictive TCP streaming instead of UDP.
<br>If streaming hosting upload is limited, video bitrate should be 100kbps lower than total upload so it fits with audio and data added. Trying to broadcast higher will result in disconnect/failure.

<h4>Audio Bitrate</h4>
<input name="webrtcAudioBitrate" type="text" id="webrtcAudioBitrate" size="10" maxlength="16" value="<?php echo esc_attr( $options['webrtcAudioBitrate'] ); ?>"/>

				<?php
				break;

			case 'hls':
				?>
<h3>Transcoding, HTML5, HLS, MPEG DASH</h3>
Configure transcoding and HTML5 based HLS, MPEG DASH delivery. HTTP Live Streaming is a great option for streaming to mobile browsers. Transcoding is required to convert between specific encoding formats required by HTML5 HLS, MPEG, WebRTC or Flash.
<BR>Special Requirements: This functionality requires FFMPEG with necessary codecs on web host and publishing trough Wowza Streaming Engine server to deliver transcoded streams as HLS.
<BR>Recommended Hosting: <a href="https://webrtchost.com/hosting-plans/#Complete-Hosting" target="_vwhost">VideoWhisper Complete Hosting</a> - turnkey rtmp address, configuration for archiving, transcoding streams, delivery to mobiles as HLS, playlists scheduler, IP cameras, advanced external encoder support.
<br>If you like this plugin and <a href="https://broadcastlivevideo.com/demo/">live demos</a>, you can <a href="https://wordpress.org/support/plugin/videowhisper-live-streaming-integration/reviews/#new-post">leave a review</a> and <a href="https://videowhisper.com/tickets_submit.php?topic=Complimentary+Review+Offer">contact</a> to get a complimentary offer for a trial hosting/turnkey month at half price. 

<br>Upgrade options: For a more advanced HTML5 interface, including bitrate measurements for broadcast/playback, video conferencing, 2 way video calls, pay per minute, video collaboration with presentation and file sharing, emotes and mentions, sound notifications, dark mode, see <a href="https://paidvideochat.com/html5-videochat/">PaidVideochat HTML5 Videochat</a>.


<h4>Clarifications on Transcoding</h4>
Flash and RTMP camera streaming applications are not supported in mobile browsers. Special solutions are required for mobile users to implement support for this type of features (<A href="https://videowhisper.com/?p=iPhone-iPad-Apps">read more</a>) including transcoding the streams to HTML5 formats.
<BR>Plain streaming is possible in mobile browser with HTML5 as HLS (HTTP Live Streaming), MPEG-DASH, WebRTC depending on browsers.
<BR>Broadcasting from mobile is possible with generic RTMP mobile encoders like Larix for iOS/Android that can be used to publish plain stream (no chat or interactions) and <a href="admin.php?page=live-streaming&tab=webrtc">HTML5 WebRTC</a>. Generic encoders require user to copy and paste rtmp address, channel name, settings and also Stream Session Control (included with recommended Wowza hosting) to show external published streams as active channels on site.

<h4>Server Command Execution</h4>
<select name="enable_exec" id="enable_exec">
  <option value="0" <?php echo $options['enable_exec'] ? '' : 'selected'; ?>>Disabled</option>
  <option value="1" <?php echo $options['enable_exec'] ? 'selected' : ''; ?>>Enabled</option>
</select>
<BR>By default, all features that require executing server commands are disabled, for security reasons. Enable only after making sure your server is configured to safely execute server commands like FFmpeg. If you have own server, isolation is recommended with <a href="https://docs.cloudlinux.com/cloudlinux_os_components/#cagefs">CageFS</a> or similar tools.

<?php
if ($options['enable_exec'])
{
?>

<h3>Detection: FFMPEG & Codecs</h3>
				<?php

				echo 'FFMPEG: ';
				// $cmd ='timeout -s KILL 3 ' . $options['ffmpegPath'] . ' -version';
				$cmd = $options['ffmpegPath'] . ' -version';

				$output = '';
				if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );
				if ( $returnvalue == 127 ) {
					echo wp_kses_post( "<b>Warning: not detected: $cmd</b>" );
					self::requirementUpdate( 'ffmpeg', 0 );
				} else {
					echo 'found';

					if ( $returnvalue != 126 ) {
						echo '<BR>' . esc_html( $output[0] );
						echo '<BR>' . esc_html( $output[1] );

						self::requirementUpdate( 'ffmpeg', 1 );
					} else {
						echo ' but is NOT executable by current user: ' . esc_html( $processUser );
						self::requirementUpdate( 'ffmpeg', 0 );
					}
				}

				?>
<BR><?php echo self::requirementRender( 'ffmpeg' ); ?>
				<?php
				$cmd = $options['ffmpegPath'] . ' -codecs';
				if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );

				// detect codecs
				$hlsAudioCodec = ''; // hlsAudioCodec
				if ( $output ) {
					if ( count( $output ) ) {
						echo '<br>Codec libraries:';
						foreach ( array( 'h264', 'vp6', 'speex', 'nellymoser', 'aacplus', 'vo_aacenc', 'faac', 'fdk_aac', 'vp8', 'vp9', 'opus' ) as $cod ) {
							$det  = 0;
							$outd = '';
							echo wp_kses_post( "<BR>$cod : " );
							foreach ( $output as $outp ) {
								if ( strstr( $outp, $cod ) ) {
									$det  = 1;
									$outd = $outp;
								}
							};

							if ( $det ) {
								echo esc_html( "detected ($outd)" );
							} elseif ( in_array( $cod, array( 'aacplus', 'vo_aacenc', 'faac', 'fdk_aac' ) ) ) {
								echo esc_html( "lib$cod is missing but other aac codec may be available" );
							} else {
								echo wp_kses_post( "<b>missing: configure and install FFMPEG with lib$cod if you don't have another library for that codec</b>" );
							}

							if ( $det && in_array( $cod, array( 'aacplus', 'vo_aacenc', 'faac', 'fdk_aac' ) ) ) {
								$hlsAudioCodec = 'lib' . $cod;
							}
						}
					}
				}
				?>
<BR>You need only 1 AAC codec for transcoding to AAC. Depending on <a href="https://trac.ffmpeg.org/wiki/Encode/AAC#libfaac">AAC library available on your system</a> you may need to update transcoding parameters. Latest FFMPEG also includes a native encoder (aac).


				<?php
				$ffmpegDisabled = self::requirementDisabled( 'ffmpeg' );
				if ( $ffmpegDisabled ) {
					$options['transcoding'] = 0;
				}
				?>

<h4>Enable HTML5 Transcoding</h4>
<select name="transcoding" id="transcoding" <?php echo esc_attr( $ffmpegDisabled ); ?>>
  <option value="0" <?php echo $options['transcoding'] ? '' : 'selected'; ?>>Disabled</option>
  <option value="1" <?php echo $options['transcoding'] == 1 ? 'selected' : ''; ?>>Enabled</option>
  <option value="2" <?php echo $options['transcoding'] == 2 ? 'selected' : ''; ?>>Available</option>
  <option value="3" <?php echo $options['transcoding'] == 3 ? 'selected' : ''; ?>>Adaptive</option>
  <option value="4" <?php echo $options['transcoding'] == 4 ? 'selected' : ''; ?>>Preferred</option>
</select>
<BR>This enables account level transcoding based on FFMPEG (if requirements are present). <BR>Transcoding is required for re-encoding live streams broadcast using web client to new re-encoded streams accessible by mobile HTML5 browsers using HLS / MPEG DASH. This requires high server processing power for each stream.
<BR>Transcoding is also required when converting streams between RTMP and WebRTC.
<BR>HTML5 HLS support is also required on RTMP server and  is available with <a href="https://webrtchost.com/hosting-plans/#Complete-Hosting">VideoWhisper HTML5 Streaming Hosting</a> .
<BR>Account level transcoding is not required when stream is already broadcast with external encoders in appropriate formats (H264, AAC with supported settings) or using Wowza Transcoder Addon (usually on dedicated servers).
<BR>HTML5 Playback: If transcoding is enabled will be played on mobiles (Auto). If Available will be also shown to PC users as option. Adaptive will try to show interface depending on source. If Preferred will be used instead of Flash, in Auto mode.


<h4>Live Transcoding</h4>
				<?php

				$processUser = get_current_user();
				$processUid  = getmyuid();

				echo wp_kses_post( "This section shows FFMPEG transcoding and snapshot retrieval processes currently run by account '$processUser' (#$processUid). Transcoding starts some time after stream is published for VideoWhisper web apps or when Stream Session Control is enabled.<BR>" );

				$cmd = "ps aux | grep 'ffmpeg'";
				if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );
				// var_dump($output);

				$transcoders = 0;
				foreach ( $output as $line ) {
					if ( strstr( $line, 'ffmpeg' ) ) {
						$columns = preg_split( '/\s+/', $line );
						if ( ( $processUser == $columns[0] || $processUid == $columns[0] ) && ( ! in_array( $columns[10], array( 'sh', 'grep' ) ) ) ) {

							echo esc_html( ' + Process #' . $columns[1] . ' CPU: ' . $columns[2] . ' Mem: ' . $columns[3] . ' Start: ' . $columns[8] . ' CPU Time: ' . $columns[9] . ' Cmd: ' );
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

				?>


<h4>FFMPEG Path</h4>
<input name="ffmpegPath" type="text" id="ffmpegPath" size="100" maxlength="256" value="<?php echo esc_attr( $options['ffmpegPath'] ); ?>"/>
<BR>Path to latest FFMPEG. Required for transcoding of web based streams, generating snapshots for external broadcasting applications (requires Stream session control to notify plugin about these streams).
Default: <?php echo esc_html( $optionsDefault['ffmpegPath'] ); ?>


<h4>FFMPEG Codec Configuration</h4>
<select name="ffmpegConfiguration" id="ffmpegConfiguration">
  <option value="0" <?php echo $options['ffmpegConfiguration'] ? '' : 'selected'; ?>>Manual</option>
  <option value="1" <?php echo $options['ffmpegConfiguration'] == 1 ? 'selected' : ''; ?>>Auto</option>
</select>
<BR>Auto will configure based on detected AAC codec libraries (recommended). Requires saving settings to apply.

				<?php
				$hlsAudioCodecReadOnly = '';

				if ( $options['ffmpegConfiguration'] ) {
					if ( ! $hlsAudioCodec ) {
						$hlsAudioCodec = 'aac';
					}
					$options['ffmpegTranscode'] = "-c:v copy -c:a $hlsAudioCodec -b:a 96k";

					if ( $options['webrtcVideoCodec'] != '42e01f' ) {
						$options['ffmpegTranscode'] = " -c:v libx264 -profile:v baseline -level 3.0 -c:a $hlsAudioCodec -b:a 96k -tune zerolatency";
						echo '<br>Warning: As WebRTC is not configured to use H264, video also needs to be transcoded. This requires high hosting processing resources which may result in slower site speed or failed requests. A hosting plan with high processing resources (CPU & memory) is required for video transcoding.';
					}

					$hlsAudioCodecReadOnly = 'readonly';
				}
				?>

<h4>FFMPEG Transcoding Parameters for HLS / MPEG-DASH / Flash Playback (H264 + AAC)</h4>
<input name="ffmpegTranscode" type="text" id="ffmpegTranscode" size="100" maxlength="256" value="<?php echo esc_attr( $options['ffmpegTranscode'] ); ?>" <?php echo esc_attr( $hlsAudioCodecReadOnly ); ?>/>
<BR>For lower server load and higher performance, web clients should be configured to broadcast video already suitable for target device (H.264 Baseline 3.1 for most iOS devices) so only audio needs to be encoded.

<BR>Ex.(transcode audio using latest FFMPEG with libfdk_aac): -c:v copy -c:a libfdk_aac -b:a 96k
<BR>Ex.(transcode audio using latest FFMPEG with native aac): -c:v copy -c:a aac -b:a 96k
<BR>Ex.(transcode video+audio in latest FFMPEG with libfdk_aac): -c:v libx264 -profile:v baseline -level 3.0 -c:a libfdk_aac -b:a 96k -tune zerolatency
<BR>Ex.(transcode audio using older FFMPEG with libfaac): -vcodec copy -acodec libfaac -ac 2 -ar 22050 -ab 96k
<BR>Ex.(transcode video+audio using older FFMPEG): -vcodec libx264 -s 480x360 -r 15 -vb 512k -x264opts vbv-maxrate=364:qpmin=4:ref=4 -coder 0 -bf 0 -analyzeduration 0 -level 3.1 -g 30 -maxrate 768k -acodec libfaac -ac 2 -ar 22050 -ab 96k
<BR>For advanced settings see <a href="https://developer.apple.com/library/ios/technotes/tn2224/_index.html#//apple_ref/doc/uid/DTS40009745-CH1-SETTINGSFILES">iOS HLS Supported Codecs<a> and <a href="https://trac.ffmpeg.org/wiki/Encode/AAC">FFMPEG AAC Encoding Guide</a>.

<h4>HTTP Streaming Base URL</h4>
This is used for accessing transcoded streams on HLS playback. Available with <a href="https://webrtchost.com/hosting-plans/#Complete-Hosting">VideoWhisper HTML5 Streaming Hosting</a> .<br>
<input name="httpstreamer" type="text" id="httpstreamer" size="100" maxlength="256" value="<?php echo esc_attr( $options['httpstreamer'] ); ?>"/>
<BR>External players and encoders (if enabled) are not monitored or controlled by this plugin, unless special Stream session control is available.
<BR>Application folder must match rtmp application (ex: videowhisper-x)
<BR>Ex: https://[your-server]:1935/videowhisper-x/ works when publishing to rtmp://[your-server]/videowhisper-x
<BR>HTTPS Recommended: Some browsers will require a SSL certificate for MPEG DASH / HLS streaming and show warnings/errors if using mixed or unsecure urls.

<h4>RTP Snapshots</H4>
<select name="rtpSnapshots" id="rtpSnapshots" >
  <option value="0" <?php echo $options['rtpSnapshots'] ? '' : 'selected'; ?>>Disabled</option>
  <option value="1" <?php echo $options['rtpSnapshots'] == 1 ? 'selected' : ''; ?>>Enabled</option>
</select>
<br/>Recommended: Disabled, as latest HTML5 Videochat app generates snapshots client side and and uploads to server. Extracting snapshots server side is only required for external streams, including from OBS, IP cameras, specific to Wowza SE.

<h4>Transcode streams to WebRTC</h4>
<select name="transcodeRTC" id="transcodeRTC">
  <option value="0" <?php echo $options['transcodeRTC'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['transcodeRTC'] == '1' ? 'selected' : ''; ?>>Yes</option>
</select>
<br>Make streams from other sources available for WebRTC playback. Involves processing resources (high CPU & memory load). 

				<?php
				$hlsAudioCodecReadOnly = '';

				if ( $options['ffmpegConfiguration'] ) {
					$options['ffmpegTranscodeRTC'] = '-c:v copy -c:a libopus';
						$hlsAudioCodecReadOnly     = 'readonly';

				}

				?>
<h4>FFMPEG Transcoding Parameters for WebRTC Playback (H264+Opus)</h4>
<input name="ffmpegTranscodeRTC" type="text" id="ffmpegTranscodeRTC" size="100" maxlength="256" value="<?php echo esc_attr( $options['ffmpegTranscodeRTC'] ); ?>" <?php echo esc_attr( $hlsAudioCodecReadOnly ); ?>/>
<BR>This should convert RTMP stream to H264 baseline restricted and Opus, compatible with most browsers. Video tracks encoded with -c:v libx264 -profile:v baseline -level 3.0 can be used as is on some browers. Default WebRTC profile for H264 is 42e01f.
<br>For most browsers including Chrome, Safari, Firefox: -c:v libx264 -profile:v baseline -level 3.0 -c:a libopus -tune zerolatency
<br>For some browsers like Chrome, Firefox, not Safari, when broadcasting H264 baseline from flash client: -c:v copy -c:a libopus
<br>Default: <?php echo esc_html( $optionsDefault['ffmpegTranscodeRTC'] ); ?>

<h4>Transcode streams From WebRTC</h4>
<select name="transcodeFromRTC" id="transcodeFromRTC">
  <option value="0" <?php echo $options['transcodeFromRTC'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['transcodeFromRTC'] == '1' ? 'selected' : ''; ?>>Yes</option>
</select>
<br>Make streams from WebRTC available for HLS/MPEG/RTMP playback. Involves processing resources (high CPU & memory load). 


<h4>Auto Transcoding</h4>
<select name="transcodingAuto" id="transcodingAuto">
  <option value="0" <?php echo $options['transcodingAuto'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['transcodingAuto'] == '1' ? 'selected' : ''; ?>>On Request</option>
  <option value="2" <?php echo $options['transcodingAuto'] == '2' ? 'selected' : ''; ?>>Always</option>
</select>
<BR>On Request starts transcoder when HLS / MPEG DASH is requested (by a mobile user) and Always when broadcast occurs. As HLS latency is usually several seconds, first viewer may not be able to access stream when using On Request.
<BR>Always will also check transcoding status from time to time (when broadcaster updates status). For external broadcasters (desktop/mobile), Stream Session Control is required to activate web transcoding.
<BR>Auto transcoding will work only if channel post <a href="admin.php?page=live-streaming&tab=features">Transcode Feature</a> is enabled.

<h4>Manual Transcoding</h4>
<select name="transcodingManual" id="transcodingManual">
  <option value="0" <?php echo $options['transcodingManual'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['transcodingManual'] == '1' ? 'selected' : ''; ?>>Yes</option>
</select>
<BR>Shows transcoding panel to broadcaster for manually toggling transcoding at runtime (for use when automated transcoding is disabled).


<h4>Transcoding Warning</h4>
<select name="transcodingWarning" id="transcodingWarning">
  <option value="0" <?php echo $options['transcodingWarning'] ? '' : 'selected'; ?>>Disabled</option>
  <option value="1" <?php echo $options['transcodingWarning'] == '1' ? 'selected' : ''; ?>>Broadcaster</option>
  <option value="2" <?php echo $options['transcodingWarning'] == '2' ? 'selected' : ''; ?>>Broadcaster and Viewers</option>
</select>
<BR>Warn users about latency and delay related to the extra operation of transcoding the stream and HLS delivery. Recommended while testing and for setups with multiple streaming options, for users to select optimal broadcast/delivery combination available (WebRTC, RTMP).

<h4>Transcode Re-Streams</h4>
<select name="transcodeReStreams" id="transcodeReStreams">
  <option value="0" <?php echo $options['transcodeReStreams'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['transcodeReStreams'] == '1' ? 'selected' : ''; ?>>Yes</option>
</select>
<br>Incoming streams should be encoded with H264 & AAC for playback without transcoding. Default: No

<h4>FFmpeg Recording</h4>
<select name="recordingFFmpeg" id="recordingFFmpeg">
  <option value="0" <?php echo $options['recordingFFmpeg'] ? '' : 'selected'; ?>>Disabled</option>
  <option value="1" <?php echo $options['recordingFFmpeg'] == 1 ? 'selected' : ''; ?>>Enabled</option>
</select>
<BR>Default FFmpeg recording settings. Use FFmpeg to record all streams if enabled.


<h4>MPEG-Dash Device Target</h4>
<select name="detect_mpeg" id="detect_mpeg">
  <option value="" <?php echo $options['detect_mpeg'] ? '' : 'selected'; ?>>None</option>
  <option value="android" <?php echo $options['detect_mpeg'] == 'android' ? 'selected' : ''; ?>>Android</option>
  <option value="nonsafari" <?php echo $options['detect_mpeg'] == 'nonsafari' ? 'selected' : ''; ?>>Except Safari</option>
  <option value="all" <?php echo $options['detect_mpeg'] == 'all' ? 'selected' : ''; ?>>Android & PC</option>
</select>
<BR>Show MPEG Dash for certain types of devices. Most browsers will require HTTPS.

<h4>HLS Device Target</h4>
<select name="detect_hls" id="detect_hls">
  <option value="" <?php echo $options['detect_hls'] ? '' : 'selected'; ?>>None</option>
  <option value="ios" <?php echo $options['detect_hls'] == 'ios' ? 'selected' : ''; ?>>iOS</option>
  <option value="mobile" <?php echo $options['detect_hls'] == 'mobile' ? 'selected' : ''; ?>>iOS & Android</option>
  <option value="safari" <?php echo $options['detect_hls'] == 'safari' ? 'selected' : ''; ?>>iOS & PC Safari</option>
  <option value="all" <?php echo $options['detect_hls'] == 'all' ? 'selected' : ''; ?>>Mobile & PC Safari</option>
</select>
<BR>Show HLS for certain types of devices. Does not overwrite MPEG Dash if enabled. Mobile covers iOS & Android.

<h4>FFMPEG RTMP Timeout</h4>
<input name="ffmpegTimeout" type="text" id="ffmpegTimeout" size="5" maxlength="20" value="<?php echo esc_attr( $options['ffmpegTimeout'] ); ?>"/>s
<BR>Disconnect quick ffmpeg connections for stream info or snapshots after this timeout. Implemented by Stream Session control.

<h4>FFMPEG Snapshot Background Command</h4>
<input name="ffmpegSnapshotBackground" type="text" id="ffmpegSnapshotBackground" size="20" maxlength="256" value="<?php echo esc_attr( $options['ffmpegSnapshotBackground'] ); ?>"/>
<br>Snapshot command background command. Leave blank to wait for completion (not send in background), which will result in script delay. Default: <?php echo esc_html( $optionsDefault['ffmpegSnapshotBackground'] ); ?>

<h4>FFMPEG Snapshot Timeout Command</h4>
<input name="ffmpegSnapshotTimeout" type="text" id="ffmpegSnapshotTimeout" size="20" maxlength="256" value="<?php echo esc_attr( $options['ffmpegSnapshotTimeout'] ); ?>"/>
<br>Snapshot command timeout command. Leave blank to remove timeout. Default: <?php echo esc_html( $optionsDefault['ffmpegSnapshotTimeout'] ); ?>
				<?php		
} // end $options['enable_exec']					
				break;

			/*
			case 'ipcamera':
			?>
			<h3>IP Camera / Re-Streaming Settings</h3>
			Configuring different streaming server settings is useful when you don't want to archive these streams.
			<?php
			break;
			*/

			case 'external':
				?>
<h3>External Encoder/App Settings</h3>
Users can broadcast using external RTMP encoding applications (<a href="https://obsproject.com">OBS Open Broadcaster Software</a>, <a href="https://itunes.apple.com/us/app/wowza-gocoder/id640338185?mt=8">GoCoder iOS</a>/<a href="https://play.google.com/store/apps/details?id=com.wowza.gocoder&hl=en">Android app</a>, XSplit, Adobe Flash Media Live Encoder, Wirecast).
<br>External players and encoders (if enabled) are not monitored or controlled by this plugin, unless special Stream session control is available.
 
 <h4>External Application Addresses</h4>
<select name="externalKeys" id="externalKeys">
  <option value="0" <?php echo $options['externalKeys'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['externalKeys'] ? 'selected' : ''; ?>>Yes</option>
</select>
<BR>Shows "External Apps" button for each channel in Broadcast Live section. Channel owners will receive access to their secret publishing and playback addresses for each channel.
<BR>Enables external application support by inserting authentication info (username, channel name, key for broadcasting/watching) directly in RTMP address. RTMP server will pass these parameters to webLogin scripts for direct authentication without website access. This feature requires special RTMP side support for managing these parameters.
<br>Advanced external app session control requires server side Stream Session Control setup.
 <BR><?php echo self::requirementRender( 'rtmp_status' ); ?>

 <h4>External Transcoder Keys</h4>
<select name="externalKeysTranscoder" id="externalKeysTranscoder">
  <option value="0" <?php echo $options['externalKeysTranscoder'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['externalKeysTranscoder'] ? 'selected' : ''; ?>>Yes</option>
</select>
<BR>Direct authentication parameters will be used for transcoder, external stream thumbnails in case webLogin is enabled. RTMP server will pass these parameters to webLogin scripts for direct authentication without website access. Without this FFMPEG requests would be denied by streaming server as unauthorized.

<h4>External Encoder Transcoding</h4>
<select name="transcodeExternal" id="transcodeExternal">
  <option value="0" <?php echo $options['transcodeExternal'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['transcodeExternal'] == '1' ? 'selected' : ''; ?>>Yes</option>
</select>
<BR>Only enable if external streams (from OBS, Wirecast, GoCoder) don't come encoded as H264 & AAC.
<br>Warning: Transcoding involves extra latency, extra delay for stream to become available in new version and high server processing load (cpu & memory).


				<?php
				break;

			case 'server':
				?>
<h3>Server Settings</h3>
Configure server settings for RTMP/HLS and web. 

<br>Streaming settings can be quickly imported (as provided for VideoWhisper setups):
<br><a href="admin.php?page=live-streaming&tab=import" class="button">Import Settings</a>

<BR>For a quick, hassle free and cost effective setup, start with <a href="https://site2stream.com/html5/">turnkey live streaming site plan</a> instead of setting up own live streaming servers.
<BR>Recommended Hosting: <a href="https://webrtchost.com/hosting-plans/" target="_vwhost">Complete Hosting with HTML5 Live Streaming</a> - All hosting requirements including HTML5 live streaming server services, SSL for site and streaming, specific server tools and configurations for advanced features.

<h4>RTMP/HLS Server Type</h4>
<select name="rtmpServer" id="rtmpServer">
  <option value="videowhisper" <?php echo $options['rtmpServer'] == 'videowhisper' ? 'selected' : ''; ?>>VideoWhisper</option>
  <option value="wowza" <?php echo $options['rtmpServer'] == 'wowza' ? 'selected' : ''; ?>>Wowza SE</option>
</select>
<br>Choose supported RTMP/HLS server type: VideoWhisper hosting or Wowza Streaming Engine.

<?php
submit_button('Save & Show');
?>
Save server type change to display specific settings.

<?php if ( $options['rtmpServer'] == 'videowhisper' ) { ?>

<h4>Account Name</h4>
<input name="vwsAccount" type="text" id="vwsAccount" size="32" maxlength="64" value="<?php echo esc_attr( $options['vwsAccount'] ); ?>"/>
<br>Account name is used in stream names.

<h4>Token for Account</h4>
<input name="vwsToken" type="text" id="vwsToken" size="32" maxlength="64" value="<?php echo esc_attr( $options['vwsToken'] ); ?>"/>

<h4>RTMP Server Address</h4>
<input name="videowhisperRTMP" type="text" id="videowhisperRTMP" size="100" maxlength="256" value="<?php echo esc_attr( $options['videowhisperRTMP'] ); ?>"/>*

<h4>HLS Server Address</h4>
<input name="videowhisperHLS" type="text" id="videowhisperHLS" size="100" maxlength="256" value="<?php echo esc_attr( $options['videowhisperHLS'] ); ?>"/>

<h4>Master Broadcast PIN</h4>
<input name="broadcastPin" type="text" id="broadcastPin" size="32" maxlength="64" value="<?php echo esc_attr( $options['broadcastPin'] ); ?>"/>
<br>Can be reset from VideoWhisper account.

<h4>Master Playback PIN</h4>
<input name="playbackPin" type="text" id="playbackPin" size="32" maxlength="64" value="<?php echo esc_attr( $options['playbackPin'] ); ?>"/>
<br>Can be reset from VideoWhisper account.

<h4>Stream Validation</h4>
<select name="videowhisperStream" id="videowhisperStream">
  <option value="0" <?php echo $options['videowhisperStream'] ? '' : 'selected'; ?>>Disabled</option>
  <option value="all" <?php echo $options['videowhisperStream'] == 'all' ? 'selected' : ''; ?>>All</option>
  <option value="broadcast" <?php echo $options['videowhisperStream'] == 'broadcast' ? 'selected' : ''; ?>>Only Broadcast</option>
</select>
<br>Pins can be generated per stream for validation instead of sharing, to prevent users from publishing/playing other streams using your account. Stream Validation involves configuring this Stream URL for the <a href="https://consult.videowhisper.com/my-accounts/">VideoWhisper account</a>:<br>
	<?php //display admin ajax url
			$admin_ajax = admin_url() . 'admin-ajax.php';
			$stream_url = htmlentities( $admin_ajax . '?action=vwls_stream' );
			echo $stream_url;
?>
<h4>Stream Notifications</h4>
Stream notifications are required to show streams (rooms) live on website when published with external RTMP econder. Involves configuring this Notification URL for the VideoWhisper account:<br>
<?php
			$stream_url = htmlentities( $admin_ajax . '?action=vwls_notify' );
			echo $stream_url;
}//end videowhisper settings

if ( $options['rtmpServer'] == 'wowza' ) { ?>


<h4>RTMP Address</h4>
<input name="rtmp_server" type="text" id="rtmp_server" size="100" maxlength="256" value="<?php echo esc_attr( $options['rtmp_server'] ); ?>"/>
<BR><?php 
//echo self::requirementRender( 'rtmp_server_configure' );
 ?>

<br>On VideoWhisper setups, server side Stream Session Control needs to be configured by streaming server administrator, <a href="https://videowhisper.com/tickets_submit.php?topic=Stream+Session+Control">on request</a>.

<h4>Streams Path (IP Camera Streams /  Playlists)</h4>
<input name="streamsPath" type="text" id="streamsPath" size="100" maxlength="256" value="<?php echo esc_attr( $options['streamsPath'] ); ?>"/>
<BR>Path to .stream files monitored by streaming server for restreaming.
<BR>Such functionality requires latest Wowza Streaming Engine, web and rtmp on same sever, <a href='https://www.wowza.com/forums/content.php?39-How-to-re-stream-video-from-an-IP-camera-(RTSP-RTP-re-streaming)#config_xml'>specific setup</a>. Streaming server loads configuration from web files, connects to IP camera stream or video file, loads stream and delivers in format suitable for web publishing.
<BR>This functionality is available with <a href="https://webrtchost.com/hosting-plans/#Complete-Hosting" target="_vwhost">VideoWhisper Complete Hosting plans</a> and servers, when hosting both web and rtmp on same plan/server so web scripts can access streaming configuration files.
If custom ports are used, server firewall must be configured to allow connections.
<BR>Can be same as streams path configured in VideoShareVOD.
<BR> 
				<?php
				echo esc_html( $options['streamsPath'] ) . ' : ';
				if ( file_exists( $options['streamsPath'] ) ) {
					echo 'Found. ';
					if ( is_writable( $options['streamsPath'] ) ) {
						echo 'Writable. (OK)';
					} else {
						echo 'NOT writable.';
					}
				} else {
					echo '<b>NOT found!</b>';
				}
				?>

<h4>Token Key</h4>
<input name="tokenKey" type="text" id="tokenKey" size="32" maxlength="64" value="<?php echo esc_attr( $options['tokenKey'] ); ?>"/>
<BR>A <a href="https://videowhisper.com/?p=RTMP+Applications#settings">secure token</a> can be used with Wowza Media Server.


<h4>Web Key, Web Login/Status, Session Control</h4>
<input name="webKey" type="text" id="webKey" size="32" maxlength="64" value="<?php echo esc_attr( $options['webKey'] ); ?>"/>
<BR>A web key can be used for Web Session Check.  Application.xml settings in &lt;Root&gt;&lt;Application&gt;&lt;Properties&gt; :<br>

<textarea readonly cols="100" rows="4">
<?php
				$admin_ajax = admin_url() . 'admin-ajax.php';
				$webLogin   = htmlentities( $admin_ajax . '?action=vwls&task=rtmp_login&s=' );
				$webLogout  = htmlentities( $admin_ajax . '?action=vwls&task=rtmp_logout&s=' );
				$webStatus  = htmlentities( $admin_ajax . '?action=vwls&task=rtmp_status' );

				echo esc_textarea(
					"<!-- VideoWhisper.com: RTMP Session Control https://videowhisper.com/?p=rtmp-session-control -->
<Property>
<Name>acceptPlayers</Name>
<Value>true</Value>
</Property>
<Property>
<Name>webLogin</Name>
<Value>$webLogin</Value>
</Property>
<Property>
<Name>webKey</Name>
<Value>" . $options['webKey'] . "</Value>
</Property>
<Property>
<Name>webLogout</Name>
<Value>$webLogout</Value>
</Property>
<Property>
<Name>webStatus</Name>
<Value>$webStatus</Value>
</Property>
				"
				)
				?>
</textarea>
<BR>Dedicated IP Based. HTTP, when using same server for web and streaming or CloudFlare/firewall that blocks requests:
<br><textarea readonly cols="100" rows="4">
<?php

				$admin_ajax = 'http://' . $_SERVER['SERVER_ADDR'] . str_replace( home_url(), '', admin_url() ) . 'admin-ajax.php';
				$webLogin   = htmlentities( $admin_ajax . '?action=vwls&task=rtmp_login&s=' );
				$webLogout  = htmlentities( $admin_ajax . '?action=vwls&task=rtmp_logout&s=' );
				$webStatus  = htmlentities( $admin_ajax . '?action=vwls&task=rtmp_status' );

				echo esc_textarea(
					"<!-- VideoWhisper.com: Stream Session Control  -->
<Property>
<Name>acceptPlayers</Name>
<Value>true</Value>
</Property>
<Property>
<Name>webLogin</Name>
<Value>$webLogin</Value>
</Property>
<Property>
<Name>webKey</Name>
<Value>" . $options['webKey'] . "</Value>
</Property>
<Property>
<Name>webLogout</Name>
<Value>$webLogout</Value>
</Property>
<Property>
<Name>webStatus</Name>
<Value>$webStatus</Value>
</Property>
			"
				)
				?>
</textarea>
<BR><?php 
//echo self::requirementRender( 'rtmp_status' ); 
?>

<BR>Session Control : webStatus will not work on 3rd party servers without this configured for RTMP side (channel online status will not update). Test if functional by monitoring if external broadcast remains LIVE and session control is detected in <a href="admin.php?page=live-streaming-stats">Statistics</a>.
<BR>Broadcaster can't connect at same time from web broadcasting interface and external encoder with session control (as session name will be rejected as duplicate).
<BR>Benefits of using Stream Session Control: advanced support for external encoders like OBS (shows channels as live on site, generates snapshots, usage stats, transcoding), protect rtmp address from external usage (broadcast and playback require the secret keys associated with active site channels), faster availability and updates for transcoding/snapshots.
<BR>Certain services or firewalls like Cloudflare will reject access of streaming server for web requests. Make sure configured web requests can be called by streaming server.
<br>Locked Streaming Settings: Session Control locks advanced features and security to a single installation. Other applications, plugins will not be able to use same streaming settings and will get rejected without the proper keys provided by this installation. Using multiple plugins / interfaces for similar features is confusing for users so it is recommended to use only one solution. A different solution can use a different live streaming setup or hosting plan.

<h4>Web Status, Session Control</h4>
<select name="webStatus" id="webStatus">
  <option value="auto" <?php echo $options['webStatus'] == 'auto' ? 'selected' : ''; ?>>Auto</option>
  <option value="enabled" <?php echo $options['webStatus'] == 'enabled' ? 'selected' : ''; ?>>Enabled</option>
  <option value="strict" <?php echo $options['webStatus'] == 'strict' ? 'selected' : ''; ?>>Strict</option>
  <option value="disabled" <?php echo $options['webStatus'] == 'disabled' ? 'selected' : ''; ?>>Disabled</option>
</select>
<BR>Auto will automatically enable first time webLogin successful authentication occurs for a broadcaster. Will also configure the server IP restriction.
<br>In Strict mode additional IPs can't be added by webLogin authorisation (not recommended as streaming server may have multiple IPs).
<br>Set Disabled to make sure WebRTC streams are displayed when session control does not work (otherwise it will show HLS teaser when offline). 

<h4>Web Status Server IP Restriction</h4>
<input name="rtmp_restrict_ip" type="text" id="rtmp_restrict_ip" size="100" maxlength="512" value="<?php echo esc_attr( $options['rtmp_restrict_ip'] ); ?>"/>
<BR>Allow status updates only from configured IP(s). If not defined will configure automatically when first successful webLogin authorisation occurs for a broadcaster. Web status will not work if this is empty or not configured right.
<BR>Some streaming servers use different IPs. All must be added as comma separated values.
				<?php

				if ( in_array( $options['webStatus'], array( 'enabled', 'strict', 'auto' ) ) ) {
					if ( file_exists( $path = $options['uploadsPath'] . '/_rtmpStatus.txt' ) ) {
						$url = self::path2url( $path );
						echo 'Found: <a target=_blank href="' . esc_url( $url ) . '">last status request</a> ' . date( 'D M j G:i:s T Y', filemtime( $path ) );
					}
				}
				?>

<?php

} //end wowza settings
?>

<h4>Uploads Path</h4>
<p>Path where logs and snapshots will be uploaded. Make sure you use a location outside plugin folder to avoid losing logs on updates and plugin uninstallation.</p>
<input name="uploadsPath" type="text" id="uploadsPath" size="80" maxlength="256" value="<?php echo esc_attr( $options['uploadsPath'] ); ?>"/>
				<?php
				if ( ! file_exists( $options['uploadsPath'] ) ) {
					echo '<br><b>Warning: Folder does not exist. If this warning persists after first access check path permissions:</b> ' . esc_html( $options['uploadsPath'] );
				}
				if ( ! strstr( $options['uploadsPath'], get_home_path() ) ) {
					echo '<br><b>Warning: Uploaded files may not be accessible by web (path is not within WP installation path).</b>';
				}

				echo '<br>WordPress Path: ' . get_home_path();
				echo '<br>WordPress URL: ' . get_site_url();
				?>
<br>wp_upload_dir()['basedir'] : 
				<?php
				$wud = wp_upload_dir();
				echo esc_html( $wud['basedir'] );
				?>
<br>$_SERVER['DOCUMENT_ROOT'] : <?php echo esc_html( $_SERVER['DOCUMENT_ROOT'] ); ?>

<h4>Show Channel Watch when Offline</h4>
<p>Display channel watch interface even if channel is not detected as broadcasting.</p>
<select name="alwaysWatch" id="alwaysWatch">
  <option value="0" <?php echo $options['alwaysWatch'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['alwaysWatch'] ? 'selected' : ''; ?>>Yes</option>
</select>
<br>Useful when broadcasting with external apps and Server side Stream session control is not available. Also set Session Control: Disabled if that does not work, to show WebRTC player even when not detected as online. 
<br>Watch interface always shows for channels that stream from IP cameras or playlists (not affected by this setting).
<BR>Warning: Enabling this disables event details, that show on channel page while channel is offline. Disabling this, requires broadcast to be started before viewers come to page.
			
			
<h4>Server Command Execution</h4>
<select name="enable_exec" id="enable_exec">
  <option value="0" <?php echo $options['enable_exec'] ? '' : 'selected'; ?>>Disabled</option>
  <option value="1" <?php echo $options['enable_exec'] ? 'selected' : ''; ?>>Enabled</option>
</select>
<BR>By default, all features that require executing server commands are disabled, for security reasons. Enable only after making sure your server is configured to safely execute server commands like FFmpeg. If you have own server, isolation is recommended with <a href="https://docs.cloudlinux.com/cloudlinux_os_components/#cagefs">CageFS</a> or similar tools.			
				<?php
				break;

			case 'broadcaster':
				$options['parametersBroadcaster'] = htmlentities( stripslashes( $options['parametersBroadcaster'] ) );
				$options['layoutCodeBroadcaster'] = htmlentities( stripslashes( $options['layoutCodeBroadcaster'] ) );

				?>
<h3>Video Broadcasting</h3>
Options for video broadcasting.
<h4>Who can broadcast video channels</h4>
<select name="canBroadcast" id="canBroadcast">
  <option value="members" <?php echo $options['canBroadcast'] == 'members' ? 'selected' : ''; ?>>All Members</option>
  <option value="list" <?php echo $options['canBroadcast'] == 'list' ? 'selected' : ''; ?>>Members in List</option>
</select>
<br>These users will be able to use broadcasting interface for managing channels (Broadcast Live) and have access to rtmp address keys for using external applications, if enabled.

<h4>Members allowed to broadcast video (comma separated user names, roles, emails, IDs)</h4>
<textarea name="broadcastList" cols="64" rows="4" id="broadcastList"><?php echo esc_textarea( $options['broadcastList'] ); ?>
</textarea>


<h4>Maximum Number of Broadcasting Channels (per User)</h4>
<input name="maxChannels" type="text" id="maxChannels" size="2" maxlength="4" value="<?php echo esc_attr( $options['maxChannels'] ); ?>"/>
<BR>Maximum channels users are allowed to create from frontend if channel posts are enabled.

<h4>Maximum Broadcating Time (0 = unlimited)</h4>
<input name="broadcastTime" type="text" id="broadcastTime" size="7" maxlength="7" value="<?php echo esc_attr( $options['broadcastTime'] ); ?>"/> (minutes/period)

<h4>Maximum Channel Watch Time (total cumulated view time, 0 = unlimited)</h4>
<input name="watchTime" type="text" id="watchTime" size="10" maxlength="10" value="<?php echo esc_attr( $options['watchTime'] ); ?>"/> (minutes/period)

<h4>Usage Period Reset (0 = never)</h4>
<input name="timeReset" type="text" id="timeReset" size="4" maxlength="4" value="<?php echo esc_attr( $options['timeReset'] ); ?>"/> (days)

<h4>Banned Words in Names</h4>
<textarea name="bannedNames" cols="64" rows="4" id="bannedNames"><?php echo esc_attr( $options['bannedNames'] ); ?>
</textarea>
<br>Users trying to broadcast channels using these words will be disconnected.


<h4>Redirect broadcaster from own channel page</h4>
<select name="broadcasterRedirect" id="broadcasterRedirect">
  <option value="0" <?php echo $options['broadcasterRedirect'] ? '' : 'selected'; ?>>No</option>
  <option value="dashboard" <?php echo $options['broadcasterRedirect'] == 'dashboard' ? 'selected' : ''; ?>>Broadcast Live Dashboard</option>
  <option value="broadcast" <?php echo $options['broadcasterRedirect'] == 'broadcast' ? 'selected' : ''; ?>>Broadcast Channel</option>
</select>
<BR>Redirect broadcaster when accessing own channel page to dashboard or broadcasting interface instead of watch/video interface. Does not redirect when accessing specific interfaces with parameter like hls, mpeg, webrtc.

				<?php
				break;

			// ! Premium channels
			case 'premium':
				?>
<h3>Premium Membership Levels and Channels</h3>
Options for membership levels and premium channels. Premium channels can have higher usage limitations, special settings and features that can be defined here.
Use in combination with <a href='admin.php?page=live-streaming&tab=features'>Channel Features</a> to define specific capabilities depending on role.

<h4>Number of Premium Levels</h4>
<input name="premiumLevelsNumber" type="text" id="premiumLevelsNumber" size="7" maxlength="7" value="<?php echo esc_attr( $options['premiumLevelsNumber'] ); ?>"/>
<br>Number of premium membership levels.

				<?php

				$premiumLev = unserialize( $options['premiumLevels'] );

				for ( $i = 0; $i < $options['premiumLevelsNumber']; $i++ ) {

					$premiumLev[ $i ]['level'] = $i + 1;

					foreach ( array( 'premiumList', 'canWatchPremium', 'watchListPremium', 'pBroadcastTime', 'pWatchTime', 'pCamBandwidth', 'pCamMaxBandwidth', 'pMaxChannels' ) as $varName ) {
						if ( isset( $_POST[ $varName . $i ] ) ) {
							$premiumLev[ $i ][ $varName ] = sanitize_textarea_field( $_POST[ $varName . $i ] );
						}
						if ( ! isset( $premiumLev[ $i ][ $varName ] ) && isset( $options[ $varName ] )) {
							$premiumLev[ $i ][ $varName ] = $options[ $varName ] ; // default from options
						}
					}
					?>

<h3>Premium Level <?php echo intval( $i + 1 ); ?></h3>

<h4>Members that broadcast premium channels (Premium members: comma separated user names, roles, emails, IDs)</h4>
<textarea name="premiumList<?php echo intval( $i ); ?>" cols="64" rows="4" id="premiumList<?php echo intval( $i ); ?>"><?php echo esc_textarea( $premiumLev[ $i ]['premiumList'] ); ?>
</textarea>
<br>Highest level match is selected.
<br>Warning: Certain plugins may implement roles that have a different label than role name. Ex: s2member_level1

<h4>Who can watch premium channels</h4>
<select name="canWatchPremium<?php echo intval( $i ); ?>" id="canWatchPremium<?php echo intval( $i ); ?>">
  <option value="all" <?php echo $premiumLev[ $i ]['canWatchPremium'] == 'all' ? 'selected' : ''; ?>>Anybody</option>
  <option value="members" <?php echo $premiumLev[ $i ]['canWatchPremium'] == 'members' ? 'selected' : ''; ?>>All Members</option>
  <option value="list" <?php echo $premiumLev[ $i ]['canWatchPremium'] == 'list' ? 'selected' : ''; ?>>Members in List</option>
</select>

<h4>Members allowed to watch premium channels (comma separated usernames, roles, emails, IDs)</h4>
<textarea name="watchListPremium<?php echo intval( $i ); ?>" cols="64" rows="4" id="watchListPremium<?php echo intval( $i ); ?>"><?php echo esc_textarea( $premiumLev[ $i ]['watchListPremium'] ); ?>
</textarea>

<h4>Maximum Number of channels</h4>
<input name="pMaxChannels<?php echo intval( $i ); ?>" type="text" id="pMaxChannels<?php echo intval( $i ); ?>" size="7" maxlength="7" value="<?php echo esc_attr( $premiumLev[ $i ]['pMaxChannels'] ); ?>"/> channels
<br>How many channels can user of this level create. Leave blank or 0 to use default (<?php echo esc_html( $optionsDefault['maxChannels'] ); ?>).  Only limits creation of new channels: Reducing this does not delete/disable existing channels.

<h4>Maximum Broadcasting Time per Channel</h4>
<input name="pBroadcastTime<?php echo intval( $i ); ?>" type="text" id="pBroadcastTime<?php echo intval( $i ); ?>" size="7" maxlength="7" value="<?php echo esc_attr( $premiumLev[ $i ]['pBroadcastTime'] ); ?>"/> (minutes/period)
<br>0 = unlimited

<h4>Maximum Channel Watch Time per Channel</h4>
<input name="pWatchTime<?php echo intval( $i ); ?>" type="text" id="pWatchTime<?php echo intval( $i ); ?>" size="10" maxlength="10" value="<?php echo esc_attr( $premiumLev[ $i ]['pWatchTime'] ); ?>"/> (minutes/period)
<br>Total cumulated view time. 0 = unlimited

<h4>Video Stream Bandwidth</h4>
<input name="pCamBandwidth<?php echo intval( $i ); ?>" type="text" id="pCamBandwidth<?php echo intval( $i ); ?>" size="7" maxlength="7" value="<?php echo esc_attr( $premiumLev[ $i ]['pCamBandwidth'] ); ?>"/> (bytes/s)
<br>Default stream size for web broadcasting interface.

<h4>Maximum Video Stream Bandwidth (at runtime)</h4>
<input name="pCamMaxBandwidth<?php echo intval( $i ); ?>" type="text" id="pCamMaxBandwidth<?php echo intval( $i ); ?>" size="7" maxlength="7" value="<?php echo esc_attr( $premiumLev[ $i ]['pCamMaxBandwidth'] ); ?>"/> (bytes/s)
<br>Maximum stream size for web broadcasting interface.
					<?php
				}

				$options['premiumLevels'] = serialize( $premiumLev );
				update_option( 'VWliveStreamingOptions', $options );

				?>

<h3>Common Settings</h3>

<h4>Usage Period Reset</h4>
<input name="timeReset" type="text" id="timeReset" size="4" maxlength="4" value="<?php echo esc_attr( $options['timeReset'] ); ?>"/> (days)
<br>Same as for regular channels. 0 = never
				<?php
				break;
			case 'features':
				// ! Channel Features
				?>
<h3>Channel Features</h3>
Enable channel features, accessible by owner (broadcaster).
<br>Specify comma separated list of user roles, emails, logins able to setup these features for their channels.
<br>Use All to enable for everybody and None or blank to disable.
				<?php

				$features = self::roomFeatures();

				foreach ( $features as $key => $feature ) {
					if ( $feature['installed'] ) {
						echo '<h3>' . esc_html( $feature['name'] ) . '</h3>';
						echo '<textarea name="' . esc_attr( $key ) . '" cols="64" rows="2" id="' . esc_attr( $key ) . '">' . esc_textarea( trim( $options[ $key ] ) )  . '</textarea>';
						echo '<br>' . esc_html( $feature['description'] );
					}
				}

				break;

			case 'watcher':
				$options['parameters'] = htmlentities( stripslashes( $options['parameters'] ) );
				$options['layoutCode'] = htmlentities( stripslashes( $options['layoutCode'] ) );
				$options['watchStyle'] = htmlentities( stripslashes( $options['watchStyle'] ) );

				?>
<h3>Video Watch / Viewer</h3>
Settings for video subscribers that watch the live channels using the advanced watch video & chat or plain video interface (VideoWhisper Flash based applications for PC browsers). These settings do not apply for external apps or HTML5 alternatives (HLS, MPEG-DASH, WebRTC).

<h4>Who can watch video</h4>
<select name="canWatch" id="canWatch">
  <option value="all" <?php echo $options['canWatch'] == 'all' ? 'selected' : ''; ?>>Anybody</option>
  <option value="members" <?php echo $options['canWatch'] == 'members' ? 'selected' : ''; ?>>All Members</option>
  <option value="list" <?php echo $options['canWatch'] == 'list' ? 'selected' : ''; ?>>Members in List</option>
</select>
<h4>Members allowed to watch video (comma separated usernames, roles, IDs)</h4>
<textarea name="watchList" cols="100" rows="4" id="watchList"><?php echo esc_textarea( $options['watchList'] ); ?>
</textarea>

<h4>Default Viewer Interface</h4>
<select name="viewerInterface" id="viewerInterface">
  <option value="chat" <?php echo $options['viewerInterface'] == 'chat' ? '' : 'selected'; ?>>Video + Chat</option>
  <option value="video" <?php echo $options['viewerInterface'] == 'video' ? 'selected' : ''; ?>>Only Video</option>
</select>
<br>Show interactive watch interface (video, chat, user list, tips) or just video.
<br>For simplified interface in HTML5, disable Transcoding Warnings from <a href="admin.php?page=live-streaming&tab=hls">HTML5 Transcoding</a> tab.

<h4>HTML Chat Visitor Writing</h4>
<select name="htmlchatVisitorWrite" id="htmlchatVisitorWrite">
  <option value="0" <?php echo $options['htmlchatVisitorWrite'] ? '' : 'selected'; ?>>No</option>
  <option value="1" <?php echo $options['htmlchatVisitorWrite'] ? 'selected' : ''; ?>>Yes</option>
</select>
<br>Allow visitors to write in HTML chat. Not recommended as that may result in message abuse. HTML5 chat interface styling is defined in htmlchat/css/chat-watch.css .

<h4>Video Only Width</h4>
<input name="videoWidth" type="text" id="videoWidth" size="7" maxlength="7" value="<?php echo esc_attr( $options['videoWidth'] ); ?>"/>

<h4>Video Only Height</h4>
<input name="videoHeight" type="text" id="videoHeight" size="7" maxlength="7" value="<?php echo esc_attr( $options['videoHeight'] ); ?>"/>

				<?php
					// 
				break;

			case 'billing':
				?>
<h3>Billing Settings</h3>
This solution can use a credits/tokens wallet for tips to broadcasters (and showing balance).

<h4>Active Wallet</h4>
<select name="wallet" id="wallet">
  <option value="MyCred" <?php echo $options['wallet'] == 'MyCred' ? 'selected' : ''; ?>>MyCred</option>
  <option value="WooWallet" <?php echo $options['wallet'] == 'WooWallet' ? 'selected' : ''; ?>>WooWallet</option>
</select>
<BR>Select wallet to use with solution.

<h4>Multi Wallet</h4>
<select name="walletMulti" id="walletMulti">
  <option value="0" <?php echo $options['walletMulti'] == '0' ? 'selected' : ''; ?>>Disabled</option>
  <option value="1" <?php echo $options['walletMulti'] == '1' ? 'selected' : ''; ?>>Show</option>
  <option value="2" <?php echo $options['walletMulti'] == '2' ? 'selected' : ''; ?>>Manual</option>
  <option value="3" <?php echo $options['walletMulti'] == '3' ? 'selected' : ''; ?>>Auto</option>
</select>
<BR>Show will display balances for available wallets, manual will allow transferring to active wallet, auto will automatically transfer all to active wallet.
				<?php

				submit_button();
				?>

<h3>TeraWallet (WooWallet WooCommerce Wallet</h3>
				<?php
				if ( is_plugin_active( 'woo-wallet/woo-wallet.php' ) ) {
					echo 'WooWallet Plugin Detected';

					if ( $GLOBALS['woo_wallet'] ) {
						$wooWallet = $GLOBALS['woo_wallet'];

						if ( $wooWallet->wallet ) {
							echo '<br>Testing balance: You have: ' . esc_html( $wooWallet->wallet->get_wallet_balance( get_current_user_id() ) );

							?>
	<ul>
		<li><a class="secondary button" href="admin.php?page=woo-wallet">User Credits History & Adjust</a></li>
		<li><a class="secondary button" href="users.php">User List with Balance</a></li>
	</ul>
							<?php
						} else {
							echo 'Error: WooWallet->wallet not ready! Make sure <a href="https://woocommerce.com/?aff=18336&cid=1980980" target="_woocommerce">WooCommerce</a> is also installed and active. <a href="plugin-install.php">Plugins > Add New Plugin</a>';
						}
					} else {
						echo 'Error: woo_wallet not found!';
					}
				} else {
					echo 'Not detected. Please install and activate <a target="_plugin" href="https://wordpress.org/plugins/woo-wallet/">WooCommerce Wallet</a> from <a href="plugin-install.php">Plugins > Add New</a>!';
				}

				?>
				<br>
WooCommerce Wallet plugin is based on <a href="https://woocommerce.com/?aff=18336&cid=1980980" target="_woocommerce">WooCommerce</a> plugin and allows customers to store their money in a digital wallet. The customers can add money to their wallet using various payment methods set by the admin, available in WooCommerce. The customers can also use the wallet money for purchasing products from the WooCommerce store.
<br> + Configure WooCommerce payment gateways from <a target="_gateways" href="admin.php?page=wc-settings&tab=checkout">WooCommerce > Settings, Payments tab</a>.
<br> + Enable payment gateways from <a target="_gateways" href="admin.php?page=woo-wallet-settings">Woo Wallet Settings</a>.
<br> + Setup a page for users to buy credits with shortcode [woo-wallet]. My Wallet section is also available in WooCommerce My Account page (/my-account).

<h4>WooCommerce Memberships, Subscriptions and Conversion Tools</h4>
<ul>
	<LI><a href="https://woocommerce.com/products/woocommerce-memberships/?aff=18336&cid=1980980">WooCommerce Memberships</a> Setup paid membership as products. Leveraged with Subscriptions plugin allows membership subscriptions.</LI>
	<LI><a href="https://woocommerce.com/products/woocommerce-subscriptions/?aff=18336&cid=1980980">WooCommerce Subscriptions</a> Setup subscription products, content. Leverages Membership plugin to setup membership subscriptions.</LI>
	<LI><a href="https://woocommerce.com/products/follow-up-emails/?aff=18336&cid=1980980">WooCommerce Follow Up</a> Follow Up by emails and twitter automatically, drip campaigns.</LI>
	<LI><a href="https://woocommerce.com/products/woocommerce-bookings/?aff=18336&cid=1980980">WooCommerce Bookings</a> Let your customers book reservations, appointments on their own.</LI>
</ul>


<h3>myCRED Wallet (MyCred)</h3>

<h4>1) myCRED</h4>
				<?php
				if ( is_plugin_active( 'mycred/mycred.php' ) ) {
					echo 'MyCred Plugin Detected';
				} else {
					echo 'Not detected. Please install and activate <a target="_mycred" href="https://wordpress.org/plugins/mycred/">myCRED</a> from <a href="plugin-install.php">Plugins > Add New</a>!';
				}

				if ( function_exists( 'mycred_get_users_balance' ) ) {
					$balance = mycred_get_users_balance( get_current_user_id() );

					echo '<br>Testing MyCred balance: You have ' . esc_html( $balance . ' ' . htmlspecialchars( $options['currencyLong'] ) ) . '. ';

					if ( ! strlen( $balance ) ) {
						echo 'Warning: No balance detected! Unless this account is excluded, there should be a MyCred balance. MyCred plugin may not be configured/enabled correctly.';
					}
					?>
	<ul>
		<li><a class="secondary button" href="admin.php?page=mycred">Transactions Log</a></li>
		<li><a class="secondary button" href="users.php">User Credits History & Adjust</a></li>
	</ul>
					<?php
				}
				?>
<a target="_mycred" href="https://wordpress.org/plugins/mycred/">myCRED</a> is a stand alone adaptive points management system that lets you award / charge your users for interacting with your WordPress powered website. The Buy Content add-on allows you to sell any publicly available post types, including webcam posts created by this plugin. You can select to either charge users to view the content or pay the post's author either the whole sum or a percentage.

	<br> + After installing and enabling myCRED, activate these <a href="admin.php?page=mycred-addons">addons</a>: buyCRED, Sell Content are required and optionally Notifications, Statistics or other addons, as desired for project.

	<br> + Configure in <a href="admin.php?page=mycred-settings ">Core Setting > Format > Decimals</a> at least 2 decimals to record fractional token usage. With 0 decimals, any transactions under 1 token will not be recorded.




<h4>2) myCRED buyCRED Module</h4>
				<?php
				if ( class_exists( 'myCRED_buyCRED_Module' ) ) {
					echo 'Detected';
					?>
	<ul>
		<li><a class="secondary button" href="edit.php?post_type=buycred_payment">Pending Payments</a></li>
		<li><a class="secondary button" href="admin.php?page=mycred-purchases-mycred_default">Purchase Log</a> - If you enable BuyCred separate log for purchases.</li>
		<li><a class="secondary button" href="edit-comments.php">Troubleshooting Logs</a> - MyCred logs troubleshooting information as comments.</li>
	</ul>
					<?php
				} else {
					echo 'Not detected. Please install and activate myCRED with <a href="admin.php?page=mycred-addons">buyCRED addon</a>!';
				}
				?>

<p> + myCRED <a href="admin.php?page=mycred-addons">buyCRED addon</a> should be enabled and at least 1 <a href="admin.php?page=mycred-gateways">payment gateway</a> configured, for users to be able to buy credits.
<br> + Setup a page for users to buy credits with shortcode <a target="mycred" href="http://codex.mycred.me/shortcodes/mycred_buy_form/">[mycred_buy_form]</a> or use <a href="https://wordpress.org/plugins/paid-membership/">Paid Membership & Content</a> - My Wallet page (that can manage multi wallet MyCred, TeraWallet).
<br> + "Thank You Page", "Cancellation Page" should be configured from <a href="admin.php?page=mycred-settings">buyCred settings</a>.</p>
<p>Troubleshooting: If you experience issues with IPN tests, check recent access logs (recent Visitors from CPanel) to identify exact requests from billing site, right after doing a test.</p>


<h4>3) myCRED Sell Content Module</h4>
				<?php
				if ( class_exists( 'myCRED_Sell_Content_Module' ) ) {
					echo 'Detected';
				} else {
					echo 'Not detected. Please install and activate myCRED with <a href="admin.php?page=mycred-addons">Sell Content addon</a>!';
				}
				?>
<p>
myCRED <a href="admin.php?page=mycred-addons">Sell Content addon</a> should be enabled as it's required to enable certain stat shortcodes. Optionally select "<?php echo ucwords( $options['custom_post'] ); ?>" - I Manually Select as Post Types you want to sell in <a href="admin.php?page=mycred-settings">Sell Content settings tab</a> so access to webcams can be sold from backend. You can also configure payout to content author from there (Profit Share) and expiration, if necessary.
				<?php
				break;

			case 'tips':
				// ! Pay Per View Settings

				?>
<h3>Tips</h3>
Allows viewers to send tips from watch interface. Requires <a href="admin.php?page=live-streaming&tab=billing">billing setup</a>.

<h4>Enable Tips</h4>
<select name="tips" id="tips">
  <option value="1" <?php echo $options['tips'] ? 'selected' : ''; ?>>Yes</option>
  <option value="0" <?php echo $options['tips'] ? '' : 'selected'; ?>>No</option>
</select>
<br>Allows clients to tip performers. Tips feature is implemented both in Flash and HTML chat interface.

<h4>Donate User Roles</h4>
<input name="rolesDonate" type="text" id="rolesDonate" size="100" maxlength="250" value="<?php echo esc_attr( $options['rolesDonate'] ); ?>"/>
<BR>Comma separated roles allowed to donate. Ex: administrator, editor, author, contributor, subscriber, performer, creator, studio, client, fan
<br>Leave empty to allow anybody or only an inexistent role (none) to disable for everybody.
<br> - Your roles (for troubleshooting):
				<?php
			global $current_user;
			foreach ( $current_user->roles as $role )
			{
				echo esc_html( $role ) . ' ';
			}
?>
			<br> - Current WordPress roles:
				<?php
			global $wp_roles;
			foreach ( $wp_roles->roles as $role_slug => $role )
			{
				echo esc_html( $role_slug ) . '= "' . esc_html( $role['name'] ) . '" ';
			}
?>

<h4>Enable Room Goals</h4>
<select name="goals" id="goals">
  <option value="1" <?php echo $options['goals'] ? 'selected' : ''; ?>>Yes</option>
  <option value="0" <?php echo $options['goals'] ? '' : 'selected'; ?>>No</option>
</select>
<br>Goals are based on total gifts/donations from all users (crowdfunding).


<h4>Goals Configuration</h4>
<textarea name="goalsConfig" id="goalsConfig" cols="120" rows="12"><?php echo esc_textarea( $options['goalsConfig'] ); ?></textarea>
<BR>
Default:<br><textarea readonly cols="120" rows="6"><?php echo esc_textarea( $optionsDefault['goalsConfig'] ); ?></textarea>
<BR>Parsed configuration (should be an array or arrays):<BR>
				<?php

			var_dump( $options['goalsDefault'] );
?>
<BR>Serialized:<BR>
				<?php

			echo esc_html( serialize( $options['goalsDefault'] ) );
?>

<h4>Tip Options</h4>
				<?php
				$tipOptions            = stripslashes( $options['tipOptions'] );
				$options['tipOptions'] = htmlentities( stripslashes( $options['tipOptions'] ) );
				?>
<textarea name="tipOptions" id="tipOptions" cols="100" rows="8"><?php echo esc_textarea( $options['tipOptions'] ); ?></textarea>
<br>List of tip options as XML. Sounds and images must be deployed in ls/templates/live/tips folder.
 Default:<br><textarea readonly cols="100" rows="4"><?php echo esc_textarea( $optionsDefault['tipOptions'] ); ?></textarea>

<br>Tips data parsed:
				<?php

				if ( $tipOptions ) {
					$p = xml_parser_create();
					xml_parse_into_struct( $p, trim( $tipOptions ), $vals, $index );
					$error = xml_get_error_code( $p );
					xml_parser_free( $p );

					if ( $error ) {
						echo '<br>Error:' . xml_error_string( $error );
					}

					if ( is_array( $vals ) ) {
						foreach ( $vals as $tKey => $tip ) {
							if ( $tip['tag'] == 'TIP' ) {
								echo '<br>- ';
								var_dump( $tip['attributes'] );
							}
						}
					}
				}
				?>

<h4>Broadcaster Earning Ratio</h4>
<input name="tipRatio" type="text" id="tipRatio" size="10" maxlength="16" value="<?php echo esc_attr( $options['tipRatio'] ); ?>"/>
<br>Performer receives this ratio from client tip.
<br>Ex: 0.9; Set 0 to disable (performer receives nothing). Set 1 for performer to get full amount paid by client.

<h4>Client Tip Cooldown</h4>
<input name="tipCooldown" type="text" id="tipCooldown" size="10" maxlength="16" value="<?php echo esc_attr( $options['tipCooldown'] ); ?>"/>s
<BR>A minimum time client has to wait before sending a new tip. This prevents accidental multi tipping and overspending. Set 0 to disable (not recommended).

<h4>Manage Balance Page</h4>
<select name="balancePage" id="balancePage">
				<?php

				$args   = array(
					'sort_order'   => 'asc',
					'sort_column'  => 'post_title',
					'hierarchical' => 1,
					'post_type'    => 'page',
					'post_status'  => 'publish',
				);
				$sPages = get_pages( $args );
				foreach ( $sPages as $sPage ) {
					echo '<option value="' . intval( $sPage->ID ) . '" ' . ( $options['balancePage'] == ( $sPage->ID ) ? 'selected' : '' ) . '>' . esc_html( $sPage->post_title ) . '</option>' . "\r\n";
				}
				?>
</select>
<br>Page linked from balance section, usually a page where registered users can buy credits.

				<?php submit_button(); ?>

<a name="brave"></a>

<h3>Receive Tips and Site Contributions in Crypto</h3>
<a href="https://brave.com/bro242">Brave</a> is a special build of the popular Chrome browser, focused on privacy & speed & ad blocking and already used by millions. Users get airdrops and rewards from ads they are willing to watch and content creators (publishers) like site owners get tips and automated revenue from visitors. This is done in $BAT and can be converted to other cryptocurrencies like Bitcoin or withdrawn in USD, EUR.
<br>Additionally, with Brave you can easily test if certain site features are disabled by privacy features, cookie restrictions or common ad blocking rules. 
	<p>How to receive contributions and tips for your site:
	<br>+ Get the <a href="https://brave.com/bro242">Brave Browser</a>. You will get a browser wallet, airdrops and get to see how tips and contributions work.
	<br>+ Join <a href="https://creators.brave.com/">Brave Creators Publisher Program</a> and add your site(s) as channels. If you have an established site, you may have automated contributions or tips already available from site users that accessed using Brave. Your site(s) will show with a Verified Publisher badge in Brave browser and users know they can send you tips directly.
	<br>+ You can setup and connect an Uphold wallet to receive your earnings and be able to withdraw to bank account or different wallet. You can select to receive your deposits in various currencies and cryptocurrencies (USD, EUR, BAT, BTC, ETH and many more).
</p>

				<?php
				break;

		}

		if ( ! in_array( $active_tab, array( 'setup', 'live', 'stats', 'shortcodes', 'support', 'reset', 'troubleshooting', 'billing', 'tips', 'appearance' ) ) ) {
			submit_button();
		}
		?>

</form>
</div>

<style>
.vwInfo
{
background-color: #fffffa;
padding: 8px;
margin: 8px;
border-radius: 4px;	
display:block;
border: #999 1px solid;
box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}
</style>
	
		<?php
	}


	static function adminDocs() {
		$options = self::getOptions();

		?>
<h2>Broadcast Live Video - Live Streaming by VideoWhisper.com</h2>

This solution involves special streaming hosting services for live streaming and interactions: See hosting requirements and option pages from <a href="admin.php?page=live-streaming&tab=support">Support Resources</a> section.

<h3>Quick Backend Setup Tutorial</h3>
<ol>
<li>Install and activate the VideoWhisper Broadcast Live Video - Live Streaming Integration plugin from WP backend: already done if you see this. </li>
<li>From <a href="admin.php?page=live-streaming&tab=import">BroadcastLiveVideo > Settings: Import</a> import streaming server settings as provided by VideoWhisper or manually edit settings in appropriate sections.</li>
<li>From <a href="options-permalink.php">Settings > Permalinks</a> enable a SEO friendly structure (ex. Post name)</li>
<li>From <a href="admin.php?page=live-streaming&tab=pages">BroadcastLiveVideo > Settings: Pages</a> setup feature pages.</li>
<li>From <a href="nav-menus.php">Appearance > Menus</a> add Channels and Broadcast Live pages to main site menu.</li>
<li>Optional: Install and enable a <a href="admin.php?page=live-streaming&tab=billing">billing plugin</a> to allow owners to sell channel access</li>
<li>Optional: <a href="plugin-install.php?s=videowhisper&tab=search&type=term">Install</a> and enable the <a href="https://videosharevod.com/">VideoShareVOD</a> plugin to enable video broadcast archive import, video publishing, management.</li>
<li>Setup <a href="edit-tags.php?taxonomy=category&post_type=channel">channel categories</a>, common to site content.</li>
<li>Configure cache so users can access dynamic content, chat and live streams.
<br>If you have a cache plugin like <a href="options-general.php?page=wpsupercache&tab=settings">WP Super Cache</a>, disable caching for visitors who have a cookie set in their browser, don’t cache pages with GET parameters and add and exception for "/<?php echo esc_html( $options['custom_post'] ); ?>/" pages. </li>
<li><a href="https://broadcastlivevideo.com/customize">Customize</a></li>
</ol>


<h3>BroadcastLiveVideo Installation URLs</h3>

	- Users can setup their channels and start broadcast from Broadcast Live page:
	<br><?php echo get_permalink( get_option( 'vwls_page_manage' ) ); ?>
	<br>Try broadcasting as described at https://broadcastlivevideo.com/broadcast-html5-webrtc-to-mobile-hls/ and https://broadcastlivevideo.com/broadcast-with-obs-or-other-external-encoder/ .
	<br>
	<br>- After broadcasting, channels show in Channels list:
	<br><?php echo get_permalink( get_option( 'vwls_page_channels' ) ); ?>
	<br>
	<br>- Configure your site logos (after uploading):
	<br><?php echo admin_url( 'admin.php?page=live-streaming&tab=appearance' ); ?>

	<br>- Customize further as described at:
	<br>https://broadcastlivevideo.com/customize

	<br>- Contact VideoWhisper for clarifications or custom development:
	<br>https://videowhisper.com/tickets_submit.php

<br>
<br>- To prevent spam registrations use a captcha plugin (get a key from google) and user verification plugin to automatically verify users by email confirmation. 
<br>https://wordpress.org/plugins/search/captcha/
<br>https://www.google.com/recaptcha/admin/create#list
<br>https://wordpress.org/plugins/search/user-verification/

<br>
<br>- Also setup a special email account from CPanel and a SMTP plugin to make sure users receive notification emails.
<br>Use a WP SMTP mailing plugin and setup a real email account from your hosting backend (setup an email from CPanel) or external (Gmail or other provider), to send emails using SSL and all verifications. This should reduce incidents where users don’t find registration emails due to spam filter triggering. Also instruct users to check their spam folders if they don’t find registration emails.
<br>https://wordpress.org/plugins/search/smtp/

<br>
<br>- For more advanced web interface and features including video conferencing, 2 way video calls, pay per minute, video collaboration with presentation and file sharing, emotes and mentions, see PaidVideochat HTML5 Videochat:
<br>https://paidvideochat.com/html5-videochat/


<h3>Customize with Premium Plugins / Addons</h3>
<ul>
	<LI><a href="http://themeforest.net/popular_item/by_category?category=wordpress&ref=videowhisper">Premium Themes</a> Professional WordPress themes.</LI>
	<LI><a href="https://woocommerce.com/?aff=18336&cid=1980980">WooCommerce</a> Free shopping cart plugin, supports multiple free and premium gateways with TeraWallet/WooWallet plugin and various premium eCommerce plugins.</LI>

	<LI><a href="https://woocommerce.com/products/woocommerce-memberships/?aff=18336&cid=1980980">WooCommerce Memberships</a> Setup paid membership as products. Leveraged with Subscriptions plugin allows membership subscriptions.</LI>

	<LI><a href="https://woocommerce.com/products/woocommerce-subscriptions/?aff=18336&cid=1980980">WooCommerce Subscriptions</a> Setup subscription products, content. Leverages Membership plugin to setup membership subscriptions.</LI>

	<LI><a href="https://woocommerce.com/products/woocommerce-bookings/?aff=18336&cid=1980980">WooCommerce Bookings</a> Let your customers book reservations, appointments on their own.</LI>

	<LI><a href="https://woocommerce.com/products/follow-up-emails/?aff=18336&cid=1980980">WooCommerce Follow Up</a> Follow Up by emails and twitter automatically, drip campaigns.</LI>

	<LI><a href="https://updraftplus.com/?afref=924">Updraft Plus</a> Automated WordPress backup plugin. Free for local storage. For production sites external backups are recommended (premium).</LI>
</ul>


<h3>ShortCodes</h3>
<ul>
  <li><h4>[videowhisper_watch channel=&quot;Channel Name&quot; width=&quot;100%&quot; height=&quot;100%&quot; flash=&quot;0&quot;]</h4>
	Displays watch interface with video and discussion. If iOS is detected it shows HLS instead. Container style can be configured from plugin settings. Auto detection unless flash="1" forced.</li>

  <li><h4>[videowhisper_htmlchat_playback channel=&quot;Channel Name&quot; 	post_id=&quot;Channel Post ID&quot; videowidth=&quot;480px&quot; videoheight=&quot;360px&quot;]</h4>
	  Displays html chat with HTML5 live stream.</li>

  <li><h4>[videowhisper_video channel=&quot;Channel Name&quot; width=&quot;480px&quot; height=&quot;360px&quot; html5=&quot;auto&quot;]</h4>
  Displays video only interface. Depending on device and settings will show HTML5. Set html5=&quot;always&quot; to force html5 video.</li>

  <li><h4>[videowhisper_hls channel=&quot;Channel Name&quot; width=&quot;480px&quot; height=&quot;360px&quot;]</h4>
  Displays HTML5 HLS (HTTP Live Streaming) video interface. Shows istead of watch and video interfaces if iOS is detected. Stream must be published in compatible format (H264,AAC) or transcoding must be enabled and active for stream to show.</li>

 <li><h4>[videowhisper_mpeg channel=&quot;Channel Name&quot; width=&quot;480px&quot; height=&quot;360px&quot;]</h4>
  Displays HTML5 MPEG DASH video interface. Shows instead of watch and video interfaces if Android is detected. Stream must be published in compatible format (H264,AAC) or transcoding must be enabled and active for stream to show.</li>

  <li><h4>[videowhisper_webrtc_playback channel=&quot;Channel Name&quot; width=&quot;480px&quot; height=&quot;360px&quot;]</h4>
  Displays WebRTC video playback interface.</li>

  <li>
	<h4>[videowhisper_broadcast channel=&quot;Channel Name&quot; flash=&quot;0&quot;]</h4>
	Shows broadcasting interface. If not provided, channel name is detected depending on settings, post type, user. Only owner can access for channel posts. Auto detection unless flash="1" forced.
   </li



  <li>
	<h4>[videowhisper_channel_user]</h4>
	Displays broadcasting interface for a channel with same name as user. Creates channel automatically if not existing. For single channel per user setups.
   </li>

  <li>
	<h4>[videowhisper_webrtc_broadcast channel=&quot;Channel Name&quot;]</h4>
	Shows WebRTC broadcasting interface. If not provided, channel name is detected depending on settings, post type, user. Only owner can access for channel posts.
   </li>

	<li>
	<h4>[videowhisper_external channel=&quot;Channel Name&quot;] [videowhisper_external_broadcast channel=&quot;Channel Name&quot;][videowhisper_external_playback channel=&quot;Channel Name&quot;]</h4>
	Shows settings for broadcasting/playback with external applications. Channel name is detected depending on settings, post type, user. Only owner can access for channel posts.
   </li>
	 <li>
		 <h4>[videowhisper_channels per_page="8" perrow="" order_by="edate" category_id="" select_category="1" 'select_tags="1" select_name="1" select_order="1" select_page="1" include_css="1" ban="0" id=""]</h4>
		 Lists channels with snapshots, ordered by most recent online and with pagination.
	 </li>

	 <li>
	 <h4>
	 [videowhisper_channel_manage]
	 </h4>
		 Displays channel management page.
	 </li>
</ul>
<h3>Documentation, Support, Customizations</h3>
<ul>
<li>Home Page and Documentation: <a href="https://videowhisper.com/?p=WordPress+Live+Streaming">VideoWhisper - WordPress Live Streaming</a></li>
<li>WordPress Plugin Page: <a href="https://wordpress.org/plugins/videowhisper-live-streaming-integration/">VideoWhisper Live Streaming Integration</a></li>
<li>Contact Page: <a href="https://videowhisper.com/tickets_submit.php">Contact VideoWhisper</a></li>
</ul>
<p>After ordering solution and setting up existing editions, VideoWhisper.com developers can customize these for additional fees depending on exact requirements.</p>
		<?php
	}



	// ! Pages
	static function updatePages() {
		// if (!$page_id || $page_id == "-1" || !$page_id2 || $page_id2 == "-1")  add_action('wp_loaded', array('VWliveStreaming','updatePages'));

		$options = get_option( 'VWliveStreamingOptions' );

		if ( $options['disablePage'] == '0' || $options['disablePageC'] == '0' ) {
			// create a menu to add pages
			$menu_name   = 'VideoWhisper';
			$menu_exists = wp_get_nav_menu_object( $menu_name );

			if ( ! $menu_exists ) {
				$menu_id = wp_create_nav_menu( $menu_name );
			} else {
				$menu_id = $menu_exists->term_id;
			}
		}

		// if not disabled create
		if ( $options['disablePage'] == '0' ) {
			global $user_ID;
			$page                   = array();
			$page['post_type']      = 'page';
			$page['post_content']   = '[videowhisper_channel_manage]';
			$page['post_parent']    = 0;
			$page['post_author']    = $user_ID;
			$page['post_status']    = 'publish';
			$page['post_title']     = 'Broadcast Live';
			$page['comment_status'] = 'closed';

			$page_id = get_option( 'vwls_page_manage' );
			if ( $page_id > 0 ) {
				$page['ID'] = $page_id;
			}

			$pageid = wp_insert_post( $page );
			update_option( 'vwls_page_manage', $pageid );

			$link = get_permalink( $pageid );

			if ( $menu_id && $pageid ) {
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'  => 'Broadcast Live',
						'menu-item-url'    => $link,
						'menu-item-status' => 'publish',
					)
				);
			}
		}

		if ( $options['disablePageC'] == '0' ) {
			global $user_ID;
			$page                   = array();
			$page['post_type']      = 'page';
			$page['post_content']   = '[videowhisper_channels]';
			$page['post_parent']    = 0;
			$page['post_author']    = $user_ID;
			$page['post_status']    = 'publish';
			$page['post_title']     = 'Channels';
			$page['comment_status'] = 'closed';

			$page_id = get_option( 'vwls_page_channels' );
			if ( $page_id > 0 ) {
				$page['ID'] = $page_id;
			}

			$pageid = wp_insert_post( $page );
			update_option( 'vwls_page_channels', $pageid );

			$link = get_permalink( $pageid );

			if ( $menu_id && $pageid ) {
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'  => 'Channels',
						'menu-item-url'    => $link,
						'menu-item-status' => 'publish',
					)
				);
			}
		}

	}

	static function deletePages() {
		 $options = get_option( 'VWliveStreamingOptions' );

		if ( $options['disablePage'] ) {
			$page_id = get_option( 'vwls_page_manage' );
			if ( $page_id > 0 ) {
				wp_delete_post( $page_id );
				update_option( 'vwls_page_manage', -1 );
			}
		}

		if ( $options['disablePageC'] ) {
			$page_id = get_option( 'vwls_page_channels' );
			if ( $page_id > 0 ) {
				wp_delete_post( $page_id );
				update_option( 'vwls_page_channels', -1 );
			}
		}

	}

	static function log($message, $level = 5, $options = null)
	{
		if (!$options) $options = self::getOptions();
		
		//levels: 0 = none, 1 = error, 2 = warning, 3 = notice, 4 = info, 5 = debug 
		if ($options['logLevel'] >= $level) 
		{
			$logFile = $options['uploadsPath'];

			//create folder if not exists
			if (!file_exists($logFile)) mkdir($logFile, 0777, true);
			$logFile .= '/logs';
			if (!file_exists($logFile)) mkdir($logFile, 0777, true);
			$logFile .= '/'.date('Y-m-d').'.txt';

			//include date and level in message and end of line
			$message = date('Y-m-d H:i:s') . ' [' . $level . '] ' . $message . PHP_EOL;
			file_put_contents($logFile, $message, FILE_APPEND);
		}
	}



}
