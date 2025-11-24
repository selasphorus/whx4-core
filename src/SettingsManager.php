<?php

namespace WXC;

use WXC\Plugin;

final class SettingsManager
{
    private const OPTION = 'wxc_plugin_settings';

    public function getOption(): array
    {
        $saved = get_option(self::OPTION, []);
        //$saved = get_option( 'wxc_plugin_settings', [] );
        //
        $defaults = [
            'initialized'     => 0,
            'active_modules'     => [],
            'enabled_post_types' => [],
            'enabled_taxonomies' => [],
        ];

        //return array_merge( $defaults, $saved );
        return array_merge($defaults, is_array($saved) ? $saved : []);
    }

    public function save(array $patch): void
    {
        $current = $this->getOption();
        update_option(self::OPTION, array_replace_recursive($current, $patch));
    }

    /**
     * Seed first-run defaults once: activate modules and enable all their post types.
     * Call this AFTER all modules have been registered via filters.
     *
     * @param array<string,class-string> $availableModules keyed e.g. by slug => FQCN
     * @param array<string,string[]>     $allPostTypesByModule moduleSlug => [postTypeSlug,...]
     */
    public function ensureInitialized(array $availableModules): void //, array $allPostTypesByModule
    {
        //error_log( '=== SettingsManager::ensureInitialized() ===' );
        $opt = $this->getOption();

        $needsSeeding =
            (empty($opt['active_modules'])
            && empty($opt['enabled_post_types'])
            && $opt['initialized'] !== 1
            );
            //&& get_option('wxc_initialized', 0) !== 1;

        if (!$needsSeeding) {
            //error_log( 'NOT needsSeeding' );
            return;
        }

        // WIP -- allow for filtering of default modules
        //$defaultModules = $this->getDefaultActiveModules($availableModules);
        //$defaultModules = $this->getDefaultActiveModules(array_keys($availableModules));
        $defaultModules = $availableModules;
        $defaultSlugs = array_keys($availableModules);
        //error_log( 'defaultModules:' . print_r($defaultModules, true) );
        //error_log( 'defaultSlugs:' . print_r($defaultSlugs, true) );

        // Enable all known post types for each active module
        $enabled = [];
        //foreach ($defaultActive as $moduleSlug) {
        foreach( $defaultModules as $moduleSlug => $moduleClass ) {
            $module = class_exists( $moduleClass ) ? new $moduleClass() : null;
            $postTypes = $module ? $module->getPostTypes() : [];
            //error_log( 'postTypes for moduleSlug: ' . $moduleSlug . ':' . print_r($postTypes, true) );
            //foreach ( $postTypes as $slug => $label ) :
            $enabled[$moduleSlug] = array_keys($postTypes);
            //$enabled[$moduleSlug] = array_values($allPostTypesByModule[$moduleSlug] ?? []);
        }

        $this->save([
            'initialized' => 1,
            'active_modules'     => $defaultSlugs,
            'enabled_post_types' => $enabled,
        ]);

        //update_option('wxc_initialized', 1);
    }

    /**
     * Filterable default: “all discovered modules” unless overridden.
     *
     */
    public function getDefaultActiveModules(array $allModules): array
    {
        //error_log( '=== SettingsManager::getDefaultActiveModules() ===' );
        $allModuleSlugs = array_keys($allModules);
        $defaultSlugs = apply_filters('wxc_default_active_modules', $allModuleSlugs);
        //return array_values(array_unique(array_filter($defaults, 'is_string')));
        // WIP -- need return multidimensional array, not just slugs
        return $defaults;
    }
    /*
    protected function getDefaultActiveModules(): array
    {
        error_log( '=== Plugin::getDefaultActiveModules() ===' );
        // Default = “all discovered modules”, overrideable via filter
        // Keys must match what you use in getAvailableModules(), e.g., slugs or FQCNs
        $all = array_keys($this->getAvailableModules());
        $defaults = apply_filters('wxc_default_active_modules', $all);
        return $defaults;
    }
    */

    //
    public function getActiveModuleSlugs(): array
    {
        //return $this->getOption()['active_modules'];
        $option = $this->getOption()['active_modules'] ?? [];
        return is_array($option) ? $option : [];
    }

    /** @return array<string,string[]> moduleSlug => [postTypeSlug,...] */
    public function getEnabledPostTypeSlugsByModule(): array
    {
        return $this->getOption()['enabled_post_types'];
    }

    /** @return string[] flat list */
    public function getEnabledPostTypeSlugs(): array
    {
        $grouped = $this->getEnabledPostTypeSlugsByModule();
        //$grouped = $this->getOption()['enabled_post_types'] ?? [];
        $flat = [];
        foreach ( $grouped as $moduleSlugs ) {
            if ( is_array($moduleSlugs) ) {
                $flat = array_merge($flat, $moduleSlugs);
            }
        }
        //return array_unique( $flat );
        return array_values(array_unique($flat));
    }

    //public function getEnabledTaxonomySlugs(): array { ... }

}
