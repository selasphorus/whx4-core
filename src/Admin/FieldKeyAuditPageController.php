<?php

namespace atc\WXC\Admin;

use atc\WXC\App;
use atc\WXC\Migrations\FieldKeyMigrator;

final class FieldKeyAuditPageController
{
    public function addHooks(): void
    {
        //error_log( '=== FieldKeyAuditPageController: addHooks() ===' );
        ////add_action( 'admin_menu', [ $this, 'addMenuPage' ] );
        //add_action( 'admin_init', [ $this, 'registerSettings' ] );
    }

    /*public static function register(): void
    {
        add_action( 'admin_menu', [ self::class, 'addMenuPage' ] );
    }*/

    public static function addMenuPage(): void
    {
        add_submenu_page(
            'wxc-settings', // parent slug, change if needed
            'ACF Field Audit',
            'ACF Field Audit',
            'manage_options',
            'wxc-field-audit',
            [ self::class, 'renderPage' ]
            //[ $this, 'renderPage' ] // callback
        );
    }

    public static function renderPage(): void
    {
        if ( isset( $_POST['run_audit'] ) && check_admin_referer( 'wxc_field_audit_action', 'wxc_field_audit_nonce' ) ) {
            self::renderResults();
            return;
        }

        if ( isset( $_POST['delete_orphans'] ) && check_admin_referer( 'wxc_field_audit_action', 'wxc_field_audit_nonce' ) ) {
            self::handleDeleteOrphans();
            return;
        }

        ?>
        <div class="wrap">
            <h1>ACF Field Key Audit</h1>
            <?php self::maybeRenderNotice(); ?>
            <form method="post">
                <?php wp_nonce_field( 'wxc_field_audit_action', 'wxc_field_audit_nonce' ); ?>
                <p><button type="submit" class="button button-primary" name="run_audit">Run Audit</button></p>
            </form>
        </div>
        <?php
    }

    private static function renderResults(): void
    {
        $registeredKeys = apply_filters( 'wxc_registered_field_keys', [] );
        $audit = FieldKeyMigrator::auditFieldKeys( $registeredKeys );

        ?>
        <div class="wrap">
            <h1>ACF Field Key Audit Results</h1>

            <h2>Registered Field Keys (Found)</h2>
            <ul>
                <?php foreach ( $audit['registered'] as $key ) : ?>
                    <li style="color:green;">&#10004; <?php echo esc_html( $key ); ?></li>
                <?php endforeach; ?>
            </ul>

            <h2>Orphaned Field Keys (Not Registered)</h2>
            <form method="post">
                <?php wp_nonce_field( 'wxc_field_audit_action', 'wxc_field_audit_nonce' ); ?>
                <ul>
                    <?php foreach ( $audit['orphaned'] as $key ) : ?>
                        <li style="color:red;">
                            <label>
                                <input type="checkbox" name="delete_keys[]" value="<?php echo esc_attr( $key ); ?>">
                                <?php echo esc_html( $key ); ?>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p>
                    <button type="submit" class="button button-secondary" name="delete_orphans">Delete Selected Orphaned Keys</button>
                </p>
            </form>
            <?php /*<ul>
                <?php foreach ( $audit['orphaned'] as $key ) : ?>
                    <li style="color:red;">&#10060; <?php echo esc_html( $key ); ?></li>
                <?php endforeach; ?>
            </ul> */ ?>

            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wxc-field-audit' ) ); ?>" class="button">Run Again</a></p>
        </div>
        <?php
    }

    private static function handleDeleteOrphans(): void
    {
        if ( empty( $_POST['delete_keys'] ) || ! is_array( $_POST['delete_keys'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=wxc-field-audit&deleted=0' ) );
            exit;
            //wp_die( 'No orphaned keys selected.' );
        }

        global $wpdb;

        $deleted = 0;

        foreach ( $_POST['delete_keys'] as $fieldKey ) {
            $fieldKey = sanitize_text_field( $fieldKey );

            $deleted += $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->postmeta} WHERE meta_key REGEXP '^_' AND meta_value = %s",
                    $fieldKey
                )
            );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=wxc-field-audit&deleted=' . $deleted ) );
        exit;
    }

    private static function maybeRenderNotice(): void
    {
        if ( isset( $_GET['deleted'] ) ) {
            $deleted = (int) $_GET['deleted'];

            if ( $deleted > 0 ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $deleted ) . ' orphaned field keys deleted successfully.</p></div>';
            } elseif ( $deleted === 0 ) {
                echo '<div class="notice notice-warning is-dismissible"><p>No orphaned keys were deleted.</p></div>';
            }
        }
    }

    private static function checkDuplicateKeys(): void
    {
        if ( ! function_exists( 'acf_get_local_fields' ) ) {
            echo '<div class="notice notice-error"><p>ACF not loaded yet.</p></div>';
            return;
        }

        $fields = acf_get_local_fields();
        $seenKeys = [];
        $duplicates = [];

        foreach ( $fields as $field ) {
            if ( isset( $field['key'] ) ) {
                $key = $field['key'];

                if ( isset( $seenKeys[ $key ] ) ) {
                    $duplicates[] = $key;
                } else {
                    $seenKeys[ $key ] = true;
                }
            }
        }

        ?>
        <div class="wrap">
            <h1>Check for Duplicate ACF Field Keys</h1>

            <?php if ( ! empty( $duplicates ) ) : ?>
                <div class="notice notice-error"><p>
                    ⚠️ <strong>Duplicate ACF Field Keys found:</strong><br>
                    <?php echo esc_html( implode( ', ', $duplicates ) ); ?>
                </p></div>
            <?php else : ?>
                <div class="notice notice-success"><p>
                    ✅ No duplicate ACF Field Keys detected.
                </p></div>
            <?php endif; ?>

            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=rex-field-audit' ) ); ?>" class="button">Back to Audit</a></p>
        </div>
        <?php
    }
}
