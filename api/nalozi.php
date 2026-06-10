<?php
require_once __DIR__.'/db.php';
cors();
auth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// GET lista — svi nalozi (bez punih podataka, samo pregled)
if($method === 'GET' && $action === 'list'){
  $rows = db()->query(
    "SELECT id, klijent, napomena, status, created_at FROM radni_nalozi ORDER BY id DESC"
  )->fetchAll();
  json_out(['ok'=>true, 'data'=>$rows]);
}

// GET jedan nalog sa svim podacima
if($method === 'GET' && $action === 'get'){
  $id = (int)($_GET['id'] ?? 0);
  $st = db()->prepare("SELECT * FROM radni_nalozi WHERE id=?");
  $st->execute([$id]);
  $r = $st->fetch();
  if(!$r) json_out(['ok'=>false,'err'=>'Not found'], 404);
  $r['input_data']  = json_decode($r['input_data'],  true);
  $r['result_data'] = json_decode($r['result_data'], true);
  json_out(['ok'=>true, 'data'=>$r]);
}

// POST sačuvaj (novi ili update)
if($method === 'POST' && $action === 'save'){
  $b  = body();
  $id = (int)($b['id'] ?? 0);
  $kl = $b['klijent']  ?? '';
  $np = $b['napomena'] ?? '';
  $st = $b['status']   ?? 'aktivan';
  $in = json_encode($b['input_data']  ?? null, JSON_UNESCAPED_UNICODE);
  $re = json_encode($b['result_data'] ?? null, JSON_UNESCAPED_UNICODE);

  if($id > 0){
    $q = db()->prepare("UPDATE radni_nalozi SET klijent=?,napomena=?,status=?,input_data=?,result_data=?,updated_at=NOW() WHERE id=?");
    $q->execute([$kl,$np,$st,$in,$re,$id]);
    json_out(['ok'=>true, 'id'=>$id]);
  } else {
    $q = db()->prepare("INSERT INTO radni_nalozi (klijent,napomena,status,input_data,result_data) VALUES(?,?,?,?,?)");
    $q->execute([$kl,$np,$st,$in,$re]);
    json_out(['ok'=>true, 'id'=>db()->lastInsertId()]);
  }
}

// POST obriši (soft delete — status=otkazan)
if($method === 'POST' && $action === 'delete'){
  $b  = body();
  $id = (int)($b['id'] ?? 0);
  db()->prepare("UPDATE radni_nalozi SET status='otkazan' WHERE id=?")->execute([$id]);
  json_out(['ok'=>true]);
}

json_out(['ok'=>false,'err'=>'Bad request'], 400);
