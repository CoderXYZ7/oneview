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
$allow_download = isset($file['allow_download']) ? $file['allow_download'] : true;

// Type Detection
$is_video = strpos($mime, 'video/') === 0;
$is_audio = strpos($mime, 'audio/') === 0;
// Note: We differentiate media for player handling, but they share logic
$is_media = $is_video || $is_audio;
$is_image = strpos($mime, 'image/') === 0;
$is_text  = strpos($mime, 'text/') === 0 || $mime === 'application/json' || $mime === 'application/xml';

// --- ACTIONS ---

// 1. Lock Trigger API
if ($action === 'lock') {
    // Only lock if we have a rule that requires it (e.g. Media Ended, or just generic manual trigger)
    // For now, we trust the client to trigger this only when appropriate (e.g. media end)
    // or if we forced it.
    update_file_lock($id, true); 
    echo "Locked";
    exit;
}

// 2. Stream/Download Action
if ($action === 'stream') {
    $path = $file['path'];
    
    // Start Lock Timer if not started
    if ($file['lock_time'] == 0) {
        update_file_lock($id, false, time());
    }
    
    // Security: Check Download Permission
    // If download is NOT allowed, we only serve renderable types (Image, Text, Media).
    // Binary files are blocked because they can't be viewed inline, only downloaded.
    $renderable = $is_media || $is_image || $is_text;
    if (!$allow_download && !$renderable) {
        http_response_code(403);
        die("Download forbidden.");
    }

    // Immediate Lock for Non-Media (One-time viewing)
    if (!$is_media && $file['lock_on_access']) {
        update_file_lock($id, true, time());
    }

    if (!file_exists($path)) {
        http_response_code(404);
        die("File content missing.");
    }

    // Serve File
    
    // If download allowed, suggest filename. 
    // If NOT allowed (inline view), or media, don't force attachment.
    if ($allow_download && !$is_media && !$is_image && !$is_text) {
         $disposition = 'Content-Disposition: attachment; filename="' . $file['filename'] . '"';
    } else {
         $disposition = 'Content-Disposition: inline; filename="' . $file['filename'] . '"';
    }
    
    // Use Range Support
    send_file_range($path, $mime, $disposition);
    exit;
}

// 3. Page View
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View File</title>
    <style>
        body { font-family: sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; background: #222; color: #fff; margin: 0; padding: 20px; box-sizing: border-box; }
        .container { text-align: center; max-width: 100%; width: 100%; display: flex; flex-direction: column; align-items: center; }
        h1 { margin-bottom: 20px; font-size: 1.5rem; word-break: break-all; }
        .btn { display: inline-block; padding: 15px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; font-size: 1.2rem; cursor: pointer; border: none; margin-top: 20px; }
        .btn:hover { background: #0056b3; }
        .viewer-content { max-width: 100%; box-shadow: 0 0 20px rgba(0,0,0,0.5); background: #333; padding: 10px; border-radius: 4px; }
        img, video, audio { max-width: 100%; max-height: 80vh; display: block; }
        pre { text-align: left; overflow: auto; max-height: 80vh; width: 100%; max-width: 800px; white-space: pre-wrap; margin: 0; }
        .locked-msg { color: #aaa; margin-top: 20px; font-style: italic; }
    </style>
    <?php if (!$allow_download): ?>
    <script>
        // Disable Right Click
        document.addEventListener('contextmenu', event => event.preventDefault());
        // Disable Drag and Drop (simple)
        document.addEventListener('dragstart', event => event.preventDefault());
    </script>
    <?php endif; ?>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($file['filename']); ?></h1>

        <?php if ($is_image): ?>
            <!-- IMAGE VIEWER -->
            <div class="viewer-content">
                <img src="?id=<?php echo $id; ?>&action=stream" alt="File Content">
            </div>

        <?php elseif ($is_video): ?>
            <!-- VIDEO VIEWER -->
            <div class="viewer-content">
                <video id="mediaPlayer" controls <?php echo $allow_download ? '' : 'controlsList="nodownload"'; ?> autoplay>
                    <source src="?id=<?php echo $id; ?>&action=stream" type="<?php echo $mime; ?>">
                    Your browser does not support the video tag.
                </video>
            </div>
            
        <?php elseif ($is_audio): ?>
            <!-- AUDIO VIEWER -->
            <div class="viewer-content">
                <audio id="mediaPlayer" controls <?php echo $allow_download ? '' : 'controlsList="nodownload"'; ?> autoplay>
                    <source src="?id=<?php echo $id; ?>&action=stream" type="<?php echo $mime; ?>">
                    Your browser does not support the audio tag.
                </audio>
            </div>

        <?php elseif ($is_text): ?>
            <!-- TEXT VIEWER -->
            <div class="viewer-content">
                <?php
                    // Fetch content for inline display (safely)
                    // We use file_get_contents on the PATH because we are local.
                    // Reading first 100KB to prevent memory issues with massive logs
                    $content = file_get_contents($file['path'], false, null, 0, 102400); 
                    if (filesize($file['path']) > 102400) $content .= "\n... (Truncated)";
                ?>
                <pre><?php echo htmlspecialchars($content); ?></pre>
            </div>
             <!-- Trigger one-time lock for text since it's "viewed" immediately if logic dictates -->
             <!-- The stream logic handles lock if we fetch via stream, but here we read directly.
                  So we must trigger lock if lock_on_access is ON. -->
             <?php 
                if ($file['lock_on_access']) {
                    update_file_lock($id, true, time());
                }
             ?>

        <?php else: ?>
            <!-- GENERIC DOWNLOAD -->
            <?php if ($allow_download): ?>
                <p>This file type cannot be previewed.</p>
                <a href="?id=<?php echo $id; ?>&action=stream" class="btn">Download File</a>
            <?php else: ?>
                <p>Preview unavailable and download is disabled.</p>
            <?php endif; ?>
            
        <?php endif; ?>

        <?php if ($is_media): ?>
            <script>
                const player = document.getElementById('mediaPlayer');
                const fileId = "<?php echo $id; ?>";
                player.addEventListener('ended', function() {
                    fetch('?id=' + fileId + '&action=lock');
                });
            </script>
        <?php endif; ?>
        
        <?php if ($file['lock_on_access'] && !$is_media): ?>
            <p class="locked-msg">File locked after access.</p>
        <?php endif; ?>
    </div>
</body>
</html>
