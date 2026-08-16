# AG GROUP — Lista sečenja i pakovanje

PWA za izradu radnih naloga za bioklimatske pergole: proračun profila, lista sečenja,
obračun materijala, magacin sa optimizacijom sečenja i kalendar produkcije.

## Gde šta živi

| Fajl | Šta radi |
|---|---|
| `index.html` | Cela aplikacija — HTML, CSS, JS, base64 slike profila i okova. ~340 KB. |
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
3. Uploaduje se **i `index.html` i `sw.js`**; `api/*.php` samo kad je backend menjan

Trenutna verzija: `v2026-06-11.17` / cache `lista-secenja-v19`.

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
- Max razmak između raja **4150 mm**: `brStubova = max(2, ceil((D + 4150) / (4150 + sirinaRaja)))`
- `brRaja = brStubova`, `brPolja = brRaja − 1`
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

### Cerada
- Širina = **D − 10 mm** (zategne se na punu širinu pergole)
- Dužina = dijagonala + 400
- Nijedna rolna ne sme preko **3000 mm** — sastav uvek pada na keder,
  algoritam traži poslednji keder unutar 3000 mm od početka tekuće rolne

### Razmak korniša (kedera)
- Ciljni opseg **399–499 mm**, auto-uklapanje gađa 449 mm
- Korisna dužina = dijagonala − 100 − 70; K1 na 100 mm od prednje ivice,
  rezerva uz zid 470 mm
- **Spojene pergole** (`fiksniRazmak > 0`): razmak se zadaje ručno da bi korniši
  susednih pergola bili u liniji. Razmaci idu **od K1 (od fronta)**, a razlika se
  uklapa u **poslednje polje pre kecelje** (sme da bude manje od ostalih).

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
