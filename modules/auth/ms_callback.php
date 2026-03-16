<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';

session_start();
$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';

if (!$code || $state !== ($_SESSION['ms_state'] ?? '')) {
    die('Estado invalido. <a href="'.APP_URL.'/modules/auth/login.php">Volver</a>');
}

$clientId     = defined('MS_CLIENT_ID')     ? MS_CLIENT_ID     : '';
$clientSecret = defined('MS_CLIENT_SECRET') ? MS_CLIENT_SECRET : '';
$redirectUri  = APP_URL.'/modules/auth/ms_callback.php';

// Obtener token
$resp = file_get_contents('https://login.microsoftonline.com/common/oauth2/v2.0/token', false,
    stream_context_create(['http'=>['method'=>'POST','header'=>'Content-Type: application/x-www-form-urlencoded',
    'content'=>http_build_query(['client_id'=>$clientId,'client_secret'=>$clientSecret,
    'code'=>$code,'redirect_uri'=>$redirectUri,'grant_type'=>'authorization_code'])]]));

$token = json_decode($resp, true);
if (empty($token['access_token'])) {
    die('Error al obtener token. <a href="'.APP_URL.'/modules/auth/login.php">Volver</a>');
}

// Obtener perfil del usuario
$profile = json_decode(file_get_contents('https://graph.microsoft.com/v1.0/me', false,
    stream_context_create(['http'=>['header'=>'Authorization: Bearer '.$token['access_token']]])), true);

// Intentar obtener foto
$photoUrl = null;
try {
    $photoData = @file_get_contents('https://graph.microsoft.com/v1.0/me/photo/$value', false,
        stream_context_create(['http'=>['header'=>'Authorization: Bearer '.$token['access_token']]]));
    if ($photoData) {
        $dest = UPLOAD_DIR.'avatars/';
        if (!is_dir($dest)) mkdir($dest,0755,true);
        $fname = 'ms_'.md5($profile['id']).'.jpg';
        file_put_contents($dest.$fname, $photoData);
        $photoUrl = UPLOAD_URL.'avatars/'.$fname;
    }
} catch (Exception $e) {}

$msUser = [
    'id'    => $profile['id'],
    'mail'  => $profile['mail'] ?? $profile['userPrincipalName'] ?? '',
    'photo' => $photoUrl,
    'token' => $token['access_token'],
];

if (Auth::loginMicrosoft($msUser)) {
    header('Location: '.APP_URL.'/dashboard.php'); exit;
} else {
    header('Location: '.APP_URL.'/modules/auth/login.php?error=ms_no_user');
}
