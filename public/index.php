<?php
require_once '../private/functions.php';

$id = $_GET['id'] ?? '';
$action = $_GET['action'] ?? 'page';

if (!$id) {
    http_response_code(404);
    die("File not found.");
}

$file = get_file($id);

if (!$file) {
    http_response_code(404);
    die("File not found.");
}

// Check permanent lock
if ($file['locked']) {
    http_response_code(403);
    die("<h1>Access Unavailable</h1><p>This file has been locked.</p>");
}

// Check Timeout Lock
if ($file['lock_time'] > 0 && $file['max_minutes'] > 0) {
    $expiration_time = $file['lock_time'] + ($file['max_minutes'] * 60);
    if (time() > $expiration_time) {
        update_file_lock($id, true); // Finalize lock
        http_response_code(403);
        die("<h1>Access Unavailable</h1><p>This file has expired.</p>");
    }
}

$mime = $file['mime_type'] ?? 'application/octet-stream';
$is_media = strpos($mime, 'video/') === 0 || strpos($mime, 'audio/') === 0;

// --- ACTIONS ---

// 1. Lock Trigger API (Called by JS on media end)
if ($action === 'lock') {
    if ($file['lock_on_access']) {
        update_file_lock($id, true);
        echo "Locked";
    }
    exit;
}

// 2. Stream/Download Action
if ($action === 'stream') {
    $path = $file['path'];
    
    // Start Lock Timer if not started
    if ($file['lock_time'] == 0) {
        update_file_lock($id, false, time());
    }
    
    // Immediate Lock for Non-Media (One-time download)
    if (!$is_media && $file['lock_on_access']) {
        update_file_lock($id, true, time());
        // NOTE: If we lock *before* serving, serving might be okay in this script execution, 
        // but subsequent requests fail.
        // For downloads, this is fine.
    }

    if (!file_exists($path)) {
        http_response_code(404);
        die("File content missing.");
    }

    // Serve File
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    
    if (!$is_media) {
        // Force download for non-media to avoid browser preview locking issues
        header('Content-Disposition: attachment; filename="' . $file['filename'] . '"');
    }
    
    // Simple readfile for now. For large media, range support would be better 
    // but requirements stick to "Favor simplicity" and "no frameworks".
    // Standard readfile is blocking and memory intensive for large files, but simplest.
    readfile($path);
    exit;
}

// 3. Page View (Default)
// Don't lock just on page load, wait for user interaction to be safe against crawlers/previews.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View File</title>
    <style>
        body { font-family: sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; background: #222; color: #fff; margin: 0; }
        .container { text-align: center; max-width: 90%; }
        h1 { margin-bottom: 20px; }
        .btn { display: inline-block; padding: 15px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; font-size: 1.2rem; cursor: pointer; border: none; }
        .btn:hover { background: #0056b3; }
        video, audio { max-width: 100%; max-height: 80vh; box-shadow: 0 0 20px rgba(0,0,0,0.5); }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($is_media): ?>
            <h1>Media Access</h1>
            <!-- 
               Autoplay is often blocked by browsers properly without intersection.
               We let user click play.
            -->
            <?php 
                $tag = strpos($mime, 'video/') === 0 ? 'video' : 'audio';
            ?>
            <<?php echo $tag; ?> controls id="mediaPlayer">
                <source src="?id=<?php echo $id; ?>&action=stream" type="<?php echo $mime; ?>">
                Your browser does not support the element.
            </<?php echo $tag; ?>>
            
            <script>
                const player = document.getElementById('mediaPlayer');
                const fileId = "<?php echo $id; ?>";
                
                // On Ended -> Lock
                player.addEventListener('ended', function() {
                    fetch('?id=' + fileId + '&action=lock');
                });
                
                // On Play -> Ideally we ensure lock timer starts if not already
                // The stream request does this, but redundancy is good.
                player.addEventListener('play', function() {
                     // We rely on the stream request to start the timer
                });
            </script>
        <?php else: ?>
            <h1>File Access</h1>
            <p><?php echo htmlspecialchars($file['filename']); ?></p>
            <p><strong>Warning:</strong> Accessing this file may lock it permanently.</p>
            <a href="?id=<?php echo $id; ?>&action=stream" class="btn">View / Download File</a>
        <?php endif; ?>
    </div>
</body>
</html>
