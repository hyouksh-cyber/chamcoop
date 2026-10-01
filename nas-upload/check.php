<?php header('Content-Type: text/html; charset=utf-8'); ini_set('display_errors','1'); error_reporting(E_ALL);
echo '<meta charset="utf-8"><body style="font-family:Malgun Gothic"><h3>서버 점검</h3>';
echo 'PHP 버전: <b>'.PHP_VERSION.'</b><br>';
echo '폴더 쓰기: '.(is_writable(__DIR__) ? '가능' : '<b style="color:red">불가 (권한 설정 필요)</b>').'<br>';
echo 'api.php 존재: '.(file_exists(__DIR__.'/api.php') ? '예' : '<b style="color:red">아니오</b>').'<br>';
echo 'setup 완료: '.(file_exists(__DIR__.'/data/setup.lock') ? '예' : '아니오').'<br>';
foreach (['json','session','mbstring','zip'] as $e) echo "확장 $e: ".(extension_loaded($e) ? '있음' : '<b style="color:red">없음</b>').'<br>';
echo 'password_hash: '.(function_exists('password_hash') ? '있음' : '<b style="color:red">없음(PHP 5.5 미만)</b>');
