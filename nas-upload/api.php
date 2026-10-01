<?php
date_default_timezone_set('Asia/Seoul');
session_start();
define('D', __DIR__.'/data');
const RANK = ['top'=>1,'mid'=>2,'admin'=>3,'staff'=>4];
const KINDS = ['notice','press','photo'];
const EXT_OK = ['jpg','jpeg','png','gif','webp','pdf','hwp','hwpx','doc','docx','xls','xlsx','ppt','pptx','zip','txt'];

function out($d = []) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>true]+$d, JSON_UNESCAPED_UNICODE); exit; }
function fail($m, $c = 400) { http_response_code($c); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'error'=>$m], JSON_UNESCAPED_UNICODE); exit; }
function jread($f, $def) { if (!file_exists($f)) return $def; $j = json_decode(file_get_contents($f), true); return $j === null ? $def : $j; }
function jwrite($f, $d) { $fp = fopen($f, 'c'); flock($fp, LOCK_EX); ftruncate($fp, 0); fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); fflush($fp); flock($fp, LOCK_UN); fclose($fp); }
function users() { return jread(D.'/users.json', []); }
function orgs() { return jread(D.'/orgs.json', []); }
function safe($s) { return trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]/u', '_', $s)); }
function stamp() { return date('Y년m월d일H시i분'); }
function orgsOf($u) { $all = array_column(orgs(), 'id'); return RANK[$u['role']] <= 2 ? $all : array_values(array_intersect($u['orgs'] ?? [], $all)); }
function orgName($id) { foreach (orgs() as $o) if ($o['id'] === $id) return $o['name']; return $id; }
function me() {
  if (empty($_SESSION['uid'])) fail('로그인이 필요합니다.', 401);
  foreach (users() as $u) if ($u['id'] === $_SESSION['uid'] && $u['status'] === 'active') return $u;
  fail('로그인이 필요합니다.', 401);
}
function curOrg($u) { $o = $_SESSION['org'] ?? ''; if (!in_array($o, orgsOf($u), true)) fail('접근할 수 없는 기관입니다.', 403); return $o; }
function need($u, $maxRank) { if (RANK[$u['role']] > $maxRank) fail('권한이 없습니다.', 403); }
function menuOk($u, $m) { return RANK[$u['role']] <= 2 || in_array($m, $u['menus'] ?? [], true); }
function unitOk($u, $org, $unit) { return RANK[$u['role']] <= 2 || in_array($unit, $u['units'][$org] ?? [], true); }
function od($org, $f = '') { return D."/$org".($f ? "/$f" : ''); }
function isClosed($org, $unit, $date) { $c = jread(od($org, 'closings.json'), []); return isset($c[$unit.'|'.substr($date, 0, 7)]); }
function pubUser($u) { unset($u['pw']); return $u; }
function body() { $j = json_decode(file_get_contents('php://input'), true); return is_array($j) ? $j : $_POST; }

$a = $_GET['a'] ?? '';
$in = body();

if (!file_exists(D.'/setup.lock') && $a !== 'status') fail('먼저 setup.php를 실행하세요.', 503);

switch ($a) {
case 'status': out(['setup'=>file_exists(D.'/setup.lock'), 'orgs'=>orgs()]);

case 'register':
  $id = trim($in['id'] ?? ''); $pw = $in['pw'] ?? ''; $name = trim($in['name'] ?? ''); $org = $in['org'] ?? ''; $role = $in['role'] ?? 'staff';
  if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $id)) fail('아이디는 영문/숫자/_ 3~20자입니다.');
  if (strlen($pw) < 6) fail('비밀번호는 6자 이상입니다.');
  if ($name === '') fail('이름을 입력하세요.');
  if (!in_array($org, array_column(orgs(), 'id'), true)) fail('기관을 선택하세요.');
  if (!in_array($role, ['mid','admin','staff'], true)) $role = 'staff';
  $us = users(); foreach ($us as $u) if ($u['id'] === $id) fail('이미 사용 중인 아이디입니다.');
  $us[] = ['id'=>$id,'name'=>$name,'pw'=>password_hash($pw, PASSWORD_DEFAULT),'role'=>$role,'status'=>'pending','orgs'=>[$org],'menus'=>['notice','press','photo'],'units'=>new stdClass,'canUnlock'=>false,'created'=>date('c')];
  jwrite(D.'/users.json', $us); out(['msg'=>'가입 신청이 완료되었습니다. 관리자 승인 후 로그인할 수 있습니다.']);

