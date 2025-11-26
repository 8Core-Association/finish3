<?php

/**
 * Plaćena licenca
 * (c) 2025 8Core Association
 * Tomislav Galić <tomislav@8core.hr>
 * Marko Šimunović <marko@8core.hr>
 * Web: https://8core.hr
 * Kontakt: info@8core.hr | Tel: +385 099 851 0717
 * Sva prava pridržana. Ovaj softver je vlasnički i zaštićen je autorskim i srodnim pravima 
 * te ga je izričito zabranjeno umnožavati, distribuirati, mijenjati, objavljivati ili 
 * na drugi način eksploatirati bez pismenog odobrenja autora.
 */

/**
 * A4 Omat Spisa Generator for SEUP Module
 * Generates A4 portrait format document covers with predmet information and hierarchical document list
 */
class Omat_Generator
{
    private $db;
    private $conf;
    private $user;
    private $langs;

    public function __construct($db, $conf, $user, $langs)
    {
        $this->db = $db;
        $this->conf = $conf;
        $this->user = $user;
        $this->langs = $langs;
    }

    /**
     * Generate A4 portrait omat spisa for predmet
     */
    public function generateOmat($predmet_id, $save_to_ecm = true)
    {
        try {
            require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';
            require_once DOL_DOCUMENT_ROOT . '/ecm/class/ecmfiles.class.php';
            require_once __DIR__ . '/predmet_helper.class.php';

            $predmetData = $this->getPredmetData($predmet_id);
            if (!$predmetData) {
                throw new Exception('Predmet not found');
            }

            $aktiData = $this->getAktiWithRelations($predmet_id);

            $pdf = pdf_getInstance();
            $pdf->SetFont(pdf_getPDFFont($this->langs), '', 12);

            $pdf->AddPage('P', 'A4');

            $this->generatePage1($pdf, $predmetData);
            $this->generatePage2and3($pdf, $predmetData, $aktiData);
            $this->generatePage4($pdf);

            $filename = $this->generateFilename($predmetData);

            if ($save_to_ecm) {
                return $this->saveToECM($pdf, $filename, $predmet_id);
            } else {
                return $this->generatePreview($pdf, $predmetData, $aktiData);
            }

        } catch (Exception $e) {
            dol_syslog("Omat generation error: " . $e->getMessage(), LOG_ERR);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get predmet data with all related information
     */
    private function getPredmetData($predmet_id)
    {
        $sql = "SELECT 
                    p.ID_predmeta,
                    p.klasa_br,
                    p.sadrzaj,
                    p.dosje_broj,
                    p.godina,
                    p.predmet_rbr,
                    p.naziv_predmeta,
                    p.tstamp_created,
                    u.name_ustanova,
                    u.code_ustanova,
                    k.ime_prezime,
                    k.rbr as korisnik_rbr,
                    k.naziv as radno_mjesto,
                    ko.opis_klasifikacijske_oznake,
                    ko.vrijeme_cuvanja
                FROM " . MAIN_DB_PREFIX . "a_predmet p
                LEFT JOIN " . MAIN_DB_PREFIX . "a_oznaka_ustanove u ON p.ID_ustanove = u.ID_ustanove
                LEFT JOIN " . MAIN_DB_PREFIX . "a_interna_oznaka_korisnika k ON p.ID_interna_oznaka_korisnika = k.ID
                LEFT JOIN " . MAIN_DB_PREFIX . "a_klasifikacijska_oznaka ko ON p.ID_klasifikacijske_oznake = ko.ID_klasifikacijske_oznake
                WHERE p.ID_predmeta = " . (int)$predmet_id;

        $resql = $this->db->query($sql);
        if ($resql && $obj = $this->db->fetch_object($resql)) {
            // Format klasa
            $obj->klasa_format = $obj->klasa_br . '-' . $obj->sadrzaj . '/' . 
                                $obj->godina . '-' . $obj->dosje_broj . '/' . 
                                $obj->predmet_rbr;
            return $obj;
        }
        
        return false;
    }

    /**
     * Get akti with related prilozi, otpreme, and zaprimanja
     */
    private function getAktiWithRelations($predmet_id)
    {
        $akti = [];

        $sql = "SELECT
                    a.ID_akta,
                    a.urb_broj,
                    a.datum_kreiranja,
                    ef.filename,
                    ef.rowid as ecm_file_id
                FROM " . MAIN_DB_PREFIX . "a_akti a
                LEFT JOIN " . MAIN_DB_PREFIX . "ecm_files ef ON a.fk_ecm_file = ef.rowid
                WHERE a.ID_predmeta = " . (int)$predmet_id . "
                ORDER BY a.urb_broj ASC";

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($akt = $this->db->fetch_object($resql)) {
                $akt->prilozi = $this->getPriloziForAkt($akt->ID_akta);
                $akt->otpreme = $this->getOtpremeForDocument($akt->ecm_file_id, 'akt');
                $akt->zaprimanja = $this->getZaprimanjaForDocument($akt->ecm_file_id, 'akt');
                $akti[] = $akt;
            }
        }

        return $akti;
    }

    /**
     * Get prilozi for specific akt
     */
    private function getPriloziForAkt($akt_id)
    {
        $prilozi = [];

        $sql = "SELECT
                    p.prilog_rbr,
                    ef.filename,
                    ef.rowid as ecm_file_id
                FROM " . MAIN_DB_PREFIX . "a_prilozi p
                LEFT JOIN " . MAIN_DB_PREFIX . "ecm_files ef ON p.fk_ecm_file = ef.rowid
                WHERE p.ID_akta = " . (int)$akt_id . "
                ORDER BY CAST(p.prilog_rbr AS UNSIGNED) ASC";

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($prilog = $this->db->fetch_object($resql)) {
                $prilog->otpreme = $this->getOtpremeForDocument($prilog->ecm_file_id, 'prilog');
                $prilog->zaprimanja = $this->getZaprimanjaForDocument($prilog->ecm_file_id, 'prilog');
                $prilozi[] = $prilog;
            }
        }

        return $prilozi;
    }

