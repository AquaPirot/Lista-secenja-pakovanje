# AG GROUP — Lista sečenja i pakovanje

PWA za izradu radnih naloga za bioklimatske pergole: proračun profila, lista sečenja,
obračun materijala, magacin sa optimizacijom sečenja i kalendar produkcije.

## Gde šta živi

| Fajl | Šta radi |
|---|---|
| `index.html` | Cela aplikacija za pergole — HTML, CSS, JS. ~225 KB. |
| `slike.js` | Sve sličice (base64) kao `window.IMG`. ~700 KB, kešira se posebno (`SLIKE_VER` u `sw.js` + `?v=` u `index.html`). |
| `giljotina.html` | Zaseban program za giljotine; arhiva na serveru (status `gio` u istoj tabeli). |
| `slajding.html` | Zaseban program za klizni sistem sa 4 šine (4 krila); arhiva na serveru (status `sld`). |
| `slajding-nor.html` | Zaseban program za **Nor** klizni sistem (2–5 šina), po Nor kalkulatoru; arhiva status `snr`. |
| `pocetna.html` | Naslovna — izbor pergola / giljotina / klizni sistem 4 šine / Slajding Nor + traka Porudžbina. |
| `porudzbina.html` | Porudžbina: pergole (poručeni profili → koliko pergola izlazi + okov/vijci/elektronika) i stakleni sistemi (mere → profili sa otpadom, staklo, okov, PDF na turskom). |
| `tests/provera.cjs` | Automatska provera proračuna (pravila + referenca). **Pokrenuti posle svake izmene.** |
| `sw.js` | Service worker. Network-first za HTML, cache-first za ikone, `/api/` se nikad ne kešira. |
| `popis.html` | Zaseban mobile-first ekran za popis magacina na telefonu. |
| `api/*.php` | Backend na hostingu: `nalozi.php`, `magacin.php`, `artikli.php`, `db.php`, `config.php`. |
| `manifest.json`, `icons/` | PWA instalacija. |

Produkcija: **app.aggroup.rs** (cPanel hosting). API na `https://app.aggroup.rs/api`,
ključ `X-Api-Key: ag2025app` — mora da se poklapa sa `api/config.php`.

## Objavljivanje izmena

Nema automatskog deploya — fajlovi se ručno prebacuju kroz **cPanel File Manager**
u Document Root poddomena `app.aggroup.rs` (nađe se u cPanel → Domains).

Uz svaku izmenu:
1. `APP_VER` u `index.html` (prikazuje se u futeru — tako se proverava da je upload prošao)
2. `CACHE_NAME` u `sw.js` (bez toga browseri i telefoni drže staru verziju)
3. Uploaduje se **i `index.html` i `sw.js`** (i `slike.js` kad se slike menjaju); `api/*.php` samo kad je backend menjan
4. Pre commita: `node tests/provera.cjs` — pravila moraju da prođu; razlika u referenci je
   u redu samo ako je izmena namerna (tada `--snimi`)

Trenutna verzija: `v2026-06-11.45` / cache `lista-secenja-v55` / slike v2 · klizni `s-v2026-11-19.3` · Nor `n-v2026-11-24.1` · porudžbina `p-v2026-11-24.1`.

## Izgled ekrana i radni nalog (od v2026-06-11.41)

- **Gornja traka**: NALOG | OBRAČUN | IZVEŠTAJ + Sačuvaj, ✓ Pregled i izdavanje, PDF;
  drugi red: Arhiva, Proizvodnja, Kalendar, Magacin (Stanje, Istorija), ← Početna.
- **Forma** u karticama: 1 Nalog i kupac (+ `napomena`), 2 Mere, 3 Konstrukcija, 4 Krov,
  5 Pogon i rasveta, 6 Čelik. `select[data-pills]` se prikazuje kao dugmad (`initPills`/`syncPills`),
  sam select ostaje skriven i nosi vrednost — ID-jevi polja su nepromenjeni.
- **Tabla „Proračun uživo"** (`renderDash`): raja, polja, razmak korniša, dužina raja, pad, korniša,
  kontrola (`kontrole()`), cena koštanja — samo na ekranu, ne štampa se.
- **Ručni broj raja**: `rajMod` (auto/rucno) + `brRajaRucno`; sme samo VIŠE od auto
  (`R.auto.autoRaja`), `R.auto.rajRucno` = zadato ručno.
