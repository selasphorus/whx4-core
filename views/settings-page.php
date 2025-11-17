<?php
// Assume these are passed into scope before including this file:
$availableModules   = $availableModules ?? [];
$activeModules      = $activeModules ?? [];
$enabledPostTypes   = $enabledPostTypes ?? [];
?>

<div class="wrap">
    <h1>Plugin Settings</h1>
    <form method="post" action="options.php">
        <?php settings_fields( 'whx4_plugin_settings_group' ); ?>
        <?php do_settings_sections( 'whx4_plugin_settings' ); ?>

        <?php
        /*
        echo '<h3>Troubleshooting...</h3>';
        echo "availableModules: <pre>" . print_r($availableModules, true) . "</pre>";
        echo "activeModules: <pre>" . print_r($activeModules, true) . "</pre>";
        echo "enabledPostTypes: <pre>" . print_r($enabledPostTypes, true) . "</pre>";
        */
        ?>

        <table class="form-table" id="whx4-settings-table">
            <tbody>
                <?php foreach ( $availableModules as $moduleSlug => $moduleClass ) :
                    $isActive  = in_array( $moduleSlug, $activeModules, true );
                    $module    = class_exists( $moduleClass ) ? new $moduleClass() : null;
                    $postTypes = $module ? $module->getPostTypes() : [];
                ?>
                    <tr>
                        <th scope="row">
                            <label>
                                <input
                                    type="checkbox"
                                    class="module-toggle"
                                    name="whx4_plugin_settings[active_modules][]"
                                    value="<?php echo esc_attr( $moduleSlug ); ?>"
                                    <?php checked( $isActive ); ?>
                                />
                                <?php echo esc_html( ucfirst($moduleSlug) ); // TODO: use module getName instead? ?>
                            </label>
                        </th>
                        <td></td>
                    </tr>

                    <?php if ( ! $module ) : ?>
                        <tr>
                            <td colspan="2">Missing class: <?php echo esc_html( $moduleClass ); ?></td>
                        </tr>
                    <?php else : ?>
                        <tr id="post-types-<?php echo esc_attr( $moduleSlug ); ?>" class="post-type-row" <?php if ( ! $isActive ) echo 'style="display:none;"'; ?>>
                            <td colspan="2" style="padding-left: 30px;">
                                <?php foreach ( $postTypes as $slug => $label ) :
                                    $isEnabled = isset( $enabledPostTypes[ $moduleSlug ] ) && in_array( $slug, $enabledPostTypes[ $moduleSlug ], true );
                                ?>
                                    <label style="display:block;">
                                        <input
                                            type="checkbox"
                                            name="whx4_plugin_settings[enabled_post_types][<?php echo esc_attr( $moduleSlug ); ?>][]"
                                            value="<?php echo esc_attr( $slug ); ?>"
                                            <?php checked( $isEnabled ); ?>
                                        />
                                        Enable <code><?php echo esc_html( $slug ); ?></code>: <?php echo esc_html( $label ); ?> <?php //echo "[key: " . esc_html( $moduleSlug ) . ']'; ?>
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
