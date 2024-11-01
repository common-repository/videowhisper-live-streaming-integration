<?php

// BroadcastLiveVideo.com : HTML5 Videochat
// This file includes mainly functionality related to integrating the HTML5 Videochat application with WordPress platform (database, user system, shortcodes) in the BroadcastLiveVideo turnkey site solution.
// Handles AJAX calls from HTML5 Videochat application, receiving and returning chat updates, delivering configuration parameters and data.

namespace VideoWhisper\LiveStreaming;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'VW_H5VLS_DEVMODE', 0 );

trait H5Videochat {

    static function generatePin()
	{
		return rand(10000, 99999);
	}

	static function vwls_notify()
	{
	
	//called by videowhisper streaming server to notify current streaming status (i.e to display rooms as live)

	$options = self::getOptions();

	// output clean
	if (ob_get_length()) ob_clean();


	$token = sanitize_text_field($_POST['token'] ?? '');

	if (!$token || $token != $options['vwsToken']) {
		echo json_encode( ['deny' => 1, 'info' => 'Invalid account token: ' . $token, 'POST' => $_POST] );
		exit();
	}

	//self::requirementMet( 'rtmp_status' );

					// start logging
					$dir       = $options['uploadsPath'];
					$filename1 = $dir . '/_rtmpStreams.txt';
					$dfile     = fopen( $filename1, 'w' );

					fputs( $dfile, 'VideoWhisper Log for RTMP Streams' . "\r\n" );
					fputs( $dfile, 'Server Date: ' . "\r\n" . date( 'D M j G:i:s T Y' ) . "\r\n" );
					fputs( $dfile, '$_POST:' . "\r\n" . json_encode( $_POST ) );

					
					
		$streams= json_decode( stripslashes( $_POST['streams'] ?? ''), true ) ;
		if (json_last_error() !== JSON_ERROR_NONE) 
		{
					// Handle the error appropriately
					fputs( $dfile,  "\r\n" . 'JSON Error:' .  json_last_error_msg() );
		}else  fputs( $dfile,  "\r\n" . 'Streams:' . json_encode( $streams ) . ' A:'. is_array( $streams ) );

	$rtmp_test = 0;
	$ztime = time();

	$resultStreams = [];
	
	global $wpdb;
    $table_channels = $wpdb->prefix . 'vw_lsrooms';


	if ( is_array( $streams ) ) 
	foreach ($streams as $stream => $params) 
		{

		$resultStreams[$stream] = [ 'name' => $stream];

        //stream is per channel
        $postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE `post_title` = '$stream' AND `post_type`='" . $options['custom_post'] . "' LIMIT 0,1" ); // 
        
	
			$room = $stream;
			$disconnect = '';
			$resultStreams[$stream] = [ 'postID' => $postID ];
			
				
				$post = get_post($postID);
                $userID = 0;
				if ($post) 
                {
                    $room = $post->post_title;
                    $userID = $post->post_author;
                }
				$r = $room;
					

					$resultStreams[$stream]['room'] = $room;
                    $resultStreams[$stream]['userID'] =  $userID;

                    $session = self::sessionUpdate( $stream, $room, 1, 2, 0, 0, $userID, $postID, $options); // not strict in case user also in web chat

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

                        if ( $ban = self::containsAny( $channel->name ?? '', $options['bannedNames'] ) ) {
                            $disconnect = "Room banned ($ban)!";
                        }

                        // calculate time in ms based on previous request
                        $lastTime    = $session->edate * 1000;
                        $currentTime = $ztime * 1000;

                        // update time
                        $expTime = $options['onlineExpiration1'] + 60;
                        $dS      = floor( ( $currentTime - $lastTime ) / 1000 );
                        // if ($dS > $expTime || $dS<0) $disconnect = "Web server out of sync for rtmp broadcaster ($dS > $expTime) !"; //Updates should be faster; fraud attempt?

                        if ($channel) {
                        $channel->btime += $dS;

                        // update room
                        $sql = "UPDATE `$table_channels` set edate=$ztime, btime = " . $channel->btime . " where id = '" . $channel->id . "'";
                        $wpdb->query( $sql );
                        }

                        // detect transcoding to avoid altering source info
                        $transcoding   = 0;
                        $stream_webrtc = $session->room . '_webrtc';
                        $stream_hls    = 'i_' . $session->room;

                        update_post_meta( $postID, 'edate', $ztime );
                         update_post_meta( $postID, 'btime', $channel->btime );

                            update_post_meta( $postID, 'stream-protocol', 'rtmp' );
                            update_post_meta( $postID, 'stream-type', 'external' );
                            update_post_meta( $postID, 'stream-updated', $ztime );

                            self::updateViewers( $postID, $session->room, $options );
                    

                        // record stream if enabled
                        if ( ! $disconnect ) {
                            self::streamRecord( $session, $session->room, 'rtmp', $postID, $options );
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

                    //set disconnect if any
                    $resultStreams[$stream]['disconnect'] = $disconnect;

	} //end foreach streams

	$result = ['time' => time(), 'streams' => $resultStreams, 'received' => count($streams) ];
	
	fputs( $dfile,  "\r\n" . 'Result:' . json_encode( $result )  );

	echo json_encode($result);
	exit();

	}

	static function vwls_stream()
	{
		//called by videowhisper streaming server to get stream broadcast/playback pins for stream validation

		$options = self::getOptions();

		// output clean
		if (ob_get_length()) {
			ob_clean();
		}

		$token = sanitize_text_field($_POST['token'] ?? '');
		$stream = sanitize_text_field($_POST['stream'] ?? '');

		if (!$token || $token != $options['vwsToken']) {
			echo json_encode(['deny' => 1, 'info' => 'Invalid account token']);
			exit();
		}

		if (!$stream) {
			echo json_encode(['deny' => 1, 'info' => 'Missing stream name']);
			exit();
		}

        //pin is per post 
        global $wpdb;
        $postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE `post_title` = '$stream' AND `post_type`='" . $options['custom_post'] . "' LIMIT 0,1" ); // 

		if (!$postID) {
			echo json_encode(['deny' => 1, 'info' => 'Channel post not found for this stream: ' . $stream]);
			exit();
		}

		$result= [];
		$result['broadcastPin'] = self::getPin($postID, 'broadcast', $options);
		$result['playbackPin'] = self::getPin($postID, 'playback', $options);
		$result['pid'] = $postID;

	 	echo json_encode($result);
		exit();
	}

	static function getPin($postID, $type = 'broadcast', $options = null)
	{
		if (!$options) $options = self::getOptions();

		if ($options['videowhisperStream'])
		{

			if ($type == 'broadcast')
			{
				$broadcastPin = get_post_meta($postID, 'broadcastPin', true);
				if (!$broadcastPin)
				{
					$broadcastPin = self::generatePin();
					update_post_meta($postID, 'broadcastPin', $broadcastPin);
				}
				return $broadcastPin;
			}
			else
			{
				$playbackPin = get_post_meta($postID, 'playbackPin', true);
				if (!$playbackPin)
				{
					$playbackPin = self::generatePin();
					update_post_meta($postID, 'playbackPin', $playbackPin);
				}
				return $playbackPin;
			}
		}

		if ($type == 'broadcast') return trim($options['broadcastPin']);
		return trim($options['playbackPin']);
	}


    static function isPerformer( $userID, $postID ) {

        // is specified user a performer (broadcaster) for this room
        if ( ! $userID ) {
            return 0;
        }

        if ( ! $postID ) {
            return 0;
        }

        $current_user = get_userdata( $userID );
        if ( ! $current_user ) {
            return 0;
        }
        
        if ( ! $current_user->ID ) {
            return 0;
        }

        $post = get_post( $postID );
        if ( ! $post ) {
            return 0;
        }

        // owner
        if ( $post->post_author == $current_user->ID ) {
            return 1;
        }

        // performer (post owner is studio)
        if ( get_post_meta( $postID, 'performerID', true ) == $current_user->ID ) {
            return 2;
        }

        // multi performer posts (array)
        $performerIDs = get_post_meta( $postID, 'performerID', false );
        if ( $performerIDs ) {
            if ( is_array( $performerIDs ) ) {
                if ( in_array( $current_user->ID, $performerIDs ) ) {
                    return 3;
                }
            }
        }

        //neither
        return 0;
    }

    static function isModerator($userID, $options = null, $user = null, $roles = null)
	{
		if ( !$userID ) return false;
		
		if ( !$options ) $options = self::getOptions();
		
		if ( !$user) $user = get_userdata( $userID );
		
		if ( !$roles )
		{
			$roles = explode( ',', $options['roleModerators'] );
			foreach ( $roles as $key => $value )$roles[ $key ] = trim( $value );
		}

		if ( self::any_in_array( $roles, $user->roles ) ) return true;

		return false;
	}

	static function rolesUser( $csvRoles, $user)
	{
		// user has any of the listed roles
		// if (self::rolesUser( $option['rolesDonate'], wp_get_current_user() )

		if (!$csvRoles) return true; //all allowed if not defined

		$roles = explode(',', $csvRoles);
		foreach ($roles as $key => $value) $roles[$key] = trim($value);
	
		if (!$user || !isset($user->roles) ) 
		if ( self::any_in_array( $roles, ['Guest','Visitor'] ) ) return true;
		else return false; //not logged in

		if ( self::any_in_array( $roles, $user->roles ) ) return true;

		return false;
	}
    
	static function appRole( $userID, $parameter, $default, $options ) {
		// returns parameter depending on user role
		if ( ! array_key_exists( 'appRoles', $options ) ) {
			return $default;
		}
		if ( ! array_key_exists( $parameter, $options['appRoles'] ) ) {
			return $default;
		}
		if ( ! array_key_exists( 'roles', $options['appRoles'][ $parameter ] ) ) {
			return $default;
		}

		$value = $options['appRoles'][ $parameter ]['value'];
		$other = $options['appRoles'][ $parameter ]['other'];

		$rolesS = trim( $options['appRoles'][ $parameter ]['roles'] );
		if ( $rolesS == '' || $rolesS == 'NONE' ) {
			return $other;
		}

		// special handling
		if ( $rolesS == 'ALL' ) {
			return $value;
		}
		if ( $userID && $rolesS == 'MEMBERS' ) {
			return $value;
		}

		$roles = explode( ',', $rolesS );
		foreach ( $roles as $key => $role ) {
			$roles[ $key ] = trim( $role ); // remove spaces
		}

		$user = get_userdata( $userID );
		if ( ! $user ) {
			return $other;
		}
		foreach ( $roles as $role ) {
			if ( in_array( $role, $user->roles ) ) {
				return $value;
			}
		}

		return $other;
	}

    static function appStreamBroadcast( $userID, $post, $options ) {
		// broadcasting stream

		$user = get_userdata( $userID );
		// $broadcaster = self::isPerformer($userID, $post->ID)
		$streamName = sanitize_file_name($post->post_title);

		return self::webrtcStreamQuery( $userID, $post->ID, 1, $streamName, $options, 0, $post->post_title, 0 );
	}


	static function appStreamPlayback( $userID, $performerID, $post, $options ) {
		$user = get_userdata( $performerID );
		// $broadcaster = self::isPerformer($userID, $post->ID);
		$streamName = sanitize_file_name($post->post_title);

		return self::webrtcStreamQuery( $userID, $post->ID, 0, $streamName, $options, 0, $post->post_title, 0 );
	}

    static function videowhisper_h5vls_app( $atts ) {
            // Shortcode: HTML5 Videochat
            $stream  = '';
            $postID  = 0;
            $options = self::getOptions();
            $room = ''; 
    
            if ( is_single() ) {
                $postID = get_the_ID();
                if ( get_post_type( $postID ) == $options['custom_post'] ) {
                    $room = get_the_title( $postID );
                } else {
                    $postID = 0;
                }
            }
    
            if ( ! $room ) {
                $room = sanitize_text_field( $_GET['room'] ?? '' );
            }
    
            $atts = shortcode_atts(
                array(
                    'room'      => $room,
                    'webcam_id' => $postID,
                    'silent'    => 0,
                    'session' 	=> 0,
                    'type'      => '', // /audio/text
                    'title'     => '',
                    'width'		=>'',
                    'height'	=>'',
    
                ),
                $atts,
                'videowhisper_h5vls_app'
            );
    
            if ( $atts['room'] ) {
                $room = $atts['room']; // parameter channel="name"
            }
            if ( $atts['webcam_id'] ) {
                $postID = intval($atts['webcam_id']);
            }
    
            $width = $atts['width'];
            if ( ! $width ) {
                $width = '100%';
            }
            $height = $atts['height'];
            if ( ! $height ) {
                $height = '360px';
            }
    
            $room = sanitize_file_name( $room );
    
            global $wpdb;
        
    
            // only room provided
            if ( ! $postID && $room ) {
                $postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . sanitize_file_name( $room ) . "' and post_type='" . $options['custom_post'] . "' LIMIT 0,1" );
            }
    
            // only wecam_id provided
            if ( ! $room ) {
                $post = get_post( $postID );
                if ( ! $post ) {
                    return "VideoWhisper HTML5 App Error: Room not found! (#$postID)";
                }
                $room = sanitize_file_name( $post->post_title );
            }
    
            $htmlCode = "<!--VideoWhisper.com/BroadcastLiveVideo.com/videowhisper_h5vls_app/$room#$postID-->";
    
            $roomID   = $postID;
            $roomName = sanitize_file_name( $room );
    
            $userID = get_current_user_id(); // 0 if no user logged in
    
            $isPerformer = 0;
            $isModerator = 0;
            
            if ( $userID ) {
                $isPerformer = self::isPerformer( $userID, $roomID );
    
                $user = get_userdata( $userID );

				if ($isPerformer) $userName = $room;
				else 
				{
	                $userField    = $options['userName'];
					if ( ! $userField ) {
						$userField = 'user_nicename';
					}
					if ( $user->$userField ) {
						$userName = $user->$userField;
					} else $userName = $user->user_login;
					$userName = sanitize_file_name( $userName ); 
				}
				
                $isModerator = self::isModerator( $userID, $options, $user);
    
                // access keys
                if ( $user ) {
                    $userkeys   = $user->roles;
                    $userkeys[] = $user->user_login;
                    $userkeys[] = $user->user_nicename;
                    $userkeys[] = $user->ID;
                    $userkeys[] = $user->user_email;
                    $userkeys[] = $user->display_name;
                } else {
                    $userkeys = array( 'Member' );
                }
    
                if ( $isPerformer ) {
                    // performer publishing with HTML5 app - save info for responsive playback
                    update_post_meta( $postID, 'performer', $userName );
                    update_post_meta( $postID, 'performerUserID', $userID );
    
                    update_post_meta( $postID, 'stream-protocol', 'rtsp' );
                    update_post_meta( $postID, 'stream-type', 'webrtc' );
                    update_post_meta( $postID, 'stream-mode', 'direct' );
                    update_post_meta( $postID, 'roomInterface', 'html5app' );
                }
            } else {
    
                // use a cookie for visitor username persistence
                if ( $_COOKIE['htmlchat_username'] ?? false ) {
                    $userName = sanitize_file_name( $_COOKIE['htmlchat_username'] );
                } else {
                    $userName = 'G_' . base_convert( time() % 36 * rand( 0, 36 * 36 ), 10, 36 );
                    // setcookie('htmlchat_username', $userName); // set in init()
                }
                $isVisitor = 1;
    
                $userkeys = array( 'Guest' );
            }
            
           // access control
            // check if banned
            $bans = get_post_meta( $postID, 'bans', true );
            if ( $bans ) {
    
                // clean expired bans
                foreach ( $bans as $key => $ban ) {
                    if ( $ban['expires'] < time() ) {
                        unset( $bans[ $key ] );
                        $bansUpdate = 1;
                    }
                }
                if ( $bansUpdate ) {
                    update_post_meta( $postID, 'bans', $bans );
                }
    
                $clientIP = self::get_ip_address();
    
                foreach ( $bans as $ban ) {
                    if ( $clientIP == $ban['ip'] || ( $uid > 0 && $uid == $ban['uid'] ) ) {
    
                        return '<div class="ui segment red">' . $response['error'] = __( 'You are banned from accessing this room!', 'live-streaming' ) . ' ' . $ban['by'] . ' : ' . date( DATE_RFC2822, $ban['expires'] ) . '</div>';
    
                    }
                }
            }
            
            //restricted roles    
            if ( !$isPerformer && isset( $user ) )
            {
                $roleRestricted = explode( ',', $options['roleRestricted'] );
                foreach ( $roleRestricted as $key => $value ) $roleRestricted[ $key ] = trim( $value );
                
                 if ( self::any_in_array( $roleRestricted, $user->roles ) ) return '<div class="ui segment red">' . __( 'Your role can not access other rooms.', 'live-streaming' ) .  '</div>';
            }
    
    
            $canWatch  = $options['canWatch'];
            $watchList = $options['watchList'];
    
            if ( !$isPerformer && !$isModerator ) {
    
                switch ( $canWatch ) {
                    case 'all':
                        break;
    
                    case 'members':
                        if ( ! $userID ) {
                            return '<div class="ui segment red">' . __( 'Login is required to access.', 'live-streaming' ) . '<BR><a class="ui button primary qbutton" href="' . wp_login_url() . '">' . __( 'Login', 'live-streaming' ) . '</a>  <a class="ui button secondary qbutton" href="' . wp_registration_url() . '">' . __( 'Register', 'live-streaming' ) . '</a>' . '</div>';
                        }
                        break;
    
                    case 'list';
    
                        if ( ! $userID ) {
                            return '<div class="ui segment red">' . __( 'Login is required to access.', 'live-streaming' ) . '<BR><a class="ui button primary qbutton" href="' . wp_login_url() . '">' . __( 'Login', 'live-streaming' ) . '</a>  <a class="ui button secondary qbutton" href="' . wp_registration_url() . '">' . __( 'Register', 'live-streaming' ) . '</a>' . '</div>';
                        }
                        if ( ! self::inList( $userkeys, $watchList ) ) {
                            return '<div class="ui segment red">' . __( 'You are not in the allowed client list configured from backend settings.', 'live-streaming' ) . '</div>';
                        }
                    break;
                }
    
                $accessList = get_post_meta( $postID, 'vw_accessList', true );
                if ( $accessList ) {
                    if ( ! self::inList( $userkeys, $accessList ) ) {
                        return '<div class="ui segment red">' . __( 'This room is restricted by access list. You are not in room access list.', 'live-streaming' ) . '</div>';
                    }
                }
            }
            
            
            // create a session
            $session = self::sessionUpdate(  $userName, $roomName, $isPerformer, 11, 0, 1, $userID, $postID, $options );
    
            if ( is_object($session) )
            {
                    $sessionID = $session->id;
            }
            else {
                $sessionID = 0;
                $htmlCode .= '<div class="ui segment red">Error: Session could not be created: ' . "sessionUpdate($userName, $roomName, $isPerformer, 11, 0, 1, $userID, $postID, ". self::get_ip_address() . ")</div>";
            }
    
             $wlJS = '';
            if ( $options['whitelabel'] ) {
                $wlJS = ', checkWait: true, whitelabel: ' . $options['whitelabel'];
            }  
    
            $ajaxurl   = admin_url() . 'admin-ajax.php?action=h5vls_app'; //wp ajax server
            $serverURL2 = plugins_url('videowhisper-live-streaming-integration/server/') ; //fast server
            
            $sessionKey = 'VideoWhisper';
            
            $pDev = (VW_H5VLS_DEVMODE ? ', devMode: true' : '');
            
            $modeVersion = trim($options['modeVersion'] ?? '');

            $dataCode = "window.VideoWhisper = {userID: $userID, sessionID: $sessionID, sessionKey: '$sessionKey', roomID: $roomID, performer: $isPerformer, userName: '$userName', roomName: '$roomName', serverURL: '$ajaxurl', serverURL2: '$serverURL2', modeVersion: '$modeVersion' $wlJS $pDev}";
    
            wp_enqueue_script( 'fomantic-ui', dirname( plugin_dir_url( __FILE__ ) ) . '/scripts/semantic/semantic.min.js', array( 'jquery' ) );
    
            $k = 0;
            $CSSfiles = scandir( dirname( dirname( __FILE__ ) ) . '/static/css/' );
            foreach ( $CSSfiles as $filename ) {
                if ( strpos( $filename, '.css' ) && ! strpos( $filename, '.css.map' ) ) {
                    wp_enqueue_style( 'vw-h5vcams-app' . ++$k, dirname( plugin_dir_url( __FILE__ ) ) . '/static/css/' . $filename );
                }
            }
    
            $countMain = 0;
            $countRuntime  = 0;
            $JSfiles       = scandir( dirname( dirname( __FILE__ ) ) . '/static/js/' );
            foreach ( $JSfiles as $filename ) {
                if ( strpos( $filename, '.js' ) && ! strpos( $filename, '.js.map' ) && ! strpos( $filename, '.txt' ) ) {
                    wp_enqueue_script( 'vw-h5vcams-app' . ++$k, dirname( plugin_dir_url( __FILE__ ) ) . '/static/js/' . $filename, array(), '', true );
    
                    if ( ! strstr( $filename, 'LICENSE.txt' ) ) {
                        if ( substr( $filename, 0, 5 ) == 'main.' ) {
                            $countMain++;
                        }
                    }
                    if ( ! strstr( $filename, 'LICENSE.txt' ) ) {
                        if ( substr( $filename, 0, 7 ) == 'runtime' ) {
                            $countRuntime++;
                        }
                    }
                }
            }
    
            if ( $countMain > 1 || $countRuntime > 1 ) {
                $htmlCode .= '<div class="ui segment red">Warning: Possible duplicate JS files in application folder! Only latest versions should be deployed.</div>';
            }
    
            $cssCode = html_entity_decode( stripslashes( $options['appCSS'] ?? '' ) );
    
            if ( VW_H5VLS_DEVMODE || $options['debugMode']) 
            {
                $htmlCode .= '<!--VideoWhisper.com debug info: videowhisper_h5vls_app shortcode atts=' . serialize($atts) .  '-->';
            }
    
            $htmlCode .= <<<HTMLCODE
    <!--VideoWhisper.com - HTML5 Videochat web app - p:$isPerformer uid:$userID postID:$postID r:$room s:$sessionID-->
    <noscript>You need to enable JavaScript to run this app. For more details see <a href="https://broadcastlivevideo.com">BroadcastLiveVideo</a> or <a href="https://videowhisper.com/">contact HTML5 Videochat developers</a>.</noscript>
    <div id="videowhisperAppContainer"><div id="videowhisperVideochat"></div></div>
    <script>$dataCode;
    document.cookie = "html5videochat=DoNotCache";
    </script>
    <style>
    
    #videowhisperAppContainer
    {
    display: block;
    min-height: 725px;
    height: inherit;
    background-color: #eee;
    position: relative;
    z-index: 102 !important;
    }
    
    #videowhisperVideochat
    {
    display: block;
    width: 100%;
    height: 100%;
    position: absolute;
    z-index: 102 !important;
    }
    
    $cssCode
    </style>
    HTMLCODE;
    
            if ($isPerformer) if ($options['lovense'])
            {
                //lovense broadcaster integration (integrates with Lovense API, browser, extension)
                wp_enqueue_script( 'lovense-api-broadcast', 'https://api.lovense.com/cam-extension/static/js-sdk/broadcast.js', array(), '', false ); //lovense api integration, per integration specs
    
    //documentation and feedback not matching: 
    $receiveTipCall = 'camExtension.receiveTip(amount, "' . $userName . '", clientName, "VideoWhisper");';
    if ($options['lovenseTipParams'] == 3) $receiveTipCall = 'camExtension.receiveTip(amount, clientName, "VideoWhisper");';
    
                $htmlCode .= '
    <SCRIPT>
                jQuery(document).ready(function(){
                    
                  const camExtension = new CamExtension(\'' . $options['lovensePlatform'] . '\', \'' . $userName . '\')
                  
                    camExtension.on(\'ready\',async function(ce) {
                        const version = await ce.getCamVersion();
                        console.log("Lovense Broadcast", version);
                        
                        if (typeof window.VideoWhisper.chatNotification != "undefined" ) window.VideoWhisper.chatNotification("Lovense " + version);
                    })
      
                       camExtension.on("postMessage", (data) => {
                      // Process the data which to be sent
                      // Send the data to chat room
                         console.log("Lovense Broadcast postMessage", data);
                         
                         if (typeof window.VideoWhisper.chatNotification != "undefined" ) window.VideoWhisper.chatNotification(data);
                    })
      
                    camExtension.on("toyStatusChange", (data) => {
                      // Handle toy information data
                      // data = [{
                      //      id:"d6c35fe83348",
                      //      name:"toy name",
                      //      type:"lush",
                      //      status:"on",
                      //      version:"",
                      //      battery:80
                      // }]
                      
                      console.log("Lovense Broadcast toyStatusChange", data);
                      
                      if (typeof window.VideoWhisper.chatNotification != "undefined" ) window.VideoWhisper.chatNotification("Lovense " + data.name + " " + type + " " + status + " " + battery + "%");
                  })
     
                     camExtension.on("tipQueueChange", (data) => {
                          //  handle queue information data
                          //  data = {
                          //      running: [ ],
                          //      queue: [ ],
                          //      waiting: [ ]
                          //  }
                            
                            console.log("Lovense Broadcast tipQueueChange", data);
       
                      })
                      
                     camExtension.on("settingsChange", (data) => {
                          //  handle configuration information data
                          //  data = {
                          //      levels:{},
                          //      special:{},
                          //  }
                             
                             console.log("Lovense Broadcast settingsChange", data);				      
                      })  
                     
                     
                    window.VideoWhisper.performerTip = function(amount, clientName)
                    {
                        //performer received a tip  
                        console.log("window.performerTip", amount, clientName);
                        ' . $receiveTipCall . '
                        if (typeof window.VideoWhisper.chatNotification != "undefined" ) window.VideoWhisper.chatNotification("Lovense " + clientName + ": " + amount);				
                    }
                         
                  })  
    </SCRIPT>
                ';
            }
    
    
            $vwtemplate = '';
            if ( array_key_exists( 'vwtemplate', $_GET ) ) {
                $vwtemplate = sanitize_text_field( $_GET['vwtemplate'] );
            }
            
            if ( $vwtemplate != 'app' && $options['postTemplate'] != '+app' ) {
                $htmlCode .= '<div class="ui form"><a class="ui button secondary fluid" href="' . add_query_arg( array( 'vwtemplate' => 'app' ), get_permalink( $postID ) ) . '"><i class="window maximize icon"></i> ' . __( 'Open in Full Page', 'live-streaming' ) . '</a></div>';
            }
    
            $state = 'block';
            if ( ! $options['videowhisper'] ) {
                $state = 'none';
            }
            $htmlCode .= '<div id="VideoWhisper" style="display: ' . $state . ';"><p>Powered by <a href="https://videowhisper.com">VideoWhisper / Live Video Site Builder</a> / <a href="https://broadcastlivevideo.com/">Broadcast Live Video / Streaming Site Builder</a>.</p></div>';
    
            return $htmlCode;
        }


//app functions

