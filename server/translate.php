<?php
// chat server for quick ajax requests


define( 'VW_DEVMODE', 1 );
if ( VW_DEVMODE ) {
	ini_set( 'display_errors', 1 ); // debug only
	error_reporting( E_ALL & ~E_NOTICE & ~E_STRICT );
}

$time0 = microtime(true);

define( 'SHORTINIT', true );
include_once '../../../../wp-load.php';

$response = [];

//global $wpdb;
$options = get_option( 'VWliveStreamingOptions' );


			if ( $options['corsACLO'] ) {
				$http_origin = ( isset($_SERVER['HTTP_ORIGIN']) &&$_SERVER['HTTP_ORIGIN'] )  ? $_SERVER['HTTP_ORIGIN'] : $_SERVER['HTTP_REFERER'];
				$response['HTTP_ORIGIN'] = $http_origin;

				$found   = 0;
				$domains = explode( ',', $options['corsACLO'] );
				foreach ( $domains as $domain ) {
					if ( $http_origin == trim( $domain ) ) {
						$found = 1;
					}
				}

				if ( $found ) {
					header( 'Access-Control-Allow-Origin: ' . $http_origin );
					header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE, HEAD' ); // POST, GET, OPTIONS, PUT, DELETE, HEAD
					header( 'Access-Control-Allow-Credentials: true' );
					header( 'Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With' ); // Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With

					if ( 'OPTIONS' == $_SERVER['REQUEST_METHOD'] ) {
						status_header( 200 );
						exit();
					}
				}
			}
			

//

//https://github.com/DeepLcom/deepl-php 
//https://www.deepl.com/account/summary

$authKey =  $options['deepLkey']; 
if (!$authKey) 
{
	$response['error'] = 'Missing DeepL key from plugin settings.';
	
	echo json_encode( $response );
	die();	
}


// session info received through VideoWhisper POST var
$VideoWhisper = isset($_POST['VideoWhisper']) && is_array($_POST['VideoWhisper']) ? $_POST['VideoWhisper'] : [];

if ($VideoWhisper) {
    $userID     = intval($VideoWhisper['userID']);
    $sessionID  = intval($VideoWhisper['sessionID']);
    $sessionKey = intval($VideoWhisper['sessionKey']);
}

$message = isset($_POST['message']) && is_array($_POST['message']) ? $_POST['message'] : []; // array elements sanitized individually
if ($message){
    $m_text = sanitize_textarea_field($message['text']);
    $m_language = sanitize_text_field($message['language']);
    $m_flag = sanitize_text_field($message['flag']);
}
    
// target language
$language = sanitize_text_field($_POST['language'] ?? '');
$flag = sanitize_text_field($_POST['flag'] ?? '');

if ( (!$message || !$VideoWhisper || !$language) && !$_GET['update_languages']) 
{
	$response['error'] = 'Missing required parameters.';
	echo json_encode( $response );
	die();	
}




if ( $_GET['update_languages'] )
{
	//administrative request	
}
else
{
	//check if session is valid 
			global $wpdb;
			$table_sessions1 = $wpdb->prefix . 'vw_sessions';
			$sqlS1    = "SELECT * FROM $table_sessions1 WHERE id='$sessionID' AND uid='$userID' AND status='1' LIMIT 1";
			$session1 = $wpdb->get_row( $sqlS1 );

			$table_sessions2 = $wpdb->prefix . 'vw_lwsessions';
			$sqlS2    = "SELECT * FROM $table_sessions2 WHERE id='$sessionID' AND uid='$userID' AND status='1' LIMIT 1";
			$session2 = $wpdb->get_row( $sqlS2 );

			if ( !$session1 && !$session2 ) 	
			{
				$response['error'] = 'Invalid session!';
				$response['sql1'] = $sqlS1 ;
				$response['sql2'] = $sqlS2 ;
				echo json_encode( $response );
				die();			
			}
}


//DeepL API
include_once 'vendor/autoload.php';
$translator = new \DeepL\Translator($authKey);


//update supported langues
if ($_GET['update_languages'] == 'videowhisper' )
{
	$sources = [];
	$sourceLanguages = $translator->getTargetLanguages();;
foreach ($sourceLanguages as $sourceLanguage) 
    $sources [ strtolower($sourceLanguage->code) ]= $sourceLanguage->name ; // Example: 'English (en)'
    
    update_option('VWdeepLlangs', $sources );
    
	echo 'Languages Updated: ' . serialize($sources) . '<br>';
	var_dump($sources);
	
	die();	
}

//translate
if ($message && $language && $VideoWhisper) 
{

try {	
 
 $translation = $translator->translateText( $m_text, substr($m_language, 0, 2) , $language );
 $response['translation'] = $translation->text;
 $response['detectedSourceLang'] = $translation->detectedSourceLang;
 
} catch (\DeepL\DocumentTranslationException $error) {
	    $response['error'] = ( $error->getMessage() ?? 'unknown error' );
    }

$response['language'] = $language;
$response['flag'] = $flag;

$time1 = microtime(true);

$response['duration'] = $time1 - $time0;

echo json_encode( $response );
die();
}
