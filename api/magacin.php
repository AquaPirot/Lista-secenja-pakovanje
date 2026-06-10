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

// POST korekcija — ručna izmena sa razlogom, upisuje u log
if($method === 'POST' && $action === 'korekcija'){
  $b     = body();
  $sifra = $b['sifra']  ?? '';
  $data  = $b['data']   ?? [];
  $razlog = $b['razlog'] ?? 'Ručna korekcija';
  if(!$sifra) json_out(['ok'=>false,'err'=>'No sifra'], 400);

  $pdo = db();
  $pdo->beginTransaction();
  if(isset($data['komadi'])){
    $q = $pdo->prepare("INSERT INTO magacin (sifra,tip,komadi,updated_at) VALUES(?,?,?,NOW())
      ON DUPLICATE KEY UPDATE tip=VALUES(tip), komadi=VALUES(komadi), updated_at=NOW()");
    $q->execute([$sifra, 'profil', json_encode($data['komadi'], JSON_UNESCAPED_UNICODE)]);
  } else {
    $q = $pdo->prepare("INSERT INTO magacin (sifra,tip,kolicina,minimum,updated_at) VALUES(?,?,?,?,NOW())
      ON DUPLICATE KEY UPDATE kolicina=VALUES(kolicina), minimum=VALUES(minimum), updated_at=NOW()");
    $q->execute([$sifra, 'kom', $data['kolicina'] ?? 0, $data['minimum'] ?? 0]);
  }
  $ql = $pdo->prepare("INSERT INTO magacin_log (sifra, akcija, detalji) VALUES(?,?,?)");
  $ql->execute([$sifra, 'korekcija', json_encode(['razlog'=>$razlog,'data'=>$data], JSON_UNESCAPED_UNICODE)]);
  $pdo->commit();
  json_out(['ok'=>true]);
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

// POST proizvodnja — novo stanje + upis u magacin_log (jedna transakcija)
if($method === 'POST' && $action === 'proizvodnja'){
  $b     = body();
  $state = $b['state'] ?? [];
  $log   = $b['log']   ?? [];
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
  $ql = $pdo->prepare("INSERT INTO magacin_log (sifra, akcija, detalji) VALUES(?,?,?)");
  foreach($log as $l){
    $ql->execute([$l['sifra'] ?? '', $l['akcija'] ?? 'secenje',
                  json_encode($l['detalji'] ?? [], JSON_UNESCAPED_UNICODE)]);
  }
  $pdo->commit();
  json_out(['ok'=>true, 'log'=>count($log)]);
}

// GET istorija — poslednje promene
if($method === 'GET' && $action === 'log'){
  $rows = db()->query("SELECT sifra, akcija, detalji, vreme FROM magacin_log ORDER BY id DESC LIMIT 100")->fetchAll();
  foreach($rows as &$r) $r['detalji'] = json_decode($r['detalji'], true);
  json_out(['ok'=>true, 'data'=>$rows]);
}

json_out(['ok'=>false,'err'=>'Bad request'], 400);
