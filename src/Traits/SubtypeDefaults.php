<?php
declare(strict_types=1);

namespace atc\WXC\Traits;

trait SubtypeDefaults
{
    public static function getPostType(): string
    {
        return static::POST_TYPE;
    }
    
    public static function getTaxonomy(): string
    {
        return static::TAXONOMY;
    }
    
    protected function queryDefaults(): array
    {
        return [
            'per_page' => 20,
            'orderby'  => 'title',
            'order'    => 'ASC',
        ];
    }

    public function getQuerySpec(array $overrides = []): array
    {
        $spec = [
            'post_type' => static::getPostType(),
            'tax'       => [
                static::getTaxonomy() => [$this->getTermSlug()],
            ],
        ] + $this->queryDefaults();

        // Optional per-class tweak without overriding the whole method
        if (method_exists($this, 'customizeQuerySpec')) {
            /** @var array $spec */
            $spec = $this->customizeQuerySpec($spec);
        }

        return array_replace_recursive($spec, $overrides);
    }
}
