# REFERENCA — AG GROUP Lista sečenja i pakovanje

Kompletan tehnički opis aplikacije: sve formule, konstante, pravila i strukture.
Napisano tako da se iz ovog jednog dokumenta može rekonstruisati ceo proračun
bez čitanja koda.

**Verzija dokumenta prati:** `v2026-06-11.17` / SW cache `lista-secenja-v19`

---

## 1. ŠTA APLIKACIJA RADI

PWA (radi i offline) za proizvodnju bioklimatskih pergola. Iz četiri terenske mere
generiše kompletan radni nalog: listu profila za sečenje, mapu bušenja, obračun
materijala, skida profile sa stanja magacina uz optimizaciju sečenja, i vodi
kalendar produkcije po rokovima isporuke.

**Četiri ekrana** (dugmad u zaglavlju): Nalog · Obračun · 📦 Magacin · 📋 Istorija · 📅 Kalendar

**Produkcija:** app.aggroup.rs (cPanel). Deploy je ručni — upload `index.html` + `sw.js`
kroz File Manager. Verzija se proverava u futeru aplikacije.

---

## 2. ULAZNI PODACI (forma)

### ① Terenske mere (mm)
| Polje | ID | Značenje |
|---|---|---|
| Širina | `sirina` | **D** — izmera na objektu |
| Dubina / projekcija | `dubina` | **B** — zid do spoljne mere stuba |
| Zadnja visina | `zadnjaVisina` | **A** — pod do cevi na zidu |
| Prednja visina | `prednjaVisina` | **C** — pod do oluka |
| Broj komada | `brKomada` | koliko **identičnih** pergola u jednom nalogu (default 1) |
| Zidić / parapet | `parapetOn` + `parapetVisina` | oduzima se od prednje visine → dužina stuba |

### ② Izbor profila
| Polje | ID | Opcije |
|---|---|---|
| Cev na zidu | `cev` | `nema` · `80x40` (default) · `100x100` |
| Stub | `stub` | `110x144` (default) · `100x100` |
| Širina raja | `sirinaRaja` | `75` · `90` (default) · `110` |
| Keder | `kederTip` | `jednostrani` · `obostrani` (default) |
| Korniš sklop | `kornisSklop` | `jednodelni` (default) · `seceni` |
| Cev motora | `cevMotora` | `jednodelna` (default) · `po poljima` |
| Boja RAL | `bojaRal` | 7016 · 9005 · 9006 · 8019 · 9010 · prazno (ručno) |
| Boja cerade | `bojaCerade` | Antracit · Krem · Crna · Miš boja |

### ③ Korniši · motor · svetla
| Polje | ID | Napomena |
|---|---|---|
| Auto-uklapanje intervala | `autoInterval` | checkbox, default uključen |
| Broj intervala korniša | `brIntervala` | aktivno samo kad je auto isključen |
| Zadati razmak — spojene pergole | `fiksniRazmak` | 0 = isključeno |
| Svetala po kornišu | `svetalaPoKornizu` | obično 3–6 |
| Motor (model) | `motor` | slobodan tekst |

### ④ Čelična konstrukcija (opciono)
`celicnaOn` + dinamičke vrste `steelRows`: profil (100x100 / 80x40 / 120x80) × komada × dužina

### ⑤ Podaci za nalog
`brNaloga` · `kupac` · `adresa` · `datum` · `rok` (date picker) · `radnik`

---

## 3. JEZGRO PRORAČUNA — `compute(I)`

Redosled je bitan jer se svaka sledeća vrednost oslanja na prethodne.

### 3.1 Pomoćna vrednost — horizontalna dimenzija cevi
```
cevHoriz = 100  ako je cev 100x100
         = 40   ako je cev 80x40
         = 0    ako nema cevi
```

### 3.2 Broj raja i stubova
```
MAX_RAZMAK_RAJA = 4150 mm

brStubova = max(2, ceil((D + 4150) / (4150 + sirinaRaja)))
brRaja    = brStubova
brPolja   = brRaja − 1
sirinaPolja = (D − brRaja × sirinaRaja) / brPolja
preko10m  = D > 10000        (informativno upozorenje)
```
**Logika:** između dva susedna raja sme najviše 4150 mm čistog razmaka. Svaki
dodatni raj nosi i svoju širinu, zato je u imeniocu `razmak + širinaRaja`.
Minimum su uvek 2 raja.

