<?php
/**
 * Frontend functionality class
 *
 * @package EseabasiInventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class Eseabasi_Frontend {
    
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Eseabasi_Database();
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('init', array($this, 'init_shortcodes'));
        add_action('wp_ajax_eseabasi_submit_import', array($this, 'ajax_submit_import'));
        add_action('wp_ajax_nopriv_eseabasi_submit_import', array($this, 'ajax_submit_import'));
        add_action('wp_ajax_eseabasi_submit_stock', array($this, 'ajax_submit_stock'));
        add_action('wp_ajax_nopriv_eseabasi_submit_stock', array($this, 'ajax_submit_stock'));
        add_action('wp_ajax_eseabasi_submit_chopped', array($this, 'ajax_submit_chopped'));
        add_action('wp_ajax_nopriv_eseabasi_submit_chopped', array($this, 'ajax_submit_chopped'));
        add_action('wp_ajax_eseabasi_get_current_values', array($this, 'ajax_get_current_values'));
        add_action('wp_ajax_nopriv_eseabasi_get_current_values', array($this, 'ajax_get_current_values'));
        add_action('wp_ajax_eseabasi_export_history', array($this, 'ajax_export_history'));
        add_action('wp_ajax_nopriv_eseabasi_export_history', array($this, 'ajax_export_history'));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_style('eseabasi-frontend-style', ESEABASI_PLUGIN_URL . 'public/css/frontend-style.css', array(), ESEABASI_VERSION);
        wp_enqueue_script('eseabasi-frontend-script', ESEABASI_PLUGIN_URL . 'public/js/frontend-script.js', array('jquery'), ESEABASI_VERSION, true);
        
        wp_localize_script('eseabasi-frontend-script', 'eseabasi_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eseabasi_frontend_nonce'),
            'current_user' => $this->get_current_user_info(),
            'strings' => array(
                'loading' => __('Loading...', 'eseabasi-inventory'),
                'error' => __('An error occurred. Please try again.', 'eseabasi-inventory'),
                'success' => __('Operation completed successfully.', 'eseabasi-inventory'),
                'confirm_submit' => __('Are you sure you want to submit this form?', 'eseabasi-inventory'),
                'invalid_number' => __('Please enter a valid positive number.', 'eseabasi-inventory'),
                'required_field' => __('This field is required.', 'eseabasi-inventory')
            )
        ));
    }
    
    /**
     * Initialize shortcodes
     */
    public function init_shortcodes() {
        add_shortcode('eseabasi_import_form', array($this, 'import_form_shortcode'));
        add_shortcode('eseabasi_import_history', array($this, 'import_history_shortcode'));
        add_shortcode('eseabasi_stock_form', array($this, 'stock_form_shortcode'));
        add_shortcode('eseabasi_stock_history', array($this, 'stock_history_shortcode'));
        add_shortcode('eseabasi_chopped_form', array($this, 'chopped_form_shortcode'));
        add_shortcode('eseabasi_chopped_history', array($this, 'chopped_history_shortcode'));
        add_shortcode('eseabasi_analytics', array($this, 'analytics_shortcode'));
    }
    
    /**
     * Get current user info
     */
    private function get_current_user_info() {
        $current_user = wp_get_current_user();
        return array(
            'id' => $current_user->ID,
            'name' => $current_user->display_name ?: $current_user->user_login,
            'is_admin' => current_user_can('manage_options')
        );
    }
    
    /**
     * Import form shortcode
     */
    public function import_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Import Form', 'eseabasi-inventory')
        ), $atts);
        
        $products = $this->db->get_products_by_type('import');
        $current_user = $this->get_current_user_info();
        
        ob_start();
        ?>
        <div class="eseabasi-form-container" id="eseabasi-import-form">
            <div class="eseabasi-form-header">
                <h2><?php echo esc_html($atts['title']); ?></h2>
                <div class="form-meta-info">
                    <div class="meta-item">
                        <strong><?php echo esc_html__('Staff:', 'eseabasi-inventory'); ?></strong>
                        <span id="staff-name"><?php echo esc_html($current_user['name']); ?></span>
                    </div>
                    <div class="meta-item">
                        <strong><?php echo esc_html__('Date:', 'eseabasi-inventory'); ?></strong>
                        <span id="current-date"><?php echo esc_html($this->db->get_lagos_date()); ?></span>
                    </div>
                    <div class="meta-item">
                        <strong><?php echo esc_html__('Time:', 'eseabasi-inventory'); ?></strong>
                        <span id="current-time"><?php echo esc_html($this->db->get_lagos_time()); ?></span>
                    </div>
                </div>
            </div>
            
            <form id="import-form" class="eseabasi-form">
                <?php wp_nonce_field('eseabasi_import_form', 'eseabasi_import_nonce'); ?>
                
                <div class="form-table-wrapper">
                    <table class="eseabasi-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Product Name', 'eseabasi-inventory'); ?></th>
                                <th><?php echo esc_html__('Quantity', 'eseabasi-inventory'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td class="product-name">
                                    <?php echo esc_html($product->name); ?>
                                    <input type="hidden" name="products[<?php echo esc_attr($product->id); ?>][id]" value="<?php echo esc_attr($product->id); ?>">
                                    <input type="hidden" name="products[<?php echo esc_attr($product->id); ?>][name]" value="<?php echo esc_attr($product->name); ?>">
                                </td>
                                <td>
                                    <input type="number" 
                                           name="products[<?php echo esc_attr($product->id); ?>][quantity]" 
                                           step="0.01" 
                                           min="0" 
                                           placeholder="0.00"
                                           class="quantity-input">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="submit-import">
                        <?php echo esc_html__('Submit Import', 'eseabasi-inventory'); ?>
                    </button>
                    <button type="button" class="btn btn-secondary" id="reset-form">
                        <?php echo esc_html__('Reset Form', 'eseabasi-inventory'); ?>
                    </button>
                </div>
            </form>
            
            <div class="form-messages" id="import-messages"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Import history shortcode
     */
    public function import_history_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Import History', 'eseabasi-inventory'),
            'per_page' => 20
        ), $atts);
        
        ob_start();
        ?>
        <div class="eseabasi-history-container" id="eseabasi-import-history">
            <div class="eseabasi-history-header">
                <h2><?php echo esc_html($atts['title']); ?></h2>
                <div class="history-actions">
                    <button class="btn btn-primary export-btn" data-type="import">
                        <?php echo esc_html__('Export CSV', 'eseabasi-inventory'); ?>
                    </button>
                </div>
            </div>
            
            <div class="history-filters">
                <div class="filter-group">
                    <label for="date-from"><?php echo esc_html__('From:', 'eseabasi-inventory'); ?></label>
                    <input type="date" id="date-from" class="filter-input">
                </div>
                <div class="filter-group">
                    <label for="date-to"><?php echo esc_html__('To:', 'eseabasi-inventory'); ?></label>
                    <input type="date" id="date-to" class="filter-input">
                </div>
                <div class="filter-group">
                    <label for="product-filter"><?php echo esc_html__('Product:', 'eseabasi-inventory'); ?></label>
                    <select id="product-filter" class="filter-input">
                        <option value=""><?php echo esc_html__('All Products', 'eseabasi-inventory'); ?></option>
                        <?php 
                        $products = $this->db->get_products_by_type('import');
                        foreach ($products as $product): 
                        ?>
                        <option value="<?php echo esc_attr($product->id); ?>"><?php echo esc_html($product->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <button class="btn btn-secondary filter-btn"><?php echo esc_html__('Filter', 'eseabasi-inventory'); ?></button>
                    <button class="btn btn-link reset-filter-btn"><?php echo esc_html__('Reset', 'eseabasi-inventory'); ?></button>
                </div>
            </div>
            
            <div class="history-content" id="import-history-content">
                <?php echo $this->render_import_history(); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Stock form shortcode
     */
    public function stock_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Stock Form', 'eseabasi-inventory')
        ), $atts);
        
        $products = $this->db->get_products_by_type('stock');
        $current_user = $this->get_current_user_info();
        $current_date = $this->db->get_lagos_date();
        
        // Get current values for the day
        $current_values = $this->get_current_stock_values($current_date);
        
        ob_start();
        ?>
        <div class="eseabasi-form-container" id="eseabasi-stock-form">
            <div class="eseabasi-form-header">
                <h2><?php echo esc_html($atts['title']); ?></h2>
                <div class="form-meta-info">
                    <div class="meta-item">
                        <strong><?php echo esc_html__('Staff:', 'eseabasi-inventory'); ?></strong>
                        <span id="staff-name"><?php echo esc_html($current_user['name']); ?></span>
                    </div>
                    <div class="meta-item">
                        <strong><?php echo esc_html__('Date:', 'eseabasi-inventory'); ?></strong>
                        <span id="current-date"><?php echo esc_html($current_date); ?></span>
                    </div>
                    <div class="meta-item">
                        <strong><?php echo esc_html__('Time:', 'eseabasi-inventory'); ?></strong>
                        <span id="current-time"><?php echo esc_html($this->db->get_lagos_time()); ?></span>
                    </div>
                </div>
            </div>
            
            <form id="stock-form" class="eseabasi-form">
                <?php wp_nonce_field('eseabasi_stock_form', 'eseabasi_stock_nonce'); ?>
                
                <div class="form-table-wrapper">
                    <table class="eseabasi-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Products', 'eseabasi-inventory'); ?></th>
                                <th><?php echo esc_html__('Opening Packs', 'eseabasi-inventory'); ?></th>
                                <th><?php echo esc_html__('Added Packs (from chopped/imports)', 'eseabasi-inventory'); ?></th>
                                <th><?php echo esc_html__('Used Packs', 'eseabasi-inventory'); ?></th>
                                <th><?php echo esc_html__('Closing Packs', 'eseabasi-inventory'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): 
                                $values = isset($current_values[$product->id]) ? $current_values[$product->id] : null;
                                $opening = $values ? $values->opening_value : 0;
                                $added = $values ? $values->added_value : 0;
                                $used = $values ? $values->used_value : 0;
                                $closing = $opening + $added - $used;
                            ?>
                            <tr data-product-id="<?php echo esc_attr($product->id); ?>">
                                <td class="product-name">
                                    <?php echo esc_html($product->name); ?>
                                    <input type="hidden" name="products[<?php echo esc_attr($product->id); ?>][id]" value="<?php echo esc_attr($product->id); ?>">
                                    <input type="hidden" name="products[<?php echo esc_attr($product->id); ?>][name]" value="<?php echo esc_attr($product->name); ?>">
                                </td>
                                <td>
                                    <input type="number" 
                                           name="products[<?php echo esc_attr($product->id); ?>][opening_packs]" 
                                           step="0.01" 
                                           min="0"
                                           value="<?php echo esc_attr($opening); ?>"
                                           class="opening-input <?php echo !$current_user['is_admin'] ? 'readonly' : ''; ?>"
                                           <?php echo !$current_user['is_admin'] ? 'readonly' : ''; ?>>
                                </td>
                                <td>
                                    <input type="number" 
                                           name="products[<?php echo esc_attr($product->id); ?>][added_packs]" 
                                           step="0.01" 
                                           min="0"
                                           value="<?php echo esc_attr($added); ?>"
                                           class="added-input readonly"
                                           readonly>
                                </td>
                                <td>
                                    <input type="number" 
                                           name="products[<?php echo esc_attr($product->id); ?>][used_packs]" 
                                           step="0.01" 
                                           min="0"
                                           value="<?php echo esc_attr($used); ?>"
                                           class="used-input">
                                </td>
                                <td>
                                    <input type="number" 
                                           name="products[<?php echo esc_attr($product->id); ?>][closing_packs]" 
                                           step="0.01" 
                                           min="0"
                                           value="<?php echo esc_attr($closing); ?>"
                                           class="closing-input readonly"
                                           readonly>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="form-remarks">
                    <label for="stock-remarks"><?php echo esc_html__('Remarks:', 'eseabasi-inventory'); ?></label>
                    <textarea id="stock-remarks" name="remarks" rows="3" placeholder="<?php echo esc_attr__('Enter any remarks here...', 'eseabasi-inventory'); ?>"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="submit-stock">
                        <?php echo esc_html__('Submit Stock', 'eseabasi-inventory'); ?>
                    </button>
                    <button type="button" class="btn btn-secondary" id="reset-form">
                        <?php echo esc_html__('Reset Form', 'eseabasi-inventory'); ?>
                    </button>
                </div>
            </form>
            
            <div class="form-messages" id="stock-messages"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Stock history shortcode
     */
    public function stock_history_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Stock Record History', 'eseabasi-inventory'),
            'per_page' => 20
        ), $atts);
        
        ob_start();
        ?>
        <div class="eseabasi-history-container" id="eseabasi-stock-history">
            <div class="eseabasi-history-header">
                <h2><?php echo esc_html($atts['title']); ?></h2>
                <div class="history-actions">
                    <button class="btn btn-primary export-btn" data-type="stock">
                        <?php echo esc_html__('Export CSV', 'eseabasi-inventory'); ?>
                    </button>
                </div>
            </div>
            
            <div class="history-filters">
                <div class="filter-group">
                    <label for="date-from"><?php echo esc_html__('From:', 'eseabasi-inventory'); ?></label>
                    <input type="date" id="date-from" class="filter-input">
                </div>
                <div class="filter-group">
                    <label for="date-to"><?php echo esc_html__('To:', 'eseabasi-inventory'); ?></label>
                    <input type="date" id="date-to" class="filter-input">
                </div>
                <div class="filter-group">
                    <label for="product-filter"><?php echo esc_html__('Product:', 'eseabasi-inventory'); ?></label>
                    <select id="product-filter" class="filter-input">
                        <option value=""><?php echo esc_html__('All Products', 'eseabasi-inventory'); ?></option>
                        <?php 
                        $products = $this->db->get_products_by_type('stock');
                        foreach ($products as $product): 
                        ?>
                        <option value="<?php echo esc_attr($product->id); ?>"><?php echo esc_html($product->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <button class="btn btn-secondary filter-btn"><?php echo esc_html__('Filter', 'eseabasi-inventory'); ?></button>
                    <button class="btn btn-link reset-filter-btn"><?php echo esc_html__('Reset', 'eseabasi-inventory'); ?></button>
                </div>
            </div>
            
            <div class="history-content" id="stock-history-content">
                <?php echo $this->render_stock_history(); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Chopped form shortcode
     */
    public function chopped_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Chopped Form', 'eseabasi-inventory')
        ), $atts);
        
        $products = $this->db->get_products_by_type('chopped');
        $current_user = $this->get_current_user_info();
        $current_date = $this->db->get_lagos_date();
        
        // Get current values for the day
        $current_values = $this->get_current_chopped_values($current_date);
        
        ob_start();
        ?>
        <div class="eseabasi-form-container" id="eseabasi-chopped-form">
            <div class="eseabasi-form-header">
                <h2><?php echo esc_html($atts['title']); ?></h2>
                <div class="form-meta-info">
                    <div class="meta-item">
                        <strong><?php echo esc_html__('Staff:', 'eseabasi-inventory'); ?></strong>
                        <span id="staff-name"><?php echo esc_html($current_user['name']); ?></span>
                    </div>
                    <div class="meta-item">
                        <strong><?php echo esc_html__('Date:', 'eseabasi-inventory'); ?></strong>
                        <span id="current-date"><?php echo esc_html($current_date); ?></span>
                    </div>
                    <div class="meta-item">
                        <strong><?php echo esc_html__('Time:', 'eseabasi-inventory'); ?></strong>
                        <span id="current-time"><?php echo esc_html($this->db->get_lagos_time()); ?></span>
                    </div>
                </div>
            </div>
            
            <form id="chopped-form" class="eseabasi-form">
                <?php wp_nonce_field('eseabasi_chopped_form', 'eseabasi_chopped_nonce'); ?>
                
                <div class="form-table-wrapper">
                    <table class="eseabasi-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Fruit', 'eseabasi-inventory'); ?></th>
                                <th><?php echo esc_html__('Opening (Whole)', 'eseabasi-inventory'); ?></th>
                                <th><?php echo esc_html__('Import (Whole)', 'eseabasi-inventory'); ?></th>
                                <th><?php echo esc_html__('Prepared (Whole)', 'eseabasi-inventory'); ?></th>
                                <th><?php echo esc_html__('Closing (Whole)', 'eseabasi-inventory'); ?></th>
                                <th><?php echo esc_html__('Pack(s) Gotten', 'eseabasi-inventory'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): 
                                $values = isset($current_values[$product->id]) ? $current_values[$product->id] : null;
                                $opening = $values ? $values->opening_value : 0;
                                $import = $values ? $values->import_value : 0;
                                $prepared = $values ? $values->prepared_value : 0;
                                $packs_gotten = $values ? $values->packs_gotten_value : 0;
                                $closing = $opening + $import - $prepared;
                            ?>
                            <tr data-product-id="<?php echo esc_attr($product->id); ?>">
                                <td class="product-name">
                                    <?php echo esc_html($product->name); ?>
                                    <input type="hidden" name="products[<?php echo esc_attr($product->id); ?>][id]" value="<?php echo esc_attr($product->id); ?>">
                                    <input type="hidden" name="products[<?php echo esc_attr($product->id); ?>][name]" value="<?php echo esc_attr($product->name); ?>">
                                </td>
                                <td>
                                    <input type="number" 
                                           name="products[<?php echo esc_attr($product->id); ?>][opening_whole]" 
                                           step="0.01" 
                                           min="0"
                                           value="<?php echo esc_attr($opening); ?>"
                                           class="opening-input <?php echo !$current_user['is_admin'] ? 'readonly' : ''; ?>"
                                           <?php echo !$current_user['is_admin'] ? 'readonly' : ''; ?>>
                                </td>
                                <td>
                                    <input type="number" 
                                           name="products[<?php echo esc_attr($product->id); ?>][import_whole]" 
                                           step="0.01" 
                                           min="0"
                                           value="<?php echo esc_attr($import); ?>"
                                           class="import-input readonly"
                                           readonly>
                                </td>
                                <td>
                                    <input type="number" 
                                           name="products[<?php echo esc_attr($product->id); ?>][prepared_whole]" 
                                           step="0.01" 
                                           min="0"
                                           value="<?php echo esc_attr($prepared); ?>"
                                           class="prepared-input">
                                </td>
                                <td>
                                    <input type="number" 
                                           name="products[<?php echo esc_attr($product->id); ?>][closing_whole]" 
                                           step="0.01" 
                                           min="0"
                                           value="<?php echo esc_attr($closing); ?>"
                                           class="closing-input readonly"
                                           readonly>
                                </td>
                                <td>
                                    <input type="number" 
                                           name="products[<?php echo esc_attr($product->id); ?>][packs_gotten]" 
                                           step="0.01" 
                                           min="0"
                                           value="<?php echo esc_attr($packs_gotten); ?>"
                                           class="packs-input">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="form-remarks">
                    <label for="chopped-remarks"><?php echo esc_html__('Remarks:', 'eseabasi-inventory'); ?></label>
                    <textarea id="chopped-remarks" name="remarks" rows="3" placeholder="<?php echo esc_attr__('Enter any remarks here...', 'eseabasi-inventory'); ?>"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="submit-chopped">
                        <?php echo esc_html__('Submit Chopped', 'eseabasi-inventory'); ?>
                    </button>
                    <button type="button" class="btn btn-secondary" id="reset-form">
                        <?php echo esc_html__('Reset Form', 'eseabasi-inventory'); ?>
                    </button>
                </div>
            </form>
            
            <div class="form-messages" id="chopped-messages"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Chopped history shortcode
     */
    public function chopped_history_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Chopped Record History', 'eseabasi-inventory'),
            'per_page' => 20
        ), $atts);
        
        ob_start();
        ?>
        <div class="eseabasi-history-container" id="eseabasi-chopped-history">
            <div class="eseabasi-history-header">
                <h2><?php echo esc_html($atts['title']); ?></h2>
                <div class="history-actions">
                    <button class="btn btn-primary export-btn" data-type="chopped">
                        <?php echo esc_html__('Export CSV', 'eseabasi-inventory'); ?>
                    </button>
                </div>
            </div>
            
            <div class="history-filters">
                <div class="filter-group">
                    <label for="date-from"><?php echo esc_html__('From:', 'eseabasi-inventory'); ?></label>
                    <input type="date" id="date-from" class="filter-input">
                </div>
                <div class="filter-group">
                    <label for="date-to"><?php echo esc_html__('To:', 'eseabasi-inventory'); ?></label>
                    <input type="date" id="date-to" class="filter-input">
                </div>
                <div class="filter-group">
                    <label for="product-filter"><?php echo esc_html__('Product:', 'eseabasi-inventory'); ?></label>
                    <select id="product-filter" class="filter-input">
                        <option value=""><?php echo esc_html__('All Products', 'eseabasi-inventory'); ?></option>
                        <?php 
                        $products = $this->db->get_products_by_type('chopped');
                        foreach ($products as $product): 
                        ?>
                        <option value="<?php echo esc_attr($product->id); ?>"><?php echo esc_html($product->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <button class="btn btn-secondary filter-btn"><?php echo esc_html__('Filter', 'eseabasi-inventory'); ?></button>
                    <button class="btn btn-link reset-filter-btn"><?php echo esc_html__('Reset', 'eseabasi-inventory'); ?></button>
                </div>
            </div>
            
            <div class="history-content" id="chopped-history-content">
                <?php echo $this->render_chopped_history(); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Analytics shortcode
     */
    public function analytics_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Analytics Dashboard', 'eseabasi-inventory')
        ), $atts);
        
        $analytics = new Eseabasi_Analytics();
        
        ob_start();
        ?>
        <div class="eseabasi-analytics-container">
            <div class="eseabasi-analytics-header">
                <h2><?php echo esc_html($atts['title']); ?></h2>
            </div>
            
            <?php echo $analytics->render_analytics_dashboard(); ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get current stock values for a specific date
     */
    private function get_current_stock_values($date) {
        global $wpdb;
        
        $current_values_table = $wpdb->prefix . 'eseabasi_current_values';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $current_values_table 
            WHERE form_type = 'stock' AND current_date = %s
        ", $date));
        
        $values = array();
        foreach ($results as $result) {
            $values[$result->product_id] = $result;
        }
        
        return $values;
    }
    
    /**
     * Get current chopped values for a specific date
     */
    private function get_current_chopped_values($date) {
        global $wpdb;
        
        $current_values_table = $wpdb->prefix . 'eseabasi_current_values';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $current_values_table 
            WHERE form_type = 'chopped' AND current_date = %s
        ", $date));
        
        $values = array();
        foreach ($results as $result) {
            $values[$result->product_id] = $result;
        }
        
        return $values;
    }
    
    /**
     * Render import history
     */
    private function render_import_history($filters = array()) {
        global $wpdb;
        
        $imports_table = $wpdb->prefix . 'eseabasi_imports';
        $products_table = $wpdb->prefix . 'eseabasi_products';
        
        $where_clauses = array('1=1');
        $params = array();
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = 'i.import_date >= %s';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = 'i.import_date <= %s';
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['product_id'])) {
            $where_clauses[] = 'i.product_id = %d';
            $params[] = intval($filters['product_id']);
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $query = "
            SELECT i.*, p.name as product_name 
            FROM $imports_table i 
            LEFT JOIN $products_table p ON i.product_id = p.id 
            WHERE $where_sql 
            ORDER BY i.created_at DESC 
            LIMIT 50
        ";
        
        if (!empty($params)) {
            $records = $wpdb->get_results($wpdb->prepare($query, $params));
        } else {
            $records = $wpdb->get_results($query);
        }
        
        ob_start();
        ?>
        <table class="eseabasi-table history-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Product', 'eseabasi-inventory'); ?></th>
                    <th><?php echo esc_html__('Quantity', 'eseabasi-inventory'); ?></th>
                    <th><?php echo esc_html__('Staff', 'eseabasi-inventory'); ?></th>
                    <th><?php echo esc_html__('Date', 'eseabasi-inventory'); ?></th>
                    <th><?php echo esc_html__('Time', 'eseabasi-inventory'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($records)): ?>
                    <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?php echo esc_html($record->product_name); ?></td>
                        <td><?php echo number_format($record->quantity, 2); ?></td>
                        <td><?php echo esc_html($record->staff_name); ?></td>
                        <td><?php echo esc_html($record->import_date); ?></td>
                        <td><?php echo esc_html($record->import_time); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="no-records"><?php echo esc_html__('No import records found.', 'eseabasi-inventory'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render stock history
     */
    private function render_stock_history($filters = array()) {
        global $wpdb;
        
        $stock_table = $wpdb->prefix . 'eseabasi_stock';
        $products_table = $wpdb->prefix . 'eseabasi_products';
        
        $where_clauses = array('1=1');
        $params = array();
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = 's.stock_date >= %s';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = 's.stock_date <= %s';
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['product_id'])) {
            $where_clauses[] = 's.product_id = %d';
            $params[] = intval($filters['product_id']);
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $query = "
            SELECT s.*, p.name as product_name 
            FROM $stock_table s 
            LEFT JOIN $products_table p ON s.product_id = p.id 
            WHERE $where_sql 
            ORDER BY s.created_at DESC 
            LIMIT 50
        ";
        
        if (!empty($params)) {
            $records = $wpdb->get_results($wpdb->prepare($query, $params));
        } else {
            $records = $wpdb->get_results($query);
        }
        
        ob_start();
        ?>
        <table class="eseabasi-table history-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Product', 'eseabasi-inventory'); ?></th>
                    <th><?php echo esc_html__('Opening', 'eseabasi-inventory'); ?></th>
                    <th><?php echo esc_html__('Added', 'eseabasi-inventory'); ?></th>
                    <th><?php echo esc_html__('Used', 'eseabasi-inventory'); ?></th>
                    <th><?php echo esc_html__('Closing', 'eseabasi-inventory'); ?></th>
                    <th><?php echo esc_html__('Staff', 'eseabasi-inventory'); ?></th>
                    <th><?php echo esc_html__('Date', 'eseabasi-inventory'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($records)): ?>
                    <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?php echo esc_html($record->product_name); ?></td>
                        <td><?php echo number_format($record->opening_packs, 2); ?></td>
                        <td><?php echo number_format($record->added_packs, 2); ?></td>
                        <td><?php echo number_format($record->used_packs, 2); ?></td>
                        <td><?php echo number_format($record->closing_packs, 2); ?></td>
                        <td><?php echo esc_html($record->staff_name); ?></td>
                        <td><?php echo esc_html($record->stock_date); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="no-records"><?php echo esc_html__('No stock records found.', 'eseabasi-inventory'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render chopped history
     */
    private function render_chopped_history($filters = array()) {
        global $wpdb;
        
        $chopped_table = $wpdb->prefix . 'eseabasi_chopped';
        $products_table = $wpdb->prefix . 'eseabasi_products';
        
        $where_clauses = array('1=1');
        $params = array();
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = 'c.chopped_date >= %s';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = 'c.chopped_date <= %s';
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['product_id'])) {
            $where_clauses[] = 'c.product_id = %d';
            $params[] = intval($filters['product_id']);
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $query = "
            SELECT c.*, p.name as product_name 
            FROM $chopped_table c 
            LEFT JOIN $products_table p ON c.product_id = p.id 
            WHERE $where_sql 
            ORDER BY c.created_at DESC 
            LIMIT 50
        ";
        
        if (!empty($params)) {
            $records = $wpdb->get_results($wpdb->prepare($query, $params));
        } else {
            $records = $wpdb->get_results($query);
        }
        
        ob_start();
        ?>
        <table class="eseabasi-table history-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Fruit', 'eseabasi-inventory'); ?></th>
                    <th><?php echo esc_html__('Opening', 'eseabasi-inventory'); ?></th>
                    <th><?php echo esc_html__('Import', 'eseabasi-inventory'); ?></th>
                    <th><?php echo esc_html__('Prepared', 'eseabasi-inventory'); ?></th>
                    <th><?php echo esc_html__('Closing', 'eseabasi-inventory'); ?></th>
                    <th><?php echo esc_html__('Packs Gotten', 'eseabasi-inventory'); ?></th>
                    <th><?php echo esc_html__('Staff', 'eseabasi-inventory'); ?></th>
                    <th><?php echo esc_html__('Date', 'eseabasi-inventory'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($records)): ?>
                    <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?php echo esc_html($record->product_name); ?></td>
                        <td><?php echo number_format($record->opening_whole, 2); ?></td>
                        <td><?php echo number_format($record->import_whole, 2); ?></td>
                        <td><?php echo number_format($record->prepared_whole, 2); ?></td>
                        <td><?php echo number_format($record->closing_whole, 2); ?></td>
                        <td><?php echo number_format($record->packs_gotten, 2); ?></td>
                        <td><?php echo esc_html($record->staff_name); ?></td>
                        <td><?php echo esc_html($record->chopped_date); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="no-records"><?php echo esc_html__('No chopped records found.', 'eseabasi-inventory'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Submit import form
     */
    public function ajax_submit_import() {
        check_ajax_referer('eseabasi_frontend_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to submit data.', 'eseabasi-inventory')));
        }
        
        // Implementation will be completed in integration class
        $integration = new Eseabasi_Integration();
        $result = $integration->process_import_submission($_POST);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Submit stock form
     */
    public function ajax_submit_stock() {
        check_ajax_referer('eseabasi_frontend_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to submit data.', 'eseabasi-inventory')));
        }
        
        // Implementation will be completed in integration class
        $integration = new Eseabasi_Integration();
        $result = $integration->process_stock_submission($_POST);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Submit chopped form
     */
    public function ajax_submit_chopped() {
        check_ajax_referer('eseabasi_frontend_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to submit data.', 'eseabasi-inventory')));
        }
        
        // Implementation will be completed in integration class
        $integration = new Eseabasi_Integration();
        $result = $integration->process_chopped_submission($_POST);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Get current values
     */
    public function ajax_get_current_values() {
        check_ajax_referer('eseabasi_frontend_nonce', 'nonce');
        
        $form_type = sanitize_text_field($_POST['form_type']);
        $date = sanitize_text_field($_POST['date']);
        
        if ($form_type === 'stock') {
            $values = $this->get_current_stock_values($date);
        } elseif ($form_type === 'chopped') {
            $values = $this->get_current_chopped_values($date);
        } else {
            wp_send_json_error(array('message' => __('Invalid form type.', 'eseabasi-inventory')));
        }
        
        wp_send_json_success(array('values' => $values));
    }
    
    /**
     * AJAX: Export history
     */
    public function ajax_export_history() {
        check_ajax_referer('eseabasi_frontend_nonce', 'nonce');
        
        $type = sanitize_text_field($_POST['type']);
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        
        // Implementation will be added for CSV/PDF export
        wp_send_json_success(array('message' => __('Export functionality will be implemented.', 'eseabasi-inventory')));
    }
}