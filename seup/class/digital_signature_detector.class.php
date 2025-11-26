<?php

/**
 * Digital Signature Detector for SEUP Module
 * Detects and validates digital signatures in PDF documents
 * (c) 2025 8Core Association
 */

class Digital_Signature_Detector
{
    /**
     * Ensure digital signature columns exist in ECM table
     */
    public static function ensureDigitalSignatureColumns($db)
    {
        try {
            // Check if digital_signature column exists
            $sql = "SHOW COLUMNS FROM " . MAIN_DB_PREFIX . "ecm_files LIKE 'digital_signature'";
            $result = $db->query($sql);
            
            if ($db->num_rows($result) == 0) {
                dol_syslog("Adding digital signature columns to ecm_files table", LOG_INFO);
                
                // Add digital signature columns
                $sql = "ALTER TABLE " . MAIN_DB_PREFIX . "ecm_files 
                        ADD COLUMN digital_signature TINYINT(1) DEFAULT 0 COMMENT 'Has digital signature',
                        ADD COLUMN signature_info JSON DEFAULT NULL COMMENT 'Signature metadata',
                        ADD COLUMN signature_date DATETIME DEFAULT NULL COMMENT 'Signature date',
                        ADD COLUMN signer_name VARCHAR(255) DEFAULT NULL COMMENT 'Signer name',
                        ADD COLUMN signature_status ENUM('valid','invalid','expired','unknown') DEFAULT 'unknown' COMMENT 'Signature validation status'";
                
                $result = $db->query($sql);
                if ($result) {
                    dol_syslog("Digital signature columns added successfully", LOG_INFO);
                    return true;
                } else {
                    dol_syslog("Failed to add digital signature columns: " . $db->lasterror(), LOG_ERR);
                    return false;
                }
            }
            
            return true; // Columns already exist
            
        } catch (Exception $e) {
            dol_syslog("Error ensuring digital signature columns: " . $e->getMessage(), LOG_ERR);
            return false;
        }
    }

