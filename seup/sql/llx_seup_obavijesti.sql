-- Tablica za obavijesti
-- NAPOMENA: Naziv tablice je "a_seup_obavijesti" i dodaje se na Dolibarrov PREFIX
-- Npr. ako je PREFIX = llx_, puni naziv će biti: llx_a_seup_obavijesti
CREATE TABLE IF NOT EXISTS PREFIX_a_seup_obavijesti (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    naslov VARCHAR(255) NOT NULL,
    sadrzaj TEXT NOT NULL,
    tip ENUM('info', 'upozorenje', 'tutorial') NOT NULL DEFAULT 'info',
    vanjski_link VARCHAR(512) DEFAULT NULL,
    aktivan TINYINT(1) NOT NULL DEFAULT 1,
    datum_kreiranja DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    datum_izmjene DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    fk_user_kreirao INT NOT NULL,
    INDEX idx_aktivan (aktivan),
    INDEX idx_datum (datum_kreiranja),
    INDEX idx_tip (tip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tablica za praćenje pročitanih obavijesti
CREATE TABLE IF NOT EXISTS PREFIX_a_seup_obavijesti_procitane (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_user INT NOT NULL,
    fk_obavijest INT NOT NULL,
    datum_procitano DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_obavijest (fk_user, fk_obavijest),
    INDEX idx_user (fk_user),
    INDEX idx_obavijest (fk_obavijest),
    CONSTRAINT fk_obavijest_procitana FOREIGN KEY (fk_obavijest)
        REFERENCES PREFIX_a_seup_obavijesti(rowid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Testni podaci (dodaje se samo ako nema podataka)
INSERT INTO PREFIX_a_seup_obavijesti (naslov, sadrzaj, tip, aktivan, fk_user_kreirao)
SELECT 'Dobrodošli u SEUP sustav!',
       'Ovo je testna obavijest. Sustav obavijesti omogućuje administratorima slanje važnih poruka svim korisnicima.',
       'info', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM PREFIX_a_seup_obavijesti WHERE naslov = 'Dobrodošli u SEUP sustav!');

INSERT INTO PREFIX_a_seup_obavijesti (naslov, sadrzaj, tip, aktivan, fk_user_kreirao)
SELECT 'Pogledajte tutorial za novi modul',
       'Naučite kako koristiti modul obavijesti u samo 5 minuta. Kliknite na link za pristup videu.',
       'tutorial', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM PREFIX_a_seup_obavijesti WHERE naslov = 'Pogledajte tutorial za novi modul');

INSERT INTO PREFIX_a_seup_obavijesti (naslov, sadrzaj, tip, aktivan, fk_user_kreirao)
SELECT 'Važno upozorenje',
       'Sustav će biti u održavanju sutra od 02:00 do 04:00. Molimo vas da sačuvate sav rad prije tog vremena.',
       'upozorenje', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM PREFIX_a_seup_obavijesti WHERE naslov = 'Važno upozorenje');
