<?php

namespace atc\WXC;

use atc\WXC\App;
//use atc\WXC\Util\NamespaceUtil;
use ReflectionClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use atc\WXC\Contracts\FieldGroupInterface;
use atc\WXC\Contracts\PostTypeFieldGroupInterface;
use atc\WXC\Contracts\SubtypeFieldGroupInterface;

class FieldGroupLoader
{
    private bool $registered = false;

    public function register(): void
    {
        if ( $this->registered ) return;
        add_action( 'init', [$this, 'boot'], BootOrder::CPTS );
        $this->registered = true;
    }

    public function boot(): void
    {
        //error_log( '=== FieldGroupLoader::boot() ===' );

        // Abort if no modules have been booted
        if ( !App::ctx()->modulesBooted() ) {
            //error_log( '=== no modules booted yet => abort ===' );
            return;
        }

        $this->registerAll();
    }

    ////

    public function registerAll(): void
    {
        //error_log( '=== registerAll field groups ===' );
        foreach( App::ctx()->getActiveModules() as $moduleClass ) {
            $this->registerFieldsForModule( $moduleClass );
        }
    }

    protected function registerFieldsForModule( string $moduleClass ): void
    {
        //error_log( '=== registerFieldsForModule for moduleClass: ' . $moduleClass . ' ===' );
        $ref = new \ReflectionClass( $moduleClass );
        $moduleDir = dirname( $ref->getFileName() );
        $fieldsDir = $moduleDir . '/Fields';
        $subtypesDir  = $fieldsDir . '/Subtypes'; // maybe don't need this -- just put everything in fieldsDir, clearly named

        if ( !is_dir( $fieldsDir ) ) {
            error_log( '*** fieldsDir: ' . $fieldsDir . ' not found. Aborting registration.' );
            return;
        }

        $activePostTypes = App::ctx()->getActivePostTypes();
        //error_log( 'activePostTypes: ' . print_r($activePostTypes, true) );

        // === Build a map of postType slug => short class name (e.g. rex_event => Event)
        $slugMap = [];

        // Instantiate the module class
        $module = new $moduleClass();

        if ( method_exists( $moduleClass, 'getPostTypeHandlerClasses' ) ) {
            foreach ( $module->getPostTypeHandlerClasses() as $handlerClass ) {
                if ( !class_exists( $handlerClass ) ) {
                    continue;
                }

                // Try to reflect default config
                $handlerSlug = null;

                try {
                    $reflection = new \ReflectionClass( $handlerClass );
                    $props = $reflection->getDefaultProperties();

                    if ( isset( $props['config']['slug'] ) ) {
                        $handlerSlug = $props['config']['slug'];
                    }
                } catch ( \ReflectionException ) {
                    // fall back to instantiation
                }

                // Fallback: instantiate handler only if needed
                if ( !$handlerSlug ) {
                    try {
                        $handler = new $handlerClass();
                        $handlerSlug = $handler->getSlug();
                    } catch ( \Throwable ) {
                        continue;
                    }
                }

                if ( $handlerSlug ) {
                    $shortName = basename( str_replace( '\\', '/', $handlerClass ) );
                    $slugMap[ $handlerSlug ] = $shortName;
                    //error_log( 'handlerSlug: ' . $handlerSlug . '; shortName: ' . $shortName );
                }
            }
        }

        // === Scan for field files (includes optional Fields/Subtypes)
        $fieldFiles = array_merge(
            glob( $fieldsDir . '/*Fields.php' ) ?: [],
            is_dir( $subtypesDir ) ? ( glob( $subtypesDir . '/*Fields.php' ) ?: [] ) : []
        );

        //foreach ( glob( $fieldsDir . '/*Fields.php' ) as $file ) {
        foreach ( $fieldFiles as $file ) {
            require_once $file;
            //error_log( 'Fields file filename: ' . $file );

            $className = $this->getFQCNFromFilename( $file );
            //error_log( 'Fields file className: ' . $className );

            if (
                class_exists( $className ) &&
                is_subclass_of( $className, FieldGroupInterface::class )
            ) {
                // First, handle global post-type scoped field groups
                if (is_subclass_of($className, PostTypeFieldGroupInterface::class)) {
                    //error_log( 'className: ' . $className . ' is_subclass_of PostTypeFieldGroupInterface');
                    try {
                        $instance = new $className();
                        $pt = $instance->getPostType();
                        if (array_key_exists($pt, $activePostTypes)) {
                            $className::register();
                            continue; // handled
                        }
                    } catch (\Throwable $e) { /* ignore and fall through */ }
                }

                // Handle Subtype-scoped field groups
                if ( is_subclass_of( $className, SubtypeFieldGroupInterface::class ) ) {
                    //error_log( 'className: ' . $className . ' is_subclass_of SubtypeFieldGroupInterface');
                    try {
                        $instance = new $className();
                        $pt = $instance->getPostType();
                        if ( array_key_exists( $pt, $activePostTypes ) ) {
                            $className::register();
                            continue;
                        }
                    } catch ( \Throwable $e ) { /* ignore and fall through */ }
                }

                $basename = basename( $file, '.php' ); // e.g. "MonsterFields"
                $shortName = str_replace( 'Fields', '', $basename ); // e.g. "Monster"
                //error_log( 'basename: ' . $basename . '; shortName: ' . $shortName );

                $matched = false;

                foreach ( $slugMap as $slug => $expectedName ) {
                    if ( strtolower( $shortName ) === strtolower( $expectedName ) ) {
                        if ( array_key_exists( $slug, $activePostTypes ) ) {
                            //error_log( 'about to register (via slugMap): ' . $className );
                            $className::register();
                            $matched = true;
                            break;
                        }
                    }
                }

                if ( !$matched && $this->isModuleFieldGroup( $basename, $moduleClass ) ) {
                    //error_log( 'about to register (via slugMap): ' . $className );
                    $className::register();
                }
            } else {
                // Something's wrong. Do some logging.
                if ( !class_exists( $className ) ) { error_log( '*** class: ' . $className . ' DNE' ); } else if ( !is_subclass_of( $className, FieldGroupInterface::class ) ) { error_log( '*** class: ' . $className . ' is not subclass of FieldGroupInterface' ); }
            }
        }
    }

