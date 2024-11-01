<?php
namespace VideoWhisper\LiveStreaming;

// advanced FFmpeg

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

trait IPTV {
	// restream sources (IPTV, IPCams, Streams)


	// recording
	static function is_true( $val, $return_null = false ) {
		$boolval = ( is_string( $val ) ? filter_var( $val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) : (bool) $val );
		return $boolval === null && ! $return_null ? false : $boolval;
	}


	static function startProcess( $cmd = '', $log_file = '', $postID = '', $stream = '', $type = '', $options = '' ) {
		// start and log a process
		// $cmd must end in &

		if ( ! $options ) {
			$options = get_option( 'VWliveStreamingOptions' );
		}

		if ( !$options['enable_exec'] ) return;
 
		// release timeout slots before starting new process
		// self::processTimeout();

		self::log( 'startProcess: start / $stream=' . $stream . ' $type=' . $type . ' $cmd=' . $cmd, 4, $options);

		if ( $options['enable_exec'] ) $processId = exec( $cmd . ' echo $!;', $output, $returnvalue );

		if ( $options['enable_exec'] ) exec( "echo '$cmd' >> " . $log_file . '-cmd.txt', $output, $returnvalue );

		$uploadsPath = $options['uploadsPath'];

		$processPath = $uploadsPath . '/_process/';
		if ( ! file_exists( $processPath ) ) {
			mkdir( $processPath );
		}

		if ( $processId ) {

			$info = array(
				'postID' => $postID,
				'stream' => $stream,
				'type'   => $type,
				'time'   => time(),
			);

			self::varSave( $processPath . $processId, $info );

			$lastLog = $options['uploadsPath'] . '/lastLog-' . $type . '.txt';
			self::varSave(
				$lastLog,
				array(
					'type'   => $type,
					'postID' => $postID,
					'file'   => $log_file,
					'cmd'    => $cmd,
					'return' => $returnvalue,
					'output' => $output,
					'time'   => time(),
				)
			);

			self::log( 'startProcess: success / $stream=' . $stream . ' $type=' . $type . ' $processId=' . $processId . ' $log_file=' . $log_file, 4, $options);

		}
	}

	static function processTimeout( $search = 'ffmpeg', $force = false, $verbose = false ) {
		// clear processes for listings that are not online
		$options = get_option( 'VWliveStreamingOptions' );

		if ( !$options['enable_exec'] ) return;

		if ( ! $force && ! self::timeTo( 'processTimeout', 300, $options ) ) {
			return;
		}

		if ( $verbose ) {
			echo '<BR>Checking timeout processes (associated with offline listings) ...';
		}

		$processTimeout = $options['processTimeout'];
		if ( $processTimeout < 10 ) {
			$processTimeout = 90;
		}

		$uploadsPath = $options['uploadsPath'];
		if ( ! file_exists( $uploadsPath ) ) {
			mkdir( $uploadsPath );
		}

		$processPath = $uploadsPath . '/_process/';
		if ( ! file_exists( $processPath ) ) {
			mkdir( $processPath );
		}

		$processUser = get_current_user();

		$cmd = "ps aux | grep '$search'";
		if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );
		// var_dump($output);

		$transcoders = 0;
		$kills       = 0;