**Granice za raj 90:** do 8570 mm → 3 raja · od 8571 mm → 4 raja

### 3.3 Pad i dijagonala
```
kat1 (visinska razlika) = A − C + 150
kat2 (horizontala)      = B − cevHoriz
pad    = kat1 / kat2 × 100        [%]
padOk  = pad ≥ 8 i pad ≤ 10
dijagonala = √(kat1² + kat2²)
duzRaja    = dijagonala − 200
```
**+150 mm** je visina profila oluka/šine — zadnja visina se meri do cevi na zidu,
prednja do oluka.
**−200 mm** su nalegi: prednji poklopac šine + zadnji spoj na cev.

> ⚠ **Otvoreno pitanje:** provera 8–10 % pali upozorenje u praktično svakoj realnoj
> konfiguraciji (i kod podrazumevanih vrednosti forme). Ili opseg nije tačan za
> ovaj način gradnje, ili se pad meri drugačije. **Nije razrešeno.**

### 3.4 Cerada
```
sirinaCerade = D − 10          (zategne se na punu širinu)
duzCerade    = dijagonala + 400
korisnaZaKornize = dijagonala − 100 − 70
```
`korisnaZaKornize` je rastojanje od prvog do poslednjeg kedera. K1 stoji na
100 mm od prednje ivice, rezerva uz zid je 470 mm (400 + 70).

### 3.5 Razmak korniša (kedera)

**Auto-uklapanje** (`suggestIntervala`) bira broj intervala koji daje razmak
najbliži **449 mm**, u opsegu **399–499 mm**:
```
za n = 1..40:
  r = korisnaZaKornize / n
  ako je 399 ≤ r ≤ 499  → score = |r − 449|
  ako je r > 499        → score = 100000 + (r − 499)
  ako je r < 399        → score = 100000 + (399 − r) × 2
bira se n sa najmanjim score
```

**Normalan režim** (`fiksniRazmak = 0`):
```
brIntervala   = auto ? predlogIntervala : ručni unos
razmakKornisa = korisnaZaKornize / brIntervala
poljeKecelja  = razmakKornisa
```

**Spojene pergole** (`fiksniRazmak > 0`) — kad dve ili tri pergole različitih
dubina stoje jedna do druge, korniši moraju vizuelno da budu u liniji:
```
brIntervala   = max(1, ceil(korisnaZaKornize / fiksniRazmak))
razmakKornisa = fiksniRazmak
poljeKecelja  = korisnaZaKornize − (brIntervala − 1) × fiksniRazmak
```
Razmaci idu **od K1 (od fronta)**, a razlika se uklapa u **poslednje polje pre
kecelje**, koje sme da bude manje od ostalih.

```
razmakOk       = 399 ≤ razmakKornisa ≤ 499
kornisaUkupno  = brIntervala + 1
```

### 3.6 Pozicije kedera
Mereno od **prednje ivice cerade** (K1 = front/kecelja, poslednji = uz zid):
```
K1                    → 100
poslednji             → 100 + korisnaZaKornize
srednji, fiksni režim → 100 + (i−1) × fiksniRazmak
srednji, normalan     → 100 + (i−1) × korisnaZaKornize / (kornisaUkupno−1)
```
Ovaj niz (`kederPozicije`) je **jedinstven izvor** za mapu cerade, sastav rolni
i prikaz u nalogu.

### 3.7 Podela korniša na tipove
```
debeli      = 2                          (uvek — jedan uz zid, jedan uz oluk)
tanki       = max(0, kornisaUkupno − 2)
tankiSvetlo = ceil(tanki / 2)            (svaki drugi ima LED)
tankiBez    = tanki − tankiSvetlo
```

### 3.8 Dužina korniša ⭐

Sečeni korniš ima **po jedan komad u svakom polju**. Svi umetci su po **50 mm**
i ulaze u širinu pergole.

```
n = kornisKomPoPoz = sečeni ? max(2, brPolja) : 1

umetakaUDuzini = obostrani   ? n + 1     (2 ugaona + (n−1) srednjih)
                 jednostrani ? n − 1     (samo srednji — ugaoni ne skraćuju korniš)

duzKornisa      = (D − 50 × umetakaUDuzini) / n
srednjihUmetaka = n − 1        (konektora po jednoj poziciji korniša)
```

