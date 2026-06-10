<?php
// Probni uvoz iz narudžbine NOR 0000002872
// Pokrenuti JEDNOM — posle toga ODMAH OBRISI sa servera!
require_once __DIR__.'/db.php';

function fill($n, $duzina){ return array_fill(0, $n, ['duzina'=>(string)$duzina]); }

$uvoz = [
  '10501' => array_merge(fill(15, 7000), fill(15, 9000)),   // Šina raj 90×150
  '10468' => fill(20, 9000),                                  // Oluk
  '10419' => fill(20, 9000),                                  // Stub 110×144
  '10420' => array_merge(fill(20, 9000), fill(5, 6500)),     // Panel
  '10417' => fill(20, 9000),                                  // Cev motora
];

$nije_uvezeno = [
  '10418' => '62×9000 — Thin Stringcourse (verovatno 10470 ili 10471 u app-u?)',
  '10416' => '20×9000 — Thick Stringcourse (verovatno 10472 u app-u?)',
  '1481'  => '15×6300 — 100×50×2 cev (nije u listi)',
  '10467' => '10×7500 — Bull Block Tube 2 (nije u listi)',
];

$pdo = db();
$ok = [];
foreach($uvoz as $sifra => $komadi){
  $q = $pdo->prepare("INSERT INTO magacin (sifra, tip, komadi, updated_at) VALUES(?,?,?,NOW())
    ON DUPLICATE KEY UPDATE komadi=VALUES(komadi), updated_at=NOW()");
  $q->execute([$sifra, 'profil', json_encode($komadi, JSON_UNESCAPED_UNICODE)]);
  $ok[] = $sifra.' ('.count($komadi).' komada)';
}

header('Content-Type: text/html; charset=utf-8');
echo '<h3>✅ Uvezeno:</h3><ul>';
foreach($ok as $l) echo '<li>'.$l.'</li>';
echo '</ul>';
echo '<h3>⚠️ Nije uvezeno (proveri šifre):</h3><ul>';
foreach($nije_uvezeno as $s=>$t) echo '<li><b>'.$s.'</b>: '.$t.'</li>';
echo '</ul>';
echo '<p><b>OBRISI import_test.php sa servera!</b></p>';
