<?php
namespace VideoWhisper\LiveStreaming;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// ini_set('display_errors', 1);

trait Requirements {
	// define and check requirements

	static function requirementsDefinitions() {
		 $adminSettings = 'admin.php?page=live-streaming&tab=';

		$broadcastPageID = get_option( 'vwls_page_manage' );
		if ( $broadcastPageID ) {
			$broadcastPage = get_permalink( $broadcastPageID );
		} else {
			$broadcastPage = $adminSettings . 'pages';
		}

		// ordered
		return array(

			'setup'                 => array(
				'title'   => 'Start Setup',
				'warning' => 'Plugin requires setup to configure and activate features.',
				'info'    => 'Setting up features. Setup involves multiple steps for configuring and activating live streaming features.',
				'fix'     => 'Start from Setup Overview page: see backend documentation, setup tutorial',
				'url'     => $adminSettings . 'setup',
			),

			'VWliveWebcams'         => array(
				'type'           => 'class_conflict',
				'class_conflict' => 'VWliveWebcams',
				'title'          => 'Choose BroadcastLiveVideo or PaidVideochat',
				'warning'        => 'Similar turnkey site plugin were detected on same setup.',
				'info'           => 'Having only 1 live streaming plugin is recommended. Multiple plugins/interfaces for similar features can be confusing to users. Also requires different streaming setups as configuring Session Control for one will disable connections from the other.',
				'fix'            => 'Disable one of the plugins (<a href="https://paidvideochat.com">PaidVideochat</a> or <a href="https://broadcastlivevideo.com">BroadcastLiveVideo</a>). Using PaidVideochat is recommended as it provides more advanced features, including Live Broadcast as chat mode. Running both for specific setups requires independent Session Control configurations for each (in different streaming plans, setups).',
				'url'            => 'plugins.php',
				'manual'         => 1,
			),

			/*
			'rtmp_server_configure' => array(
				'type'    => 'option_configured',
				'option'  => 'rtmp_server',
				'title'   => 'Configure a functional RTMP address for OBS and other encoders',
				'warning' => 'A valid RTMP address was not configured, yet.',
				'info'    => 'RTMP streaming applications for live video streaming and interactions.',
				'fix'     => 'Deploy solution on <a href="https://webrtchost.com/hosting-plans/#Complete-Hosting" target="_blank">Complete Turnkey Streaming Hosting from WebRTChost.com</A> (recommended) for full capabilities including HTML5 or add <A href="https://webrtchost.com/hosting-plans/#Streaming-Only" target="_blank">remote streaming services with RTMP/WebRTC/HLS/DASH</A> to existing setup or enable only basic videochat and live streaming functionality over RTMP with a <a target="_blank" href="https://hostrtmp.com/">remote RTMP address from HostRTMP.com</a>. For more details see <a href="https://videowhisper.com/?p=Requirements" target="_vwrequirements">requirements</a>.',
				'url'     => $adminSettings . 'server',
			),

			'vwsSocket' => array(
				'type'    => 'option_configured',
				'option'  => 'vwsSocket',
				'title'   => 'Configure P2P WebRTC using VideoWhisper WebRTC',
				'warning' => 'P2P WebRTC was not configured, yet. ',
				'info'    => 'P2P in HTML5 Videochat. When using Wowza SE, P2P is not required so you can Skip this.',
				'fix'     => 'Get a FREE or paid account from <a href="https://webrtchost.com/hosting-plans/#WebRTC-Only" target="_blank">WebRTC Host: P2P</a> and configure VideoWhisper WebRTC Adress & Token in settings, or install your own <a href="https://github.com/videowhisper/videowhisper-webrtc">VideoWhisper WebRTC</a> and own STUN/TURN servers. Or skip if you use Wowza SE and do not want to enable Auto with P2P for private calls, yet.',
				'url'     => $adminSettings . 'webrtc',
			),
*/
			'setup_pages'           => array(
				'title'   => 'Setup Pages',
				'warning' => 'Pages to access functionality are not setup, yet.',
				'info'    => 'Accessing main features: broadcast live channels, list channels.',
				'fix'     => 'Setup feature pages and menu from Pages tab in settings.',
				'url'     => $adminSettings . 'pages',
				'type'    => 'option_defined',
				'option'  => 'vwls_page_manage',
			),

			'ffmpeg'                => array(
				'title'   => 'FFMPEG on Web Host',
				'warning' => 'FFMPEG was not detected, yet.',
				'info'    => 'Stream snapshots, stream analysis, on demand dynamic transcoding between different encodings specific to WebRTC/RTMP/RTSP/HLS/MPEG, needed for HTML5 playback.',
				'fix'     => 'For full capabilities deploy solution on <a href="https://webrtchost.com/hosting-plans/#Complete-Hosting" target="_blank">Complete Turnkey Streaming Hosting from WebRTChost.com</A> (recommended). If you host on your own dedicated server or VPS and have administrator access, you can opt for the <a href="https://videowhisper.com/?p=FFMPEG-Installation">FFMPEG Installation service</A>.',
				'url'     => $adminSettings . 'hls',
			),
/*
			'wsURLWebRTC_configure' => array(
				'type'    => 'option_configured',
				'option'  => 'wsURLWebRTC',
				'title'   => 'Configure WebRTC relay for HTML5 WebRTC',
				'warning' => 'Wowza SE WebRTC relay address was not configured, yet.',
				'info'    => 'HTML5 interface for WebRTC broadcast / playback (mobile support).',
				'fix'     => 'Deploy solution on <a href="https://webrtchost.com/hosting-plans/#Complete-Hosting" target="_blank">Complete Turnkey Streaming Hosting from WebRTChost.com</A> (recommended) for full capabilities or add only WebRTC relay streaming service using <A href="https://webrtchost.com/hosting-plans/#Streaming-Only" target="_blank">remote streaming service with RTMP/WebRTC/HLS/DASH</A> (FFMPEG required on web host). For more details see <a href="https://videowhisper.com/?p=Requirements" target="_vwrequirements">requirements</a>.',
				'url'     => $adminSettings . 'webrtc',

			),

			'rtmp_status'           => array(
				'title'   => 'Setup Session Control',
				'warning' => 'Session control was not detected, yet.',
				'info'    => 'Advanced support with external encoders like OBS and WebRTC broadcasts (shows channels as live on site, generates snapshots, usage stats, transcoding), protection of streaming address from unauthorized usage (broadcast and playback require the secret keys associated with active site channels), faster availability and updates for transcoding/snapshots. This checkpoint is triggered when there are active streaming sessions.',
				'fix'     => 'Deploy solution on <a href="https://webrtchost.com/hosting-plans/#Complete-Hosting" target="_blank">Complete Turnkey Streaming Hosting from WebRTChost.com</A> (recommended) for full capabilities or add only WebRTC relay streaming service using <A href="https://webrtchost.com/hosting-plans/#Streaming-Only" target="_blank">remote streaming service with RTMP/WebRTC/HLS/DASH</A> (FFMPEG required on web host). Then request streaming provider to enable it.',
				'url'     => $adminSettings . 'server',

			),

			'webrtc_test'           => array(
				'title'   => 'Test WebRTC Broadcast',
				'warning' => 'No WebRTC broadcast was detected, yet.',
				'info'    => 'Making sure WebRTC broadcasting works.',
				'fix'     => 'Go to Broadcast Live page, setup a channel and broadcast using WebRTC(HTML5) web interface as in this <a target"_tutorial" href="https://broadcastlivevideo.com/broadcast-html5-webrtc-to-mobile-hls/">HTML5 web broadcasting tutorial</a>. Requires session control to detect the stream.',
				'url'     => $broadcastPage,
			),

			'rtmp_test'             => array(
				'title'   => 'Test RTMP Broadcast',
				'warning' => 'No RTMP broadcast was detected, yet.',
				'info'    => 'Making sure RTMP broadcasting works.',
				'fix'     => 'Go to Broadcast Live page, setup a channel and broadcast using OBS or other an external RTMP encoder (mobile Larix iOS/Android) if session control is active, as in <a target"_tutorial" href="https://broadcastlivevideo.com/broadcast-with-obs-or-other-external-encoder/">OBS broadcasting tutorial</a>.',
				'url'     => $broadcastPage,
			),
*/
			'resources'             => array(
				'title'   => 'Review Suggested Plugins',
				'warning' => 'You did not check suggested plugins and support resources, yet.',
				'info'    => 'Extend solution functionality and optimize security, reliability.',
				'fix'     => 'Review suggested plugins and support options on Support Resources section.',
				'url'     => $adminSettings . 'support#plugins',
			),

			'appearance'            => array(
				'title'   => 'Review Appearance',
				'warning' => 'You did not review appearance settings, yet.',
				'info'    => 'Customizing logos, interface dark mode, styles.',
				'fix'     => 'Review appearance settings.',
				'url'     => $adminSettings . 'appearance',
			),

			'review'                => array(
				'title'   => 'Support Developers with a Review',
				'warning' => 'You did not review plugin, yet.',
				'info'    => 'If you have nice ideas, suggestions for further development or just want to share your experience or tips for other website owners, leave a review on WP repository. Skip this if you do not want to support the developers.',
				'fix'     => 'Leave a good review on WP repository to support plugin developers.',
				'url'     => 'https://wordpress.org/support/plugin/videowhisper-live-streaming-integration/reviews/#new-post',
				'manual'  => 1,
			),
		);
	}
	static function requirements_plugins_loaded() {
		 $remind = get_option( __CLASS__ . '_requirementsRemind' );

		if ( $remind < time() ) {
			if ( ! array_key_exists( 'tab', $_GET ) || $_GET['tab'] != 'setup' ) {
				add_action( 'admin_notices', array( __CLASS__, 'requirements_admin_notices' ) );
				add_action( 'wp_ajax_vws_notice', array( __CLASS__, 'vws_notice' ) );
			}
		}
	}

