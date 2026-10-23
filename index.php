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
function placeholder(string $t): string { $c = ['#262c45','#1f3a3c','#2f2846','#1f3248'][crc32($t) % 4];
 return 'data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 200"><rect width="320" height="200" fill="'.$c.'"/><path d="M120 80l-30 20 30 20M200 80l30 20-30 20M175 70l-30 60" fill="none" stroke="#8ea2ff" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/></svg>'); }
function thumb(array $p): string { return $p['thumb'] && is_file(ROOT.'/uploads/'.$p['thumb']) ? 'uploads/'.rawurlencode($p['thumb']) : placeholder($p['title']); }

$P = fn(string $k, $d = '') => $_GET[$k] ?? $d;
$pdo = db(); $S = cfg();
function adcfg(array $S): array { $l = trim($S['ad_link'] ?? ''); return ['on'=>($S['ad_enabled'] ?? '0') === '1', 'link'=>preg_match('~^https?://~i', $l) ? $l : '', 'sec'=>min(120, max(5, (int)($S['ad_seconds'] ?? 15))), 'banner'=>(string)($S['ad_banner'] ?? '')]; }
function ad_session(): void { if (session_status() !== PHP_SESSION_ACTIVE) { session_set_cookie_params(['httponly'=>true, 'samesite'=>'Lax']); session_start(); } }
$A = adcfg($S);
function adslot(array $S, string $k, bool $show): string { $c = trim((string)($S[$k] ?? '')); return ($show && $c !== '') ? '<div class="adrow"><div class="adslot">'.$c.'</div></div>' : ''; }
// Download (served via PHP; only DB-resolved paths)
if (isset($_GET['dl'])) {
 $st = $pdo->prepare("SELECT * FROM projects WHERE slug=? AND status='published'"); $st->execute([(string)$_GET['dl']]); $p = $st->fetch();
 $f = $p ? realpath(ROOT.'/downloads/'.basename($p['file_path'])) : false;
 if (!$p || !$f || strpos($f, realpath(ROOT.'/downloads').DIRECTORY_SEPARATOR) !== 0) { http_response_code(404); exit('File not available.'); }
 if ($A['on']) { // ad gate: the file is only served after the countdown on the ad page has finished
  ad_session(); $t = (int)($_SESSION['adw'][$p['slug']] ?? 0);
  if (!$t || time() - $t < $A['sec'] - 1) { header('Location: ?go='.rawurlencode($p['slug'])); exit; }
  unset($_SESSION['adw'][$p['slug']]); session_write_close();
 }
 $pdo->prepare('UPDATE projects SET downloads=downloads+1 WHERE id=?')->execute([$p['id']]);
 $pdo->prepare('INSERT INTO download_logs(project_id,at) VALUES(?,?)')->execute([$p['id'], time()]);
 $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $p['orig_name']) ?: 'download.'.$p['file_type'];
 header('Content-Type: '.(strtolower(pathinfo($p['file_path'], PATHINFO_EXTENSION)) === 'zip' ? 'application/zip' : 'text/html; charset=utf-8'));
 header('Content-Disposition: attachment; filename="'.$name.'"'); header('Content-Length: '.filesize($f)); header('X-Content-Type-Options: nosniff');
 readfile($f); exit;
}
$cats = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM projects p WHERE p.category_id=c.id AND p.status="published") n FROM categories c ORDER BY name')->fetchAll();
function card(array $p): string { return '<article class="card"><a href="?p='.h($p['slug']).'" class="th"><img src="'.h(thumb($p)).'" alt="" loading="lazy" width="320" height="200"></a><div class="cb"><div class="meta"><span>'.h($p['cat'] ?? 'Uncategorized').'</span><span class="ft">'.h(strtoupper($p['file_type'])).'</span></div><h3><a href="?p='.h($p['slug']).'">'.h($p['title']).'</a></h3><p>'.h($p['short_desc']).'</p><div class="meta sm"><span>v'.h($p['version']).'</span><span>Updated '.dt($p['updated_at']).'</span><span>'.icon('dl').(int)$p['downloads'].'</span></div><a class="btn ghost" href="?p='.h($p['slug']).'">View details</a></div></article>'; }
const SEL = 'SELECT p.*, c.name cat FROM projects p LEFT JOIN categories c ON c.id=p.category_id';
function rows(string $w, string $o, int $n, array $a = []): array { $s = db()->prepare(SEL." WHERE p.status='published' $w ORDER BY $o LIMIT $n"); $s->execute($a); return $s->fetchAll(); }
$view = 'home'; $q = trim((string)$P('q')); $cat = (string)$P('cat'); $ty = (string)$P('type'); $sort = (string)$P('sort', 'new');
if (isset($_GET['go'])) $view = 'wait'; elseif (isset($_GET['p'])) $view = 'detail'; elseif (isset($_GET['page'])) $view = 'page'; elseif ($q !== '' || $cat !== '' || $ty !== '' || isset($_GET['browse'])) $view = 'browse';
$proj = null;
if ($view === 'detail' || $view === 'wait') { $s = $pdo->prepare(SEL." WHERE p.slug=? AND p.status='published'"); $s->execute([(string)($view === 'wait' ? $_GET['go'] : $_GET['p'])]); $proj = $s->fetch(); if (!$proj) { http_response_code(404); $view = 'nf'; } }
$left = 0;
if ($view === 'wait') {
 if (!$A['on']) { header('Location: ?dl='.rawurlencode($proj['slug'])); exit; }
 ad_session(); $t = (int)($_SESSION['adw'][$proj['slug']] ?? 0);
 if (!$t || time() - $t > 900) { $t = time(); $_SESSION['adw'][$proj['slug']] = $t; }
 session_write_close(); $left = max(0, $A['sec'] - (time() - $t));
 header('Cache-Control: no-store');
}
$title = $proj['title'] ?? $S['site_name'];
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="dark"><meta name="theme-color" content="#1e222d"><title><?=h($title)?> | <?=h($S['site_name'])?></title><meta name="description" content="<?=h($proj['short_desc'] ?? $S['site_desc'])?>"><link rel="icon" href="assets/images/logo.svg" type="image/svg+xml"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"><link rel="stylesheet" href="<?=h(asset('assets/css/style.css'))?>"></head><body>
<header class="hd"><div class="wrap nav"><a href="./" class="logo" aria-label="<?=h($S['site_name'])?>"><?=str_replace('<svg ','<svg class="logo-svg" ',(string)@file_get_contents(ROOT.'/assets/images/logo.svg'))?></a><button class="mt" aria-label="Toggle menu" aria-expanded="false" aria-controls="mn"><?=icon('menu')?></button>
<nav id="mn"><a href="./">Home</a><a href="?browse=1">Browse</a><details class="dd"><summary>Categories</summary><div><?php foreach ($cats as $c): ?><a href="?cat=<?=h($c['slug'])?>"><?=h($c['name'])?> (<?=$c['n']?>)</a><?php endforeach; if (!$cats) echo '<span>No categories yet</span>'; ?></div></details><a href="?browse=1&sort=new">Latest</a><a href="?browse=1&sort=pop">Popular</a>
<form action="./" class="sf" role="search"><input type="search" name="q" value="<?=h($q)?>" placeholder="Search projects" aria-label="Search projects"><button aria-label="Search"><?=icon('search')?></button></form><a class="btn" href="?browse=1">Browse projects</a></nav></div></header><main>
<?=adslot($S, 'ad_site_1', !in_array($view, ['wait','nf'], true))?>
<?php if ($view === 'home'): $feat = rows('AND p.featured=1', 'p.updated_at DESC', 3); $new = rows('', 'p.created_at DESC', 6); $pop = rows('', 'p.downloads DESC', 3); $upd = rows('', 'p.updated_at DESC', 3); ?>
<section class="hero"><div class="wrap"><h1>Ready-to-use web projects, free to download</h1><p><?=h($S['site_desc'])?></p><form action="./" class="big" role="search"><input type="search" name="q" placeholder="Search by project name or description" aria-label="Search projects"><button class="btn"><?=icon('search')?>Search</button></form><a class="btn ghost" href="?browse=1">Browse all projects</a></div></section>
<?php foreach (['Featured projects'=>$feat,'Latest projects'=>$new,'Popular downloads'=>$pop,'Recently updated'=>$upd] as $t=>$l): if ($l): ?><section class="wrap sec"><h2><?=$t?></h2><div class="grid"><?php foreach ($l as $p) echo card($p); ?></div></section><?php endif; endforeach;
if (!$new): ?><section class="wrap sec"><div class="empty"><h2>No projects published yet</h2><p>Check back soon, new downloads will appear here.</p></div></section><?php endif; ?>
<?php elseif ($view === 'browse'):
 $w = ' WHERE p.status="published"'; $a = [];
 if ($q !== '') { $w .= ' AND (p.title LIKE ? OR p.short_desc LIKE ? OR p.full_desc LIKE ?)'; $l = '%'.addcslashes($q, '%_').'%'; array_push($a, $l, $l, $l); }
 if ($cat !== '') { $w .= ' AND c.slug=?'; $a[] = $cat; }
 if (in_array($ty, ['zip','html'], true)) { $w .= ' AND p.file_type=?'; $a[] = $ty; }
 $ord = ['new'=>'p.created_at DESC','old'=>'p.created_at ASC','pop'=>'p.downloads DESC','upd'=>'p.updated_at DESC'][$sort] ?? 'p.created_at DESC';
 $per = max(1, (int)$S['per_page']); $pg = max(1, (int)$P('pg', 1));
 $cs = $pdo->prepare('SELECT COUNT(*) FROM projects p LEFT JOIN categories c ON c.id=p.category_id'.$w); $cs->execute($a); $tot = (int)$cs->fetchColumn(); $pages = max(1, (int)ceil($tot / $per)); $pg = min($pg, $pages);
 $s = $pdo->prepare(SEL.$w." ORDER BY $ord LIMIT $per OFFSET ".(($pg-1)*$per)); $s->execute($a); $list = $s->fetchAll();
 $qs = fn(int $n) => '?'.http_build_query(array_filter(['q'=>$q,'cat'=>$cat,'type'=>$ty,'sort'=>$sort,'browse'=>1,'pg'=>$n])); ?>
