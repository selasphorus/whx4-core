<?php

namespace WXC\Admin;

use WXC\App;
use WXC\Templates\ViewLoader;

class SettingsPageController
{
    public function addHooks(): void
    {
        //error_log( '=== SettingsPageController::addHooks() ===' );
        // Register the settings page using the new registry system
        add_action('wxc_admin_pages_init', [$this, 'registerSettingsPage']);
        
        // Register settings on admin_init (unchanged)
        add_action('admin_init', [$this, 'registerSettings']);
    }

    /*public function addSettingsPage(): void
    {
        //error_log( '=== SettingsPageController::addSettingsPage() ===' );
        add_options_page(
            'WXC v2 Plugin Settings', // page_title
            'WXC v2 Settings', // menu_title
            'manage_options', // capability
            'wxc-settings', // menu_slug
            [ $this, 'renderSettingsPage' ] // callback
        );
    }*/
    
    /**
     * Register the WXC settings page with the AdminPageRegistry
     */
    public function registerSettingsPage(AdminPageRegistry $registry): void
    {
        $registry->registerPage('wxc-settings', [
            'type' => 'options',
            'page_title' => 'WXC v2 Plugin Settings',
            'menu_title' => 'WXC v2 Settings',
            'capability' => 'manage_options',
            'menu_slug' => 'wxc-settings',
            'controller' => [$this, 'renderSettingsPage'],
        ]);
    }

    public function registerSettings(): void
    {
        //error_log( '=== SettingsPageController::registerSettings() ===' );
        register_setting('wxc_plugin_settings_group', 'wxc_plugin_settings');

        add_settings_section(
            'wxc_main_settings',
            'Module and Post Type Settings',
            null,
            'wxc_plugin_settings'
        );
    }

    public function renderSettingsPage(): void
    {
        //error_log( '=== SettingsPageController::renderSettingsPage() ===' );
        ViewLoader::render('settings-page', [
            'availableModules' => App::ctx()->getAvailableModules(),
            'activeModules'    => App::ctx()->getSettingsManager()->getActiveModuleSlugs(),
            'enabledPostTypes' => App::ctx()->getSettingsManager()->getEnabledPostTypeSlugsByModule(),
        ]);
    }

    // WIP 08/19/25
    /*public function sanitizeOptions(array $input): array
    {
        $saved    = $this->getOption();
        $allowed  = array_keys($this->plugin->getAvailableModules());

        $active = array_values(array_intersect(
            $input['active_modules'] ?? [],
            $allowed
        ));

        $saved['active_modules'] = $active;

        // Keep whatever else you store (enabled_post_types, etc.)
        // Merge other fields with appropriate sanitization...

        return $saved;
    }*/
}
