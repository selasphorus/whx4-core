<?php

declare(strict_types=1);

namespace atc\WXC\Templates;

/**
 * Enumerates the categories of view templates Rex can load.
 *
 * Usage examples:
 *   ViewKind::GLOBAL
 *   ViewKind::MODULE
 *   ViewKind::POSTTYPE
 *   ViewKind::PARTIAL
 *   ViewKind::LAYOUT
 *   ViewKind::SHORTCODE
 *   ViewKind::EMAIL
 *   ViewKind::ADMIN
 */
enum ViewKind: string
{
    case GLOBAL    = 'global';    // Plugin-wide or shared views
    case MODULE    = 'module';    // Module-scoped views (e.g. supernatural)
    case POSTTYPE  = 'posttype';  // Specific CPT views (e.g. supernatural/monster)
    case PARTIAL   = 'partial';   // Reusable snippets/partials
    case LAYOUT    = 'layout';    // Wrappers/layout files
    case SHORTCODE = 'shortcode'; // Shortcode templates
    case EMAIL     = 'email';     // Email templates
    case ADMIN     = 'admin';     // Admin-only views
}
