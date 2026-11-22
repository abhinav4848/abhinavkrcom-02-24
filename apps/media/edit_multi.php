<?php
session_start();
require_once __DIR__ . '/includes/multi-upload.php';

$pending = pending_media_uploads();

$requestType = $_POST['request_type'] ?? '';
if (in_array($requestType, ['add_entry', 'delete_upload'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    $filename = $_POST['filename_final'] ?? '';
    if (!is_string($filename) || !isset($pending[$filename])) {
        echo json_encode(['error' => 'missing']);
        exit;
    }

    $temporaryFile = MEDIA_PENDING_DIRECTORY . $filename;
    if ($requestType === 'delete_upload') {
        $deleted = @unlink($temporaryFile);
        if (!$deleted) {
            http_response_code(500);
        }
        echo json_encode(['error' => $deleted ? '' : 'delete']);
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    if ($title === '') {
        echo json_encode(['error' => 'title']);
        exit;
    }

    require __DIR__ . '/includes/connect-db.php';
    require_once __DIR__ . '/includes/compareImages.php';

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
        $check = $db->prepare('SELECT id, filename_final FROM media_entries WHERE hash = ? LIMIT 1');
        $check->execute([$hash]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);
        if ($existing !== false) {
            $db->rollBack();
            unlink($temporaryFile);
            echo json_encode([
                'error' => 'exists',
                'link' => (int) $existing['id'],
                'media_url' => 'storage/files/' . rawurlencode($existing['filename_final']),
            ]);
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
        echo json_encode(['error' => '', 'link' => $id, 'media_url' => 'storage/files/' . rawurlencode($filename)]);
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
        <div id="review-status" class="d-none alert alert-success" role="status"></div>
        <p id="empty-review" class="d-none">No uploads waiting for review. <a href="multifile_uploader.php">Upload more files</a></p>
        <div class="row">
            <?php foreach ($pending as $filename => $originalName) {
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $isVideo = in_array($extension, ['mp4', 'webm'], true);
                $previewUrl = 'storage/tmpfiles/' . rawurlencode($filename);
                $originalTitle = pathinfo($originalName, PATHINFO_FILENAME);
                ?>
            <div class="media-review-item col-md-6 col-xl-4 mb-3">
                <div class="card h-100">
                    <?php if ($isVideo) { ?>
                    <div class="embed-responsive embed-responsive-16by9 bg-dark">
                        <video class="embed-responsive-item" src="<?= $previewUrl ?>" controls playsinline preload="none"></video>
                    </div>
                    <?php } else { ?>
                    <a class="media-file-link d-block" href="<?= $previewUrl ?>" target="_blank" rel="noopener" aria-label="Open image in a new tab">
                        <img src="<?= $previewUrl ?>" class="card-img-top" alt="">
                    </a>
                    <?php } ?>
                    <div class="card-body">
                        <p class="small text-muted text-break"><?= htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if ($isVideo) { ?>
                        <a class="media-file-link btn btn-outline-primary btn-sm mb-3" href="<?= $previewUrl ?>" target="_blank" rel="noopener">Play video in a new tab</a>
                        <?php } ?>
                        <form class="media-save" method="post">
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
                            <button type="submit" name="request_type" value="add_entry" class="btn btn-primary">Save to library</button>
                            <button type="submit" name="request_type" value="delete_upload" class="btn btn-outline-danger" formnovalidate>Delete upload</button>
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
        var item = form.closest('.media-review-item');
        var submitter = event.originalEvent && event.originalEvent.submitter;
        var action = submitter ? submitter.value : 'add_entry';
        var deleting = action === 'delete_upload';
        if (deleting && !window.confirm('Delete this pending upload?')) {
            return;
        }

        var buttons = form.find('button');
        var button = form.find('button[value="' + action + '"]');
        var buttonText = button.text();
        var status = form.find('.save-status');
        var submitData = form.serializeArray();
        submitData.push({name: 'request_type', value: action});

        buttons.prop('disabled', true);
        button.text(deleting ? 'Deleting…' : 'Saving…');
        status.removeClass('alert alert-success alert-info alert-danger').empty();

        $.post('edit_multi.php', submitData, null, 'json')
            .done(function (result) {
                if (deleting && result.error === '') {
                    item.remove();
                    $('#review-status').removeClass('d-none').text('Upload deleted.');
                    if (!$('.media-review-item').length) {
                        $('#empty-review').removeClass('d-none');
                    }
                    return;
                }
                if (result.error === '' || result.error === 'exists') {
                    var existing = result.error === 'exists';
                    status.addClass('alert ' + (existing ? 'alert-info' : 'alert-success'))
                        .text(existing ? 'Already in your library. ' : 'Saved to your library. ')
                        .append($('<a>', {class: 'alert-link', text: 'View library entry', href: 'view.php?id=' + result.link}));
                    item.find('video, img.card-img-top').attr('src', result.media_url);
                    item.find('.media-file-link').attr('href', result.media_url);
                    form.find(':input').prop('disabled', true);
                    form.find('button[value="delete_upload"]').remove();
                    button.text(existing ? 'Already saved' : 'Saved');
                    return;
                }

                showError(result.error === 'title'
                    ? 'Enter a title before saving.'
                    : 'This upload is no longer pending. Refresh the page to see the remaining files.');
            })
            .fail(function () {
                showError(deleting ? 'The upload could not be deleted. Try again.' : 'The file could not be saved. Try again.');
            });

        function showError(message) {
            status.addClass('alert alert-danger').text(message);
            buttons.prop('disabled', false);
            button.text(buttonText);
        }
    });
    </script>
</body>

</html>
