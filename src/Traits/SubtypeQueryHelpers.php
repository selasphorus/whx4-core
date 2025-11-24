<?php
declare(strict_types=1);

namespace atc\WXC\Traits;

use atc\WXC\Query\PostQuery;

trait SubtypeQueryHelpers
{
    /**
     * Convenience method: run the query and return WP_Post[].
     * @return \WP_Post[]
     */
    public function find(array $overrides = []): array
    {
        return PostQuery::find($this->getQuerySpec($overrides));
        //$result = Event::find($atts);
    }
}
