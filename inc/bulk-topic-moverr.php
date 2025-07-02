<?php
/**
 * Plugin Name: Bulk Topic Term Mover
 * Description: Admin tool to bulk move topic terms under parent terms
 * Version: 1.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BulkTopicTermMover {
    
    private $taxonomy = 'topics'; // Your ACF taxonomy name
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_post_bulk_move_terms', array($this, 'handle_bulk_move'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'Bulk Move Topics',
            'Bulk Move Topics',
            'manage_categories',
            'bulk-move-topics',
            array($this, 'admin_page')
        );
    }
    
    public function enqueue_scripts($hook) {
        if ($hook !== 'tools_page_bulk-move-topics') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js', array('jquery'), '4.1.0', true);
        wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css', array(), '4.1.0');
        
        // Custom styles and scripts
        wp_add_inline_style('select2', '
            .term-mover-container {
                max-width: 800px;
                margin: 20px 0;
            }
            .form-section {
                background: #fff;
                padding: 20px;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                margin-bottom: 20px;
            }
            .form-section h3 {
                margin-top: 0;
                color: #23282d;
            }
            .select2-container {
                width: 100% !important;
            }
            .move-preview {
                background: #f7f7f7;
                border: 1px solid #ddd;
                padding: 15px;
                margin: 15px 0;
                border-radius: 4px;
            }
            .preview-item {
                padding: 5px 0;
                border-bottom: 1px solid #eee;
            }
            .preview-item:last-child {
                border-bottom: none;
            }
            .success-message {
                background: #d4edda;
                color: #155724;
                padding: 10px 15px;
                border: 1px solid #c3e6cb;
                border-radius: 4px;
                margin: 15px 0;
            }
            .error-message {
                background: #f8d7da;
                color: #721c24;
                padding: 10px 15px;
                border: 1px solid #f5c6cb;
                border-radius: 4px;
                margin: 15px 0;
            }
        ');
        
        wp_add_inline_script('select2', '
            jQuery(document).ready(function($) {
                $(".terms-select").select2({
                    placeholder: "Select terms to move...",
                    allowClear: true
                });
                
                $(".parent-select").select2({
                    placeholder: "Select new parent term...",
                    allowClear: true
                });
                
                // Preview functionality
                function updatePreview() {
                    var selectedTerms = $(".terms-select").val();
                    var parentTerm = $(".parent-select option:selected").text();
                    var parentId = $(".parent-select").val();
                    
                    if (selectedTerms && selectedTerms.length > 0 && parentId) {
                        var preview = "<h4>Preview of changes:</h4>";
                        selectedTerms.forEach(function(termId) {
                            var termText = $(".terms-select option[value=\'" + termId + "\']").text();
                            preview += "<div class=\"preview-item\">";
                            preview += "<strong>" + termText + "</strong> will be moved under <strong>" + parentTerm + "</strong>";
                            preview += "</div>";
                        });
                        $(".move-preview").html(preview).show();
                    } else {
                        $(".move-preview").hide();
                    }
                }
                
                $(".terms-select, .parent-select").on("change", updatePreview);
                
                // Form validation
                $("#bulk-move-form").on("submit", function(e) {
                    var selectedTerms = $(".terms-select").val();
                    var parentTerm = $(".parent-select").val();
                    
                    if (!selectedTerms || selectedTerms.length === 0) {
                        alert("Please select at least one term to move.");
                        e.preventDefault();
                        return false;
                    }
                    
                    if (!parentTerm) {
                        alert("Please select a parent term.");
                        e.preventDefault();
                        return false;
                    }
                    
                    // Check if trying to move a parent under its own child
                    if (selectedTerms.includes(parentTerm)) {
                        alert("You cannot move a term under itself.");
                        e.preventDefault();
                        return false;
                    }
                    
                    return confirm("Are you sure you want to move " + selectedTerms.length + " term(s)? This action cannot be undone.");
                });
            });
        ');
    }
    
    public function admin_page() {
        // Handle success/error messages
        $message = '';
        if (isset($_GET['moved'])) {
            $count = intval($_GET['moved']);
            $message = '<div class="success-message">Successfully moved ' . $count . ' term(s)!</div>';
        } elseif (isset($_GET['error'])) {
            $error = sanitize_text_field($_GET['error']);
            $message = '<div class="error-message">Error: ' . $error . '</div>';
        }
        
        // Get all terms in the taxonomy
        $terms = get_terms(array(
            'taxonomy' => $this->taxonomy,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        // Organize terms by hierarchy for better display
        $term_hierarchy = $this->build_term_hierarchy($terms);
        
        ?>
        <div class="wrap">
            <h1>Bulk Move Topic Terms</h1>
            <p>Use this tool to move multiple topic terms under a new parent term.</p>
            
            <?php echo $message; ?>
            
            <div class="term-mover-container">
                <form id="bulk-move-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('bulk_move_terms_nonce', 'bulk_move_nonce'); ?>
                    <input type="hidden" name="action" value="bulk_move_terms">
                    
                    <div class="form-section">
                        <h3>Select Terms to Move</h3>
                        <select name="terms_to_move[]" class="terms-select" multiple style="width: 100%; height: 200px;">
                            <?php echo $this->render_term_options($term_hierarchy); ?>
                        </select>
                        <p class="description">Hold Ctrl/Cmd to select multiple terms</p>
                    </div>
                    
                    <div class="form-section">
                        <h3>Select New Parent Term</h3>
                        <select name="new_parent" class="parent-select" style="width: 100%;">
                            <option value="">-- Make Root Level --</option>
                            <?php echo $this->render_term_options($term_hierarchy, 0, true); ?>
                        </select>
                        <p class="description">Choose the parent term, or leave empty to move to root level</p>
                    </div>
                    
                    <div class="move-preview" style="display: none;"></div>
                    
                    <p class="submit">
                        <input type="submit" class="button-primary" value="Move Selected Terms">
                    </p>
                </form>
            </div>
            
            <div class="form-section">
                <h3>Current Term Hierarchy</h3>
                <div style="font-family: monospace; line-height: 1.6;">
                    <?php echo $this->display_term_hierarchy($term_hierarchy); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function build_term_hierarchy($terms) {
        $hierarchy = array();
        $term_map = array();
        
        // First, create a map of all terms
        foreach ($terms as $term) {
            $term_map[$term->term_id] = $term;
            $term_map[$term->term_id]->children = array();
        }
        
        // Then, build the hierarchy
        foreach ($terms as $term) {
            if ($term->parent == 0) {
                $hierarchy[$term->term_id] = $term_map[$term->term_id];
            } else {
                if (isset($term_map[$term->parent])) {
                    $term_map[$term->parent]->children[$term->term_id] = $term_map[$term->term_id];
                }
            }
        }
        
        return $hierarchy;
    }
    
    private function render_term_options($terms, $depth = 0, $exclude_children = false) {
        $output = '';
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
        
        foreach ($terms as $term) {
            $output .= '<option value="' . $term->term_id . '">';
            $output .= $indent . esc_html($term->name) . ' (' . $term->count . ')';
            $output .= '</option>';
            
            if (!$exclude_children && !empty($term->children)) {
                $output .= $this->render_term_options($term->children, $depth + 1, $exclude_children);
            }
        }
        
        return $output;
    }
    
    private function display_term_hierarchy($terms, $depth = 0) {
        $output = '';
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
        
        foreach ($terms as $term) {
            $output .= $indent . '├─ ' . esc_html($term->name) . ' (' . $term->count . ' posts)<br>';
            
            if (!empty($term->children)) {
                $output .= $this->display_term_hierarchy($term->children, $depth + 1);
            }
        }
        
        return $output;
    }
    
    public function handle_bulk_move() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['bulk_move_nonce'], 'bulk_move_terms_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check user permissions
        if (!current_user_can('manage_categories')) {
            wp_die('You do not have permission to perform this action');
        }
        
        $terms_to_move = isset($_POST['terms_to_move']) ? array_map('intval', $_POST['terms_to_move']) : array();
        $new_parent = isset($_POST['new_parent']) ? intval($_POST['new_parent']) : 0;
        
        if (empty($terms_to_move)) {
            $this->redirect_with_error('No terms selected to move');
            return;
        }
        
        $moved_count = 0;
        $errors = array();
        
        foreach ($terms_to_move as $term_id) {
            // Prevent moving a term under itself or its descendants
            if ($new_parent != 0 && $this->is_descendant($term_id, $new_parent)) {
                $errors[] = "Cannot move term ID $term_id: would create circular reference";
                continue;
            }
            
            $result = wp_update_term($term_id, $this->taxonomy, array(
                'parent' => $new_parent
            ));
            
            if (is_wp_error($result)) {
                $errors[] = "Failed to move term ID $term_id: " . $result->get_error_message();
            } else {
                $moved_count++;
            }
        }
        
        if (!empty($errors)) {
            $this->redirect_with_error(implode('; ', $errors));
        } else {
            $this->redirect_with_success($moved_count);
        }
    }
    
    private function is_descendant($parent_id, $potential_child_id) {
        $term = get_term($potential_child_id, $this->taxonomy);
        
        while ($term && $term->parent != 0) {
            if ($term->parent == $parent_id) {
                return true;
            }
            $term = get_term($term->parent, $this->taxonomy);
        }
        
        return false;
    }
    
    private function redirect_with_success($count) {
        $redirect_url = admin_url('tools.php?page=bulk-move-topics&moved=' . $count);
        wp_redirect($redirect_url);
        exit;
    }
    
    private function redirect_with_error($error) {
        $redirect_url = admin_url('tools.php?page=bulk-move-topics&error=' . urlencode($error));
        wp_redirect($redirect_url);
        exit;
    }
}

// Initialize the plugin
new BulkTopicTermMover();
?>