**Kontrola:** `n × duzKornisa + 50 × umetakaUDuzini = D` (do 1–2 mm zaokruženja)

Razvijeno po slučajevima:

| | Jednodelni (n=1) | Sečeni 3 raja (n=2) | Sečeni 4 raja (n=3) | Sečeni 5 raja (n=4) |
|---|---|---|---|---|
| **Jednostrani** | `D` | `(D−50)/2` | `(D−100)/3` | `(D−150)/4` |
| **Obostrani** | `D−100` | `(D−150)/2` | `(D−200)/3` | `(D−250)/4` |

**Primer 9450 mm, obostrani, 4 raja → 3 polja:**
`50 + 3083 + 50 + 3083 + 50 + 3083 + 50 = 9450` ✓

**Pravila izbora:**
- Sečeni je moguć **samo od 3 raja naviše**
- Obostrani + ≥3 raja → sečeni je **obavezan** (jednodelni se disabluje u formi)

### 3.9 Ostali profili
```
duzOluka     = D + 150
limKom       = ceil(D / 1250)              trapezni lim 125×80
duzStuba     = max(0, C − visinaZidića)
brPanela     = brPolja
duzPanela    = sirinaPolja + 100

cev motora, jednodelna:  duzCevMotora = D + 100        brCevMotora = 1
cev motora, po poljima:  duzCevMotora = ceil((D+50)/brPolja)   brCevMotora = brPolja
```

### 3.10 Spajanje cerade
Nijedna rolna ne sme biti duža od **3000 mm**. Sastav uvek pada na keder.

```
spliceNeeded = duzCerade > 3000
start = 0
dok je (duzCerade − start) > 3000:
    traži NAJVEĆI i takav da je kederPozicije[i] > start
                              i kederPozicije[i] ≤ start + 3000
    ako ne postoji → spliceOk = false, prekid
    zabeleži sastav na toj poziciji
    start = ta pozicija
rolne = razmaci između uzastopnih sastava + ostatak do kraja
```

### 3.11 LED bušenje
Korniš se deli na `n` jednakih segmenata, svetlo ide u **centar svakog**:
```
pozicija k = duzKornisa × (2k − 1) / (2n)        k = 1..n
razmakRupa    = duzKornisa / n
odIviceDoRupe = pozicija prve rupe = razmak / 2
svetalaUkupno = svetalaPoKornizu × tankiSvetlo
```
Pozicije se računaju direktno iz formule (ne sabiranjem zaokruženih vrednosti),
pa nema nagomilavanja greške.

### 3.12 Rupe za kolica
Buše se **samo kod jednostranog jednodelnog** korniša (`brRaja ≥ 2`):
```
m = 45 mm od svake ivice
2 raja:  [45, duzKornisa − 45]
3+ raja: [45, ... ravnomerno ..., duzKornisa − 45]
         srednje: 45 + k × (duzKornisa − 90) / (brRaja − 1),  k = 1..brRaja−2
```
- **Sečeni jednostrani** → skica se ne crta (svaki komad se buši na jednu stranu,
  srednja rupa ide u konektor)
- **Obostrani** → korniš se uopšte ne buši, kolica idu kroz rupe u umetcima

### 3.13 Trafo
```
potrošnja = svetalaUkupno × 0,34 A

≤ 10 A → trafo 10A, cena 15 €
≤ 20 A → trafo 20A, cena 22 €
≤ 30 A → trafo 30A, cena 30 €
> 30 A → ⚠ upozorenje
```

---

## 4. LISTA ZA SEČENJE — `aluCalc(I, R)`

Osam pozicija. Sve količine se na kraju množe sa `brKomada`.

| # | Pozicija | Profil | Mera | Komada |
|---|---|---|---|---|
| 1 | Šina (raj) | po širini raja | `duzRaja` | `brRaja` |
| 2 | Oluk | 10468 | `D + 150` | 1 |
| 3 | Stub | po izboru | `duzStuba` | `brStubova` |
| 4 | Cev motora | 10417 | `duzCevMotora` | `brCevMotora` |
| 5 | Panel | 10420 | `sirinaPolja + 100` | `brPolja` |
| 6 | Debeli korniš (ivični) | po kederu | `duzKornisa` | `2 × n` |
| 7 | Tanki korniš — sa svetlom | po kederu | `duzKornisa` | `tankiSvetlo × n` |
| 8 | Tanki korniš — bez svetla | po kederu | `duzKornisa` | `tankiBez × n` |

