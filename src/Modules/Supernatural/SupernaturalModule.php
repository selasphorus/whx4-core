<?php

namespace atc\WXC\Modules\Supernatural;

use atc\WXC\Module as BaseModule;
use atc\WXC\Query\PostQuery;
use atc\WXC\Shortcodes\ShortcodeManager;

use atc\WXC\Modules\Supernatural\PostTypes\Monster;
use atc\WXC\Modules\Supernatural\PostTypes\Enchanter;
use atc\WXC\Modules\Supernatural\PostTypes\Spell;

final class SupernaturalModule extends BaseModule
{
    public function boot(): void
    {
        //error_log( '=== SupernaturalModule::boot() ===' );
        $this->registerDefaultViewRoot();

        parent::boot();

        //ViewLoader::registerModuleViewRoot( 'supernatural', __DIR__ . '/views' ); // default
        // Override with custom path
        //ViewLoader::registerModuleViewRoot( 'supernatural', WP_CONTENT_DIR . '/shared-supernatural-views' );
        
        // Register shortcodes
        ShortcodeManager::add(\WXC\Modules\Supernatural\Shortcodes\SupernaturalShortcode::class);
    }

    /*
    // Optional: override defaults that match to namespace
    public function getSlug(): string
    {
        return 'spooky';
    }
    public function getName(): string
    {
        return 'Spooky Things';
    }
    */

    public function getPostTypeHandlerClasses(): array
    {
        return [
            Monster::class,
            //Enchanter::class,
            //Spell::class,
        ];
    }
    
    public function getModuleStats(): array
    {
        return [
            'monsters'   => wp_count_posts('monster')->publish ?? 0,
            //'enchanters' => wp_count_posts('enchanter')->publish ?? 0,
        ];
    }
    
    /**
     * @return \WP_Post[]  All Monster posts matching the color.
     * This is a sample function to show a module-level find method by meta_key
     */
    public function findMonstersByColor(string $color, array $options = []): array
	{
		$postType = 'monster';
		
		// NTS: The array_replace() function replaces the values of the first array with the values from following arrays.
		$filters = array_replace([
			'post_type' => $postType,
			'meta'      => [
				['key' => 'monster_color', 'equals' => $color],
			],
			'limit'  => -1,
			'orderby'   => 'title',
			'order'     => 'ASC',
		], $options);
	
		return $this->findViaHandler($postType, $filters);
	}
}
