=== Broadcast Live Video - Live Streaming : HTML5, WebRTC, HLS, RTSP, RTMP ===
Contributors: videowhisper
Author: VideoWhisper.com
Author URI: https://videowhisper.com
Plugin Name: Live Streaming - Broadcast Live Video
Plugin URI: https://BroadcastLiveVideo.com
Donate link: https://videowhisper.com/?p=Invest
Tags: live, streaming, video, broadcast, webcam 
Requires at least: 5.0
Tested up to: 6.6
Stable tag: trunk
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Live video streaming with chat, HTML5 HLS mobile delivery, RTMP encoders, RTSP IP cameras, membership control, pay-per-view channels, tips.

== Description ==

Broadcast live video streaming channels from various sources (*PC webcams, mobile cameras, RTSP IP cameras, desktop RTMP encoder apps like OBS, iOS/Android encoders as Larix Broadcaster*).

[Broadcast Live Video - Turnkey Streaming Site Solution](https://broadcastlivevideo.com/)

New: Latest version integrates HTML5 Videochat that supports both Wowza SE relay streaming and P2P WebRTC using a signaling server with STUN/TURN: 
[Free Open Source WebRTC Signaling Server](https://github.com/videowhisper/videowhisper-webrtc)

Turnkey Site Demo (using this plugin):
[VideoNow.Live](https://videonow.live/)

Plain WebRTC Live Streaming using HTML5 Videochat (no registration required):
[WebRTC Live Streaming / P2P Signaling](https://demo.videowhisper.com/vws-html5-livestreaming/)
[WebRTC Live Streaming / Wowza SE](https://demo.videowhisper.com/html5-videochat-php/)


Live channels can be displayed on website pages in web player with chat, tips or plain *HTML5* WebRTC / HLS / MPEG DASH live video streaming for mobile. Solution manages unlimited channels, membership types.

Functionality is stand alone (without need to use 3rd party services) so specific streaming hosting is required. Site owner (and users) have full ownership and access control for the live streaming content, without depending on 3rd party platforms and their terms.

For more details see the dedicated site for [Broadcast Live Video](https://broadcastlivevideo.com/ "Broadcast Live Video") solution.

*WebRTC* live video broadcasting and playback is supported, trough media server, as relay, for reliability and scalability or P2P trough WebRTC signaling server with STUN/TURN support.

= Key Features =
* live video channels (custom post type)
* channel setup and management page in frontend
* channel listings with live AJAX updates
* web broadcast with codec and quality settings (H264, Speex)
* 24/7 IP camera support (restream rtsp, rtmp, rtmps, udp streams) with Setup Wizard
* transcoding support for plain HTML5 HLS / MPEG DASH live video delivery (on request/auto)
* WebRTC support for plain HTML5 broadcasting and playback
* automated detection of iOS/Android Safari/Chrome for HTML5 delivery
* AJAX chat for viewers to chat while watching stream in HTML5 browser
* usage permissions by role, email, id, name
* limit broadcasting and watch time per channel
* premium channels (unlimited levels)
* channel stats (broadcast/watch time, last activity)
* P2P groups support for better, faster video streaming and lower rtmp server bandwidth usage
* external broadcaster/player support with special RTMP side (Wirecast, Flash Media Live Encoder, OBS Open Broadcaster Software, iOS/Android GoCoder app)
* generate snapshots for external streams with special RTMP side
* recording setting per channel, including for WebRTC streams
* video archive import with [Video Share VOD](http://wordpress.org/plugins/video-share-vod/  "Video Share / Video On Demand") WordPress Plugin
* playlists support (schedule video files for playback as live stream)
* paid channel support with myCRED integration (owner sets price)
* tips to broadcaster (both in Flash and HTML5 views) with myCRED, TeraWallet (WooCommerce Gateways)
* channel password / access list (owner sets list of user roles, logins, emails)
* custom floating logo, ads
* show event details (title, start, end, picture, description) while channel is offline
* integrates [Rate Star Review - AJAX Reviews for Content, with Star Ratings](https://wordpress.org/plugins/rate-star-review/ "Rate Star Review - AJAX Reviews for Content, with Star Ratings")

Use this software for setting up features like on Twitch TV, Justin TV, UStream tv, Mogulus, LiveStream, RealLifeCam, Stickam, YouNow, Blog tv, Live yahoo or their clones and alternatives. Also can be used in combinations with mobile apps similar to Periscope, Meerkat.

Includes a widget that can display online broadcasters and their show names.

= Samples: Live Sites and Turnkey Setups =
* [VideoNow.Live Channel Broadcasting](https://videonow.live "VideoNow.Live Broadcasting")
* [How to setup an alternative/clone site like Twitch, Hitbox, Livestream, JustinTv, UStream](https://turnkeyclone.com/twitch-tv-script-for-live-broadcasting/ "How to setup an alternative/clone site like Twitch, Hitbox, Livestream, JustinTv, UStream")

= Monetization =
* Membership Ready with Role Permissions: Can be used with membership/subscription plugins to setup paid membership types.
* Pay Per View Ready with Custom Post Type: Can be used with access control / sell content plugins to setup paid access to live broadcasts.
* Custom ads right in text chat box, for increased conversion
* Tips: Users can buy tokens (credits) to tip broadcasters using MyCred credits plugin.
* Recommended: [Paid Membership](https://wordpress.org/plugins/paid-membership/  "Paid Membership") WordPress Plugin allows members to purchase membership with credits (use same billing system as for tips)

= BuddyPress integration =
If BuddyPress is installed this will add a Live Stream tab to the group where users can watch live video and chat realtime. Admins can broadcast anytime from Admin > Live Streaming.

= Hosting Requirements =
* This plugin has [requirements](https://videowhisper.com/?p=Requirements "Live Streaming Requirements") beyond regular WordPress hosting specifications: specific live streaming servers, certificates, licensing, tools and configuration for HTML5 live camera streaming.
* For testing, register for a [Free Streaming account with WebRTC & RTMP/HLS](https://webrtchost.com/hosting-plans/#Streaming-Only)
* On own dedicated server or VPS you can deploy [VideoWhisper WebRTC](https://github.com/videowhisper/videowhisper-webrtc) signaling server, for basic live streaming functionality using TURN servers.
* Some advanced features require executing server commands for accessing tools like FFmpeg. This involves special configuration and security precautions on web server. By default executing commands (and these features) are disabled.


== Installation ==
* Review this plugin configuration tutorial (with some screenshots):
[BroadcastLiveVideo - Installation Tutorial](http://broadcastlivevideo.com/setup-tutorial/)
* Before installing this make sure all hosting requirements are met:
[Live Streaming Hosting Requirements](https://videowhisper.com/?p=Requirements)

== Screenshots ==
1. Live Broadcast (for publisher)
2. Live Video Watch (for active viewers, discuss online, see who else is watching)
3. Live Video Streaming (for passive viewers, simple live video)
4. Manage channels features from frontend 
5. Channels listing with AJAX live updates, star ratings
6. Playlist: Schedule videos to play as live stream
7. Manage channel videos 
8. Broadcast using HTML5 WebRTC and AJAX chat (iPad view)
9. Playback using HTML5 HLS and AJAX chat (iPhone view)
10. Access IP Camera / Re-Stream Setup Wizard

== Attributions == 
Some demo site screenshots show tests with the "Big Buck Bunny" video, available under Creative Commons Attribution at https://peach.blender.org/download/ .

== Documentation ==
* Plugin Homepage : https://broadcastlivevideo.com 
* Developer Contact : https://consult.videowhisper.com


== Demo ==
* Test it on live site https://videonow.live


== Extra ==
More information, the latest updates, other plugins and non-WordPress editions can be found at https://videowhisper.com/ .

== Changelog ==

= 6.1 = 
* Support for VideoWhisper server (RTMP/HLS + WebRTC)
* Automated import of streaming settings from VideoWhisper account

= 5.7 =
* Integrates HTML5 Videochat with client side snapshots (no longer relies on FFmpeg for WebRTC streams)
* Integrates offline video (teaser), floating logo in H5V

= 5.6 =
* Integrates HTML5 Videochat with P2P WebRTC signaling support

= 5.5 = 
* Removed Flash interfaces (discontinued by most browsers)
* Improved code

= 5.4 =
* Setup Overview page and notifications with requirements, steps
* Interface class setting for applying inverted (dark mode) or other Semantic UI  classes
* Adaptive streaming bitrate based on resolution
* 1/2 category selector mode with optional subcategories only
* Updated BuddyPress integration to create channel post
* Hosting limits bitrate
* On demand recording setting per channel using FFmpeg

= 5.3 =
* Admin bar menus for quick plugin access
* Tips in HTML chat: AJAX updated balance and Tip buttons as configured from backend with image, sound, amount
* MPEG DASH Shaka Player (by Google) for increased reliability
* Added support for tipping with WooWallet credits
* User channel shortcode [videowhisper_channel_user] to create a channel automatically for current user and display broadcasting interface
* POT translation file
* Re-Streaming / IP Camera optimizations : Auto-Pause and resume on channel access or owner activity

= 5.2 =
* AJAX Chat with HTML5 stream playback
* Semantic UI integration for improved interface
* Integrate [Rate Star Review - AJAX Reviews for Content, with Star Ratings](https://wordpress.org/plugins/rate-star-review/ "Rate Star Review - AJAX Reviews for Content, with Star Ratings")
* Filter by Tags, Name
* Options to set HTML5 interfaces (WebRTC broadcast, transcoded playback) as available or preferred
* WebRTC Broadcast with AJAX chat
* Automatically using most suitable delivery method in HTML5 view (WebRTC if directly available, HLS, MPEG-DASH)
* IP Camera / Re-Stream Setup Wizard

= 5.1 =
* WebRTC broadcast and playback
* MPEG DASH transcoding and delivery

= 4.67 =
* Broadcaster layout code

= 4.66 =
* User watch limit: Set watching time limits based on role (membership)
* Configure parameters by user role (overrides channel settings)
* Update channel image by uploading picture
* Event Info: While channel is offline show event title, start, end, description

= 4.65 =
* View Profile context menu in participants lists
* User avatar in participants context menu
* Easy webcam/microphone select from dropdowns on preview panel.

= 4.63 =
* Schedule playlists option
* Toggle default loader, loader static image option
* Advanced permission lists per channel: group chat, write in chat, view participants, private chat

= 4.61 =
* On demand archiving support in web broadcating app

= 4.42.1 =
* Tips for broadcaster using myCRED

= 4.32.51 =
* Auto transcoding (on HLS request or always)

= 4.32.41 =
* Access Password

= 4.32.37 =
* Unlimited premium channel levels
* Feature control by user roles/lists:
** custom/hide logo
** custom/hide ads
** transcode

= 4.32.21 =
* myCRED integration: allow selling access to channels
* channel access list (owner can configure user logins, emails, roles that can access)

= 4.32.8 =
* Improved iOS HLS transcoding reliability (retry and verify automatically)

= 4.32.8 =
* Navigation menus (setup in backend) for Channel Categories

= 4.32.7 =
* Improved channel AJAX listings: list by category in custom order

= 4.32.6 =
* Ban channel interface
* Web server load optimisation settings
* New channel meta

= 4.32.1 =
* Broadcasting application v4.32 (w. autopilot reconnect)

= 4.29.26 =
* Report log file usage in stats.

= 4.29.19 =
* Category and tag archive pages also include channels

= 4.29.17 =
* Display warning on channel page when channel time is exceeded or channel is offline

= 4.29.16 =
* Support for VideoWhisper Video Share / Video On Demand (VOD) plugin

= 4.29.8 =
* iOS detection, automated display of direct/transcoded HLS video
* external encoder authentication, status monitoring with special RTMP side

= 4.27.4 =
* Channel posts with frontend management and automated snapshot
* Channel management page where users can setup channes from frontend
* Channels list page, automatically updated with AJAX, pagination
* Shortcodes watch, video, HTML5 HLS, broadcast

= 4.27.3 =
* Improved admin settings with tabs and more options
* Control access by roles, ID, email
* Limit broadcasting and watch time per channel
* Premium channels with better features and quality
* Transcoding for iPhone / iPad support
* Toggle Logo/Watermark
* Channel statistics
* Broadcast directly from backend without widget
* Broadcast link only for logged in users

= 4.27 =
* Broadcaster application v4.27
* Insert online channel snapshots in posts and pages with [videowhisper livesnapshots] shortcode
* RTMP web session check support
* External authentication

= 4.25 =
* Broadcaster application v4.25
* Video & sound codec settings
* Floating watermark settings

= 4.07 =
* Broadcaster application v4.07
* Widget includes counter of room participants for each room

= 4.05 =
* Integrated latest application versions (with broadcaster application v4.05) that include P2P.
* Added more settings to control P2P / RTMP streaming, secure token if enabled, bandwidth detection.
* Fixed some possible security vulnerabilites for hosts with magic_quotes Off.

= 2.2 =
* BuddyPress integration: If BuddyPress is installed this will add a Live Stream tab to the group where users can watch live video and chat realtime. Admins can broadcast anytime from Admin > Live Streaming.

= 2.1 =
* Permissions for broadcasters (members, list) and watchers (all, members, list).
* Choose name to use in application (display name, login, nice name).

= 2.0 =
* Everything is in the plugin folder to allow automated updates.
* Settings page to fill rtmp address, some broadcaster options.

= 1.0.2 =
* Plugin to integrate live streaming installed in a videowhisper_streaming folder on site root.