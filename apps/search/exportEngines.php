<?php

require __DIR__ . '/connect-db.php';
require __DIR__ . '/engineTransfer.php';

$engines = $link->pdo()->query(
    'SELECT "site name", identifier, "old-identifier", "url-prefix", "url-suffix",
            baseurl, nsfw, hidden FROM searcher_engines ORDER BY id'
)->fetchAll(PDO::FETCH_ASSOC);
$payload = json_encode(
    ['format' => ENGINE_EXPORT_FORMAT, 'engines' => $engines],
    JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
);

header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="exportedEngines.html"');
header('Cache-Control: private, no-store');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Smart Search engine export</title>
</head>
<body>
    <h1>Smart Search engine export</h1>
    <p>To restore this file, choose <strong>Import engines</strong> in Smart Search.</p>
    <p><?= count($engines) ?> engines</p>
    <ul>
        <?php foreach ($engines as $engine): ?>
            <li><?= engine_html($engine['site name']) ?><?= $engine['hidden'] ? ' (Archived)' : '' ?></li>
        <?php endforeach ?>
    </ul>
    <script type="application/json" id="smart-search-engines"><?= $payload ?></script>
</body>
</html>
