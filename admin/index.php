<?php
function loadAdminPassword() {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, 'ADMIN_PASS=') === 0) {
                $pass = trim(substr($line, strlen('ADMIN_PASS=')));
                if (!empty($pass)) {
                    return $pass;
                }
            }
        }
    }
    
    // 2. Если .env нет — пробуем переменную окружения
    $envPass = getenv('ADMIN_PASS');
    if (!empty($envPass)) {
        return $envPass;
    }
    
    // 3. Если ничего нет — блокируем доступ
    die('Ошибка: пароль не найден. Создайте файл .env в корне сайта с ADMIN_PASS=ваш_пароль');
}

define('ADMIN_PASSWORD', loadAdminPassword());


define('SESSION_LIFETIME', 28800); // 8 часов
define('PRODUCTS_FILE',  __DIR__ . '/../data/products.json');
define('UPLOAD_DIR',     __DIR__ . '/../img/products-img/');
define('UPLOAD_URL',     '../img/products-img/');
define('SESSION_NAME',   'mp_admin');


session_name(SESSION_NAME);
session_start();

if (!empty($_SESSION['auth'])) {
    $elapsed = time() - ($_SESSION['last_activity'] ?? 0);
    if ($elapsed > SESSION_LIFETIME) {
        session_destroy();
        session_start();
        $_SESSION = [];
    } else {
        $_SESSION['last_activity'] = time();
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if ($_SESSION['login_attempts'] >= 5) {
    sleep(3);
}

$error = '';
$authed = !empty($_SESSION['auth']);


if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['auth'] = true;
        $_SESSION['last_activity'] = time();
        $_SESSION['login_attempts'] = 0;
        header('Location: index.php');
        exit;
    } else {
        $_SESSION['login_attempts']++;
        $error = 'Неверный пароль';
    }
}


function loadProducts(): array {
    if (!file_exists(PRODUCTS_FILE)) return [];
    $json = file_get_contents(PRODUCTS_FILE);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function saveProducts(array $products): bool {
    // Бэкап
    if (file_exists(PRODUCTS_FILE)) {
        $backupName = PRODUCTS_FILE . '.bak.' . date('Y-m-d_H-i-s');
        copy(PRODUCTS_FILE, $backupName);
        $backups = glob(PRODUCTS_FILE . '.bak.*');
        if (count($backups) > 10) {
            usort($backups, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            $toDelete = array_slice($backups, 0, count($backups) - 10);
            foreach ($toDelete as $f) {
                @unlink($f);
            }
        }
    }
    
    $dir = dirname(PRODUCTS_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    
    $fp = fopen(PRODUCTS_FILE, 'r+');
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return false;
    }
    
    ftruncate($fp, 0);
    fwrite($fp, json_encode($products, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    flock($fp, LOCK_UN);
    fclose($fp);
    
    return true;
}

function generateId(): string {
    return 'p' . time() . rand(100, 999);
}

function parseOptionRows(string $prefix, array $post): array {
    $result = [];
    $labels = $post[$prefix . '_label'] ?? [];
    $prices = $post[$prefix . '_price'] ?? [];
    foreach ($labels as $i => $label) {
        $label = trim($label);
        if ($label === '') continue;
        $result[] = ['label' => $label, 'price' => (int)($prices[$i] ?? 0)];
    }
    return $result;
}


function uploadPhoto(array $file): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime = match($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream'
        };
    }
    
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMimes)) return false;
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'])) return false;
    if ($file['size'] > 5 * 1024 * 1024) return false;
    
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    $name = uniqid('photo_') . '.' . $ext;
    $dest = UPLOAD_DIR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;
    return UPLOAD_URL . $name;
}


