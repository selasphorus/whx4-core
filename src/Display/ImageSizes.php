<?php

declare(strict_types=1);

namespace atc\WXC\Display;

/**
 * Registers WXC standard image sizes.
 *
 * Sizes are filterable before registration, allowing per-site
 * customisation or removal via the 'wxc_image_sizes' filter:
 *
 *   add_filter('wxc_image_sizes', function(array $sizes): array {
 *       unset($sizes['grid_crop_rectangle']); // remove deprecated size
 *       $sizes['grid_crop_square']['width'] = 400; // resize for this install
 *       return $sizes;
 *   });
 */
class ImageSizes
{
    /**
     * Default size definitions.
     * Each entry: [ width, height, crop ]
     *
     * @return array<string, array{width:int, height:int, crop:bool, label:string}>
     */
    public static function defaults(): array
    {
        return [
            'grid_crop_square' => [
                'width'  => 600,
                'height' => 600,
                'crop'   => true,
                'label'  => __('Grid Crop (square)', 'wxc'),
            ],
            'grid_crop_landscape' => [
                'width'  => 534,
                'height' => 300,
                'crop'   => true,
                'label'  => __('Grid Crop (landscape)', 'wxc'),
            ],
            'grid_crop_portrait' => [
                'width'  => 350,
                'height' => 525,
                'crop'   => true,
                'label'  => __('Grid Crop (portrait)', 'wxc'),
            ],
        ];
    }

    /**
     * Register image sizes and expose them in the media library UI.
     * Call from CoreServices::boot().
     */   
    public static function boot(): void
	{
		$sizes = apply_filters('wxc_image_sizes', static::defaults());
	
		foreach ($sizes as $name => $size) {
			add_image_size($name, $size['width'], $size['height'], $size['crop']);
		}
	
		add_filter('image_size_names_choose', static function (array $existing) use ($sizes): array {
			foreach ($sizes as $name => $size) {
				$existing[$name] = $size['label'];
			}
			return $existing;
		});
	}
}