	static function requirementsStatus() {
		return get_option( __CLASS__ . '_requirements' );
	}

	static function requirementsGet() {
		 $defs  = self::requirementsDefinitions();
		$status = self::requirementsStatus();

		if ( ! $status ) {
			return $defs;
		}
		if ( ! is_array( $status ) ) {
			return $defs;
		}

		$merged = array();
		foreach ( $defs as $key => $value ) {
			if ( array_key_exists( $key, $status ) ) {
				$r_merged       = array_merge( (array) $value, (array) $status[ $key ] );
				$merged[ $key ] = $r_merged;
			} else {
				$merged[ $key ] = $value;
			}

			$merged[ $key ]['label'] = $key;

		}

		return $merged;
	}

	static function requirements_admin_notices() {
		$requirement = self::nextRequirement();

		if ( ! $requirement ) {
			return; // nothing to show
		}

		$htmlCode = self::requirementRender( $requirement['label'], 'overview', $requirement );

		$ajaxurl = get_admin_url() . 'admin-ajax.php';
		// onclick="noticeAction('skip',
		?>
	<div id="vwNotice" class="notice notice-success is-dismissible">
		<h4>Broadcast Live Video - Live Streaming: What to do next?</h4>Turnkey Site Setup Wizard with Requirement Checkpoints and Suggestions
		<?php echo wp_kses_post( $htmlCode ); ?>
		<a href="admin.php?page=live-streaming&tab=setup" >Setup Overview</a>
		| <a href="admin.php?page=live-streaming&tab=setup&skip=<?php echo esc_attr( $requirement['label'] ); ?>">Skip "<?php echo esc_html( $requirement['title'] ); ?>"</a>

		| <a href="admin.php?page=live-streaming&tab=support" >Support Resources</a>
		| <a target="_videowhisper" href="https://videowhisper.com/tickets_submit.php" >Contact Developers</a>
		| <a  href="#" onclick="noticeAction('remind', '<?php echo esc_attr( $requirement['label'] ); ?>')" >Remind me Tomorrow</a>

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

	<script>

		function noticeAction(task, label)
		{
					var data = {
					'action': 'vws_notice',
					'task': task,
					'label': label,
					};

		  jQuery.post('<?php echo esc_url( $ajaxurl ); ?>', data, function() {});

		  vwNotice = document.getElementById("vwNotice");
		  if (vwNotice) vwNotice.style.display = "none";
		}
	</script>
		<?php
	}

