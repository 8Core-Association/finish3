# Omot Spisa A4 - Changelog

## Datum: 2025-11-26

### Nova verzija: A4 Format (Portretna orijentacija)

---

## Implementirane Izmjene

### 1. Format omota promijenjen iz A3 u A4 portrait

**Prije:** A3 format (297 x 420 mm)
**Sada:** A4 format (210 x 297 mm), portretna orijentacija

---

### 2. Nova struktura dokumenta (4 stranice)

#### **Stranica 1 - Naslovnica**
- Naziv tijela (Pravne osobe/Ustanove)
- Oznaka unutarnje ustrojstvene jedinice (code_ustanova)
- Klasifikacijska oznaka (klasa predmeta)
- Predmet (naziv predmeta)
- Mjesto za barkod (predvidjeno za buducnost - opciono QR kod)

#### **Stranica 2 - Hijerarhijski popis dokumenata**
Format zapisa:
```
1. 0000-0-1-21-25-01
   Dokument: "Odluka_o_prijemu.pdf"
   Datum kreiranja: 15.01.2025
   Otprema: Dostavljeno "Ministarstvo obrazovanja" dana 20.01.2025

   - Prilog ID: 101 | Datum dodavanja: 15.01.2025
     Datoteka: "Prilog_1_CV.pdf"
     Kreirao: Ivan Horvat
     Zaprimanje: Od "Ministarstvo obrazovanja" dana 14.01.2025

   - Prilog ID: 102 | Datum dodavanja: 15.01.2025
     Datoteka: "Prilog_2_Dokument.pdf"
     Kreirao: Marko Marić
     Otprema: Dostavljeno "Ured gradonačelnika" dana 20.01.2025
     Otprema: Dostavljeno "Državni zavod" dana 22.01.2025

2. 0000-0-1-21-25-02
   Dokument: "Rješenje.pdf"
   Datum kreiranja: 16.01.2025
   Otprema: Dostavljeno "Ministarstvo" dana 18.01.2025
```

#### **Stranica 3 - Nastavak liste**
- Identična kao stranica 2
- Nastavak liste ako ima vise dokumenata
- Ako nema vise dokumenata → prazna stranica ali kreirana

#### **Stranica 4 - Prazna zadnja stranica**
- Dodana prazna stranica u ispis

---

### 3. Backend izmjene

#### **Fajl: `/seup/class/omat_generator.class.php`**

##### **Nove metode:**

1. **`getAktiWithRelations($predmet_id)`**
   - Dohvaca sve akte za predmet
   - Za svaki akt dohvaca: priloge, otpreme, zaprimanja

2. **`getPriloziForAkt($akt_id)`**
   - Dohvaca sve priloge za odredjeni akt
   - Za svaki prilog dohvaca: ID_priloga, prilog_rbr, datum_kreiranja, filename, created_by (firstname + lastname)
   - LEFT JOIN s llx_user preko fk_user_c
   - Za svaki prilog dohvaca: otpreme, zaprimanja

3. **`getOtpremeForDocument($ecm_file_id, $tip_dokumenta)`**
   - Dohvaca sve otpreme za dokument (akt ili prilog)

4. **`getZaprimanjaForDocument($ecm_file_id, $tip_dokumenta)`**
   - Dohvaca sve zaprimanja za dokument (akt ili prilog)

5. **`generateAktOznaka($predmetData, $urb_broj)`**
   - Generira akt oznaku u formatu: `code_ustanova-rbr_zaposlenika-godina-urb_broj` (BEZ uglatih zagrada)

##### **Izmijenjene metode:**

1. **`generateOmat()`**
   - Promijenjen format iz A3 na A4 portrait
   - Koristi nove metode za dohvacanje hijerarhijskih podataka

2. **`generatePage1()`**
   - Prilagodjen layout za A4 format
   - Pojednostavljena struktura
   - Dodan placeholder za barkod/QR kod

3. **`generatePage2and3()`**
   - Potpuno preradjena struktura
   - Prikaz za AKT: oznaka, naziv dokumenta, datum kreiranja, otpreme/zaprimanja
   - Prikaz za PRILOG: ID priloga, datum dodavanja, otpreme/zaprimanja
   - Hijerarhijski prikaz: Akt → Prilozi → Otpreme/Zaprimanja
   - Datumi bez sata (samo dd.mm.yyyy)
   - Automatski nastavak na stranicu 3 ako treba

