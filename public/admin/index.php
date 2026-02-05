<?php
require_once '../../private/functions.php';
check_auth();

$files = load_data();

// Handle Unlock Action
if (isset($_GET['unlock'])) {
    $id_to_unlock = $_GET['unlock'];
    update_file_lock($id_to_unlock, false, 0); // Reset lock state and time
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        body { font-family: sans-serif; padding: 20px; max-width: 1000px; margin: 0 auto; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        .status-locked { color: red; font-weight: bold; }
        .status-unlocked { color: green; font-weight: bold; }
        .btn { padding: 5px 10px; text-decoration: none; border-radius: 4px; font-size: 0.9em; }
        .btn-unlock { background: #28a745; color: white; }
        .btn-upload { background: #007bff; color: white; padding: 10px 20px; }
        .btn-logout { background: #6c757d; color: white; }
    </style>
</head>
<body>
    <header>
        <h1>File Management</h1>
        <div>
            <a href="upload.php" class="btn btn-upload">Upload New File</a>
            <a href="logout.php" class="btn btn-logout">Logout</a>
        </div>
    </header>

    <table>
        <thead>
            <tr>
                <th>Filename</th>
                <th>Share Link</th>
                <th>Status</th>
                <th>Lock Rules</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($files as $file): ?>
                <tr>
                    <td><?php echo htmlspecialchars($file['filename']); ?></td>
                    <td>
                        <a href="../index.php?id=<?php echo $file['id']; ?>" target="_blank">
                            <?php echo $file['id']; ?>
                        </a>
                    </td>
                    <td>
                        <?php if ($file['locked']): ?>
                            <span class="status-locked">LOCKED</span>
                        <?php else: ?>
                            <span class="status-unlocked">UNLOCKED</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                            $rules = [];
                            if ($file['lock_on_access']) $rules[] = "Immediate";
                            if ($file['max_minutes'] > 0) $rules[] = $file['max_minutes'] . "m Timeout";
                            echo implode(", ", $rules);
                        ?>
                    </td>
                    <td>
                        <a href="edit.php?id=<?php echo $file['id']; ?>" class="btn btn-logout" style="background:#17a2b8;">Edit</a>
                        <?php if ($file['locked']): ?>
                            <a href="?unlock=<?php echo $file['id']; ?>" class="btn btn-unlock">Unlock</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
