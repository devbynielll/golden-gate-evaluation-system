<?php

// PHP's development server is deliberately bound to 127.0.0.1 by the launcher.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
require __DIR__.'/environment.php';
[$root,$runtime]=ggcClientPaths();
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'/');
$public=ggcClientCanonical($root.'/public'); $file=ggcClientCanonical($public.$path);
if ($path!=='/' && $file && str_starts_with($file,$public.'/') && is_file($file)
    && !in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['php','phtml','phar'], true)) return false;
$app=ggcClientBoot();
if (str_starts_with($path,'/storage/')) {
    $base=ggcClientCanonical($runtime.'/storage/app/public'); $upload=ggcClientCanonical($base.'/'.substr($path,9));
    if (!$upload || !str_starts_with($upload,$base.'/') || !is_file($upload)) { http_response_code(404); return; }
    header('Content-Type: '.(mime_content_type($upload)?:'application/octet-stream'));
    header('X-Content-Type-Options: nosniff'); readfile($upload); return;
}
$app->handleRequest(\Illuminate\Http\Request::capture());
