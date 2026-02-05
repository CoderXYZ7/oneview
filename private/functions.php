<?php
require_once __DIR__ . '/config.php';

session_start();

function load_data() {
    if (!file_exists(DATA_FILE)) {
        return [];
    }
    $json = file_get_contents(DATA_FILE);
    return json_decode($json, true) ?: [];
}

function save_data($data) {
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

function get_file($id) {
    $data = load_data();
    foreach ($data as $file) {
        if ($file['id'] === $id) {
            return $file;
        }
    }
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
                $file['lock_time'] = $lock_time;
            }
            break;
        }
    }
    save_data($data);
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
