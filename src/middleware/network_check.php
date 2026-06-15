<?php
require_once __DIR__ . '/file_access_lock/gateway_locker.php';
require_once __DIR__ . '/../config/logs.php';

verify_pipeline_access(['ratelimit.php']);

function check_ip()
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = $ip_list[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    $check_proxy_url = "https://proxycheck.io/v2/" . $ip . "?vpn=1&asn=1";
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $check_proxy_url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 8);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, env('CURLOPT_SSL_VERIFYPEER', false));
    $proxy_response = curl_exec($curl);
    if ($proxy_response !== false) {
        $ip_info = json_decode($proxy_response, true);
        $proxy_data = $ip_info[$ip];
        //If hosted on Local
        if (empty($proxy_data["proxy"]) || empty($proxy_data["type"])) {
            return ["status" => 'local', 'msg' => 'Local host detected', 'ip' => $ip];
        }
        //when hosted on cloud
        elseif ($proxy_data['proxy'] === 'yes' || $proxy_data['type'] === 'VPN') {
            log_activity("Security Alert: VPN/Proxy detected for IP: {$ip}");
            return ['status' => 'blocked', 'msg' => 'VPN or PROXY detected'];
        } else {
            return ['status' => 'allowed', 'msg' => 'Good networking', 'ip' => $ip];
        }
    }
}