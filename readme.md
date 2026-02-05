# Secure Single-Use File Access System

**Development Specification**

## 1. Objective

Develop a web system that allows an administrator to upload files and generate shareable links that grant **one-time, controlled access** to those files. Once access begins, the file becomes **permanently locked** until manually unlocked by an administrator.

---

## 2. Core Requirements

### 2.1 File Support

* Support **any file type**
* Files must **never be publicly accessible**
* All file access must be mediated by server logic

---

### 2.2 Access Model

Each shared file has an independent lifecycle.

#### Initial State

* File is **unlocked**
* Share link is valid

#### Lock Triggers (ANY triggers permanent lock)

* First access to the file
* X minutes after first access (admin-configurable)
* Media playback ends (if applicable)

#### Locked State

* File is completely inaccessible
* Share link returns **403 / unavailable**
* File remains locked indefinitely
* **Only admin can unlock**

---

## 3. Admin Capabilities

### 3.1 Authentication

* Password-protected admin area
* No public admin endpoints

### 3.2 File Management

Admin must be able to:

* Upload files
* Set lock rules per file:

  * Lock on first access (boolean)
  * Lock timeout in minutes (integer)
* Generate a unique share link per file
* View lock status
* Manually unlock files

---

## 4. Public User Capabilities

* Open a share link
* If unlocked:

  * Access file (stream or view)
* If locked:

  * Receive “Unavailable” response
* No login
* No ability to unlock or reset

---

## 5. Locking Rules (Authoritative)

### 5.1 Lock Timing

* Lock starts **at first access**, not page load
* Lock timeout starts at first access
* Lock is **irreversible without admin action**

### 5.2 Media-Aware Behavior

If file is media (audio/video):

* Lock starts on `play`
* Lock finalizes on:

  * Media `ended`
  * OR timeout reached

Non-media files:

* Lock starts on first download/view request

---

## 6. Security Constraints

### 6.1 File Protection

* Uploaded files stored outside web root
* Direct file URLs must not exist
* Server must reject access when locked

### 6.2 Download Prevention (Best Effort)

* Do not expose real file paths
* Use streamed responses
* Disable browser download controls for media

**Note:**
Perfect download prevention is impossible in browsers and is explicitly out of scope.

---

## 7. Data Model

### 7.1 File Record

Each file must store:

| Field          | Type    | Description        |
| -------------- | ------- | ------------------ |
| id             | string  | Public share ID    |
| filename       | string  | Original name      |
| path           | string  | Server file path   |
| locked         | boolean | Current lock state |
| lock_time      | int     | Unix timestamp     |
| max_minutes    | int     | Lock timeout       |
| lock_on_access | boolean | Immediate lock     |

---

## 8. Public API Behavior (Conceptual)

### 8.1 Access Endpoint

* Validates file ID
* Checks lock state
* Applies lock rules
* Streams file if allowed
* Returns 403 if locked

### 8.2 Admin Endpoints

* Upload file
* Unlock file
* List file states

---

## 9. Technology Constraints

* **Frontend:** HTML + vanilla JavaScript
* **Backend:** PHP
* **Storage:** JSON or simple persistence (no DB required)
* **No frameworks**

---

## 10. Non-Goals (Explicit)

* DRM
* Watermarking
* Multi-user concurrency guarantees
* Audit logging
* Access analytics

---

## 11. Acceptance Criteria

A developer’s implementation is correct if:

* A file becomes inaccessible immediately after first use
* File remains locked indefinitely
* Admin can unlock and reuse the file
* Files cannot be accessed directly via URL
* Lock rules are enforced server-side

---

## 12. Notes to Developer

* Treat **lock state as authoritative**
* Client-side logic is advisory only
* Assume hostile clients
* Favor simplicity over cleverness

---