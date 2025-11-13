<?php
// File: src/includes/breadcrumbs.php

function generate_breadcrumbs() {
    $breadcrumbs = [];
    $url_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path_parts = explode('/', trim($url_path, '/'));

    // Remove the project folder if present
    $project_folder = 'Contas';
    $project_folder_index = array_search($project_folder, $path_parts);
    if ($project_folder_index !== false) {
        $path_parts = array_slice($path_parts, $project_folder_index + 1);
    }

    // Don't show breadcrumbs on index.php
    if (count($path_parts) > 0 && end($path_parts) === 'index.php') {
        return;
    }

    $base_url = '/Contas/public/';
    $current_url = $base_url;
    $breadcrumbs[] = ['url' => $base_url . 'index.php', 'label' => 'Home'];

    foreach ($path_parts as $part) {
        if (empty($part) || $part === 'public') continue;
        $current_url .= $part . '/';
        $label = ucfirst(str_replace(['_', '.php'], [' ', ''], $part));
        $breadcrumbs[] = ['url' => $current_url, 'label' => $label];
    }

    echo '<nav class="flex mb-4" aria-label="Breadcrumb">';
    echo '<ol class="inline-flex items-center space-x-1 md:space-x-3">';
    foreach ($breadcrumbs as $index => $crumb) {
        $is_last = $index === count($breadcrumbs) - 1;
        if ($is_last) {
            echo '<li class="inline-flex items-center">';
            echo '</li>';
        } else {
            echo '<li class="inline-flex items-center">';
            echo '<a href="' . htmlspecialchars($crumb['url']) . '" class="inline-flex items-center text-xl font-medium text-gray-700 hover:text-gray-900 border-b-2 border-transparent hover:border-blue-500 pb-1">';
            if ($index > 0) {
                echo '<svg class="w-6 h-6 text-blue-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>';
            }
            echo htmlspecialchars($crumb['label']);
            echo '</a>';
            echo '</li>';
        }
    }
    echo '</ol>';
    echo '</nav>';
}

generate_breadcrumbs();
?>