case 'login':
  foreach (users() as $u) if ($u['id'] === trim($in['id'] ?? '')) {
    if (!password_verify($in['pw'] ?? '', $u['pw'])) break;
    if ($u['status'] === 'pending') fail('아직 승인 대기 중입니다.');
    if ($u['status'] !== 'active') fail('사용이 중지된 계정입니다.');
    $org = $in['org'] ?? '';
    if (!in_array($org, orgsOf($u), true)) fail('이 기관에 접근 권한이 없습니다.');
    session_regenerate_id(true); $_SESSION['uid'] = $u['id']; $_SESSION['org'] = $org; out();
  }
  fail('아이디 또는 비밀번호가 올바르지 않습니다.');

case 'logout': session_destroy(); out();

case 'me':
  $u = me(); $o = curOrg($u);
  $allowed = array_values(array_filter(orgs(), fn($x) => in_array($x['id'], orgsOf($u), true)));
  out(['user'=>pubUser($u), 'org'=>$o, 'orgName'=>orgName($o), 'orgs'=>$allowed]);

case 'switch_org':
  $u = me(); if (!in_array($in['org'] ?? '', orgsOf($u), true)) fail('접근 권한이 없습니다.', 403);
  $_SESSION['org'] = $in['org']; out();

// ---------- 기관 관리 (최고관리자) ----------
case 'org_add':
  $u = me(); need($u, 1); $n = trim($in['name'] ?? ''); if ($n === '') fail('기관명을 입력하세요.');
  $ol = orgs(); $oid = 'o'.bin2hex(random_bytes(3)); $ol[] = ['id'=>$oid,'name'=>$n]; jwrite(D.'/orgs.json', $ol);
  @mkdir(od($oid,'uploads'), 0775, true); @mkdir(od($oid,'backups'), 0775, true);
  jwrite(od($oid,'types.json'), ['types'=>[],'units'=>[['id'=>'common','name'=>'기관공통','typeId'=>'']]]); jwrite(od($oid,'posts.json'), []); jwrite(od($oid,'closings.json'), new stdClass);
  out();
case 'org_del':
  $u = me(); need($u, 1); $id = $in['id'] ?? ''; $ol = orgs();
  if (count($ol) < 2) fail('마지막 기관은 삭제할 수 없습니다.');
  $ol = array_values(array_filter($ol, fn($o) => $o['id'] !== $id)); jwrite(D.'/orgs.json', $ol);
  if (is_dir(od($id))) @rename(od($id), D.'/_deleted_'.$id.'_'.date('YmdHis'));
  $us = users(); foreach ($us as &$x) $x['orgs'] = array_values(array_diff($x['orgs'] ?? [], [$id])); jwrite(D.'/users.json', $us);
  if (($_SESSION['org'] ?? '') === $id) $_SESSION['org'] = $ol[0]['id'];
  out();
case 'org_rename':
  $u = me(); need($u, 1); $ol = orgs(); foreach ($ol as &$o) if ($o['id'] === ($in['id'] ?? '')) $o['name'] = trim($in['name']); jwrite(D.'/orgs.json', $ol); out();

// ---------- 사용자 관리 ----------
case 'users_list':
  $u = me(); need($u, 2);
  out(['users'=>array_map('pubUser', users()), 'me'=>$u['id']]);
