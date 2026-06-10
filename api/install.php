<?php
// Pokretati JEDNOM — posle toga ODMAH OBRISI ovaj fajl sa servera!
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__.'/config.php';

// Test konekcije
try {
  $pdo = new PDO(
    'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );
} catch(PDOException $e){
  die('❌ Greška konekcije: ' . $e->getMessage());
}

// Kreiraj tabele jednu po jednu (exec ne može više odjednom)
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS radni_nalozi (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    klijent     VARCHAR(200) DEFAULT '',
    napomena    TEXT,
    status      VARCHAR(20)  DEFAULT 'aktivan',
    input_data  JSON,
    result_data JSON,
    created_at  DATETIME DEFAULT NOW(),
    updated_at  DATETIME DEFAULT NOW() ON UPDATE NOW()
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS magacin (
    sifra      VARCHAR(50)  NOT NULL PRIMARY KEY,
    tip        VARCHAR(20)  DEFAULT 'kom',
    komadi     JSON,
    kolicina   DECIMAL(10,3) DEFAULT 0,
    minimum    DECIMAL(10,3) DEFAULT 0,
    updated_at DATETIME DEFAULT NOW() ON UPDATE NOW()
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS magacin_log (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    sifra    VARCHAR(50),
    akcija   VARCHAR(30),
    detalji  JSON,
    nalog_id INT NULL,
    vreme    DATETIME DEFAULT NOW()
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  echo '✓ Tabele kreirane uspešno! ODMAH OBRISI install.php sa servera!';
} catch(PDOException $e){
  die('❌ Greška pri kreiranju tabela: ' . $e->getMessage());
}
