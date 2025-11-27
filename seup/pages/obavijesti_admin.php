<?php

/**
 * Plaćena licenca
 * (c) 2025 Tomislav Galić <tomislav@8core.hr>
 * Suradnik: Marko Šimunović <marko@8core.hr>
 * Web: https://8core.hr
 * Kontakt: info@8core.hr | Tel: +385 099 851 0717
 * Sva prava pridržana. Ovaj softver je vlasnički i zabranjeno ga je
 * distribuirati ili mijenjati bez izričitog dopuštenia autora.
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

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
dol_include_once('/seup/class/obavijesti_helper.class.php');

if (!$user->admin) {
    accessforbidden();
}

// AUTO-KREIRANJE TABLICA AKO NE POSTOJE (fiksni prefix: a_)
$table_obavijesti = 'a_seup_obavijesti';
$table_procitane = 'a_seup_obavijesti_procitane';

// Provjeri da li tablica postoji
$sql_check = "SHOW TABLES LIKE '" . $table_obavijesti . "'";
$resql = $db->query($sql_check);

if (!$resql || $db->num_rows($resql) == 0) {
    // Tablica ne postoji - kreiraj je
    $sql_create_obavijesti = "
    CREATE TABLE IF NOT EXISTS " . $table_obavijesti . " (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $sql_create_procitane = "
    CREATE TABLE IF NOT EXISTS " . $table_procitane . " (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        fk_user INT NOT NULL,
        fk_obavijest INT NOT NULL,
        datum_procitano DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_obavijest (fk_user, fk_obavijest),
        INDEX idx_user (fk_user),
        INDEX idx_obavijest (fk_obavijest),
        CONSTRAINT fk_obavijest_procitana FOREIGN KEY (fk_obavijest)
            REFERENCES " . $table_obavijesti . "(rowid) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->query($sql_create_obavijesti);
    $db->query($sql_create_procitane);
}

$action = GETPOST('action', 'alpha');
$obavijest_id = GETPOST('id', 'int');

$obavijestHelper = new ObavijestHelper($db, $user);

$error = 0;
$message = '';

if ($action == 'create' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = array(
        'naslov' => GETPOST('naslov', 'alpha'),
        'sadrzaj' => GETPOST('sadrzaj', 'alpha'),
        'tip' => GETPOST('tip', 'alpha'),
        'vanjski_link' => GETPOST('vanjski_link', 'alpha'),
        'aktivan' => GETPOST('aktivan', 'int') ? 1 : 0
    );

    if (empty($data['naslov']) || empty($data['sadrzaj'])) {
        $error++;
        $message = 'Naslov i sadržaj su obavezni!';
    } else {
        $result = $obavijestHelper->createObavijest($data);
        if ($result) {
            $message = 'Obavijest uspješno kreirana!';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error++;
            $message = 'Greška pri kreiranju obavijesti!';
        }
    }
}

if ($action == 'update' && $_SERVER['REQUEST_METHOD'] == 'POST' && $obavijest_id) {
    $data = array(
        'naslov' => GETPOST('naslov', 'alpha'),
        'sadrzaj' => GETPOST('sadrzaj', 'alpha'),
        'tip' => GETPOST('tip', 'alpha'),
        'vanjski_link' => GETPOST('vanjski_link', 'alpha'),
        'aktivan' => GETPOST('aktivan', 'int') ? 1 : 0
    );

    if (empty($data['naslov']) || empty($data['sadrzaj'])) {
        $error++;
        $message = 'Naslov i sadržaj su obavezni!';
    } else {
        $result = $obavijestHelper->updateObavijest($obavijest_id, $data);
        if ($result) {
            $message = 'Obavijest uspješno ažurirana!';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error++;
            $message = 'Greška pri ažuriranju obavijesti!';
        }
    }
}

if ($action == 'delete' && $obavijest_id) {
    $result = $obavijestHelper->deleteObavijest($obavijest_id);
    if ($result) {
        $message = 'Obavijest uspješno obrisana!';
    } else {
        $error++;
        $message = 'Greška pri brisanju obavijesti!';
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$obavijesti = $obavijestHelper->getAllObavijesti();
$edit_obavijest = null;

if ($action == 'edit' && $obavijest_id) {
    $edit_obavijest = $obavijestHelper->getObavijest($obavijest_id);
}

llxHeader('', 'Upravljanje obavijestima', '');

print '<link href="../css/obavijesti.css" rel="stylesheet">';
?>

<div class="obavijesti-admin-container">
    <h1>Upravljanje obavijestima <span class="badge badge-info">Admin Only</span></h1>

    <?php if ($message): ?>
        <div class="alert <?php echo $error ? 'alert-danger' : 'alert-success'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="obavijesti-admin-form">
        <h2><?php echo $edit_obavijest ? 'Uredi obavijest' : 'Nova obavijest'; ?></h2>
        <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
            <input type="hidden" name="token" value="<?php echo newToken(); ?>">
            <input type="hidden" name="action" value="<?php echo $edit_obavijest ? 'update' : 'create'; ?>">
            <?php if ($edit_obavijest): ?>
                <input type="hidden" name="id" value="<?php echo $edit_obavijest['rowid']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="naslov">Naslov *</label>
                <input type="text" id="naslov" name="naslov" class="form-control"
                       value="<?php echo $edit_obavijest ? htmlspecialchars($edit_obavijest['naslov']) : ''; ?>"
                       required maxlength="255">
            </div>

            <div class="form-group">
                <label for="sadrzaj">Sadržaj *</label>
                <textarea id="sadrzaj" name="sadrzaj" class="form-control" rows="4" required><?php echo $edit_obavijest ? htmlspecialchars($edit_obavijest['sadrzaj']) : ''; ?></textarea>
            </div>

            <div class="form-group">
                <label for="tip">Tip obavijesti</label>
                <select id="tip" name="tip" class="form-control">
                    <option value="info" <?php echo ($edit_obavijest && $edit_obavijest['tip'] == 'info') ? 'selected' : ''; ?>>Info</option>
                    <option value="upozorenje" <?php echo ($edit_obavijest && $edit_obavijest['tip'] == 'upozorenje') ? 'selected' : ''; ?>>Upozorenje</option>
                    <option value="tutorial" <?php echo ($edit_obavijest && $edit_obavijest['tip'] == 'tutorial') ? 'selected' : ''; ?>>Tutorial</option>
                </select>
            </div>

            <div class="form-group">
                <label for="vanjski_link">Vanjski link (tutorial)</label>
                <input type="url" id="vanjski_link" name="vanjski_link" class="form-control"
                       value="<?php echo $edit_obavijest ? htmlspecialchars($edit_obavijest['vanjski_link']) : ''; ?>"
                       placeholder="https://example.com" maxlength="512">
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="aktivan" name="aktivan" value="1"
                           <?php echo (!$edit_obavijest || $edit_obavijest['aktivan']) ? 'checked' : ''; ?>>
                    Aktivna obavijest
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?php echo $edit_obavijest ? 'Ažuriraj' : 'Kreiraj'; ?>
                </button>
                <?php if ($edit_obavijest): ?>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">Odustani</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="obavijesti-admin-list">
        <h2>Postojeće obavijesti (<?php echo count($obavijesti); ?>)</h2>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Naslov</th>
                    <th>Tip</th>
                    <th>Vanjski link</th>
                    <th>Status</th>
                    <th>Kreirano</th>
                    <th>Akcije</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($obavijesti)): ?>
                    <tr>
                        <td colspan="7" class="text-center">Nema obavijesti</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($obavijesti as $obavijest): ?>
                        <tr class="<?php echo $obavijest['aktivan'] ? '' : 'obavijest-neaktivna'; ?>">
                            <td><?php echo $obavijest['rowid']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($obavijest['naslov']); ?></strong><br>
                                <small><?php echo htmlspecialchars(substr($obavijest['sadrzaj'], 0, 80)); ?>...</small>
                            </td>
                            <td>
                                <span class="badge badge-<?php
                                    echo $obavijest['tip'] == 'info' ? 'info' :
                                        ($obavijest['tip'] == 'upozorenje' ? 'warning' : 'success');
                                ?>">
                                    <?php echo ucfirst($obavijest['tip']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($obavijest['vanjski_link']): ?>
                                    <a href="<?php echo htmlspecialchars($obavijest['vanjski_link']); ?>" target="_blank" rel="noopener">
                                        Link <i class="fa fa-external-link"></i>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($obavijest['aktivan']): ?>
                                    <span class="badge badge-success">Aktivna</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Neaktivna</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo dol_print_date($db->jdate($obavijest['datum_kreiranja']), 'dayhour'); ?>
                            </td>
                            <td>
                                <a href="<?php echo $_SERVER['PHP_SELF'] . '?action=edit&id=' . $obavijest['rowid']; ?>"
                                   class="btn btn-sm btn-primary" title="Uredi">
                                    <i class="fa fa-edit"></i>
                                </a>
                                <a href="<?php echo $_SERVER['PHP_SELF'] . '?action=delete&id=' . $obavijest['rowid'] . '&token=' . newToken(); ?>"
                                   class="btn btn-sm btn-danger" title="Obriši"
                                   onclick="return confirm('Jeste li sigurni da želite obrisati ovu obavijest?');">
                                    <i class="fa fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
llxFooter();
$db->close();
