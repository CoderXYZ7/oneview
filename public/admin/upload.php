<?php
require_once '../../private/functions.php';
check_auth();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $id = generate_id();
        $filename = basename($file['name']);
        $target_path = UPLOADS_DIR . $id . '_' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $lock_on_access = isset($_POST['lock_on_access']);
            $max_minutes = (int)$_POST['max_minutes'];
            
            $file_data = [
                'id' => $id,
                'filename' => $filename,
                'path' => $target_path,
                'locked' => false,
                'lock_time' => 0,
                'max_minutes' => $max_minutes,
                'lock_on_access' => $lock_on_access,
                'mime_type' => mime_content_type($target_path) // Store mime type for serving
            ];
            
            add_file($file_data);
            header('Location: index.php');
            exit;
        } else {
            $error = "Failed to save file.";
        }
    } else {
        $error = "Upload error: " . $file['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload File</title>
    <style>
        body { font-family: sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="number"] { width: 100%; padding: 8px; box-sizing: border-box; }
        .btn { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #666; text-decoration: none; }
    </style>
</head>
<body>
    <a href="index.php" class="back-link">&larr; Back to Dashboard</a>
    
    <h1>Upload File</h1>
    
    <?php if ($error): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    
    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label>Select File</label>
            <input type="file" name="file" required>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="lock_on_access" value="1" checked>
                Lock immediately on first access (page load / play)
            </label>
        </div>
        
        <div class="form-group">
            <label>Lock Timeout (minutes)</label>
            <input type="number" name="max_minutes" value="<?php echo DEFAULT_LOCK_TIMEOUT; ?>" min="0">
            <small>Set to 0 to disable timeout (manual or immediate lock only)</small>
        </div>
        
        <button type="submit" class="btn">Upload & Generate Link</button>
    </form>
</body>
</html>
