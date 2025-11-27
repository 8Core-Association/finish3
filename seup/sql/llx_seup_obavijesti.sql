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
