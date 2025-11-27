# Modul Obavijesti - SEUP Sustav

## Pregled

Modul obavijesti omogućuje administratorima slanje sistemskih obavijesti svim korisnicima SEUP sustava. Obavijesti se prikazuju kroz interaktivno zvonce s badge-om i slider na početnoj stranici.

## Značajke

### 1. **Bell notifikacija s animacijom**
   - Žuto zvonce u gornjem desnom kutu
   - Pulsiranje i shake animacija kada postoje nepročitane obavijesti
   - Badge s brojem nepročitanih obavijesti
   - Dropdown lista obavijesti na klik

### 2. **Slider obavijesti**
   - Prikazuje se između statistika na index stranici
   - Automatski carousel za multiple obavijesti
   - Gradient dizajn s navigacijom (strelice + točkice)
   - Gumb za zatvaranje koji automatski označava kao pročitano

### 3. **Admin panel**
   - Pristup: `/seup/pages/obavijesti_admin.php` (samo za admins)
   - CRUD operacije (Create, Read, Update, Delete)
   - Tipovi obavijesti: Info, Upozorenje, Tutorial
   - Vanjski linkovi (za tutoriale)
   - Aktivacija/deaktivacija obavijesti

### 4. **Tracking pročitanih obavijesti**
   - Database persistent tracking po korisniku
   - Auto-refresh svakih 60 sekundi
   - Označi sve kao pročitano funkcionalnost

### 5. **Sound notifikacija**
   - Zvučni efekt kada klikneš bell i imaš novih obavijesti
   - Placeholder sound file (zamijeniti s pravim .mp3)

## Datotečna struktura

```
seup/
├── sql/
│   └── llx_seup_obavijesti.sql          # Database schema
├── class/
│   ├── obavijesti_helper.class.php      # Helper klasa za CRUD operacije
│   └── autocomplete.php                  # AJAX endpoints (dodani novi)
├── pages/
│   └── obavijesti_admin.php             # Admin stranica za upravljanje
├── css/
│   └── obavijesti.css                   # Svi stilovi
├── js/
│   └── obavijesti.js                    # Bell logic, AJAX, animacije
├── sounds/
│   └── notification.mp3                 # Sound efekt (placeholder)
└── seupindex.php                        # Integracija bell + slider
```

## Database schema

### Tablica: `llx_seup_obavijesti`
- `rowid` - Primary key
- `naslov` - Naslov obavijesti (VARCHAR 255)
- `sadrzaj` - Sadržaj obavijesti (TEXT)
- `tip` - Tip: info/upozorenje/tutorial
- `vanjski_link` - Link za tutoriale (VARCHAR 512, nullable)
- `aktivan` - Status aktivnosti (TINYINT 0/1)
- `datum_kreiranja` - Timestamp kreiranja
- `datum_izmjene` - Timestamp posljednje izmjene
- `fk_user_kreirao` - ID korisnika koji je kreirao

### Tablica: `llx_seup_obavijesti_procitane`
- `rowid` - Primary key
- `fk_user` - ID korisnika
- `fk_obavijest` - ID obavijesti
- `datum_procitano` - Timestamp
- **UNIQUE KEY** na (fk_user, fk_obavijest)

## Instalacija

### 1. Pokrenuti SQL skriptu
```bash
mysql -u root -p dolibarr_db < seup/sql/llx_seup_obavijesti.sql
```

### 2. Provjeriti da su svi fajlovi na mjestu
- CSS: `seup/css/obavijesti.css`
- JS: `seup/js/obavijesti.js`
- Helper: `seup/class/obavijesti_helper.class.php`
- Sound: `seup/sounds/notification.mp3` (zamijeniti s pravim!)

### 3. Testirati admin pristup
- URL: `http://your-dolibarr/custom/seup/pages/obavijesti_admin.php`
- Login kao admin user
- Kreirati test obavijest

### 4. Provjeriti index stranicu
- URL: `http://your-dolibarr/custom/seup/seupindex.php`
- Zvonce bi trebalo biti vidljivo
- Slider bi se trebao prikazati ako ima aktivnih obavijesti

## Korištenje

### Kreiranje obavijesti (Admin)