**Izbor profila:**
```
raj:    75 → STR75RAJ · 90 → 10501 · 110 → 10475
stub:   110x144 → 10419 · 100x100 → 10414
korniš: jednostrani → 10472 (debeli) + 10471 (tanki)
        obostrani   → 10416 (debeli) + 10418 (tanki)
```

**Težina i cena po poziciji:**
```
metaraUk = (mera / 1000) × komada
kg       = metaraUk × kg_po_metru
cena     = kg × 6,00 €/kg
```

### Težine profila (kg/m) — iz NOR proforme
| Šifra | Naziv | kg/m |
|---|---|---|
| 10501 | Šina 90×150 | 4,129 |
| 10475 | Šina 110×150 | 7,116 |
| 10468 | Oluk | 4,006 |
| 10419 | Stub 110×144 | 2,927 |
| 10414 | Stub 100×100 | 2,227 |
| 10471 | Tanki korniš | 0,809 |
| 10472 | Debeli korniš | 1,491 |
| 10418 | Tanki korniš obostrani | 0,737 |
| 10416 | Debeli korniš obostrani | 1,288 |
| 10470 | Tanki stringcourse / konektor 10471 | 0,590 |
| 10515 | Konektor debelog korniša 10472 | 1,161 |
| 10420 | Panel | 1,355 |
| 10417 | Cev motora | 1,071 |
| STR75RAJ | Raj 75×150 | — |

---

## 5. OKOV — `applyOkov(R, I)` i `KATALOG`

Svaka stavka ima ili **`pravilo`** (množi se sa bazom) ili **`fn(c)`** (svoja formula).
Sve količine se na kraju množe sa `brKomada`.

**Baze za `pravilo`:**
```
raj    → brRaja
stub   → brStubova
kornis → kornisaUkupno
svetlo → svetalaUkupno
fiksno → 1
```

**Kontekst `c` dostupan formulama:**
`raja` · `stubova` · `polja` · `kornisaUkupno` · `debeli` · `tanki` · `tankiSvetlo` ·
`svetla` · `cevMotora` · `duzRaja` · `duzOluka` · `duzKornisa` · `sirina` ·
`sirinaCerade` · `kornisSeceni` · `srednjihUmetaka` · `keder` · `kederJednostrani` ·
`korisnaCerade`

### 5.1 Plastika
| Šifra | Naziv | Količina | €/kom |
|---|---|---|---|
| ARV-01 | Velike plastike za zupčanik | × raja | 1,30 |
| ARV-02 | Veliki zupčanik | × raja | 0,70 |
| ARV-03 | Kočnica | × raja | 0,20 |
| ARV-04 | Prednji zatezač L/D | × raja | 1,15 |
| ARV-05 | Komplet za prednji ležaj | × raja | 0,60 |
| ARV-07 | Plastika spoja kaiša | × raja | 0,16 |
| ARV-08 | Velika kolica | × raja | 0,75 |
| ARV-09 | Mala kolica | `tanki × raja` | 0,55 |
| ARV-06 | Odvod vode oluka | × stubova | 0,30 |
| ARV-19 | Ugaoni umetak tankog korniša | `tanki × 2` | 0,17 |
| ARV-20 | Srednji umetak tankog korniša | `sečeni ? tanki × srednjihUmetaka : 0` | 0,17 |
| ARV-21 | Ugaoni umetak debelog korniša | `debeli × 2` | 0,20 |
| ARV-22 | Srednji umetak debelog korniša | `sečeni ? debeli × srednjihUmetaka : 0` | 0,20 |
| ARV-T | Tapa (poklopac rupe 20mm) | `jednostrani ? kornisaUkupno × raja : 0` | 0,10 |
| ARV-D | Tipla | `jednostrani ? kornisaUkupno × raja : 0` | 0,10 |
| PRG-117 | Veliki ležaj | `2 × raja` | 0,70 |
| PRG-119 | Mali ležaj | `2 × raja` | 0,50 |

