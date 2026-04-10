<?php

namespace atc\WXC\Modules\Supernatural\PostTypes;

use atc\WXC\PostTypes\PostTypeHandler;
use atc\WXC\Modules\Supernatural\Taxonomies\Habitat; // ???

class Monster extends PostTypeHandler
{
	protected static function defineConfig(): array
    {
        return [
            'slug'             => 'monster',
			'menu_icon'        => 'dashicons-palmtree',
			'supports'         => ['title', 'author', 'thumbnail', 'editor', 'excerpt', 'revisions'],
			'taxonomies'       => ['habitat'],
            //'default_taxonomy' => 'category',
            'labels'      => [
				'add_new_item' => 'Summon New Monster',
				'not_found'    => 'No monsters lurking nearby',
			],
        ];
    }

	public function boot(): void
	{
	    parent::boot();
	    self::registerTitleDefaults(static::getSlug(), [
			'line_breaks'   => true,
			'show_subtitle' => true,
			'hlevel_sub'    => 4,
			'called_by'      => 'Monster::boot',
			'append'         => ' {Rowarrr!}',
		]);
	}

    /*
    // Usage in code:
    $handler = new Monster( get_post(123) );
	if ($handler->isPost()) {
		$title = get_the_title( $handler->getObject() );
	}
	*/

	//public function getCustomContent(\WP_Post $post): string
	public function getCustomContent()
	{
		//return "Hello, Monster!";

		/*$habitat = get_post_meta($post->ID, 'rex_supernatural_habitat', true);
		if ( ! $habitat ) {
			return '';
		}*/
		$habitat = "The Great Dismal Swamp"; // tft

		$html  = '<div class="rex-custom-content rex-monster-meta">';
		$html .= '<strong>Habitat:</strong> ' . esc_html($habitat);
		$html .= '</div>';

		return $html;
	}

	// TODO: remove this function -- here now only as a simple working example. For future, just go straight to getPostMeta and
	// create separate functions only if further formatting or manipulation is required.
	public function getColor(): string
	{
		return (string)$this->getPostMeta('monster_color', 'orange');
	}

    public function getSN(): string
    {
        return (string)$this->getPostMeta('secret_name', 'Unknown');
    }

}

