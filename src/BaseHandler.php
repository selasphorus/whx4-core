<?php

namespace atc\WXC;

use atc\WXC\Utils\Text;
use atc\WXC\Traits\HasTypeProperties;

// Shared logic & constructor for all registrable types (CPTs, taxonomies, maybe more)
abstract class BaseHandler
{
    use HasTypeProperties;

    protected const TYPE = 'post_type';
    protected \WP_Post|\WP_Term|null $object = null;
    
    /**
     * Define the handler's static configuration.
     *
     * Concrete handlers implement this to declare type-level properties:
     * slug, labels, supports, taxonomies, capabilities, etc.
     */
    abstract protected static function defineConfig(): array;

    /**
     * Return the handler's static configuration.
     */
    public static function getConfig(): array
    {
        return static::defineConfig();
    }

    public function __construct(\WP_Post|\WP_Term|null $object = null)
    {
        $this->object = $object;
    }
    
    public static function getType(): string
	{
		return static::TYPE;
	}
	
	///

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
	
	///
	
	/**
	 * Resolve a short class name or 'Module:name' string to a FQCN under a
	 * given sub-namespace of the handler's module.
	 *
	 * Already-qualified names (containing '\') are returned as-is.
	 *
	 * @param  string $name        Short name, 'Module:name', or FQCN.
	 * @param  string $subNamespace Sub-namespace segment, e.g. 'Taxonomies', 'Shortcodes'.
	 * @return string
	 */
	protected static function resolveFqcn(string $name, string $subNamespace): string
	{
		if (str_contains($name, '\\')) {
			return ltrim($name, '\\');
		}
	
		if (!preg_match('/^(.*\\\\Modules\\\\)([^\\\\]+)/', static::class, $m)) {
			return Text::studly($name);
		}
	
		$targetModule = $m[2];
		$basename     = $name;
	
		if (str_contains($name, ':')) {
			[$targetModule, $basename] = array_map('trim', explode(':', $name, 2));
			if ($targetModule === '') {
				$targetModule = $m[2];
			}
		}
	
		return $m[1] . $targetModule . '\\' . $subNamespace . '\\' . Text::studly($basename);
	}

}
