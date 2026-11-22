<?php

require __DIR__ . '/connect-db.php';
require __DIR__ . '/engineTransfer.php';

session_start();
$_SESSION['engine_import_token'] ??= bin2hex(random_bytes(32));
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!is_string($_POST['token'] ?? null) || !hash_equals($_SESSION['engine_import_token'], $_POST['token'])) {
            throw new InvalidArgumentException('Reload this page and try again.');
        }
        $file = $_FILES['engines'] ?? [];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $file['size'] > ENGINE_EXPORT_LIMIT) {
            throw new InvalidArgumentException('Choose a Smart Search HTML export up to 1 MB.');
        }
        $html = file_get_contents($file['tmp_name'], false, null, 0, ENGINE_EXPORT_LIMIT + 1);
        if ($html === false || trim($html) === '' || strlen($html) > ENGINE_EXPORT_LIMIT) {
            throw new InvalidArgumentException('Could not read the selected engine export.');
        }
        [$added, $skipped] = import_engine_export($link->pdo(), read_engine_export($html));
        $message = "Added $added engines. Skipped $skipped already present.";
    } catch (InvalidArgumentException $error) {
        http_response_code(400);
        $message = $error->getMessage();
    } catch (JsonException) {
        http_response_code(400);
        $message = 'This file contains invalid engine data.';
    } catch (PDOException $error) {
        http_response_code(500);
        error_log('Smart Search import database error: ' . $error->getCode());
        $message = 'Could not import the engines. The database changes were rolled back.';
    }
}
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: private, no-store');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Import engines | Smart Search</title>
    <style>
        body { max-width: 36rem; margin: 3rem auto; padding: 0 1.25rem; font: 1rem/1.65 system-ui, sans-serif; }
        label, input[type=file] { display: block; margin-bottom: .75rem; }
        button { font: inherit; padding: .4rem .75rem; }
    </style>
</head>
<body>
    <p><a href="index.php">Back to Smart Search</a></p>
    <h1>Import engines</h1>
    <p>Choose an engine export. Existing engines keep their current settings and archived engines stay archived.</p>
    <?php if ($message !== ''): ?>
        <p role="status"><?= engine_html($message) ?></p>
    <?php endif ?>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="token" value="<?= engine_html($_SESSION['engine_import_token']) ?>">
        <label for="engines">Smart Search export</label>
        <input type="file" name="engines" id="engines" accept=".html,.htm,text/html" required>
        <button type="submit">Import engines</button>
    </form>
</body>
</html>