<section class="wrap sec"><h1 class="h2">Browse projects</h1><form class="filters" action="./"><input type="hidden" name="browse" value="1"><label>Search<input type="search" name="q" value="<?=h($q)?>"></label><label>Category<select name="cat"><option value="">All</option><?php foreach ($cats as $c): ?><option value="<?=h($c['slug'])?>" <?=$cat === $c['slug'] ? 'selected' : ''?>><?=h($c['name'])?></option><?php endforeach; ?></select></label><label>File type<select name="type"><option value="">All</option><option value="zip" <?=$ty === 'zip' ? 'selected' : ''?>>ZIP</option><option value="html" <?=$ty === 'html' ? 'selected' : ''?>>HTML</option></select></label><label>Sort<select name="sort"><?php foreach (['new'=>'Newest','old'=>'Oldest','pop'=>'Most downloaded','upd'=>'Recently updated'] as $k=>$v) echo '<option value="'.$k.'"'.($sort === $k ? ' selected' : '').'>'.$v.'</option>'; ?></select></label><button class="btn">Apply</button><a href="?browse=1" class="btn ghost">Clear</a></form>
<p class="muted"><?=$tot?> project<?=$tot === 1 ? '' : 's'?> found</p>
<?php if ($list): ?><div class="grid"><?php foreach ($list as $p) echo card($p); ?></div><nav class="pager" aria-label="Pagination"><?php if ($pg > 1) echo '<a href="'.h($qs($pg-1)).'">Previous</a>'; ?><span>Page <?=$pg?> of <?=$pages?></span><?php if ($pg < $pages) echo '<a href="'.h($qs($pg+1)).'">Next</a>'; ?></nav>
<?php else: ?><div class="empty"><h2>No projects match your filters</h2><p>Try a different keyword or clear the filters to see everything.</p><a class="btn" href="?browse=1">Clear filters</a></div><?php endif; ?></section>
<?php elseif ($view === 'detail'): $rel = rows('AND p.category_id IS ? AND p.id!=?', 'p.updated_at DESC', 3, [$proj['category_id'], $proj['id']]); ?>
<section class="wrap sec detail"><div><h1><?=h($proj['title'])?></h1><p class="lead"><?=h($proj['short_desc'])?></p><img class="hero-img" src="<?=h(thumb($proj))?>" alt="Preview of <?=h($proj['title'])?>"><h2>About this project</h2><p class="pre"><?=h($proj['full_desc'])?></p>
<?php if (trim($proj['features'])): ?><h2>Features</h2><ul class="feat"><?php foreach (preg_split('/\R/', trim($proj['features'])) as $f) if (trim($f) !== '') echo '<li>'.h(trim($f)).'</li>'; ?></ul><?php endif;
foreach (['instructions'=>'Installation and usage','requirements'=>'Requirements','changelog'=>'Changelog'] as $k=>$t) if (trim($proj[$k])) echo '<h2>'.$t.'</h2><p class="pre">'.h($proj[$k]).'</p>'; ?></div>
<aside class="side"><a class="btn wide dl-btn" href="?go=<?=h($proj['slug'])?>"<?=$A['on'] && $A['link'] ? ' data-ad="'.h($A['link']).'"' : ''?>><?=icon('dl')?>Download</a><dl><dt>Category</dt><dd><?=h($proj['cat'] ?? 'Uncategorized')?></dd><dt>File type</dt><dd><?=h(strtoupper($proj['file_type']))?></dd><dt>Version</dt><dd><?=h($proj['version'])?></dd><dt>File size</dt><dd><?=fsize((int)$proj['file_size'])?></dd><dt>Uploaded</dt><dd><?=dt($proj['created_at'])?></dd><dt>Updated</dt><dd><?=dt($proj['updated_at'])?></dd><dt>Downloads</dt><dd><?=(int)$proj['downloads']?></dd></dl></aside></section>
<?php if ($rel): ?><section class="wrap sec"><h2>Related projects</h2><div class="grid"><?php foreach ($rel as $p) echo card($p); ?></div></section><?php endif; ?>
<?php elseif ($view === 'wait'): ?>
<section class="wrap sec wait"><div class="wbox"><span class="adtag">Sponsored</span><h1>Your download is being prepared</h1>
<p class="muted"><?=h($proj['title'])?> &middot; <?=h(strtoupper($proj['file_type']))?> &middot; <?=fsize((int)$proj['file_size'])?></p>
<div class="wbar" aria-hidden="true"><i id="cdbar"></i></div>
<p id="cdmsg" class="wmsg">Download starts in <b id="cdn"><?=$left?></b> seconds</p>
<a id="cdbtn" class="btn wide dis" href="?dl=<?=h($proj['slug'])?>" aria-disabled="true"><?=icon('dl')?>Download</a>
<?php if ($A['link']): ?><a class="btn ghost wide" href="<?=h($A['link'])?>" target="_blank" rel="noopener sponsored nofollow">Visit sponsor</a><?php endif; ?>
<?php if (trim($A['banner']) !== ''): ?><div class="adbox"><?=$A['banner']?></div><?php endif; ?>
<noscript><p class="muted">Please enable JavaScript to see the countdown.</p></noscript>
<p class="muted"><a href="?p=<?=h($proj['slug'])?>">&larr; Back to project</a></p>
<span id="cd" data-left="<?=$left?>" data-total="<?=$A['sec']?>" hidden></span></div></section>
<?php elseif ($view === 'page'): $pg = (string)$_GET['page']; $T = [
 'about'=>['About', 'This site is a curated library of web projects that the administrator has uploaded for download. Each listing describes what the project contains, how to use it and which version you are getting.'],
 'faq'=>['Frequently asked questions', "How do I download a project?\nOpen a project and use the Download button. Files are served directly from this site.\n\nWhat are ZIP and HTML files?\nZIP files contain a full project folder. HTML files are single-file projects you can open in a browser.\n\nAre downloads free?\nDownloads are available to everyone unless a project page states otherwise."],
 'instructions'=>['Download instructions', "1. Find a project using search, categories or filters.\n2. Open its page and read the requirements and instructions.\n3. Select Download and save the file.\n4. For ZIP files, extract the archive before use. Scan files with your own security tools as you would with any download."],
 'contact'=>['Contact', ($S['contact_email'] ? 'Email: '.$S['contact_email'] : 'No contact email has been published yet.').($S['support_url'] ? "\nSupport: ".$S['support_url'] : '')],
 'privacy'=>['Privacy policy', 'This site records the number of downloads per project and the time of each download. It does not record personal data in those logs. Administrators sign in using a session cookie that is required for the admin area only. A temporary session cookie is also used on the download page to time the countdown. This site may show advertising from a third-party ad network, which can set its own cookies or collect usage data under its own policy.'],
 'terms'=>['Terms of use', 'Projects are provided as is, without warranty. Review each file before using it in production. Respect any license that comes with a project.']];
 $t = $T[$pg] ?? null; if (!$t) http_response_code(404); ?><section class="wrap sec narrow"><h1><?=h($t[0] ?? 'Page not found')?></h1><p class="pre"><?=h($t[1] ?? 'That page does not exist.')?></p></section>
