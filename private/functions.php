<?php
require_once __DIR__ . '/config.php';

session_start();

function load_data() {
    if (!file_exists(DATA_FILE)) {
        error_log("load_data: DATA_FILE not found: " . DATA_FILE);
        return [];
    }
    $json = file_get_contents(DATA_FILE);
    $data = json_decode($json, true);
    if ($data === null) {
        error_log("load_data: Failed to decode JSON. Error: " . json_last_error_msg());
        error_log("load_data: Content: " . substr($json, 0, 100) . "...");
        return [];
    }
    return $data;
}

function save_data($data) {
    error_log("save_data: Saving " . count($data) . " items to " . DATA_FILE);
    $result = file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
    if ($result === false) {
        error_log("save_data: FAILED to write to " . DATA_FILE);
    } else {
        error_log("save_data: Wrote $result bytes.");
    }
}

function get_file($id) {
    error_log("get_file: Looking for ID " . $id);
    $data = load_data();
    foreach ($data as $file) {
        if ($file['id'] === $id) {
            error_log("get_file: Found " . $id);
            return $file;
        }
    }
    error_log("get_file: ID " . $id . " NOT FOUND");
    return null;
}

function add_file($file_data) {
    $data = load_data();
    $data[] = $file_data;
    save_data($data);
}

function update_file_lock($id, $locked, $lock_time = null) {
    $data = load_data();
    foreach ($data as &$file) {
        if ($file['id'] === $id) {
            $file['locked'] = $locked;
            if ($lock_time !== null) {
                // If locking, set time. If unlocking, reset (0) is usually passed or handled by caller
                $file['lock_time'] = $lock_time;
            }
            break;
        }
    }
    save_data($data);
}

function update_file_options($id, $updates) {
    error_log("update_file_options: Updating " . $id . " with " . json_encode($updates));
    $data = load_data();
    $found = false;
    foreach ($data as &$file) {
        if ($file['id'] === $id) {
            $found = true;
            if (isset($updates['lock_on_access'])) $file['lock_on_access'] = $updates['lock_on_access'];
            if (isset($updates['max_minutes'])) $file['max_minutes'] = (int)$updates['max_minutes'];
            if (isset($updates['allow_download'])) $file['allow_download'] = $updates['allow_download'];
            break;
        }
    }
    
    if (!$found) {
        error_log("update_file_options: Failed to find file to update.");
    } else {
        save_data($data);
    }
}

function delete_file($id) {
    error_log("delete_file: Deleting " . $id);
    $data = load_data();
    $new_data = [];
    $found_path = null;
    
    foreach ($data as $file) {
        if ($file['id'] === $id) {
            $found_path = $file['path'];
            continue; // Exclude from new data
        }
        $new_data[] = $file;
    }
    
    if ($found_path) {
        if (file_exists($found_path)) {
            if (unlink($found_path)) {
                error_log("delete_file: Deleted file " . $found_path);
            } else {
                error_log("delete_file: FAILED to delete file " . $found_path);
            }
        } else {
            error_log("delete_file: File path not found on disk " . $found_path);
        }
    } else {
        error_log("delete_file: ID " . $id . " not found in data.");
    }
    
    save_data($new_data);
}

function send_file_range($path, $mime_type, $disposition_header) {
    if (!file_exists($path)) {
        header("HTTP/1.1 404 Not Found");
        return;
    }

    $size = filesize($path);
    $length = $size;
    $start = 0;
    $end = $size - 1;

    header("Content-Type: $mime_type");
    header("Accept-Ranges: bytes");
    header($disposition_header);

    if (isset($_SERVER['HTTP_RANGE'])) {
        $c_start = $start;
        $c_end = $end;

        list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
        if (strpos($range, ',') !== false) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes $start-$end/$size");
            exit;
        }

        if ($range == '-') {
            $c_start = $size - substr($range, 1);
        } else {
            $range = explode('-', $range);
            $c_start = $range[0];
            $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
        }
        
        $c_end = ($c_end > $end) ? $end : $c_end;
        if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes $start-$end/$size");
            exit;
        }
        
        $start = $c_start;
        $end = $c_end;
        $length = $end - $start + 1;
        
        fseek($fp = fopen($path, 'rb'), $start);
        header('HTTP/1.1 206 Partial Content');
        header("Content-Range: bytes $start-$end/$size");
    } else {
        $fp = fopen($path, 'rb'); // Open for full read
    }

    header("Content-Length: $length");
    
    // Output content in chunks
    $buffer = 1024 * 8;
    while (!feof($fp) && ($p = ftell($fp)) <= $end) {
        if ($p + $buffer > $end) {
            $buffer = $end - $p + 1;
        }
        set_time_limit(0);
        echo fread($fp, $buffer);
        flush();
    }
    fclose($fp);
}

function check_auth() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }
}

function generate_id() {
    return bin2hex(random_bytes(16));
}