		// ! user sessions vw_vmls_sessions
		static function sessionValid( $sessionID, $userID, $broadcaster = false ) {
			// returns true if session is valid

			global $wpdb;

            if ( $broadcaster ) {
				$table_sessions = $wpdb->prefix . 'vw_sessions';
			} else {
				$table_sessions = $wpdb->prefix . 'vw_lwsessions';
			}

            //status 0: pending, 1: active, 2: ended, 3: expired
			$sqlS    = "SELECT * FROM $table_sessions WHERE id='$sessionID' AND uid='$userID' AND status < 2 LIMIT 1";
			$session = $wpdb->get_row( $sqlS );

			if ( $session ) {
				return $session;
			} else {
				return false;
			}
		}

        static function appFail( $message = 'Request Failed', $response = null, $errorMore = '', $errorURL = '' ) {
            // bad request: fail
    
            if ( ! $response ) {
                $response = array();
            }
    
            $response['error'] = $message;
    
            $response['VideoWhisper'] = 'https://videowhisper.com';
    
            if ( $errorMore ) {
                $response['errorMore'] = $errorMore;
                $response['errorURL']  = $errorURL;
            }
    
            echo json_encode( $response );
    
            die();
        }

  
static function language2flag($lang)
         {
             $flags = [ 'en-us'=>'us', 'en-gb'=>'gb', 'pt-br'=> 'br', 'pt-pt' => 'pt','zh'=>'cn', 'ja' => 'jp', 'el' => 'gr', 'da' => 'dk', 'en' => 'us'];
             if ( array_key_exists($lang, $flags ) ) return $flags[ $lang ];
             return $lang;
         }

static function languageField($userID, $options)
{
    
    $langs = [];

    $languages = get_option( 'VWdeepLlangs' );
    if ($languages)
    {
        
         foreach ($languages as $lang => $label) $langs []= ['value' => $lang, 'flag' => self::language2flag( $lang ), 'key' => $lang, 'text' => $label];
    }
    else $langs []= ['value' => 'en-us', 'flag' => 'us', 'key' => 'en-us', 'text' => 'English US'];
 
             $h5v_language = get_user_meta( $userID, 'h5v_language', true );
             if (!$h5v_language) $h5v_language = 'en-us';

             return [
             'name'        => 'h5v_language',
            'description' => __( 'Chat Language', 'live-streaming' ),
            'details'     => __( 'Language you will be writing in chat and would prefer to read.', 'live-streaming' ),
            'type'        => 'dropdown',
            'value'       => $h5v_language,
            'flag'		  => self::language2flag($h5v_language),
            'options'     => $langs,	 			
             ];


}
        
static function appUserOptions( $session, $options ) {
    
    $h5v_language = get_user_meta( $session->uid, 'h5v_language', true );
    if (!$h5v_language) $h5v_language = 'en-us';
    
    return array(
        'h5v_language'      => $h5v_language,
        'h5v_flag'      	=> self::language2flag( $h5v_language ),	
        'h5v_sfx'           => self::is_true( get_user_meta( $session->uid, 'h5v_sfx', true ) ),
        'h5v_audio'         => self::is_true( get_user_meta( $session->uid, 'h5v_audio', true ) ),
        'h5v_dark'          => self::is_true( get_user_meta( $session->uid, 'h5v_dark', true ) ),
        'h5v_pip'           => self::is_true( get_user_meta( $session->uid, 'h5v_pip', true ) ),
        'h5v_min'           => self::is_true( get_user_meta( $session->uid, 'h5v_min', true ) ),
     );
}

static function appSfx() {
    // sound effects sources

    $base = dirname( plugin_dir_url( __FILE__ ) ) . '/sounds/';

    return array(
        'message' => $base . 'message.mp3',
        'hello'   => $base . 'hello.mp3',
        'leave'   => $base . 'leave.mp3',
        'call'    => $base . 'call.mp3',
        'warning' => $base . 'warning.mp3',
        'error'   => $base . 'error.mp3',
        'buzz'    => $base . 'buzz.mp3',
    );
}

static function appRoomOptions( $post, $session, $options ) {
    $configuration = array();

    if ( ! $options['appOptions'] ) {
        return $configuration;
    }

    if ( $session->broadcaster ) {

        $fields = array(
            'room_private'     => array(
                'name'        => 'room_private',
                'description' => __( 'Not Public', 'live-streaming' ),
                'details'     => __( 'Hide room from public listings. Can be accessed by room link.', 'live-streaming' ),
                'type'        => 'toggle',
                'value'       => self::is_true( get_post_meta( $post->ID, 'room_private', true ) ),
            ),
            'room_audio'       => array(
                'name'        => 'room_audio',
                'description' => __( 'Audio Only', 'live-streaming' ),
                'details'     => __( 'Audio only room mode: Only microphone, no webcam video, for all participants. Applies both to group and private calls. Disables video calls.', 'live-streaming' ),
                'type'        => 'toggle',
                'value'       => $audio = self::is_true( get_post_meta( $post->ID, 'room_audio', true ) ),
            ),

            'room_text'        => array(
                'name'        => 'room_text',
                'description' => __( 'Text Only', 'live-streaming' ),
                'details'     => __( 'Text only room mode: Only text, for all participants. Applies both to group and private calls. Disables video and audio calls.', 'live-streaming' ),
                'type'        => 'toggle',
                'value'       => $audio = self::is_true( get_post_meta( $post->ID, 'room_text', true ) ),
            ),        

        );



        $fields['external_rtmp'] = array(
            'name'        => 'external_rtmp',
            'description' => __( 'External Broadcast', 'live-streaming' ),
            'details'     => __( 'Broadcast with external RTMP encoder: Show a broadcast tab with settings to configure an external RTMP encoder.', 'live-streaming' ),
            'type'        => 'toggle',
            'value'       => $external_rtmp = self::is_true( get_post_meta( $post->ID, 'external_rtmp', true ) )  || !metadata_exists('post', $post->ID, 'external_rtmp'),
        );


        // record
        
        if ( $options['recording'] ) 
        {

        $fields['stream_record']         = array(
            'name'        => 'stream_record',
            'description' => __( 'Record Perfomer', 'live-streaming' ),
            'details'     => __( 'Record performer stream.', 'live-streaming' ),
            'type'        => 'toggle',
            'value'       => $stream_record = self::is_true( get_post_meta( $post->ID, 'stream_record', true ) ),
        );
        
        }

        if ( $options['tips'] ) {
            $fields['gifts'] = array(
                'name'        => 'gifts',
                'description' => __( 'Gifts', 'live-streaming' ),
                'details'     => __( 'Enable Gifts button in Actions bar. Gifts apply to current room goal when enabled. Disable to hide current room goal from text chat.', 'live-streaming' ),
                'type'        => 'toggle',
                'value'       => $gifts = self::is_true( get_post_meta( $post->ID, 'gifts', true ) ),
            );
        }

        if ( $options['goals'] ) {
            $fields['goals_panel'] = array(
                'name'        => 'goals_panel',
                'description' => __( 'Goals Panel', 'live-streaming' ),
                'details'     => __( 'Show goals panel for participants to see all goals. Users can donate to any Independent goal.', 'live-streaming' ),
                'type'        => 'toggle',
                'value'       => $goals_panel = self::is_true( get_post_meta( $post->ID, 'goals_panel', true ) ),
            );
        }
        if ( $options['goals'] ) {
            $fields['goals_sort'] = array(
                'name'        => 'goals_sort',
                'description' => __( 'Goals Sort', 'live-streaming' ),
                'details'     => __( 'Sort goals by current donations, descending.', 'live-streaming' ),
                'type'        => 'toggle',
                'value'       => $goals_sort = self::is_true( get_post_meta( $post->ID, 'goals_sort', true ) ),
            );
        }

        $configuration['room'] = array(
            'name'   => __( 'Room Preferences', 'live-streaming' ) . ': ' . sanitize_file_name( $post->post_title ),
            'fields' => $fields,
        );
    }

    // user options
    $fieldsUser = [];
    
    $fieldsUser = [
        
        'h5v_language'    => self::languageField($session->uid, $options),

        'h5v_sfx'    => array(
            'name'        => 'h5v_sfx',
            'description' => __( 'Sound Effects', 'live-streaming' ),
            'details'     => __( 'Sound effects (on actions).', 'live-streaming' ),
            'type'        => 'toggle',
            'value'       => $sfx = self::is_true( get_user_meta( $session->uid, 'h5v_sfx', true ) ),
        ),
        'h5v_dark'   => array(
            'name'        => 'h5v_dark',
            'description' => __( 'Dark Mode', 'live-streaming' ),
            'details'     => __( 'Dark interface mode.', 'live-streaming' ),
            'type'        => 'toggle',
            'value'       => $darkMode = self::is_true( get_user_meta( $session->uid, 'h5v_dark', true ) ),
        ),
        'h5v_pip'    => array(
            'name'        => 'h5v_pip',
            'description' => __( 'Picture in Picture', 'live-streaming' ),
            'details'     => __( 'Picture in picture mode with camera over video.', 'live-streaming' ),
            'type'        => 'toggle',
            'value'       => $pipMode = self::is_true( get_user_meta( $session->uid, 'h5v_pip', true ) ),
        ),
        'h5v_min'    => array(
            'name'        => 'h5v_min',
            'description' => __( 'Minimalist', 'live-streaming' ),
            'details'     => __( 'Show less buttons, features and interface elements.', 'live-streaming' ),
            'type'        => 'toggle',
            'value'       => $minMode = self::is_true( get_user_meta( $session->uid, 'h5v_min', true ) ),
        ),

        'h5v_audio'  => array(
            'name'        => 'h5v_audio',
            'description' => __( 'Audio Only', 'live-streaming' ),
            'details'     => __( 'Audio only user mode: Publish only microphone, no webcam video. Applies both to group and private calls.', 'live-streaming' ),
            'type'        => 'toggle',
            'value'       => $userAudio = self::is_true( get_user_meta( $session->uid, 'h5v_audio', true ) ),
        )
    ];

    $configuration['user'] = array(
        'name'   => __( 'User Preferences', 'live-streaming' ) . ': ' . $session->username,
        'fields' => $fieldsUser,
    );

    $configuration['meta'] = array( 'time' => time() );

    return $configuration;

}

static function appText() {
    // implement translations

   // returns texts
   return array(
       'Send'                                   => __( 'Send', 'live-streaming' ),
       'Type your message'                      => __( 'Type your message', 'live-streaming' ),

       'Wallet'                                 => __( 'Wallet', 'live-streaming' ),
       'Balance'                                => __( 'Balance', 'live-streaming' ),
       'Pending Balance'                        => __( 'Pending Balance', 'live-streaming' ),
       'Session Time'                           => __( 'Session Time', 'live-streaming' ),
       'Session Cost'                           => __( 'Session Cost', 'live-streaming' ),

       'Record'                                 => __( 'Record', 'live-streaming' ),
       'Start'                                  => __( 'Start', 'live-streaming' ),
       'Stop'                                   => __( 'Stop', 'live-streaming' ),
       'Discard'                                => __( 'Discard', 'live-streaming' ),
       'Download'                               => __( 'Download', 'live-streaming' ),
       'Uploading. Please wait...'              => __( 'Uploading. Please wait...', 'live-streaming' ),

       'Chat'                                   => __( 'Chat', 'live-streaming' ),
       'Camera'                                 => __( 'Camera', 'live-streaming' ),
       'Users'                                  => __( 'Users', 'live-streaming' ),
       'Options'                                => __( 'Options', 'live-streaming' ),
       'Files'                                  => __( 'Files', 'live-streaming' ),
       'Presentation'                           => __( 'Presentation', 'live-streaming' ),

       'Tap for Sound'                          => __( 'Tap for Sound', 'live-streaming' ),
       'Enable Audio'                           => __( 'Enable Audio', 'live-streaming' ),
       'Mute'                                   => __( 'Mute', 'live-streaming' ),
       'Reload'                                 => __( 'Reload', 'live-streaming' ),
       'Ignore'                                 => __( 'Ignore', 'live-streaming' ),

       'Packet Loss: Download Connection Issue' => __( 'Packet Loss: Download Connection Issue', 'live-streaming' ),
       'Packet Loss: Upload Connection Issue'   => __( 'Packet Loss: Upload Connection Issue', 'live-streaming' ),

       'Broadcast'                              => __( 'Broadcast', 'live-streaming' ),
       'Stop Broadcast'                         => __( 'Stop Broadcast', 'live-streaming' ),
       'Make a selection to start!'             => __( 'Make a selection to start!', 'live-streaming' ),

       'Gift'                                   => __( 'Gift', 'live-streaming' ),
       'Gifts'                                  => __( 'Gifts', 'live-streaming' ),

       'Lights On'                              => __( 'Lights On', 'live-streaming' ),
       'Dark Mode'                              => __( 'Dark Mode', 'live-streaming' ),
       'Picture in Picture'                     => __( 'Picture in Picture', 'live-streaming' ),

       'Enter Fullscreen'                       => __( 'Enter Fullscreen', 'live-streaming' ),
       'Exit Fullscreen'                        => __( 'Exit Fullscreen', 'live-streaming' ),

       'Site Menu'                              => __( 'Site Menu', 'live-streaming' ),

       'Nevermind'                              => __( 'Nevermind', 'live-streaming' ),
   
        'Name'                                   => __( 'Name', 'live-streaming' ),
       'Size'                                   => __( 'Size', 'live-streaming' ),
       'Age'                                    => __( 'Age', 'live-streaming' ),
       'Upload: Drag and drop files here, or click to select files' => __( 'Upload: Drag and drop files here, or click to select files', 'live-streaming' ),
       'Uploading. Please wait...'              => __( 'Uploading. Please wait...', 'live-streaming' ),
       'Open'                                   => __( 'Open', 'live-streaming' ),
       'Delete'                                 => __( 'Delete', 'live-streaming' ),

       'Media Displayed'                        => __( 'Media Displayed', 'live-streaming' ),
       'Remove'                                 => __( 'Remove', 'live-streaming' ),
       'Default'                                => __( 'Default', 'live-streaming' ),
       'Empty'                                  => __( 'Empty', 'live-streaming' ),

       'Profile'                                => __( 'Profile', 'live-streaming' ),
       'Show'                                   => __( 'Show', 'live-streaming' ),

       'Private Call'                           => __( 'Private Call', 'live-streaming' ),
       'Exit'                                   => __( 'Exit', 'live-streaming' ),

       'External Broadcast'                     => __( 'External Broadcast', 'live-streaming' ),
       'Not Available'                          => __( 'Not Available', 'live-streaming' ),
       'Streaming'                              => __( 'Streaming', 'live-streaming' ),
       'Closed'                                 => __( 'Closed', 'live-streaming' ),
       'Use after ending external broadcast, to faster restore web based webcam interface.' => __( 'Use after ending external broadcast, to faster restore web based webcam interface.', 'live-streaming' ),

       'Add'                                    => __( 'Add', 'live-streaming' ),
       'Complete'                               => __( 'Complete', 'live-streaming' ),
   );
}


static function appTipOptions( $options = null ) {

   $tipOptions = stripslashes( $options['tipOptions'] );
   if ( $tipOptions ) {
       $p = xml_parser_create();
       xml_parse_into_struct( $p, trim( $tipOptions ), $vals, $index );
       $error = xml_get_error_code( $p );
       xml_parser_free( $p );

       if ( is_array( $vals ) ) {
           return $vals;
       }
   }

   return array();

}