### 5.2 Metal
| Šifra | Naziv | Količina | €/kom |
|---|---|---|---|
| ARV-M1 | Zidni nosač šine | × raja | 2,63 |
| ARV-M2a | Zadnji spoj šine — sa rupom | `max(0, raja × 2 − 1)` | 2,31 |
| ARV-M2b | Zadnji spoj šine — bez rupe | 1 (fiksno) | 2,27 |
| ARV-M4 | Fiksator motora na šinu | 1 (fiksno) | 0,80 |
| ARV-M5 | Podloška fiksatora motora | 1 (fiksno) | 0,40 |
| ARV-M6 | Spoj šina–veliki oluk | `stubova × 2` | 0,59 |
| ARV-M7 | Spoj stub–oluk | × stubova | 1,51 |
| ARV-M8 | Spoj rasponke i raja | `polja × 2` | 1,00 |
| ARV-M10 | Prednji zatezač automat | × raja | 0,75 |
| ARV-M11 | Prednji poklopac šine | × raja | 4,93 |
| ARV-M12 | Donja stopa stuba | × stubova | 5,20 |
| ARV-M13 | Bočni poklopac oluka L+D | 2 (fiksno) | 0,75 |

### 5.3 Fitilji i kederi (u metrima)
| Šifra | Naziv | Formula | €/m |
|---|---|---|---|
| PRG-102 | Fitilj / keder šine | `raja × duzRaja / 1000 × 2` | 0,35 |
| PRG-104 | Fitilj / keder oluka | `duzOluka / 1000` | 0,35 |
| PRG-101 | Fitilj / keder cerade — korniš | `kornisaUkupno × sirinaCerade / 1000` | 0,35 |
| PRG-103 | Fitilj kabla | `2 × korisnaCerade / 1000` | 0,32 |
| PRG-105 | **T10×16 zupčasti kaiš** | `raja × (2 × duzRaja + 500) / 1000` | 1,55 |

### 5.4 LED
| Šifra | Naziv | Formula | Cena |
|---|---|---|---|
| PRG-108 | 24V horizontalni LED | × svetalaUkupno | 0,52 €/kom |
| PRG-116 | 2×0,75 kabl | `sirina/1000 × kornisaUkupno + 5` | 0,13 €/m |

---

## 6. OBRAČUN — `obracunCalc(I, R, alu, okov)`

### Konstante cena
```
ALU_PRICE_PER_KG      = 6,00 €/kg
CERADA_PER_M2         = 5,00 €/m²
MOTOR_PRICE           = 70 €
DALJINSKI_PRICE       = 15 €
DIMER_PRICE           = 25 €
SRAFOVI_PAUSAL        = 35 €
RSD_PER_EUR           = 117
TRAPEZ_LIM_PER_M2_EUR = 900 RSD  ≈ 7,69 €/m²
CELIK_CEV_PER_M_EUR   = 1100 RSD ≈ 9,40 €/m
```

### Stavke zbira
```
m²cerade   = sirinaCerade × duzCerade / 1.000.000 × brKomada
ceradaCena = m²cerade × 5

cevNaZiduM   = (cev ≠ nema) ? D/1000 × brKomada : 0
cevNaZiduEur = cevNaZiduM × 9,40

trapezniLimKom = limKom × brKomada
trapezniLimM2  = kom × 1,25 × 0,80          (1 komad = 1 m²)
trapezniLimEur = m² × 7,69

elektronika = (motor + daljinski + dimer + trafo) × brKomada + LED iz kataloga
čelična     = Σ(kom × dužina)/1000 × brKomada × 9,40
šrafovi     = 35 × brKomada

SUBTOTAL = alu + cerada + plastika + metal + fitilj +
           cevNaZidu + trapezniLim + elektronika + šrafovi + čelična
```
> Subtotal je **samo trošak materijala** — marža, rad, montaža, profit i PDV se
> dodaju posebno. To piše i kao upozorenje u samom obračunu.

---

## 7. BROJ KOMADA (`brKomada`)

Jedan nalog za više **identičnih** pergola. Množi se **sve**: alu pozicije, okov,
fitilji, LED, cerada, motor/daljinski/dimer/trafo, lim, cev na zidu, čelična
konstrukcija, šrafovi. Skidanje sa magacina takođe uzima n×.

Mesta gde se množenje primenjuje: `aluCalc` (nad `it.kom`), `applyOkov` (nad `kol`),
`obracunCalc` (cerada, elektronika, lim, cev, čelična, paušal).

---

## 8. MAGACIN I OPTIMIZACIJA SEČENJA

### `planirajSecenje(trazeni, magacin)` — BEST FIT DECREASING

