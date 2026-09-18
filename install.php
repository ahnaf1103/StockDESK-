<?php
session_start();
$configFile = __DIR__ . '/config.php';
$message = '';
$ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['db_host'] ?? '');
    $name = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = (string)($_POST['db_pass'] ?? '');
    try {
        $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $sql = file_get_contents(__DIR__ . '/setup.sql');
        foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql))) as $statement) $pdo->exec($statement);
        file_put_contents($configFile, '<?php return ' . var_export(['db_host'=>$host,'db_name'=>$name,'db_user'=>$user,'db_pass'=>$pass,'db_charset'=>'utf8mb4','app_name'=>'StockDesk'], true) . ';');
        $message = 'Installation complete. Delete install.php and setup.sql now.';
        $ok = true;
    } catch (Throwable $e) { $message = 'Connection or schema error: ' . $e->getMessage(); }
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>StockDesk Installer</title><style>body{font:15px system-ui;background:#f5f4f0;color:#18181a;display:grid;place-items:center;min-height:100vh;margin:0}.box{width:min(520px,calc(100% - 30px));background:#fff;padding:28px;border-radius:18px;box-shadow:0 10px 35px #0002}h1{margin-top:0;color:#ba7517}label{display:block;font-size:12px;font-weight:700;margin-top:13px}input{width:100%;padding:10px;border:1px solid #c8c5ba;border-radius:9px;box-sizing:border-box;margin-top:5px}button{margin-top:18px;padding:10px 16px;border:0;border-radius:9px;background:#ba7517;color:#fff;font-weight:700}.msg{padding:10px;background:<?php echo $ok?'#dffaf0':'#fde8e8';?>;border-radius:9px;margin-bottom:15px}</style></head><body><main class="box"><h1>StockDesk Installer</h1><p>Enter the MySQL details from your hosting control panel. Nothing is prefilled.</p><?php if($message): ?><div class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?><form method="post"><label>Database host<input name="db_host" required value="<?php echo htmlspecialchars($_POST['db_host'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><label>Database name<input name="db_name" required value="<?php echo htmlspecialchars($_POST['db_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><label>Database user<input name="db_user" required value="<?php echo htmlspecialchars($_POST['db_user'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><label>Database password<input name="db_pass" type="password" required></label><button type="submit">Install StockDesk</button></form></main></body></html>
