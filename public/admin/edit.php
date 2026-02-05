<?php
require_once '../../private/functions.php';
check_auth();

$id = $_GET['id'] ?? '';
$file = get_file($id);

if (!$file) {
    die("File not found.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("edit.php: Received POST for " . $id);
    error_log("edit.php: POST Data: " . print_r($_POST, true));

    $updates = [
        'lock_on_access' => isset($_POST['lock_on_access']),
        'allow_download' => isset($_POST['allow_download']),
        'max_minutes' => (int)$_POST['max_minutes']
    ];
    
    update_file_options($id, $updates);
    $success = "File updated successfully.";
    $file = get_file($id); // Reload
} else {
    error_log("edit.php: Loaded page for " . $id);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit File</title>
    <style>
        body { font-family: sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="number"] { width: 100%; padding: 8px; box-sizing: border-box; }
        .btn { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .success { color: green; margin-bottom: 10px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #666; text-decoration: none; }
    </style>
</head>
<body>
    <a href="index.php" class="back-link">&larr; Back to Dashboard</a>
    
    <h1>Edit File Options</h1>
    <h3><?php echo htmlspecialchars($file['filename']); ?></h3>
    
    <?php if ($success): ?>
        <p class="success"><?php echo $success; ?></p>
    <?php endif; ?>
    
    <form method="post">
        <div class="form-group">
            <label>
                <input type="checkbox" name="lock_on_access" value="1" <?php echo $file['lock_on_access'] ? 'checked' : ''; ?>>
                Lock immediately on first access (page load / play)
            </label>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="allow_download" value="1" <?php echo isset($file['allow_download']) && $file['allow_download'] ? 'checked' : ''; ?>>
                Allow Download / Save
            </label>
        </div>
        
        <div class="form-group">
            <label>Lock Timeout (minutes)</label>
            <input type="number" name="max_minutes" value="<?php echo $file['max_minutes']; ?>" min="0">
            <small>Set to 0 to disable timeout (manual or immediate lock only)</small>
        </div>
        
        <button type="submit" class="btn">Update Options</button>
    </form>
</body>
</html>
