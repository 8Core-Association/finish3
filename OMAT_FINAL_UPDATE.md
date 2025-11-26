# Omot Spisa - Finalne Izmjene

**Datum:** 2025-11-26
**Verzija:** A4 Format - Finalna verzija

---

## SAŽETAK IZMJENA

### 1. Akt Oznaka - Izmjena formata

**PRIJE:**
```
[0000-0-1]-[21]-[25]-[01]
```

**SADA:**
```
0000-0-1-21-25-01
```

**NAPOMENA:** Uklonjene uglate zagrade!

---

### 2. Prikaz Akta - Dodani podaci

**Format prikaza:**
```
1. 0000-0-1-21-25-01
   Dokument: "Odluka_o_prijemu.pdf"
   Datum kreiranja: 15.01.2025
```

**Dodano:**
- Naziv dokumenta (filename iz ECM)
- Datum kreiranja (datum_kreiranja iz llx_a_akti)
- Format datuma: `dd.mm.yyyy` (bez sata)

---

### 3. Prikaz Priloga - Nova struktura

**Format prikaza:**
```
   - Prilog ID: 101 | Datum dodavanja: 15.01.2025
     Zaprimanje: Od "Ministarstvo obrazovanja" dana 14.01.2025
     Otprema: Dostavljeno "Ured gradonačelnika" dana 20.01.2025
     Otprema: Dostavljeno "Državni zavod" dana 22.01.2025
```

**Dodano:**
- ID_priloga (iz llx_a_prilozi)
- Datum dodavanja (datum_kreiranja iz llx_a_prilozi)
- Zaprimanje: Od "naziv" dana dd.mm.yyyy
- Otprema: Dostavljeno "naziv" dana dd.mm.yyyy
- Format datuma: `dd.mm.yyyy` (bez sata)

**Napomena:** Ako prilog nema zaprimanja/otprema, ne prikazuje se ništa (čist prikaz).

---

### 4. Otprema/Zaprimanje za Akt

**Ako akt ima otpremu/zaprimanje, prikazuje se odmah ispod datuma kreiranja:**

```
1. 0000-0-1-21-25-01
   Dokument: "Odluka_o_prijemu.pdf"
   Datum kreiranja: 15.01.2025
   Otprema: Dostavljeno "Ministarstvo obrazovanja" dana 20.01.2025
   Zaprimanje: Od "Državni zavod" dana 14.01.2025
```

---

## BACKEND IZMJENE

### Fajl: `/seup/class/omat_generator.class.php`

#### **Izmijenjeni SQL upiti:**

1. **`getAktiWithRelations()`**
   - Dodano: `ORDER BY CAST(a.urb_broj AS UNSIGNED) ASC` - sortiranje po numeričkoj vrijednosti

2. **`getPriloziForAkt()`**
   - Dodano u SELECT: `p.ID_priloga`, `p.datum_kreiranja`
   - Dodano: `ORDER BY CAST(p.prilog_rbr AS UNSIGNED) ASC`

#### **Izmijenjene metode:**

1. **`generatePage2and3()`**
   - Dodano prikazivanje naziva dokumenta za akt
   - Dodano prikazivanje datuma kreiranja za akt
   - Dodano prikazivanje otprema/zaprimanja za akt (odmah ispod datuma)
   - Dodano prikazivanje ID_priloga i datuma dodavanja za prilog
   - Format datuma: `date('d.m.Y', strtotime($datum))` - BEZ sata
   - Tekst za otpremu: `"Otprema: Dostavljeno \"[naziv]\" dana [dd.mm.yyyy]"`
   - Tekst za zaprimanje: `"Zaprimanje: Od \"[naziv]\" dana [dd.mm.yyyy]"`

2. **`generateAktOznaka()`**
   - PRIJE: `sprintf('[%s]-[%s]-[%s]-[%s]', ...)`
   - SADA: `sprintf('%s-%s-%s-%s', ...)` - BEZ uglatih zagrada

3. **`generatePreviewHTML()`**
   - Ažuriran HTML za modal preview
   - Identična logika kao PDF generiranje
   - Dodani svi novi podaci (dokumenti, datumi, ID-ovi)

---

## CSS IZMJENE

Nisu potrebne dodatne izmjene - postojeći stilovi pokrivaju novu strukturu.

---

## PRIMJER KOMPLETNOG ISPISA

```
STRANICA 1:
-----------
Naziv tijela: Osnovna škola "Test"
Oznaka unutarnje ustrojstvene jedinice: 0000-0-1
Klasifikacijska oznaka: 001-01-11-25-01
Predmet: Prijam novih učenika za školsku godinu 2024/2025

[Mjesto za barkod]


STRANICA 2:
-----------
POPIS DOKUMENATA

1. 0000-0-1-21-25-01
   Dokument: "Odluka_o_prijemu.pdf"
   Datum kreiranja: 15.01.2025
   Otprema: Dostavljeno "Ministarstvo obrazovanja" dana 20.01.2025

   - Prilog ID: 101 | Datum dodavanja: 15.01.2025
     Zaprimanje: Od "Ministarstvo obrazovanja" dana 14.01.2025

   - Prilog ID: 102 | Datum dodavanja: 15.01.2025
     Otprema: Dostavljeno "Ured gradonačelnika" dana 20.01.2025
     Otprema: Dostavljeno "Državni zavod" dana 22.01.2025

2. 0000-0-1-21-25-02
   Dokument: "Rješenje.pdf"
   Datum kreiranja: 16.01.2025

   - Prilog ID: 103 | Datum dodavanja: 16.01.2025


STRANICA 3:
-----------
(nastavak ako treba, inače prazna)


STRANICA 4:
-----------
(prazna zadnja stranica)
```

---

## TESTIRANJE

Testirajte sljedeće scenarije:

1. **Akt bez priloga**
   - Treba prikazati samo oznaku, dokument, datum

2. **Akt s prilozima bez otprema/zaprimanja**
   - Prilog prikazuje samo ID i datum dodavanja

3. **Akt s prilozima koji imaju otpreme/zaprimanja**
   - Prilog prikazuje sve podatke (ID, datum, otpreme, zaprimanja)

4. **Akt s otpremom/zaprimanjem**
   - Otprema/zaprimanje se prikazuje odmah ispod datuma kreiranja

5. **Vise akata**
   - Sortiranje po urb_broj (numeričko)

6. **Vise priloga po aktu**
   - Sortiranje po prilog_rbr (numeričko)

7. **Vise otprema/zaprimanja po dokumentu**
   - Svaka otprema/zaprimanje nova linija

---

## FILES MODIFIED

1. `/seup/class/omat_generator.class.php`
   - Metode: `getAktiWithRelations()`, `getPriloziForAkt()`, `generatePage2and3()`, `generateAktOznaka()`, `generatePreviewHTML()`

2. `/OMAT_A4_CHANGELOG.md`
   - Ažuriran changelog s novim formatom

3. `/OMAT_FINAL_UPDATE.md` (NOVI)
   - Sažetak finalnih izmjena

---

**Implementirao:** Claude Code
**Datum:** 2025-11-26
**Status:** ✅ ZAVRŠENO
