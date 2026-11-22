<?php
session_start();
require_once __DIR__ . '/includes/multi-upload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pending = pending_media_uploads();
    $_SESSION['files'] = array_keys($pending);
    $_SESSION['filename_original_name'] = array_values($pending);

    $uploadCount = 0;
    $uploadErrors = [];
    $uploads = $_FILES['upload'] ?? [];
    foreach ($uploads['name'] ?? [] as $index => $name) {
        $uploadError = $uploads['error'][$index];
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($uploadError !== UPLOAD_ERR_OK) {
            $uploadErrors[] = "$name could not be uploaded. Check the file size and try again.";
            continue;
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($uploads['type'][$index], MEDIA_UPLOAD_TYPES[$extension] ?? [], true)) {
            $uploadErrors[] = "$name is unsupported. Choose an MP4, WebM, JPEG, PNG, or GIF file.";
            continue;
        }

        $filename = time() . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        if (!is_dir(MEDIA_PENDING_DIRECTORY)) {
            mkdir(MEDIA_PENDING_DIRECTORY, 0755, true);
        }
        if (!move_uploaded_file($uploads['tmp_name'][$index], MEDIA_PENDING_DIRECTORY . $filename)) {
            $uploadErrors[] = "$name could not be uploaded. Try again.";
            continue;
        }
        $_SESSION['files'][] = $filename;
        $_SESSION['filename_original_name'][] = $name;
        $uploadCount++;
    }

    if ($uploadCount === 0 && $uploadErrors === []) {
        $uploadErrors[] = 'No files were received. Select files and try again. If the batch is large, upload fewer files at a time.';
    }
    $_SESSION['media_upload_notice'] = [$uploadCount, $uploadErrors];
    header('Location: multifile_uploader.php', true, 303);
    exit;
}

[$uploadCount, $uploadErrors] = $_SESSION['media_upload_notice'] ?? [0, []];
unset($_SESSION['media_upload_notice']);
$pendingCount = count(pending_media_uploads());
?>
<!doctype html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.95, shrink-to-fit=no">
    <meta name="theme-color" content="#28a745">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="includes/libraries/bootstrap.min.css">

    <title>Upload Multiple Files</title>

    <style type="text/css">
    </style>
</head>

<body>
    <?php include('includes/navbar.php');?>
    <div class="container py-4">
        <h1 class="h3 mb-3">Upload multiple files</h1>
        <?php if ($uploadCount > 0) { ?>
        <div class="alert alert-success" role="status">
            <p class="font-weight-bold mb-1"><?= $uploadCount ?> <?= $uploadCount === 1 ? 'file' : 'files' ?> uploaded successfully.</p>
            <p class="mb-0">Review and save the files to add them to your library.</p>
        </div>
        <?php } ?>
        <?php if ($uploadErrors !== []) { ?>
        <div class="alert alert-warning" role="alert">
            <p class="font-weight-bold mb-1">Upload issues</p>
            <ul class="mb-0 pl-4">
                <?php foreach ($uploadErrors as $message) { ?>
                <li><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></li>
                <?php } ?>
            </ul>
        </div>
        <?php } ?>
        <?php if ($pendingCount > 0) { ?>
        <a class="btn btn-success mb-4" href="edit_multi.php">Review and save <?= $pendingCount ?> <?= $pendingCount === 1 ? 'file' : 'files' ?></a>
        <?php } ?>
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="upload-files">Choose videos or images</label>
                <input type="file" class="form-control-file" id="upload-files" name="upload[]"
                    accept=".mp4,.webm,.jpg,.jpeg,.png,.gif" multiple required>
            </div>
            <button class="btn btn-primary" type="submit">Upload files</button>
        </form>
    </div>

    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="includes/libraries/jquery-3.2.1.min.js">
    </script>
    <script src="includes/libraries/popper.min.js">
    </script>
    <script src="includes/libraries/bootstrap.min.js">
    </script>
</body>

</html>