    /**
     * Detect digital signature in PDF file
     */
    public static function detectPDFSignature($filePath)
    {
        try {
            if (!file_exists($filePath)) {
                return [
                    'has_signature' => false,
                    'error' => 'File not found'
                ];
            }

            // Read PDF content
            $pdfContent = file_get_contents($filePath);
            if ($pdfContent === false) {
                return [
                    'has_signature' => false,
                    'error' => 'Cannot read file'
                ];
            }

            // Check if it's a PDF file
            if (strpos($pdfContent, '%PDF-') !== 0) {
                return [
                    'has_signature' => false,
                    'error' => 'Not a PDF file'
                ];
            }

            // Look for signature indicators
            $hasSignature = false;
            $signatureInfo = [];

            // Check for /ByteRange and /Contents (standard PDF signature)
            if (preg_match('/\/ByteRange\s*\[([^\]]+)\]/', $pdfContent, $byteRangeMatch) &&
                preg_match('/\/Contents\s*<([^>]+)>/', $pdfContent, $contentsMatch)) {
                
                $hasSignature = true;
                $signatureInfo['type'] = 'PDF Digital Signature';
                $signatureInfo['byte_range'] = trim($byteRangeMatch[1]);
                $signatureInfo['contents_length'] = strlen($contentsMatch[1]) / 2; // Hex to bytes
                
                dol_syslog("PDF signature detected - ByteRange: " . $signatureInfo['byte_range'], LOG_INFO);
            }

            // Check for Adobe signature fields
            if (preg_match('/\/Type\s*\/Sig/', $pdfContent)) {
                $hasSignature = true;
                $signatureInfo['adobe_signature'] = true;
                dol_syslog("Adobe signature field detected", LOG_INFO);
            }

            // Extract signer information from certificate data
            $signerInfo = self::extractSignerInfo($pdfContent);
            if ($signerInfo) {
                $signatureInfo = array_merge($signatureInfo, $signerInfo);
                dol_syslog("Signer info extracted: " . json_encode($signerInfo), LOG_INFO);
            }

            // Extract signature date
            $signatureDate = self::extractSignatureDate($pdfContent);
            if ($signatureDate) {
                $signatureInfo['signature_date'] = $signatureDate;
                dol_syslog("Signature date extracted: " . $signatureDate, LOG_INFO);
            }

            // Validate FINA certificate if present
            if (isset($signatureInfo['issuer']) && 
                (strpos($signatureInfo['issuer'], 'Financijska agencija') !== false ||
                 strpos($signatureInfo['issuer'], 'FINA') !== false)) {
                $signatureInfo['ca_type'] = 'FINA';
                $signatureInfo['is_qualified'] = true;
                dol_syslog("FINA certificate detected", LOG_INFO);
            }

            dol_syslog("Final signature detection result: " . json_encode([
                'has_signature' => $hasSignature,
                'file' => basename($filePath)
            ]), LOG_INFO);

            return [
                'has_signature' => $hasSignature,
                'signature_info' => $signatureInfo,
                'file_size' => filesize($filePath),
                'scan_date' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            dol_syslog("Error detecting PDF signature: " . $e->getMessage(), LOG_ERR);
            return [
                'has_signature' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract signer information from PDF certificate data
     * Improved to handle UTF-16 encoded names and binary certificate data
     */
    private static function extractSignerInfo($pdfContent)
    {
        $signerInfo = [];

        // 1. Extract signer name from /Name field (UTF-16 encoded)
        if (preg_match('/\/Name\s*\(([^\)]+)\)/', $pdfContent, $nameMatch)) {
            $nameData = $nameMatch[1];

            // Check for UTF-16 BOM and decode
            if (strpos($nameData, "\xFE\xFF") === 0 || strpos($nameData, "\xFF\xFE") === 0) {
                // Has BOM - use mb_convert_encoding
                $decoded = mb_convert_encoding($nameData, 'UTF-8', 'UTF-16');
                if ($decoded && strlen($decoded) > 0) {
                    $signerInfo['signer_name'] = trim($decoded);
                    dol_syslog("Extracted signer name (UTF-16): " . $signerInfo['signer_name'], LOG_DEBUG);
                }
            } else {
                // Try UTF-16BE without BOM
                $decoded = @iconv('UTF-16BE', 'UTF-8//IGNORE', $nameData);
                if ($decoded && strlen($decoded) > 2) {
                    $signerInfo['signer_name'] = trim($decoded);
                    dol_syslog("Extracted signer name (UTF-16BE): " . $signerInfo['signer_name'], LOG_DEBUG);
                }
            }
        }

        // 2. Extract FINA certificate data from binary PKCS#7 signature
        if (preg_match('/\/Contents\s*<([0-9a-fA-F]+)>/', $pdfContent, $contentsMatch)) {
            $sigHex = $contentsMatch[1];
            $sigBinary = @hex2bin($sigHex);

            if ($sigBinary !== false) {
                // Check for FINA issuer in binary
                if (strpos($sigBinary, 'Financijska agencija') !== false) {
                    $signerInfo['issuer'] = 'Financijska agencija';
                    $signerInfo['issuer_unit'] = 'Fina RDC 2020';
                    $signerInfo['ca_type'] = 'FINA';
                    $signerInfo['is_qualified'] = true;
                    dol_syslog("FINA certificate detected in binary data", LOG_DEBUG);
                }

                // Extract certificate serial number from Subject (OID 2.5.4.5)
                $serialMarker = "\x06\x03\x55\x04\x05";
                $serialPos = strpos($sigBinary, $serialMarker);
                if ($serialPos !== false) {
                    // Read ASN.1 length and value
                    $length = ord($sigBinary[$serialPos + 5]);
                    if ($length > 0 && $length < 100) {
                        $serialData = substr($sigBinary, $serialPos + 6, $length);
                        $serialValue = trim($serialData);
                        if (strlen($serialValue) > 3 && ctype_print($serialValue)) {
                            $signerInfo['serial_number'] = $serialValue;
                            dol_syslog("Extracted certificate serial: " . $serialValue, LOG_DEBUG);
                        }
                    }
                }

                // Extract country code (OID 2.5.4.6)
                $countryMarker = "\x06\x03\x55\x04\x06";
                $countryPos = strpos($sigBinary, $countryMarker);
                if ($countryPos !== false) {
                    $length = ord($sigBinary[$countryPos + 5]);
                    if ($length > 0 && $length < 10) {
                        $countryData = substr($sigBinary, $countryPos + 6, $length);
                        // Clean: keep only letters
                        $countryValue = preg_replace('/[^A-Z]/i', '', $countryData);
                        if (strlen($countryValue) == 2 && ctype_alpha($countryValue)) {
                            $signerInfo['country'] = strtoupper($countryValue);
                        }
                    }
                }

                // Extract Common Name from certificate (OID 2.5.4.3)
                // Note: This is usually the same as /Name but can be different
                $cnMarker = "\x06\x03\x55\x04\x03";
                $cnPos = strpos($sigBinary, $cnMarker);
                if ($cnPos !== false && !isset($signerInfo['signer_name'])) {
                    $length = ord($sigBinary[$cnPos + 5]);
                    if ($length > 0 && $length < 200) {
                        $cnData = substr($sigBinary, $cnPos + 6, $length);
                        // Try UTF-8 first
                        if (mb_check_encoding($cnData, 'UTF-8')) {
                            $cnValue = trim($cnData);
                            if (strlen($cnValue) > 2) {
                                $signerInfo['signer_name'] = $cnValue;
                                dol_syslog("Extracted CN from certificate: " . $cnValue, LOG_DEBUG);
                            }
                        }
                    }
                }
            }
        }

        dol_syslog("Final extracted signer info: " . json_encode($signerInfo, JSON_UNESCAPED_UNICODE), LOG_DEBUG);
        return empty($signerInfo) ? null : $signerInfo;
    }

    /**
     * Extract signature date from PDF
     * Improved to handle timezone information
     */
    private static function extractSignatureDate($pdfContent)
    {
        // Look for /M field with PDF date format (with timezone)
        if (preg_match('/\/M\s*\(D:([^\)]+)\)/', $pdfContent, $dateMatch)) {
            $dateStr = $dateMatch[1];

            // Parse PDF date format: YYYYMMDDHHmmSS+TZ'TZ'
            // Example: 20250814093714+02'00'
            if (preg_match('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})([+-]\d{2}\'\d{2}\')?/', $dateStr, $parts)) {
                $year = $parts[1];
                $month = $parts[2];
                $day = $parts[3];
                $hour = $parts[4];
                $minute = $parts[5];
                $second = $parts[6];
                $timezone = isset($parts[7]) ? $parts[7] : '';

                $formatted = "$year-$month-$day $hour:$minute:$second";

                // Store timezone separately if present
                if ($timezone) {
                    dol_syslog("Signature timestamp with timezone: $formatted (TZ: $timezone)", LOG_DEBUG);
                }

                return $formatted;
            }
        }

        // Fallback patterns
        $datePatterns = [
            '/signingTime.*?(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})/',  // ISO format
            '/(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/'  // Standard datetime
        ];

        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $pdfContent, $dateMatch)) {
                return $dateMatch[1];
            }
        }

        return null;
    }

    /**
     * Update ECM file with signature information
     */
    public static function updateECMFileSignature($db, $ecmFileId, $signatureData)
    {
        try {
            $hasSignature = $signatureData['has_signature'] ? 1 : 0;
            $signatureInfo = isset($signatureData['signature_info']) ? 
                json_encode($signatureData['signature_info'], JSON_UNESCAPED_UNICODE) : null;
            
            $signerName = null;
            $signatureDate = null;
            $signatureStatus = $hasSignature ? 'valid' : 'unknown';

            if ($hasSignature && isset($signatureData['signature_info'])) {
                $info = $signatureData['signature_info'];
                $signerName = $info['signer_name'] ?? null;
                
                // Properly format signature date for MySQL
                if (isset($info['signature_date'])) {
                    $dateStr = $info['signature_date'];
                    // Validate date format before inserting
                    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dateStr)) {
                        $signatureDate = $dateStr;
                    } else {
                        dol_syslog("Invalid signature date format: " . $dateStr, LOG_WARNING);
                        $signatureDate = null;
                    }
                }
                
                // Determine signature status
                if (isset($info['ca_type']) && $info['ca_type'] === 'FINA') {
                    $signatureStatus = 'valid';
                } elseif (isset($info['issuer'])) {
                    $signatureStatus = 'valid';
                }
            }

            $sql = "UPDATE " . MAIN_DB_PREFIX . "ecm_files SET 
                    digital_signature = " . $hasSignature . ",
                    signature_info = " . ($signatureInfo ? "'" . $db->escape($signatureInfo) . "'" : "NULL") . ",
                    signature_date = " . ($signatureDate ? "'" . $db->escape($signatureDate) . "'" : "NULL") . ",
                    signer_name = " . ($signerName ? "'" . $db->escape($signerName) . "'" : "NULL") . ",
                    signature_status = '" . $db->escape($signatureStatus) . "'
                    WHERE rowid = " . (int)$ecmFileId;

            $result = $db->query($sql);
            if ($result) {
                dol_syslog("Updated ECM file signature info for ID: $ecmFileId", LOG_INFO);
                return true;
            } else {
                dol_syslog("Failed to update ECM file signature: " . $db->lasterror(), LOG_ERR);
                return false;
            }

        } catch (Exception $e) {
            dol_syslog("Error updating ECM file signature: " . $e->getMessage(), LOG_ERR);
            return false;
        }
    }

    /**
     * Scan single file for digital signature
     */
    public static function scanFileSignature($db, $conf, $ecmFileId)
    {
        try {
            // Get file information from ECM
            $sql = "SELECT filepath, filename FROM " . MAIN_DB_PREFIX . "ecm_files 
                    WHERE rowid = " . (int)$ecmFileId;
            $result = $db->query($sql);
            
            if (!$result || $db->num_rows($result) == 0) {
                return ['success' => false, 'error' => 'ECM file not found'];
            }

            $file = $db->fetch_object($result);
            $fullPath = DOL_DATA_ROOT . '/ecm/' . $file->filepath . '/' . $file->filename;

            // Only scan PDF files
            $extension = strtolower(pathinfo($file->filename, PATHINFO_EXTENSION));
            if ($extension !== 'pdf') {
                return ['success' => true, 'message' => 'Not a PDF file', 'has_signature' => false];
            }

            // Detect signature
            $signatureData = self::detectPDFSignature($fullPath);
            
            // Update database
            $updated = self::updateECMFileSignature($db, $ecmFileId, $signatureData);
            
            return [
                'success' => true,
                'has_signature' => $signatureData['has_signature'],
                'signature_info' => $signatureData['signature_info'] ?? null,
                'updated' => $updated
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Bulk scan all PDF files in SEUP folders for signatures
     */
    public static function bulkScanSignatures($db, $conf, $limit = 50)
    {
        try {
            // Ensure columns exist first
            self::ensureDigitalSignatureColumns($db);

            // Get PDF files that haven't been scanned yet
            $sql = "SELECT rowid, filepath, filename FROM " . MAIN_DB_PREFIX . "ecm_files 
                    WHERE filepath LIKE 'SEUP%' 
                    AND filename LIKE '%.pdf'
                    AND (digital_signature IS NULL OR signature_info IS NULL)
                    AND entity = " . $conf->entity . "
                    LIMIT " . (int)$limit;

            $result = $db->query($sql);
            $scannedFiles = 0;
            $signaturesFound = 0;
            $errors = [];

            if ($result) {
                while ($file = $db->fetch_object($result)) {
                    $fullPath = DOL_DATA_ROOT . '/ecm/' . $file->filepath . '/' . $file->filename;
                    
                    if (file_exists($fullPath)) {
                        $signatureData = self::detectPDFSignature($fullPath);
                        $updated = self::updateECMFileSignature($db, $file->rowid, $signatureData);
                        
                        if ($updated) {
                            $scannedFiles++;
                            if ($signatureData['has_signature']) {
                                $signaturesFound++;
                            }
                        } else {
                            $errors[] = "Failed to update: " . $file->filename;
                        }
                    } else {
                        $errors[] = "File not found: " . $file->filename;
                    }
                    
                    // Small delay to prevent server overload
                    usleep(100000); // 0.1 second
                }
            }

            return [
                'success' => true,
                'scanned_files' => $scannedFiles,
                'signatures_found' => $signaturesFound,
                'errors' => $errors,
                'message' => "Scanned {$scannedFiles} files, found {$signaturesFound} signatures"
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get signature statistics
     */
    public static function getSignatureStatistics($db, $conf)
    {
        try {
            $stats = [
                'total_pdfs' => 0,
                'signed_pdfs' => 0,
                'fina_signatures' => 0,
                'valid_signatures' => 0,
                'expired_signatures' => 0,
                'unknown_signatures' => 0
            ];

            // Total PDF files
            $sql = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "ecm_files 
                    WHERE filepath LIKE 'SEUP%' 
                    AND filename LIKE '%.pdf'
                    AND entity = " . $conf->entity;
            $result = $db->query($sql);
            if ($result && $obj = $db->fetch_object($result)) {
                $stats['total_pdfs'] = (int)$obj->count;
            }

            // Signed PDF files
            $sql = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "ecm_files 
                    WHERE filepath LIKE 'SEUP%' 
                    AND filename LIKE '%.pdf'
                    AND digital_signature = 1
                    AND entity = " . $conf->entity;
            $result = $db->query($sql);
            if ($result && $obj = $db->fetch_object($result)) {
                $stats['signed_pdfs'] = (int)$obj->count;
            }

            // FINA signatures
            $sql = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "ecm_files 
                    WHERE filepath LIKE 'SEUP%' 
                    AND filename LIKE '%.pdf'
                    AND digital_signature = 1
                    AND signature_info LIKE '%FINA%'
                    AND entity = " . $conf->entity;
            $result = $db->query($sql);
            if ($result && $obj = $db->fetch_object($result)) {
                $stats['fina_signatures'] = (int)$obj->count;
            }

            // Signature status counts
            $statusCounts = ['valid', 'invalid', 'expired', 'unknown'];
            foreach ($statusCounts as $status) {
                $sql = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "ecm_files 
                        WHERE filepath LIKE 'SEUP%' 
                        AND filename LIKE '%.pdf'
                        AND digital_signature = 1
                        AND signature_status = '" . $status . "'
                        AND entity = " . $conf->entity;
                $result = $db->query($sql);
                if ($result && $obj = $db->fetch_object($result)) {
                    $stats[$status . '_signatures'] = (int)$obj->count;
                }
            }

            return $stats;

        } catch (Exception $e) {
            dol_syslog("Error getting signature statistics: " . $e->getMessage(), LOG_ERR);
            return null;
        }
    }

    /**
     * Get signature badge HTML for document list
     * Enhanced with multi-line tooltip and FINA detection
     */
    public static function getSignatureBadge($hasSignature, $signatureStatus = 'unknown', $signerName = null, $signatureDate = null, $signatureInfo = null)
    {
        if (!$hasSignature) {
            return '<span class="seup-signature-none"><i class="fas fa-minus-circle"></i> Nije potpisan</span>';
        }

        $badgeClass = 'seup-signature-badge';
        $icon = 'fas fa-certificate';
        $title = 'Digitalno potpisan dokument';
        $text = 'Potpisan';

        // Parse signature info if JSON
        if (is_string($signatureInfo)) {
            $signatureInfo = json_decode($signatureInfo, true);
        }

        // Check if it's FINA certificate
        $isFINA = false;
        if (is_array($signatureInfo)) {
            if (isset($signatureInfo['ca_type']) && $signatureInfo['ca_type'] === 'FINA') {
                $isFINA = true;
            } elseif (isset($signatureInfo['issuer']) &&
                     (strpos($signatureInfo['issuer'], 'Financijska agencija') !== false ||
                      strpos($signatureInfo['issuer'], 'FINA') !== false)) {
                $isFINA = true;
            }
        }

        switch ($signatureStatus) {
            case 'valid':
                $badgeClass .= ' seup-signature-valid';
                $icon = 'fas fa-certificate';

                // Build multi-line tooltip
                $tooltipLines = [];
                $tooltipLines[] = 'DIGITALNO POTPISAN DOKUMENT';
                $tooltipLines[] = '';

                if ($isFINA) {
                    $tooltipLines[] = '🏛️ FINA Certifikat (Kvalificirani potpis)';
                    $text = 'FINA Potpisan';
                } else {
                    $tooltipLines[] = '✓ Valjan digitalni potpis';
                    $text = 'Potpisan';
                }

                if ($signerName) {
                    $tooltipLines[] = 'Potpisnik: ' . $signerName;
                }

                if ($signatureDate) {
                    $formattedDate = date('d.m.Y H:i', strtotime($signatureDate));
                    $tooltipLines[] = 'Datum potpisa: ' . $formattedDate;
                }

                if (is_array($signatureInfo)) {
                    if (isset($signatureInfo['issuer'])) {
                        $tooltipLines[] = 'Izdavatelj: ' . $signatureInfo['issuer'];
                    }
                    if (isset($signatureInfo['issuer_unit'])) {
                        $tooltipLines[] = 'Jedinica: ' . $signatureInfo['issuer_unit'];
                    }
                    if (isset($signatureInfo['serial_number'])) {
                        $tooltipLines[] = 'Serijski broj: ' . $signatureInfo['serial_number'];
                    }
                    if (isset($signatureInfo['country'])) {
                        $tooltipLines[] = 'Država: ' . $signatureInfo['country'];
                    }
                }

                $title = implode('&#10;', array_map('htmlspecialchars', $tooltipLines));
                break;

            case 'invalid':
                $badgeClass .= ' seup-signature-invalid';
                $icon = 'fas fa-exclamation-triangle';
                $title = 'NEVALJAN DIGITALNI POTPIS&#10;&#10;Potpis nije moguće verificirati ili je narušen integritet dokumenta.';
                $text = 'Nevaljan';
                break;

            case 'expired':
                $badgeClass .= ' seup-signature-expired';
                $icon = 'fas fa-clock';
                $title = 'ISTEKAO DIGITALNI POTPIS&#10;&#10;Certifikat potpisa je istekao.';
                $text = 'Istekao';
                break;

            default:
                $badgeClass .= ' seup-signature-unknown';
                $icon = 'fas fa-question-circle';
                $title = 'NEPOZNAT STATUS POTPISA&#10;&#10;Potpis je detektiran ali nije moguće utvrditi valjanost.';
                $text = 'Nepoznato';
                break;
        }

        return '<span class="' . $badgeClass . '" title="' . $title . '">' .
               '<i class="' . $icon . '"></i> ' . $text .
               '</span>';
    }

    /**
     * Auto-scan file for signature when uploading
     */
    public static function autoScanOnUpload($db, $conf, $filePath, $ecmFileId)
    {
        // Only scan PDF files
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            return ['success' => true, 'message' => 'Not a PDF file'];
        }

        // Ensure columns exist
        self::ensureDigitalSignatureColumns($db);

        // Detect signature
        $signatureData = self::detectPDFSignature($filePath);
        
        // Update database
        $updated = self::updateECMFileSignature($db, $ecmFileId, $signatureData);
        
        if ($signatureData['has_signature']) {
            dol_syslog("Digital signature detected in uploaded file: " . basename($filePath), LOG_INFO);
        }

        return [
            'success' => true,
            'has_signature' => $signatureData['has_signature'],
            'signature_info' => $signatureData['signature_info'] ?? null,
            'updated' => $updated
        ];
    }

    /**
     * Validate signature against known CAs
     */
    public static function validateSignature($signatureInfo)
    {
        if (!$signatureInfo || !is_array($signatureInfo)) {
            return 'unknown';
        }

        // Check for FINA (Croatian qualified certificates)
        if (isset($signatureInfo['ca_type']) && $signatureInfo['ca_type'] === 'FINA') {
            return 'valid';
        }

        // Check for other known CAs
        $trustedCAs = [
            'Financijska agencija',
            'FINA',
            'Adobe',
            'DocuSign',
            'GlobalSign',
            'DigiCert'
        ];

        if (isset($signatureInfo['issuer'])) {
            foreach ($trustedCAs as $ca) {
                if (stripos($signatureInfo['issuer'], $ca) !== false) {
                    return 'valid';
                }
            }
        }

        return 'unknown';
    }

    /**
     * Get detailed signature information for display
     */
    public static function getSignatureDetails($signatureInfo)
    {
        if (!$signatureInfo) {
            return null;
        }

        if (is_string($signatureInfo)) {
            $signatureInfo = json_decode($signatureInfo, true);
        }

        if (!is_array($signatureInfo)) {
            return null;
        }

        $details = [];
        
        if (isset($signatureInfo['signer_name'])) {
            $details['Potpisnik'] = $signatureInfo['signer_name'];
        }
        
        if (isset($signatureInfo['organization'])) {
            $details['Organizacija'] = $signatureInfo['organization'];
        }
        
        if (isset($signatureInfo['issuer'])) {
            $details['Izdavatelj'] = $signatureInfo['issuer'];
        }
        
        if (isset($signatureInfo['signature_date'])) {
            $details['Datum potpisa'] = $signatureInfo['signature_date'];
        }
        
        if (isset($signatureInfo['ca_type'])) {
            $details['Tip certifikata'] = $signatureInfo['ca_type'];
        }
        
        if (isset($signatureInfo['is_qualified']) && $signatureInfo['is_qualified']) {
            $details['Kvalificirani'] = 'Da';
        }

        return $details;
    }
}