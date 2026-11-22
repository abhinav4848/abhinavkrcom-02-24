<?php
session_start();
require_once __DIR__ . '/includes/multi-upload.php';

$pending = pending_media_uploads();

if (($_POST['request_type'] ?? '') === 'add_entry') {
    header('Content-Type: application/json; charset=utf-8');
    $filename = $_POST['filename_final'] ?? '';
    $title = trim($_POST['title'] ?? '');
    if (!isset($pending[$filename]) || $title === '') {
        echo json_encode(['error' => $title === '' ? 'title' : 'missing']);
        exit;
    }

    require __DIR__ . '/includes/connect-db.php';
    require_once __DIR__ . '/includes/compareImages.php';

    $temporaryFile = MEDIA_PENDING_DIRECTORY . $filename;
    $libraryFile = MEDIA_LIBRARY_DIRECTORY . $filename;
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $db = $link->pdo();
    $moved = false;

    try {
        $hash = sha1_file($temporaryFile);
        if ($hash === false) {
            throw new RuntimeException('Could not read the pending upload.');
        }

        $db->beginTransaction();
        $check = $db->prepare('SELECT id FROM media_entries WHERE hash = ? LIMIT 1');
        $check->execute([$hash]);
        $existingId = $check->fetchColumn();
        if ($existingId !== false) {
            $db->rollBack();
            unlink($temporaryFile);
            echo json_encode(['error' => 'exists', 'link' => (int) $existingId]);
            exit;
        }

        $similarHash = in_array($extension, ['jpg', 'jpeg', 'png'], true)
            ? (new compareImages($temporaryFile))->getHasString()
            : '0';
        $insert = $db->prepare(
            'INSERT INTO media_entries
                (title, description, filename_final, filename_original_name, filename_ext, hash, similar_hash, upload_time)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $title, $_POST['description'] ?? '', $filename, $pending[$filename], $extension,
            $hash, $similarHash, date('Y-m-d H:i:s'),
        ]);
        $id = (int) $db->lastInsertId();

        if (!is_dir(MEDIA_LIBRARY_DIRECTORY)) {
            mkdir(MEDIA_LIBRARY_DIRECTORY, 0755, true);
        }
        if (is_file($libraryFile) || !rename($temporaryFile, $libraryFile)) {
            throw new RuntimeException('Could not move the upload into the library.');
        }
        $moved = true;
        $db->commit();
        echo json_encode(['error' => '', 'link' => $id]);
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($moved) {
            rename($libraryFile, $temporaryFile);
        }
        error_log('Media upload save failed: ' . $error->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'save']);
    }
    exit;
}

if ($pending === []) {
    header('Location: multifile_uploader.php');
    exit;
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="theme-color" content="#28a745">
    <link rel="stylesheet" href="includes/libraries/bootstrap.min.css">
    <title>Review uploaded files</title>
</head>

<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <main class="container py-4">
        <h1 class="h3 mb-3">Review uploaded files</h1>
        <p>Save each file to add it to your library.</p>
        <a class="btn btn-outline-primary mb-3" href="view-all.php?type=vids">View Vids</a>
        <div class="row">
            <?php foreach ($pending as $filename => $originalName) {
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $previewUrl = 'storage/tmpfiles/' . rawurlencode($filename);
                $originalTitle = pathinfo($originalName, PATHINFO_FILENAME);
                ?>
            <div class="col-md-6 col-xl-4 mb-3">
                <div class="card h-100">
                    <?php if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'], true)) { ?>
                    <img src="<?= $previewUrl ?>" class="card-img-top" alt="">
                    <?php } else { ?>
                    <a href="<?= $previewUrl ?>" target="_blank" rel="noopener" class="btn btn-light">Preview video</a>
                    <?php } ?>
                    <div class="card-body">
                        <p class="small text-muted text-break"><?= htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') ?></p>
                        <form class="media-save" method="post">
                            <input type="hidden" name="request_type" value="add_entry">
                            <input type="hidden" name="filename_final" value="<?= htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') ?>">
                            <label class="w-100">
                                Title
                                <input type="text" name="title" class="form-control" required
                                    value="<?= htmlspecialchars($originalTitle, ENT_QUOTES, 'UTF-8') ?>">
                            </label>
                            <label class="w-100">
                                Description
                                <textarea name="description" class="form-control" rows="2"></textarea>
                            </label>
                            <button type="submit" class="btn btn-primary">Save to library</button>
                            <div class="save-status mt-3 mb-0" role="status"></div>
                        </form>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
    </main>
    <script src="includes/libraries/jquery-3.2.1.min.js"></script>
    <script src="includes/libraries/popper.min.js"></script>
    <script src="includes/libraries/bootstrap.min.js"></script>
    <script>
    $('.media-save').on('submit', function (event) {
        event.preventDefault();
        var form = $(this);
        var button = form.find('button');
        var status = form.find('.save-status');
        var submitData = form.serialize();

        button.prop('disabled', true).text('Saving…');
        status.removeClass('alert alert-success alert-info alert-danger').empty();

        $.post('edit_multi.php', submitData, null, 'json')
            .done(function (result) {
                if (result.error === '' || result.error === 'exists') {
                    var existing = result.error === 'exists';
                    status.addClass('alert ' + (existing ? 'alert-info' : 'alert-success'))
                        .text(existing ? 'Already in your library. ' : 'Saved to your library. ')
                        .append($('<a>', {class: 'alert-link', text: 'View file', href: 'view.php?id=' + result.link}));
                    form.find(':input').prop('disabled', true);
                    button.text(existing ? 'Already saved' : 'Saved');
                    return;
                }

                showError(result.error === 'title'
                    ? 'Enter a title before saving.'
                    : 'This upload is no longer pending. Refresh the page to see the remaining files.');
            })
            .fail(function () {
                showError('The file could not be saved. Try again.');
            });

        function showError(message) {
            status.addClass('alert alert-danger').text(message);
            button.prop('disabled', false).text('Save to library');
        }
    });
    </script>
</body>

</html>