- **Pregled pre izdavanja** (`otvoriPregled`, `pregledStavke()`): svaka stavka se štiklira,
  „Izdaj nalog" čuva nalog sa `_izdato` i štampa. **Izveštaj o pergoli** (`renderIzvestaj`) je
  siže bez cena, dostupan na IZVEŠTAJ i iz Arhive.
- **Radni nalog po listovima**: zbirni list + A sečenje aluminijuma, B cerada (za ceradare: rolne,
  mapa kedera, PRG-101), C okov i pakovanje, D elektronika, E bravarija. Svaki list počinje na novoj
  strani, osim E koji ide ispod D (kratak je). **Nema polja za potpise** — u praksi se ne potpisuje.
  Stavke okova, vijaka i elektronike su u dve kolone (`stavke()`), štampa je A4 (`@page{size:A4}`).

## Novi proizvodi — uvek poseban fajl (dogovor sa vlasnikom)

Svaki proizvod je **zaseban program** i fajlovi se ne mešaju: pergola (`index.html`), giljotina
(`giljotina.html`), klizni sistem (`slajding.html`), a sledeći (npr. ZIP zavese) dobijaju svoj fajl.
Šablon je `slajding.html`/`giljotina.html`: isti izgled, PIN, nalog + obračun, arhiva na serveru sa
**svojim statusom** u `radni_nalozi` (dodati ga u `nalozi.php`: lista po `tip`, i isključiti iz liste
pergola i kalendara), kartica na `pocetna.html`, `CACHE_NAME` u `sw.js`. Formule daje vlasnik sa
fizičkog uzorka — ne pogađati, pitati.

## Pristup i čuvanje

- **PIN** (4 cifre) na svim stranama, pamti se 30 dana na uređaju (`ag_pin_do` u localStorage).
  U kodu je samo heš. Nalog otvoren preko QR koda (`#z1~`) ne traži PIN. Zaštita je na strani
  pretraživača — sprečava slučajan pristup, nije prava serverska zaštita.
- **Isti nalog = isti broj naloga + kupac** (`nalogKljuc`): Sačuvaj i Izdaj tada AŽURIRAJU isti zapis;
  promena broja ili kupca čuva nov nalog. „＋ Novi nalog" vraća formu na početne vrednosti.
- **Broj naloga** se dodeljuje sam pri čuvanju/izdavanju ako je polje prazno: `GGGG-NNN`, najveći
  sačuvani broj u tekućoj godini + 1 (`sledeciBrojNaloga`). Bez interneta polje ostaje prazno.
- **Strana motora** (`motorStrana`: levo/desno, gledano čeono) — ispisuje se na zbirnom listu i
  listovima A, C, D zbog poklopaca. Boje cerade: Krem 3D (podrazumevano), Krem mat, Antracit, Crna, Miš boja.
- Korisnik koristi **Nalog, Obračun, Izveštaj i Arhivu**; Proizvodnja, Kalendar i Magacin postoje,
  ali se u praksi ne koriste — ne ulagati u njih bez pitanja.

## Klizni sistem (slajding.html)

X = širina, Y = visina **sistema** (otvor je par mm veći). 4 šine, 4 krila. Formule sa fizičkog uzorka (vlasnik):
1046 šina sa pragom X − 22 (između dve kutije 1047 od 11 mm) × 2 · 1047 kutija Y × 2 · 1053-1 završni Y × 2 ·
1049 ručka Y − 42 × 2 · 1051 letvica stakla Y − 42 × 6 · 1050 baza krila (X − 258)/4 + 4 × 8 (gore i dole na svakom krilu).
Okov: po krilu 2 spojnice gore + 2 dole, 2 seta točkova. 1048 i 1099 se ne koriste. Cene — naknadno.
**Staklo** termopan 4+12+4 (1 po krilu): širina = baza 1050 + 22 · visina = dužina 1049 − 126 = Y − 168;
obe mere se **uvek zaokružuju naviše na ceo mm** (681,3 → 682). Žleb 1049/1051 je dubok 18 mm (staklo ulazi 13),
žleb 1050 15 mm (staklo ulazi 10) — svuda ostaje 5 mm za gumu/lepak. Primeri: 2865×2490 → 678×2322,
2800×1790 → 662×1622, 3380×2110 → 807×1942.
Ručke 1049 su **u ogledalu**: krila 1–4 s leva na desno gledano iznutra; krilo 4 (desno iznutra) ima 1049 sa ručkom
KA UNUTRA, krilo 1 (desno gledano spolja) ručku KA SPOLJA; kraći deo 1049 je između stuba i ručke; i iznutra i spolja
se otvara s desna na levo. Nalog ima odeljak „Raspored krila i ručki" (`rasporedRucki()`): pogled iznutra, spolja,
presek odozgo i tabela vertikala po krilu.