case 'user_update':
  $u = me(); need($u, 2); $org = curOrg($u); $us = users(); $found = false;
  foreach ($us as $i => &$t) if ($t['id'] === ($in['id'] ?? '')) {
    $found = true;
    if ($t['role'] === 'top') fail('최고관리자는 변경할 수 없습니다.');
    if ($u['role'] === 'mid' && $t['role'] === 'mid') fail('중간관리자는 최고관리자만 변경할 수 있습니다.', 403);
    if (!empty($in['delete'])) { array_splice($us, $i, 1); jwrite(D.'/users.json', $us); out(); }
    if (isset($in['status']) && in_array($in['status'], ['active','pending','rejected'], true)) $t['status'] = $in['status'];
    if (isset($in['role']) && in_array($in['role'], ['mid','admin','staff'], true)) {
      if ($in['role'] === 'mid' && $u['role'] !== 'top') fail('중간관리자 지정은 최고관리자만 가능합니다.', 403);
      $t['role'] = $in['role']; }
    if (isset($in['menus'])) $t['menus'] = array_values(array_intersect($in['menus'], ['notice','press','photo','closing']));
    if (isset($in['units'])) { if (!is_array($t['units'])) $t['units'] = []; $t['units'][$org] = array_values($in['units']); }
    if (isset($in['orgs'])) $t['orgs'] = array_values(array_intersect($in['orgs'], array_column(orgs(), 'id')));
    if (isset($in['canUnlock'])) { if ($u['role'] !== 'top') fail('마감해제 자격은 최고관리자만 부여할 수 있습니다.', 403); $t['canUnlock'] = (bool)$in['canUnlock']; }
    if (!empty($in['pw'])) { if (strlen($in['pw']) < 6) fail('비밀번호는 6자 이상입니다.'); $t['pw'] = password_hash($in['pw'], PASSWORD_DEFAULT); }
  }
  // mid가 대기중인 mid 신청자를 승인하는 것 차단
  if (!$found) fail('사용자를 찾을 수 없습니다.');
  jwrite(D.'/users.json', $us); out();

// ---------- 사업유형 / 사업단 ----------
case 'types_get': $u = me(); $o = curOrg($u); out(jread(od($o,'types.json'), ['types'=>[],'units'=>[]]));
case 'type_add': case 'type_del': case 'unit_add': case 'unit_del':
  $u = me(); need($u, 2); $o = curOrg($u); $t = jread(od($o,'types.json'), ['types'=>[],'units'=>[]]);
  $n = trim($in['name'] ?? '');
  if ($a === 'type_add') { if ($n === '') fail('이름을 입력하세요.'); $t['types'][] = ['id'=>'t'.bin2hex(random_bytes(3)),'name'=>$n]; }
  if ($a === 'unit_add') { if ($n === '') fail('이름을 입력하세요.'); $t['units'][] = ['id'=>'u'.bin2hex(random_bytes(3)),'name'=>$n,'typeId'=>$in['typeId'] ?? '']; }
  if ($a === 'type_del') { foreach ($t['units'] as $x) if ($x['typeId'] === ($in['id'] ?? '')) fail('사업단이 남아 있는 유형은 삭제할 수 없습니다. 사업단을 먼저 삭제하세요.');
    $t['types'] = array_values(array_filter($t['types'], fn($x) => $x['id'] !== $in['id'])); }
  if ($a === 'unit_del') { $id = $in['id'] ?? ''; if ($id === 'common') fail('기관공통은 삭제할 수 없습니다.');
    foreach (jread(od($o,'posts.json'), []) as $p) if ($p['unit'] === $id) fail('등록된 게시물이 있는 사업단은 삭제할 수 없습니다.');
    $t['units'] = array_values(array_filter($t['units'], fn($x) => $x['id'] !== $id)); }
  jwrite(od($o,'types.json'), $t); out();

// ---------- 게시물 ----------
case 'posts_list':
  $u = me(); $o = curOrg($u); $k = $_GET['kind'] ?? '';
  $ps = array_values(array_filter(jread(od($o,'posts.json'), []), fn($p) => $p['kind'] === $k));
  usort($ps, fn($x, $y) => strcmp($y['date'].$y['id'], $x['date'].$x['id'])); out(['posts'=>$ps]);