	static function vws_notice() {
		// update_option( 'my_dismiss_notice', true );

		$task = sanitize_text_field( $_POST['task'] );

		switch ( $task ) {
			case 'remind':
				update_option( __CLASS__ . '_requirementsRemind', time() + 86400 );
				break;

			case 'skip':
				$label = sanitize_file_name( $_POST['label'] );
				self::requirementUpdate( $label, 1, 'skip' );
				break;
		}

		ob_clean();
		exit;
	}


	// item handling

	static function requirementStatus( $requirement, $meta = 'status' ) {
		if ( ! $requirement ) {
			return 0;
		}
		if ( ! is_array( $requirement ) ) {
			return 0;
		}
		if ( ! array_key_exists( $meta, $requirement ) ) {
			return 0;
		}

		return $requirement[ $meta ];
	}



	static function requirementUpdate( $label, $value, $meta = 'status' ) {
		// echo "requirementUpdate($label, $value, $meta = 'status')";

		$status = self::requirementsStatus();
		if ( ! is_array( $status ) ) {
			$status = array();
		}

		if ( array_key_exists( $label, $status ) ) {
			$metas = $status[ $label ];
		} else {
			$metas = array();
		}

		if ( $meta == 'status' && $metas['status'] != $value ) {
			$metas['updated'] = time(); // mark as update only if changed
		}
		$metas[ $meta ] = $value;

		$status[ $label ] = $metas;
		update_option( __CLASS__ . '_requirements', $status );
	}