		foreach ( $output as $line ) {
			if ( strstr( $line, $search ) ) {
				$columns = preg_split( '/\s+/', $line );
				if ( $processUser == $columns[0] && ( ! in_array( $columns[10], array( 'sh', 'grep' ) ) ) ) {
					$transcoders++;

					$killThis = false;

					$info = self::varLoad( $processPath . $columns[1] );

					if ( $info === false ) {
						// not found: kill it
						// $killThis = true;

						if ( $verbose ) {
							echo '<br>Warning: No info found for process #' . esc_html( $columns[1] );
						}
					} else {
						if ( $info['postID'] ) {
							$edate = (int) get_post_meta( $info['postID'], 'edate', true );
							if ( time() - $edate > $processTimeout ) {
								$killThis = true; // kill if not online last $processTimeout s
							}
						}
					}

					if ( $killThis ) {
						$cmd = 'kill -9 ' . $columns[1];
						if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );

						$kills++;
						if ( $verbose ) {
							echo '<br>processTimeout (item offline) Killed #' . esc_html( $columns[1] );
						}
					}
				}
			}
		}

		if ( $verbose ) {
			echo '<br>' . esc_html( $transcoders ) . ' processes found, ' . esc_html( $kills ) . ' cleared';
		}

	}


		// record a stream
	static function streamRecord( $session, $stream = '', $type = 'rtsp', $postID = 0, $options = null ) {


		if ( !$options['enable_exec'] ) return;

		if ( ! $postID ) {
			$postID = $session->rid;
		}
		if ( ! $postID ) {
			return; // no room, no record
		}
		if ( ! $stream ) {
			$stream = $session->username;
		}
		$room = $session->room ? $session->room : $postID;


		if ( ! self::timeTo( $room . '/record-' . $stream, 29, $options ) ) {
			return;
		}

		// recording enabled?
		$recordingOn = self::is_true( get_post_meta( $postID, 'vw_recording', true ) ); // record performer

		if (!$recordingOn) return;
		 
		// recordings path
		$dir = $options['streamsPath'];
		if ( $dir && !file_exists( $dir ) ) {
			mkdir( $dir );
		}

		$filename  = $stream . '_' . time();
		$filepath  = $dir . '/' . $filename;
		$log_file .= $filepath . '.log';

			// detect recording process - cancel if already started and disabled
			$cmd = "ps auxww | grep '$dir/$stream'";
			if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );

			$recording = 0;
		foreach ( $output as $line ) {
			if ( strstr( $line, 'ffmpeg' ) ) {
				$recording = 1;

				// kill
				if ( ! $recordingOn ) {
						$columns = preg_split( '/\s+/', $line );
						$kcmd    = 'kill -KILL ' . $columns[1];
						if ( $options['enable_exec'] ) exec( $kcmd, $koutput, $kreturnvalue );
				}
			}
		}

		if ( $recording ) {
			return;
		}
		if ( ! $recordingOn ) {
			return;
		}

			// souce & command
		if ( $type == 'rtsp' ) {
			$userID      = 0;
			$streamQuery = self::webrtcStreamQuery( $session->uid, $postID, 0, $stream, $options, 1 );

			// usually webrtc
			$cmd = $options['ffmpegPath'] . ' -y -i "' . $options['rtsp_server'] . '/' . $streamQuery . "\" -c:v copy -c:a copy \"$filepath.webm\" >&$log_file & ";
		} else // type == 'rtmp'
			{
			$roomRTMPserver = $options['rtmp_server'];

			if ( $options['externalKeysTranscoder'] ) {
				$keyView         = md5( 'vw' . $options['webKey'] . $postID );
				$rtmpAddressView = $roomRTMPserver . '?' . urlencode( 'ffmpegSave_' . $stream ) . '&' . urlencode( $stream ) . '&' . $keyView . '&0&videowhisper';
			} else {
				$rtmpAddressView = $roomRTMPserver;
			}

			$cmd = $options['ffmpegPath'] . ' -y -i "' . $rtmpAddressView . '/' . $stream . "\" -c:v copy -c:a copy \"$filepath.mp4\" >&$log_file & ";
		}

			$cmd = 'nice ' . $cmd;

			// start and log recording process
			self::startProcess( $cmd, $log_file, $postID, $stream, 'record', $options );

	}


	// IPTV re-streaming: handles ffmpeg restreaming processes

	static function streamProtocols( $handler = 'ffmpeg' ) {
		// wowza
		if ( $handler == 'wowza' ) {
			return array( 'rtsp', 'udp', 'rtmp', 'rtmps', 'wowz', 'wowzs' );
		}

		// ffmpeg (default)
		return array( 'rtsp', 'rtp', 'srtp', 'udp', 'tcp', 'rtmp', 'rtmps', 'rtmpt', 'rtmpts', 'rtmpe', 'rtmpte', 'mmsh', 'mmst', 'http', 'https', 'tls' );
	}

	static function streamStart( $postID, $options = null ) {
		
		if ( !$options['enable_exec'] ) return;
		
		if ( ! $postID ) {
			return;
		}
		if ( ! $options ) {
			$options = self::getOptions();
		}

		$address = get_post_meta( $postID, 'vw_ipCamera', true );
		if ( ! $address ) {
			return;
		}

		list($addressProtocol) = explode( ':', strtolower( $address ) );
		if ( ! in_array( $addressProtocol, self::streamProtocols() ) ) {
			return;
		}

		$post   = get_post( $postID );
		$stream = $post->post_title;

		// publishing rtmp keys on this server
		if ( $options['externalKeysTranscoder'] ) {
			$userID = $post->post_author;

			$key         = md5( 'vw' . $options['webKey'] . $userID . $postID );
			$rtmpAddress = $options['rtmp_server'] . '?' . urlencode( $stream_hls ) . '&' . urlencode( $stream ) . '&' . $key . '&1&' . $userID . '&videowhisper';
		} else {
			$rtmpAddress = $options['rtmp_server'];
		}

		// paths
		$uploadsPath = $options['uploadsPath'];
		if ( ! file_exists( $uploadsPath ) ) {
			mkdir( $uploadsPath );
		}

		$roomPath = $uploadsPath . "/$stream/";
		if ( ! file_exists( $roomPath ) ) {
			mkdir( $roomPath );
		}

		$log_file = $roomPath . 'iptvStart.log';

		$cmd = $options['ffmpegPath'] . ' ' . ' -threads 1 -codec copy -bsf:v h264_mp4toannexb -bsf:a aac_adtstoasc -f flv "' .
			$rtmpAddress . '/' . $stream . '" -re -stream_loop -1 -i "' . $address . "\" >&$log_file & ";

		// -codec copy -bsf:v h264_mp4toannexb -bsf:a aac_adtstoasc
		// /usr/local/bin/ffmpeg -threads 1 -codec copy -bsf:v h264_mp4toannexb -bsf:a aac_adtstoasc -f flv "rtmp://videonow.live/videonow-xarchive?&Santorini-Parkour&1731175304590cf984d3b6f2fbcde576&1&1&videowhisper/Santorini-Parkour" -re -stream_loop -1 -i "https://bitdash-a.akamaihd.net/content/MI201109210084_1/m3u8s/f08e80da-bf1d-4e3d-8899-f0f6155f6efa.m3u8"

		// log and executed cmd
		if ( $options['enable_exec'] ) exec( "echo '" . date( DATE_RFC2822 ) . ":: $cmd' >> $log_file.cmd", $output, $returnvalue );

		if ( $options['enable_exec'] ) $pid = exec( $cmd, $output, $returnvalue );

		$lastLog = $options['uploadsPath'] . '/lastLog-iptvStart.txt';
		self::varSave(
			$lastLog,
			array(
				'pid'     => $pid,
				'file'    => $log_file,
				'cmd'     => $cmd,
				'return'  => $returnvalue,
				'output0' => $output[0],
				'time'    => time(),
			)
		);

		update_post_meta( $postID, 'stream-protocol', 'rtmp' ); // optimize?
		update_post_meta( $postID, 'stream-mode', 'iptv' );

		update_post_meta( $postID, 'iptvPid', $pid );
		update_post_meta( $postID, 'iptvStart', time() );
		update_post_meta( $postID, 'iptvLive', 1 );

		// update active streams list
		$iptvActive    = $options['uploadsPath'] . '/iptvActive.txt';
		$streamsActive = self::varLoad( $iptvActive );
		if ( ! is_array( $streamsActive ) ) {
			$streamsActive = array();
		}
		$streamsActive[ $postID ] = $pid;
		self::varSave( $iptvActive, $streamsActive );
	}

	static function streamStop( $postID, $options = null ) {
				
		if ( ! $postID ) {
			return;
		}
		if ( ! $options ) {
			$options = self::getOptions();
		}

		$pid = get_post_meta( $postID, 'iptvPid', true );
		if ( ! $pid ) {
			return;
		}

		$cmd = 'kill -KILL ' . $pid;

		update_post_meta( $postID, 'iptvStop', time() );
		update_post_meta( $postID, 'iptvLive', 0 );
		update_post_meta( $postID, 'iptvPid', 0 ); // no longer needed to run

		// update active streams list
		$iptvActive    = $options['uploadsPath'] . '/iptvActive.txt';
		$streamsActive = self::varLoad( $iptvActive );
		if ( ! is_array( $streamsActive ) ) {
			$streamsActive = array();
		}
		if ( array_key_exists( $postID, $streamsActive ) ) {
			unset( $streamsActive[ $postID ] );
			self::varSave( $iptvActive, $streamsActive );
		}
	}

	static function streamRunning( $postID, $options = null ) {
		
		// check if running
		if ( ! $postID ) {
			return;
		}
		if ( ! $options ) {
			$options = self::getOptions();
		}
		

		$pid  = get_post_meta( $postID, 'iptvPid', true );
		$live = get_post_meta( $postID, 'iptvLive', true );

		if ( ! $pid && $live ) {
			update_post_meta( $postID, 'iptvLive', 0 );
			return;
		}

		if ( $pid ) {
			if ( file_exists( "/proc/$pid" ) ) {
				update_post_meta( $postID, 'iptvRunning', time() );
				return 1;
			} else {
				update_post_meta( $postID, 'iptvLive', 0 );
				return 0;
			}
		}
	}

	static function streamMonitor( $postID, $options = null ) {
		// restart if process died
		$pid = get_post_meta( $postID, 'iptvPid', true );
		if ( ! $pid ) {
			return;
		}

		if ( ! self::streamRunning( $postID, $options ) ) {
			self::streamStart( $postID, $options );
		}
	}


	static function restreamPause( $postID, $stream, $options ) {

		$timeTo = self::timeTo( $stream . '/restreamPause' . $postID, 3, $options );
		$running = '';
		$vw_ipCamera = '';
		
		if ( ! $timeTo ) {
			return "<!--VideoWhisper-restreamPause:$stream#$postID:not_timeTo=$timeTo-->"; // already checked recently (prevent several calls on same request)
		}

		if ( $options['restreamPause'] ) {
			$paused = 1;
		} else {
			$paused = 0;
		}

		// updates restream Status
		$activeTime = time() - $options['restreamTimeout'] - 1;

		if ( $paused && $options['restreamAccessedUser'] ) {
			// access time
			$accessedUser = get_post_meta( $postID, 'accessedUser', true );
			if ( $accessedUser > $activeTime ) {
				$paused = 0;
			}
		}

		if ( $paused && $options['restreamAccessed'] ) {

			$accessed = get_post_meta( $postID, 'accessed', true );
			if ( $accesse > $activeTime ) {
				$paused = 0;
			}
		}

		if ( $paused && $options['restreamActiveOwner'] ) {
			// author site access time
			$userID     = get_post_field( 'post_author', $postID );
			$accessTime = get_user_meta( $userID, 'accessTime', true );

			if ( $accessTime > $activeTime ) {
				$paused = 0;
			}
		}

		if ( $paused && $options['restreamActiveUser'] ) {
			$userAccessTime = intval( get_option( 'userAccessTime', 0 ) );
			if ( $userAccessTime > $activeTime ) {
				$paused = 0;
			}
		}

		$streamMode = get_post_meta( $postID, 'stream-mode', true );

		if ( $streamMode == 'iptv' ) {
			// handle iptv
					$running = self::streamRunning( $postID, $options );

			if ( $paused && $running ) {
				self::streamStop( $postID, $options );
			}

			if ( ! $paused ) {
				if ( ! $running ) {
					self::streamStart( $postID, $options );
				}
			}
		} else // wowza restream handling
		{
			$streamFile = $options['streamsPath'] . '/' . $stream;

			if ( $paused ) {
				// disable
				if ( file_exists( $streamFile ) ) {
					unlink( $streamFile );
				}
			} else {
				// enable
				if ( ! file_exists( $streamFile ) ) {
					$vw_ipCamera = get_post_meta( $postID, 'vw_ipCamera', true );

					$myfile = fopen( $streamFile, 'w' );
					if ( $myfile ) {
						fwrite( $myfile, $vw_ipCamera );
						fclose( $myfile );
					}
				}
			}
		}

		update_post_meta( $postID, 'restreamPaused', $paused );
		
			return  "<!--VideoWhisper-restreamPause:$stream#$postID:Paused=$paused|Running=$running|ipCamera=$vw_ipCamera-->";

	}



	// IPTV/IPCam Setup UX

	static function videowhisper_stream_setup( $atts ) {
			// Shortcode: Setup IPTV / IPCamera ajax

			$options = self::getOptions();

			$atts = shortcode_atts(
				array(
					'include_css' => '1',
					'channel_id'  => '-1',
					'handler'     => 'wowza', // iptv/wowza
					'id'          => '',
				),
				$atts,
				'videowhisper_stream_setup'
			);
		
		$id   = sanitize_title( $atts['id'] );
		if ( ! $id ) {
			$id = uniqid();
		}

			$handler = $atts['handler'];
		if ( isset( $_GET['h'] ) ) {
			$handler = sanitize_file_name( $_GET['h'] );
		}

			$postID = intval( $atts['channel_id'] );
		if ( $postID < 0 ) {
			$postID = 0;
		}

			$current_user = wp_get_current_user();

			$address      = 'rtsp://[user:password]@public-IP-or-Domain[:port]/[stream-path]';
			$channelTitle = 'New';

			$addButton = 'Setup Stream';

		if ( $postID && $current_user ) {

			$channel = get_post( $postID );

			if ( $channel ) {
				if ( $channel->post_author == $current_user->ID ) {
					$address      = get_post_meta( $postID, 'vw_ipCamera', true );
					$channelTitle = $channel->post_title;
					$addButton    = 'Update Stream Address';

				} else {
					$postID    = 0;
					$htmlCode .= 'Only owner can edit existing channel address.';
				}
			}
		}

			$streamInfoCode = '<H4>IPTV / IP Camera - Stream Access Requirements</H4>
<UL>
<LI>You will need the stream address of your IP camera or IPTV channel. Insert address exactly as it works in <a target="_blank" href="http://www.videolan.org/vlc/index.html">VLC</a> (File > Open Network) or other player. Test before submitting. </LI>
<LI>Address should start with one of these supported protocols: rtsp://, rtmp://, udp://, rtmps://, wowz://, wowzs:// .</LI>
<LI>For increased playback support, H264 or H265 video with AAC audio encoded streams should be configured if possible, from IP Camera / Streaming source settings. </LI>
<LI>For IP cameras, you can find RTSP address in its documentation or camera provider support.</LI>
<LI>Username and password of IP camera / stream needs to be specified if needed to access that stream.</LI>
<LI>Port needs to be specified if different from default for that protocol. Non standard ports (other than than 554 RTSP, 1935 RTMP) may be rejected by firewall. Contact site/server administrator if you need to use special.</LI>
<LI>If device/stream does not have a public address, your local network administrator can <a target="_blank" rel="nofollow" href="https://portforward.com/">Forward Camera Port trough Router</a>.</LI>
<LI>If your network is not publicly accessible (your ISP did not allocate a static public IP), your local network or IP camera administrator can setup <a target="_blank" rel="nofollow"  href="https://docs.cpanel.net/cpanel/domains/dynamic-dns/">Dynamic DNS</a> (DDNS) for external access.</LI>
<LI>For a free consultation for re-streaming, <a href="https://consult.videowhisper.com">Contact</a> and include a sample stream address for evaluation.</LI>
</UL>';

			$imgCode = '';
		if ( $postID ) {
			$snapshotPath = get_post_meta( $postID, 'vw_lastSnapshot', true );
			if ( $snapshotPath ) {
				$imgCode = '<p>Last Snapshot:<br><IMG class="ui rounded image big" SRC="' . self::path2url( $snapshotPath ) . '"></p>';
			}
		}

			self::enqueueUI();

			$ajaxurl = wp_nonce_url( admin_url() . 'admin-ajax.php?action=vwls_stream_setup&channel=' . $postID . '&h=' . $handler . '&id=' . $id, 'vwsec' );

			$htmlCode = <<<HTMLCODE
<H4>Setup IPTV / IP Camera - Existing Stream : $channelTitle</H4>
<!-- $handler -->			
<div id="videowhisperResponse$id">
Stream Address <input name="address" type="text" id="address" value="$address" size="80" maxlength="250"/>
<BR><input class="ui button" type="submit" name="button" id="button" value="$addButton" onClick="loadResponse$id('<div class=\'ui active inline text large loader\'>Trying to connect. Please wait...</div>', '$ajaxurl&address=' + escape(document.getElementById('address').value))"/>
$imgCode
</div>

<script type="text/javascript">
var \$j = jQuery.noConflict();
var loader$id;

	function loadResponse$id(message, request_url){

	if (message)
	if (message.length > 0)
	{
	  \$j("#videowhisperResponse$id").html(message);
	}
		if (loader$id) loader$id.abort();

		loader$id = \$j.ajax({
			url: request_url,
			success: function(data) {
				\$j("#videowhisperResponse$id").html(data);
			}
		});
	}
</script>
$streamInfoCode
HTMLCODE;

		if ( $atts['include_css'] ) {
			$htmlCode .= '<STYLE>' . html_entity_decode( stripslashes( $options['customCSS'] ) ) . '</STYLE>';
		}

			return $htmlCode;
	}


	static function respond( $msg, $request = '' ) {		
			echo $msg ; // outputs setup forms, escaped on generation
			die;
		}



	// ! AJAX IPTV / IP Camera - Stream Setup
	static function vwls_stream_setup() {
		
		//verify nonce
				$nonce = $_REQUEST['_wpnonce'];
				if ( ! wp_verify_nonce( $nonce, 'vwsec' ) ) {
					echo 'Invalid nonce!';
					exit;
				}
				
		$options = self::getOptions();

		ob_clean();
	
		$id      = esc_attr( sanitize_title( $_GET['id'] ) );
		$postID  = intval( $_GET['channel'] );
		$handler = esc_attr( sanitize_text_field( $_GET['h'] ) );
		$error = '';

		$devMode = 0;
		if ( ! is_user_logged_in() ) {
			$postID = 0;
		} else {
			$current_user = wp_get_current_user();
			if ( user_can( $current_user, 'administrator' ) ) {
				$devMode = 1;
			}
		}
		

		$ajaxurl =  wp_nonce_url( admin_url() . 'admin-ajax.php?action=vwls_stream_setup&channel=' . $postID . '&h=' . $handler . '&id=' . $id, 'vwsec' );;

		$address  = sanitize_text_field( $_GET['address'] ) ;
		$label    = esc_attr( sanitize_file_name( $_GET['label'] ) );
		$username = esc_attr( sanitize_file_name( $_GET['username'] ) );
		$email    = sanitize_email( $_GET['email'] );

		if ( $postID ) {
			$current_user = wp_get_current_user();
			$channel      = get_post( $postID );

			if ( ! $channel ) {
				$error .= 'Channel not found!';
			} elseif ( $channel->post_author != $current_user->ID ) {
				$postID = 0;
				$error .= 'Only owner can edit existing channel address.';
			}
		}
		
		if ( !$options['enable_exec'] ) $error .= 'Executing server commands is disabled from plugin settings.';

		if ( ! $label ) {

			if ( ! $address ) {
				$error = 'A stream address is required';

			} else {
	
				// protocol
				list($addressProtocol) = explode( ':', strtolower( $address ) );
				if ( ! in_array( $addressProtocol, array( 'rtsp', 'udp', 'srt', 'rtmp', 'rtmps', 'wowz', 'wowzs', 'http', 'https' ) ) ) {
					$error .= ( $error ? '<br>' : '' ) . "Address protocol not supported ($addressProtocol). Address should use one of these protocols: rtsp://, srt:// udp://, rtmp://, rtmps://, wowz://, wowzs://";

				}

				// demo
				if ( strstr( $address, '[' ) || strstr( $address, 'stream-path' ) ) {
					$error .= ( $error ? '<br>' : '' ) . 'Address should not contain special characters or sample path provided as demo. You need your own address to test. Insert address exactly as it works in <a target="_blank" rel="nofollow" href="http://www.videolan.org/vlc/index.html">VLC</a> or other player.';
				}

				// local
				if ( strstr( $address, '192.168.' ) || strstr( $address, 'localhost' ) ) {
					$error .= ( $error ? '<br>' : '' ) . 'Address host should point to a publicly accessible device (a <a target="_blank"  href="https://www.iplocation.net/public-vs-private-ip-address">public IP</a> or domain). When address points to a local (intranet) address (192.168..) or localhost, stream is not accessible from internet.';

				}
			}

			//vars escaped previously
			$retryCode = <<<HTMLCODE
<BR>Stream Address <input name="address" type="text" id="address" value="$address" size="80" maxlength="250"/>
<BR><input type="submit" name="button" id="button" value="Try Stream" onClick="loadResponse$id('<div class=\'ui active inline text large loader\'>Trying to connect. Please wait...</div>', '$ajaxurl&address=' + escape(document.getElementById('address').value))"/>
HTMLCODE;

			if ( $error ) {
				self::respond( wp_kses_post( $error ) . $retryCode );
			}

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
				self::respond( 'Error - Folder does not exist and could not be created: ' . esc_html( $dir ) . ' - ' . esc_html( $error['message'] ) );
			}

			$filename     = $dir . '/stream' . uniqid() . '.jpg';
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
			if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );
			if ( $options['enable_exec'] ) exec( "echo '$cmd' >> $log_file_cmd", $output, $returnvalue );

			$lastLog = $options['uploadsPath'] . '/lastLog-streamSetup.txt';
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
			if ( $devMode ) {
				$devInfo = "[Admin Dev Info RTSP-TCP: $cmd]";
			}

			// try also try over udp without $cmdP
			if ( ! file_exists( $filename ) ) {
				$cmd = $options['ffmpegSnapshotTimeout'] . ' ' . $options['ffmpegPath'] . " -y -frames 1 \"$filename\" $cmdT -i \"" . $address . "\" >&$log_file  ";

				// echo $cmd;
				if ( $options['enable_exec'] ) exec( $cmd, $output, $returnvalue );
				if ( $options['enable_exec'] ) exec( "echo '$cmd' >> $log_file_cmd", $output, $returnvalue );

				$lastLog = $options['uploadsPath'] . '/lastLog-streamSetup.txt';
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

				if ( $devMode ) {
					$devInfo .= " [Admin Dev Info RTSP-UDP: $cmd]";
				}
			}

			// failed
			if ( ! file_exists( $filename ) ) {
				
				$respondCode = 'Error: Stream could not be accessed.';
				if ( $devMode) {
					$respondCode .= '<br>Missing Snapshot: ' . esc_html($filename);
					//include contents of $log_file
					$respondCode .= '<pre>' . esc_html(get_file_contents($log_file)) . '</pre>';
				}
				$respondCode .= '<br>Snapshot could not be retrieved from ' . $addressProtocol . ': ' . $address . esc_html( $devInfo ) . $retryCode;
				self::respond( $respondCode );
			}

			$previewSrc = self::path2url( $filename );
			$imgCode    = '<IMG class="ui rounded image big" SRC="' . $previewSrc . '">';

			$infoCode = 'IP Camera/Stream is accessible: you can setup this channel stream.';

			$regCode = '';
			$extraGET = '';
			
			if ( ! is_user_logged_in() ) {
				if ( $options['ipcamera_registration'] ) {
					$regCode .= <<<HTMLCODE
<BR>Also provide an username and email to quickly setup an account for managing your camera securely.
<BR>Username<input name="username" type="text" id="username" value="" size="32" maxlength="64"/>
<BR>Email<input name="email" type="text" id="email" value="" size="64" maxlength="64"/>
HTMLCODE;
					$extraGET = "+ '&username=' + escape(document.getElementById('username').value)+ '&email=' + escape(document.getElementById('email').value)";

				} else {
					$addCode .= $infoCode;
					$addCode .= self::loginRequiredWarning();
					self::respond( wp_kses_post( $addCode . $imgCode ) );
				}
			}

				$addButton = 'Add Stream Channel';

				$channelTitle = '';
				$warnSuffix = '';
				
			if ( $channel ) {
				$channelTitle = esc_html( $channel->post_title );

				$warnSuffix = '';
				if ( $handler == 'wowza' ) {
					// re-stream channel ends in .stream
					$suffix    = '.stream';
					$suffixLen = strlen( $suffix );
					if ( substr( $channelTitle, -$suffixLen, $suffixLen ) != $suffix ) {
						$channelTitle .= $suffix;
					}
					$warnSuffix = '<BR>Stream channels end in ".stream" (will be added if missing) for this type of re-streaming.';
				}

				$addButton = 'Update Channel';
			}
			
				//vars static or escaped/secured previously
				$addCode .= <<<HTMLCODE
