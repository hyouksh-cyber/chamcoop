<?php
// 최초 1회 실행: http://192.168.111.49:8000/chamcoop-upload/setup.php
date_default_timezone_set('Asia/Seoul');
$D = __DIR__.'/data';
$msg = ''; $done = false;
if (file_exists($D.'/setup.lock')) { $done = true; $msg = '이미 설치가 완료되었습니다. 다시 설치하려면 data 폴더를 삭제하세요.'; }
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = trim($_POST['id'] ?? ''); $pw = $_POST['pw'] ?? ''; $name = trim($_POST['name'] ?? '');
  $orgs = array_values(array_filter(array_map('trim', explode("\n", $_POST['orgs'] ?? ''))));
  if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $id)) $msg = '아이디는 영문/숫자/_ 3~20자로 입력하세요.';
  elseif (strlen($pw) < 6) $msg = '비밀번호는 6자 이상이어야 합니다.';
  elseif ($name === '') $msg = '이름을 입력하세요.';
  elseif (!$orgs) $msg = '기관을 1개 이상 입력하세요.';
  else {
    if (!is_dir($D) && !@mkdir($D, 0775, true)) $msg = 'data 폴더를 만들 수 없습니다. 폴더 쓰기 권한을 확인하세요.';
    else {
      file_put_contents($D.'/.htaccess', "Require all denied\nDeny from all\n");
      file_put_contents($D.'/index.html', '');
      $ol = [];
      foreach ($orgs as $o) { $oid = 'o'.bin2hex(random_bytes(3)); $ol[] = ['id'=>$oid,'name'=>$o];
        foreach (['uploads','backups'] as $s) @mkdir("$D/$oid/$s", 0775, true);
        file_put_contents("$D/$oid/types.json", json_encode(['types'=>[],'units'=>[['id'=>'common','name'=>'기관공통','typeId'=>'']]], JSON_UNESCAPED_UNICODE));
        file_put_contents("$D/$oid/posts.json", '[]'); file_put_contents("$D/$oid/closings.json", '{}'); }
      @mkdir("$D/_backups", 0775, true);
      file_put_contents($D.'/orgs.json', json_encode($ol, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
      file_put_contents($D.'/users.json', json_encode([[ 'id'=>$id,'name'=>$name,'pw'=>password_hash($pw, PASSWORD_DEFAULT),'role'=>'top','status'=>'active','orgs'=>[],'menus'=>[],'units'=>new stdClass,'canUnlock'=>true,'created'=>date('c') ]], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
      file_put_contents($D.'/setup.lock', date('c'));
      $done = true; $msg = '설치 완료! 아래 버튼으로 로그인하세요. 보안을 위해 setup.php 파일은 삭제해도 됩니다.';
    }
  }
}
$checks = [
  'PHP 7.4 이상 ('.PHP_VERSION.')' => version_compare(PHP_VERSION,'7.4','>='),
  '폴더 쓰기 가능' => is_writable(__DIR__),
  'ZipArchive (홈페이지용 묶음 내려받기)' => class_exists('ZipArchive'),
];
?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>최초 설치</title>
<style>body{font-family:'Malgun Gothic',sans-serif;background:#f3f6f4;margin:0;padding:30px}.b{max-width:520px;margin:auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 2px 10px #0002}
h1{font-size:22px;margin-top:0}label{display:block;margin:12px 0 4px;font-weight:bold;font-size:14px}input,textarea{width:100%;padding:9px;border:1px solid #bbb;border-radius:6px;box-sizing:border-box;font:inherit}
button,.btn{margin-top:18px;padding:11px 20px;background:#2e7d5b;color:#fff;border:0;border-radius:6px;font-size:16px;cursor:pointer;text-decoration:none;display:inline-block}
.m{padding:10px;border-radius:6px;background:#fff3cd;margin:12px 0}.ok{background:#d4edda}li{margin:4px 0}.x{color:#c0392b}.y{color:#2e7d5b}</style></head><body><div class="b">
<h1>📂 공지·보도자료·사진 관리 시스템 설치</h1>
<ul><?php foreach($checks as $k=>$v) echo '<li class="'.($v?'y':'x').'">'.($v?'✔ ':'✘ ').htmlspecialchars($k).'</li>'; ?></ul>
<p style="font-size:13px;color:#666">업로드 한도: 파일당 <?=ini_get('upload_max_filesize')?> / 한 번에 <?=ini_get('post_max_size')?></p>
<?php if($msg) echo '<div class="m '.($done?'ok':'').'">'.htmlspecialchars($msg).'</div>'; ?>
<?php if($done): ?><a class="btn" href="index.html">로그인 화면으로</a>
<?php else: ?>
<form method="post">
<label>기관 목록 (한 줄에 하나)</label><textarea name="orgs" rows="3">대덕구시니어클럽
참살이사회적협동조합</textarea>
<label>최고관리자 아이디</label><input name="id" required>
<label>최고관리자 이름</label><input name="name" required>
<label>비밀번호 (6자 이상)</label><input name="pw" type="password" required>
<button>설치하기</button></form><?php endif; ?>
</div></body></html>
