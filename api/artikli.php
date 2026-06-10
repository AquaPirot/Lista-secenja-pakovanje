<?php
require_once __DIR__.'/db.php';
cors();
auth();

// tabela se kreira automatski pri prvom pozivu
db()->exec("CREATE TABLE IF NOT EXISTS artikli (
  sifra VARCHAR(32) PRIMARY KEY,
  naziv VARCHAR(190) NOT NULL,
  kategorija VARCHAR(32) NOT NULL,
  tip VARCHAR(16) NOT NULL DEFAULT 'kom',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARSET=utf8mb4");

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// GET lista svih custom artikala
if($method === 'GET' && $action === 'list'){
  $rows = db()->query("SELECT sifra, naziv, kategorija, tip FROM artikli ORDER BY created_at")->fetchAll();
  json_out(['ok'=>true, 'data'=>$rows]);
}

// POST dodaj novi artikal
if($method === 'POST' && $action === 'add'){
  $b = body();
  $sifra      = trim($b['sifra'] ?? '');
  $naziv      = trim($b['naziv'] ?? '');
  $kategorija = trim($b['kategorija'] ?? '');
  $tip        = trim($b['tip'] ?? 'kom');
  if(!$sifra || !$naziv || !$kategorija) json_out(['ok'=>false,'err'=>'Nedostaje šifra, naziv ili kategorija'], 400);
  if(!in_array($tip, ['profil','kom','metar','metar2'])) $tip = 'kom';

  $q = db()->prepare("INSERT INTO artikli (sifra, naziv, kategorija, tip) VALUES(?,?,?,?)
    ON DUPLICATE KEY UPDATE naziv=VALUES(naziv), kategorija=VALUES(kategorija), tip=VALUES(tip)");
  $q->execute([$sifra, $naziv, $kategorija, $tip]);
  json_out(['ok'=>true]);
}

// POST obriši artikal (samo iz kataloga — stanje u magacinu ostaje)
if($method === 'POST' && $action === 'delete'){
  $b = body();
  $sifra = trim($b['sifra'] ?? '');
  if(!$sifra) json_out(['ok'=>false,'err'=>'No sifra'], 400);
  $q = db()->prepare("DELETE FROM artikli WHERE sifra=?");
  $q->execute([$sifra]);
  json_out(['ok'=>true]);
}

json_out(['ok'=>false,'err'=>'Bad request'], 400);
