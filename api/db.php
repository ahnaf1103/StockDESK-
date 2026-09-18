<?php
class DB {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $config = require __DIR__ . '/../config.php';
        $host = $config['db_host'];
        $db   = $config['db_name'];
        $user = $config['db_user'];
        $pass = $config['db_pass'];
        $charset = $config['db_charset'] ?? 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
             header('Content-Type: application/json');
             echo json_encode(['ok' => false, 'error' => 'Database connection failed. Please check your InfinityFree credentials.']);
             exit;
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new DB();
        }
        return self::$instance->pdo;
    }

    public static function query($sql, $params = []) {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch($sql, $params = []) {
        return self::query($sql, $params)->fetch();
    }

    public static function fetchAll($sql, $params = []) {
        return self::query($sql, $params)->fetchAll();
    }

    public static function lastInsertId() {
        return self::getInstance()->lastInsertId();
    }

    public static function setup() {
        // Tables are created automatically on first load if they don't exist
        error_log("Running DB setup...");
        $queries = [
            "CREATE TABLE IF NOT EXISTS users (
                id          VARCHAR(36) PRIMARY KEY,
                name        VARCHAR(120) NOT NULL,
                email       VARCHAR(191) UNIQUE NOT NULL,
                pass_hash   VARCHAR(255) NOT NULL,
                role        VARCHAR(60)  NOT NULL DEFAULT 'Staff',
                color       VARCHAR(20)  DEFAULT '#BA7517',
                avatar_url  TEXT,
                permissions JSON         NOT NULL,
                linked_google   TINYINT(1) DEFAULT 0,
                linked_facebook TINYINT(1) DEFAULT 0,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS inventory (
                id          VARCHAR(36) PRIMARY KEY,
                model       VARCHAR(191) NOT NULL,
                config      VARCHAR(100) DEFAULT '',
                brand       ENUM('HONOR','REDMI','ONEPLUS','OTHER') NOT NULL,
                type        ENUM('Regular','LMP','Repair') NOT NULL,
                color       VARCHAR(80)  DEFAULT '',
                house       VARCHAR(120) DEFAULT '',
                source      VARCHAR(255) DEFAULT '',
                price       DECIMAL(10,2) DEFAULT 0,
                start_stock INT UNSIGNED DEFAULT 0,
                stock_in    INT UNSIGNED DEFAULT 0,
                stock_out   INT UNSIGNED DEFAULT 0,
                description TEXT,
                note        TEXT,
                image_url   TEXT,
                imeis       JSON         DEFAULT NULL,
                variations  JSON         DEFAULT NULL,
                deleted_at  DATETIME     DEFAULT NULL,
                deleted_by  VARCHAR(36)  DEFAULT NULL,
                created_by  VARCHAR(36)  DEFAULT NULL,
                date_added  DATE         NOT NULL,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_brand (brand),
                INDEX idx_type  (type),
                INDEX idx_deleted (deleted_at),
                INDEX idx_model (model)
            )",
            "CREATE TABLE IF NOT EXISTS history (
                id         VARCHAR(36) PRIMARY KEY,
                inv_id     VARCHAR(36) NOT NULL,
                model      VARCHAR(191) NOT NULL,
                config     VARCHAR(100) DEFAULT '',
                type       VARCHAR(60),
                action     ENUM('IN','OUT') NOT NULL,
                qty        INT NOT NULL,
                note       TEXT,
                created_by VARCHAR(36),
                date       DATE NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_inv  (inv_id),
                INDEX idx_date (date),
                FOREIGN KEY (inv_id) REFERENCES inventory(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS sessions (
                token      VARCHAR(64) PRIMARY KEY,
                user_id    VARCHAR(36) NOT NULL,
                expires_at DATETIME    NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS wa_chats (
                id         VARCHAR(36) PRIMARY KEY,
                user_id    VARCHAR(36) NOT NULL,
                name       VARCHAR(191) NOT NULL,
                type       ENUM('group','contact') DEFAULT 'contact',
                color      VARCHAR(20),
                status_txt VARCHAR(191) DEFAULT '',
                messages   JSON DEFAULT NULL,
                unread     INT DEFAULT 0,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )"
        ];

        foreach ($queries as $sql) {
            self::getInstance()->exec($sql);
        }

        // Insert default admin if no users exist
        $adminCount = self::fetch("SELECT COUNT(*) as count FROM users")['count'];
        if ($adminCount == 0) {
            $id = 'admin-001';
            $email = 'admin@stockdesk.app';
            $pass = password_hash('admin', PASSWORD_DEFAULT);
            $perms = json_encode(['addItem'=>true, 'editItem'=>true, 'deleteItem'=>true, 'clearHistory'=>true, 'manageUsers'=>true, 'emptyTrash'=>true]);
            self::query("INSERT INTO users (id, name, email, pass_hash, role, permissions) VALUES (?, ?, ?, ?, ?, ?)", 
                [$id, 'Admin', $email, $pass, 'Super Admin', $perms]);
        }
    }
}
?>