## Slajding Nor (slajding-nor.html)

Po Nor Excel kalkulatoru „Sürme Hesaplama" (X širina, Y visina, D = šine = krila 2–5). Podrazumevano: bez praga
(eşiksiz), termopan 4+12+4 (profili 10394/10396/10398), bez srednjeg H profila. Gornja kasa je UVEK sa pragom
(10407/10406/10405/10494); donja sa pragom ili bez (10410/10409/10408/10492). Rezovi: kase X − 24 · baza krila
(staklo − 30) × 2D · krilo sa ručkom Y − 105 (prag Y − 125) × 2 · kenet Y − 39 (Y − 59) × (2D − 2) · bočna kasa
Y × 2 · završni U 10400 Y − 15 (Y − 26) × 2 · H 10401 Y − 39 × 1. Staklo: ((X − 146) + (D − 1)·4 − 79·H) / D ×
(Y − 193 | prag Y − 213), zaokruženo naviše; D po sistemu. Okov: točak 2D, garnitura krila sa ručkom 2,
kenet garnitura D − 2; sa pragom još 4 + 4 zaptivke. Aluminijum 4,65 €/kg (Nor potvrda).
Napomena: u Nor Excelu izbor „eşikli" ne radi (tursko İ u formuli) — ovde su odbici kako su zamišljeni.
Provereno: 3000×3000, 4 šine, tek cam, sa H → 32,83 kg i staklo 697×2807 (isto kao Excel).

## Porudžbina (porudzbina.html)

Ne sadrži sopstvene formule: učitava `index.html`, `giljotina.html`, `slajding.html` i `slajding-nor.html`
u skrivene iframe-ove (isti server) i poziva njihove `compute/aluCalc/applyOkov/vijcanaRoba` i `proracun`.
Promena formule u programu se zato automatski vidi i u porudžbini. Radi samo sa servera (ne kao `file://`).
- **Pergole**: lista poručenih profila (šifra, dužina šipke, broj šipki) + tipovi pergola; jedan tip može biti
  „najviše što može" — povećava se broj dok sve šifre staju (best-fit sečenje, rez 5 mm). Prikaz: koliko izlazi,
  šta fali za još jednu, iskorišćenje po profilu, ostaci, plan sečenja, i porudžbina okova (ARV/PRG/AK), vijaka,
  elektronike (motor, dimer, trafo po tipu, kutija, daljinski po pergoli), cerade m², lima, cevi na zidu.
  „Popuni listu prema pergolama" predlaže šipke za zadate komade.
- **Stakleni sistemi**: redovi sistema (giljotina fiksna/brisiva, Slajding Nor, klizni 4 šine) sa merama i komadima;
  za svaki profil bira dužinu šipke sa najmanje poručenih metara od ponuđenih (Gio 7000, Nor 6000/7500/9000).
  kg: giljotina po katalogu (`kat`), Nor po `PROF`. 10417 giljotine se po izboru ne poručuje. Dugme „Türkçe" daje
  porudžbinu za Nor (profil, boy, adet, metre, kg + napomena sa spiskom sistema i molbom za aksesoar).
- Stanje forme se pamti u `localStorage` (`por_stanje`, `por_sistemi`, `por_tab`); nema arhive na serveru.

## Arhitektura proračuna

Sve teče kroz jedan lanac u `index.html`:

```
render()  →  compute(I)  →  aluCalc(I,R)  →  applyOkov(R,I)  →  obracunCalc(...)
                  ↓              ↓                ↓
            renderNalog(...)  ili  renderObracun(...)
```

- **`compute(I)`** — jezgro. Iz unetih mera računa dijagonalu, raje, korniše, ceradu,
  pozicije kedera, LED rupe. Vraća objekat `R` sa granama `in / auto / raj / cerada /
  kornis / splice / profili2 / led / kolica`.
- **`aluCalc`** — pozicije za sečenje (šina, oluk, stub, cev motora, panel, korniši).
- **`applyOkov`** — primenjuje `KATALOG` pravila; svaka stavka ima `pravilo` (raj/stub/
  kornis/svetlo/fiksno) ili `fn(c)` za posebne formule.