    protected function isModuleFieldGroup( string $basename, string $moduleClass ): bool
    {
        $moduleBaseName = ( new \ReflectionClass( $moduleClass ) )->getShortName();
        $expectedName = $moduleBaseName . 'Fields';

        return $basename === $expectedName;
    }

    /*
    // V1 -- works for WXC but not add-on modules
    protected function getFullyQualifiedClassName( string $file ): string
    {
        // Get path for "src" dir
        $srcPath = dirname( __DIR__, 2 ) . '/src/';

        // Normalize slashes
        $file = str_replace( ['\\', '/'], DIRECTORY_SEPARATOR, $file );
        $srcPath = str_replace( ['\\', '/'], DIRECTORY_SEPARATOR, $srcPath );

        // Remove everything before "src/"
        $relativePath = str_replace( $srcPath, '', $file );

        // Replace directory separators with backslashes and strip ".php"
        $relativePath = str_replace( [DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relativePath );

        return 'WXC\\' . $relativePath;
    }*/

    // WIP alt generalized method
    /*protected function getFullyQualifiedClassName(string $file): string
    {
        return NamespaceUtil::fqcnFromFile($file, 'WXC', dirname(__DIR__, 2) . '/src/');
    }*/

    // Get fqcn from filename
    protected function getFQCNFromFilename(string $file): string
    {
        // 1) Prefer the declared namespace in the file (works for external add-on modules)
        $namespace = $this->extractNamespaceFromFile($file);
        $className = pathinfo($file, PATHINFO_FILENAME);

        if ($namespace) {
            return $namespace . '\\' . $className;
        }

        // 2) Fallback: derive from path relative to this plugin's src/
        $srcPath = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, dirname(__DIR__, 2) . '/src/'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $normalizedFile = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $file);

        $relativePath = str_starts_with($normalizedFile, $srcPath)
            ? substr($normalizedFile, strlen($srcPath))
            : $normalizedFile; // last-resort

        $relativePath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
        $relativePath = preg_replace('/\.php$/', '', $relativePath);

        // Keep old behavior for backward compatibility
        return 'WXC\\' . $relativePath;
    }

    /**
     * Robustly extract the declared namespace from a PHP file.
     * Returns null if none is found.
     */
    private function extractNamespaceFromFile(string $file): ?string
    {
        $code = @file_get_contents($file);
        if ($code === false || $code === '') {
            return null;
        }

        // Use tokens for reliability across formatting styles.
        $tokens = token_get_all($code);

        $collect = false;
        $namespace = '';

        foreach ($tokens as $token) {
            if (is_array($token)) {
                if ($token[0] === T_NAMESPACE) {
                    $collect = true;
                    $namespace = '';
                    continue;
                }

                if ($collect && ($token[0] === T_STRING || $token[0] === T_NS_SEPARATOR || (defined('T_NAME_QUALIFIED') && $token[0] === T_NAME_QUALIFIED))) {
                    $namespace .= $token[1];
                    continue;
                }
            } else {
                // End of namespace declaration
                if ($collect && $token === ';') {
                    return $namespace !== '' ? $namespace : null;
                }
            }
        }

        return null;
    }


    /*
    public static function registerAll(): void {
        $basePath = __DIR__ . '/../Modules/';
        $baseNamespace = 'WXC\\Modules\\';
        $activePostTypes = PostTypeRegistrar::getActivePostTypes();

        $iterator = new RegexIterator(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath)),
            '/Fields\/.+\.php$/',
            RegexIterator::GET_MATCH
        );

        foreach( $iterator as $files ) {
            foreach( $files as $file ) {
                require_once $file;

                $relativePath = str_replace([$basePath, '.php'], '', $file);
                $classPath = str_replace('/', '\\', $relativePath);
                $fqcn = $baseNamespace . $classPath;

                if(
                    class_exists($fqcn) &&
                    is_subclass_of($fqcn, FieldGroupInterface::class)
                ) {
                    $groupPostTypes = $fqcn::getPostTypes();
                    if( array_intersect($groupPostTypes, $activePostTypes) ) {
                        $fqcn::register();
                    }
                }
            }
        }
    }
    */
}
