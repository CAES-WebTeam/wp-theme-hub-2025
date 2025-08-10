<?php
/**
 * Plugin Name: Taxonomy Renamer Tool
 * Description: A tool to safely rename the 'keywords' taxonomy to 'topics',
 * migrate terms and post assignments, and update ACF fields.
 * Version: 1.0
 * Author: Your Name
 */

// Start the session if it's not already started. This is crucial for displaying messages after redirect.
if ( ! session_id() ) {
    session_start();
}

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class for handling taxonomy migration from 'keywords' to 'topics'.
 */
class TaxonomyMigrator {

    private $old_taxonomy = 'keywords';
    private $new_taxonomy = 'topics';
    private $acf_field_key = 'field_primary_keywords'; // Original ACF field key
    private $acf_field_name = 'primary_keywords'; // Original ACF field name
    private $acf_group_key = 'group_primary_keyword'; // Original ACF field group key

    public function __construct() {
        // Add admin menu page
        add_action( 'admin_menu', array( $this, 'add_admin_menu_page' ) );
        // Handle form submissions
        add_action( 'admin_init', array( $this, 'handle_form_submission' ) );

        // Ensure the new taxonomy is registered early for migration purposes
        add_action( 'init', array( $this, 'register_new_taxonomy_for_migration' ), 5 );
        // Ensure the old taxonomy is still registered for migration purposes
        add_action( 'init', array( $this, 'register_old_taxonomy_for_migration' ), 5 );
        // Ensure ACF fields are registered for modification
        add_action( 'acf/init', array( $this, 'register_acf_fields_for_migration' ), 5 );
    }

    /**
     * Registers the new 'topics' taxonomy.
     * This is registered early to ensure it's available during migration.
     */
    public function register_new_taxonomy_for_migration() {
        $labels = array(
            'name'              => _x('Topics', 'taxonomy general name'),
            'singular_name'     => _x('Topic', 'taxonomy singular name'),
            'search_items'      => __('Search Topics'),
            'all_items'         => __('All Topics'),
            'parent_item'       => __('Parent Topic'),
            'parent_item_colon' => __('Parent Topic:'),
            'edit_item'         => __('Edit Topic'),
            'update_item'       => __('Update Topic'),
            'add_new_item'      => __('Add New Topic'),
            'new_item_name'     => __('New Topic Name'),
            'menu_name'         => __('Topics'),
        );

        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'show_in_rest'      => true,
            'rewrite'           => array('slug' => 'topic'),
        );

