<?php
// plugins.php

class PluginManager {
    private $pdo;
    private $plugins = [];
    private $active_plugins = ['example']; // Default active plugins, could be moved to DB/config later

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadPlugins();
    }

    private function loadPlugins() {
        foreach ($this->active_plugins as $plugin_name) {
            $plugin_file = __DIR__ . "/plugins/{$plugin_name}.php";
            if (file_exists($plugin_file)) {
                require_once $plugin_file;
                $class_name = ucfirst($plugin_name) . 'Plugin';
                if (class_exists($class_name)) {
                    $this->plugins[$plugin_name] = new $class_name($this->pdo);
                }
            }
        }
    }

    public function executeHook($hook_name, $data = []) {
        $results = [];
        foreach ($this->plugins as $plugin_name => $plugin) {
            if (method_exists($plugin, $hook_name)) {
                $results[$plugin_name] = $plugin->$hook_name($data);
            }
        }
        return $results;
    }

    public function getActivePlugins() {
        return $this->active_plugins;
    }

    public function getAvailablePlugins() {
        $plugin_files = glob(__DIR__ . '/plugins/*.php');
        $available = [];
        foreach ($plugin_files as $file) {
            $plugin_name = basename($file, '.php');
            $available[] = $plugin_name;
        }
        return $available;
    }

    public function activatePlugin($plugin_name) {
        if (!in_array($plugin_name, $this->active_plugins)) {
            $this->active_plugins[] = $plugin_name;
            $this->loadPlugins(); // Reload to include new plugin
            return true;
        }
        return false;
    }

    public function deactivatePlugin($plugin_name) {
        $index = array_search($plugin_name, $this->active_plugins);
        if ($index !== false) {
            unset($this->active_plugins[$index]);
            $this->active_plugins = array_values($this->active_plugins);
            unset($this->plugins[$plugin_name]);
            return true;
        }
        return false;
    }

    public function uploadPlugin($file) {
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['application/x-php', 'text/x-php', 'text/php'];
            if (!in_array($file['type'], $allowed_types)) {
                return "Only PHP files are allowed.";
            }
            $max_size = 2 * 1024 * 1024; // 2MB
            if ($file['size'] > $max_size) {
                return "File size must be less than 2MB.";
            }
            
            $plugin_name = basename($file['name'], '.php');
            $destination = __DIR__ . "/plugins/{$plugin_name}.php";
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                return "Plugin uploaded successfully!";
            } else {
                return "Failed to upload plugin.";
            }
        }
        return "Upload error: " . $file['error'];
    }
}

abstract class BasePlugin {
    protected $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
}