    /**
     * Get otpreme for document
     */
    private function getOtpremeForDocument($ecm_file_id, $tip_dokumenta)
    {
        $otpreme = [];

        $sql = "SELECT
                    primatelj_naziv,
                    datum_otpreme,
                    nacin_otpreme
                FROM " . MAIN_DB_PREFIX . "a_otprema
                WHERE fk_ecm_file = " . (int)$ecm_file_id . "
                AND tip_dokumenta = '" . $this->db->escape($tip_dokumenta) . "'
                ORDER BY datum_otpreme ASC";

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $otpreme[] = $obj;
            }
        }

        return $otpreme;
    }

    /**
     * Get zaprimanja for document
     */
    private function getZaprimanjaForDocument($ecm_file_id, $tip_dokumenta)
    {
        $zaprimanja = [];

        $sql = "SELECT
                    posiljatelj_naziv,
                    datum_zaprimanja,
                    nacin_zaprimanja
                FROM " . MAIN_DB_PREFIX . "a_zaprimanja
                WHERE fk_ecm_file = " . (int)$ecm_file_id . "
                AND tip_dokumenta = '" . $this->db->escape($tip_dokumenta) . "'
                ORDER BY datum_zaprimanja ASC";

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $zaprimanja[] = $obj;
            }
        }

        return $zaprimanja;
    }

    /**
     * Generate page 1 - Front page with basic information (A4 Portrait)
     */
    private function generatePage1($pdf, $predmetData)
    {
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(false);

        $pdf->SetFont(pdf_getPDFFont($this->langs), 'B', 12);
        $pdf->Cell(0, 10, $this->encodeText('Naziv tijela:'), 0, 1, 'L');
        $pdf->SetFont(pdf_getPDFFont($this->langs), '', 11);
        $pdf->MultiCell(0, 7, $this->encodeText($predmetData->name_ustanova), 0, 'L');
        $pdf->Ln(5);

        $pdf->SetFont(pdf_getPDFFont($this->langs), 'B', 12);
        $pdf->Cell(0, 10, $this->encodeText('Oznaka unutarnje ustrojstvene jedinice:'), 0, 1, 'L');
        $pdf->SetFont(pdf_getPDFFont($this->langs), '', 11);
        $pdf->Cell(0, 7, $this->encodeText($predmetData->code_ustanova), 0, 1, 'L');
        $pdf->Ln(5);

        $pdf->SetFont(pdf_getPDFFont($this->langs), 'B', 12);
        $pdf->Cell(0, 10, $this->encodeText('Klasifikacijska oznaka:'), 0, 1, 'L');
        $pdf->SetFont(pdf_getPDFFont($this->langs), '', 11);
        $pdf->Cell(0, 7, $predmetData->klasa_format, 0, 1, 'L');
        $pdf->Ln(5);

        $pdf->SetFont(pdf_getPDFFont($this->langs), 'B', 12);
        $pdf->Cell(0, 10, $this->encodeText('Predmet:'), 0, 1, 'L');
        $pdf->SetFont(pdf_getPDFFont($this->langs), '', 11);
        $pdf->MultiCell(0, 7, $this->encodeText($predmetData->naziv_predmeta), 0, 'L');
        $pdf->Ln(10);

        $pdf->SetFont(pdf_getPDFFont($this->langs), 'B', 11);
        $pdf->Cell(0, 10, $this->encodeText('Mjesto za barkod'), 0, 1, 'C');
        $pdf->Rect(75, $pdf->GetY(), 60, 30);
        $pdf->Ln(35);

        $pdf->SetFont(pdf_getPDFFont($this->langs), 'I', 9);
        $pdf->Cell(0, 5, $this->encodeText('(predvidjeno za buducnost - QR kod na osnovu klase)'), 0, 1, 'C');
    }

    /**
     * Generate pages 2 and 3 - Hierarchical document list (A4 Portrait)
     */
    private function generatePage2and3($pdf, $predmetData, $aktiData)
    {
        $pdf->AddPage('P', 'A4');
        $pdf->SetMargins(15, 15, 15);

        if (empty($aktiData)) {
            $pdf->SetFont(pdf_getPDFFont($this->langs), 'I', 11);
            $pdf->Cell(0, 10, $this->encodeText('Nema dokumenata'), 0, 1, 'C');
            $pdf->AddPage('P', 'A4');
            return;
        }

        $rb = 1;
        foreach ($aktiData as $akt) {
            if ($pdf->GetY() > 260) {
                $pdf->AddPage('P', 'A4');
            }

            $akt_oznaka = $this->generateAktOznaka($predmetData, $akt->urb_broj);

            $pdf->SetFont(pdf_getPDFFont($this->langs), 'B', 10);
            $pdf->Cell(10, 6, $rb . '.', 0, 0, 'L');
            $pdf->Cell(0, 6, $akt_oznaka, 0, 1, 'L');

            if (!empty($akt->prilozi)) {
                $prilog_rb = 1;
                foreach ($akt->prilozi as $prilog) {
                    if ($pdf->GetY() > 270) {
                        $pdf->AddPage('P', 'A4');
                    }

                    $pdf->SetFont(pdf_getPDFFont($this->langs), '', 9);
                    $pdf->Cell(15, 5, '', 0, 0, 'L');
                    $pdf->Cell(0, 5, $this->encodeText('- Prilog: rb ' . $prilog_rb), 0, 1, 'L');

                    if (!empty($prilog->otpreme)) {
                        foreach ($prilog->otpreme as $otprema) {
                            $pdf->SetFont(pdf_getPDFFont($this->langs), 'I', 8);
                            $pdf->Cell(25, 4, '', 0, 0, 'L');
                            $pdf->Cell(0, 4, $this->encodeText('* Otprema: ' . $otprema->primatelj_naziv), 0, 1, 'L');
                        }
                    }

                    if (!empty($prilog->zaprimanja)) {
                        foreach ($prilog->zaprimanja as $zaprimanje) {
                            $pdf->SetFont(pdf_getPDFFont($this->langs), 'I', 8);
                            $pdf->Cell(25, 4, '', 0, 0, 'L');
                            $pdf->Cell(0, 4, $this->encodeText('* Zaprimanje: ' . $zaprimanje->posiljatelj_naziv), 0, 1, 'L');
                        }
                    }

                    $prilog_rb++;
                }
            }

            if (!empty($akt->otpreme)) {
                foreach ($akt->otpreme as $otprema) {
                    $pdf->SetFont(pdf_getPDFFont($this->langs), 'I', 9);
                    $pdf->Cell(15, 4, '', 0, 0, 'L');
                    $pdf->Cell(0, 4, $this->encodeText('- Otprema: ' . $otprema->primatelj_naziv), 0, 1, 'L');
                }
            }

            if (!empty($akt->zaprimanja)) {
                foreach ($akt->zaprimanja as $zaprimanje) {
                    $pdf->SetFont(pdf_getPDFFont($this->langs), 'I', 9);
                    $pdf->Cell(15, 4, '', 0, 0, 'L');
                    $pdf->Cell(0, 4, $this->encodeText('- Zaprimanje: ' . $zaprimanje->posiljatelj_naziv), 0, 1, 'L');
                }
            }

            $pdf->Ln(3);
            $rb++;
        }

        if ($pdf->PageNo() == 2) {
            $pdf->AddPage('P', 'A4');
        }
    }

    /**
     * Generate akt oznaka: [code_ustanova]-[rbr_zaposlenika]-[godina]-[urb_broj]
     */
    private function generateAktOznaka($predmetData, $urb_broj)
    {
        return sprintf(
            '[%s]-[%s]-[%s]-[%s]',
            $predmetData->code_ustanova,
            $predmetData->korisnik_rbr,
            $predmetData->godina,
            $urb_broj
        );
    }

    /**
     * Generate page 4 - Empty back page (A4 Portrait)
     */
    private function generatePage4($pdf)
    {
        $pdf->AddPage('P', 'A4');
    }

    /**
     * Encode text for proper Croatian character display in PDF
     */
    private function encodeText($text)
    {
        // Ensure UTF-8 encoding
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }
        
        // Alternative: Manual character replacement if font doesn't support UTF-8
        // Uncomment if needed:
        /*
        $croatian_chars = [
            'č' => 'c', 'ć' => 'c', 'đ' => 'd', 'š' => 's', 'ž' => 'z',
            'Č' => 'C', 'Ć' => 'C', 'Đ' => 'D', 'Š' => 'S', 'Ž' => 'Z'
        ];
        $text = strtr($text, $croatian_chars);
        */
        
        return $text;
    }

    /**
     * Save PDF to ECM as attachment
     */
    private function saveToECM($pdf, $filename, $predmet_id)
    {
        try {
            // Get predmet folder path
            $relative_path = Predmet_helper::getPredmetFolderPath($predmet_id, $this->db);
            $full_path = DOL_DATA_ROOT . '/ecm/' . $relative_path;
            
            // Ensure directory exists
            if (!is_dir($full_path)) {
                dol_mkdir($full_path);
            }
            
            // Save PDF file
            $filepath = $full_path . $filename;
            $pdf->Output($filepath, 'F');
            
            // Create ECM record
            $ecmfile = new EcmFiles($this->db);
            $ecmfile->filepath = rtrim($relative_path, '/');
            $ecmfile->filename = $filename;
            $ecmfile->label = 'Omot spisa - ' . $filename;
            $ecmfile->entity = $this->conf->entity;
            $ecmfile->gen_or_uploaded = 'generated';
            $ecmfile->description = 'Automatski generirani omot spisa za predmet ' . $predmet_id;
            $ecmfile->fk_user_c = $this->user->id;
            $ecmfile->fk_user_m = $this->user->id;
            $ecmfile->date_c = dol_now();
            $ecmfile->date_m = dol_now();
            
            $result = $ecmfile->create($this->user);
            if ($result > 0) {
                dol_syslog("Omot spisa saved to ECM: " . $filename, LOG_INFO);
                
                return [
                    'success' => true,
                    'message' => 'Omot spisa je uspješno kreiran i dodan u privitak',
                    'filename' => $filename,
                    'ecm_id' => $result,
                    'download_url' => DOL_URL_ROOT . '/document.php?modulepart=ecm&file=' . urlencode($relative_path . $filename)
                ];
            } else {
                throw new Exception('Failed to create ECM record: ' . $ecmfile->error);
            }

        } catch (Exception $e) {
            dol_syslog("Error saving omot to ECM: " . $e->getMessage(), LOG_ERR);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate preview data for modal
     */
    public function generatePreview($predmet_id)
    {
        try {
            $predmetData = $this->getPredmetData($predmet_id);
            if (!$predmetData) {
                throw new Exception('Predmet not found');
            }

            $aktiData = $this->getAktiWithRelations($predmet_id);

            return [
                'success' => true,
                'predmet' => $predmetData,
                'akti' => $aktiData,
                'preview_html' => $this->generatePreviewHTML($predmetData, $aktiData)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate HTML preview for modal
     */
    private function generatePreviewHTML($predmetData, $aktiData)
    {
        $html = '<div class="seup-omat-preview">';

        $html .= '<div class="seup-omat-page seup-omat-page-a4">';
        $html .= '<div class="seup-omat-section">';
        $html .= '<h4>Naziv tijela:</h4>';
        $html .= '<p>' . htmlspecialchars($predmetData->name_ustanova) . '</p>';
        $html .= '</div>';

        $html .= '<div class="seup-omat-section">';
        $html .= '<h4>Oznaka unutarnje ustrojstvene jedinice:</h4>';
        $html .= '<p>' . htmlspecialchars($predmetData->code_ustanova) . '</p>';
        $html .= '</div>';

        $html .= '<div class="seup-omat-section">';
        $html .= '<h4>Klasifikacijska oznaka:</h4>';
        $html .= '<p>' . htmlspecialchars($predmetData->klasa_format) . '</p>';
        $html .= '</div>';

        $html .= '<div class="seup-omat-section">';
        $html .= '<h4>Predmet:</h4>';
        $html .= '<p>' . htmlspecialchars($predmetData->naziv_predmeta) . '</p>';
        $html .= '</div>';

        $html .= '<div class="seup-omat-barcode">';
        $html .= '<p style="text-align:center; margin-top: 20px;"><strong>Mjesto za barkod</strong></p>';
        $html .= '<div style="border: 1px solid #ccc; height: 80px; margin: 10px auto; width: 200px;"></div>';
        $html .= '<p style="text-align:center; font-size: 11px; color: #666;">(predvidjeno za buducnost - QR kod)</p>';
        $html .= '</div>';

        $html .= '</div>';

        $html .= '<div class="seup-omat-page seup-omat-page-a4">';
        $html .= '<h3 class="seup-omat-title" style="font-size: 14px; margin-bottom: 15px;">POPIS DOKUMENATA</h3>';

        if (empty($aktiData)) {
            $html .= '<p class="seup-omat-empty">Nema dokumenata</p>';
        } else {
            $rb = 1;
            foreach ($aktiData as $akt) {
                $akt_oznaka = $this->generateAktOznaka($predmetData, $akt->urb_broj);

                $html .= '<div class="seup-omat-akt" style="margin-bottom: 15px;">';
                $html .= '<div style="font-weight: bold; margin-bottom: 5px;">' . $rb . '. ' . htmlspecialchars($akt_oznaka) . '</div>';

                if (!empty($akt->prilozi)) {
                    $prilog_rb = 1;
                    foreach ($akt->prilozi as $prilog) {
                        $html .= '<div style="margin-left: 20px; font-size: 13px;">- Prilog: rb ' . $prilog_rb . '</div>';

                        if (!empty($prilog->otpreme)) {
                            foreach ($prilog->otpreme as $otprema) {
                                $html .= '<div style="margin-left: 40px; font-size: 12px; font-style: italic; color: #555;">* Otprema: ' . htmlspecialchars($otprema->primatelj_naziv) . '</div>';
                            }
                        }

                        if (!empty($prilog->zaprimanja)) {
                            foreach ($prilog->zaprimanja as $zaprimanje) {
                                $html .= '<div style="margin-left: 40px; font-size: 12px; font-style: italic; color: #555;">* Zaprimanje: ' . htmlspecialchars($zaprimanje->posiljatelj_naziv) . '</div>';
                            }
                        }

                        $prilog_rb++;
                    }
                }

                if (!empty($akt->otpreme)) {
                    foreach ($akt->otpreme as $otprema) {
                        $html .= '<div style="margin-left: 20px; font-size: 13px; font-style: italic;">- Otprema: ' . htmlspecialchars($otprema->primatelj_naziv) . '</div>';
                    }
                }

                if (!empty($akt->zaprimanja)) {
                    foreach ($akt->zaprimanja as $zaprimanje) {
                        $html .= '<div style="margin-left: 20px; font-size: 13px; font-style: italic;">- Zaprimanje: ' . htmlspecialchars($zaprimanje->posiljatelj_naziv) . '</div>';
                    }
                }

                $html .= '</div>';
                $rb++;
            }
        }

        $html .= '</div>';

        $html .= '<div class="seup-omat-page seup-omat-page-a4">';
        $html .= '<p style="text-align:center; color: #999; padding-top: 100px;">Stranica 3 (nastavak ako treba)</p>';
        $html .= '</div>';

        $html .= '<div class="seup-omat-page seup-omat-page-a4">';
        $html .= '<p style="text-align:center; color: #999; padding-top: 100px;">Stranica 4 (prazna zadnja stranica)</p>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate filename for omat
     */
    private function generateFilename($predmetData)
    {
        $klasa_safe = str_replace('/', '_', $predmetData->klasa_format);
        $datum = dol_print_date(dol_now(), '%Y%m%d_%H%M%S');
        
        return 'Omot_' . $klasa_safe . '_' . $datum . '.pdf';
    }

    /**
     * Get omot statistics
     */
    public static function getOmotStatistics($db, $conf)
    {
        try {
            $stats = [
                'total_omoti' => 0,
                'generated_today' => 0,
                'generated_this_month' => 0
            ];

            // Count total generated omoti
            $sql = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "ecm_files 
                    WHERE filename LIKE 'Omot_%'
                    AND filepath LIKE 'SEUP%'
                    AND entity = " . $conf->entity;
            $resql = $db->query($sql);
            if ($resql && $obj = $db->fetch_object($resql)) {
                $stats['total_omoti'] = (int)$obj->count;
            }

            // Count generated today
            $today = dol_print_date(dol_now(), '%Y-%m-%d');
            $sql = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "ecm_files 
                    WHERE filename LIKE 'Omot_%'
                    AND filepath LIKE 'SEUP%'
                    AND DATE(FROM_UNIXTIME(date_c)) = '" . $today . "'
                    AND entity = " . $conf->entity;
            $resql = $db->query($sql);
            if ($resql && $obj = $db->fetch_object($resql)) {
                $stats['generated_today'] = (int)$obj->count;
            }

            // Count generated this month
            $month = dol_print_date(dol_now(), '%Y-%m');
            $sql = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "ecm_files 
                    WHERE filename LIKE 'Omot_%'
                    AND filepath LIKE 'SEUP%'
                    AND DATE_FORMAT(FROM_UNIXTIME(date_c), '%Y-%m') = '" . $month . "'
                    AND entity = " . $conf->entity;
            $resql = $db->query($sql);
            if ($resql && $obj = $db->fetch_object($resql)) {
                $stats['generated_this_month'] = (int)$obj->count;
            }

            return $stats;

        } catch (Exception $e) {
            dol_syslog("Error getting omot statistics: " . $e->getMessage(), LOG_ERR);
            return null;
        }
    }
}