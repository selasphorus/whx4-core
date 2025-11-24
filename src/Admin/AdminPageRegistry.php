<?php

namespace atc\WXC\Admin;

/**
 * Registry for admin pages that allows add-on plugins to register their own pages
 * 
 * This class provides a centralized way for WXC and its add-ons to register
 * admin pages. It supports both top-level pages and subpages under existing menus.
 */
class AdminPageRegistry
{
    private static ?self $instance = null;
    
    /** @var array<string, array> Registered page configurations */
    private array $pages = [];
    
    private function __construct()
    {
        // Private constructor for singleton
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize the registry and register hooks
     */
    public function init(): void
    {
        // Allow plugins to register pages early
        do_action('wxc_admin_pages_init', $this);
        
        // Register all pages on admin_menu
        add_action('admin_menu', [$this, 'registerPages'], 10);
    }
    
    /**
     * Register an admin page
     * 
     * @param string $id Unique identifier for the page
     * @param array $config Page configuration
     * 
     * Configuration options:
     * - type: 'options'|'menu'|'submenu' (default: 'options')
     * - parent_slug: Required for submenu pages
     * - page_title: Page title (required)
     * - menu_title: Menu title (required)
     * - capability: Required capability (default: 'manage_options')
     * - menu_slug: Menu slug (default: sanitized version of $id)
     * - icon_url: Icon URL for top-level menu pages
     * - position: Menu position for top-level menu pages
     * - controller: Callable to render the page (required)
     */
    public function registerPage(string $id, array $config): void
    {
        // Validate required fields
        if (empty($config['page_title']) || empty($config['menu_title'])) {
            error_log("AdminPageRegistry: page_title and menu_title are required for page '{$id}'");
            return;
        }
        
        if (empty($config['controller']) || !is_callable($config['controller'])) {
            error_log("AdminPageRegistry: Valid controller callable is required for page '{$id}'");
            return;
        }
        
        // Set defaults
        $config = array_merge([
            'type' => 'options',
            'capability' => 'manage_options',
            'menu_slug' => sanitize_key($id),
        ], $config);
        
        // Validate submenu has parent_slug
        if ($config['type'] === 'submenu' && empty($config['parent_slug'])) {
            error_log("AdminPageRegistry: parent_slug is required for submenu page '{$id}'");
            return;
        }
        
        $this->pages[$id] = $config;
    }
    
    /**
     * Register all pages with WordPress
     * Called on admin_menu hook
     */
    public function registerPages(): void
    {
        foreach ($this->pages as $id => $config) {
            $hook = null;
            
            switch ($config['type']) {
                case 'options':
                    $hook = add_options_page(
                        $config['page_title'],
                        $config['menu_title'],
                        $config['capability'],
                        $config['menu_slug'],
                        $config['controller']
                    );
                    break;
                    
                case 'menu':
                    $hook = add_menu_page(
                        $config['page_title'],
                        $config['menu_title'],
                        $config['capability'],
                        $config['menu_slug'],
                        $config['controller'],
                        $config['icon_url'] ?? '',
                        $config['position'] ?? null
                    );
                    break;
                    
                case 'submenu':
                    $hook = add_submenu_page(
                        $config['parent_slug'],
                        $config['page_title'],
                        $config['menu_title'],
                        $config['capability'],
                        $config['menu_slug'],
                        $config['controller']
                    );
                    break;
            }
            
            // Store the hook for potential use (e.g., context-specific scripts)
            if ($hook) {
                $this->pages[$id]['hook'] = $hook;
            }
        }
    }
    
    /**
     * Get all registered pages
     * 
     * @return array<string, array>
     */
    public function getPages(): array
    {
        return $this->pages;
    }
    
    /**
     * Check if a page is registered
     */
    public function hasPage(string $id): bool
    {
        return isset($this->pages[$id]);
    }
    
    /**
     * Get a specific page configuration
     */
    public function getPage(string $id): ?array
    {
        return $this->pages[$id] ?? null;
    }
}