<?php

class ObavijestHelper
{
    private $db;
    private $user;

    public function __construct($db, $user)
    {
        $this->db = $db;
        $this->user = $user;
    }

    public function getAktivneObavijesti()
    {
        $sql = "SELECT rowid, naslov, sadrzaj, tip, vanjski_link, datum_kreiranja
                FROM " . MAIN_DB_PREFIX . "a_seup_obavijesti
                WHERE aktivan = 1
                ORDER BY datum_kreiranja DESC";

        $resql = $this->db->query($sql);
        $obavijesti = array();

        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $obavijesti[] = array(
                    'rowid' => $obj->rowid,
                    'naslov' => $obj->naslov,
                    'sadrzaj' => $obj->sadrzaj,
                    'tip' => $obj->tip,
                    'vanjski_link' => $obj->vanjski_link,
                    'datum_kreiranja' => $obj->datum_kreiranja,
                    'procitana' => $this->isObavijestProcitana($obj->rowid)
                );
            }
        }

        return $obavijesti;
    }

    public function getNeprocitaneObavijesti()
    {
        $obavijesti = $this->getAktivneObavijesti();
        return array_filter($obavijesti, function($obavijest) {
            return !$obavijest['procitana'];
        });
    }

    public function getBrojNeprocitanih()
    {
        return count($this->getNeprocitaneObavijesti());
    }

    public function isObavijestProcitana($obavijest_id)
    {
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "a_seup_obavijesti_procitane
                WHERE fk_user = " . ((int) $this->user->id) . "
                AND fk_obavijest = " . ((int) $obavijest_id);

        $resql = $this->db->query($sql);

        if ($resql) {
            return $this->db->num_rows($resql) > 0;
        }

        return false;
    }

    public function oznaciKaoProcitanu($obavijest_id)
    {
        if ($this->isObavijestProcitana($obavijest_id)) {
            return true;
        }

        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "a_seup_obavijesti_procitane
                (fk_user, fk_obavijest, datum_procitano)
                VALUES (" . ((int) $this->user->id) . ", " . ((int) $obavijest_id) . ", NOW())";

        $resql = $this->db->query($sql);

        return $resql ? true : false;
    }

    public function oznaciSveKaoProcitane()
    {
        $obavijesti = $this->getAktivneObavijesti();
        $success = true;

        foreach ($obavijesti as $obavijest) {
            if (!$obavijest['procitana']) {
                if (!$this->oznaciKaoProcitanu($obavijest['rowid'])) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    public function createObavijest($data)
    {
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "a_seup_obavijesti
                (naslov, sadrzaj, tip, vanjski_link, aktivan, fk_user_kreirao)
                VALUES (
                    '" . $this->db->escape($data['naslov']) . "',
                    '" . $this->db->escape($data['sadrzaj']) . "',
                    '" . $this->db->escape($data['tip']) . "',
                    " . ($data['vanjski_link'] ? "'" . $this->db->escape($data['vanjski_link']) . "'" : "NULL") . ",
                    " . ((int) $data['aktivan']) . ",
                    " . ((int) $this->user->id) . "
                )";

        $resql = $this->db->query($sql);

        return $resql ? $this->db->last_insert_id(MAIN_DB_PREFIX . "a_seup_obavijesti") : false;
    }

    public function updateObavijest($rowid, $data)
    {
        $sql = "UPDATE " . MAIN_DB_PREFIX . "a_seup_obavijesti SET
                naslov = '" . $this->db->escape($data['naslov']) . "',
                sadrzaj = '" . $this->db->escape($data['sadrzaj']) . "',
                tip = '" . $this->db->escape($data['tip']) . "',
                vanjski_link = " . ($data['vanjski_link'] ? "'" . $this->db->escape($data['vanjski_link']) . "'" : "NULL") . ",
                aktivan = " . ((int) $data['aktivan']) . "
                WHERE rowid = " . ((int) $rowid);

        return $this->db->query($sql) ? true : false;
    }

    public function deleteObavijest($rowid)
    {
        $sql = "DELETE FROM " . MAIN_DB_PREFIX . "a_seup_obavijesti
                WHERE rowid = " . ((int) $rowid);

        return $this->db->query($sql) ? true : false;
    }

    public function getAllObavijesti()
    {
        $sql = "SELECT rowid, naslov, sadrzaj, tip, vanjski_link, aktivan, datum_kreiranja, datum_izmjene
                FROM " . MAIN_DB_PREFIX . "a_seup_obavijesti
                ORDER BY datum_kreiranja DESC";

        $resql = $this->db->query($sql);
        $obavijesti = array();

        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $obavijesti[] = array(
                    'rowid' => $obj->rowid,
                    'naslov' => $obj->naslov,
                    'sadrzaj' => $obj->sadrzaj,
                    'tip' => $obj->tip,
                    'vanjski_link' => $obj->vanjski_link,
                    'aktivan' => $obj->aktivan,
                    'datum_kreiranja' => $obj->datum_kreiranja,
                    'datum_izmjene' => $obj->datum_izmjene
                );
            }
        }

        return $obavijesti;
    }

    public function getObavijest($rowid)
    {
        $sql = "SELECT rowid, naslov, sadrzaj, tip, vanjski_link, aktivan, datum_kreiranja
                FROM " . MAIN_DB_PREFIX . "a_seup_obavijesti
                WHERE rowid = " . ((int) $rowid);

        $resql = $this->db->query($sql);

        if ($resql && $obj = $this->db->fetch_object($resql)) {
            return array(
                'rowid' => $obj->rowid,
                'naslov' => $obj->naslov,
                'sadrzaj' => $obj->sadrzaj,
                'tip' => $obj->tip,
                'vanjski_link' => $obj->vanjski_link,
                'aktivan' => $obj->aktivan,
                'datum_kreiranja' => $obj->datum_kreiranja
            );
        }

        return null;
    }
}