        // Register the new taxonomy and associate it with the same post types
        register_taxonomy( $this->new_taxonomy, array('post', 'publications', 'shorthand_story'), $args );
    }

    /**
     * Registers the old 'keywords' taxonomy.
     * This is registered early to ensure it's available during migration.
     */
    public function register_old_taxonomy_for_migration() {
        $labels = array(
            'name'              => _x('Keywords', 'taxonomy general name'),
            'singular_name'     => _x('Keyword', 'taxonomy singular name'),
            'search_items'      => __('Search Keywords'),
            'all_items'         => __('All Keywords'),
            'parent_item'       => __('Parent Keyword'),
            'parent_item_colon' => __('Parent Keyword:'),
            'edit_item'         => __('Edit Keyword'),
            'update_item'       => __('Update Keyword'),
            'add_new_item'      => __('Add New Keyword'),
            'new_item_name'     => __('New Keyword Name'),
            'menu_name'         => __('Keywords'),
        );

        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'show_in_rest'      => true,
            'rewrite'           => array('slug' => 'keyword'),
        );

        // Register the taxonomy and associate it with the 'publications' post type
        register_taxonomy( $this->old_taxonomy, array('post', 'publications','shorthand_story'), $args);
    }

    /**
     * Registers the ACF fields early to ensure they are available for modification.
     * This uses the user-provided ACF field definition.
     */
    public function register_acf_fields_for_migration() {
        if( function_exists('acf_add_local_field_group') ):
            acf_add_local_field_group(array(
                'key' => $this->acf_group_key,
                'title' => 'Primary Keywords', // Original title
                'fields' => array(
                    array(
                        'key' => $this->acf_field_key,
                        'label' => 'Primary Keywords',
                        'name' => $this->acf_field_name,
                        'type' => 'taxonomy',
                        'taxonomy' => $this->old_taxonomy, // Original taxonomy
                        'field_type' => 'multi_select',
                        'allow_null' => 1,
                        'return_format' => 'object',
                        'multiple' => 1,
                    )
                ),
                'location' => array(
                    array(
                        array(
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'post',
                        ),
                    ),
                    array(
                        array(
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'publications',
                        ),
                    ),
                    array(
                        array(
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'shorthand_story',
                        ),
                    ),
                ),
                'position' => 'side',
                'menu_order' => 0,
                'style' => 'default',
            ));
        endif;
    }

    /**
     * Adds the admin menu page under "Tools".
     */
    public function add_admin_menu_page() {
        add_management_page(
            'Taxonomy Renamer',
            'Taxonomy Renamer',
            'manage_options',
            'taxonomy-renamer',
            array( $this, 'render_admin_page' )
        );
    }

    /**
     * Renders the admin page content.
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>Taxonomy Renamer Tool</h1>
            <p>This tool will migrate terms and post assignments from the '<?php echo esc_html( $this->old_taxonomy ); ?>' taxonomy to a new '<?php echo esc_html( $this->new_taxonomy ); ?>' taxonomy.</p>
            <p>It will also update the associated ACF field (<code><?php echo esc_html( $this->acf_field_name ); ?></code>) to use the new taxonomy.</p>
            <p><strong>Important:</strong> It is highly recommended to backup your database before proceeding with the migration.</p>

            <form method="post">
                <?php wp_nonce_field( 'taxonomy_migration_action', 'taxonomy_migration_nonce' ); ?>
                <p>
                    <label>
                        <input type="checkbox" name="include_drafts" value="1"> Include Draft Posts
                    </label>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="remove_old_terms" value="1"> Remove old 'keywords' terms from posts after migration
                    </label>
                </p>
                <p>
                    <input type="submit" name="preview_migration" class="button button-secondary" value="Preview Migration">
                    <input type="submit" name="execute_migration" class="button button-primary" value="Execute Migration">
                </p>
            </form>

            <div id="migration-results">
                <?php
                // Display messages from session
                if ( isset( $_SESSION['taxonomy_migrator_message'] ) ) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . wp_kses_post( $_SESSION['taxonomy_migrator_message'] ) . '</p></div>';
                    unset( $_SESSION['taxonomy_migrator_message'] );
                }
                if ( isset( $_SESSION['taxonomy_migrator_error'] ) ) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . wp_kses_post( $_SESSION['taxonomy_migrator_error'] ) . '</p></div>';
                    unset( $_SESSION['taxonomy_migrator_error'] );
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Handles form submissions for preview and execution.
     */
    public function handle_form_submission() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['preview_migration'] ) || isset( $_POST['execute_migration'] ) ) {
            if ( ! isset( $_POST['taxonomy_migration_nonce'] ) || ! wp_verify_nonce( $_POST['taxonomy_migration_nonce'], 'taxonomy_migration_action' ) ) {
                $_SESSION['taxonomy_migrator_error'] = 'Security check failed. Please try again.';
                return;
            }

            $is_preview = isset( $_POST['preview_migration'] );
            $include_drafts = isset( $_POST['include_drafts'] ) && $_POST['include_drafts'] === '1';
            $remove_old_terms = isset( $_POST['remove_old_terms'] ) && $_POST['remove_old_terms'] === '1'; // Get value of new checkbox

            $this->perform_migration( $is_preview, $include_drafts, $remove_old_terms ); // Pass the new parameter
        }
    }

    /**
     * Performs the taxonomy migration.
     *
     * @param bool $is_preview True if this is a preview run, false for actual execution.
     * @param bool $include_drafts True if draft posts should be included in the migration.
     * @param bool $remove_old_terms True if old 'keywords' terms should be removed from posts.
     */
    private function perform_migration( $is_preview, $include_drafts = false, $remove_old_terms = false ) {
        $message = '';
        $error = '';
        $results = array(
            'terms_migrated' => 0,
            'posts_updated'  => 0,
            'acf_updated'    => false,
            'term_map'       => array(),
        );

        $message .= '<h2>' . ( $is_preview ? 'Preview' : 'Execution' ) . ' Results:</h2>';

        // 1. Migrate Terms
        $old_terms = get_terms( array(
            'taxonomy'   => $this->old_taxonomy,
            'hide_empty' => false,
        ) );

        if ( is_wp_error( $old_terms ) ) {
            $error .= '<p>Error retrieving old terms: ' . esc_html( $old_terms->get_error_message() ) . '</p>';
            $_SESSION['taxonomy_migrator_error'] = $error;
            return;
        }

        if ( empty( $old_terms ) ) {
            $message .= '<p>No terms found in the "' . esc_html( $this->old_taxonomy ) . '" taxonomy to migrate.</p>';
        } else {
            $message .= '<h3>Term Migration:</h3>';
            foreach ( $old_terms as $old_term ) {
                $new_term_id = 0;
                $existing_new_term = get_term_by( 'slug', $old_term->slug, $this->new_taxonomy );

                if ( $existing_new_term ) {
                    $new_term_id = $existing_new_term->term_id;
                    $message .= '<p>Term "' . esc_html( $old_term->name ) . '" (ID: ' . esc_html( $old_term->term_id ) . ') already exists as "' . esc_html( $existing_new_term->name ) . '" (ID: ' . esc_html( $new_term_id ) . ') in "' . esc_html( $this->new_taxonomy ) . '".</p>';
                } else {
                    if ( ! $is_preview ) {
                        $insert_result = wp_insert_term(
                            $old_term->name,
                            $this->new_taxonomy,
                            array(
                                'description' => $old_term->description,
                                'slug'        => $old_term->slug,
                                'parent'      => 0, // Assuming non-hierarchical, or handle parent if needed
                            )
                        );

                        if ( ! is_wp_error( $insert_result ) ) {
                            $new_term_id = $insert_result['term_id'];
                            $results['terms_migrated']++;
                            $message .= '<p>Migrated term "' . esc_html( $old_term->name ) . '" (ID: ' . esc_html( $old_term->term_id ) . ') to "' . esc_html( $this->new_taxonomy ) . '" (New ID: ' . esc_html( $new_term_id ) . ').</p>';
                        } else {
                            $error .= '<p>Error migrating term "' . esc_html( $old_term->name ) . '": ' . esc_html( $insert_result->get_error_message() ) . '</p>';
                        }
                    } else {
                        $message .= '<p>Would migrate term "' . esc_html( $old_term->name ) . '" (ID: ' . esc_html( $old_term->term_id ) . ') to "' . esc_html( $this->new_taxonomy ) . '".</p>';
                        // For preview, we can simulate a new ID or just use 0.
                        // For accurate post mapping preview, we need a consistent simulated ID.
                        // Let's use a placeholder for preview.
                        $new_term_id = 'PREVIEW_NEW_ID_' . $old_term->term_id;
                    }
                }
                if ( $new_term_id ) {
                    $results['term_map'][ $old_term->term_id ] = $new_term_id;
                }
            }
        }

        // 2. Migrate Post Assignments
        $post_types = array('post', 'publications', 'shorthand_story');
        $args = array(
            'post_type'      => $post_types,
            'posts_per_page' => -1, // Get all posts
            'tax_query'      => array(
                array(
                    'taxonomy' => $this->old_taxonomy,
                    'field'    => 'slug',
                    'terms'    => array_map( function( $term ) { return $term->slug; }, $old_terms ),
                    'operator' => 'IN',
                ),
            ),
            'fields'         => 'ids', // Only get post IDs for efficiency
        );

        // Conditionally set post_status based on $include_drafts
        if ( $include_drafts ) {
            $args['post_status'] = array( 'publish', 'draft' );
        } else {
            $args['post_status'] = 'publish';
        }

        $posts_with_old_terms = get_posts( $args );

        if ( is_wp_error( $posts_with_old_terms ) ) {
            $error .= '<p>Error retrieving posts with old terms: ' . esc_html( $posts_with_old_terms->get_error_message() ) . '</p>';
            $_SESSION['taxonomy_migrator_error'] = $error;
            return;
        }

        $message .= '<h3>Post Assignment Migration:</h3>';
        if ( empty( $posts_with_old_terms ) ) {
            $message .= '<p>No posts found with terms from the "' . esc_html( $this->old_taxonomy ) . '" taxonomy.</p>';
        } else {
            foreach ( $posts_with_old_terms as $post_id ) {
                $old_post_terms = wp_get_post_terms( $post_id, $this->old_taxonomy, array( 'fields' => 'ids' ) );
                $new_post_terms = array();

                foreach ( $old_post_terms as $old_term_id ) {
                    if ( isset( $results['term_map'][ $old_term_id ] ) ) {
                        $new_post_terms[] = $results['term_map'][ $old_term_id ];
                    }
                }

                if ( ! empty( $new_post_terms ) ) {
                    if ( ! $is_preview ) {
                        // Conditionally remove old taxonomy terms
                        if ( $remove_old_terms ) {
                            wp_set_post_terms( $post_id, null, $this->old_taxonomy );
                            $message .= '<p>Removed old "' . esc_html( $this->old_taxonomy ) . '" terms from post ID ' . esc_html( $post_id ) . '.</p>';
                        } else {
                            $message .= '<p>Retained old "' . esc_html( $this->old_taxonomy ) . '" terms on post ID ' . esc_html( $post_id ) . ' (optional removal skipped).</p>';
                        }

                        // Add new taxonomy terms
                        $set_result = wp_set_post_terms( $post_id, $new_post_terms, $this->new_taxonomy, false ); // false to append
                        if ( ! is_wp_error( $set_result ) ) {
                            $results['posts_updated']++;
                            $message .= '<p>Updated post ID ' . esc_html( $post_id ) . ': assigned new "' . esc_html( $this->new_taxonomy ) . '" terms (' . implode( ', ', array_map('esc_html', $new_post_terms) ) . ').</p>';
                        } else {
                            $error .= '<p>Error updating terms for post ID ' . esc_html( $post_id ) . ': ' . esc_html( $set_result->get_error_message() ) . '</p>';
                        }
                    } else {
                        $message .= '<p>Would update post ID ' . esc_html( $post_id ) . ': assign new "' . esc_html( $this->new_taxonomy ) . '" terms (' . implode( ', ', array_map('esc_html', $new_post_terms) ) . ').</p>';
                        if ( $remove_old_terms ) {
                            $message .= '<p>Would remove old "' . esc_html( $this->old_taxonomy ) . '" terms from post ID ' . esc_html( $post_id ) . '.</p>';
                        } else {
                            $message .= '<p>Would retain old "' . esc_html( $this->old_taxonomy ) . '" terms on post ID ' . esc_html( $post_id ) . ' (optional removal skipped).</p>';
                        }
                    }
                }
            }
        }

        // 3. Update ACF Field
        if ( function_exists( 'acf_get_field' ) && function_exists( 'acf_update_field' ) ) {
            $acf_field = acf_get_field( $this->acf_field_key );

            if ( $acf_field ) {
                if ( $acf_field['taxonomy'] === $this->old_taxonomy ) {
                    if ( ! $is_preview ) {
                        $acf_field['taxonomy'] = $this->new_taxonomy;
                        $acf_field['name'] = 'primary_topics'; // Update field name
                        $acf_field['label'] = 'Primary Topics'; // Update field label
                        $acf_field['key'] = 'field_primary_topics'; // Update field key for consistency

                        $update_result = acf_update_field( $acf_field );
                        if ( $update_result ) {
                            $results['acf_updated'] = true;
                            $message .= '<h3>ACF Field Update:</h3>';
                            $message .= '<p>Updated ACF field "Primary Keywords" (<code>' . esc_html( $this->acf_field_name ) . '</code>) to use the new "' . esc_html( $this->new_taxonomy ) . '" taxonomy, renamed to "Primary Topics" (<code>primary_topics</code>) and key to <code>field_primary_topics</code>.</p>';
                        } else {
                            $error .= '<p>Error updating ACF field: Could not update the field definition.</p>';
                        }
                    } else {
                        $message .= '<h3>ACF Field Update:</h3>';
                        $message .= '<p>Would update ACF field "Primary Keywords" (<code>' . esc_html( $this->acf_field_name ) . '</code>) to use the new "' . esc_html( $this->new_taxonomy ) . '" taxonomy, rename to "Primary Topics" (<code>primary_topics</code>) and key to <code>field_primary_topics</code>.</p>';
                    }
                } else {
                    $message .= '<h3>ACF Field Update:</h3>';
                    $message .= '<p>ACF field "Primary Keywords" (<code>' . esc_html( $this->acf_field_name ) . '</code>) already uses the "' . esc_html( $acf_field['taxonomy'] ) . '" taxonomy. No update needed.</p>';
                }
            } else {
                $error .= '<p>ACF field with key <code>' . esc_html( $this->acf_field_key ) . '</code> not found. Please ensure ACF is active and the field group is registered.</p>';
            }
        } else {
            $error .= '<p>ACF plugin not active or functions not available. Cannot update ACF field.</p>';
        }

        if ( ! $is_preview && empty( $error ) ) {
            $message .= '<h2>Migration Complete!</h2>';
            $message .= '<p><strong>Next Steps:</strong></p>';
            $message .= '<ul>';
            $message .= '<li>You can now remove the old "keywords" taxonomy registration from your `functions.php` or plugin file.</li>';
            $message .= '<li>You should also update the `acf_add_local_field_group` call in your code to reflect the new taxonomy name, label, and key for the "Primary Topics" field.</li>';
            $message .= '<li>Clear any caching plugins you might be using.</li>';
            $message .= '</ul>';
        }

        // Store messages in session to display after redirect
        $_SESSION['taxonomy_migrator_message'] = $message;
        if ( ! empty( $error ) ) {
            $_SESSION['taxonomy_migrator_error'] = $error;
        }

        // Redirect to prevent form resubmission
        wp_redirect( admin_url( 'tools.php?page=taxonomy-renamer' ) );
        exit;
    }
}

