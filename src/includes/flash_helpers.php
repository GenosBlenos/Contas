<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function flashMessage(string $type, string $message): void {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessages(): array {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

function displayFlashMessages(): void {
    $messages = getFlashMessages();
    if (empty($messages)) {
        return;
    }

    foreach ($messages as $msg) {
        $type = htmlspecialchars($msg['type']);
        $message = htmlspecialchars($msg['message']);
        
        $colorClass = 'bg-blue-100 border-blue-500 text-blue-700'; // Padrão
        if ($type === 'success') {
            $colorClass = 'bg-green-100 border-green-500 text-green-700';
        } elseif ($type === 'error') {
            $colorClass = 'bg-red-100 border-red-500 text-red-700';
        } elseif ($type === 'warning') {
            $colorClass = 'bg-yellow-100 border-yellow-500 text-yellow-700';
        }

        echo "<div class='{$colorClass} border-l-4 p-4 mb-4' role='alert'>";
        echo "<p class='font-bold'>" . ucfirst($type) . "</p>";
        echo "<p>{$message}</p>";
        echo "</div>";
    }
}
