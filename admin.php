<?php
declare(strict_types=1);
define('ROOT', __DIR__);
function db(): PDO { static $d = null; if ($d) return $d;
 try {
  foreach (['uploads','downloads'] as $x) if (!is_dir(ROOT."/$x")) mkdir(ROOT."/$x", 0755);
  if (!is_file(ROOT.'/.htaccess')) file_put_contents(ROOT.'/.htaccess', "<FilesMatch \"\\.(sqlite|sqlite-wal|sqlite-shm|txt)$\">\nRequire all denied\n</FilesMatch>\n");
  if (!is_file(ROOT.'/downloads/.htaccess')) file_put_contents(ROOT.'/downloads/.htaccess', "Require all denied\n");
  if (!is_file(ROOT.'/uploads/.htaccess')) file_put_contents(ROOT.'/uploads/.htaccess', "<FilesMatch \"(?i)\\.(?!(jpe?g|png|webp)$).+$\">\nRequire all denied\n</FilesMatch>\n");
  $d = new PDO('sqlite:'.ROOT.'/database.sqlite');
  $d->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $d->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  $d->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
  $d->exec("CREATE TABLE IF NOT EXISTS admins(id INTEGER PRIMARY KEY, username TEXT UNIQUE NOT NULL, hash TEXT NOT NULL, created_at INTEGER NOT NULL);
  CREATE TABLE IF NOT EXISTS categories(id INTEGER PRIMARY KEY, name TEXT NOT NULL, slug TEXT UNIQUE NOT NULL, description TEXT DEFAULT '');
  CREATE TABLE IF NOT EXISTS projects(id INTEGER PRIMARY KEY, title TEXT NOT NULL, slug TEXT UNIQUE NOT NULL, short_desc TEXT DEFAULT '', full_desc TEXT DEFAULT '', features TEXT DEFAULT '', category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL, thumb TEXT DEFAULT '', file_path TEXT DEFAULT '', orig_name TEXT DEFAULT '', file_type TEXT DEFAULT 'zip', file_size INTEGER DEFAULT 0, version TEXT DEFAULT '1.0.0', changelog TEXT DEFAULT '', instructions TEXT DEFAULT '', requirements TEXT DEFAULT '', status TEXT NOT NULL DEFAULT 'draft', featured INTEGER DEFAULT 0, downloads INTEGER DEFAULT 0, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL);
  CREATE INDEX IF NOT EXISTS i_p_status ON projects(status, updated_at);
  CREATE INDEX IF NOT EXISTS i_p_cat ON projects(category_id);
  CREATE TABLE IF NOT EXISTS settings(k TEXT PRIMARY KEY, v TEXT NOT NULL);
  CREATE TABLE IF NOT EXISTS download_logs(id INTEGER PRIMARY KEY, project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE, at INTEGER NOT NULL);
  CREATE TABLE IF NOT EXISTS login_attempts(id INTEGER PRIMARY KEY, ip TEXT, at INTEGER);");
  $s = $d->prepare('INSERT OR IGNORE INTO settings(k,v) VALUES(?,?)');
  foreach (['site_name'=>'Packrate','site_desc'=>'A library of downloadable web projects, templates and source files.','footer'=>'Files are provided as published by the site administrator.','contact_email'=>'','support_url'=>'','per_page'=>'12','max_upload_mb'=>'30','ad_enabled'=>'0','ad_seconds'=>'15','ad_link'=>'','ad_banner'=>'','ad_site_1'=>'','ad_site_2'=>''] as $k=>$v) $s->execute([$k,$v]);
  $mg = $d->prepare("INSERT OR IGNORE INTO settings(k,v) VALUES('migr_upload30','1')"); $mg->execute(); if ($mg->rowCount()) $d->exec("UPDATE settings SET v='30' WHERE k='max_upload_mb' AND v='20'");
 } catch (Throwable $e) { error_log($e->getMessage()); http_response_code(500); exit('The site is temporarily unavailable.'); }
 return $d; }
function cfg(): array { static $c = null; return $c ??= db()->query('SELECT k,v FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR); }
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function slugify(string $s): string { $s = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-'); return $s ?: 'item'; }
function asset(string $p): string { return $p.'?v='.(@filemtime(ROOT.'/'.$p) ?: 1); }
function fsize(int $b): string { foreach (['B','KB','MB','GB'] as $i=>$u) { if ($b < 1024 || $i == 3) return round($b, $i ? 1 : 0)." $u"; $b /= 1024; } return ''; }
function dt($t): string { return date('M j, Y', (int)$t); }
function icon(string $n): string { $p = ['search'=>'<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>','dl'=>'<path d="M12 4v11m0 0l-4-4m4 4l4-4M5 20h14"/>','menu'=>'<path d="M4 7h16M4 12h16M4 17h16"/>','box'=>'<path d="M3 7l9-4 9 4v10l-9 4-9-4zM3 7l9 4 9-4M12 11v10"/>','grid'=>'<rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="4" width="7" height="7" rx="1"/><rect x="4" y="13" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/>','tag'=>'<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1"/>','cog'=>'<circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/>','out'=>'<path d="M9 4H5v16h4M16 8l4 4-4 4M20 12H9"/>','plus'=>'<path d="M12 5v14M5 12h14"/>'];
 return '<svg class="ic" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($p[$n] ?? '').'</svg>'; }
function placeholder(string $t): string { $c = ['#e0e7ff','#ccfbf1','#ede9fe','#dbeafe'][crc32($t) % 4];
 return 'data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 200"><rect width="320" height="200" fill="'.$c.'"/><path d="M120 80l-30 20 30 20M200 80l30 20-30 20M175 70l-30 60" fill="none" stroke="#4f46e5" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/></svg>'); }
function thumb(array $p): string { return $p['thumb'] && is_file(ROOT.'/uploads/'.$p['thumb']) ? 'uploads/'.rawurlencode($p['thumb']) : placeholder($p['title']); }

ini_set('session.cookie_httponly', '1'); ini_set('session.use_strict_mode', '1'); ini_set('session.cookie_samesite', 'Lax');
session_start(); $pdo = db(); $S = cfg();
header('X-Frame-Options: DENY'); header('X-Content-Type-Options: nosniff');
$_SESSION['csrf'] ??= bin2hex(random_bytes(16));
function csrf(): string { return '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">'; }
function flash(string $m, string $t = 'ok'): void { $_SESSION['flash'] = [$t, $m]; }
function go(string $u): never { header('Location: '.$u); exit; }
const ADMIN_USER = 'halim987'; const ADMIN_PASS = '97&%87js'; // fixed login, no setup token
$r = $pdo->prepare('SELECT id,hash FROM admins WHERE username=?'); $r->execute([ADMIN_USER]); $ar = $r->fetch();
if (!$ar) $pdo->prepare('INSERT INTO admins(username,hash,created_at) VALUES(?,?,?)')->execute([ADMIN_USER, password_hash(ADMIN_PASS, PASSWORD_DEFAULT), time()]);
elseif (!password_verify(ADMIN_PASS, $ar['hash'])) $pdo->prepare('UPDATE admins SET hash=? WHERE id=?')->execute([password_hash(ADMIN_PASS, PASSWORD_DEFAULT), $ar['id']]);
$admins = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn(); $in = !empty($_SESSION['aid']); $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
function save_file(string $key, array $ok, int $max, string $dir): ?array { // returns [stored, orig, size, ext] or null; throws on invalid
 $up = (string)($_POST[$key.'_up'] ?? ''); // file uploaded in small parts by admin.js
 if ($up !== '') {
  if (!preg_match('/^[a-f0-9]{24}$/', $up)) throw new RuntimeException('Bad upload id.');
  $tp = ROOT."/$dir/tmp-$up.part"; if (!is_file($tp)) throw new RuntimeException('The uploaded file was not found. Please upload it again.');
  $nm = (string)($_POST[$key.'_name'] ?? ''); $ext = strtolower(pathinfo($nm, PATHINFO_EXTENSION));
  try {
   if (!isset($ok[$ext])) throw new RuntimeException('Unsupported file type: .'.h($ext));
   if (filesize($tp) > $max) throw new RuntimeException('File is larger than the '.fsize($max).' limit.');
   if (!in_array((new finfo(FILEINFO_MIME_TYPE))->file($tp), $ok[$ext], true)) throw new RuntimeException('File content does not match its extension.');
   if ($ext === 'zip' && file_get_contents($tp, false, null, 0, 2) !== 'PK') throw new RuntimeException('Not a valid ZIP archive.');
  } catch (Throwable $e) { @unlink($tp); throw $e; }
  $name = bin2hex(random_bytes(12)).'.'.($ext === 'jpeg' ? 'jpg' : ($ext === 'htm' ? 'html' : $ext)); $sz = filesize($tp);
  if (!rename($tp, ROOT."/$dir/$name")) { @unlink($tp); throw new RuntimeException('Could not store the file.'); }
  return [$name, preg_replace('/[^A-Za-z0-9._-]/', '_', basename($nm)), (int)$sz, $ext]; }
 $b64 = (string)($_POST[$key.'_b64'] ?? ''); // file sent as encoded text (hosts that block multipart uploads)
 if ($b64 !== '' && (empty($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE)) {
  $raw = base64_decode($b64, true); $nm = (string)($_POST[$key.'_name'] ?? ''); $ext = strtolower(pathinfo($nm, PATHINFO_EXTENSION));
  if ($raw === false || $raw === '') throw new RuntimeException('The file could not be read.');
  if (strlen($raw) > $max) throw new RuntimeException('File is larger than the '.fsize($max).' limit.');
  if (!isset($ok[$ext])) throw new RuntimeException('Unsupported file type: .'.h($ext));
  if (!in_array((new finfo(FILEINFO_MIME_TYPE))->buffer($raw), $ok[$ext], true)) throw new RuntimeException('File content does not match its extension.');
  if ($ext === 'zip' && substr($raw, 0, 2) !== 'PK') throw new RuntimeException('Not a valid ZIP archive.');
  if (in_array($ext, ['jpg','jpeg','png','webp'], true) && !@getimagesizefromstring($raw)) throw new RuntimeException('Not a valid image.');
  $name = bin2hex(random_bytes(12)).'.'.($ext === 'jpeg' ? 'jpg' : ($ext === 'htm' ? 'html' : $ext));
  if (file_put_contents(ROOT."/$dir/$name", $raw) === false) throw new RuntimeException('Could not store the file.');
  return [$name, preg_replace('/[^A-Za-z0-9._-]/', '_', basename($nm)), strlen($raw), $ext]; }
 if (empty($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) return null; $f = $_FILES[$key];
 if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed (error '.$f['error'].').');
 if ($f['size'] > $max) throw new RuntimeException('File is larger than the '.fsize($max).' limit.');
 $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)); if (!isset($ok[$ext])) throw new RuntimeException('Unsupported file type: .'.h($ext));
 $m = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']); if (!in_array($m, $ok[$ext], true)) throw new RuntimeException('File content does not match its extension.');
 $head = file_get_contents($f['tmp_name'], false, null, 0, 4);
 if ($ext === 'zip' && substr($head, 0, 2) !== 'PK') throw new RuntimeException('Not a valid ZIP archive.');
 if (in_array($ext, ['jpg','jpeg','png','webp'], true) && !@getimagesize($f['tmp_name'])) throw new RuntimeException('Not a valid image.');
 $name = bin2hex(random_bytes(12)).'.'.($ext === 'jpeg' ? 'jpg' : ($ext === 'htm' ? 'html' : $ext));
 if (!move_uploaded_file($f['tmp_name'], ROOT."/$dir/$name")) throw new RuntimeException('Could not store the file.');
 return [$name, preg_replace('/[^A-Za-z0-9._-]/', '_', basename($f['name'])), (int)$f['size'], $ext]; }
function rm(string $dir, string $n): void { if ($n !== '' && is_file(ROOT."/$dir/".basename($n))) @unlink(ROOT."/$dir/".basename($n)); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Invalid request token. Go back and reload the page.'); }
 $act = (string)($_POST['act'] ?? '');
 try {
  if ($act === 'login') {
   $pdo->prepare('DELETE FROM login_attempts WHERE at<?')->execute([time()-900]);
   $c = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip=?'); $c->execute([$ip]);
   if ($c->fetchColumn() >= 5) { http_response_code(429); throw new RuntimeException('Too many attempts. Try again in 15 minutes.'); }
   $s = $pdo->prepare('SELECT * FROM admins WHERE username=?'); $s->execute([trim((string)$_POST['username'])]); $a = $s->fetch();
   if ($a && password_verify((string)$_POST['password'], $a['hash'])) { session_regenerate_id(true); $_SESSION['aid'] = $a['id']; $_SESSION['csrf'] = bin2hex(random_bytes(16)); go('admin.php'); }
   $pdo->prepare('INSERT INTO login_attempts(ip,at) VALUES(?,?)')->execute([$ip, time()]); throw new RuntimeException('Username or password is incorrect.');
  }
  if (!$in) { http_response_code(403); exit('Sign in required.'); }
  if ($act === 'chunk') { header('Content-Type: application/json');
   try { $id = (string)($_POST['uid'] ?? ''); if (!preg_match('/^[a-f0-9]{24}$/', $id)) throw new RuntimeException('Bad upload id.');
    $max = max(1, (int)$S['max_upload_mb']) * 1048576; $p = ROOT."/downloads/tmp-$id.part"; $off = (int)($_POST['off'] ?? 0);
    foreach (glob(ROOT.'/downloads/tmp-*.part') ?: [] as $o) if (filemtime($o) < time() - 3600) @unlink($o);
    $raw = base64_decode((string)($_POST['data'] ?? ''), true); if ($raw === false || $raw === '') throw new RuntimeException('Bad upload part.');
    $cur = is_file($p) ? filesize($p) : 0; if ($off === 0 && $cur > 0 && $cur !== strlen($raw)) { @unlink($p); $cur = 0; }
    if ($cur === $off + strlen($raw)) { echo json_encode(['ok'=>true]); exit; }
    if ($cur !== $off) throw new RuntimeException('Upload went out of sync. Please try again.');
    if ($cur + strlen($raw) > $max) { @unlink($p); throw new RuntimeException('File is larger than the '.fsize($max).' limit.'); }
    if (file_put_contents($p, $raw, FILE_APPEND | LOCK_EX) === false) throw new RuntimeException('Could not store the file.');
    echo json_encode(['ok'=>true]);
   } catch (Throwable $e) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$e instanceof RuntimeException ? $e->getMessage() : 'Upload failed.']); }
   exit; }
  if ($act === 'logout') { session_destroy(); go('admin.php'); }
  if ($act === 'save_project') {
   $id = (int)($_POST['id'] ?? 0); $t = trim((string)$_POST['title']); if ($t === '') throw new RuntimeException('Title is required.');
   $slug = slugify(trim((string)$_POST['slug']) ?: $t); $max = max(1, (int)$S['max_upload_mb']) * 1048576;
   $old = null; if ($id) { $o = $pdo->prepare('SELECT * FROM projects WHERE id=?'); $o->execute([$id]); $old = $o->fetch() ?: throw new RuntimeException('Project not found.'); }
   $d = $pdo->prepare('SELECT id FROM projects WHERE slug=? AND id!=?'); $d->execute([$slug, $id]); if ($d->fetch()) throw new RuntimeException('That slug is already used.');
   $fl = save_file('file', ['zip'=>['application/zip','application/x-zip-compressed','application/octet-stream'],'html'=>['text/html','text/plain'],'htm'=>['text/html','text/plain']], $max, 'downloads');
   $im = save_file('thumb', ['jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'png'=>['image/png'],'webp'=>['image/webp']], 5242880, 'uploads');
   if (!$old && !$fl) throw new RuntimeException('Choose a ZIP or HTML file to upload.');
   $st = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft'; $cid = (int)$_POST['category_id'] ?: null; $now = time();
   $v = [$t, $slug, trim((string)$_POST['short_desc']), trim((string)$_POST['full_desc']), trim((string)$_POST['features']), $cid, trim((string)$_POST['version']) ?: '1.0.0', trim((string)$_POST['changelog']), trim((string)$_POST['instructions']), trim((string)$_POST['requirements']), $st, empty($_POST['featured']) ? 0 : 1];
   $pdo->beginTransaction();
   if ($old) { $pdo->prepare('UPDATE projects SET title=?,slug=?,short_desc=?,full_desc=?,features=?,category_id=?,version=?,changelog=?,instructions=?,requirements=?,status=?,featured=?,updated_at=? WHERE id=?')->execute([...$v, $now, $id]); }
   else { $pdo->prepare('INSERT INTO projects(title,slug,short_desc,full_desc,features,category_id,version,changelog,instructions,requirements,status,featured,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([...$v, $now, $now]); $id = (int)$pdo->lastInsertId(); }
   if ($fl) $pdo->prepare('UPDATE projects SET file_path=?,orig_name=?,file_size=?,file_type=? WHERE id=?')->execute([$fl[0], $fl[1], $fl[2], $fl[3] === 'zip' ? 'zip' : 'html', $id]);
   $ch = (string)($_POST['file_type'] ?? ''); if (in_array($ch, ['zip','html'], true)) $pdo->prepare('UPDATE projects SET file_type=? WHERE id=?')->execute([$ch, $id]);
   if ($im) $pdo->prepare('UPDATE projects SET thumb=? WHERE id=?')->execute([$im[0], $id]);
   $pdo->commit(); if ($old && $fl) rm('downloads', $old['file_path']); if ($old && $im) rm('uploads', $old['thumb']);
   flash('Project saved.'); go('admin.php?s=projects');
  }
  if ($act === 'toggle' || $act === 'delete_project') {
   $id = (int)$_POST['id']; $o = $pdo->prepare('SELECT * FROM projects WHERE id=?'); $o->execute([$id]); $p = $o->fetch();
   if ($p && $act === 'toggle') { $pdo->prepare("UPDATE projects SET status=CASE status WHEN 'published' THEN 'draft' ELSE 'published' END, updated_at=? WHERE id=?")->execute([time(), $id]); flash('Status changed.'); }
   if ($p && $act === 'delete_project') { $pdo->prepare('DELETE FROM projects WHERE id=?')->execute([$id]); rm('downloads', $p['file_path']); rm('uploads', $p['thumb']); flash('Project deleted.'); }
   go('admin.php?s=projects');
  }
  if ($act === 'save_cat') { $id = (int)$_POST['id']; $n = trim((string)$_POST['name']); if ($n === '') throw new RuntimeException('Category name is required.'); $sl = slugify(trim((string)$_POST['slug']) ?: $n); $ds = trim((string)$_POST['description']);
   try { $id ? $pdo->prepare('UPDATE categories SET name=?,slug=?,description=? WHERE id=?')->execute([$n, $sl, $ds, $id]) : $pdo->prepare('INSERT INTO categories(name,slug,description) VALUES(?,?,?)')->execute([$n, $sl, $ds]); } catch (PDOException) { throw new RuntimeException('That category slug already exists.'); }
   flash('Category saved.'); go('admin.php?s=cats'); }
  if ($act === 'delete_cat') { $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([(int)$_POST['id']]); flash('Category deleted. Its projects are now uncategorized.'); go('admin.php?s=cats'); }
  if ($act === 'settings') { $up = $pdo->prepare('UPDATE settings SET v=? WHERE k=?');
   foreach (['site_name','site_desc','footer','contact_email','support_url'] as $k) $up->execute([mb_substr(trim((string)$_POST[$k]), 0, 300), $k]);
   $up->execute([(string)min(60, max(3, (int)$_POST['per_page'])), 'per_page']); $up->execute([(string)min(100, max(1, (int)$_POST['max_upload_mb'])), 'max_upload_mb']); $up->execute([((string)($_POST['ad_enabled'] ?? '0') === '1') ? '1' : '0', 'ad_enabled']); $up->execute([(string)min(120, max(5, (int)($_POST['ad_seconds'] ?? 15))), 'ad_seconds']);
   $lk = trim((string)($_POST['ad_link'] ?? '')); if ($lk !== '' && !preg_match('~^https?://~i', $lk)) $lk = ''; $up->execute([mb_substr($lk, 0, 1000), 'ad_link']); $up->execute([mb_substr(trim((string)($_POST['ad_banner'] ?? '')), 0, 5000), 'ad_banner']);
   foreach (['ad_site_1','ad_site_2'] as $k) $up->execute([mb_substr(trim((string)($_POST[$k] ?? '')), 0, 5000), $k]);
   flash('Settings saved.'); go('admin.php?s=settings'); }
 } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $err = $e instanceof RuntimeException ? $e->getMessage() : 'Something went wrong. Please try again.'; if (!$e instanceof RuntimeException) error_log($e->getMessage()); }
}
$fl = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); $s = (string)($_GET['s'] ?? 'dash');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Admin | <?=h($S['site_name'])?></title><link rel="icon" href="assets/images/logo.svg" type="image/svg+xml"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"><link rel="stylesheet" href="<?=h(asset('assets/css/style.css'))?>"><link rel="stylesheet" href="<?=h(asset('assets/css/admin.css'))?>"></head><body class="adm">
<?php if (!$in): ?><main class="auth"><form method="post" class="panel"><img src="assets/images/logo.svg" alt="<?=h($S['site_name'])?>" height="32"><?=csrf()?>
<?php if ($fl) echo '<p class="note '.$fl[0].'">'.h($fl[1]).'</p>'; if (!empty($err)) echo '<p class="note err" role="alert">'.h($err).'</p>'; ?>
<h1>Sign in</h1><input type="hidden" name="act" value="login"><label>Username<input name="username" required autocomplete="username"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button class="btn wide">Sign in</button></form></main>
<?php else:
 $cats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll(); $nav = ['dash'=>['Dashboard','grid'],'projects'=>['Projects','box'],'cats'=>['Categories','tag'],'settings'=>['Settings','cog']]; ?>
<div class="shell"><aside class="sb" id="sb"><img src="assets/images/logo.svg" alt="<?=h($S['site_name'])?>" height="30"><nav><?php foreach ($nav as $k=>$n) echo '<a href="?s='.$k.'"'.($s === $k || ($s === 'edit' && $k === 'projects') ? ' aria-current="page"' : '').'>'.icon($n[1]).$n[0].'</a>'; ?><a href="./" target="_blank" rel="noopener"><?=icon('search')?>View site</a></nav></aside>
<div class="main"><div class="top"><button class="mt" id="sbt" aria-label="Open menu" aria-expanded="false" aria-controls="sb"><?=icon('menu')?></button><form method="post"><?=csrf()?><input type="hidden" name="act" value="logout"><button class="btn ghost"><?=icon('out')?>Sign out</button></form></div><div class="body">
<?php if ($fl) echo '<p class="note '.$fl[0].'" role="status">'.h($fl[1]).'</p>'; if (!empty($err)) echo '<p class="note err" role="alert">'.h($err).'</p>';
if ($s === 'dash'): $n = fn(string $q) => (int)$pdo->query($q)->fetchColumn(); ?>
<h1>Dashboard</h1><div class="stats"><?php foreach (['Total projects'=>'SELECT COUNT(*) FROM projects','Published'=>"SELECT COUNT(*) FROM projects WHERE status='published'",'Drafts'=>"SELECT COUNT(*) FROM projects WHERE status='draft'",'Total downloads'=>'SELECT COALESCE(SUM(downloads),0) FROM projects','Categories'=>'SELECT COUNT(*) FROM categories'] as $l=>$q) echo '<div class="stat"><span>'.$l.'</span><strong>'.$n($q).'</strong></div>'; ?></div>
<h2>Recent uploads</h2><?php $r = $pdo->query('SELECT title,status,created_at,updated_at FROM projects ORDER BY created_at DESC LIMIT 5')->fetchAll(); if (!$r) echo '<p class="empty">No projects yet. <a href="?s=edit">Upload your first project</a>.</p>'; else { echo '<div class="tw"><table><tr><th>Title</th><th>Status</th><th>Uploaded</th><th>Updated</th></tr>'; foreach ($r as $x) echo '<tr><td>'.h($x['title']).'</td><td><span class="badge '.h($x['status']).'">'.h($x['status']).'</span></td><td>'.dt($x['created_at']).'</td><td>'.dt($x['updated_at']).'</td></tr>'; echo '</table></div>'; } ?>
<h2>Recent downloads</h2><?php $r = $pdo->query('SELECT p.title,l.at FROM download_logs l JOIN projects p ON p.id=l.project_id ORDER BY l.at DESC LIMIT 8')->fetchAll(); if (!$r) echo '<p class="muted">No downloads recorded yet.</p>'; else { echo '<div class="tw"><table><tr><th>Project</th><th>Time</th></tr>'; foreach ($r as $x) echo '<tr><td>'.h($x['title']).'</td><td>'.date('M j, Y H:i', $x['at']).'</td></tr>'; echo '</table></div>'; }
elseif ($s === 'projects'): $q = trim((string)($_GET['q'] ?? '')); $st = (string)($_GET['st'] ?? ''); $w = ' WHERE 1=1'; $a = []; if ($q !== '') { $w .= ' AND p.title LIKE ?'; $a[] = '%'.addcslashes($q, '%_').'%'; } if (in_array($st, ['draft','published'], true)) { $w .= ' AND p.status=?'; $a[] = $st; }
 $x = $pdo->prepare("SELECT p.*, c.name cat FROM projects p LEFT JOIN categories c ON c.id=p.category_id".$w.' ORDER BY p.updated_at DESC'); $x->execute($a); $r = $x->fetchAll(); ?>
<div class="row"><h1>Projects</h1><a class="btn" href="?s=edit"><?=icon('plus')?>New project</a></div><form class="filters" method="get"><input type="hidden" name="s" value="projects"><label>Search<input name="q" value="<?=h($q)?>"></label><label>Status<select name="st"><option value="">All</option><option value="published" <?=$st === 'published' ? 'selected' : ''?>>Published</option><option value="draft" <?=$st === 'draft' ? 'selected' : ''?>>Draft</option></select></label><button class="btn ghost">Filter</button></form>
<?php if (!$r) echo '<p class="empty">No projects found.</p>'; else { echo '<div class="tw"><table><tr><th>Title</th><th>Category</th><th>Type</th><th>Status</th><th>Downloads</th><th>Actions</th></tr>'; foreach ($r as $p) echo '<tr><td>'.h($p['title']).'</td><td>'.h($p['cat'] ?? '-').'</td><td>'.h(strtoupper($p['file_type'])).'</td><td><span class="badge '.h($p['status']).'">'.h($p['status']).'</span></td><td>'.(int)$p['downloads'].'</td><td class="acts"><a href="?s=edit&id='.$p['id'].'">Edit</a><a href="./?p='.h($p['slug']).'" target="_blank" rel="noopener">Preview</a><form method="post">'.csrf().'<input type="hidden" name="id" value="'.$p['id'].'"><button name="act" value="toggle">'.($p['status'] === 'published' ? 'Unpublish' : 'Publish').'</button><button name="act" value="delete_project" class="danger" data-confirm="Delete this project and its files?">Delete</button></form></td></tr>'; echo '</table></div>'; }
elseif ($s === 'edit'): $id = (int)($_GET['id'] ?? 0); $p = ['title'=>'','slug'=>'','short_desc'=>'','full_desc'=>'','features'=>'','category_id'=>0,'version'=>'1.0.0','changelog'=>'','instructions'=>'','requirements'=>'','status'=>'draft','featured'=>0,'file_type'=>'']; if ($id) { $o = $pdo->prepare('SELECT * FROM projects WHERE id=?'); $o->execute([$id]); $p = $o->fetch() ?: $p; } $p = array_merge($p, array_intersect_key($_POST, $p)); ?>
<h1><?=$id ? 'Edit project' : 'New project'?></h1><form method="post" enctype="multipart/form-data" class="panel form"><?=csrf()?><input type="hidden" name="act" value="save_project"><input type="hidden" name="id" value="<?=$id?>">
<label>Title<input name="title" id="ti" value="<?=h($p['title'])?>" required></label><label>Slug<input name="slug" id="sl" value="<?=h($p['slug'])?>" pattern="[a-z0-9-]*"></label><label>Short description<input name="short_desc" value="<?=h($p['short_desc'])?>" maxlength="200"></label><label>Full description<textarea name="full_desc" rows="5"><?=h($p['full_desc'])?></textarea></label><label>Features (one per line)<textarea name="features" rows="4"><?=h($p['features'])?></textarea></label>
<label>Category<select name="category_id"><option value="0">Uncategorized</option><?php foreach ($cats as $c) echo '<option value="'.$c['id'].'"'.($c['id'] == $p['category_id'] ? ' selected' : '').'>'.h($c['name']).'</option>'; ?></select></label><label>Version<input name="version" value="<?=h($p['version'])?>"></label><label>File type<select name="file_type"><option value="">Auto-detect from uploaded file</option><option value="zip" <?=$p['file_type'] === 'zip' ? 'selected' : ''?>>ZIP</option><option value="html" <?=$p['file_type'] === 'html' ? 'selected' : ''?>>HTML</option></select></label>
<label>Download file (ZIP or HTML, max <?=(int)$S['max_upload_mb']?> MB)<input type="file" name="file" accept=".zip,.html,.htm" <?=$id ? '' : 'required'?>></label><label>Thumbnail (JPG, PNG or WebP, max 5 MB)<input type="file" name="thumb" accept="image/jpeg,image/png,image/webp"></label>
<label>Installation instructions<textarea name="instructions" rows="3"><?=h($p['instructions'])?></textarea></label><label>Requirements<textarea name="requirements" rows="3"><?=h($p['requirements'])?></textarea></label><label>Changelog<textarea name="changelog" rows="3"><?=h($p['changelog'])?></textarea></label>
<label>Status<select name="status"><option value="draft">Draft</option><option value="published" <?=$p['status'] === 'published' ? 'selected' : ''?>>Published</option></select></label><label class="chk"><input type="checkbox" name="featured" <?=$p['featured'] ? 'checked' : ''?>> Featured on the home page</label><div class="row"><button class="btn">Save project</button><a class="btn ghost" href="?s=projects">Cancel</a></div></form>
<?php elseif ($s === 'cats'): $e = null; if (!empty($_GET['id'])) { $o = $pdo->prepare('SELECT * FROM categories WHERE id=?'); $o->execute([(int)$_GET['id']]); $e = $o->fetch(); } ?>
<h1>Categories</h1><form method="post" class="panel form"><?=csrf()?><input type="hidden" name="act" value="save_cat"><input type="hidden" name="id" value="<?=$e['id'] ?? 0?>"><label>Name<input name="name" value="<?=h($e['name'] ?? '')?>" required></label><label>Slug<input name="slug" value="<?=h($e['slug'] ?? '')?>"></label><label>Description<input name="description" value="<?=h($e['description'] ?? '')?>"></label><button class="btn"><?=$e ? 'Save category' : 'Add category'?></button></form>
<?php if (!$cats) echo '<p class="empty">No categories yet.</p>'; else { echo '<div class="tw"><table><tr><th>Name</th><th>Slug</th><th>Actions</th></tr>'; foreach ($cats as $c) echo '<tr><td>'.h($c['name']).'</td><td>'.h($c['slug']).'</td><td class="acts"><a href="?s=cats&id='.$c['id'].'">Edit</a><form method="post">'.csrf().'<input type="hidden" name="id" value="'.$c['id'].'"><button name="act" value="delete_cat" class="danger" data-confirm="Delete this category? Its projects become uncategorized.">Delete</button></form></td></tr>'; echo '</table></div>'; }
elseif ($s === 'settings'): ?><h1>Settings</h1><form method="post" class="panel form"><?=csrf()?><input type="hidden" name="act" value="settings"><?php foreach (['site_name'=>'Site name','site_desc'=>'Site description','footer'=>'Footer text','contact_email'=>'Contact email','support_url'=>'Support link'] as $k=>$l) echo '<label>'.$l.'<input name="'.$k.'" value="'.h($S[$k]).'"></label>'; ?><label>Projects per page (3-60)<input type="number" name="per_page" min="3" max="60" value="<?=h($S['per_page'])?>"></label><label>Max upload size in MB (up to 100; files upload in small parts)<input type="number" name="max_upload_mb" min="1" value="<?=h($S['max_upload_mb'])?>"></label><h2>Download ads</h2><label>Ad page before download<select name="ad_enabled"><option value="1"<?=($S['ad_enabled'] ?? '0') === '1' ? ' selected' : ''?>>Enabled</option><option value="0"<?=($S['ad_enabled'] ?? '0') !== '1' ? ' selected' : ''?>>Disabled</option></select></label><label>Ad time in seconds (5-120, e.g. 15 or 30)<input type="number" name="ad_seconds" min="5" max="120" value="<?=h($S['ad_seconds'] ?? '15')?>"></label><label>Direct link URL (opens in a new tab when a visitor clicks Download)<input type="url" name="ad_link" placeholder="https://..." value="<?=h($S['ad_link'] ?? '')?>"></label><label>Banner ad code (optional, shown on the ad page)<textarea name="ad_banner" rows="4" placeholder="Paste banner HTML or script code here"><?=h($S['ad_banner'] ?? '')?></textarea></label><label>Site banner 1 (top of every page, e.g. 320x50)<textarea name="ad_site_1" rows="4" placeholder="Paste banner code here"><?=h($S['ad_site_1'] ?? '')?></textarea></label><label>Site banner 2 (bottom of every page, e.g. 300x250)<textarea name="ad_site_2" rows="4" placeholder="Paste banner code here"><?=h($S['ad_site_2'] ?? '')?></textarea></label><button class="btn">Save settings</button></form>
<?php endif; ?></div></div></div><?php endif; ?><script src="<?=h(asset('assets/js/admin.js'))?>"></script></body></html>
