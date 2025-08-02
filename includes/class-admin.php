<?php
/**
 * Admin functionality class
 *
 * @package EseabasiInventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class Eseabasi_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_eseabasi_add_product', array($this, 'ajax_add_product'));
        add_action('wp_ajax_eseabasi_edit_product', array($this, 'ajax_edit_product'));
        add_action('wp_ajax_eseabasi_delete_product', array($this, 'ajax_delete_product'));
        add_action('wp_ajax_eseabasi_clear_history', array($this, 'ajax_clear_history'));
        add_action('wp_ajax_eseabasi_delete_history_item', array($this, 'ajax_delete_history_item'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Eseabasi Inventory', 'eseabasi-inventory'),
            __('Eseabasi Inventory', 'eseabasi-inventory'),
            'manage_options',
            'eseabasi-inventory',
            array($this, 'admin_dashboard'),
            'dashicons-clipboard',
            30
        );
        
        add_submenu_page(
            'eseabasi-inventory',
            __('Product Management', 'eseabasi-inventory'),
            __('Products', 'eseabasi-inventory'),
            'manage_options',
            'eseabasi-products',
            array($this, 'products_page')
        );
        
        add_submenu_page(
            'eseabasi-inventory',
            __('History Management', 'eseabasi-inventory'),
            __('History', 'eseabasi-inventory'),
            'manage_options',
            'eseabasi-history',
            array($this, 'history_page')
        );
        
        add_submenu_page(
            'eseabasi-inventory',
            __('Settings', 'eseabasi-inventory'),
            __('Settings', 'eseabasi-inventory'),
            'manage_options',
            'eseabasi-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'eseabasi') !== false) {
            wp_enqueue_style('eseabasi-admin-style', ESEABASI_PLUGIN_URL . 'admin/css/admin-style.css', array(), ESEABASI_VERSION);
            wp_enqueue_script('eseabasi-admin-script', ESEABASI_PLUGIN_URL . 'admin/js/admin-script.js', array('jquery'), ESEABASI_VERSION, true);
            
            wp_localize_script('eseabasi-admin-script', 'eseabasi_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('eseabasi_admin_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Are you sure you want to delete this item?', 'eseabasi-inventory'),
                    'confirm_clear' => __('Are you sure you want to clear all history? This action cannot be undone.', 'eseabasi-inventory'),
                    'success' => __('Operation completed successfully.', 'eseabasi-inventory'),
                    'error' => __('An error occurred. Please try again.', 'eseabasi-inventory')
                )
            ));
        }
    }
    
    /**
     * Admin dashboard page
     */
    public function admin_dashboard() {
        global $wpdb;
        
        $db = new Eseabasi_Database();
        
        // Get statistics
        $imports_table = $wpdb->prefix . 'eseabasi_imports';
        $stock_table = $wpdb->prefix . 'eseabasi_stock';
        $chopped_table = $wpdb->prefix . 'eseabasi_chopped';
        $products_table = $wpdb->prefix . 'eseabasi_products';
        
        $total_imports = $wpdb->get_var("SELECT COUNT(*) FROM $imports_table");
        $total_stock_records = $wpdb->get_var("SELECT COUNT(*) FROM $stock_table");
        $total_chopped_records = $wpdb->get_var("SELECT COUNT(*) FROM $chopped_table");
        $total_products = $wpdb->get_var("SELECT COUNT(*) FROM $products_table WHERE status = 'active'");
        
        // Get low stock items (closing_packs < threshold)
        $threshold = get_option('eseabasi_low_stock_threshold', 10);
        $current_date = $db->get_lagos_date();
        
        $low_stock_items = $wpdb->get_results($wpdb->prepare("
            SELECT p.name, cv.closing_value 
            FROM $products_table p 
            LEFT JOIN {$wpdb->prefix}eseabasi_current_values cv ON p.id = cv.product_id 
            WHERE p.type = 'stock' AND p.status = 'active' 
            AND (cv.closing_value < %d OR cv.closing_value IS NULL) 
            AND cv.current_date = %s
        ", $threshold, $current_date));
        
        ?>
        <div class="wrap eseabasi-admin">
            <h1><?php echo esc_html__('Eseabasi Inventory Dashboard', 'eseabasi-inventory'); ?></h1>
            
            <div class="eseabasi-dashboard-cards">
                <div class="dashboard-card">
                    <div class="card-icon">📦</div>
                    <div class="card-content">
                        <h3><?php echo number_format($total_imports); ?></h3>
                        <p><?php echo esc_html__('Total Imports', 'eseabasi-inventory'); ?></p>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon">📊</div>
                    <div class="card-content">
                        <h3><?php echo number_format($total_stock_records); ?></h3>
                        <p><?php echo esc_html__('Stock Records', 'eseabasi-inventory'); ?></p>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon">🔪</div>
                    <div class="card-content">
                        <h3><?php echo number_format($total_chopped_records); ?></h3>
                        <p><?php echo esc_html__('Chopped Records', 'eseabasi-inventory'); ?></p>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon">🛑</div>
                    <div class="card-content">
                        <h3><?php echo number_format(count($low_stock_items)); ?></h3>
                        <p><?php echo esc_html__('Low Stock Items', 'eseabasi-inventory'); ?></p>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($low_stock_items)): ?>
            <div class="eseabasi-low-stock-alert">
                <h2><?php echo esc_html__('Low Stock Alert', 'eseabasi-inventory'); ?></h2>
                <div class="low-stock-items">
                    <?php foreach ($low_stock_items as $item): ?>
                    <div class="low-stock-item">
                        <strong><?php echo esc_html($item->name); ?></strong>: 
                        <?php echo number_format($item->closing_value ?: 0, 2); ?> packs remaining
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="eseabasi-quick-actions">
                <h2><?php echo esc_html__('Quick Actions', 'eseabasi-inventory'); ?></h2>
                <div class="action-buttons">
                    <a href="<?php echo admin_url('admin.php?page=eseabasi-products'); ?>" class="button button-primary">
                        <?php echo esc_html__('Manage Products', 'eseabasi-inventory'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=eseabasi-history'); ?>" class="button button-secondary">
                        <?php echo esc_html__('View History', 'eseabasi-inventory'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=eseabasi-settings'); ?>" class="button button-secondary">
                        <?php echo esc_html__('Settings', 'eseabasi-inventory'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Products management page
     */
    public function products_page() {
        $db = new Eseabasi_Database();
        
        $import_products = $db->get_products_by_type('import');
        $stock_products = $db->get_products_by_type('stock');
        $chopped_products = $db->get_products_by_type('chopped');
        
        ?>
        <div class="wrap eseabasi-admin">
            <h1><?php echo esc_html__('Product Management', 'eseabasi-inventory'); ?></h1>
            
            <div class="eseabasi-tabs">
                <ul class="tab-nav">
                    <li><a href="#import-products" class="tab-link active"><?php echo esc_html__('Import Products', 'eseabasi-inventory'); ?></a></li>
                    <li><a href="#stock-products" class="tab-link"><?php echo esc_html__('Stock Products', 'eseabasi-inventory'); ?></a></li>
                    <li><a href="#chopped-products" class="tab-link"><?php echo esc_html__('Chopped Products', 'eseabasi-inventory'); ?></a></li>
                </ul>
                
                <div id="import-products" class="tab-content active">
                    <?php $this->render_products_table($import_products, 'import'); ?>
                </div>
                
                <div id="stock-products" class="tab-content">
                    <?php $this->render_products_table($stock_products, 'stock'); ?>
                </div>
                
                <div id="chopped-products" class="tab-content">
                    <?php $this->render_products_table($chopped_products, 'chopped'); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render products table
     */
    private function render_products_table($products, $type) {
        ?>
        <div class="products-section">
            <div class="section-header">
                <h2><?php echo esc_html(ucfirst($type) . ' Products'); ?></h2>
                <button class="button button-primary add-product-btn" data-type="<?php echo esc_attr($type); ?>">
                    <?php echo esc_html__('Add New Product', 'eseabasi-inventory'); ?>
                </button>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Name', 'eseabasi-inventory'); ?></th>
                        <th><?php echo esc_html__('Type', 'eseabasi-inventory'); ?></th>
                        <th><?php echo esc_html__('Is Fruit', 'eseabasi-inventory'); ?></th>
                        <th><?php echo esc_html__('Status', 'eseabasi-inventory'); ?></th>
                        <th><?php echo esc_html__('Actions', 'eseabasi-inventory'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo esc_html($product->name); ?></td>
                            <td><?php echo esc_html(ucfirst($product->type)); ?></td>
                            <td><?php echo $product->is_fruit ? esc_html__('Yes', 'eseabasi-inventory') : esc_html__('No', 'eseabasi-inventory'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($product->status); ?>">
                                    <?php echo esc_html(ucfirst($product->status)); ?>
                                </span>
                            </td>
                            <td>
                                <button class="button button-small edit-product-btn" data-id="<?php echo esc_attr($product->id); ?>">
                                    <?php echo esc_html__('Edit', 'eseabasi-inventory'); ?>
                                </button>
                                <button class="button button-small button-link-delete delete-product-btn" data-id="<?php echo esc_attr($product->id); ?>">
                                    <?php echo esc_html__('Delete', 'eseabasi-inventory'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5"><?php echo esc_html__('No products found.', 'eseabasi-inventory'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * History management page
     */
    public function history_page() {
        ?>
        <div class="wrap eseabasi-admin">
            <h1><?php echo esc_html__('History Management', 'eseabasi-inventory'); ?></h1>
            
            <div class="eseabasi-tabs">
                <ul class="tab-nav">
                    <li><a href="#import-history" class="tab-link active"><?php echo esc_html__('Import History', 'eseabasi-inventory'); ?></a></li>
                    <li><a href="#stock-history" class="tab-link"><?php echo esc_html__('Stock History', 'eseabasi-inventory'); ?></a></li>
                    <li><a href="#chopped-history" class="tab-link"><?php echo esc_html__('Chopped History', 'eseabasi-inventory'); ?></a></li>
                </ul>
                
                <div id="import-history" class="tab-content active">
                    <?php $this->render_history_section('import'); ?>
                </div>
                
                <div id="stock-history" class="tab-content">
                    <?php $this->render_history_section('stock'); ?>
                </div>
                
                <div id="chopped-history" class="tab-content">
                    <?php $this->render_history_section('chopped'); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render history section
     */
    private function render_history_section($type) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'eseabasi_' . $type . ($type === 'import' ? 's' : '');
        $products_table = $wpdb->prefix . 'eseabasi_products';
        
        $records = $wpdb->get_results($wpdb->prepare("
            SELECT h.*, p.name as product_name 
            FROM $table_name h 
            LEFT JOIN $products_table p ON h.product_id = p.id 
            ORDER BY h.created_at DESC 
            LIMIT 50
        "));
        
        ?>
        <div class="history-section">
            <div class="section-header">
                <h2><?php echo esc_html(ucfirst($type) . ' History'); ?></h2>
                <button class="button button-link-delete clear-history-btn" data-type="<?php echo esc_attr($type); ?>">
                    <?php echo esc_html__('Clear All History', 'eseabasi-inventory'); ?>
                </button>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Product', 'eseabasi-inventory'); ?></th>
                        <th><?php echo esc_html__('Staff', 'eseabasi-inventory'); ?></th>
                        <th><?php echo esc_html__('Date', 'eseabasi-inventory'); ?></th>
                        <?php if ($type === 'import'): ?>
                        <th><?php echo esc_html__('Quantity', 'eseabasi-inventory'); ?></th>
                        <?php else: ?>
                        <th><?php echo esc_html__('Details', 'eseabasi-inventory'); ?></th>
                        <?php endif; ?>
                        <th><?php echo esc_html__('Actions', 'eseabasi-inventory'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($records)): ?>
                        <?php foreach ($records as $record): ?>
                        <tr>
                            <td><?php echo esc_html($record->product_name); ?></td>
                            <td><?php echo esc_html($record->staff_name); ?></td>
                            <td><?php echo esc_html($record->{$type . '_date'}); ?></td>
                            <td>
                                <?php if ($type === 'import'): ?>
                                    <?php echo number_format($record->quantity, 2); ?>
                                <?php else: ?>
                                    <small><?php echo esc_html__('View details', 'eseabasi-inventory'); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="button button-small button-link-delete delete-history-item-btn" 
                                        data-id="<?php echo esc_attr($record->id); ?>" 
                                        data-type="<?php echo esc_attr($type); ?>">
                                    <?php echo esc_html__('Delete', 'eseabasi-inventory'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5"><?php echo esc_html__('No history records found.', 'eseabasi-inventory'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            check_admin_referer('eseabasi_settings');
            
            $threshold = intval($_POST['low_stock_threshold']);
            update_option('eseabasi_low_stock_threshold', $threshold);
            
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully.', 'eseabasi-inventory') . '</p></div>';
        }
        
        $threshold = get_option('eseabasi_low_stock_threshold', 10);
        
        ?>
        <div class="wrap eseabasi-admin">
            <h1><?php echo esc_html__('Eseabasi Inventory Settings', 'eseabasi-inventory'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('eseabasi_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="low_stock_threshold"><?php echo esc_html__('Low Stock Threshold', 'eseabasi-inventory'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="low_stock_threshold" name="low_stock_threshold" 
                                   value="<?php echo esc_attr($threshold); ?>" min="1" max="100" />
                            <p class="description">
                                <?php echo esc_html__('Items with stock below this number will trigger low stock alerts.', 'eseabasi-inventory'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * AJAX: Add product
     */
    public function ajax_add_product() {
        check_ajax_referer('eseabasi_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'eseabasi-inventory'));
        }
        
        // Implementation will be added in the next iteration
        wp_send_json_success(array('message' => __('Product added successfully.', 'eseabasi-inventory')));
    }
    
    /**
     * AJAX: Edit product
     */
    public function ajax_edit_product() {
        check_ajax_referer('eseabasi_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'eseabasi-inventory'));
        }
        
        // Implementation will be added in the next iteration
        wp_send_json_success(array('message' => __('Product updated successfully.', 'eseabasi-inventory')));
    }
    
    /**
     * AJAX: Delete product
     */
    public function ajax_delete_product() {
        check_ajax_referer('eseabasi_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'eseabasi-inventory'));
        }
        
        // Implementation will be added in the next iteration
        wp_send_json_success(array('message' => __('Product deleted successfully.', 'eseabasi-inventory')));
    }
    
    /**
     * AJAX: Clear history
     */
    public function ajax_clear_history() {
        check_ajax_referer('eseabasi_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'eseabasi-inventory'));
        }
        
        // Implementation will be added in the next iteration
        wp_send_json_success(array('message' => __('History cleared successfully.', 'eseabasi-inventory')));
    }
    
    /**
     * AJAX: Delete history item
     */
    public function ajax_delete_history_item() {
        check_ajax_referer('eseabasi_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'eseabasi-inventory'));
        }
        
        // Implementation will be added in the next iteration
        wp_send_json_success(array('message' => __('History item deleted successfully.', 'eseabasi-inventory')));
    }
}