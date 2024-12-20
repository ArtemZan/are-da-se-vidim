<?php
session_start();
require_once 'config.php';
require_once 'google_config.php';

// Composer autoloader for Google API client
require_once 'vendor/autoload.php';

$client = new Google_Client();
$client->setClientId($google_config['client_id']);
$client->setClientSecret($google_config['client_secret']);
$client->setRedirectUri($google_config['redirect_uri']);
$client->setScopes($google_config['scopes']);

if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        $client->setAccessToken($token);

        // Get user info
        $google_oauth = new Google\Service\Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();

        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
            $stmt->execute([$google_account_info->getId()]);
            $user = $stmt->fetch();

            if (!$user) {
                // Create new user
                $stmt = $pdo->prepare("
                    INSERT INTO users (google_id, email, name, picture_url) 
                    VALUES (?, ?, ?, ?) 
                    RETURNING id
                ");
                $stmt->execute([
                    $google_account_info->getId(),
                    $google_account_info->getEmail(),
                    $google_account_info->getName(),
                    $google_account_info->getPicture()
                ]);
                $user_id = $stmt->fetchColumn();
            } else {
                $user_id = $user['id'];
                // Update existing user info
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET email = ?, name = ?, picture_url = ? 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $google_account_info->getEmail(),
                    $google_account_info->getName(),
                    $google_account_info->getPicture(),
                    $user_id
                ]);
            }

            // Store user info in session
            $_SESSION['user'] = [
                'id' => $user_id,
                'email' => $google_account_info->getEmail(),
                'name' => $google_account_info->getName(),
                'picture' => $google_account_info->getPicture()
            ];

            // Redirect back to the event page
            $redirect_url = $_SESSION['redirect_after_login'] ?? 'index.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect_url);
            exit;
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            die('Database error occurred. Please try again later.');
        }
    } catch (Exception $e) {
        error_log('Authentication error: ' . $e->getMessage());
        die('Authentication error occurred. Please try again later.');
    }
} else {
    header('Location: index.php');
    exit;
} 