- **`PROFILI`** — šifre, nazivi (`nazivSr` mora da prati nazive u magacinu), `kgm`, slike.
- **`IMG`** — base64 sličice, ključevi `prof_10471`, `plast_*`, `metal_*`, `fit_*`.

## Poslovna pravila (ovo je suština — ne menjati napamet)

### Raje i stubovi
- Max razmak između raja **4000 mm**, meri se sa rajevima (D / brPolja ≤ 4000), isto za sve širine raja:
  `brStubova = max(2, ceil(D / 4000) + 1)` → do 4000 = 2 raja, do 8000 = 3, do 12000 = 4
- Širine raja: 70 (10412, max dužina 5000 mm — nije za velike izbačaje), 75 (GÇ-2631, 75×155, 5,390 kg/m), 90 (podrazumevano, 10501), 110 (10475)
- Oluk: standardni 10468 (podrazumevano) ili sa duplim dnom 10477 — isti poklopci, odvod, keder i šrafovi; spojka sa rajem je ARV.10.079 umesto ARV-M6
- `brPolja = brRaja − 1`. **Broj stubova**: auto = broj raja; ručno (`stubMod`/`brStubovaRucno`) može više
  ILI manje, i 0 (`R.auto.stubRucno`). Menja samo ono što ide po stubu (stub, stopa, spoj stub–oluk,
  odvod, vijci stuba). Spoj šina–oluk (ARV-M6 / ARV.10.079) ide **po raju** (× 2).
- Dužina raja = dijagonala − 200; pad mora biti 8–10 %

### Korniši — sklop mora da da punu širinu pergole
Sečeni korniš ima **po jedan komad u svakom polju** → `n = brPolja` komada po poziciji.
Svi umetci su po **50 mm** i ulaze u širinu:

- **obostrani**: 2 ugaona (krajevi) + (n−1) srednjih konektora = **n+1** umetaka
- **jednostrani**: samo (n−1) srednjih konektora — ugaoni ne skraćuju korniš

```
duzKornisa = (D − 50 × umetakaUDuzini) / n
```

Primer 9450 mm, obostrani, 4 raja → 3 polja:
`50 + 3083 + 50 + 3083 + 50 + 3083 + 50 = 9450`

Provera da formula pokriva sve slučajeve (n=1 je jednodelni):

| | Jednodelni (n=1) | Sečeni, 3 raja (n=2) | Sečeni, 4 raja (n=3) |
|---|---|---|---|
| **Jednostrani** | `D` | `(D − 50) / 2` | `(D − 100) / 3` |
| **Obostrani** | `D − 100` | `(D − 150) / 2` | `(D − 200) / 3` |

Srednjih umetaka (konektora) ima `n − 1` **po svakoj poziciji korniša** —
`ARV-20` i `ARV-22` množe se sa `srednjihUmetaka`, ne fiksno sa 1.

- Sečeni je moguć **samo od 3 raja naviše**
- Obostrani + ≥3 raja → sečeni je **obavezan** (jednodelni se disabluje)
- Profili po kederu: jednostrani → **10472** (debeli) + **10471** (tanki);
  obostrani → **10416** + **10418**
- **Tanki korniš sa LED lajsnom EK-29935** (41×56,9, 1,072 kg/m; `kornisLed`='lajsna', `R.led.ledLajsna`):
  samo uz **obostrani** keder i samo umesto tankih korniša **sa svetlom** (bez svetla ostaju 10418).
  Ista svetla, isti poklopci i umetci. **Ne buši se** — tačke na listu A samo pokazuju gde ide svetlo.

### Cerada
- Širina = **D − 10 mm** (zategne se na punu širinu pergole)
- Dužina = dijagonala + 400
- Nijedna rolna ne sme preko **3000 mm** — sastav uvek pada na keder,
  algoritam traži poslednji keder unutar 3000 mm od početka tekuće rolne

### Razmak korniša (kedera)
- Ciljni opseg **399–499 mm**, auto-uklapanje gađa 449 mm
- Korisna dužina = dijagonala − 50 − 70; K1 na **50 mm** od prednje ivice (ranije 100),
  rezerva uz zid 470 mm
- **Spojene pergole** (`fiksniRazmak > 0`): razmak se zadaje ručno da bi korniši
  susednih pergola bili u liniji. Razmaci idu **od K1 (od fronta)**, a razlika se
  uklapa u **poslednje polje pre kecelje** (sme da bude manje od ostalih).
  Ako bi ostatak bio **ispod 200 mm**, ne dodaje se keder — poslednje polje se proširi
  (ostatak + zadati razmak), jer to polje nema opterećenje.