case 'post_save':
  $u = me(); $o = curOrg($u); $kind = $_POST['kind'] ?? ''; if (!in_array($kind, KINDS, true)) fail('잘못된 구분입니다.');
  if (!menuOk($u, $kind)) fail('이 메뉴 권한이 없습니다.', 403);
  $unit = $_POST['unit'] ?? 'common'; $date = $_POST['date'] ?? ''; $title = trim($_POST['title'] ?? '');
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) fail('날짜를 입력하세요.'); if ($title === '') fail('제목을 입력하세요.');
  if (!unitOk($u, $o, $unit)) fail('이 사업단에 대한 권한이 없습니다.', 403);
  if (isClosed($o, $unit, $date)) fail('해당 월은 마감되어 저장할 수 없습니다. (마감 해제 필요)', 423);
  $ps = jread(od($o,'posts.json'), []); $idx = null; $id = $_POST['id'] ?? '';
  if ($id !== '') { foreach ($ps as $i => $p) if ($p['id'] === $id) $idx = $i; if ($idx === null) fail('게시물을 찾을 수 없습니다.');
    $old = $ps[$idx]; if (!unitOk($u, $o, $old['unit'])) fail('권한이 없습니다.', 403);
    if (isClosed($o, $old['unit'], $old['date'])) fail('기존 게시물의 월이 마감되어 수정할 수 없습니다.', 423); }
  $keep = json_decode($_POST['keep'] ?? '[]', true) ?: []; $files = [];
  if ($idx !== null) foreach ($old['files'] as $f) if (in_array($f['stored'], $keep, true)) $files[] = $f; else @unlink(od($o,'uploads/'.$f['stored']));
  if (!empty($_FILES['files'])) foreach ($_FILES['files']['name'] as $i => $nm) {
    if ($_FILES['files']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
    if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) fail("'$nm' 업로드 실패 (파일이 너무 크거나 오류). 서버 업로드 한도를 확인하세요.");
    $ext = strtolower(pathinfo($nm, PATHINFO_EXTENSION)); if (!in_array($ext, EXT_OK, true)) fail("'$nm' 은(는) 허용되지 않는 형식입니다.");
    $st = bin2hex(random_bytes(8)).'.'.$ext; move_uploaded_file($_FILES['files']['tmp_name'][$i], od($o,'uploads/'.$st));
    $files[] = ['name'=>safe($nm), 'stored'=>$st];
  }
  $rec = ['id'=>$idx !== null ? $id : 'p'.date('ymdHis').bin2hex(random_bytes(2)), 'kind'=>$kind, 'unit'=>$unit, 'date'=>$date, 'title'=>$title,
    'tag'=>trim($_POST['tag'] ?? ''), 'body'=>$_POST['body'] ?? '', 'source'=>trim($_POST['source'] ?? ''), 'url'=>trim($_POST['url'] ?? ''),
    'files'=>$files, 'author'=>$idx !== null ? $old['author'] : $u['name'], 'updatedBy'=>$u['name'], 'updated'=>date('c')];
  if ($idx !== null) $ps[$idx] = $rec; else $ps[] = $rec;
  jwrite(od($o,'posts.json'), $ps); out(['post'=>$rec]);

case 'post_delete':
  $u = me(); $o = curOrg($u); $ps = jread(od($o,'posts.json'), []);
  foreach ($ps as $i => $p) if ($p['id'] === ($in['id'] ?? '')) {
    if (!menuOk($u, $p['kind']) || !unitOk($u, $o, $p['unit'])) fail('권한이 없습니다.', 403);
    if (isClosed($o, $p['unit'], $p['date'])) fail('마감된 월의 게시물은 삭제할 수 없습니다.', 423);
    foreach ($p['files'] as $f) @unlink(od($o,'uploads/'.$f['stored']));
    array_splice($ps, $i, 1); jwrite(od($o,'posts.json'), $ps); out();
  }
  fail('게시물을 찾을 수 없습니다.');

case 'file':
  $u = me(); $o = curOrg($u); $st = basename($_GET['k'] ?? ''); $p = od($o,'uploads/'.$st);
  if (!is_file($p)) { http_response_code(404); exit; }
  $ext = strtolower(pathinfo($st, PATHINFO_EXTENSION)); $mt = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','pdf'=>'application/pdf'][$ext] ?? 'application/octet-stream';
  header('Content-Type: '.$mt); header('Content-Length: '.filesize($p));
  if (!empty($_GET['name'])) header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($_GET['name']));
  readfile($p); exit;

// ---------- 마감 ----------
case 'closings_get': $u = me(); $o = curOrg($u); out(['closings'=>(object)jread(od($o,'closings.json'), [])]);
case 'close': case 'unclose':
  $u = me(); $o = curOrg($u); $unit = $in['unit'] ?? ''; $m = $in['month'] ?? '';
  if (!preg_match('/^\d{4}-\d{2}$/', $m)) fail('월 형식 오류');
  if (!menuOk($u, 'closing')) fail('마감 메뉴 권한이 없습니다.', 403);
  $c = jread(od($o,'closings.json'), []); $key = $unit.'|'.$m;
  if ($a === 'close') { if (!unitOk($u, $o, $unit)) fail('이 사업단 권한이 없습니다.', 403); $c[$key] = ['by'=>$u['name'],'at'=>date('c')]; }
  else { if (RANK[$u['role']] !== 1 && empty($u['canUnlock'])) fail('마감 해제는 최고관리자 또는 해제 자격자만 가능합니다.', 403); unset($c[$key]); }
  jwrite(od($o,'closings.json'), (object)$c); out();

