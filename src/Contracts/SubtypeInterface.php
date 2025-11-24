<?php
declare(strict_types=1);

namespace atc\WXC\Contracts;

interface SubtypeInterface
{
    public static function getPostType(): string;   // replaces a constant
    public static function getTaxonomy(): string; // replaces a constant
    //
    //public function getPostType(): string; // e.g. 'group'
    public function getSlug(): string;     // e.g. 'employers'
    public function getLabel(): string;    // e.g. 'Employers'
    public function getTermArgs(): array;  // optional extras for wp_insert_term()
    public function getTermSlug(): string; // lets you keep plural display label separate from singular term slug
    public function getQuerySpec(array $overrides = []): array; // implemented by trait: SubtypeDefaults
    public function find(array $overrides = []): array; // implemented by trait: SubtypeQueryHelpers
    
    // For possible future use
    //public function isMetaBacked(): bool;
    //public function getBackingKeyOrTax(): string; // taxonomy name OR meta key
}