### Bušenje
- **LED**: korniš se deli na `n` jednakih segmenata, svetlo u centru svakog →
  pozicija = `L × (2k−1) / (2n)` (bez nagomilavanja greške)
- **Rupe za kolica**: samo kod **jednostranog jednodelnog** korniša — 45 mm od obe
  ivice, ostale ravnomerno između. Kod **sečenog** se skica ne crta (svaki komad se
  buši na jednu stranu, srednja rupa ide u konektor). Kod **obostranog** se ne buši
  uopšte — kolica idu kroz rupe u umetcima.

### Okov — posebni slučajevi
- **Tapa (ARV-T) i Tipla (ARV-D)**: samo jednostrani keder, `kornisaUkupno × raja`
- **Srednji umetci (konektori)**: samo kod sečenog korniša
- **Zupčasti kaiš T10×16**: `raja × (2 × duzRaja + 500) / 1000` m
- **PRG-101 keder cerade**: `kornisaUkupno × sirinaCerade` (puna širina, ne dužina korniša)

### Broj komada
Polje `brKomada` — jedan nalog za više **identičnih** pergola. Množi se sve:
alu pozicije, okov, cerada, elektronika, lim, cev na zidu, čelična konstrukcija,
paušal. Skidanje sa magacina takođe uzima n×.

## Magacin i optimizacija sečenja

`planirajSecenje(trazeni, magacin)` koristi **best-fit**: za svaki rez bira šipku koja
ostavlja najmanji otpad (a ne najveću šipku prvo). Rez 5500 ide iz šipke 5500, ne iz 7500.
Ostatak preko praga se vraća na stanje i ponovo koristi.

Pragovi otpada: podrazumevano **2000 mm**, za šine/stubove (`10501`, `10475`, `10419`,
`10414`) **1500 mm**.

## Arhiva i kalendar

- Nalozi se čuvaju kroz `api/nalozi.php?action=save`; fallback na `localStorage` bez neta
- `ARCH_FIELDS` — lista ID-jeva polja koja se čuvaju. **Svako novo polje u formi mora
  da se doda ovde**, inače se ne pamti u nalogu.
- Statusi: `aktivan` → `proizvodnja` → `isporucen` (+ `otkazan` = soft delete)
- Kalendar (`action=kalendar`) grupiše naloge po polju `rok`; nalozi bez roka idu u
  posebnu sekciju ispod

### Kompatibilnost sa starim nalozima
`loadOrder` mapira stare formate na nove — `keder`+`kornisTip` i `kornisVrsta` se
prevode u `kederTip`+`kornisSklop`; `brKomada` i `fiksniRazmak` dobijaju podrazumevane
vrednosti (1 i 0) ako fale.

## Šta je urađeno, šta je u planu

Predlozi o kojima se razgovaralo, redosled po korisnosti:

1. **Interaktivno štikliranje** — radnik na telefonu štiklira stavke sečenja i pakovanja,
   status se čuva na serveru, kancelarija vidi napredak u realnom vremenu (započeto,
   nije implementirano)
2. **QR kod na štampanom nalogu** — skenira se i otvara nalog na telefonu
3. **Minimalne zalihe** — prag po profilu, upozorenje „naručiti" kad stanje padne ispod
4. **Zbirna nabavka** — ukupne količine za sve naloge u pripremi vs. stanje magacina
5. **Dupliranje naloga** — kopiraj postojeći i promeni samo mere

## Napomene za rad na kodu

- Sve je u jednom fajlu — pre izmene proveriti `grep` da li se ista vrednost koristi
  na više mesta (npr. opsezi razmaka se pojavljuju i u CSS labelama i u napomenama)
- Provera sintakse bez browsera:
  ```
  node -e "const h=require('fs').readFileSync('index.html','utf8');
  [...h.matchAll(/<script>([\s\S]*?)<\/script>/g)].forEach((m,i)=>{
    try{new Function(m[1])}catch(e){console.log(i,e.message)}}); console.log('OK')"
  ```
- `compute()` se može testirati izolovano: izvući `<script>` blok koji je sadrži,
  stubovati `document` i pozvati direktno sa objektom `I`
- Poruke ka korisniku, komentari u kodu i tekst u nalogu — **na srpskom**