// Initialize the migrator
new TaxonomyMigrator();

// --- Original Taxonomy and ACF Field Registration (Keep these in your theme/plugin for now) ---
// You will remove these after successful migration and update them to 'topics'
// The TaxonomyMigrator class registers these temporarily for its own use during migration.

// Register the 'Keywords' taxonomy for the Publications
function register_keywords_taxonomy_original()
{
    $labels = array(
        'name'              => _x('Keywords', 'taxonomy general name'),
        'singular_name'     => _x('Keyword', 'taxonomy singular name'),
        'search_items'      => __('Search Keywords'),
        'all_items'         => __('All Keywords'),
        'parent_item'       => __('Parent Keyword'),
        'parent_item_colon' => __('Parent Keyword:'),
        'edit_item'         => __('Edit Keyword'),
        'update_item'       => __('Update Keyword'),
        'add_new_item'      => __('Add New Keyword'),
        'new_item_name'     => __('New Keyword Name'),
        'menu_name'         => __('Keywords'),
    );

    $args = array(
        'hierarchical'      => false,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'show_in_rest'      => true,
        'rewrite'           => array('slug' => 'keyword'),
    );

    // Register the taxonomy and associate it with the 'publications' post type
    // This is the original registration from your input.
    // The TaxonomyMigrator class will also register this, but this is for your reference.
    register_taxonomy('keywords', array('post', 'publications','shorthand_story'), $args);
}
// Do not add this action if you are using the TaxonomyMigrator class, as it registers it.
// If you are putting this in functions.php, ensure this is only called once.
// add_action('init', 'register_keywords_taxonomy_original');

// Primary Keywords ACF Field - Original
function create_primary_keyword_field_original() {
    if( function_exists('acf_add_local_field_group') ):
        acf_add_local_field_group(array(
            'key' => 'group_primary_keyword',
            'title' => 'Primary Keywords',
            'fields' => array(
                array(
                    'key' => 'field_primary_keywords',
                    'label' => 'Primary Keywords',
                    'name' => 'primary_keywords',
                    'type' => 'taxonomy',
                    'taxonomy' => 'keywords',
                    'field_type' => 'multi_select',
                    'allow_null' => 1,
                    'return_format' => 'object',
                    'multiple' => 1,
                )
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'post',
                    ),
                ),
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'publications',
                    ),
                ),
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'shorthand_story',
                    ),
                ),
            ),
            'position' => 'side',
            'menu_order' => 0,
            'style' => 'default',
        ));
    endif;
}
// Do not add this action if you are using the TaxonomyMigrator class, as it registers it.
// If you are putting this in functions.php, ensure this is only called once.
// add_action('acf/init', 'create_primary_keyword_field_original');