```
1. Grupiši tražene rezove po šifri profila
2. Sortiraj rezove opadajuće (najveći prvi)
3. Za svaki rez c:
     a) nađi VEĆ OTVORENU šipku sa najmanjim ostatkom (remaining − c ≥ 0)
     b) nađi NOVU šipku sa stanja sa najmanjim ostatkom (len − c ≥ 0)
     c) uzmi onu sa manjim ostatkom
        (pri izjednačenju → koristi već otvorenu, da se ne troši cela šipka)
     d) ako nijedna ne staje → upiši u "nedostaje"
4. Za svaku otvorenu šipku: ako je ostatak ≥ prag → vraća se na stanje
```

**Zašto best-fit:** raniji algoritam je uzimao najveću šipku prvo, pa je rez od
5500 sekao iz šipke 7500 (2000 otpada) iako je 5500 bila na stanju. Sada uzima 5500.

**Pragovi otpada** (ispod toga je otpad, iznad se vraća na stanje):
```
podrazumevano                          2000 mm
10501, 10475, 10419, 10414 (šine/stubovi)  1500 mm
```

### Struktura stanja
Magacin čuva po šifri **listu pojedinačnih komada** sa dužinama (ne samo ukupnu
količinu), pa optimizacija zna tačno kojim šipkama raspolaže.

---

## 9. BACKEND API

`https://app.aggroup.rs/api` · zaglavlje `X-Api-Key: ag2025app`
(mora se poklapati sa `api/config.php`)

### `nalozi.php`
| Akcija | Metod | Opis |
|---|---|---|
| `list` | GET | aktivni nalozi (`?status=proizvodnja` za puštene) |
| `get&id=` | GET | jedan nalog sa svim podacima |
| `save` | POST | novi ili update (`id > 0` = update) |
| `kalendar` | GET | svi nalozi + `rok`/`kupac`/`radnik`/`dim` iz `input_data` |
| `status` | POST | promena statusa (`aktivan`/`proizvodnja`/`isporucen`/`otkazan`) |
| `delete` | POST | soft delete → `status = otkazan` |

### `magacin.php`
`get` · `set` · `setall` · `korekcija` · `proizvodnja` (skidanje sa stanja) · `log`

### `artikli.php`
`list` · `add` · `delete`

### Tabele
```
radni_nalozi (id, klijent, napomena, status, input_data JSON, result_data JSON,
              created_at, updated_at)
magacin      (sifra PK, tip, komadi JSON, ...)
magacin_log  (id, sifra, akcija, detalji JSON, vreme)
```

> `result_data` postoji u bazi ali se trenutno **upisuje kao `null`** — arhiva
> pamti samo ulazne mere, pa se stari nalozi preračunavaju aktuelnim formulama.
> Ako se formula promeni, stari nalog prikazaće drugačije mere od onih po kojima
> je stvarno rađen.

---

## 10. ARHIVA, STATUSI, KALENDAR

**`ARCH_FIELDS`** — lista ID-jeva polja koja se čuvaju u nalog:
```
sirina, dubina, zadnjaVisina, prednjaVisina, brKomada, cev, stub, sirinaRaja,
kederTip, kornisSklop, cevMotora, bojaRal, bojaCerade, autoInterval, brIntervala,
fiksniRazmak, svetalaPoKornizu, motor, celicnaOn, parapetOn, parapetVisina,
brNaloga, kupac, adresa, datum, rok, radnik
```
> ⚠ **Svako novo polje u formi mora da se doda ovde**, inače se ne pamti u nalogu.

**Statusi:** `aktivan` (U pripremi) → `proizvodnja` (U produkciji) → `isporucen`
(Isporučeno). Plus `otkazan` = soft delete.

**Kalendar** grupiše naloge po polju `rok`; boje: bronza = u pripremi, zelena =
u produkciji, siva precrtana = isporučeno, narandžasti okvir = probijen rok.
Nalozi bez roka idu u posebnu sekciju ispod kalendara.

**Offline:** ako nema neta, `saveOrder` piše u `localStorage` pod ključem `ag_arhiva`.

### Kompatibilnost sa starim nalozima
`loadOrder` mapira stare formate:
```
keder + kornisTip  →  kederTip + kornisSklop
kornisVrsta (npr. "JOS")  →  isto
brKomada nedostaje    →  1
fiksniRazmak nedostaje →  0
```