4. **`generatePage4()`**
   - Pojednostavljena - samo prazna stranica

5. **`generatePreviewHTML()`**
   - Potpuno preradjen HTML za modal preview
   - A4 format preview s 4 stranice
   - Hijerarhijski prikaz dokumenata

6. **`generatePreview()`**
   - Azuriran za novi podatkovni model (akti umjesto attachments)

---

### 4. CSS izmjene

#### **Fajl: `/seup/css/setup-modal.css`**

**Dodani novi stilovi:**

```css
.seup-omat-preview - kontejner za preview
.seup-omat-page-a4 - A4 format stranice (595px x 842px)
.seup-omat-section - sekcije na naslovnici
.seup-omat-barcode - prostor za barkod
.seup-omat-title - naslov na stranici 2
.seup-omat-akt - stil za prikaz akta
.seup-omat-empty - poruka kad nema dokumenata
```

**Dimenzije A4 stranice:**
- Sirina: 595px
- Minimalna visina: 842px
- Padding: 40px (gore/dolje), 30px (lijevo/desno)

---

### 5. Baza podataka - struktura koju koristi omot

#### **Tablice:**

1. **`llx_a_predmet`** - Osnovni podaci o predmetu
2. **`llx_a_oznaka_ustanove`** - Podaci o ustanovi (name_ustanova, code_ustanova)
3. **`llx_a_interna_oznaka_korisnika`** - Podaci o korisniku (rbr, ime_prezime)
4. **`llx_a_akti`** - Akti vezani uz predmet (urb_broj)
5. **`llx_a_prilozi`** - Prilozi vezani uz akte (prilog_rbr)
6. **`llx_a_otprema`** - Otpreme dokumenata (primatelj_naziv, datum_otpreme)
7. **`llx_a_zaprimanja`** - Zaprimanja dokumenata (posiljatelj_naziv, datum_zaprimanja)

---

### 6. Akt Oznaka Format

**Format:** `code_ustanova-rbr_zaposlenika-godina-urb_broj`

**Primjer:** `0000-0-1-21-25-01`

**Komponente:**
- `code_ustanova` - Oznaka ustanove (npr. "0000-0-1")
- `rbr_zaposlenika` - Redni broj zaposlenika/korisnika (npr. "21")
- `godina` - Godina predmeta (dvoznamenkasta, npr. "25")
- `urb_broj` - Urudžbeni broj akta (dvoznamenkasti, npr. "01")

**NAPOMENA:** Bez uglatih zagrada!

---

## Compatibility

- Backend je potpuno kompatibilan s postojecim sustavom
- Frontend (modal) prikazuje nove A4 stranice
- PDF generator kreira A4 portrait dokument
- Sve postojece funkcionalnosti ostaju nepromijenjene

---

## Testing Notes

Testirati:
1. Generiranje omota za predmet s vise akata
2. Generiranje omota za predmet s prilozima
3. Generiranje omota za predmet s otpremama i zaprimanjima
4. Preview u modalu (4 stranice)
5. Download PDF-a (A4 format)
6. Pravilno pagination (automatski prelazak na stranicu 3)

---

## Database Queries

Nova verzija koristi vise JOIN-ova za dohvacanje hijerarhijskih podataka:
- Akti → Prilozi → Otpreme/Zaprimanja
- Sve informacije se dohvacaju u jednom pozivu za svaki nivo hijerarhije

---

## Future Improvements

1. **QR Kod generiranje** - Implementirati generiranje QR koda na osnovu klase
2. **Barkod generiranje** - Implementirati generiranje barkoda
3. **Printanje** - Dodati opciju za printanje direktno iz preview modala
4. **Export** - Dodati mogucnost export-a u druge formate (Word, Excel)

---

## Files Modified

- `/seup/class/omat_generator.class.php` - Backend logika
- `/seup/css/setup-modal.css` - CSS za A4 format preview

## Files NOT Modified (no changes needed)

- `/seup/class/predmet_action_handler.class.php` - vec ima handleGenerateOmot i handlePreviewOmot
- `/seup/pages/predmet.php` - vec ima action handlers za generate_omot i preview_omot
- Database schema - sve tablice vec postoje

---

**Implementirao:** Claude Code
**Datum:** 2025-11-26