1. Idi na `/seup/pages/obavijesti_admin.php`
2. Popuni formu:
   - **Naslov**: Kratak naslov
   - **Sadržaj**: Detaljan opis
   - **Tip**: Info/Upozorenje/Tutorial
   - **Vanjski link**: (opcionalno) za tutoriale
   - **Aktivna**: Checkbox za aktivaciju
3. Klikni "Kreiraj"

### Pregled obavijesti (Korisnici)

1. **Bell icon**:
   - Klikni na žuto zvonce u gornjem desnom kutu
   - Otvara se dropdown lista
   - Klikni na obavijest za označavanje kao pročitano

2. **Slider**:
   - Automatski se prikazuje na index stranici
   - Navigiraj strelicama ili točkicama
   - Zatvori (X) za označavanje kao pročitano

3. **Auto-refresh**:
   - Svakih 60 sekundi provjerava nove obavijesti
   - Bell automatski ažurira badge broj

## API Endpoints

### GET `/seup/class/autocomplete.php?action=get_obavijesti`
Vraća sve aktivne obavijesti za trenutnog korisnika.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "rowid": 1,
      "naslov": "Nova značajka",
      "sadrzaj": "Dodali smo novu značajku...",
      "tip": "info",
      "vanjski_link": null,
      "datum_kreiranja": "2025-01-15 10:30:00",
      "procitana": false
    }
  ]
}
```

### GET `/seup/class/autocomplete.php?action=mark_obavijest_read&id=1`
Označava obavijest kao pročitanu.

**Response:**
```json
{
  "success": true
}
```

### GET `/seup/class/autocomplete.php?action=mark_all_obavijesti_read`
Označava sve aktivne obavijesti kao pročitane.

**Response:**
```json
{
  "success": true
}
```

## Customizacija

### Promijeniti boju bell ikone
U `seup/css/obavijesti.css`:
```css
.obavijesti-bell-icon {
    color: #FFC107; /* Žuta - promijeni u bilo koju boju */
}
```

### Promijeniti auto-refresh interval
U `seup/js/obavijesti.js`:
```javascript
obavijestiBellCheckInterval = setInterval(function() {
    obavijestiBellLoadData();
}, 60000); // 60000 = 60 sekundi, promijeni prema potrebi
```

### Dodati pravi sound efekt
1. Zamijeni `/seup/sounds/notification.mp3` s pravim .mp3 fajlom
2. Testirati u browseru:
   ```javascript
   document.getElementById('obavijesti-bell-sound').play();
   ```

## Troubleshooting

### Bell se ne prikazuje
- Provjeri da li je `obavijesti.css` učitan u `seupindex.php`
- Provjeri browser konzolu za JS greške
- Provjeri da li postoji `<div class="obavijesti-bell-container">`

### Nema obavijesti u dropdown-u
- Provjeri da li postoje **aktivne** obavijesti u bazi
- Provjeri `/seup/class/autocomplete.php?action=get_obavijesti` u browseru
- Provjeri database connection

### Sound ne radi
- Browser može blokirati autoplay sound
- Testirati klik na bell - sound se pušta samo na user interaction
- Zamijeniti placeholder sound s pravim .mp3 fajlom

### Admin stranica ne radi (403 Forbidden)
- Provjeri da li si logiran kao **admin** user
- Provjeri `$user->admin` u Dolibarr-u

## Sigurnost

- **Admin only**: Samo admin korisnici mogu pristupiti admin stranici
- **XSS protection**: Svi outputi su eskejpani s `htmlspecialchars()`
- **SQL injection protection**: Parametri se escapaju s `$db->escape()`
- **CSRF protection**: Forme koriste `newToken()` za validaciju

## Budući razvoj

- [ ] Email notifikacije za nove obavijesti
- [ ] Rich text editor za sadržaj
- [ ] Filtriranje obavijesti po tipu
- [ ] Export obavijesti u CSV
- [ ] Multimedija prilozi (slike, videa)
- [ ] Planiranje objave (scheduled publishing)

## Kontakt

Za pitanja i podršku:
- Email: tomislav@8core.hr
- Web: https://8core.hr
- Tel: +385 099 851 0717

---

**© 2025 8Core Association | Sva prava pridržana**