---

## 11. HRONOLOGIJA VAŽNIH ISPRAVKI

Redom, sa razlogom — korisno da se ne vrati stara greška:

| Verzija | Izmena |
|---|---|
| .3 | Jednostrani jednodelni korniš = puna širina (bez −100 mm) |
| .5 | Cerada `D−20`, ali korniš i dalje po punoj širini `D` |
| .8 | Broj raja po formuli max 4150 mm (bilo hard-coded 3900/7800/10000) |
| .9 | Razmak korniša vraćen na 399–499 (bio greškom promenjen na 350–415) |
| .10 | Sečeni jednostrani `(D−50)/2`; skica rupa za kolica uklonjena kod sečenog |
| .11 | Optimizacija sečenja: best-fit umesto „najveća šipka prvo" |
| .12 | Cerada `D−10`; KONFIGURACIJA razdvojena na Keder + Sklop |
| .13 | `brKomada`; PRG-101 po punoj širini cerade |
| .14 | Spojene pergole — zadati razmak |
| .15 | Veće sličice + tap za uvećanje; RAL naziv i uzorak boje |
| .16 | Zadati razmak ide **od K1**, ostatak u poslednje polje |
| .17 | **Sečeni korniš: po jedan komad u svakom polju** (bilo fiksno 2) |

---

## 12. OTVORENA PITANJA I PLAN

### Nerazrešeno
1. **Opseg pada 8–10 %** — pali upozorenje u svakoj realnoj konfiguraciji.
   Treba utvrditi koliko pad realno iznosi na vašim pergolama.
2. **Visina cevi na zidu u `kat1`** — razmatrano dodavanje visine cevi
   (100 mm za 100×100, 80 mm za 80×40) jer se zadnja visina meri do donje ivice
   cevi. Analiza urađena, **odloženo** dok se ne razreši pitanje pada.
3. **`result_data`** se ne upisuje — stari nalozi se preračunavaju.

### Plan (redosled po korisnosti)
1. **Interaktivno štikliranje** — radnik na telefonu štiklira stavke sečenja i
   pakovanja, status se čuva na serveru, kancelarija vidi napredak
2. **QR kod na štampanom nalogu** — skenira se i otvara nalog na telefonu
3. **Minimalne zalihe** — prag po profilu, upozorenje „naručiti"
4. **Zbirna nabavka** — ukupne količine za sve naloge u pripremi vs. stanje
5. **Dupliranje naloga** — kopiraj postojeći i promeni samo mere

---

## 13. RAD NA KODU

**Grane:** rad ide na `claude/fix-old-code-display-Qu8fE`. Grana `main` je
zastarela (48 commit-ova iza, bez `api/` foldera i bez `APP_VER`).

**Uz svaku izmenu:** `APP_VER` u `index.html` + `CACHE_NAME` u `sw.js`, pa upload
oba fajla.

**Provera sintakse bez browsera:**
```bash
node -e "const h=require('fs').readFileSync('index.html','utf8');
[...h.matchAll(/<script>([\s\S]*?)<\/script>/g)].forEach((m,i)=>{
  try{new Function(m[1])}catch(e){console.log(i,e.message)}}); console.log('OK')"
```

**Izolovano testiranje proračuna:**
```bash
node -e "
const h=require('fs').readFileSync('index.html','utf8');
const s=[...h.matchAll(/<script>([\s\S]*?)<\/script>/g)].map(m=>m[1]);
const blk=s.find(x=>x.includes('function compute('));
global.document={getElementById:()=>null,querySelectorAll:()=>[]};
new Function(blk+'; global.__c=compute;')();
console.log(global.__c({sirina:'9450',dubina:'1750',zadnjaVisina:'2800',
  prednjaVisina:'2500',cev:'80x40',stub:'100x100',sirinaRaja:'90',
  kederTip:'obostrani',kornisSklop:'seceni',cevMotora:'jednodelna',
  autoInterval:true,fiksniRazmak:'0',svetalaPoKornizu:'6',brKomada:'1'}).kornis);
"
```

**Pravila:** sve je u jednom fajlu — pre izmene `grep` da li se vrednost koristi
na više mesta (opsezi se pojavljuju i u labelama forme i u napomenama naloga).
Poruke ka korisniku, komentari i tekst naloga — **na srpskom**.
