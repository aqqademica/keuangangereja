# Implementation Plan: Enforce Single Active Session per User

This plan outlines the design and changes required to restrict user accounts to exactly one active session. When a user logs in on a new device or browser, their older session will be invalidated on their next request.

## Proposed Changes

### 1. Database Schema Updates
We need to track the current active session ID for each user in the database.

#### [MODIFY] [schema.sql](file:///c:/xampp/htdocs/GerejaManagement/schema.sql)
*   Add the `active_session_id` column to the `users` table:
    ```sql
    active_session_id VARCHAR(255) NULL
    ```

### 2. Login Flow Updates
We need to capture and save the session ID upon successful authentication.

#### [MODIFY] [login.php](file:///c:/xampp/htdocs/GerejaManagement/login.php)
*   Immediately after a successful login and session assignment, capture the current session ID:
    ```php
    $session_id = session_id();
    ```
*   Update the database row for the user setting `active_session_id` to the new session ID:
    ```php
    $stmtSession = $conn->prepare("UPDATE users SET active_session_id = ? WHERE id = ?");
    $stmtSession->bind_param("si", $session_id, $user['id']);
    $stmtSession->execute();
    ```
*   Add support for displaying the `flash_error` session invalidation message (e.g. if the session was killed by another login) on the login screen.

### 3. Session Verification Hook
We need to check the session validity on every authenticated request.

#### [MODIFY] [includes/header.php](file:///c:/xampp/htdocs/GerejaManagement/includes/header.php)
*   Right after basic user session attributes are set:
    ```php
    $role = $_SESSION['role'];
    $name = $_SESSION['name'];
    $user_id = $_SESSION['user_id'];
    ```
*   Retrieve the `active_session_id` from the database for the logged-in `$user_id`.
*   Compare it with the current `session_id()`:
    ```php
    $stmtSessionCheck = $conn->prepare("SELECT active_session_id FROM users WHERE id = ?");
    $stmtSessionCheck->bind_param("i", $user_id);
    $stmtSessionCheck->execute();
    $db_session = $stmtSessionCheck->get_result()->fetch_assoc()['active_session_id'];
    
    if ($db_session !== session_id()) {
        // Log out user
        session_destroy();
        session_start(); // Start new session to store the flash message
        $_SESSION['flash_error'] = "Sesi Anda berakhir karena akun ini telah login di perangkat atau browser lain.";
        header("Location: login.php");
        exit;
    }
    ```

---

## Verification Plan

### Manual Verification
1. Open the application in Browser A (or an Incognito window), log in as a user (e.g., `sekretaris@gereja.local`).
2. Open the application in Browser B (regular browser), log in with the same credentials.
3. In Browser A, refresh the page or click a menu item.
4. Verify that Browser A is automatically logged out and redirected to `login.php` with the alert message: *"Sesi Anda berakhir karena akun ini telah login di perangkat atau browser lain."*
5. Verify that Browser B remains logged in and operational.
