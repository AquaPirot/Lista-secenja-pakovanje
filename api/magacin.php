<?php
require_once __DIR__.'/db.php';
cors();
auth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// GET sve stavke magacina
if($method === 'GET' && $action === 'get'){
  $rows = db()->query("SELECT sifra, tip, komadi, kolicina, minimum FROM magacin")->fetchAll();
  $out = [];
  foreach($rows as $r){
    if($r['tip'] === 'profil'){
      $out[$r['sifra']] = ['komadi' => json_decode($r['komadi'] ?? '[]', true)];
    } else {
      $out[$r['sifra']] = ['kolicina' => (float)$r['kolicina'], 'minimum' => (float)$r['minimum']];
    }
  }
  json_out(['ok'=>true, 'data'=>$out]);
}

// POST sačuvaj jednu stavku
if($method === 'POST' && $action === 'set'){
  $b     = body();
  $sifra = $b['sifra'] ?? '';
  $data  = $b['data']  ?? [];
  if(!$sifra) json_out(['ok'=>false,'err'=>'No sifra'], 400);

  if(isset($data['komadi'])){
    $q = db()->prepare("INSERT INTO magacin (sifra,tip,komadi,updated_at) VALUES(?,?,?,NOW())
      ON DUPLICATE KEY UPDATE tip=VALUES(tip), komadi=VALUES(komadi), updated_at=NOW()");
    $q->execute([$sifra, 'profil', json_encode($data['komadi'], JSON_UNESCAPED_UNICODE)]);
  } else {
    $q = db()->prepare("INSERT INTO magacin (sifra,tip,kolicina,minimum,updated_at) VALUES(?,?,?,?,NOW())
      ON DUPLICATE KEY UPDATE tip=VALUES(tip), kolicina=VALUES(kolicina), minimum=VALUES(minimum), updated_at=NOW()");
    $q->execute([$sifra, 'kom', $data['kolicina'] ?? 0, $data['minimum'] ?? 0]);
  }
  json_out(['ok'=>true]);
}

// POST snimi ceo popis odjednom (import iz localStorage)
if($method === 'POST' && $action === 'setall'){
  $b     = body();
  $state = $b['state'] ?? [];
  if(!$state) json_out(['ok'=>false,'err'=>'Empty state'], 400);

  $pdo = db();
  $pdo->beginTransaction();
  foreach($state as $sifra => $data){
    if(isset($data['komadi'])){
      $q = $pdo->prepare("INSERT INTO magacin (sifra,tip,komadi,updated_at) VALUES(?,?,?,NOW())
        ON DUPLICATE KEY UPDATE komadi=VALUES(komadi), updated_at=NOW()");
      $q->execute([$sifra, 'profil', json_encode($data['komadi'], JSON_UNESCAPED_UNICODE)]);
    } else {
      $q = $pdo->prepare("INSERT INTO magacin (sifra,tip,kolicina,minimum,updated_at) VALUES(?,?,?,?,NOW())
        ON DUPLICATE KEY UPDATE kolicina=VALUES(kolicina), minimum=VALUES(minimum), updated_at=NOW()");
      $q->execute([$sifra, 'kom', $data['kolicina'] ?? 0, $data['minimum'] ?? 0]);
    }
  }
  $pdo->commit();
  json_out(['ok'=>true, 'saved'=>count($state)]);
}

json_out(['ok'=>false,'err'=>'Bad request'], 400);
