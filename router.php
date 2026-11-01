<?php
// Router untuk PHP built-in server (pengganti aturan ProxyPass Apache):
//   /api/*       -> FastAPI 127.0.0.1:8000
//   /static/*    -> FastAPI 127.0.0.1:8000
//   /media/*     -> MediaMTX WebRTC 127.0.0.1:8889
//   /media-hls/* -> MediaMTX HLS 127.0.0.1:8888
// Selain itu dilayani dari folder frontend seperti biasa.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$proxy = null;
if (strpos($path, '/media-hls/') === 0) {
    $proxy = 'http://127.0.0.1:8888' . substr($path, strlen('/media-hls'));
} elseif (strpos($path, '/media/') === 0) {
    $proxy = 'http://127.0.0.1:8889' . substr($path, strlen('/media'));
} elseif (strpos($path, '/api/') === 0 || $path === '/api') {
    $proxy = 'http://127.0.0.1:8000' . $path;
} elseif (strpos($path, '/static/') === 0) {
    $proxy = 'http://127.0.0.1:8000' . $path;
}
if ($proxy !== null) {
    $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    if ($query) $proxy .= '?' . $query;
    $ch = curl_init($proxy);
    $headers = [];
    foreach (getallheaders() as $k => $v) {
        if (strtolower($k) === 'host') continue;
        $headers[] = "$k: $v";
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $_SERVER['REQUEST_METHOD'],
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => file_get_contents('php://input'),
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        http_response_code(502);
        echo 'Proxy error: ' . curl_error($ch);
        return true;
    }
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $rawHeaders = substr($resp, 0, $headerSize);
    $body = substr($resp, $headerSize);
    http_response_code($status);
    foreach (explode("\r\n", $rawHeaders) as $line) {
        if (stripos($line, 'Content-Type:') === 0 || stripos($line, 'Content-Disposition:') === 0) {
            header($line);
        }
    }
    echo $body;
    return true;
}
return false;
