<?php
namespace WXC;

final class BootOrder
{
    public const CAPS            = 8; // early for admin UI?
    public const CPTS            = 10;
    public const SUBTYPES        = 11;
    public const TAXONOMIES      = 12;
    public const TERM_SEED       = 13;

    // Different hook family:
    public const ACF_FIELDS      = 11; // on acf/init
    public const ENQUEUE_ASSETS  = 10; // on wp_enqueue_scripts
}
