<?php
// ⚠ BRIŠE SVE: magacin, istoriju i naloge — kreće se od nule!
// Pokrenuti JEDNOM u browseru: reset_all.php?potvrda=DA
// Posle toga ODMAH OBRISATI ovaj fajl sa servera!
require_once __DIR__.'/db.php';
header('Content-Type: text/html; charset=utf-8');

if(($_GET['potvrda'] ?? '') !== 'DA'){
  echo '<h2>⚠ Reset svih podataka</h2>
  <p>Ova skripta briše <b>SVE</b>:</p>
  <ul>
    <li>kompletno stanje magacina (svi profili, komadi, količine)</li>
    <li>celu istoriju (magacin_log)</li>
    <li>sve sačuvane radne naloge</li>
  </ul>
  <p>Katalog artikala (tabela artikli) se NE briše.</p>
  <p><a href="reset_all.php?potvrda=DA" style="background:#c0392b;color:#fff;padding:10px 20px;border-radius:5px;text-decoration:none;font-weight:bold;">⚠ DA, OBRIŠI SVE — kreni od nule</a></p>';
  exit;
}

$pdo = db();
$pdo->exec("TRUNCATE TABLE magacin");
$pdo->exec("TRUNCATE TABLE magacin_log");
$pdo->exec("TRUNCATE TABLE radni_nalozi");

echo '<h2>✅ Obrisano — sve je na nuli!</h2>
<ul>
  <li>magacin: prazan</li>
  <li>istorija: prazna</li>
  <li>nalozi: prazni</li>
</ul>
<p style="color:#c0392b;font-weight:bold;font-size:18px;">⚠ ODMAH OBRIŠI reset_all.php SA SERVERA!</p>
<p style="margin-top:16px;">
  <a href="/popis.html?reset=1" style="background:#2a6a3a;color:#fff;padding:12px 24px;border-radius:5px;text-decoration:none;font-weight:bold;font-size:16px;">
    → Otvori popis (čisti start)
  </a>
</p>
<p style="font-size:12px;color:#555;margin-top:8px;">Ovaj link čisti i lokalni keš u browseru — koristiti samo ovaj link, ne direktno popis.html</p>';