    // !App Ajax handlers
	static function h5vls_app() 
    {
    $options = self::getOptions();

    if (VW_H5VLS_DEVMODE || $options['debugMode']) {
        ini_set('display_errors', 1);
        error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
    }

    // output clean
    ob_clean();

    // D: login, public room (1 w broadcaster/viewer), 2w private vc, status
    // TD: tips

    global $wpdb;
    $table_sessions = $wpdb->prefix . 'vw_lwsessions'; // viewers
    $table_chatlog  = $wpdb->prefix . 'vw_vwls_chatlog';


    // all strings - comment echo in prod:
    if (VW_H5VLS_DEVMODE) {
        $response['POST'] = serialize($_POST);
    }
    if (VW_H5VLS_DEVMODE) {
        $response['GET'] = serialize($_GET);
    }

    $http_origin              = get_http_origin();
    $response['http_origin']  = $http_origin;
    $response['VideoWhisper'] = 'https://videowhisper.com';

    $task    = sanitize_file_name($_POST['task'] ?? 'NoTask');
    $devMode = self::is_true($_POST['devMode'] ?? false); // app in devMode
    $roomID = 0;

    $requestUID = intval($_POST['requestUID'] ?? 0); // directly requested private call

    // originally passed trough window after creating session
    // urlvar user_id > php var $userID

    // session info received trough VideoWhisper POST var
    $VideoWhisper = isset($_POST['VideoWhisper']) ? (array) $_POST['VideoWhisper'] : '';
    if ($VideoWhisper) {
        $userID     = intval($VideoWhisper['userID']);
        $sessionID  = intval($VideoWhisper['sessionID']);
        $roomID     = intval($VideoWhisper['roomID']);
        $sessionKey = intval($VideoWhisper['sessionKey']);

        $privateUID   = intval($VideoWhisper['privateUID'] ?? 0); // in private call
        $roomActionID = intval($VideoWhisper['roomActionID'] ?? 0);
    } else {
        $userID = 0;
    }

    // room is post
    $postID      = $roomID;
    $public_room = array();

    $post = get_post($roomID);
    if (! $post) {
        self::appFail('Room post not found: ' . $roomID . ' Server in DEVMODE?');
    }
    $roomName    = $post->post_title;
    $changedRoom = 0;

    // Handling the supported tasks:
    $response['task'] = $task;


    if ($task != 'login') {

        // check session
        $isPerformer = self::isPerformer($userID, $roomID);

        if ( $isPerformer )  $table_sessions = $wpdb->prefix . 'vw_sessions';
        else $table_sessions = $wpdb->prefix . 'vw_lwsessions';
        


        $session = self::sessionValid($sessionID, $userID, $isPerformer);
        if (!$session ) {
            $debugInfo = '';

            if ($options['debugMode']) {
                $debugInfo = 'Debug Info: ';

                $debugInfo .= 'App Session #' . $sessionID . ' User #' . $userID . ' Room #' . $roomID  . ' Performer ' . $isPerformer . ' / Task: ' . $task;

                $sqlS    = "SELECT * FROM $table_sessions WHERE id='$sessionID' AND uid='$userID' LIMIT 1";
                $session = $wpdb->get_row($sqlS);

                if ($session) {
                    $debugInfo .= 'Session is no longer open. Status #' . $session->status . ' Last updated: ' . ($session->edate ? date('F j, Y, g:i a', $session->edate) : '-') . ' Session Data: ' . serialize($session);
                } else {
                    $debugInfo .= 'Session with that ID was not found.';
                }
            }

            self::appFail(__('Invalid Session. Occurs if browser tab gets paused in background or room type changes and new terms apply. Reload to start a new session!', 'live-streaming') . $debugInfo);
        }

        // update online for viewer
        if (!$isPerformer) {
            $disconnect = self::updateOnline($session->username, $roomName, $postID, 7, $current_user ?? '', $options);

            if ($disconnect ) {
        
                        $errorMore = __('Channels', 'live-streaming');
                        $errorURL  = get_permalink( get_option( 'vwls_page_channels' ) );
                self::appFail('Viewer disconnected: ' . urldecode($disconnect), null, $errorMore, $errorURL);
            }
        }

        if ($session->broadcaster) {
            self::sessionUpdate($session->username, $roomName, 1, 11, 1, 1, $session->uid, $session->rid, $options);
        }

        // retreive user meta from session
        if ($session->meta) {
            $userMeta = unserialize($session->meta);
        } else $userMeta = [];

        if (! is_array($userMeta)) {
            $userMeta = array();
        }

        // set session username
        $userName = sanitize_file_name($session->username);
    }

    // login
    if ( $task == 'login' ) {
	    
        // retrieve wp info
        $user = get_userdata( $userID );
        if ( ! $user ) {
                            $isVisitor = 1;
            // self::appFail('User not found: ' . $userID);

            if ( $_COOKIE['htmlchat_username'] ) {
                $userName = sanitize_file_name( $_COOKIE['htmlchat_username'] );
            } else {
                $userName = 'G_' . base_convert( time() % 36 * rand( 0, 36 * 36 ), 10, 36 );
                // setcookie('htmlchat_username', $userName); // set in init()
            }

            $isPerformer = 0;

        } else {

            $isPerformer = self::isPerformer($userID, $roomID);

			if ($isPerformer) $userName = $roomName;
			else 
			{
	            $userField    = $options['userName'];
	            if ( ! $userField ) {
	                $userField = 'user_nicename';
	            }
	            if ( $user->$userField ) {
	                $userName = $user->$userField;
	            } else $userName = $user->user_login;
	            $userName = sanitize_file_name( $userName ); 
	        };    

        }

        // set/get room performer details
        if ( $isPerformer ) {
            update_post_meta( $postID, 'performer', $userName );
            update_post_meta( $postID, 'performerUserID', $userID );
        }


        $session = self::sessionValid( $sessionID, $userID, $isPerformer);
        if ( !$session  ) {
            self::appFail( 'Login session failed: s#' . $sessionID . ' u#' . $userID . ' p#' . $isPerformer . ' U:' . $userName .' R:'. $roomName . ' Cache plugin may prevent access to this dynamic content.' );
        }

        // session valid, login

        // retreive user meta from session
        if ( $session->meta ) {
            $userMeta = unserialize( $session->meta );
        } else $userMeta = array();

        if ( ! is_array( $userMeta ) ) {
            $userMeta = array();
        }

        // reset user preferences
        if ( $userID ) {
            if ( is_array( $options['appSetup'] ) ) {
                if ( array_key_exists( 'User', $options['appSetup'] ) ) {
                    if ( is_array( $options['appSetup']['User'] ) ) {
                        foreach ( $options['appSetup']['User'] as $key => $value ) {
                            $optionCurrent = get_user_meta( $userID, $key, true );

                            if ( empty( $optionCurrent ) || $options['appOptionsReset'] ) {
                                update_user_meta( $userID, $key, $value );
                            }
                        }
                    }
                }
            }
        }

                $balance        = floatval( self::balance( $userID, false, $options ) ); // final only, not temp
                $balancePending = floatval( self::balance( $userID, true, $options ) ); // temp

                // user session parameters and info, updates
                $response['user'] = array(
                    'from' => 'login',
                    'ID'             => intval( $userID ),
                    'name'           => $userName,
                    'sessionID'      => intval( $sessionID ),
                    'loggedIn'       => true,
                    'balance'        => number_format( $balance, 2, '.', ''  ),
                    'balancePending' => number_format( $balancePending, 2, '.', ''  ),
                    'time'           => ( $session->edate - $session->sdate ),
                    'cost'           => ( array_key_exists( 'cost', $userMeta ) ? $userMeta['cost'] : 0 ),
                    'avatar'         => get_avatar_url( $userID, array( 'default' => dirname( plugin_dir_url( __FILE__ ) ) . '/images/avatar.png' ) ),
                );

                if ( $balance < 0 ) {
                    $response['error'] = 'Error: Negative balance (' . $balance . '). Can be a result of enabling cache for user requests. Contact site administrator to review activity and adjust balance.';
                }

                $response['user']['options'] = self::appUserOptions( $session, $options );


                // config params, const
                $response['config'] = array(
                    'serverType' => $options['webrtcServer'] ?? 'wowza', //wowza/videowhisper/auto (auto will use videowhisper in privates, wowza in group streams)
                    'vwsSocket' => $options['vwsSocket'] ?? '',
                    'vwsToken' => $options['vwsToken'] ?? '',
                    'wss'              => $options['wsURLWebRTC'],
                    'application'      => $options['applicationWebRTC'],
                    'videoCodec'       => $options['webrtcVideoCodec'] ?? 'VP8',
                    'videoBitrate'     => $options['webrtcVideoBitrate'] ?? 500, //host
                    'maxBitrate'       => $options['webrtcVideoBitrate'] ?? 750, //host
                    'audioBitrate'     => $options['webrtcAudioBitrate'] ?? 32, //host
                    'audioCodec'       => $options['webrtcAudioCodec'] ?? 'opus',
                    'autoBroadcast'    => false,
                    'actionFullscreen' => true,
                    'actionFullpage'   => false,
                    'serverURL'        => $ajaxurl = admin_url() . 'admin-ajax.php?action=h5vls_app', //for uploads
                    'serverURL2'	   => plugins_url('videowhisper-live-streaming-integration/server/'), //fast server for translations
                    'multilanguage'    =>  $options['multilanguage'] ? true : false,
                    'translations' 	   => ( $options['translations'] == 'all' ? true : ( $options['translations'] == 'registered' ? is_user_logged_in() : false )  ),
                    'languages'    	   => self::languageField($userID, $options),
                    'logo'		   	 => $options['appLogo'] ?? '',
                    'modeVersion'	=> trim($options['modeVersion'] ?? ''),
                );

                // appMenu
                if ( $options['appSiteMenu'] > 0 ) {
                    $menus = wp_get_nav_menu_items( $options['appSiteMenu'] );
                    // https://developer.wordpress.org/reference/functions/wp_get_nav_menu_items/

                    $appMenu = array();
                    if ( is_array( $menus ) ) {
                        if ( count( $menus ) ) {
                            $k = 0;
                            foreach ( (array) $menus as $key => $menu_item ) {
                                if ( $menu_item->ID ) {
                                                                    $k++;
                                                                    $appMenuItem             = array();
                                                                    $appMenuItem['title']    = $menu_item->title;
                                                                    $appMenuItem['url']      = $menu_item->url;
                                                                    $appMenuItem['ID']       = intval( $menu_item->ID );
                                                                    $appMenuItem['parentID'] = intval( $menu_item->menu_item_parent );
                                                                    $appMenu[]               = $appMenuItem;
                                }
                            }

                            $appMenu[] = array(
                                'title'    => 'END',
                                'ID'       => 0,
                                'parentID' => 0,
                            ); // menu end (last item ignored by app)

                            if ( $k ) {
                                $response['config']['siteMenu'] = $appMenu;
                            }
                        }
                    }
                }

                // translations
                $response['config']['text'] = self::appText();

                $response['config']['sfx'] = self::appSfx();

                $response['config']['exitURL'] = ( get_option( 'vwls_page_channels' ) ) ?  get_permalink( get_option( 'vwls_page_channels' ) ) : get_site_url();

                $response['config']['balanceURL'] = ( $url = get_permalink( $options['balancePage'] ) ) ? $url : get_site_url();
                if ( $options['balancePage'] == -1 ) {
                    $response['config']['balanceURL'] = '';
                }

                // set default config options, in case not configured
                $optionsDefault = self::adminOptionsDefault();
                if ( is_array( $optionsDefault['appSetup'] ) ) {
                    if ( array_key_exists( 'Config', $optionsDefault['appSetup'] ) ) {
                        if ( is_array( $optionsDefault['appSetup']['Config'] ) ) {
                            foreach ( $optionsDefault['appSetup']['Config'] as $key => $value ) {
                                $response['config'][ $key ] = $value;
                            }
                        }
                    }
                }

                // pass app setup config parameters, overwrites defaults
                if ( is_array( $options['appSetup'] ) ) {
                    if ( array_key_exists( 'Config', $options['appSetup'] ) ) {
                        if ( is_array( $options['appSetup']['Config'] ) ) {
                            foreach ( $options['appSetup']['Config'] as $key => $value ) {
                                $response['config'][ $key ] = $value;
                            }
                        }
                    }
                }
                
                //enforce host limits to prevent stream rejection
                if ($response['config']['videoBitrate'] > $options['webrtcVideoBitrate']) $response['config']['videoBitrate'] = $options['webrtcVideoBitrate'] ?? 500;
                if ($response['config']['maxBitrate'] > $options['webrtcVideoBitrate']) $response['config']['maxBitrate'] = $options['webrtcVideoBitrate'] ?? 500;
                if ($response['config']['audioBitrate'] > $options['webrtcAudioBitrate']) $response['config']['audioBitrate'] = $options['webrtcAudioBitrate'] ?? 32;

                if (!isset($response['config']['snapshotInterval']) || $response['config']['snapshotInterval']< 10 ) $response['config']['snapshotInterval'] = 180;

                if (!is_user_logged_in())
                {
                    if ($options['timeIntervalVisitor']) $response['config'][ 'timeInterval' ] = intval($options['timeIntervalVisitor'] ?? 15000);
                    $response['config'][ 'recorderDisable' ] = true;
                }

                if ( ! $isPerformer ) {
                    if ( array_key_exists( 'cameraAutoBroadcastAll', $response['config'] ) ) {
                        $response['config']['cameraAutoBroadcast'] = $response['config']['cameraAutoBroadcastAll'] ?? '0';
                    } else {
                        $response['config']['cameraAutoBroadcast'] = '0';
                    }
                }

                    $response['config']['loaded'] = true;

                    //room on login
                    $response['room'] = self::appPublicRoom( $post, $session, $options, '', $public_room, 0 ); // public room or lobby
    } //end login

    // all requests, including login:
    	// check if banned
		$bans = get_post_meta( $postID, 'bans', true );
		if ( $bans ) {

			// clean expired bans
			foreach ( $bans as $key => $ban ) {
				if ( $ban['expires'] < time() ) {
					unset( $bans[ $key ] );
					$bansUpdate = 1;
				}
			}
			if ( $bansUpdate ) {
				update_post_meta( $postID, 'bans', $bans );
			}

			$clientIP = self::get_ip_address();

			foreach ( $bans as $ban ) {
				if ( $clientIP == $ban['ip'] || ( $uid > 0 && $uid == $ban['uid'] ) ) {
					$response['error'] = __( 'You are banned from accessing this room!', 'live-streaming' ) . ' ' . $ban['by'] . ' : ' . date( DATE_RFC2822, $ban['expires'] );
				}
			}
		}

        //updates
		$ztime = time();
		$needUpdate = array();	
		foreach (['room', 'user', 'options', 'files', 'media', 'questions'] as $key ) $needUpdate[$key] = 0;


        //tasks
        switch ( $task ) {

			case 'login':
			case 'tick':
				break;

                case 'recorder_upload':
                    if ( ! $roomName ) {
                        self::appFail( 'No room for recording.' );
                    }
    
                    $mode     = sanitize_text_field( $_POST['mode'] );
                    $scenario = sanitize_text_field( $_POST['scenario'] );
                    if ( ! $privateUID ) {
                        $privateUID = 0; // public room
                    }
    
                    // generate same private room folder for both users
                    if ( $privateUID ) {
                        if ( $isPerformer ) {
                            $proom = $userID . '_' . $privateUID; // performer id first
                        } else {
                            $proom = $privateUID . '_' . $userID;
                        }
                    }
    
                    $destination = $options['uploadsPath'];
                    if ( ! file_exists( $destination ) ) {
                        mkdir( $destination );
                    }
    
                    $destination .= "/$roomName";
                    if ( ! file_exists( $destination ) ) {
                        mkdir( $destination );
                    }
    
                    if ( $proom ) {
                        $destination .= "/$proom";
                        if ( ! file_exists( $destination ) ) {
                            mkdir( $destination );
                        }
                    }
    
                    //$response['_FILES'] = $_FILES;
    
                    $allowed = array( 'mp3', 'ogg', 'opus', 'mp4', 'webm', 'mkv' );
    
                    $uploads  = 0;
                    $filename = '';
    
                    if ( $_FILES ) {
                        if ( is_array( $_FILES ) ) {
                            foreach ( $_FILES as $ix => $file ) {
                                $filename = sanitize_file_name( $file['name'] );
                                
                                if ( strstr( $filename, '.php' ) ) {
                                    self::appFail( 'Bad uploader!' );
                                }

                                $ext                          = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
                                $response['uploadRecLastExt'] = $ext;
                                $response['uploadRecLastF']   = $filename;
    
                                $filepath = $destination . '/' . $filename;
    
                                if ( in_array( $ext, $allowed ) ) {
                                    if ( file_exists( $file['tmp_name'] ) ) {
                                        $errorUp = self::handle_upload( $file, $filepath ); // handle trough wp_handle_upload()
                                        if ( $errorUp ) {
                                            $response['warning'] = ( $response['warning'] ? $response['warning'] . '; ' : '' ) . 'Error uploading ' . esc_html( $filename . ':' . $errorUp );
                                        }
    
                                        $response['uploadRecLast'] = $destination . $filename;
                                        $uploads++;
                                    }
                                }
                            }
                        }
                    }
    
                    $response['uploadCount'] = $uploads;
    
                    // 1 file
                    if ( ! file_exists( $filepath ) ) {
                        $response['warning'] = 'Recording upload failed!';
                    }
    
                    if ( ! $response['warning'] && $scenario == 'chat' ) {
                        $url = self::path2url( $filepath );
    
                        $response['recordingUploadSize'] = filesize( $filepath );
                        $response['recordingUploadURL']  = $url;
    
                        $messageText       = '';
                        $messageUser       = $userName;
                        $userAvatar        = get_avatar_url( $userID, array( 'default' => dirname( plugin_dir_url( __FILE__ ) ) . '/images/avatar.png' ) );
                        $messageUserAvatar = esc_url_raw( $userAvatar );
    
                        $meta = array(
                            'userAvatar' => $messageUserAvatar,
                        );
    
                        if ( $mode == 'video' ) {
                            $meta['video'] = $url;
                        } else {
                            $meta['audio'] = $url;
                        }
    
                        $metaS = serialize( $meta );
    
                        // msg type: 2 web, 1 flash, 3 own notification
                        $sql = "INSERT INTO `$table_chatlog` ( `username`, `room`, `room_id`, `message`, `mdate`, `type`, `user_id`, `meta`, `private_uid`) VALUES ('$messageUser', '$roomName', '$roomID', '$messageText', $ztime, '2', '$userID', '$metaS', '$privateUID')";
                        $wpdb->query( $sql );
    
                        $response['sql'] = $sql;
    
                        $response['insertID'] = $wpdb->insert_id;
    
                        // also update chat log file
                        if ( $roomName ) {
                            if ( $messageText ) {
    
                                                    $messageText = strip_tags( $messageText, '<p><a><img><font><b><i><u>' );
    
                                                    $messageText = date( 'F j, Y, g:i a', $ztime ) . " <b>$userName</b>: $messageText <audio controls src='$url'></audio>";
    
                                                    $day = date( 'y-M-j', time() );
    
                                                    $dfile = fopen( $destination . "/Log$day.html", 'a' );
                                                    fputs( $dfile, $messageText . '<BR>' );
                                                    fclose( $dfile );
                            }
                        }
                    }
    
                    break;
    
                case 'options':
                    $name  = sanitize_file_name( $_POST['name'] );
                    $value = sanitize_file_name( $_POST['value'] );
    
                    if ( ! in_array( $name, array( 'requests_disable', 'room_private', 'room_random', 'calls_only', 'group_disabled', 'room_slots', 'room_conference', 'conference_auto', 'room_audio', 'room_text', 'vw_presentationMode', 'h5v_language', 'h5v_audio', 'h5v_sfx', 'h5v_dark', 'h5v_pip', 'h5v_min', 'h5v_reveal', 'h5v_reveal_warmup', 'party', 'party_reserved', 'stream_record', 'stream_record_all', 'stream_record_private', 'external_rtmp', 'goals_panel', 'goals_sort', 'gifts', 'question_closed' ) ) ) {
                        self::appFail( 'Preference not supported!' );
                    }
    
                    if ( substr( $name, 0, 3 ) == 'h5v' ) {
                        $userOption = 1;
                    } else {
                        $userOption = 0;
                    }
    
                    if ( ! is_user_logged_in() ) {
                        break; // visitors don't edit any preferences
                    }
    
                    if ( ! $session->broadcaster && ! $userOption ) {
                        $response['warning'] = __( 'Only room owner can edit room options.', 'live-streaming' );
                        break;
                    }
    
                    if ( $userOption ) {
                        update_user_meta( $userID, $name, $value );
                        update_user_meta( $userID, 'updated_options', time() );
                        $needUpdate['user'] = 1;
                    } else // room meta option
                    {
                        update_post_meta( $postID, $name, $value );
                        update_post_meta( $postID, 'updated_options', time() );
                    }
    
                    $needUpdate['options'] = 1;
    
                    if ( in_array( $name, array( 'room_slots', 'room_conference', 'vw_presentationMode' ) ) ) {
                        update_post_meta( $postID, 'updated_media', time() );
                        $needUpdate['media'] = 1;
                    }
    
                    if ( in_array( $name, array( 'room_random', 'room_conference', 'room_audio', 'room_text', 'vw_presentationMode', 'requests_disable', 'external_rtmp', 'goals_panel', 'goals_sort', 'gifts', 'question_closed' ) ) ) {
                        update_post_meta( $postID, 'updated_room', time() );
                        $needUpdate['room'] = 1;
                    }
    
                    if ( in_array( $name, array( 'stream_record', 'stream_record_private', 'stream_record_all' ) ) ) {
                        // update recording process faster?
                    }
    
                    break;
    
                case 'update':
                    // something changed - let everybody know (later implementation - selective updates, triggers)
                    $update = sanitize_file_name( $_POST['update'] );
                    update_post_meta( $postID, 'updated_' . $update, time() );
                    $needUpdate[ $update ] = 1;
    
                    break;
    
                // collaboration
    
                case 'user_kick':
                    if ( ! is_user_logged_in() ) {
                        self::appFail( 'Denied visitor.' . VW_H5V_DEVMODE );
                    }
    
                    // moderator
                    if ( ! $session->broadcaster ) {
                        $response['warning'] = __( 'Only performer can moderate.', 'live-streaming' );
                        break;
                    }
    
                    $TuserID   = intval( $_POST['userID'] );
                    $TuserName = sanitize_file_name( $_POST['userName'] );
    
                    $sqlS     = "SELECT * FROM `$table_sessions` WHERE uid='$TuserID' AND session='$TuserName' AND status='0' AND rid='$postID' LIMIT 1";
                    $Tsession = $wpdb->get_row( $sqlS );
    
                    if ( ! $Tsession ) {
                        $response['warning'] = "Participant not found to kick: #$TuserID $TuserName";
                        if ( VW_H5V_DEVMODE ) {
                            $response['warning'] .= " Dev: $sqlS";
                        }
                        break;
                    }
    
                    // prevent self block
                    $clientIP = self::get_ip_address();
                    if ( $clientIP == $Tsession->ip ) {
                        $response['warning'] = "Can not block own IP $clientIP for #$TuserID $TuserName";
                        break;
                    }
                    if ( $userID == $TuserID ) {
                        $response['warning'] = "Can not block own ID $userID for #$TuserID $TuserName";
                        break;
                    }
    
                    // block
                    $duration = 900; // 15 min
    
                    $bans = get_post_meta( $postID, 'bans', true );
                    if ( ! is_array( $bans ) ) {
                        $bans = array();
                    }
    
                    $ban    = array(
                        'user'    => $username,
                        'uid'     => $Tsession->uid,
                        'ip'      => $Tsession->ip,
                        'expires' => time() + $duration,
                        'by'      => $userName,
                    );
                    $bans[] = $ban;
    
                    update_post_meta( $postID, 'bans', $bans );
    
                    self::autoMessage( 'Kicked for 15 minutes: ' . "#$TuserID $TuserName", $session );
    
                    break;
    
                case 'user_ban':
                    if ( ! is_user_logged_in() ) {
                        self::appFail( 'Denied visitor.' . VW_H5V_DEVMODE );
                    }
    
                    // moderator
                    if ( ! $session->broadcaster ) {
                        $response['warning'] = __( 'Only performer can moderate.', 'live-streaming' );
                        break;
                    }
    
                    $TuserID   = intval( $_POST['userID'] );
                    $TuserName = sanitize_file_name( $_POST['userName'] );
    
                    $sqlS     = "SELECT * FROM `$table_sessions` WHERE uid='$TuserID' AND session='$TuserName' AND status='0' AND rid='$postID' LIMIT 1";
                    $Tsession = $wpdb->get_row( $sqlS );
    
                    if ( ! $Tsession ) {
                        $response['warning'] = "Participant not found to ban: #$TuserID $TuserName";
                        if ( VW_H5V_DEVMODE ) {
                            $response['warning'] .= " Dev: $sqlS";
                        }
                        break;
                    }
    
                    // prevent self block
                    $clientIP = self::get_ip_address();
                    if ( $clientIP == $Tsession->ip ) {
                        $response['warning'] = "Can not block own IP $clientIP for #$TuserID $TuserName";
                        break;
                    }
                    if ( $userID == $TuserID ) {
                        $response['warning'] = "Can not block own ID $userID for #$TuserID $TuserName";
                        break;
                    }
    
                    // block
                    $duration = 604800; // 7 days
    
                    $bans = get_post_meta( $postID, 'bans', true );
                    if ( ! is_array( $bans ) ) {
                        $bans = array();
                    }
    
                    $ban    = array(
                        'user'    => $username,
                        'uid'     => $Tsession->uid,
                        'ip'      => $Tsession->ip,
                        'expires' => time() + $duration,
                        'by'      => $userName,
                    );
                    $bans[] = $ban;
    
                    update_post_meta( $postID, 'bans', $bans );
    
                    self::autoMessage( 'Banned for 7 days: ' . "#$TuserID $TuserName", $session );
    
                    break;
                    case 'file_delete':
                        if ( ! is_user_logged_in() ) {
                            self::appFail( 'Denied visitor.' . VW_H5V_DEVMODE );
                        }
        
                        // moderator
                        if ( ! $session->broadcaster ) {
                            $response['warning'] = __( 'Only performer can delete files.', 'live-streaming' );
                            break;
                        }
        
                        $filename = sanitize_file_name( $_POST['file_name'] );
        
                        if ( ! $roomName ) {
                            self::appFail( 'No room.' );
                        }
                        if ( strstr( $filename, '.php' ) ) {
                            self::appFail( 'Bad.' );
                        }
        
                        $destination = $options['uploadsPath'] . "/$roomName/";
                        $file_path   = $destination . $filename;
        
                        if ( file_exists( $file_path ) ) {
                            unlink( $file_path );
                        } else {
                            $response['warning'] = __( 'File not found:', 'live-streaming' ) . ' ' . $filename;
                        }
        
                        // update list
                        update_post_meta( $postID, 'updated_files', time() );
                        $needUpdate['files'] = 1;
        
                        break;
        
                    case 'file_upload':
                        if ( !is_user_logged_in() ) {
                            self::appFail( 'Denied visitor.' );
                        }
        
                        $room = $roomName;
                        if ( ! $room ) {
                            self::appFail( 'No room.' );
                        }

        
                        $response['_FILES'] = $_FILES;
                        // $response['files'] = $_POST['files'];
        
                        $destination = sanitize_text_field( $options['uploadsPath'] ) . "/$room/";
                        if ( ! file_exists( $destination ) ) {
                            mkdir( $destination );
                        }
        
                        $allowed = array( 'swf', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'doc', 'docx', 'pdf', 'mp4', 'mp3', 'flv', 'avi', 'mpg', 'mpeg', 'webm', 'ppt', 'pptx', 'pps', 'ppsx', 'doc', 'docx', 'odt', 'odf', 'rtf', 'xls', 'xlsx' );
        
                        $uploads = 0;
        
                        if ( $_FILES ) {
                            if ( is_array( $_FILES ) ) {
                                foreach ( $_FILES as $ix => $file ) {
                                    $filename = sanitize_file_name( $file['name'] );
                                    if ( strstr( $filename, '.php' ) ) {
                                        self::appFail( 'Bad.' );
                                    }
                                    
                                    $filepath = $destination . $filename;
        
                                    $ext                       = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
                                    $response['uploadLastExt'] = $ext;
                                    $response['uploadLastF']   = $filename;
        
                                    if ( in_array( $ext, $allowed ) ) {
                                        if ( file_exists( $file['tmp_name'] ) ) {
                                            $errorUp = self::handle_upload( $file, $filepath ); // handle trough wp_handle_upload()
                                            
                                            if ( $errorUp ) {
                                                $response['warning'] = ( $response['warning'] ? $response['warning'] . '; ' : '' ) . 'Error uploading ' . esc_html( $filename . ':' . $errorUp );
                                            } else {
                                                                //add file in chat
                                                                $message =  __('New file upload.', 'live-streaming');
                                                                $meta = [ 'file_name' => $filename , 'file_url' => self::path2url( $filepath ), 'file_size' => self::humanSize( filesize( $filepath ) ) ];
                                                                        
                                                                if ( in_array($ext, ['jpg', 'jpeg', 'png', 'gif'] ) ) $meta['picture'] = $meta['file_url'];
                                                                if ( in_array($ext, ['mp4', 'webm'] ) ) $meta['video'] = $meta['file_url'];																					
                                                                if ( in_array($ext, ['mp3'] ) ) $meta['audio'] = $meta['file_url'];	
                                                                                        
                                                                self::autoMessage( $message, $session, $privateUID, $meta );
        
                                            }
        
                                            $response['uploadLast'] = $filepath;
        
                                            $uploads++;
                                        }
                                    }
                                }
                            }
                        }
        
                        $response['uploadCount'] = $uploads;
        
                        break;
                        
                        case 'external':
                            $external = ( $_POST['external'] == 'true' ? true : false );
            
                            if ( $session->meta ) {
                                $userMeta = unserialize( $session->meta );
                            }
                            if ( ! is_array( $userMeta ) ) {
                                $userMeta = array();
                            }
            
                            $userMeta['externalUpdate'] = time();
                            $userMeta['external']       = $external;
            
                            $userMetaS =  serialize( $userMeta );
                            $sql       = "UPDATE `$table_sessions` set meta='$userMetaS' WHERE id ='" . $session->id . "'";
                            $wpdb->query( $sql );
            
                            update_post_meta( $post->ID, 'updated_media', time() );
                            $needUpdate['media'] = 1;
                            break;
            
                            case 'snapshot':

                                $stream = $roomName;

                                $dir = $options['uploadsPath'];
                                if ( ! file_exists( $dir ) ) {
                                    mkdir( $dir );
                                }
                
                                $dir .= "/_snapshots";
                                if ( ! file_exists( $dir ) ) {
                                    mkdir( $dir );
                                }
                
                                // get snapshot data from H5V and save into a png file
                
                                //snapshot data from H5V
                                $data = $_POST['data'];
                
                                // Remove the metadata from the beginning of the data URI
                                $filteredData = substr($data, strpos($data, ",") + 1);
                
                                // Decode the Base64 encoded data
                                $decodedData = base64_decode($filteredData);
                
                                // Save a copy with custom name
                                if ($options['saveSnapshots']) 
                                {
                                    $dir2 = $options['uploadsPath'];
                                    if ( ! file_exists( $dir2 ) ) {
                                        mkdir( $dir2 );
                                    }
                    
                                    $dir2 .= "/$roomName";
                                    if ( ! file_exists( $dir2 ) ) {
                                        mkdir( $dir2 );
                                    }
                    
                                    $dir2 .= "/_snapshots";
                                    if ( ! file_exists( $dir2 ) ) {
                                        mkdir( $dir2 );
                                    }

                                    $filename2 =  $dir2 . '/' . $userID . '_' . time() . ".png";
                                    file_put_contents($filename2, $decodedData);

                                    $response['snapSavePath'] = $filename2;
                                }
                                
                                //overwrites last snapshot
                                $filename = $dir . '/' . $roomName . ".jpg";     
                                file_put_contents($filename, $decodedData);
                
                                $response['snapPath'] = $filename;
                
                                // generate thumb, in room (not private)
                                if ( file_exists( $filename ) && filesize( $filename ) > 0 )
                                {
                
                                    // may be old snapshot!!! maybe compare date with edate or store thumb date / check later
                                    $thumbTime = get_post_meta( $postID, 'thumbTime', true );
                                    $fileTime  = filemtime( $filename );
                                    if ( $thumbTime && $fileTime <= $thumbTime ) {
                                        break; // old file, already processed
                                    }

                                    // if snapshot successful (from stream) update edate
                                    $ztime = time();
                                    update_post_meta( $postID, 'edate', $ztime );
                                    update_post_meta( $postID, 'vw_lastSnapshot', $filename );

                                    // generate thumb
                                    $thumbWidth  = $options['thumbWidth'];
                                    $thumbHeight = $options['thumbHeight'];

                                    $src                  = imagecreatefrompng( $filename );
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
                
                                break;

                        case 'media':
                            // notify user media (streaming) updates
            
                            $connected = ( $_POST['connected'] == 'true' ? true : false );
            
                            if ( $session->meta ) {
                                $userMeta = unserialize( $session->meta );
                            }
                            if ( ! is_array( $userMeta ) ) {
                                $userMeta = array();
                            }
            
                            // if ($options['debugMode']) $userMeta['updateMediaMeta'] = $session->meta;
            
                            $userMeta['connected']       = $connected;
                            $userMeta['connectedUpdate'] = time();
            
                            // also update external broadcast info on web media publishing
                            $webStatusInterval = $options['webStatusInterval'] ?? 60;
                            if ( $webStatusInterval < 10 ) {
                                $webStatusInterval = 60;
                            }
            
                            if ( array_key_exists( 'externalUpdate', $userMeta ) ) {
                                if ( $userMeta['externalUpdate'] < time() - $webStatusInterval ) {
                                    $userMeta['external'] = false;
                                }
                            }
            
                            $userMetaS = serialize( $userMeta );
                            $sql = "UPDATE `$table_sessions` set meta='$userMetaS' WHERE id ='" . $session->id . "'";
                            $wpdb->query( $sql );
            
                            $response['taskSQL'] = $sql;
            
                            // auto  assign enabled
                            $conference      = self::is_true( get_post_meta( $post->ID, 'room_conference', true ) );
                            $conference_auto = self::is_true( get_post_meta( $post->ID, 'conference_auto', true ) ) && !self::isModerator($userID, $options);
                            
                            if ( $conference && $conference_auto ) {
            
                                $presentationMedia = self::appRoomMedia( $post, $session, $options );
            
                                $items = 0;
            
                                // remove on disconnect
                                if ( ! $connected ) {
                                    foreach ( $presentationMedia as $placement => $content ) {
                                        ++$items;
                                        if ( $content['userName'] == $userName && $content['userID'] == $userID && $content['type'] == 'user' ) {
            
                                            $content                         = array(
                                                'name' => 'Slot' . $items,
                                                'type' => 'empty',
                                                'by'   => $userName,
                                            );
                                            $presentationMedia[ $placement ] = $content;
                                            update_post_meta( $post->ID, 'presentationMedia', $presentationMedia );
                                            update_post_meta( $post->ID, 'updated_media', time() );
                                            $needUpdate['media'] = 1;
                                        }
                                    }
                                }
            
                                if ( ! $connected ) {
                                    break; // top switch
                                }
            
                                $performer = get_post_meta( $post->ID, 'performer', true );
                                if ($userName == $performer) if ( !array_key_exists('type', $presentationMedia['Main']) ) break; //no need to add performer to extra slots, unless main defined otherwise
            
                                // connected and already present: break
                                foreach ( $presentationMedia as $placement => $content ) {
                                    if ( $content['userName'] == $userName && $content['type'] == 'user' ) {
                                        break 2; // both this foreach and top switch
                                    }
                                }
            
                                // add on connect
                                foreach ( $presentationMedia as $placement => $content ) {
                                    if ( $content['type'] == 'empty' ) {
            
                                        $content = array(
                                            'type'     => 'user',
                                            'stream'   => self::appStreamPlayback( $userID, $userID, $post, $options ),
                                            'name'     => $userName,
                                            'userID'   => $userID,
                                            'userName' => $userName,
                                            'by'       => $userName,
                                            'auto'     => 1,
                                        );
            
                                        $presentationMedia[ $placement ] = $content;
                                        update_post_meta( $post->ID, 'presentationMedia', $presentationMedia );
                                        update_post_meta( $post->ID, 'updated_media', time() );
                                        $needUpdate['media'] = 1;
                                        break 2; // both this foreach and top switch
                                    }
                                    // not found
            
                                }
                            }
            
                            /*
                            $usersMeta = get_post_meta( $postID, 'vws_usersMeta', true);
                                if (!is_array($users)) $usersMeta = array();
                                if (!array_key_exists($userID, $users)) $usersMeta[$userID] = array();
            
            
                                $usersMeta[$userID]['connected'] = $connected;
                                $usersMeta[$userID]['username'] = $session->username;
                                $usersMeta[$userID]['updated'] = $ztime;
            
                                update_post_meta( $postID, 'vws_usersMeta', $usersMeta);
                                */
            
                            // if ($userID) update_user_meta($userID, 'html5_media', $ztime);
                            break;
            
                        case 'goal_add':
                            if ( ! is_user_logged_in() ) {
                                self::appFail( 'Denied visitor.' . VW_H5V_DEVMODE );
                            }
                            if ( ! $session->broadcaster ) {
                                $response['warning'] = __( 'Only performer', 'live-streaming' );
                                break;
                            }
            
                            // load goals
                            $goals = self::appRoomGoals( $post->ID, $options );
            
                            // name: this.state.name, description: this.state.description, amount: this.state.amount, independent: this.state.independent, order: this.state.order
            
                            $newIndex = intval( $_POST['order'] );
            
                            if ( array_key_exists( $newIndex, $goals ) ) {
                                // increase from there
                                foreach ( $goals as $ix => $goal ) {
                                    if ( $ix >= $newIndex ) {
                                        $goals[ $ix ]['ix'] = $ix + 1;
                                    }
                                }
                            }
            
                            $newGoals = array();
                            foreach ( $goals as $ix => $goal ) {
                                $newGoals[ $goal['ix'] ] = $goal;
                            }
            
                            $newGoal               = array(
                                'ix'          => $newIndex,
                                'name'        => sanitize_text_field( $_POST['name'] ),
                                'description' => sanitize_text_field( $_POST['description'] ),
                                'amount'      => intval( $_POST['amount'] ),
                                'independent' => boolval( $_POST['independent'] ),
                            );
                            $newGoals[ $newIndex ] = $newGoal;
            
                            // new goals with maching order keys
                            ksort( $newGoals );
            
                            update_post_meta( $post->ID, 'goals', $newGoals );
                            $needUpdate['room'] = 1;
            
                            $response['newGoalsCount'] = count( $newGoals );
                            $response['newGoals']      = serialize( $newGoals );
            
                            break;
            
                        case 'goals_reset':
                            if ( ! is_user_logged_in() ) {
                                self::appFail( 'Denied visitor.' . VW_H5V_DEVMODE );
                            }
            
                            // moderator
                            if ( ! $session->broadcaster ) {
                                $response['warning'] = __( 'Only performer', 'live-streaming' );
                                break;
                            }
            
                            $goals = self::appRoomGoals( $post->ID, $options );
            
                            foreach ( $goals as $ix => $value ) {
                                $goals[ $ix ]['current']   = 0;
                                $goals[ $ix ]['cumulated'] = 0;
                                $goals[ $ix ]['ix']        = $ix;
                            }
            
                            $goal = array_values( $goals )[0]; // first goal
            
                            update_post_meta( $post->ID, 'goal', $goal );
                            update_post_meta( $post->ID, 'goals', $goals );
                            $needUpdate['room'] = 1;
            
                            break;
            
                        case 'goal_complete':
                            if ( ! is_user_logged_in() ) {
                                self::appFail( 'Denied visitor.' . VW_H5V_DEVMODE );
                            }
                            if ( ! $session->broadcaster ) {
                                $response['warning'] = __( 'Only performer', 'live-streaming' );
                                break;
                            }
            
                            $ix = intval( $_POST['index'] );
            
                            $goals = self::appRoomGoals( $post->ID, $options );
            
                            if ( array_key_exists( $ix, $goals ) ) {
            
                                $goal = $goals[ $ix ];
            
                                $meta['progressValue']   = $goal['current'];
                                $meta['progressTotal']   = $goal['amount'];
                                $meta['progressDetails'] = $goal['name'];
                                $message                .= "\n" . __( 'Performer marked goal as complete', 'live-streaming' ) . ': ' . $goal['name'] . "\n" . $goal['completedDescription'] . "\n";
            
                                $goals[ $ix ]['current'] = 0;
                                update_post_meta( $post->ID, 'goals', $goals );
                                
                                if ($privateSession) if ( isset($privateSession['id']) ) $meta['privateSession'] = $privateSession['id'];
            
                                self::autoMessage( $message, $session, $privateUID, $meta );
            
                                $needUpdate['room'] = 1;
                            } else {
                                $response['warning'] = 'Goal index not found: ' . $ix;
                            }
            
                            break;
            
                        case 'goal_delete':
                            if ( ! is_user_logged_in() ) {
                                self::appFail( 'Denied visitor.' . VW_H5V_DEVMODE );
                            }
                            if ( ! $session->broadcaster ) {
                                $response['warning'] = __( 'Only performer', 'live-streaming' );
                                break;
                            }
            
                            $ix = intval( $_POST['index'] );
            
                            $goals = self::appRoomGoals( $post->ID, $options );
            
                            if ( array_key_exists( '', $goals ) ) {
                                unset( $goals[''] );
                                update_post_meta( $post->ID, 'goals', $goals );
                                $needUpdate['room'] = 1;
                            }
            
                            if ( array_key_exists( $ix, $goals ) ) {
                                unset( $goals[ $ix ] );
                                update_post_meta( $post->ID, 'goals', $goals );
                                $needUpdate['room'] = 1;
                            } else {
                                $response['warning'] = 'Goal index not found: ' . $ix;
                            }
            
                            break;
                            
                        case 'tipProcessed':
                        
                                        $key = sanitize_text_field( $_POST['key'] );	
            
                                         $tipsRecent = get_user_meta( $userID, 'tipsRecent', true );
                                        if (!is_array($tipsRecent)) $tipsRecent = [];
                
                                        if (array_key_exists($key, $tipsRecent)) unset($tipsRecent[$key]);
                                        
                                        foreach ($tipsRecent as $key => $tip) if (time() - $tip['time'] > 600) unset($tipsRecent[$key]); //erase older than 10min
                                        update_user_meta( $userID, 'tipsRecent', $tipsRecent );
                                        $response['tipsRecent'] = $tipsRecent;
                                        
                                        $response['user']['tipsRecent'] = $tipsRecent;
                        break;
            
                        case 'tip':
                            $error = '';
                            if ( ! $userID ) {
                                $error = __( 'Only users can tip!', 'live-streaming' );
                            }
                            
                            if ( self::isModerator($userID, $options) ) $error = __( 'Moderators can not tip!', 'live-streaming' );
            
                            if ( !self::rolesUser( $options['rolesDonate'], get_userdata(  $userID ) ) ) $error = __( 'Your role is not allowed to donate!', 'live-streaming' );
            
                            $response['warning'] = $error;
                            if ( $error ) {
                                break;
                            }
            
                            if ( $options['tipCooldown'] ) {
                                $lastTip = intval( get_user_meta( $userID, 'vwTipLast', true ) );
                                if ( $lastTip + $options['tipCooldown'] > time() ) {
                                    $error = __( 'Cooldown Required: Already sent tip recently. Try again in few seconds!', 'live-streaming' );
                                }
                            }
                            $response['warning'] = $error;
                            if ( $error ) {
                                break;
                            }
            
                            $tip = isset( $_POST['tip'] ) ? (array) $_POST['tip'] : array(); // array elements sanitized individually
            
                            $tipsURL  = sanitize_text_field( $_POST['tipsURL'] );
                            $targetID = intval( $_POST['targetID'] ); // tip recipient
            
                            $label  = wp_encode_emoji( sanitize_text_field( $tip['attributes']['LABEL'] ) );
                            $amount = intval( $tip['attributes']['AMOUNT'] );
                            $note   = wp_encode_emoji( sanitize_text_field( $tip['attributes']['NOTE'] ) );
                            $sound  = sanitize_text_field( $tip['attributes']['SOUND'] );
                            $image  = sanitize_text_field( $tip['attributes']['IMAGE'] );
                            $color  = sanitize_text_field( $tip['attributes']['COLOR'] );
            
                            $meta          = array();
                            $meta['sound'] = $tipsURL . $sound;
                            $meta['image'] = $tipsURL . $image;
                            $meta['tip']   = true;
            
                            if ($privateSession) if ( isset($privateSession['id']) ) $meta['privateSession'] = $privateSession['id'];
            
            
                            if ( ! $label ) {
                                $error = 'No tip message!';
                            }
                            $response['warning'] = $error;
                            
            
                            if ( ! $error ) {
                                $message = $label . ': ' . $note;
            
                                $message = preg_replace( '/([^\s]{48})(?=[^\s])/', '$1' . '<wbr>', $message ); // break long words <wbr>:Word Break Opportunity
            
                                $private = 0;
            
                                // tip
                                $balance                        = self::balance( $userID, true, $options );
                                $response['tipSuccess']         = 1;
                                $response['tipBalancePrevious'] = $balance;
                                $response['tipAmount']          = $amount;
            
                                if ( $amount > $balance ) {
                                    $response['tipSuccess'] = 0;
                                    $response['warning']    = "Tip amount ($amount) greater than available balance ($balance)! Not processed.";
                                } else {
            
                                    $ztime = time();
            
                                    // client cost
                                    $paid = number_format( $amount, 2, '.', '' );
                                    self::transaction( 'ppv_tip', $userID, - $paid, __( 'Tip in', 'live-streaming' ) . ' <a href="' . self::roomURL( $post->post_title ) . '">' . esc_html($post->post_title). '</a>. (' . $label . ')', $ztime );
                                    $response['tipPaid'] = $paid;
            
                                    // checking
                                    $roomOptions = unserialize( $session->roptions );
                                    $checkin     = $roomOptions['checkin'];
                                    
                                    if ( $checkin ) {
            
                                        if ( ! is_array( $checkin ) ) {
                                            $checkin = array( $checkin );
                                        }
            
                                        $divider = count( $checkin );
                                        if ( ! $divider ) {
                                            return;
                                        }
            
                                        $checkinComment = '';
                                        if ( $divider > 1 ) {
                                            $checkinComment = ' ' . __( 'checked in', 'live-streaming' ) . ' x' . $divider;
                                        }
            
                                        $received = number_format( $amount * $options['tipRatio'], 2, '.', '' );
            
                                        $share = number_format( $received / $divider, 2, '.', '' );
            
                                        foreach ( $checkin as $performerID ) 
                                        {
                                        self::transaction( 'ppv_tip_share', $performerID, $share, __( 'Tip from', 'live-streaming' ) . ' ' . $userName . ' (' . $label . ')' . ' ' . $timeStamp . $checkinComment .  ' @<a href="' . self::roomURL( $post->post_title ) . '">' . esc_html($post->post_title). '</a>', $session->id );
                                            
                                        //update tipsReceived
                                        $tip = [ 'amount' => $amount, 'clientID' => $userID, 'client' => $userName, 'received' => $share, 'time' => time() ];				
                                        $tipsRecent = get_user_meta( $performerID, 'tipsRecent', true );
                                        if (!is_array($tipsRecent)) $tipsRecent = [];
                                        $tipsRecent[$userID.'_'.time()] = $tip;
                                        update_user_meta( $performerID, 'tipsRecent', $tipsRecent );
                                                                                                
                                        }
                                    } else {
                                        // single performer earning
                                        $received = number_format( $amount * $options['tipRatio'], 2, '.', '' );
                                        self::transaction( 'ppv_tip_earn', $targetID, $received, __( 'Tip from', 'live-streaming' ) . ' ' . $userName . ' (' . $label . ')' .  ' @<a href="' . self::roomURL( $post->post_title ) . '">' . esc_html($post->post_title). '</a>', $ztime );							
                                        
                                        //update tipsRecent
                                        $tip = [ 'amount' => $amount, 'clientID' => $userID, 'client' => $userName,'received' => $received, 'time' => time() ];				
                                        $tipsRecent = get_user_meta( $targetID, 'tipsRecent', true );
                                        if (!is_array($tipsRecent)) $tipsRecent = [];
                                        $tipsRecent[$userID.'_'.time()] = $tip;
                                        update_user_meta( $targetID, 'tipsRecent', $tipsRecent );
                                    }
            
                                    // save last tip time
                                    update_user_meta( $userID, 'vwTipLast', time() );
            
                                    $response['tipTargetID'] = $targetID;
                                    $response['tipReceived'] = $received;
            
                                    // gifts button in actions bar
                                    $gifts = self::is_true( get_post_meta( $postID, 'gifts', true ) );
            
                                    // goals
                                    if ( $options['goals'] ) {
            
                                        if ( $independent = sanitize_text_field( $_POST['independent'] ) ) {
                                            $goal = self::goalIndependent( $postID, $independent, $paid, $options ); // to independent goal
                                        } else {
                                            $goal = self::goal( $postID, $paid, $options ); // to current goal
                                        }
            
                                        if ( $goal ) {
                                            $meta['progressValue']   = $goal['current'];
                                            $meta['progressTotal']   = $goal['amount'];
                                            $meta['progressDetails'] = $goal['name'];
            
                                            if ( $goal['completed'] ) {
                                                $message .= "\n" . __( 'Completed goal', 'live-streaming' ) . ': ' . $goal['completed'] . "\n" . $goal['completedDescription'] . "\n" . __( 'Starting new goal', 'live-streaming' ) . ':';
                                            }
                                            $needUpdate['room'] = 1;
                                        }
                                    }
            
                                    if ($privateSession) if ( isset($privateSession['id']) ) $meta['privateSession'] = $privateSession['id'];
            
                                    $response['tipSQLmsg'] = self::autoMessage( $message, $session, $privateUID, $meta );
                                    $response['tipMessage']                        = $message;
            
                                }
                            }
            
                            break;
                            case 'message':
                                $message = isset( $_POST['message'] ) ? (array) $_POST['message'] : ''; // array elements sanitized individually
                                if ( ! $message ) {
                                    break;
                                }
                
                                $messageText       =  wp_encode_emoji( sanitize_textarea_field( $message['text'] ) );
                                $messageUser       = sanitize_text_field( $message['userName'] );
                                $messageUserAvatar = esc_url_raw( $message['userAvatar'] );
                
                                $meta  = array(
                                    'notification'   => $message['notification'],
                                    'userAvatar'     => $messageUserAvatar,
                                    'mentionMessage' => intval( $message['mentionMessage'] ),
                                    'mentionUser'    => sanitize_text_field( $message['mentionUser'] ),
                                );
                
                                if ( isset( $message[ 'language' ] ) ) 
                                {
                                    $meta[ 'language' ] = sanitize_text_field( $message[ 'language' ] );
                                    $meta[ 'flag' ] = sanitize_text_field( $message[ 'flag' ] );
                                } else if ( $options['multilanguage'] && $options['languageDefault'] )
                                {
                                    $meta[ 'language' ] = $options['languageDefault'];
                                    $meta[ 'flag' ] = self::language2flag($meta[ 'language' ]);
                                }             
                                            
                                $metaS =  serialize( $meta ) ;
                
                
                                if ( ! $privateUID ) {
                                    $privateUID = 0; // public room
                                }
                
                                // msg type: 2 web, 1 flash, 3 own notification
                                $sql = "INSERT INTO `$table_chatlog` ( `username`, `room`, `room_id`, `message`, `mdate`, `type`, `user_id`, `meta`, `private_uid`) VALUES ('$messageUser', '$roomName', '$roomID', '$messageText', $ztime, '2', '$userID', '$metaS', '$privateUID')";
                                $wpdb->query( $sql );
                
                                $response['sql'] = $sql;
                
                                $response['insertID'] = $wpdb->insert_id;
                
                                // also update chat log file
                                if ( $roomName ) {
                                    if ( $messageText ) {
                
                                        $messageText = strip_tags( $messageText, '<p><a><img><font><b><i><u>' );
                
                                        $messageText = date( 'F j, Y, g:i a', $ztime ) . " <b>$userName</b>: $messageText";
                
                                        // generate same private room folder for both users
                                        $proom = '';
                                        if ( $privateUID ?? false ) {
                                            if ( $isPerformer ) {
                                                $proom = $userID . '_' . $privateUID; // performer id first
                                            } else {
                                                $proom = $privateUID . '_' . $userID;
                                            }
                                        }
                
                                        $dir = $options['uploadsPath'];
                                        if ( ! file_exists( $dir ) ) {
                                            mkdir( $dir );
                                        }
                
                                        $dir .= "/$roomName";
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
                                        fputs( $dfile, $messageText . '<BR>' );
                                        fclose( $dfile );
                                    }
                                }
                
                                break;            
                default:
                    $response['error'] = 'Invalid task: '  . esc_attr($task);
                    break;
            }

        //! Messages
        
        // update time
		$lastMessage   = intval( $_POST['lastMessage'] ?? 0 );
		$lastMessageID = intval( $_POST['lastMessageID'] ?? 0 );

		// retrieve only messages since user came online / updated
		$sdate = 0;
		if ( $session ) {
			$sdate = $session->sdate;
		}
		$startTime = max( $sdate, $lastMessage );

		$response['startTime'] = $startTime;

		// clean old chat logs
		$closeTime = time() - 900; // only keep for 15min
		$sql       = "DELETE FROM `$table_chatlog` WHERE mdate < $closeTime";
		$wpdb->query( $sql );

		$items = array();

		$cndNotification = "AND (type < 3 OR (type=3 AND user_id='$userID' AND username='$userName'))"; // chat message or own notification (type 3)

		$cndPrivate = "AND private_uid = '0'";
		if ( $privateUID ?? false ) {
			$cndPrivate = "AND ( (private_uid = '$privateUID' AND user_id = '$userID') OR (private_uid ='$userID' AND user_id = '$privateUID') )"; // messages in private from each to other
		}

		$cndTime = "AND mdate >= $startTime AND mdate <= $ztime AND id > $lastMessageID";

		$sql = "SELECT * FROM `$table_chatlog` WHERE room='$roomName' $cndNotification $cndPrivate $cndTime ORDER BY mdate DESC LIMIT 0,100"; // limit to last 100 messages, until processed date
		$sql = "SELECT * FROM ($sql) items ORDER BY mdate ASC"; // but order ascendent

		$response['sqlMessages'] = $sql;

		$sqlRows = $wpdb->get_results( $sql );

		$idMax = 0;
		if ( $wpdb->num_rows > 0 ) {
			foreach ( $sqlRows as $sqlRow ) {
				$item = array();

				$item['ID'] = intval( $sqlRow->id );

				if ( $item['ID'] > $idMax ) {
					$idMax = $item['ID'];
				}

				$item['userName'] = $sqlRow->username;
				$item['userID']   = intval( $sqlRow->user_id );

				$item['text'] = html_entity_decode( stripslashes( $sqlRow->message ) );
				$item['time'] = intval( $sqlRow->mdate * 1000 ); // time in ms for js

				// avatar
				$uid = $sqlRow->user_id;
				if ( ! $uid ) {
					$wpUser = get_user_by( $userName, $sqlRow->username );
					if ( ! $wpUser ) {
						$wpUser = get_user_by( 'login', $sqlRow->username );
					}
                    if ($wpUser) $uid = $wpUser->ID;
                    else $uid = 0;
				}

				$item['userAvatar'] = get_avatar_url( $uid );

				// meta
				if ( $sqlRow->meta ) {
					$meta = unserialize( $sqlRow->meta );
					foreach ( $meta as $key => $value ) {
						$item[ $key ] = $value;
					}

					$item['notification'] = ( isset($meta['notification']) && $meta['notification'] == 'true' ? true : false );
				}

				if ( $sqlRow->type == 3 ) {
					$item['notification'] = true;
				}
				
				$skipItem = 0;
                				
				if (!$skipItem) $items[] = $item;
			}
		}

		$response['messages'] = $items; // messages list

		$response['timestamp'] = $ztime; // update time

		$response['lastMessageID'] = $idMax;


		// balance and room updates, except on login
		if ( $task != 'login' ) {
			
			$lastRoomUpdate = intval( $_POST['lastRoomUpdate'] ?? 0 );

			$balance        = floatval( self::balance( $userID, false, $options ) );
			$balancePending = floatval( self::balance( $userID, true, $options ) );

			// update user only if does not exist - if (!$privateUID)
			if ( ! array_key_exists( 'user', $response ) ) 
			{
				$response['user'] =
					array(
						'from' => 'balanceUpdate',
						'loggedIn'       => true,
						'balance'        => number_format( $balance, 2, '.', ''  ),
						'balancePending' => number_format( $balancePending, 2, '.', ''  ),
						'cost'           => ( array_key_exists( 'cost', $userMeta ) ? $userMeta['cost'] : 0 ),
						'time'           => ( $session->edate - $session->sdate ),
					);
					
						 if ($options['lovense'] && $isPerformer) 
						 {	
							 //recent tips to process
			 				$tipsRecent = get_user_meta( $userID, 'tipsRecent', true );
							if (!is_array($tipsRecent)) $tipsRecent = [];
							foreach ($tipsRecent as $key => $tip) if (time() - $tip['time'] > 600) unset($tipsRecent[$key]); //erase older than 10min
							update_user_meta( $userID, 'tipsRecent', $tipsRecent );
							$response['user']['tipsRecent'] = $tipsRecent;
						}

			}

			if ( $balance < 0 ) {
				$response['error'] = 'Error: Negative balance. Can be a result of enabling cache for user requests. Contact site administrator to review and fix balance.';
			}

			// balance

			$updateTime = get_user_meta( $userID, 'updated_options', true );
			if ( $updateTime ) {
				if ( $updateTime > $lastRoomUpdate ) {
					$needUpdate['user'] = 1;
				}
			}
			if ( $needUpdate['user'] ) {
				$response['user']['options']            = self::appUserOptions( $session, $options );
				$response['user']['options']['updated'] = true;
			}

			// update room
			if ( ! $changedRoom ) {

				// items that need update: for everybody
				foreach ( array( 'files', 'media', 'options', 'room' ) as $update ) {
					if ( ! $needUpdate[$update] ) {
						$updateTime = get_post_meta( $postID, 'updated_' . $update, true );
						if ( $updateTime ) {
							if ( $updateTime > $lastRoomUpdate ) {
								$needUpdate[ $update ] = 1; // change after last msg: need update		
							} 
						}
					}
				}				

				// $needUpdate[] - send items marked for update
				if ( $needUpdate['room'] && ! $privateUID ) {
					$response['roomUpdate'] = self::appPublicRoom( $post, $session, $options, '', $response['roomUpdate'], $requestUID ); // no room update during private
				} else // update room in full or just sections
					{
					
					if ( $needUpdate['files'] ) {
						$response['roomUpdate']['files'] = self::appRoomFiles( $roomName, $options );
					}
					
					if ( $needUpdate['media'] ) {
						$response['roomUpdate']['media'] = self::appRoomMedia( $post, $session, $options );
					}
					
					if ( $needUpdate['options'] ) {
						$response['roomUpdate']['options'] = self::appRoomOptions( $post, $session, $options );
					}	

				}

				$response['roomUpdate']['users']   = self::appRoomUsers( $post, $options ); // always update online users list
				$response['roomUpdate']['updated'] = $ztime;
			}
		}

		echo json_encode( $response );
		die();

}


static function appPublicRoom( $post, $session, $options, $welcome = '', &$room = null, $requestUID = 0 ) {
    // public room parameters, specific for this user

    if ( ! $room ) {
        $room = array();
    }

    $room['ID']   = $post->ID;
    $room['privateUID'] = 0; 
    
    $room['name'] = sanitize_file_name( $post->post_title );

    $room['performer']   = sanitize_file_name( $post->post_title );
    $room['performerID'] = intval( get_post_meta( $post->ID, 'performerUserID', true ) );
    
    if ( ! $room['performerID'] ) {
        $room['performerID'] = intval( $post->post_author );
    }

    $collaboration = self::is_true( get_post_meta(  $post->ID, 'vw_presentationMode', true ) );
    $conference = self::is_true( get_post_meta( $post->ID, 'room_conference', true ) );

    $room['audioOnly'] = self::is_true( get_post_meta( $post->ID, 'room_audio', true ) );
    $room['textOnly']  = self::is_true( get_post_meta( $post->ID, 'room_text', true ) );

    $appComplexity = ( $options['appComplexity'] == '1' || ( $session->broadcaster && $options['appComplexity'] == '2' ) );

    // screen
    if ( $session->broadcaster ) {
        $roomScreen = 'BroadcastScreen';
    } else {
        $roomScreen = 'PlaybackScreen';
    }

    if ( $room['audioOnly'] ) { // audion only layouts
        if ( $session->broadcaster ) {
            $roomScreen = 'BroadcastAudioScreen';
        } else {
            $roomScreen = 'PlaybackAudioScreen';
        }
    }

    if ( $room['textOnly'] ) {
        $roomScreen = 'TextScreen';
    }

    if ( $conference || $collaboration || $appComplexity ) {
        if ( $room['textOnly'] ) {
            $roomScreen = 'CollaborationTextScreen';
        } else {
            $roomScreen = 'CollaborationScreen';
        }
    }

        $room['screen'] = $roomScreen;

        $streamName = $room['performer'];

    // only performer receives broadcast keys in public room
    if ( $session->broadcaster ) {
        $room['streamBroadcast'] = self::appStreamBroadcast( $session->uid, $post, $options );
        $room['streamNameBroadcast'] = $session->username;
    } else {
        $room['streamBroadcast'] = '';
        $room['streamNameBroadcast'] = '';
    }
    
    
     $isModerator = self::isModerator($session->uid, $options);


    $room['streamUID']      = intval( $room['performerID'] );
    $room['streamPlayback'] = self::appStreamPlayback( $session->uid, $room['streamUID'], $post, $options );
    $room['streamNamePlayback'] =  array_shift( explode( '?', $room['streamPlayback'] ) );

    $room['actionID'] = 0;

    $room['welcome']      = ' 💬 ' . sprintf( 'Welcome to public room #%d "%s", %s!', $post->ID, sanitize_file_name( $post->post_title ), $session->username );
    $room['welcomeImage'] = dirname( plugin_dir_url( __FILE__ ) ) . '/images/chat.png';

    if (VW_H5VLS_DEVMODE) $room['welcome'] .= "\nDEVMODE";

    if ( $session->broadcaster ) {
        $room['welcome'] .= "\n" . __( 'You are room broadcaster.', 'live-streaming' );
    }

    $private = self::is_true( get_post_meta( $post->ID, 'room_private', true ) );
    if ( $private ) {
        $room['welcome'] .= "\n" . __( 'This is a private room (not listed).', 'live-streaming' );
    }

    //streams
    if ( $options['reStreams'] ?? false )
    {
        
        //room stream mode?
        $streamType = get_post_meta(  $post->ID, 'stream-mode', true );
        if ($streamType == 'stream')
        {
            $streamName = get_post_meta( $post->ID, 'stream-name', true ) ;
            $room['streamName'] = $streamName ;
            $room['streamAddress'] = ($options['httprestreamer'] ? $options['httprestreamer']  : $options['httpstreamer']) . $streamName . '/playlist.m3u8' ;
            
            if ($options['debugMode']) $room['welcome'] .= "\nStream HLS: " . $room['streamHLS'];
        }
        else 
        {
            $room['streamName'] = '';
            $room['streamAddress'] = '';		
        }
        

        //stream list
        $streams = [];
        
        $reStreams = get_post_meta(  $post->ID, 'reStreams', true );
        if ( !$reStreams ) $reStreams = [];
        if ( !is_array($reStreams) ) $reStreams = [];
            
            //add streams
            foreach ($reStreams as $streamName => $address) 
            {
                $streamLabel = str_replace('.stream', '', $streamName );
                $streams[ $streamName ] = [ 'name' => $streamLabel, 'key' => $streamLabel, 'stream' => $streamName, 'address' => ($options['httprestreamer'] ? $options['httprestreamer']  : $options['httpstreamer']) . $streamName . '/playlist.m3u8' ];	
            }
            
        if ( count($streams) ) $room['streams'] = $streams;
        
        if ( $options['debugMode'] ) $room['welcome'] .= "\nRoom Streams: " . count($streams);
        
        $room['streamsAdmin'] = $session->broadcaster ? true : false ; //admin for room
    }
    


    
     if ( $isModerator && !$session->broadcaster ) $room['welcome'] .= "\n 🕵️" .  __( 'You are a moderator. You will not show in user list or generate client charges.', 'live-streaming' );

    if ( ! $session->uid ) {
        $room['actionPrivate'] = false;
        $room['welcome']      .= "\n" . 'Your are not logged in: Please register and login to access more advanced features!';
    };

    if ( $welcome ) {
        $room['welcome'] .= "\n" . $welcome;
    }

//room snapshot
$dir    = $options['uploadsPath'] . '/_snapshots';
$stream = sanitize_file_name( $post->post_title );
$snapshot = "$dir/$stream.jpg";

//even picture
$showImage = get_post_meta( $post->ID, 'showImage', true );
     if ( $showImage == 'all' ) {
         // get post thumb
         $thumbFilename = '';
         $attach_id = get_post_thumbnail_id( $post->ID );
         if ( $attach_id ) $thumbFilename = get_attached_file( $attach_id );
         if ( $thumbFilename && file_exists( $thumbFilename ) ) $snapshot = self::path2url( $thumbFilename );   
     }

if ( !file_exists($snapshot) ) $snapshot = '';
$room['snapshot'] = $snapshot ? self::path2url( $snapshot ) : '';

// offline teaser video
$videoOffline = '';
$offline_video = get_post_meta( $post->ID, 'offline_video', true );
if ( $offline_video )  
{
    $videoOffline = self::vsvVideoURL( $offline_video, $options );
    $videoSnapshot = get_post_meta( $offline_video, 'video-snapshot', true );
    if ( $videoSnapshot && file_exists( $videoSnapshot) ) $snapshot = self::path2url( $videoSnapshot );
}

$room['videoOffline'] = $videoOffline;
    
    // panel reset
    $room['panelCamera']       = false;
    $room['panelUsers']        = false;
    $room['panelOptions']      = false;
    $room['panelFiles']        = false;
    $room['panelPresentation'] = false;

    // collaboration
    if ( $collaboration ) {
        $room['welcome'] .= "\n " . __( 'Room is in collaboration mode, with a Files panel.', 'live-streaming' );
        $room['files']    = self::appRoomFiles( $room['name'], $options );

        $room['panelFiles'] = true;

        $room['filesUpload'] = VW_H5VLS_DEVMODE || is_user_logged_in();
        
        $room['filesDelete'] = boolval( $session->broadcaster );

        $room['filesPresentation'] = boolval( $session->broadcaster );
        $room['panelPresentation'] = boolval( $session->broadcaster );
    }

     $room['filesUpload'] = self::appRole( $session->uid, 'filesUpload', boolval( $session->broadcaster  || ( $collaboration && is_user_logged_in() ) ), $options );


    // room media (split view), including when disabled to reset to 1 slot
    $room['media'] = self::appRoomMedia( $post, $session, $options );

    if ( $conference || $collaboration ) {
        // all users can broadcast

        $panelCamera = self::appRole( $session->uid, 'conferenceParticipantCamera', boolval( $session->uid > 0 ), $options );

        if ( VW_H5V_DEVMODE || $panelCamera ) {
            $room['panelCamera']     = true;
            $room['streamBroadcast'] = self::appStreamBroadcast( $session->uid, $post, $options );
        }

        // assign user to media slots
        $room['usersPresentation'] = boolval( $session->broadcaster );
    } elseif ( $appComplexity ) {

        if ( $session->broadcaster ) {
            $room['panelCamera']     = true;
            $room['streamBroadcast'] = self::appStreamBroadcast( $session->uid, $post, $options );
        }
    }

    // advanced interface: always for conference, collaboration
    if ( $conference || $collaboration || $appComplexity ) {
        // users list
        $room['panelUsers']     = true;
        
        $room['usersModerator'] = ( boolval( $session->broadcaster ) || $isModerator ) && isset( $options['bans'] ) && $options['bans']; // kick/ban
        
        if ( isset( $options['bans'] ) && $options['bans'] && !$room['usersModerator'] ) {
            $room['usersModerator'] = self::appRole( $session->uid, 'banUsers', boolval( $session->broadcaster ) || $isModerator , $options ); // other users
        }

        // options for performer only
        if ( $options['appOptions'] ) {
            if ( $session->broadcaster ) {
                $room['panelOptions'] = boolval( $session->broadcaster );
                $room['options']      = self::appRoomOptions( $post, $session, $options );
            }
        }
    }

    // also needed to check when user comes online in calls
    if ( $requestUID || $conference || $collaboration || $appComplexity ) {
        $room['users'] = self::appRoomUsers( $post, $options );
    }

    // external broadcast panel: only for performer
	//if ( !metadata_exists('post', $post->ID, 'external_rtmp') ) $external_rtmp  = ( $options['webrtcServer'] == 'wowza' );else 

    //if enabled or meta does not exist 
    $external_rtmp = self::is_true( get_post_meta( $post->ID, 'external_rtmp', true ) ) || !metadata_exists('post', $post->ID, 'external_rtmp');

    $room['panelBroadcast'] = $external_rtmp && $room['panelCamera'] && $session->broadcaster;

    if ( $room['panelBroadcast'] ) {
        $room['broadcastSettings'] = self::appBroadcastSettings( $session, $post, $options );
        $room['welcome']          .= "\n 📡 " . __(
            'WebRTC broadcasting may not be supported or provide good quality on certain devices, browser versions, connections or network conditions. 
- Use best network available if you have the option: 5GHz on WiFi instead of 2.4 GHz, LTE/4G on mobile instead of 3G, wired instead of wireless.
- For increased streaming quality and reliability, you can broadcast directly to streaming server with an application like OBS for desktop or GoCoder for mobile. Advanced desktop encoders also enable advanced compositions, screen sharing, effects and transitions. See Broadcast tab next to Cam tab.',
            'live-streaming'
        );

    }

$browser  = '';
if (stripos( $_SERVER['HTTP_USER_AGENT'], 'Chrome') !== false) $browser='Chrome';
elseif (stripos( $_SERVER['HTTP_USER_AGENT'], 'Safari') !== false) $browser = 'Safari';

if ($browser == 'Safari' )
{
//$room['welcome'] .=  "\n ⚠️ " . 'In latest Safari you need to disable NSURLSession WebSocket for WebRTC streaming to work:';

if ( strstr( $_SERVER['HTTP_USER_AGENT'], " Mobile/") ) $room['welcome'] .=  "\n " . 'On iOS Mobile, open Settings application. Tap Safari, then Advanced, and then Experimental Features, disable NSURLSession WebSocket.';
else $room['welcome'] .=  "\n " . 'On PC: From Safari menu > Preferences ... > Advanced tab, enable Show Develop menu. Then from Develop menu > Experimental features disable NSURLSession WebSocket.';
}



    if ($options['lovense'])
    {
     $room['welcome'] .=  "\n 🕹 " . 'Lovense integration is enabled. Use Lovense Browser or Extension to use features.';
              
    }	
    
    // goals panel
    $goals_panel =  self::is_true( $options['goals'] && get_post_meta( $post->ID, 'goals_panel', true ) );

    if ( $goals_panel ) {
        $room['panelGoals'] = true;
        $goals_sort         = self::is_true( get_post_meta( $post->ID, 'goals_sort', true ) );
        $room['goals']      = self::appRoomGoals( $post->ID, $options, $goals_sort );
    } else {
        $room['panelGoals'] = false;
    }

    $room['goal'] = $goal = self::goal( $post->ID, $add = 0, $options ); // current goal

    // allow to complete
    if ( $session->broadcaster ) {
        $room['goalsComplete'] = true;
        $room['goalsManage']   = true;
    }

    // configure tipping options for clients: room & role
    $gifts = ( self::is_true( get_post_meta( $post->ID, 'gifts', true ) ) && self::rolesUser( $options['rolesDonate'], get_userdata(  $session->uid ) ) ) ;

        $room['tips'] = false;
        
    if ( $options['tips'] ) {
        if ( $gifts ) {
            $room['tips'] = true;
        }
    }

    if ($isModerator) $room['tips'] = false; //disable for moderators

    if ( $room['tips'] || $room['panelGoals'] ) {
        $tipOptions = self::appTipOptions( $options );

        if ( count( $tipOptions ) ) {
            $room['tipOptions'] = $tipOptions;
            $room['tipsURL']    = dirname( plugin_dir_url( __FILE__ ) ) . '/videowhisper/templates/messenger/tips/';
        }
    }

        // custom buttons
        $actionButtons = array();

        // exit
    if ( $session->broadcaster ) {
        $pid = get_option( 'vwls_page_manage' );
    } else {
        $pid = get_option( 'vwls_page_channels' );
    }
        $url                            = get_permalink( $pid );
        $actionButtons['exitDashboard'] = array(
            'name'    => 'exitDashboard',
            'icon'    => 'close',
            'color'   => 'red',
            'floated' => 'right',
            'target'  => 'top',
            'url'     => $url,
            'text'    => '',
            'tooltip' => __( 'Exit', 'live-streaming' ),
        );
        $room['actionButtons']          = $actionButtons;

        // current room goal if gifts button is enabled
        if ( $options['tips'] ) {
            if ( $options['goals'] ) {
                if ( $gifts ) {
                                    // $goal = self::goal($post->ID, 0, $options);
                    if ( $goal ) {
                        // $room['welcome'] .= "\n" . serialize($goal);

                        $room['welcome'] .= "\n 🎁 " . __( 'Current gifts goal', 'live-streaming' ) . ': ' . $goal['name'];
                        $room['welcome'] .= "\n - " . __( 'Goal description', 'live-streaming' ) . ': ' . $goal['description'];
                        $room['welcome'] .= "\n - " . __( 'Goal started', 'live-streaming' ) . ': ' . ( isset($goal['started']) ? self::humanDuration( $goal['started'] - time() ) : ' - ' );
                        $room['welcome'] .= "\n - " . __( 'Cumulated gifts (including previous goals)', 'live-streaming' ) . ': ' . $goal['cumulated'];

                        $room['welcomeProgressValue']   = $goal['current'];
                        $room['welcomeProgressTotal']   = $goal['amount'];
                        $room['welcomeProgressDetails'] = $goal['name'];
                    }
                }
            }
        }

        return $room;
}

static function goalCmp( $a, $b ) {
           return $b['current'] - $a['current'];
}


static function appRoomGoals( $postID, $options, $goals_sort = false ) {

    if ( ! $options ) {
        $options = get_option( 'VWliveWebcamsOptions' );
    }

    if ( ! $postID ) {
        return 0; // goal is per post
    }

    $goals = get_post_meta( $postID, 'goals', true );

    $saveGoal = 0;

    if ( ! $goals ) {
        $goals = $options['goalsDefault'];
    }

    if ( ! is_array( $goals ) ) {
        $goals = array(
            0 => array(
                'name'        => 'Goal',
                'description' => 'Default. No custom goals setup.',
                'amount'      => 100,
                'current'     => 0,
                'cumulated'   => 0,
            ),
        );
    }

    foreach ( $goals as $key => $value ) {
        $goals[ $key ]['description'] = stripslashes( $goals[ $key ]['description'] );
        $goals[ $key ]['current']     = floatval( $goals[ $key ]['current'] ?? 0 );
        $goals[ $key ]['ix']          = intval( $key );
    }

    if ( $goals_sort ) {
        usort( $goals, 'self::goalCmp' );
    }

    return $goals;
}

static function appBroadcastSettings( $session, $post, $options ) {
    $configuration = array();

    if ($options['rtmpServer'] == 'videowhisper') {
        $rtmpAddress =  trim( $options['videowhisperRTMP'] );
        $stream = trim($options['vwsAccount']) . '/' . trim($session->username) . '?pin=' . self::getPin($post->ID, 'broadcast', $options); 
        $rtmpURL = $rtmpAddress . '//' . $stream;
    }
    else 
    {
    $rtmpAddress = self::rtmp_address( $session->uid, $post->ID, true, $post->post_title, $post->post_title, $options );			
			
    $application = substr( strrchr( $rtmpAddress, '/' ), 1 );
    $stream      = sanitize_file_name( $post->post_title );

    $adrp1 = explode( '://', $rtmpAddress );
    $adrp2 = explode( '/', $adrp1[1] );
    $adrp3 = explode( ':', $adrp2[0] );

    $server = $adrp3[0];
    $port   = $adrp3[1] ?? '';
    if ( ! $port ) {
        $port = 1935;
    }
    
    $rtmpURL = $rtmpAddress . '/' . $stream;
    }

    $fields = array(
        'downloadOBS' => array(
            'name'        => 'downloadOBS',
            'description' => __( 'Download OBS', 'live-streaming' ),
            'details'     => __( 'OBS Studio is free desktop application for live streaming from Linux, Mac and Windows. Includes advanced composition features, screen sharing, scenes, transitions, filters, media input options.', 'live-streaming' ),
            'type'        => 'link',
            'url'         => 'https://obsproject.com',
            'icon'        => 'cloud upload',
            'color'       => 'blue',
        ),
        'streamURL'   => array(
            'name'        => 'streamURL',
            'description' => __( 'Stream URL', 'live-streaming' ),
            'details'     => __( 'RTMP Address / OBS Stream URL: full streaming address. Contains: server, port if different than default 1935, application and control parameters, key. For OBS Settings: Stream > Server.', 'live-streaming' ),
            'type'        => 'text',
            'value'       => $rtmpAddress,
        ),
        'streamKey'   => array(
            'name'        => 'streamKey',
            'description' => __( 'Stream Key', 'live-streaming' ),
            'details'     => __( 'Stream Name / OBS Stream Key: name of stream. For OBS Settings: Stream > Stream Key.' ),
            'type'        => 'text',
            'value'       => $stream,
        ),
        
    );

    $videoBitrate = 0;
    $audioBitrate = 0;

    // $videoBitrate
    $sessionsVars = self::varLoad( $options['uploadsPath'] . '/sessionsApp' );
    if ( is_array( $sessionsVars ) ) {
        if ( array_key_exists( 'limitClientRateIn', $sessionsVars ) ) {
            $limitClientRateIn = intval( $sessionsVars['limitClientRateIn'] ) * 8 / 1000;

            if ( $limitClientRateIn ) 
            {
                $videoBitrate  = $limitClientRateIn - 100;
                $audioBitrate  = 96;
            }

			//also limit to values set by admin if lower
			if ($options['webrtcVideoBitrate']) if ($videoBitrate > $options['webrtcVideoBitrate']) $videoBitrate = $options['webrtcVideoBitrate'];
			if ($options['webrtcAudioBitrate']) if ($audioBitrate > $options['webrtcAudioBitrate']) $audioBitrate = $options['webrtcAudioBitrate'];

            if ($videoBitrate )     
                $fields['videoBitrate'] = array(
                    'name'        => 'videoBitrate',
                    'description' => __( 'Maximum Video Bitrate', 'live-streaming' ),
                    'details'     => __( 'Use this value or lower for video bitrate, depending on resolution. A static background and less motion requires less bitrate than movies, sports, games. For OBS Settings: Output > Streaming > Video Bitrate. Warning: Trying to broadcast higher bitrate than allowed by streaming server will result in disconnects/failures.' ),
                    'type'        => 'text',
                    'value'       => $videoBitrate,
                );

                if ($audioBitrate )
                $fields['audioBitrate'] = array(
                    'name'        => 'audioBitrate',
                    'description' => __( 'Maximum Audio Bitrate', 'live-streaming' ),
                    'details'     => __( 'Use this value or lower for audio bitrate. If you want to use higher Audio Bitrate, lower Video Bitrate to compensate for higher audio. For OBS Settings: Output > Streaming > Audio Bitrate. Warning: Trying to broadcast higher bitrate than allowed by streaming server will result in disconnects/failures.' ),
                    'type'        => 'text',
                    'value'       => $audioBitrate,
                );

            
        }
    }
    	//vws limits
		if ($options['maxVideoBitrate'] ?? false) 
		$fields['videoBitrate'] = array(
			'name'        => 'videoBitrate',
			'description' => __( 'Maximum Video Bitrate', 'ppv-live-webcams' ),
			'details'     => __( 'Use this value or lower for video bitrate, depending on resolution. A static background and less motion requires less bitrate than movies, sports, games. For OBS Settings: Output > Streaming > Video Bitrate. Warning: Trying to broadcast higher bitrate than allowed by streaming server will result in disconnects/failures.' ),
			'type'        => 'text',
			'value'       => $options['maxVideoBitrate'],
		);

		if ($options['maxAudioBitrate'] ?? false) 
		$fields['audioBitrate'] = array(
			'name'        => 'audioBitrate',
			'description' => __( 'Maximum Audio Bitrate', 'ppv-live-webcams' ),
			'details'     => __( 'Use this value or lower for audio bitrate. If you want to use higher Audio Bitrate, lower Video Bitrate to compensate for higher audio. For OBS Settings: Output > Streaming > Audio Bitrate. Warning: Trying to broadcast higher bitrate than allowed by streaming server will result in disconnects/failures.' ),
			'type'        => 'text',
			'value'       => $options['maxAudioBitrate'],
		);


    $fields['downloadLarixIOS'] = array(
        'name'        => 'downloadLarixIOS',
        'description' => __( 'Larix Broadcaster for iOS', 'live-streaming' ),
        'details'     => __( 'Larix Broadcaster free iOS app uses full power of mobile devices cameras to stream live content..', 'live-streaming' ),
        'type'        => 'link',
        'url'         => 'https://apps.apple.com/app/larix-broadcaster/id1042474385',
        'icon'        => 'apple',
        'color'       => 'grey',
    );
    
    $fields['downloadLarixAndroid'] = array(
        'name'        => 'downloadLarixAndroid',
        'description' => __( 'Larix Broadcaster for Android', 'live-streaming' ),
        'details'     => __( 'Larix Broadcaster free iOS app uses full power of mobile devices cameras to stream live content.', 'live-streaming' ),
        'type'        => 'link',
        'url'         => 'https://play.google.com/store/apps/details?id=com.wmspanel.larix_broadcaster',
        'icon'        => 'android',
        'color'       => 'green',
    );
    
    $fields['rtmpURL'] = array(
        'name'        => 'rtmpURL',
        'description' => __( 'RTMP URL', 'live-streaming' ),
        'details'     => __( 'Full RTMP URL / Larix URL: full streaming address. Contains: server, port if different than default 1935, application and control parameters, key, stream name. For Larix Broadcaster: Connections > URL.', 'live-streaming' ),
        'type'        => 'text',
        'value'       => $rtmpURL,
    );
    
    if ($options['lovense']) $fields['downloadLovense'] = array(
        'name'        => 'downloadLovense',
        'description' => __( 'Download Lovense', 'live-streaming' ),
        'details'     => __( 'If you have a Lovense toy, download the Lovense browser or extension to integrate with site.', 'live-streaming' ),
        'type'        => 'link',
        'url'         => 'https://www.lovense.com/r/sytsk',
        'icon'        => 'heart',
        'color'       => 'pink',
    );

    $configuration['rtmp_obs'] = array(
        'name'    => __( 'RTMP Encoder: OBS / Larix Broadcaster Settings', 'live-streaming' ),
        'details' => __( 'Use these settings to broadcast with a RTMP encoder app like OBS, iOS/Android Larix Broadcaster, xSplit, Zoom Meetings Webinars.', 'live-streaming' ),
        'fields'  => $fields,
    );

    $configuration['meta'] = array(
        'time'        => time(),
        'description' => __( 'Broadcast with external apps for advanced compositions, scenes, effects, higher streaming quality and reliability compared to browser based interface and protocols. External broadcasts have higher latency and improved capacity, reliability specific to HLS delivery method. New broadcasts show in about 10 seconds and unavailability updates after 1 minute.', 'live-streaming' ),
    );

    return $configuration;

}


static function rtmp_address( $userID, $postID, $broadcaster, $username, $room, $options ) {

    $roomRTMPserver = self::rtmpServer( $postID, $options );

    if ( $broadcaster ) {
        $key = md5( 'vw' . $options['webKey'] . $userID . $postID );
        return $roomRTMPserver . '?' . urlencode( $username ) . '&' . urlencode( $room ) . '&' . $key . '&1&' . $userID . '&videowhisper';
    } else {
        $keyView = md5( 'vw' . $options['webKey'] . $postID );
        return $roomRTMPserver . '?' . urlencode( $username ) . '&' . urlencode( $room ) . '&' . $keyView . '&0' . '&videowhisper';
    }

    return $roomRTMPserver;

}

	static function appUserHLS( $stream, $options ) {

        if ( $options['rtmpServer'] == 'videowhisper' )
		{
			//pin protected playback
			$playbackPin = trim($options['playbackPin']);

			if ($options['videowhisperStream'] && $options['videowhisperStream'] != 'broadcast') //user the broadcaster's playback pin
			{
                global $wpdb;
                $postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE `post_title` = '$stream' AND `post_type`='" . $options['custom_post'] . "' LIMIT 0,1" ); // 
				$playbackPin = self::getPin($postID, 'playback', $options);
			}

			return trim($options['videowhisperHLS']) .'/' . trim($options['vwsAccount']). '/'. $stream . '/index.m3u8?pin=' . $playbackPin;
		} 

		//default wowza se stream
		return $options['httpstreamer'] . $stream . '/playlist.m3u8';
	}
	

static function appRoomUsers( $post, $options ) {

    $webStatusInterval = $options['webStatusInterval'] ?? 60;

    if ( $webStatusInterval < 10 ) {
        $webStatusInterval = 60;
    }

    global $wpdb;
    $table_sessions = $wpdb->prefix . 'vw_sessions';
    $table_viewers  = $wpdb->prefix . 'vw_lwsessions';
    
    $ztime = time();


    $rolesModerator = explode( ',', $options['roleModerators'] );
    foreach ( $rolesModerator as $key => $value ) $rolesModerator[ $key ] = trim( $value );

        //clean expired sessions
		self::cleanSessions( 0 );
		self::cleanSessions( 1 );

    // update room user list

    $items = array();

    $sqlS   = [ "SELECT * FROM `$table_sessions` WHERE rid='" . $post->ID . "' AND status = 1 ORDER BY sdate ASC", "SELECT * FROM `$table_viewers` WHERE rid='" . $post->ID . "' AND status = 1 ORDER BY sdate ASC" ];

    $no = 0;
foreach ($sqlS as $sql) {

    $sqlRows = $wpdb->get_results($sql);

    if ($wpdb->num_rows > 0) {
        foreach ($sqlRows as $sqlRow) {
            if ($sqlRow->meta) {
                $userMeta = unserialize($sqlRow->meta);
            } else $userMeta = [];

            if (! is_array($userMeta)) {
                $userMeta = array();
            }

            $item             = array();
            $item['userID']   = intval($sqlRow->uid);
            $item['userName'] = sanitize_file_name($sqlRow->username);
            if (! $item['userName']) {
                $item['userName'] = '#' . $sqlRow->uid;
            }

            $item['sdate']   = intval($sqlRow->sdate);
            $item['updated'] = intval($sqlRow->edate);
            $item['avatar']  = get_avatar_url($sqlRow->uid, array( 'default' => dirname(plugin_dir_url(__FILE__)) . '/images/avatar.png' ));

            // buddyPress profile url
            $bp_url = '';
            if (function_exists('bp_members_get_user_url')) {
                $bp_url = bp_members_get_user_url($sqlRow->uid);
            }

            $item['url'] = $bp_url ? $bp_url : get_author_posts_url($sqlRow->uid);

            if (array_key_exists('privateUpdate', $userMeta)) {
                if ($ztime - intval($userMeta['privateUpdate']) < $options['onlineTimeout']) {
                    $item['hide'] = true; // in private
                }
            }

            //hide moderators
            $isModerator = self::isModerator($sqlRow->uid, $options, null, $rolesModerator);
            if ($isModerator) {
                $item['hide'] = true;
            }

            // if ($ztime - intval($sqlRow->edate) < $options['onlineTimeout']) $item['hide'] = true; //offline
            
            if ($sqlRow->broadcaster) {
                // updated external broadcast info
                if (array_key_exists('externalUpdate', $userMeta)) {
                    if ($userMeta['externalUpdate'] < time() - $webStatusInterval) {
                        $userMeta['external']        = false; // went offline?
                        $userMeta['externalTimeout'] = true; // went offline?
                    }
                }

                if (array_key_exists('external', $userMeta)) {
                    if ($userMeta['external']) {
                        // channel stream is post name
                        $item['hls'] = self::appUserHLS($post->post_title, $options); 
                    }
                }
            }

            //language
            $language = 'en-us'; //default
            if ($sqlRow->uid) {
                $language = get_user_meta($sqlRow->uid, 'h5v_language', true);
            }
            $item['language'] = $language;
            $item['flag'] = self::language2flag($language);

            // include updated user meta
            $item['meta'] = $userMeta;

            $item['order'] = ++$no;

            $item['session_id'] = $sqlRow->id;

            $ix = $sqlRow->uid;
            if (! $ix) {
                $ix = $sqlRow->id + 100000000;
            }

            $items[ $ix ] = $item;
        }
    };

    } //end foreach

    if (! count($items) ) {
        $item['userID']          = 0;
        $item['userName']        = 'ERROR_empty';
        $item['sql']             = $sql;
        $item['wpdb-last_error'] = $wpdb->last_error;
        $item['sdate']           = 0;
        $item['updated']         = 0;
        $item['meta']            = array();
        $items[0]                = $item;
    }
    
        return $items;
}

static function time2age( $time ) {
    $ret = '';

    $seconds = time() - $time;

    $days = intval( intval( $seconds ) / ( 3600 * 24 ) );
    if ( $days ) {
        $ret .= $days . 'd ';
    }
    if ( $days > 0 ) {
        return $ret;
    }

    $hours = intval( intval( $seconds ) / 3600 ) % 24;
    if ( $days || $hours ) {
        $ret .= $hours . 'h ';
    }

    if ( $hours > 0 ) {
        return $ret;
    }

    $minutes = intval( intval( $seconds ) / 60 ) % 60;
    if ( $minutes > 3 ) {
        $ret .= $minutes . 'm';
    } else {
        $ret .= __( 'New', 'ppv-live-webcams' );
    }

    return $ret;
}

static function handle_upload( $file, $destination ) {
    // ex $_FILE['myfile']

    if ( ! function_exists( 'wp_handle_upload' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
    }

    $movefile = wp_handle_upload( $file, array( 'test_form' => false ) );

    if ( $movefile && ! isset( $movefile['error'] ) ) {
        if ( ! $destination ) {
            return 0;
        }
        rename( $movefile['file'], $destination ); // $movefile[file, url, type]
        return 0;
    } else {
        /*
         * Error generated by _wp_handle_upload()
         * @see _wp_handle_upload() in wp-admin/includes/file.php
         */
        return $movefile['error']; // return error
    }

}

static function appRoomFiles( $room, $options ) {

    $files = array();
    if ( ! $room ) {
        return $files;
    }

    $dir = $options['uploadsPath'];
    if ( ! file_exists( $dir ) ) {
        mkdir( $dir );
    }
    $dir .= "/$room";
    if ( ! file_exists( $dir ) ) {
        mkdir( $dir );
    }

    $handle = opendir( $dir );
    while ( ( $file = readdir( $handle ) ) !== false ) {
        if ( ( $file != '.' ) && ( $file != '..' ) && ( ! is_dir( "$dir/" . $file ) ) ) {
            $files[] = array(
                'name' => $file,
                'size' => intval( filesize( "$dir/$file" ) ),
                'age'  => self::time2age( $ftime = filemtime( "$dir/$file" ) ),
                'time' => intval( $ftime ),
                'url'  => self::path2url( "$dir/$file" ),
            );
        }
    }
    closedir( $handle );

    return $files;
}

static function appRoomMedia( $post, $session, $options ) {
    
    if (!$options) $options = self::getOptions();

    $media = get_post_meta( $post->ID, 'presentationMedia', true );

    // always Main to show default room stream
    if ( ! is_array( $media ) ) {
        $media = array( 'Main' => array( 'name' => 'Main' ) );
    }
    if ( ! count( $media ) ) {
        $media['Main'] = array( 'name' => 'Main' );
    }
    
    //stream hls (i.e. restream ip cameras)
    $streamMode = get_post_meta(  $post->ID, 'stream-mode', true);
    if ($streamMode == 'stream')
    {
        $streamName = get_post_meta( $post->ID, 'stream-name', true);		
        $media['Main'] = [ 'name' => $streamName, 'type' => 'hls', 'url' => ($options['httprestreamer'] ? $options['httprestreamer']  : $options['httpstreamer']) . $streamName . '/playlist.m3u8'  ];
    }

    // room slots
    $collaboration = self::is_true( get_post_meta( $post->ID, 'vw_presentationMode', true ) );
    $conference    = self::is_true( get_post_meta( $post->ID, 'room_conference', true ) );

    if ( $collaboration || $conference ) {
        $room_slots = intval( get_post_meta( $post->ID, 'room_slots', true ) );
    }
    if ( ! isset($room_slots) ) {
        $room_slots = 1;
    }

    $edited = 0;

    // always 1 if disabled
    if ( ! $collaboration && ! $conference ) {
        if ( $room_slots > 1 ) {
            $room_slots = 1;
            $edited     = 1;
        }
    }

    $items = 0;

    foreach ( $media as $placement => $content ) {
        if ( ++$items > $room_slots ) {
            unset( $media[ $placement ] ); // remove if too many
            $edited = 1;
        }
    }

    while ( count( $media ) < $room_slots ) {
        $media[ 'Slot' . ++$items ] = array(
            'name' => 'Slot' . $items,
            'type' => 'empty',
        ); // add if missing
        $edited                     = 1;
    }

    // remove missing auto sessions

    global $wpdb;
    $table_sessions = $wpdb->prefix . 'vw_vmls_sessions';

    $items = 0;
    foreach ( $media as $placement => $content ) {
        ++$items;

        if ( array_key_exists( 'auto', $content ) || ( isset($content['type']) && $content['type'] == 'user' )) {
            $sql = "SELECT COUNT(*) as n FROM `$table_sessions` WHERE uid='" . $content['userID'] . "' AND username='" . $content['userName'] . "' AND status=0";
            
            if ( ! $wpdb->get_var( $sql ) ) {
                $media[ $placement ] = array(
                    'name'    => 'Slot' . $items,
                    'type'    => 'empty',
                    'by'      => 'appRoomMedia()',
                    'comment' => $content['userName'] . '/' . $content['userID'] . ' offline',
                ); // empty slot
                $edited              = 1;
            }
        }
    }

    if ( $edited ) {
        update_post_meta( $post->ID, 'presentationMedia', $media );
        update_post_meta( $post->ID, 'updated_media', time() );
    }

    return $media;
}

	// room donations goal

		// progressive goals

		static function goalIndependent( $postID, $name, $add = 0, $options = null ) {
			if ( ! $options ) {
				$options = get_option( 'VWliveWebcamsOptions' );
			}
			if ( ! $postID ) {
				return 0; // goal is per post
			}

			$goals = get_post_meta( $postID, 'goals', true );

			foreach ( $goals as $ix => $goal ) {
				if ( $goal['name'] == $name ) {
					$goal['current']   += $add;
					$goal['cumulated'] += $add;

					if ( ! $goal['started'] ) {
						$goal['started'] = time(); // start on first gift
					}

					$goals[ $ix ] = $goal;

					update_post_meta( $postID, 'goals', $goals );

					return $goal;
				}
			}

			// otherwise add to regular goal
			self::goal( $postID, $add, $options );
		}

		static function goal( $postID, $add = 0, $options = null) {

			if ( ! $options ) {
				$options = self::getOptions();
			}

			if ( ! $postID ) {
				return 0; // goal is per post
			}

			$goal  = get_post_meta( $postID, 'goal', true );
			$goals = get_post_meta( $postID, 'goals', true );

			$saveGoal = 0;

			if ( ! $goals ) {
				$goals = $options['goalsDefault'];

			}

			if ( ! is_array( $goals ) || empty( $goals ) ) {
				$goals = array(
					0 => array(
						'ix'          => 0,
						'name'        => 'Goal',
						'description' => 'Default. No custom goals setup.',
						'amount'      => 100,
						'current'     => 0,
						'cumulated'   => 0,
					),
				);
			}

			if ( ! $goal ) {
				$goal = array_values( $goals )[0]; // first goal
			}

			if ( ! array_key_exists( 'name', $goal ) ) {
				$goal = array_values( $goals )[0]; // first goal
			}

			// reset to first goal after reset days
			if ( $goal['started'] ?? false ) {
				if ( $goal['reset'] ) {
					if ( time() - 86400 * $goal['reset'] > $goal['started'] ) {
						$goal     = array_values( $goals )[0]; // first goal
						$saveGoal = 1;
					}
				}
			}

			// $goal['goals0'] = serialize($goals[0]);

			// complete any goal
			$completed            = '';
			$completedDescription = '';

			if ( $add ) {
				
				if ( ! $goal['started'] ) {
					$goal['started'] = time(); // start on first gift
				}

				if ( $goal['current'] + $add < $goal['amount'] ) {
					$goal['current']   += $add;
					$goal['cumulated'] += $add;

	
					// update current goal
					$ix           = $goal['ix'];
					$goals[ $ix ] = $goal;


				} else {
					
					// for next
					$delta = $goal['current'] + $add - $goal['amount'];

					$goal['cumulated'] += $goal['amount'] - $goal['current'];
					$goal['current']    = $goal['amount'];
				
				
					// update current goal
					$ix           = $goal['ix'];
					$goals[ $ix ] = $goal;


					while ($delta > 0)
					{
					//find next goal (progrssive)
					
					$newGoal = '';
					
					// for ($k=0; $k < count($goals); $k++ )
					foreach ( $goals as $k => $value ) {
						if ( $value['name'] == $goal['name'] ) { // current goal found
							if ( array_key_exists( $k + 1, $goals ) ) {
								 $newGoal       = $goals[ $k + 1 ];
								 $newGoal['ix'] = $k + 1;
								 break;

							}
						}
					}

					//repeat last goal if no next goal not found
					if ( ! $newGoal ) {
						$newGoal       = end($goals);
						$newGoal['ix'] = $newGoal['ix'] + 1;
					}

					/*
					if ( ! $newGoal ) {
						$newGoal = array_values( $goals )[0]; // back to first goal if last gola not found
					}
					*/
					
					$nAdd = min( $delta, $newGoal['amount'] ); //partial or full
					$delta = $delta - $nAdd; //rest for another goal

					$newGoal['started']   = time();
					$newGoal['current']  += $nAdd;
					$newGoal['cumulated'] += $nAdd;

					$completed            = stripslashes( $goal['name'] );
					$completedDescription = stripslashes( $goal['description'] );

					// update new goal
					$nix           = $newGoal['ix'];
					$goals[ $nix ] = $newGoal;
					
					$goal = $newGoal;

					}
				}
				
			//add	
			}

			if ( ! $goal['amount'] ) {
				$goal['amount'] = 100; // avoid division by zero in case of misconfiguration
			}
			$goal['current'] = round( $goal['current'], 2 );

			$goal['progress'] = round( $goal['current'] * 100 / $goal['amount'] );

			$goal['name']        = stripslashes( $goal['name'] );
			$goal['description'] = stripslashes( $goal['description'] );

			if ( $add || $saveGoal ) {
				update_post_meta( $postID, 'goal', $goal );

				// update current goal
				$ix           = $goal['ix'];
				$goals[ $ix ] = $goal;

				update_post_meta( $postID, 'goals', $goals );
			}

			$goal['completed']            = $completed;
			$goal['completedDescription'] = $completedDescription;

			return $goal;
		}

        static function notificationMessage( $message, $session, $privateUID = 0, $meta = null ) {
            // adds a notification from server, only visible to user
    
            $ztime = time();
    
            global $wpdb;
			$table_chatlog = $wpdb->prefix . 'vw_vwls_chatlog';
    
            if ( ! $meta ) {
                $meta = array();
            }
            
            $meta['notification'] = true;
            $metaS = esc_sql( serialize( $meta ) );
    
            $sql = "INSERT INTO `$table_chatlog` ( `username`, `room`, `room_id`, `message`, `mdate`, `type`, `user_id`, `meta`, `private_uid`) VALUES ('" . $session->username . "', '" . $session->room . "', '" . $session->rid . "', '$message', $ztime, '3', '" . $session->uid . "', '$metaS', '$privateUID')";
            $wpdb->query( $sql );
    
            // todo maybe: also update chat log file
    
            return $sql;
        }
    
    
        static function autoMessage( $message, $session, $privateUID = 0, $meta = null ) {
            // adds automated user message from server, automatically generated by user action
    
            $ztime = time();
    
            global $wpdb;
			$table_chatlog = $wpdb->prefix . 'vw_vwls_chatlog';
    
            if ( ! $meta ) {
                $meta = array();
            }
            $meta['automated'] = true;
            $metaS             = esc_sql( serialize( $meta ) );
    
            $message = esc_sql( $message );
    
            $sql = "INSERT INTO `$table_chatlog` ( `username`, `room`, `room_id`, `message`, `mdate`, `type`, `user_id`, `meta`, `private_uid`) VALUES ('" . $session->username . "', '" . $session->room . "', '" . $session->rid . "', '$message', $ztime, '2', '" . $session->uid . "', '$metaS', '$privateUID')";
            $wpdb->query( $sql );
    
            return $sql;
        }
    


}