// ---------- 서버 저장 ----------
case 'backup':
  $u = me(); $o = curOrg($u); $scope = $in['scope'] ?? 'org'; $unit = $in['unit'] ?? '';
  $pack = function ($org) use ($unit) { $ps = jread(od($org,'posts.json'), []); if ($unit !== '') $ps = array_values(array_filter($ps, fn($p) => $p['unit'] === $unit));
    return ['org'=>orgName($org), 'types'=>jread(od($org,'types.json'), []), 'posts'=>$ps, 'closings'=>jread(od($org,'closings.json'), [])]; };
  if ($scope === 'system') { need($u, 2); $data = ['savedAt'=>date('c'), 'orgs'=>orgs(), 'users'=>array_map('pubUser', users()), 'data'=>array_map($pack, orgsOf($u))]; $label = '전체시스템'; $dir = D.'/_backups'; }
  else { if ($unit !== '' && !unitOk($u, $o, $unit)) fail('권한이 없습니다.', 403); $data = $pack($o); $label = $unit !== '' ? '사업단'.$unit : orgName($o); 
    if ($unit !== '') foreach ($data['types']['units'] as $x) if ($x['id'] === $unit) $label = $x['name'];
    $dir = od($o,'backups'); }
  $fn = safe($label).'-'.stamp().'-'.safe($u['name']).'.json'; jwrite($dir.'/'.$fn, $data); out(['file'=>$fn]);

// ---------- 홈페이지 반영용 내려받기 ----------
case 'export_site':
  $u = me(); need($u, 3); $o = curOrg($u); $ps = jread(od($o,'posts.json'), []); usort($ps, fn($x, $y) => strcmp($y['date'], $x['date']));
  $dir = ['notice'=>'files/notice/','press'=>'files/press/','photo'=>'files/photo/']; $j = ['notice'=>[],'press'=>[],'photo'=>[]]; $add = [];
  foreach ($ps as $p) {
    $fs = array_map(function ($f) use ($p, $dir, &$add) { $add[] = [$p['kind'], $f['stored']]; return ['name'=>$f['name'], 'url'=>$dir[$p['kind']].$f['stored']]; }, $p['files']);
    if ($p['kind'] === 'notice') { $r = ['tag'=>$p['tag'] ?: '안내', 'date'=>str_replace('-', '.', substr($p['date'], 0, 7)), 'title'=>$p['title'], 'body'=>$p['body']]; if ($fs) $r['attachments'] = $fs; $j['notice'][] = $r; }
    elseif ($p['kind'] === 'press') { $r = ['tag'=>$p['tag'] ?: '보도자료', 'title'=>$p['title'], 'body'=>$p['body'], 'source'=>$p['source'], 'url'=>$p['url']]; if ($fs) $r['img'] = $fs[0]['url']; $j['press'][] = $r; }
    else $j['photo'][] = ['date'=>$p['date'], 'title'=>$p['title'], 'body'=>$p['body'], 'images'=>array_column($fs, 'url')];
  }
  if (!class_exists('ZipArchive')) fail('이 서버에는 ZipArchive가 없어 묶음 내려받기를 할 수 없습니다.');
  $tmp = tempnam(sys_get_temp_dir(), 'z'); $z = new ZipArchive; $z->open($tmp, ZipArchive::OVERWRITE);
  $z->addFromString('notices.json', json_encode($j['notice'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
  $z->addFromString('press.json', json_encode($j['press'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
  $z->addFromString('photos.json', json_encode($j['photo'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
  foreach ($add as [$k, $st]) $z->addFile(od($o,'uploads/'.$st), $dir[$k].$st);
  $z->close(); header('Content-Type: application/zip'); header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode('홈페이지반영용-'.stamp().'.zip')); header('Content-Length: '.filesize($tmp)); readfile($tmp); unlink($tmp); exit;

default: fail('알 수 없는 요청입니다.', 404);
}
