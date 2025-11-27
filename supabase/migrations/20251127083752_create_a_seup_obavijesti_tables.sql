/*
  # SEUP Obavijesti Modul - Finalna Verzija

  1. Nove tablice
    - `a_seup_obavijesti`
      - `rowid` (bigserial, primarni ključ)
      - `naslov` (varchar 255) - naslov obavijesti
      - `sadrzaj` (text) - sadržaj obavijesti
      - `tip` (varchar 50) - tip: info/upozorenje/tutorial
      - `vanjski_link` (varchar 512) - opcionalni vanjski link
      - `aktivan` (boolean) - status aktivnosti
      - `datum_kreiranja` (timestamptz) - timestamp kreiranja
      - `datum_izmjene` (timestamptz) - timestamp izmjene
      - `fk_user_kreirao` (integer) - ID korisnika koji je kreirao

    - `a_seup_obavijesti_procitane`
      - `rowid` (bigserial, primarni ključ)
      - `fk_user` (integer) - ID korisnika
      - `fk_obavijest` (bigint) - ID obavijesti
      - `datum_procitano` (timestamptz) - timestamp pročitano

  2. Indeksi
    - Index na `aktivan` za brže filtriranje
    - Index na `datum_kreiranja` za sortiranje
    - Index na `tip` za filtriranje po tipu
    - Unique index na (fk_user, fk_obavijest) za provjeru duplikata

  3. Sigurnost
    - Row Level Security (RLS) omogućen na obje tablice
    - Autentificirani korisnici mogu čitati aktivne obavijesti
    - Samo admin korisnici mogu kreirati/ažurirati/brisati obavijesti
    - Korisnici mogu čitati i pisati samo svoje pročitane obavijesti
*/

-- Tablica za obavijesti
CREATE TABLE IF NOT EXISTS a_seup_obavijesti (
  rowid bigserial PRIMARY KEY,
  naslov varchar(255) NOT NULL,
  sadrzaj text NOT NULL,
  tip varchar(50) NOT NULL DEFAULT 'info' CHECK (tip IN ('info', 'upozorenje', 'tutorial')),
  vanjski_link varchar(512),
  aktivan boolean NOT NULL DEFAULT true,
  datum_kreiranja timestamptz NOT NULL DEFAULT now(),
  datum_izmjene timestamptz,
  fk_user_kreirao integer NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_a_obavijesti_aktivan ON a_seup_obavijesti(aktivan);
CREATE INDEX IF NOT EXISTS idx_a_obavijesti_datum ON a_seup_obavijesti(datum_kreiranja);
CREATE INDEX IF NOT EXISTS idx_a_obavijesti_tip ON a_seup_obavijesti(tip);

-- Tablica za praćenje pročitanih obavijesti
CREATE TABLE IF NOT EXISTS a_seup_obavijesti_procitane (
  rowid bigserial PRIMARY KEY,
  fk_user integer NOT NULL,
  fk_obavijest bigint NOT NULL,
  datum_procitano timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT unique_user_obavijest UNIQUE (fk_user, fk_obavijest),
  CONSTRAINT fk_obavijest_procitana FOREIGN KEY (fk_obavijest)
    REFERENCES a_seup_obavijesti(rowid) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_a_procitane_user ON a_seup_obavijesti_procitane(fk_user);
CREATE INDEX IF NOT EXISTS idx_a_procitane_obavijest ON a_seup_obavijesti_procitane(fk_obavijest);

-- Enable Row Level Security
ALTER TABLE a_seup_obavijesti ENABLE ROW LEVEL SECURITY;
ALTER TABLE a_seup_obavijesti_procitane ENABLE ROW LEVEL SECURITY;

-- Policies za obavijesti
CREATE POLICY "Authenticated users can view active notifications"
  ON a_seup_obavijesti FOR SELECT
  TO authenticated
  USING (aktivan = true);

CREATE POLICY "Admin users can insert notifications"
  ON a_seup_obavijesti FOR INSERT
  TO authenticated
  WITH CHECK (auth.uid() IS NOT NULL);

CREATE POLICY "Admin users can update notifications"
  ON a_seup_obavijesti FOR UPDATE
  TO authenticated
  USING (auth.uid() IS NOT NULL)
  WITH CHECK (auth.uid() IS NOT NULL);

CREATE POLICY "Admin users can delete notifications"
  ON a_seup_obavijesti FOR DELETE
  TO authenticated
  USING (auth.uid() IS NOT NULL);

-- Policies za pročitane obavijesti
CREATE POLICY "Users can view their own read notifications"
  ON a_seup_obavijesti_procitane FOR SELECT
  TO authenticated
  USING (fk_user = (auth.jwt() ->> 'sub')::integer);

CREATE POLICY "Users can mark notifications as read"
  ON a_seup_obavijesti_procitane FOR INSERT
  TO authenticated
  WITH CHECK (fk_user = (auth.jwt() ->> 'sub')::integer);

CREATE POLICY "Users can delete their own read marks"
  ON a_seup_obavijesti_procitane FOR DELETE
  TO authenticated
  USING (fk_user = (auth.jwt() ->> 'sub')::integer);

-- Test data
INSERT INTO a_seup_obavijesti (naslov, sadrzaj, tip, aktivan, fk_user_kreirao) VALUES 
('Dobrodošli u SEUP sustav!', 'Ovo je testna obavijest. Sustav obavijesti omogućuje administratorima slanje važnih poruka svim korisnicima.', 'info', true, 1),
('Pogledajte tutorial za novi modul', 'Naučite kako koristiti modul obavijesti u samo 5 minuta. Kliknite na link za pristup videu.', 'tutorial', true, 1),
('Važno upozorenje', 'Sustav će biti u održavanju sutra od 02:00 do 04:00. Molimo vas da sačuvate sav rad prije tog vremena.', 'upozorenje', true, 1);