<?php else: ?><section class="wrap sec"><div class="empty"><h1>Project not found</h1><p>It may have been unpublished or moved.</p><a class="btn" href="?browse=1">Browse projects</a></div></section><?php endif; ?></main>
<?=adslot($S, 'ad_site_2', $view !== 'wait')?>
<footer class="ft-b"><div class="wrap fg"><div class="fb"><strong><?=h($S['site_name'])?></strong><p><?=h($S['footer'])?></p></div><nav aria-label="Explore"><h4>Explore</h4><a href="?browse=1">Browse projects</a><a href="?browse=1&sort=new">Latest projects</a><a href="?browse=1&sort=pop">Popular downloads</a></nav><nav aria-label="Help"><h4>Help</h4><a href="?page=faq">FAQ</a><a href="?page=instructions">Download instructions</a><a href="?page=contact">Contact</a><?php if ($S['support_url']): ?><a href="<?=h($S['support_url'])?>" rel="noopener" target="_blank">Support</a><?php endif; ?></nav><nav aria-label="Legal"><h4>About</h4><a href="?page=about">About this site</a><a href="?page=privacy">Privacy policy</a><a href="?page=terms">Terms of use</a></nav></div>
<div class="wrap fnotes"><div><h4>Scan before you open</h4><p>Check every downloaded file with your own antivirus or security tools before opening or deploying it.</p></div><div><h4>Check the license</h4><p>Read any license that comes with a project before using it commercially or redistributing it.</p></div><div><h4>Back up first</h4><p>Keep a copy of your existing site or data before you install or replace anything.</p></div></div>
<div class="wrap fbar"><span>&copy; <?=date('Y')?> <?=h($S['site_name'])?>. All rights reserved.</span><span>Projects are provided as is, without warranty.<?php if ($S['contact_email']): ?> Questions? <a href="mailto:<?=h($S['contact_email'])?>"><?=h($S['contact_email'])?></a><?php endif; ?></span></div></footer><script src="<?=h(asset('assets/js/script.js'))?>"></script><script src="<?=h(asset('assets/js/ad.js'))?>"></script></body></html>