	static function requirementMet( $label ) {
		if ( ! self::requirementStatus( $label ) ) {
			self::requirementUpdate( $label, 1 );
		}
	}


	static function nextRequirement() {
		$requirements = self::requirementsGet();

		foreach ( $requirements as $label => $requirement ) {
			if ( ! self::requirementStatus( $requirement ) && ! self::requirementStatus( $requirement, 'skip' ) ) {
				$requirement['label'] = $label;
				return $requirement;
			}
		}

	}


	static function requirementDisabled( $label ) {

		if ( self::requirementCheck( $label ) ) {
			return '';
		} else {
			return 'disabled';
		}
	}

	static function requirementCheck( $label, $force = false ) {
		$requirements = self::requirementsGet();

		if ( ! array_key_exists( $label, $requirements ) ) {
			return 0; // not defined
		}

		$requirement = $requirements[ $label ];

		// already checked and valid
		if ( ! $force || ! in_array( $requirement['type'] ?? '' , array( 'option_configured' ) ) ) { // force only for possible checks
			if ( $requirement['updated'] ?? false ) {
				if ( $requirement['status'] ?? false ) {
					return $requirement['status'];
				}
			}
		}

				// check now if possible
		switch ( $requirement['type'] ?? false ) {
			case 'option_configured':
					// not configured
					$options        = self::getOptions();
					$optionsDefault = self::adminOptionsDefault();

					$requirementOption = $requirement['option'];

					$status = ( $options[ $requirementOption ] != $optionsDefault[ $requirementOption ] );

					self::requirementUpdate( $label, $status );
				return $status;

			case 'option_defined':
				$option = get_option( $requirement['option'] );
				if ( $option ) {
					$status = 1;
				} else {
					$status = 0;
				}
				self::requirementUpdate( $label, $status );
				return $status;

					break;

			case 'class_conflict':
				$status = ! ( class_exists( $requirement['class_conflict'] ) );
				self::requirementUpdate( $label, $status );
				return;

					break;
		}

			// otherwise manual
			return 0;
	}

	static function requirementRender( $label, $view = 'check', $requirement = null ) {
		$isPresent = self::requirementCheck( $label, $view == 'check' ); // force when check

		$htmlCode = '';
		$htmlButton = '';

		switch ( $view ) {
			case 'check':
				if ( ! $requirement ) {
					$requirements = self::requirementsDefinitions();
					$requirement  = $requirements[ $label ];
				}

				$htmlCode = 'Requirement check: ' . $requirement['title'];

				if ( $isPresent ) {
					$htmlCode .= ' = Checked.';
				} else {

					$htmlCode .= '<div class="vwInfo"><b>' . $requirement['warning'] . '</b> Required for: ' . $requirement['info'] .
					'<br>Quick Fix: ' . $requirement['fix'] . '</div>';
				}
				break;

			case 'overview':
				$htmlButton = '<br><a class="button" href="' . $requirement['url'] . '">' . ( $isPresent ? 'Review' : 'Proceed' ) . '</a>';

				if ( self::requirementStatus( $requirement, 'skip' ) ) {
					$htmlButton .= ' <a class="button" href="admin.php?page=live-streaming&tab=setup&unskip=' . $requirement['label'] . '">UnSkip</a>';
				}
				// elseif (!$isPresent) $htmlButton .=  ' <a class="button" href="admin.php?page=live-streaming&tab=setup&skip=' . $requirement['label'] . '">Skip</a>';

				if ( ! $isPresent && ( $requirement['manual'] ?? false ) ) {
					$htmlButton .= ' <a class="button" href="admin.php?page=live-streaming&tab=setup&done=' . $requirement['label'] . '">Done</a>';
				}

				if ( $isPresent ) {
					$htmlButton .= ' <a class="button" href="admin.php?page=live-streaming&tab=setup&check=' . $requirement['label'] . '">Check Again</a>';
				}

				if ( $requirement['updated'] ?? false ) {
					$htmlButton .= ' <small style="float:right"> Status: ' . ( $requirement['status'] ? 'Done' : 'Required' ) . ' Updated: ' . date( 'F j, Y, g:i a', $requirement['updated'] ) . '</small>';
				}

				$htmlCode .= '<div class="vwInfo"><b>' . $requirement['title'] . '</b>: ' . ( $isPresent ? 'Checked. ' : '<b>' . $requirement['warning'] . '</b> ' ) . 'Required for: ' . $requirement['info'] .
				'<br>Quick Fix: ' . $requirement['fix'] . $htmlButton . '</div>';

				break;
		}
		return $htmlCode;
	}

}
