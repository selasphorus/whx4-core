<?php

namespace WXC\ACF;

use WXC\App;

class BlockRegistrar
{
    public static function register(): void
    {
        add_action( 'acf/init', [self::class, 'registerBlocks'] );
    }

    public static function registerBlocks(): void
    {
        if ( ! function_exists( 'acf_register_block_type' ) ) {
            return;
        }

        $modules = App::ctx()->getActiveModules();
        $postTypes = App::ctx()->getActivePostTypes();

        foreach ( $modules as $module ) {
            $blockDir = $module->getPath() . '/Blocks';

            if ( ! is_dir( $blockDir ) ) {
                continue;
            }

            foreach ( glob( $blockDir . '/*.php' ) as $file ) {
                $block = include $file;

                if ( ! is_array( $block ) || empty( $block['name'] ) ) {
                    continue;
                }

                // Optional: only register blocks for enabled post types
                if ( isset( $block['post_types'] ) && is_array( $block['post_types'] ) ) {
                    $intersection = array_intersect( $block['post_types'], $postTypes );
                    if ( empty( $intersection ) ) {
                        continue;
                    }
                }

                acf_register_block_type( $block );
            }
        }
    }
}
