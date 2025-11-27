<?php
/**
 * Test stranica za testiranje obavijesti modula
 */

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
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
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

dol_include_once('/seup/class/obavijesti_helper.class.php');

header('Content-Type: text/html; charset=UTF-8');

echo "<h1>Test Obavijesti Modula</h1>";
echo "<hr>";

// Test 1: Database tablice
echo "<h2>1. Provjera Database Tablica</h2>";

$sql = "SHOW TABLES LIKE '" . MAIN_DB_PREFIX . "seup_obavijesti'";
$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
    echo "✅ Tablica <code>" . MAIN_DB_PREFIX . "seup_obavijesti</code> postoji<br>";
} else {
    echo "❌ Tablica <code>" . MAIN_DB_PREFIX . "seup_obavijesti</code> NE postoji!<br>";
    echo "<strong>Pokrenite SQL skriptu:</strong> <code>seup/sql/llx_seup_obavijesti.sql</code><br>";
}

$sql = "SHOW TABLES LIKE '" . MAIN_DB_PREFIX . "seup_obavijesti_procitane'";
$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
    echo "✅ Tablica <code>" . MAIN_DB_PREFIX . "seup_obavijesti_procitane</code> postoji<br>";
} else {
    echo "❌ Tablica <code>" . MAIN_DB_PREFIX . "seup_obavijesti_procitane</code> NE postoji!<br>";
}

echo "<hr>";

// Test 2: Helper klasa
echo "<h2>2. Test Helper Klase</h2>";

try {
    $obavijestHelper = new ObavijestHelper($db, $user);
    echo "✅ ObavijestHelper klasa uspješno instancirana<br>";

    $obavijesti = $obavijestHelper->getAllObavijesti();
    echo "✅ getAllObavijesti() radi - pronađeno: " . count($obavijesti) . " obavijesti<br>";

    $neprocitane = $obavijestHelper->getNeprocitaneObavijesti();
    echo "✅ getNeprocitaneObavijesti() radi - pronađeno: " . count($neprocitane) . " nepročitanih<br>";

    $broj = $obavijestHelper->getBrojNeprocitanih();
    echo "✅ getBrojNeprocitanih() radi - broj: " . $broj . "<br>";

} catch (Exception $e) {
    echo "❌ Greška: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// Test 3: Kreiranje test obavijesti
echo "<h2>3. Test Kreiranje Obavijesti</h2>";

if ($user->admin) {
    echo "✅ Korisnik je admin - može kreirati obavijesti<br>";

    $test_data = array(
        'naslov' => 'Test Obavijest - ' . date('Y-m-d H:i:s'),
        'sadrzaj' => 'Ovo je test obavijest kreirana automatski za testiranje sustava.',
        'tip' => 'info',
        'vanjski_link' => null,
        'aktivan' => 1
    );

    $result = $obavijestHelper->createObavijest($test_data);

    if ($result) {
        echo "✅ Test obavijest uspješno kreirana (ID: $result)<br>";
    } else {
        echo "❌ Greška pri kreiranju test obavijesti<br>";
    }
} else {
    echo "⚠️ Korisnik nije admin - ne može kreirati obavijesti<br>";
}

echo "<hr>";

// Test 4: AJAX endpoints
echo "<h2>4. Test AJAX Endpointa</h2>";

$ajax_url = DOL_URL_ROOT . '/custom/seup/class/autocomplete.php?action=get_obavijesti';
echo "📡 AJAX endpoint URL: <code>$ajax_url</code><br>";
echo "Testirajte u browseru ili AJAX pozivu<br>";

echo "<hr>";

// Test 5: Files
echo "<h2>5. Provjera Fajlova</h2>";

$files_to_check = array(
    '/seup/class/obavijesti_helper.class.php' => 'Helper klasa',
    '/seup/pages/obavijesti_admin.php' => 'Admin stranica',
    '/seup/css/obavijesti.css' => 'CSS stilovi',
    '/seup/js/obavijesti.js' => 'JavaScript',
    '/seup/sounds/notification.mp3' => 'Sound efekt',
    '/seup/sql/llx_seup_obavijesti.sql' => 'SQL skripta'
);

foreach ($files_to_check as $file => $desc) {
    $path = DOL_DOCUMENT_ROOT . '/custom' . $file;
    if (file_exists($path)) {
        echo "✅ $desc: <code>$file</code><br>";
    } else {
        echo "❌ $desc: <code>$file</code> NE POSTOJI!<br>";
    }
}

echo "<hr>";

// Test 6: User info
echo "<h2>6. Informacije o Korisniku</h2>";
echo "ID: " . $user->id . "<br>";
echo "Login: " . $user->login . "<br>";
echo "Admin: " . ($user->admin ? 'DA' : 'NE') . "<br>";

echo "<hr>";

// Test 7: Database connection
echo "<h2>7. Database Konekcija</h2>";
echo "✅ Database konekcija aktivna<br>";
echo "DB Host: " . $db->db->host_info . "<br>";
echo "DB Name: " . $db->database_name . "<br>";

echo "<hr>";
echo "<h2>✅ Testiranje Završeno</h2>";
echo "<p><a href='" . dol_buildpath('/custom/seup/seupindex.php', 1) . "'>Povratak na SEUP index</a></p>";
echo "<p><a href='" . dol_buildpath('/custom/seup/pages/obavijesti_admin.php', 1) . "'>Admin stranica obavijesti</a></p>";

$db->close();
