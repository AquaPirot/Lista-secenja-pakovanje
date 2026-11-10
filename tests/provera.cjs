/* AG GROUP — automatska provera proračuna pergole.
   Pokreće se posle SVAKE izmene u index.html:

     node tests/provera.cjs            → proverava pravila + poredi sa referencom
     node tests/provera.cjs --snimi    → snima novu referencu (samo kad je izmena NAMERNA)

   1) PRAVILA — moraju da važe za svaku kombinaciju mera (polje do 4000, sklop korniša
      daje punu širinu, rolne do 3000, razmak korniša u opsegu, K1 na 50 mm…).
   2) REFERENCA — tests/referenca.json čuva rezultat za kontrolne pergole; svaka razlika
      se ispisuje, pa se vidi da li je izmena promenila nešto što nije trebalo. */
const path = require('path'), fs = require('fs');
const { chromium } = require('playwright');
const KOREN = path.resolve(__dirname, '..');
const REF = path.join(__dirname, 'referenca.json');
const snimi = process.argv.includes('--snimi');

const osnova = { zadnjaVisina: 2800, prednjaVisina: 2450, brKomada: 1, cev: '80x40', stub: '110x144',
  sirinaRaja: 90, olukTip: '10468', kederTip: 'obostrani', kornisSklop: 'jednodelni', cevMotora: 'jednodelna',
  bojaRal: '7016', bojaCerade: 'Antracit', autoInterval: true, brIntervala: 7, fiksniRazmak: 0,
  svetalaPoKornizu: 5, motor: '', celicnaOn: false, parapetOn: false, parapetVisina: '', rajMod: 'auto', brRajaRucno: 2 };

// Mreža za pravila
const mreza = [];
for (const D of [2500, 3500, 4000, 4001, 4100, 6000, 8000, 8001, 9450, 12000, 12500])
  for (const B of [2500, 3500, 4500, 6000])
    for (const kederTip of ['obostrani', 'jednostrani'])
      for (const kornisSklop of ['jednodelni', 'seceni'])
        for (const sirinaRaja of [70, 75, 90, 110])
          mreza.push({ ...osnova, sirina: D, dubina: B, kederTip, kornisSklop, sirinaRaja });
for (const st of [0, 2, 4, 5]) for (const D of [3500, 6000, 9450])
  mreza.push({ ...osnova, sirina: D, dubina: 4000, stubMod: 'rucno', brStubovaRucno: st, kornisSklop: 'seceni' });
for (const fr of [400, 425, 430, 450, 480]) for (const B of [3000, 4000, 5000])
  mreza.push({ ...osnova, sirina: 6000, dubina: B, fiksniRazmak: fr, kornisSklop: 'seceni' });

// Kontrolne pergole za referencu (dodavati stvarne naloge koje su majstori potvrdili)
const kontrolne = {
  'mala 3500×3500 obostrani': { ...osnova, sirina: 3500, dubina: 3500, zadnjaVisina: 2650, prednjaVisina: 2150 },
  '4100 → 3 raja': { ...osnova, sirina: 4100, dubina: 3500, kornisSklop: 'seceni' },
  '6000×4000 obostrani sečeni': { ...osnova, sirina: 6000, dubina: 4000, zadnjaVisina: 2700, prednjaVisina: 2480, kornisSklop: 'seceni' },
  '6000×4000 ručno 4 raja': { ...osnova, sirina: 6000, dubina: 4000, zadnjaVisina: 2700, prednjaVisina: 2480, kornisSklop: 'seceni', rajMod: 'rucno', brRajaRucno: 4 },
  '9450 obostrani 4 raja': { ...osnova, sirina: 9450, dubina: 4500, kornisSklop: 'seceni' },
  '9450 jednostrani jednodelni': { ...osnova, sirina: 9450, dubina: 4500, kederTip: 'jednostrani' },
  '7000 jednostrani sečeni ×2 kom': { ...osnova, sirina: 7000, dubina: 4000, kederTip: 'jednostrani', kornisSklop: 'seceni', brKomada: 2 },
  'raj 70 + oluk 10477': { ...osnova, sirina: 5000, dubina: 3500, sirinaRaja: 70, olukTip: '10477', kornisSklop: 'seceni' },
  'raj 75': { ...osnova, sirina: 3800, dubina: 4000, sirinaRaja: 75 },
  'raj 110 stub 100': { ...osnova, sirina: 8000, dubina: 6000, sirinaRaja: 110, stub: '100x100', kornisSklop: 'seceni' },
  'spojene 425': { ...osnova, sirina: 6000, dubina: 4000, zadnjaVisina: 2700, prednjaVisina: 2480, fiksniRazmak: 425, kornisSklop: 'seceni' },
  'ručno 2 stuba na 3 raja': { ...osnova, sirina: 6000, dubina: 4000, kornisSklop: 'seceni', stubMod: 'rucno', brStubovaRucno: 2 },
  'ručno 4 stuba na 3 raja': { ...osnova, sirina: 6000, dubina: 4000, kornisSklop: 'seceni', stubMod: 'rucno', brStubovaRucno: 4 },
  'zidić 500 bez cevi': { ...osnova, sirina: 4000, dubina: 3000, parapetOn: true, parapetVisina: 500, cev: 'nema' },
};