$infoCode
<BR>Channel Label
<input name="label" type="text" id="label" value="$channelTitle" size="32" maxlength="64"/>
$warnSuffix
$regCode
<input name="address" type="hidden" id="address" value="$address"/>
<BR><input class="ui button" type="submit" name="button" id="button" value="$addButton" onClick="loadResponse$id('<div class=\'ui active inline text large loader\'>Trying to connect. Please wait...</div>', '$ajaxurl&address=' + escape(document.getElementById('address').value) + '&label=' + escape(document.getElementById('label').value) $extraGET )"/>
<BR><BR>
HTMLCODE;
				self::respond( $addCode . wp_kses_post( $imgCode ) );
		} else // second step : add camera
			{

			if ( is_user_logged_in() ) {
				$current_user = wp_get_current_user();
				$userID       = $current_user->ID;
			} else // register new user
				{
				if ( ! $email || ! $username ) {
					self::respond( 'You must provide a valid username and email to register and setup your camera.' );
				}

				$userID = register_new_user( $username, $password );
				if ( is_wp_error( $userID ) ) {
					self::respond( 'Registration failed:' . esc_html( $userID->get_error_message() ) );
				}
			}

			if ( $handler == 'wowza' ) {
				// re-stream channel ends in .stream
				$suffix    = '.stream';
				$suffixLen = strlen( $suffix );
				if ( substr( $label, -$suffixLen, $suffixLen ) != $suffix ) {
					$label .= $suffix;
				}
			}

			// setup new channel post
			$post = array(
				'post_name'   => $label,
				'post_title'  => $label,
				'post_author' => $userID,
				'post_type'   => $options['custom_post'],
				'post_status' => 'publish',
			);

			global $wpdb;
			$existID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE `post_title` = '$label' AND `post_type`='" . $options['custom_post'] . "' LIMIT 0,1" ); // same name, diff than postID

			if ( $existID ) {
				if ( $postID != $existID ) {
					self::respond( 'A different channel with this name already exists:' . $label );
				}
			}

			if ( $postID ) {
				$post['ID'] = $postID;
				wp_update_post( $post );
			} else {
				$postID = wp_insert_post( $post );
			}

			// add to channels table if missing
			$table_channels = $wpdb->prefix . 'vw_lsrooms';

			$sql     = "SELECT * FROM $table_channels where name='$label'";
			$channel = $wpdb->get_row( $sql );

			if ( $channel ) {
				if ( $channel->owner != $userID ) {
					self::respond( 'Channel name already used by different owner:' . $label );
				}
			}

			$ztime    = time();
			$username = $current_user->user_login;
			if ( ! $channel ) {
				$sql       = "INSERT INTO `$table_channels` ( `owner`, `name`, `sdate`, `edate`, `rdate`,`status`, `type`) VALUES ('$userID', '$label', $ztime, $ztime, $ztime, 0, $rtype)";
				$htmlCode .= 'Channel was created: ' . $label;
			}

			// copy snapshot
			$dir       = $options['uploadsPath'];
			$dir      .= '/_setup';
			$filename     = $dir . '/stream' . uniqid() . '.jpg';
			$timestamp = filemtime( $filename );

			$dir  = $options['uploadsPath'];
			$dir .= "/$label";
			if ( ! file_exists( $dir ) ) {
				mkdir( $dir );
			}

			$dir .= '/snapshots';
			if ( ! file_exists( $dir ) ) {
				mkdir( $dir );
			}

			$snapshot = "$dir/$timestamp.jpg";
			copy( $filename, $snapshot );
			update_post_meta( $postID, 'vw_lastSnapshot', $snapshot );

			$url = get_permalink( $postID );

			// setup/run stream
			$streamReady = false;

			if ( $handler == 'wowza' ) {

				if ( file_exists( $options['streamsPath'] ) ) {

					if ( $address ) {
						if ( ! strstr( $label, '.stream' ) ) {
							$htmlCode .= '<BR>Channel name must end in .stream when re-streaming!';
							$address   = '';
						}
					}

					$file = $options['streamsPath'] . '/' . $label;

					if ( $address ) {

						$myfile = fopen( $file, 'w' );
						if ( $myfile ) {
							fwrite( $myfile, $address );
							fclose( $myfile );
							$htmlCode .= '<BR>Stream file setup:<br>' . $label . ' = ' . $address;
						} else {
							$htmlCode .= '<BR>Could not write file: ' . $file;
							$address   = '';
						}
					}

					if ( in_array( $sourceProtocol, array( 'http', 'https' ) ) ) {
						update_post_meta( $postID, 'stream-hls', $label ); // http restreaming as is
					}

					list($addressProtocol) = explode( ':', $address );
					update_post_meta( $postID, 'stream-protocol', $addressProtocol ); // source required for transcoding
					update_post_meta( $postID, 'stream-mode', 'stream' );

					$streamReady = 1;

				} else {
					$htmlCode .= '<BR>Stream file could not be setup. Streams folder not found: ' . $options['streamsPath'];
				}
			} else {
				// iptv: ffmpeg

				self::streamStart( $postID, $options );

				$htmlCode .= '<BR>Stream started.';

				$streamReady = 1;

			}

			if ( $streamReady ) {

					update_post_meta( $postID, 'vw_ipCamera', $address );
					update_post_meta( $postID, 'stream-type', 'restream' );

					update_post_meta( $postID, 'edate', time() ); // detected and setup: ready to go live
					self::streamSnapshot( $label, true, $postID ); // update channel snapshot

							$htmlCode .= '<br><a href="' . get_permalink( $postID ) . '" class="ui button">Watch Channel</a>';
			}

			self::respond( wp_kses_post( $htmlCode ) );

		}
		// output end
		die;
	}


}