if ($authed) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            http_response_code(403);
            die('CSRF атака');
        }
    }
    
    // --- Удалить товар ---
    if ($action === 'delete' && isset($_POST['id'])) {
        $products = loadProducts();
        foreach ($products as $p) {
            if ($p['id'] === $_POST['id'] && !empty($p['photoURL'])) {
                $oldPhoto = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($p['photoURL'], './');
                if (file_exists($oldPhoto)) @unlink($oldPhoto);
                break;
            }
        }
        $products = array_values(array_filter($products, fn($p) => $p['id'] !== $_POST['id']));
        saveProducts($products);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
    
    // --- Переключить hidden ---
    if ($action === 'toggle_hidden' && isset($_POST['id'])) {
        $products = loadProducts();
        foreach ($products as &$p) {
            if ($p['id'] === $_POST['id']) {
                $p['hidden'] = empty($p['hidden']);
                break;
            }
        }
        saveProducts($products);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
    
    // --- Сохранить порядок ---
    if ($action === 'reorder' && isset($_POST['ids'])) {
        $ids = json_decode($_POST['ids'], true);
        $products = loadProducts();
        $map = [];
        foreach ($products as $p) $map[$p['id']] = $p;
        $sorted = [];
        foreach ($ids as $i => $id) {
            if (isset($map[$id])) {
                $map[$id]['sortOrder'] = $i + 1;
                $sorted[] = $map[$id];
                unset($map[$id]);
            }
        }
        foreach ($map as $p) $sorted[] = $p;
        saveProducts($sorted);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
    
    // --- Сохранить товар ---
    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $products = loadProducts();
        $id = trim($_POST['id'] ?? '');
        $isNew = ($id === '');
        if ($isNew) $id = generateId();
        
        // Фото
        $photoURL = $_POST['photoURL'] ?? '';
        if (!empty($_FILES['photo']['name'])) {
            $uploaded = uploadPhoto($_FILES['photo']);
            if ($uploaded) {
                if (!$isNew) {
                    foreach ($products as $oldP) {
                        if ($oldP['id'] === $id && !empty($oldP['photoURL'])) {
                            $oldPhoto = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($oldP['photoURL'], './');
                            if (file_exists($oldPhoto)) @unlink($oldPhoto);
                            break;
                        }
                    }
                }
                $photoURL = $uploaded;
            }
        }
        
        // Размеры
        $sizesRaw = $_POST['sizes'] ?? '';
        $sizes = array_values(array_filter(
            array_map('trim', explode("\n", $sizesRaw))
        ));
        
        // Материалы (как строки)
        $materialsRaw = $_POST['materials_raw'] ?? '';
        $materials = array_values(array_filter(
            array_map('trim', explode("\n", $materialsRaw))
        ));
        
        // Остальные опции
        $colors = parseOptionRows('colors', $_POST);
        $engravings = parseOptionRows('engravings', $_POST);
        $finishes = parseOptionRows('finishes', $_POST);
        $services = parseOptionRows('services', $_POST);
        
        $product = [
            'id'          => $id,
            'name'        => trim($_POST['name'] ?? ''),
            'category'    => $_POST['category'] ?? 'vertical',
            'price'       => (int)($_POST['price'] ?? 0),
            'basePrice'   => (int)($_POST['price'] ?? 0),
            'photoURL'    => $photoURL,
            'badge'       => trim($_POST['badge'] ?? '') ?: null,
            'popular'     => isset($_POST['popular']),
            'hidden'      => isset($_POST['hidden']),
            'sortOrder'   => (int)($_POST['sortOrder'] ?? 0),
            'description' => trim($_POST['description'] ?? ''),
            'components'  => [],
            'sizes'       => $sizes,
            'materials'   => $materials,
            'colors'      => $colors,
            'engravings'  => $engravings,
            'finishes'    => $finishes,
            'services'    => $services,
        ];
        
        if ($isNew) {
            $products[] = $product;
        } else {
            $found = false;
            foreach ($products as &$p) {
                if ($p['id'] === $id) { $p = $product; $found = true; break; }
            }
            if (!$found) $products[] = $product;
        }
        
        usort($products, fn($a, $b) => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0));
        saveProducts($products);
        
        header('Location: index.php?saved=1');
        exit;
    }
    
    // --- Получить товар для редактирования ---
    if ($action === 'get' && isset($_GET['id'])) {
        $products = loadProducts();
        foreach ($products as $p) {
            if ($p['id'] === $_GET['id']) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($p, JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        http_response_code(404);
        echo json_encode(['error' => 'not found']);
        exit;
    }
}


$products = $authed ? loadProducts() : [];
$visibleProducts = array_filter($products, fn($p) => ($p['category'] ?? '') !== '_component');
$visibleProducts = array_values($visibleProducts);

$categories = [
    'vertical'   => 'Вертикальные',
    'horizontal' => 'Горизонтальные',
    'carved'     => 'Резные',
    'double'     => 'Двойные',
    'combined'   => 'Комбинированные',
    'cross'      => 'С крестом',
    'complex'    => 'Комплексы',
    'fence'      => 'Ограды',
    'socle'      => 'Цоколи',
];

?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Админ — ГраньВремени.рф</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --dark: #1a1a1a; --gold: #b8944a; --gold-l: #d4ae5e;
    --red: #d32f2f; --green: #2e7d32; --gray: #f5f5f5;
    --border: #ddd; --text: #333; --text-light: #666;
    --radius: 8px;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', sans-serif; background: #f0f0f0; color: var(--text); min-height: 100vh; }

/* ── ЛОГИН ── */
.login-wrap { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
.login-box {
    background: #fff; border-radius: 16px; padding: 40px;
    width: 100%; max-width: 380px; box-shadow: 0 4px 32px rgba(0,0,0,.12);
    text-align: center;
}
.login-box h1 { font-size: 22px; margin-bottom: 6px; color: var(--dark); }
.login-box p { font-size: 13px; color: var(--text-light); margin-bottom: 28px; }
.login-box input {
    width: 100%; padding: 12px 16px; border: 1.5px solid var(--border);
    border-radius: var(--radius); font-size: 15px; margin-bottom: 14px; outline: none;
}
.login-box input:focus { border-color: var(--gold); }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px;
    border: none; border-radius: var(--radius); cursor: pointer; font-size: 13px;
    font-weight: 600; transition: .2s; text-decoration: none; }
.btn-gold  { background: var(--gold); color: #fff; }
.btn-gold:hover  { background: var(--gold-l); }
.btn-dark  { background: var(--dark); color: #fff; }
.btn-dark:hover  { background: #333; }
.btn-red   { background: #fff; color: var(--red); border: 1.5px solid var(--red); }
.btn-red:hover   { background: var(--red); color: #fff; }
.btn-green { background: #fff; color: var(--green); border: 1.5px solid #4caf50; }
.btn-green:hover { background: var(--green); color: #fff; }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn-full { width: 100%; justify-content: center; }
.error-msg { background: #fff3f3; border: 1px solid #f5c6c6; border-radius: 6px;
    color: var(--red); padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }

/* ── ШАПКА ── */
.topbar {
    background: var(--dark); color: #fff; padding: 0 24px;
    display: flex; align-items: center; justify-content: space-between;
    height: 56px; position: sticky; top: 0; z-index: 100;
    box-shadow: 0 2px 8px rgba(0,0,0,.3);
}
.topbar-logo { font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
.topbar-logo span { color: var(--gold); }
.topbar-actions { display: flex; align-items: center; gap: 12px; }

/* ── LAYOUT ── */
.main { max-width: 1300px; margin: 0 auto; padding: 24px 16px; }
.page-title { font-size: 22px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
.notice { background: #e8f5e9; border: 1px solid #a5d6a7; border-radius: 8px;
    color: var(--green); padding: 12px 18px; font-size: 13px; margin-bottom: 20px;
    display: flex; align-items: center; gap: 8px; }

/* ── КАРТОЧКИ СТАТИСТИКИ ── */
.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 28px; }
.stat-card { background: #fff; border-radius: 12px; padding: 18px 20px;
    box-shadow: 0 1px 8px rgba(0,0,0,.06); border-left: 4px solid var(--gold); }
.stat-card .num { font-size: 28px; font-weight: 800; color: var(--dark); }
.stat-card .lbl { font-size: 12px; color: var(--text-light); margin-top: 2px; }

/* ── ТАБЛИЦА ── */
.toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
.toolbar input[type=text] {
    padding: 8px 14px; border: 1.5px solid var(--border); border-radius: var(--radius);
    font-size: 13px; width: 240px; outline: none;
}
.toolbar input:focus { border-color: var(--gold); }
.toolbar select { padding: 8px 12px; border: 1.5px solid var(--border); border-radius: var(--radius); font-size: 13px; outline: none; }

.products-table-wrap { background: #fff; border-radius: 12px; box-shadow: 0 1px 8px rgba(0,0,0,.06); overflow: hidden; }
table { width: 100%; border-collapse: collapse; }
thead th { background: var(--gray); padding: 11px 14px; text-align: left; font-size: 12px;
    font-weight: 700; color: var(--text-light); text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid var(--border); }
tbody tr { border-bottom: 1px solid #f0f0f0; transition: background .15s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #fafafa; }
tbody tr.hidden-row { opacity: .5; }
td { padding: 12px 14px; font-size: 13px; vertical-align: middle; }
.td-photo { width: 60px; }
.td-photo img { width: 52px; height: 52px; object-fit: cover; border-radius: 8px;
    border: 1px solid var(--border); }
.td-photo .no-img { width: 52px; height: 52px; background: var(--gray); border-radius: 8px;
    display: flex; align-items: center; justify-content: center; color: #ccc; font-size: 20px; }
.td-name { font-weight: 600; max-width: 240px; }
.td-name .cat-tag { display: inline-block; background: var(--gray); border-radius: 4px;
    padding: 1px 7px; font-size: 11px; color: var(--text-light); margin-top: 3px; font-weight: 400; }
.td-price { font-weight: 700; white-space: nowrap; }
.td-actions { white-space: nowrap; }
.td-actions .btn { margin-right: 4px; }
.badge-tag { display: inline-block; background: var(--gold); color: #fff; font-size: 10px;
    font-weight: 700; padding: 2px 8px; border-radius: 12px; margin-left: 6px; }
.badge-hidden { background: #999; }
.drag-handle { cursor: grab; color: #ccc; font-size: 16px; padding-right: 8px; }
.drag-handle:hover { color: #999; }
tr.drag-over { border-top: 2px solid var(--gold); }

/* ── МОДАЛКА ── */
.modal-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,.6);
    z-index: 500; align-items: flex-start; justify-content: center;
    padding: 20px; overflow-y: auto;
}
.modal-overlay.open { display: flex; }
.modal {
    background: #fff; border-radius: 16px; width: 100%; max-width: 760px;
    margin: auto; position: relative; animation: slideUp .25s ease;
}
@keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: none; opacity: 1; } }
.modal-head {
    padding: 20px 24px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; background: #fff; z-index: 2; border-radius: 16px 16px 0 0;
}
.modal-head h2 { font-size: 18px; }
.modal-close { background: none; border: none; font-size: 22px; cursor: pointer; color: #999; line-height: 1; }
.modal-close:hover { color: var(--dark); }
.modal-body { padding: 24px; }
.modal-foot {
    padding: 16px 24px; border-top: 1px solid var(--border);
    display: flex; gap: 10px; justify-content: flex-end;
    position: sticky; bottom: 0; background: #fff; border-radius: 0 0 16px 16px;
}

/* ── ФОРМА ── */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-grid .full { grid-column: 1 / -1; }
.fgroup { display: flex; flex-direction: column; gap: 5px; }
.fgroup label { font-size: 12px; font-weight: 700; color: var(--text-light); text-transform: uppercase; letter-spacing: .4px; }
.fgroup input[type=text],
.fgroup input[type=number],
.fgroup select,
.fgroup textarea {
    border: 1.5px solid var(--border); border-radius: var(--radius);
    padding: 9px 12px; font-size: 14px; font-family: inherit; outline: none;
    transition: border-color .2s; width: 100%;
}
.fgroup input:focus, .fgroup select:focus, .fgroup textarea:focus { border-color: var(--gold); }
.fgroup textarea { resize: vertical; min-height: 72px; }
.check-row { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; }
.check-row input { width: 16px; height: 16px; accent-color: var(--gold); cursor: pointer; }

.photo-preview { width: 100%; max-width: 200px; height: 140px; background: var(--gray);
    border-radius: 10px; object-fit: cover; border: 1.5px solid var(--border); display: none; }
.photo-preview.show { display: block; }
.photo-placeholder { width: 100%; max-width: 200px; height: 140px; background: var(--gray);
    border-radius: 10px; border: 2px dashed var(--border);
    display: flex; align-items: center; justify-content: center; color: #bbb;
    font-size: 36px; flex-direction: column; gap: 6px; cursor: pointer; }
.photo-placeholder span { font-size: 12px; }

.section-title { font-size: 13px; font-weight: 700; color: var(--text-light);
    text-transform: uppercase; letter-spacing: .5px; margin: 20px 0 10px; padding-bottom: 6px;
    border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.options-list { display: flex; flex-direction: column; gap: 6px; }
.option-row { display: flex; gap: 8px; align-items: center; }
.option-row input[type=text] { flex: 1; }
.option-row input[type=number] { width: 100px; }
.option-row .btn-remove { background: none; border: none; color: #ccc; cursor: pointer; font-size: 16px; padding: 4px; }
.option-row .btn-remove:hover { color: var(--red); }
.btn-add-option { background: none; border: 1.5px dashed var(--border); color: var(--text-light);
    border-radius: var(--radius); padding: 7px 14px; font-size: 12px; cursor: pointer;
    width: 100%; text-align: center; transition: .2s; }
.btn-add-option:hover { border-color: var(--gold); color: var(--gold); }

.confirm-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5);
    z-index: 600; align-items: center; justify-content: center; padding: 20px; }
.confirm-overlay.open { display: flex; }
.confirm-box { background: #fff; border-radius: 14px; padding: 28px; max-width: 360px;
    width: 100%; text-align: center; }
.confirm-box h3 { font-size: 18px; margin-bottom: 10px; }
.confirm-box p { font-size: 14px; color: var(--text-light); margin-bottom: 22px; }
.confirm-box .actions { display: flex; gap: 10px; justify-content: center; }

@media (max-width: 700px) {
    .form-grid { grid-template-columns: 1fr; }
    table thead { display: none; }
    tbody tr { display: flex; flex-wrap: wrap; padding: 12px; gap: 8px; border-bottom: 1px solid var(--border); }
    td { padding: 0; border: none; }
    .td-photo { order: 0; }
    .td-name { order: 1; flex: 1; }
    .td-price { order: 2; width: 100%; }
    .td-actions { order: 3; width: 100%; }
    .td-cat, .td-sort { display: none; }
    .drag-handle { display: none; }
}
</style>
</head>
<body>

<?php if (!$authed): ?>
<!-- ══════════════ СТРАНИЦА ВХОДА ══════════════ -->
<div class="login-wrap">
  <div class="login-box">
    <h1>🔐 ГраньВремени.рф</h1>
    <p>Панель управления каталогом</p>
    <?php if ($error): ?>
      <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="password" name="password" placeholder="Пароль" autofocus autocomplete="current-password">
      <button type="submit" class="btn btn-gold btn-full"><i class="fas fa-sign-in-alt"></i> Войти</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ══════════════ ПАНЕЛЬ УПРАВЛЕНИЯ ══════════════ -->
<div class="topbar">
  <div class="topbar-logo">
    <i class="fas fa-monument" style="color:var(--gold)"></i>
    ГраньВремени.рф <span>Админ</span>
  </div>
  <div class="topbar-actions">
    <span style="font-size:12px; color:rgba(255,255,255,0.4);">
      <i class="far fa-clock"></i> Сессия: <?= round((SESSION_LIFETIME - (time() - ($_SESSION['last_activity'] ?? 0))) / 3600, 1) ?> ч
    </span>
    <a href="../catalog/" target="_blank" class="btn btn-sm" style="background:rgba(255,255,255,.1);color:#fff;">
      <i class="fas fa-external-link-alt"></i> Сайт
    </a>
    <form method="POST" style="margin:0">
      <button name="logout" class="btn btn-sm" style="background:rgba(255,255,255,.1);color:#fff;">
        <i class="fas fa-sign-out-alt"></i> Выйти
      </button>
    </form>
  </div>
</div>

<div class="main">
  <div class="page-title">
    <i class="fas fa-boxes" style="color:var(--gold)"></i> Каталог товаров
    <button class="btn btn-gold" onclick="openEditor()">
      <i class="fas fa-plus"></i> Добавить товар
    </button>
  </div>

  <?php if (isset($_GET['saved'])): ?>
  <div class="notice"><i class="fas fa-check-circle"></i> Товар успешно сохранён!</div>
  <?php endif; ?>

  <!-- Статистика -->
  <?php
    $total   = count($visibleProducts);
    $visible = count(array_filter($visibleProducts, fn($p) => empty($p['hidden'])));
  ?>
  <div class="stats">
    <div class="stat-card"><div class="num"><?= $total ?></div><div class="lbl">Всего товаров</div></div>
    <div class="stat-card"><div class="num"><?= $visible ?></div><div class="lbl">Видимых</div></div>
    <div class="stat-card"><div class="num"><?= $total - $visible ?></div><div class="lbl">Скрытых</div></div>
    <div class="stat-card"><div class="num"><?= count($categories) ?></div><div class="lbl">Категорий</div></div>
  </div>

  <!-- Инструменты -->
  <div class="toolbar">
    <input type="text" id="searchInput" placeholder="🔍 Поиск по названию..." oninput="filterTable()">
    <select id="catFilter" onchange="filterTable()">
      <option value="">Все категории</option>
      <?php foreach ($categories as $val => $lbl): ?>
        <option value="<?= $val ?>"><?= htmlspecialchars($lbl) ?></option>
      <?php endforeach; ?>
    </select>
    <label style="font-size:13px; display:flex; align-items:center; gap:6px; cursor:pointer;">
      <input type="checkbox" id="showHidden" onchange="filterTable()" style="accent-color:var(--gold);">
      Показать скрытые
    </label>
  </div>

  <!-- Таблица -->
  <div class="products-table-wrap">
    <table id="productsTable">
      <thead>
        <tr>
          <th style="width:32px"></th>
          <th style="width:70px">Фото</th>
          <th>Название</th>
          <th class="td-cat">Категория</th>
          <th>Цена</th>
          <th style="width:60px" class="td-sort">№</th>
          <th style="width:180px">Действия</th>
        </tr>
      </thead>
      <tbody id="tableBody">
        <?php foreach ($visibleProducts as $p):
          $hidden  = !empty($p['hidden']);
          $catName = $categories[$p['category'] ?? ''] ?? $p['category'] ?? '—';
          $photo   = $p['photoURL'] ?? '';
        ?>
        <tr data-id="<?= htmlspecialchars($p['id']) ?>"
            data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>"
            data-cat="<?= htmlspecialchars($p['category'] ?? '') ?>"
            class="<?= $hidden ? 'hidden-row' : '' ?>"
            draggable="true">
          <td><span class="drag-handle"><i class="fas fa-grip-vertical"></i></span></td>
          <td class="td-photo">
            <?php if ($photo): ?>
              <img src="<?= htmlspecialchars('../' . ltrim($photo, './')) ?>" alt="" onerror="this.style.display='none'">
            <?php else: ?>
              <div class="no-img"><i class="fas fa-image"></i></div>
            <?php endif; ?>
          </td>
          <td class="td-name">
            <?= htmlspecialchars($p['name']) ?>
            <?php if (!empty($p['badge'])): ?>
              <span class="badge-tag"><?= htmlspecialchars($p['badge']) ?></span>
            <?php endif; ?>
            <?php if ($hidden): ?>
              <span class="badge-tag badge-hidden">скрыт</span>
            <?php endif; ?>
            <div class="cat-tag"><?= htmlspecialchars($catName) ?></div>
          </td>
          <td class="td-cat"><?= htmlspecialchars($catName) ?></td>
          <td class="td-price"><?= number_format($p['price'] ?? 0, 0, '', ' ') ?> ₽</td>
          <td class="td-sort"><?= (int)($p['sortOrder'] ?? 0) ?></td>
          <td class="td-actions">
            <button class="btn btn-dark btn-sm" onclick="editProduct('<?= htmlspecialchars($p['id']) ?>')">
              <i class="fas fa-pencil-alt"></i> Изменить
            </button>
            <button class="btn btn-sm <?= $hidden ? 'btn-green' : 'btn-red' ?>"
                    onclick="toggleHidden('<?= htmlspecialchars($p['id']) ?>', this)">
              <i class="fas fa-<?= $hidden ? 'eye' : 'eye-slash' ?>"></i>
            </button>
            <button class="btn btn-red btn-sm" onclick="confirmDelete('<?= htmlspecialchars($p['id']) ?>', '<?= htmlspecialchars(addslashes($p['name'])) ?>')">
              <i class="fas fa-trash"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>


<div class="modal-overlay" id="editorModal">
  <div class="modal">
    <div class="modal-head">
      <h2 id="modalTitle">Добавить товар</h2>
      <button class="modal-close" onclick="closeEditor()">×</button>
    </div>
    <div class="modal-body">
      <form id="productForm" method="POST" enctype="multipart/form-data" action="index.php">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="f_id">
        <input type="hidden" name="photoURL" id="f_photoURL">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="form-grid">
          <div class="fgroup full">
            <label>Название товара *</label>
            <input type="text" name="name" id="f_name" required placeholder="Вертикальный «Классик»">
          </div>

          <div class="fgroup">
            <label>Категория *</label>
            <select name="category" id="f_category">
              <?php foreach ($categories as $val => $lbl): ?>
                <option value="<?= $val ?>"><?= htmlspecialchars($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="fgroup">
            <label>Цена (₽) *</label>
            <input type="number" name="price" id="f_price" required min="0" placeholder="18000">
          </div>

          <div class="fgroup">
            <label>Порядок (меньше = выше)</label>
            <input type="number" name="sortOrder" id="f_sortOrder" min="0" placeholder="1">
          </div>

          <div class="fgroup">
            <label>Бейдж</label>
            <input type="text" name="badge" id="f_badge" placeholder="Хит / Премиум / Новинка">
          </div>

          <div class="fgroup full" style="flex-direction:row; gap:24px; align-items:center;">
            <label class="check-row">
              <input type="checkbox" name="popular" id="f_popular"> Популярный
            </label>
            <label class="check-row">
              <input type="checkbox" name="hidden" id="f_hidden"> Скрыть из каталога
            </label>
          </div>

          <div class="fgroup full">
            <label>Описание</label>
            <textarea name="description" id="f_description" placeholder="Краткое описание товара..."></textarea>
          </div>

          <div class="fgroup full">
            <label>Фото товара</label>
            <div style="display:flex; gap:16px; align-items:flex-start; flex-wrap:wrap;">
              <div>
                <img id="photoPreview" class="photo-preview" src="" alt="Превью">
                <div id="photoPlaceholder" class="photo-placeholder" onclick="document.getElementById('f_photo').click()">
                  <i class="fas fa-cloud-upload-alt"></i>
                  <span>Нажмите для загрузки</span>
                </div>
              </div>
              <div style="flex:1; min-width:200px;">
                <input type="file" name="photo" id="f_photo" accept="image/*" style="display:none" onchange="previewPhoto(this)">
                <button type="button" class="btn btn-dark btn-sm" onclick="document.getElementById('f_photo').click()" style="margin-bottom:8px;">
                  <i class="fas fa-upload"></i> Загрузить фото
                </button>
                <p style="font-size:12px; color:#999; margin-top:6px;">JPG, PNG, WEBP до 5 МБ</p>
                <div style="margin-top:10px;">
                  <label style="font-size:12px; color:var(--text-light); font-weight:700; text-transform:uppercase; display:block; margin-bottom:4px;">Или укажите путь</label>
                  <input type="text" id="f_photoURLInput" placeholder="img/products/0001.jpg" style="width:100%; padding:8px 10px; border:1.5px solid var(--border); border-radius:var(--radius); font-size:13px; outline:none;"
                    oninput="document.getElementById('f_photoURL').value=this.value; updatePhotoPreview(this.value)">
                </div>
              </div>
            </div>
          </div>

          <div class="fgroup full">
            <label>Размеры (каждый с новой строки)</label>
            <textarea name="sizes" id="f_sizes" style="min-height:100px; font-family:monospace; font-size:13px;"
              placeholder="80×40×5 см&#10;100×50×5 см&#10;120×60×8 см"></textarea>
          </div>

          <div class="fgroup full">
            <label>Материалы (каждый с новой строки)</label>
            <textarea name="materials_raw" id="f_materials_raw" style="min-height:100px; font-family:monospace; font-size:13px;"
              placeholder="Гранит габбро&#10;Гранит красный&#10;Мрамор белый"></textarea>
            <span style="font-size:11px; color:#999;">Указывайте просто названия, без цен</span>
          </div>

        </div>

        <!-- Цвета -->
        <div class="section-title">
          Цвета (с доплатой)
          <button type="button" class="btn btn-sm btn-dark" onclick="addOption('colors')"><i class="fas fa-plus"></i> Добавить</button>
        </div>
        <div class="options-list" id="opt_colors"></div>

        <!-- Гравировки -->
        <div class="section-title">
          Гравировки (с доплатой)
          <button type="button" class="btn btn-sm btn-dark" onclick="addOption('engravings')"><i class="fas fa-plus"></i> Добавить</button>
        </div>
        <div class="options-list" id="opt_engravings"></div>

        <!-- Обработка -->
        <div class="section-title">
          Обработка поверхности (с доплатой)
          <button type="button" class="btn btn-sm btn-dark" onclick="addOption('finishes')"><i class="fas fa-plus"></i> Добавить</button>
        </div>
        <div class="options-list" id="opt_finishes"></div>

        <!-- Услуги -->
        <div class="section-title">
          Услуги (с доплатой)
          <button type="button" class="btn btn-sm btn-dark" onclick="addOption('services')"><i class="fas fa-plus"></i> Добавить</button>
        </div>
        <div class="options-list" id="opt_services"></div>

      </form>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn" onclick="closeEditor()">Отмена</button>
      <button type="button" class="btn btn-gold" onclick="document.getElementById('productForm').submit()">
        <i class="fas fa-save"></i> Сохранить товар
      </button>
    </div>
  </div>
</div>


<div class="confirm-overlay" id="confirmOverlay">
  <div class="confirm-box">
    <h3>Удалить товар?</h3>
    <p id="confirmText">Это действие нельзя отменить.</p>
    <div class="actions">
      <button class="btn" onclick="closeConfirm()">Отмена</button>
      <button class="btn btn-red" id="confirmBtn">Удалить</button>
    </div>
  </div>
</div>

<script>

function filterTable() {
    var q = document.getElementById('searchInput').value.toLowerCase();
    var cat = document.getElementById('catFilter').value;
    var showH = document.getElementById('showHidden').checked;
    document.querySelectorAll('#tableBody tr').forEach(function(tr) {
        var name = tr.dataset.name || '';
        var trCat = tr.dataset.cat || '';
        var hidden = tr.classList.contains('hidden-row');
        var show = (!q || name.includes(q)) &&
                   (!cat || trCat === cat) &&
                   (!hidden || showH);
        tr.style.display = show ? '' : 'none';
    });
}


var dragSrc = null;
document.querySelectorAll('#tableBody tr').forEach(addDragEvents);
function addDragEvents(tr) {
    tr.addEventListener('dragstart', function() { dragSrc = this; this.style.opacity = '.4'; });
    tr.addEventListener('dragend',   function() { this.style.opacity = ''; dragSrc = null; cleanDragOver(); });
    tr.addEventListener('dragover',  function(e) { e.preventDefault(); cleanDragOver(); this.classList.add('drag-over'); });
    tr.addEventListener('drop',      function(e) {
        e.preventDefault();
        if (dragSrc && dragSrc !== this) {
            var tbody = this.parentNode;
            var rows = Array.from(tbody.rows);
            var si = rows.indexOf(dragSrc);
            var ti = rows.indexOf(this);
            if (si < ti) tbody.insertBefore(dragSrc, this.nextSibling);
            else tbody.insertBefore(dragSrc, this);
            saveOrder();
        }
        cleanDragOver();
    });
}
function cleanDragOver() {
    document.querySelectorAll('#tableBody tr').forEach(function(r) { r.classList.remove('drag-over'); });
}
function saveOrder() {
    var ids = Array.from(document.querySelectorAll('#tableBody tr')).map(function(r) { return r.dataset.id; });
    fetch('index.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=reorder&ids=' + encodeURIComponent(JSON.stringify(ids)) });
}


function toggleHidden(id, btn) {
    fetch('index.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=toggle_hidden&id=' + encodeURIComponent(id) })
    .then(function() {
        var tr = btn.closest('tr');
        var isHidden = tr.classList.toggle('hidden-row');
        btn.className = 'btn btn-sm ' + (isHidden ? 'btn-green' : 'btn-red');
        btn.innerHTML = '<i class="fas fa-' + (isHidden ? 'eye' : 'eye-slash') + '"></i>';
        var badges = tr.querySelectorAll('.badge-hidden');
        badges.forEach(function(b){ b.remove(); });
        if (isHidden) {
            var span = document.createElement('span');
            span.className = 'badge-tag badge-hidden'; span.textContent = 'скрыт';
            tr.querySelector('.td-name').appendChild(span);
        }
    });
}


function confirmDelete(id, name) {
    document.getElementById('confirmText').textContent = 'Удалить «' + name + '»? Это нельзя отменить.';
    document.getElementById('confirmBtn').onclick = function() {
        fetch('index.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=delete&id=' + encodeURIComponent(id) })
        .then(function() {
            var tr = document.querySelector('#tableBody tr[data-id="' + id + '"]');
            if (tr) { tr.style.transition = 'opacity .3s'; tr.style.opacity = '0'; setTimeout(function(){ tr.remove(); }, 300); }
            closeConfirm();
        });
    };
    document.getElementById('confirmOverlay').classList.add('open');
}
function closeConfirm() { document.getElementById('confirmOverlay').classList.remove('open'); }


function openEditor() {
    resetForm();
    document.getElementById('modalTitle').textContent = 'Добавить товар';
    document.getElementById('editorModal').classList.add('open');
}
function closeEditor() {
    document.getElementById('editorModal').classList.remove('open');
}
function resetForm() {
    var form = document.getElementById('productForm');
    form.reset();
    document.getElementById('f_id').value = '';
    document.getElementById('f_photoURL').value = '';
    document.getElementById('f_photoURLInput').value = '';
    document.getElementById('photoPreview').classList.remove('show');
    document.getElementById('photoPlaceholder').style.display = '';
    ['colors','engravings','finishes','services'].forEach(function(k) {
        document.getElementById('opt_' + k).innerHTML = '';
    });
}
function editProduct(id) {
    fetch('index.php?action=get&id=' + encodeURIComponent(id))
    .then(function(r){ return r.json(); })
    .then(function(p) {
        resetForm();
        document.getElementById('modalTitle').textContent = 'Редактировать: ' + p.name;
        document.getElementById('f_id').value = p.id || '';
        document.getElementById('f_name').value = p.name || '';
        document.getElementById('f_category').value = p.category || 'vertical';
        document.getElementById('f_price').value = p.price || '';
        document.getElementById('f_sortOrder').value = p.sortOrder || '';
        document.getElementById('f_badge').value = p.badge || '';
        document.getElementById('f_description').value = p.description || '';
        document.getElementById('f_popular').checked = !!p.popular;
        document.getElementById('f_hidden').checked = !!p.hidden;
        var photoURL = p.photoURL || '';
        document.getElementById('f_photoURL').value = photoURL;
        document.getElementById('f_photoURLInput').value = photoURL;
        updatePhotoPreview(photoURL);
        var sizes = (p.sizes || []).join('\n');
        document.getElementById('f_sizes').value = sizes;
        // Материалы (как строки)
        var materials = (p.materials || []).join('\n');
        document.getElementById('f_materials_raw').value = materials;
        // Остальные опции
        ['colors','engravings','finishes','services'].forEach(function(key) {
            var items = p[key] || [];
            items.forEach(function(item) {
                addOption(key, item.label, item.price);
            });
        });
        document.getElementById('editorModal').classList.add('open');
    });
}
function addOption(key, label, price) {
    label = label || '';
    price = price !== undefined ? price : 0;
    var container = document.getElementById('opt_' + key);
    var row = document.createElement('div');
    row.className = 'option-row';
    row.innerHTML =
        '<input type="text" name="' + key + '_label[]" placeholder="Название" value="' + escHtml(label) + '">' +
        '<input type="number" name="' + key + '_price[]" placeholder="Доплата ₽" value="' + (+price) + '" min="0">' +
        '<button type="button" class="btn-remove" onclick="this.parentNode.remove()"><i class="fas fa-times"></i></button>';
    container.appendChild(row);
}
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function previewPhoto(input) {
    if (!input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('f_photoURL').value = '';
        document.getElementById('f_photoURLInput').value = '';
        showPreview(e.target.result);
    };
    reader.readAsDataURL(input.files[0]);
}
function updatePhotoPreview(url) {
    if (!url) { hidePreview(); return; }
    var src = url.startsWith('http') || url.startsWith('/') || url.startsWith('data:') ? url : '../' + url.replace(/^\.\.\//, '');
    showPreview(src);
}
function showPreview(src) {
    var img = document.getElementById('photoPreview');
    var ph = document.getElementById('photoPlaceholder');
    img.src = src;
    img.classList.add('show');
    ph.style.display = 'none';
}
function hidePreview() {
    document.getElementById('photoPreview').classList.remove('show');
    document.getElementById('photoPlaceholder').style.display = '';
}
document.getElementById('editorModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditor();
});
document.getElementById('confirmOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeConfirm();
});
</script>

<?php endif; ?>
</body>
</html>