(async () => {
  const b = await chromium.launch();
  const p = await b.newPage();
  const greske = [];
  p.on('pageerror', e => greske.push('JS greška: ' + e.message));
  await p.addInitScript(() => { try { localStorage.setItem('ag_pin_do', String(Date.now() + 1e9)); } catch (e) {} });
  await p.goto('file://' + path.join(KOREN, 'index.html'));
  await p.waitForTimeout(800);

  // 1) PRAVILA
  const pr = await p.evaluate(cases => {
    const out = [];
    const jeste = (uslov, I, poruka) => { if (!uslov) out.push(`${I.sirina}×${I.dubina} raj${I.sirinaRaja} ${I.kederTip}/${I.kornisSklop}${I.fiksniRazmak ? ' fiksni ' + I.fiksniRazmak : ''}: ${poruka}`); };
    for (const I0 of cases) {
      const I = { ...I0 };
      let R = compute(I);
      // isto što radi forma: sečeni tek od 3 raja, obostrani + 3 raja → obavezno sečeni
      if (R.auto.brRaja < 3 && I.kornisSklop === 'seceni') I.kornisSklop = 'jednodelni';
      if (R.auto.brRaja >= 3 && I.kederTip === 'obostrani') I.kornisSklop = 'seceni';
      R = compute(I);
      const a = aluCalc(I, R), o = applyOkov(R, I), ob = obracunCalc(I, R, a, o);
      const D = R.in.D, c = R.cerada, k = R.kornis;
      jeste((I.stubMod === 'rucno' || R.auto.brRaja === R.auto.brStubova) && R.auto.brPolja === R.auto.brRaja - 1, I, 'raja/stubova/polja se ne slažu');
      if (I.stubMod === 'rucno') jeste(R.auto.brStubova === +I.brStubovaRucno && a.items.find(x => x.key === 'stub').kom === +I.brStubovaRucno * Math.max(1, +I.brKomada || 1), I, 'ručni broj stubova nije primenjen');
      jeste(D / R.auto.brPolja <= 4000, I, `polje ${Math.round(D / R.auto.brPolja)} > 4000`);
      jeste(R.auto.brRaja === Math.max(2, Math.ceil(D / 4000) + 1), I, `broj raja ${R.auto.brRaja}`);
      jeste(R.raj.duzRaja === R.raj.dijagonala - 200, I, 'dužina raja ≠ dijagonala − 200');
      jeste(c.sirinaCerade === D - 10 && c.duzCerade === R.raj.dijagonala + 400, I, 'mere cerade');
      jeste(c.kederPozicije[0] === 50, I, `K1 na ${c.kederPozicije[0]}, treba 50`);
      jeste(c.duzCerade - c.kederPozicije[c.kederPozicije.length - 1] === 470, I, 'rezerva uz zid ≠ 470');
      jeste(c.kederPozicije.every((x, i, n) => i === 0 || x > n[i - 1]), I, 'kederi nisu rastući');
      jeste(c.kederPozicije.length === c.kornisaUkupno && c.kornisaUkupno === c.brIntervala + 1, I, 'broj kedera');
      if (!c.fiksniOn) jeste(c.razmakKornisa >= 399 && c.razmakKornisa <= 499, I, `razmak ${c.razmakKornisa} van 399–499`);
      else jeste(c.poljeKecelja >= 200 || c.brIntervala === 1, I, `poslednje polje ${c.poljeKecelja} < 200`);
      const n = k.kornisKomPoPoz, zbir = n * k.duzKornisa + 50 * k.umetakaUDuzini;
      jeste(Math.abs(zbir - D) <= n, I, `sklop korniša ${zbir} ≠ širina ${D}`);
      if (R.splice.spliceNeeded && R.splice.spliceOk) {
        jeste(R.splice.spliceRolne.every(x => x <= 3000), I, 'rolna preko 3000: ' + R.splice.spliceRolne.join('+'));
        jeste(R.splice.spliceRolne.reduce((s, x) => s + x, 0) === c.duzCerade, I, 'zbir rolni ≠ dužina cerade');
        jeste(R.splice.spliceList.every(s => c.kederPozicije.includes(s.pos)), I, 'sastav nije na kederu');
      }
      if (c.duzCerade > 3000) jeste(R.splice.spliceNeeded, I, 'cerada > 3000 a nema sastava');
      jeste(a.items.every(x => x.mera > 0 && x.kom >= 0 && x.prof && x.prof.kgm > 0), I, 'alu stavka bez mere ili težine');
      jeste(o.every(x => isFinite(x.kol) && x.kol >= 0), I, 'okov: neispravna količina');
      jeste(isFinite(ob.subtotal) && ob.subtotal > 0, I, 'obračun: neispravan zbir');
      const ledN = R.led.ledPozicije.length;
      jeste(ledN === R.led.svetalaPoKornizu && R.led.ledPozicije.every(x => x > 0 && x < k.duzKornisa), I, 'LED rupe van korniša');
    }
    return out;
  }, mreza);

  // 2) REFERENCA
  const sad = await p.evaluate(K => {
    const out = {};
    for (const ime in K) {
      const I = K[ime], R = compute(I), a = aluCalc(I, R), o = applyOkov(R, I), ob = obracunCalc(I, R, a, o);
      out[ime] = {
        raja: R.auto.brRaja, polje: R.auto.sirinaPolja, dijagonala: R.raj.dijagonala, duzRaja: R.raj.duzRaja, pad: R.raj.pad,
        cerada: R.cerada.sirinaCerade + '×' + R.cerada.duzCerade, kederi: R.cerada.kederPozicije.join(','),
        rolne: R.splice.spliceRolne.join('+'), kornis: R.kornis.duzKornisa + ' ×' + R.kornis.kornisKomPoPoz,
        led: R.led.ledPozicije.join(','), kolica: R.kolica.rupe.join(','),
        alu: a.items.map(x => `${x.prof.sifra}:${x.mera}×${x.kom}`).join(' '),
        okov: o.filter(x => x.kol > 0).map(x => `${x.sifra}:${x.kol}`).join(' '),
        vijci: vijcanaRoba(R, Math.max(1, +I.brKomada || 1)).lista.map(u => `${u.id}:${u.kol}`).join(' '),
        aluKg: ob.aluKg, ukupnoEur: ob.subtotal,
      };
    }
    return out;
  }, kontrolne);
  await b.close();

  let ok = true;
  console.log(`PRAVILA: ${mreza.length} kombinacija — ${pr.length ? pr.length + ' PREKRŠAJA' : 'sve u redu ✓'}`);
  pr.slice(0, 25).forEach(x => console.log('  ✗ ' + x));
  if (pr.length) ok = false;
  if (greske.length) { ok = false; greske.forEach(g => console.log('  ✗ ' + g)); }

  if (snimi || !fs.existsSync(REF)) {
    fs.writeFileSync(REF, JSON.stringify(sad, null, 1) + '\n');
    console.log(`REFERENCA: snimljena (${Object.keys(sad).length} kontrolnih pergola) → tests/referenca.json`);
  } else {
    const ref = JSON.parse(fs.readFileSync(REF, 'utf8'));
    let razl = 0;
    for (const ime of new Set([...Object.keys(ref), ...Object.keys(sad)])) {
      const r = ref[ime] || {}, s = sad[ime] || {};
      for (const polje of new Set([...Object.keys(r), ...Object.keys(s)])) {
        if (JSON.stringify(r[polje]) !== JSON.stringify(s[polje])) {
          razl++;
          console.log(`  ≠ [${ime}] ${polje}\n      bilo: ${r[polje]}\n      sada: ${s[polje]}`);
        }
      }
    }
    console.log(`REFERENCA: ${Object.keys(sad).length} kontrolnih pergola — ${razl ? razl + ' RAZLIKA (ako je izmena namerna: node tests/provera.cjs --snimi)' : 'bez razlika ✓'}`);
    if (razl) ok = false;
  }
  process.exit(ok ? 0 : 1);
})();
