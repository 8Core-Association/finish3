<?php
/**
 * Instalacijski script za SEUP obavijesti modul
 *
 * Ovaj script automatski kreira tablice s ispravnim prefix-om
 * Može se pokrenuti kroz Dolibarr admin interface ili direktno
 */

// Učitaj Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

// Provjeri admin prava
if (!$user->admin) {
    accessforbidden('Samo administratori mogu instalirati modul.');
}

header('Content-Type: text/html; charset=UTF-8');

echo "<h1>SEUP Obavijesti - Instalacija Database Tablica</h1>";
echo "<hr>";

// Učitaj SQL file
$sql_file = __DIR__ . '/llx_seup_obavijesti.sql';
if (!file_exists($sql_file)) {
    die("❌ SQL datoteka ne postoji: $sql_file");
}

$sql_content = file_get_contents($sql_file);

// NAPOMENA: Koristi fiksni prefix "a_" (admin tablice), bez zamjene
echo "<h2>Database Prefix: <code>a_</code> (fiksni admin prefix)</h2>";
echo "<hr>";

// Razdvoji SQL naredbe
$sql_statements = array_filter(
    array_map('trim', explode(';', $sql_content)),
    function($stmt) {
        return !empty($stmt) && substr(trim($stmt), 0, 2) !== '--';
    }
);

$success_count = 0;
$error_count = 0;

echo "<h2>Izvršavanje SQL Naredbi:</h2>";
echo "<pre>";

foreach ($sql_statements as $sql) {
    if (empty($sql)) continue;

    // Prikaži SQL (skraćenu verziju)
    $sql_preview = substr($sql, 0, 100) . (strlen($sql) > 100 ? '...' : '');
    echo "\n📝 " . htmlspecialchars($sql_preview) . "\n";

    $resql = $db->query($sql);

    if ($resql) {
        echo "✅ SUCCESS\n";
        $success_count++;
    } else {
        echo "❌ ERROR: " . $db->lasterror() . "\n";
        $error_count++;
    }
}

echo "</pre>";
echo "<hr>";

// Provjeri kreirane tablice
echo "<h2>Provjera Kreiranje Tablica:</h2>";

$tables_to_check = array(
    'a_seup_obavijesti',
    'a_seup_obavijesti_procitane'
);

foreach ($tables_to_check as $table) {
    $sql = "SHOW TABLES LIKE '" . $table . "'";
    $resql = $db->query($sql);

    if ($resql && $db->num_rows($resql) > 0) {
        echo "✅ Tablica <code>$table</code> uspješno kreirana<br>";
    } else {
        echo "❌ Tablica <code>$table</code> NE postoji!<br>";
    }
}

echo "<hr>";

// Statistika
echo "<h2>Rezultati Instalacije:</h2>";
echo "✅ Uspješno izvršenih: <strong>$success_count</strong><br>";
echo "❌ Greške: <strong>$error_count</strong><br>";

if ($error_count == 0) {
    echo "<br><div style='padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; color: #155724;'>";
    echo "🎉 <strong>Instalacija uspješno završena!</strong> Sve tablice su kreirane.";
    echo "</div>";
} else {
    echo "<br><div style='padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24;'>";
    echo "⚠️ <strong>Instalacija djelomično uspjela.</strong> Provjerite greške iznad.";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='" . dol_buildpath('/custom/seup/seupindex.php', 1) . "'>Povratak na SEUP index</a></p>";
echo "<p><a href='" . dol_buildpath('/custom/seup/pages/obavijesti_admin.php', 1) . "'>Admin stranica obavijesti</a></p>";

$db->close();
