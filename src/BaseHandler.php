<?php

namespace atc\WXC;

use atc\WXC\Traits\HasTypeProperties;

// Shared logic & constructor for all registrable types (CPTs, taxonomies, maybe more)
abstract class BaseHandler
{
    use HasTypeProperties;

    protected array $config = [];
    protected const TYPE = 'post_type'; //protected string $type = 'post_type';
    protected \WP_Post|\WP_Term|null $object = null;

    public function __construct(array $config = [], \WP_Post|\WP_Term|null $object = null)
	{
		$this->config = $config;
		$this->object = $object;
	}

    public function getType(): string {
		return static::TYPE;
	}
    /*public function getType(): string {
        return $this->type;
    }*/
    /*protected function defineType(): string {
		// Override in TaxonomyHandler to return 'taxonomy'
		return 'post_type';
	}*/

    //abstract protected function defineConfig(): array;

    public function getConfig(): array {
        return $this->config;
    }

    public function getObject(): \WP_Post|\WP_Term|null {
        return $this->object;
    }

    public function isPost(): bool {
        return $this->object instanceof \WP_Post;
    }

    public function isTerm(): bool {
        return $this->object instanceof \WP_Term;
    }

    // Fun with meta

    public function getValue(string $key): mixed
    {

		$id = null;

		if ($this->isPost()) {
			$id = $this->object->ID;
		} elseif ($this->isTerm()) {
			$id = "{$this->object->taxonomy}_{$this->object->term_id}";
		}

		if (!$id) {
			return null;
		}

		// Try ACF first
		if (function_exists('get_field')) {
			$acf = get_field($key, $id);
			if ($acf !== null) {
				return $acf;
			}
		}

		// Fallback to native meta
		if ($this->isPost()) {
			return get_post_meta($this->object->ID, $key, true);
		}

		if ($this->isTerm()) {
			return get_term_meta($this->object->term_id, $key, true);
		}

		return null;
	}

	public function updateValue(string $key, mixed $value): bool
	{
		// Update ACF if available
		if (function_exists('update_field')) {
			if ($this->isPost()) {
				return update_field($key, $value, $this->object->ID);
			}

			if ($this->isTerm()) {
				$field_key = "{$this->object->taxonomy}_{$this->object->term_id}";
				return update_field($key, $value, $field_key);
			}
		}

		// Fallback to native meta update
		if ($this->isPost()) {
			return update_post_meta($this->object->ID, $key, $value) !== false;
		}

		if ($this->isTerm()) {
			return update_term_meta($this->object->term_id, $key, $value) !== false;
		}

		return false;
	}

}
