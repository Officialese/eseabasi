<?php
/**
 * Integration logic class
 *
 * @package EseabasiInventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class Eseabasi_Integration {
    
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Eseabasi_Database();
        
        // Hook into daily reset
        add_action('wp', array($this, 'schedule_daily_reset'));
        add_action('eseabasi_daily_reset', array($this, 'perform_daily_reset'));
    }
    
    /**
     * Schedule daily reset event
     */
    public function schedule_daily_reset() {
        if (!wp_next_scheduled('eseabasi_daily_reset')) {
            wp_schedule_event(strtotime('tomorrow 00:01:00'), 'daily', 'eseabasi_daily_reset');
        }
    }
    
    /**
     * Perform daily reset
     */
    public function perform_daily_reset() {
        global $wpdb;
        
        $current_values_table = $wpdb->prefix . 'eseabasi_current_values';
        $previous_date = date('Y-m-d', strtotime('-1 day'));
        $current_date = $this->db->get_lagos_date();
        
        // Get yesterday's closing values for stock
        $stock_closing_values = $wpdb->get_results($wpdb->prepare("
            SELECT product_id, closing_value 
            FROM $current_values_table 
            WHERE form_type = 'stock' AND current_date = %s
        ", $previous_date));
        
        // Get yesterday's closing values for chopped
        $chopped_closing_values = $wpdb->get_results($wpdb->prepare("
            SELECT product_id, closing_value 
            FROM $current_values_table 
            WHERE form_type = 'chopped' AND current_date = %s
        ", $previous_date));
        
        // Create today's opening values for stock (yesterday's closing becomes today's opening)
        foreach ($stock_closing_values as $value) {
            $wpdb->insert(
                $current_values_table,
                array(
                    'product_id' => $value->product_id,
                    'form_type' => 'stock',
                    'current_date' => $current_date,
                    'opening_value' => $value->closing_value,
                    'added_value' => 0,
                    'used_value' => 0,
                    'closing_value' => $value->closing_value
                ),
                array('%d', '%s', '%s', '%f', '%f', '%f', '%f')
            );
        }
        
        // Create today's opening values for chopped (yesterday's closing becomes today's opening)
        foreach ($chopped_closing_values as $value) {
            $wpdb->insert(
                $current_values_table,
                array(
                    'product_id' => $value->product_id,
                    'form_type' => 'chopped',
                    'current_date' => $current_date,
                    'opening_value' => $value->closing_value,
                    'import_value' => 0,
                    'prepared_value' => 0,
                    'closing_value' => $value->closing_value,
                    'packs_gotten_value' => 0
                ),
                array('%d', '%s', '%s', '%f', '%f', '%f', '%f', '%f')
            );
        }
    }
    
    /**
     * Process import form submission
     */
    public function process_import_submission($post_data) {
        global $wpdb;
        
        try {
            $current_user = wp_get_current_user();
            $staff_name = $current_user->display_name ?: $current_user->user_login;
            $import_date = $this->db->get_lagos_date();
            $import_time = $this->db->get_lagos_time();
            
            $imports_table = $wpdb->prefix . 'eseabasi_imports';
            $products_table = $wpdb->prefix . 'eseabasi_products';
            $integration_meta_table = $wpdb->prefix . 'eseabasi_integration_meta';
            
            $imported_items = array();
            $new_import_ids = array();
            
            // Validate and process each product
            if (isset($post_data['products']) && is_array($post_data['products'])) {
                foreach ($post_data['products'] as $product_data) {
                    $product_id = intval($product_data['id']);
                    $quantity = floatval($product_data['quantity']);
                    
                    if ($quantity > 0) {
                        // Insert import record
                        $result = $wpdb->insert(
                            $imports_table,
                            array(
                                'product_id' => $product_id,
                                'quantity' => $quantity,
                                'staff_name' => $staff_name,
                                'import_date' => $import_date,
                                'import_time' => $import_time
                            ),
                            array('%d', '%f', '%s', '%s', '%s')
                        );
                        
                        if ($result !== false) {
                            $import_id = $wpdb->insert_id;
                            $new_import_ids[] = $import_id;
                            $imported_items[] = array(
                                'id' => $import_id,
                                'product_id' => $product_id,
                                'product_name' => $product_data['name'],
                                'quantity' => $quantity
                            );
                        }
                    }
                }
            }
            
            if (empty($imported_items)) {
                return array(
                    'success' => false,
                    'message' => __('No valid import data provided. Please enter quantities for at least one product.', 'eseabasi-inventory')
                );
            }
            
            // Process integrations for the new imports only
            $this->process_import_integrations($new_import_ids);
            
            return array(
                'success' => true,
                'message' => sprintf(__('Successfully imported %d items.', 'eseabasi-inventory'), count($imported_items)),
                'data' => array('imported_items' => $imported_items)
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => __('An error occurred while processing the import.', 'eseabasi-inventory') . ' ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Process import integrations for new imports only
     */
    private function process_import_integrations($new_import_ids) {
        global $wpdb;
        
        if (empty($new_import_ids)) {
            return;
        }
        
        $imports_table = $wpdb->prefix . 'eseabasi_imports';
        $products_table = $wpdb->prefix . 'eseabasi_products';
        $current_values_table = $wpdb->prefix . 'eseabasi_current_values';
        $integration_meta_table = $wpdb->prefix . 'eseabasi_integration_meta';
        $current_date = $this->db->get_lagos_date();
        
        // Get the new import records
        $import_ids_string = implode(',', array_map('intval', $new_import_ids));
        $new_imports = $wpdb->get_results("
            SELECT i.*, p.name, p.is_fruit 
            FROM $imports_table i 
            LEFT JOIN $products_table p ON i.product_id = p.id 
            WHERE i.id IN ($import_ids_string)
        ");
        
        foreach ($new_imports as $import) {
            // Check if this import has already been processed
            $existing_meta = $wpdb->get_var($wpdb->prepare("
                SELECT id FROM $integration_meta_table WHERE import_id = %d
            ", $import->id));
            
            if ($existing_meta) {
                continue; // Skip already processed imports
            }
            
            if ($import->is_fruit) {
                // Update chopped form import_whole value
                $this->update_chopped_import_value($import->product_id, $import->quantity, $current_date);
            } else {
                // Update stock form added_packs value
                $this->update_stock_added_value($import->product_id, $import->quantity, $current_date);
            }
            
            // Mark this import as processed
            $wpdb->insert(
                $integration_meta_table,
                array('import_id' => $import->id),
                array('%d')
            );
        }
    }
    
    /**
     * Update chopped form import value
     */
    private function update_chopped_import_value($product_id, $quantity, $date) {
        global $wpdb;
        
        $current_values_table = $wpdb->prefix . 'eseabasi_current_values';
        
        // Get existing values
        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $current_values_table 
            WHERE product_id = %d AND form_type = 'chopped' AND current_date = %s
        ", $product_id, $date));
        
        if ($existing) {
            // Update existing record - ADD to the current import value
            $new_import_value = $existing->import_value + $quantity;
            $new_closing_value = $existing->opening_value + $new_import_value - $existing->prepared_value;
            
            $wpdb->update(
                $current_values_table,
                array(
                    'import_value' => $new_import_value,
                    'closing_value' => $new_closing_value
                ),
                array(
                    'product_id' => $product_id,
                    'form_type' => 'chopped',
                    'current_date' => $date
                ),
                array('%f', '%f'),
                array('%d', '%s', '%s')
            );
        } else {
            // Create new record
            $wpdb->insert(
                $current_values_table,
                array(
                    'product_id' => $product_id,
                    'form_type' => 'chopped',
                    'current_date' => $date,
                    'opening_value' => 0,
                    'import_value' => $quantity,
                    'prepared_value' => 0,
                    'closing_value' => $quantity,
                    'packs_gotten_value' => 0
                ),
                array('%d', '%s', '%s', '%f', '%f', '%f', '%f', '%f')
            );
        }
    }
    
    /**
     * Update stock form added value
     */
    private function update_stock_added_value($product_id, $quantity, $date) {
        global $wpdb;
        
        $current_values_table = $wpdb->prefix . 'eseabasi_current_values';
        
        // Get existing values
        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $current_values_table 
            WHERE product_id = %d AND form_type = 'stock' AND current_date = %s
        ", $product_id, $date));
        
        if ($existing) {
            // Update existing record - ADD to the current added value
            $new_added_value = $existing->added_value + $quantity;
            $new_closing_value = $existing->opening_value + $new_added_value - $existing->used_value;
            
            $wpdb->update(
                $current_values_table,
                array(
                    'added_value' => $new_added_value,
                    'closing_value' => $new_closing_value
                ),
                array(
                    'product_id' => $product_id,
                    'form_type' => 'stock',
                    'current_date' => $date
                ),
                array('%f', '%f'),
                array('%d', '%s', '%s')
            );
        } else {
            // Create new record
            $wpdb->insert(
                $current_values_table,
                array(
                    'product_id' => $product_id,
                    'form_type' => 'stock',
                    'current_date' => $date,
                    'opening_value' => 0,
                    'added_value' => $quantity,
                    'used_value' => 0,
                    'closing_value' => $quantity
                ),
                array('%d', '%s', '%s', '%f', '%f', '%f', '%f')
            );
        }
    }
    
    /**
     * Process stock form submission
     */
    public function process_stock_submission($post_data) {
        global $wpdb;
        
        try {
            $current_user = wp_get_current_user();
            $staff_name = $current_user->display_name ?: $current_user->user_login;
            $stock_date = $this->db->get_lagos_date();
            $stock_time = $this->db->get_lagos_time();
            $remarks = sanitize_textarea_field($post_data['remarks'] ?? '');
            
            $stock_table = $wpdb->prefix . 'eseabasi_stock';
            $current_values_table = $wpdb->prefix . 'eseabasi_current_values';
            
            $updated_items = array();
            
            // Process each product
            if (isset($post_data['products']) && is_array($post_data['products'])) {
                foreach ($post_data['products'] as $product_data) {
                    $product_id = intval($product_data['id']);
                    $opening_packs = floatval($product_data['opening_packs']);
                    $added_packs = floatval($product_data['added_packs']);
                    $used_packs = floatval($product_data['used_packs']);
                    $closing_packs = floatval($product_data['closing_packs']);
                    
                    // Recalculate closing packs to ensure accuracy
                    $calculated_closing = $opening_packs + $added_packs - $used_packs;
                    
                    // Insert stock record
                    $result = $wpdb->insert(
                        $stock_table,
                        array(
                            'product_id' => $product_id,
                            'opening_packs' => $opening_packs,
                            'added_packs' => $added_packs,
                            'used_packs' => $used_packs,
                            'closing_packs' => $calculated_closing,
                            'staff_name' => $staff_name,
                            'stock_date' => $stock_date,
                            'stock_time' => $stock_time,
                            'remarks' => $remarks
                        ),
                        array('%d', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s')
                    );
                    
                    if ($result !== false) {
                        // Update current values (preserve added_packs from imports)
                        $this->update_stock_current_values($product_id, $opening_packs, $used_packs, $stock_date);
                        
                        $updated_items[] = array(
                            'product_id' => $product_id,
                            'product_name' => $product_data['name'],
                            'closing_packs' => $calculated_closing
                        );
                    }
                }
            }
            
            if (empty($updated_items)) {
                return array(
                    'success' => false,
                    'message' => __('No valid stock data provided.', 'eseabasi-inventory')
                );
            }
            
            return array(
                'success' => true,
                'message' => sprintf(__('Successfully updated stock for %d items.', 'eseabasi-inventory'), count($updated_items)),
                'data' => array('updated_items' => $updated_items)
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => __('An error occurred while processing the stock update.', 'eseabasi-inventory') . ' ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Update stock current values (preserve added_packs from imports)
     */
    private function update_stock_current_values($product_id, $opening_packs, $used_packs, $date) {
        global $wpdb;
        
        $current_values_table = $wpdb->prefix . 'eseabasi_current_values';
        
        // Get existing values
        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $current_values_table 
            WHERE product_id = %d AND form_type = 'stock' AND current_date = %s
        ", $product_id, $date));
        
        if ($existing) {
            // Update existing record (preserve added_value from imports)
            $new_closing_value = $opening_packs + $existing->added_value - $used_packs;
            
            $wpdb->update(
                $current_values_table,
                array(
                    'opening_value' => $opening_packs,
                    'used_value' => $used_packs,
                    'closing_value' => $new_closing_value
                ),
                array(
                    'product_id' => $product_id,
                    'form_type' => 'stock',
                    'current_date' => $date
                ),
                array('%f', '%f', '%f'),
                array('%d', '%s', '%s')
            );
        } else {
            // Create new record
            $wpdb->insert(
                $current_values_table,
                array(
                    'product_id' => $product_id,
                    'form_type' => 'stock',
                    'current_date' => $date,
                    'opening_value' => $opening_packs,
                    'added_value' => 0,
                    'used_value' => $used_packs,
                    'closing_value' => $opening_packs - $used_packs
                ),
                array('%d', '%s', '%s', '%f', '%f', '%f', '%f')
            );
        }
    }
    
    /**
     * Process chopped form submission
     */
    public function process_chopped_submission($post_data) {
        global $wpdb;
        
        try {
            $current_user = wp_get_current_user();
            $staff_name = $current_user->display_name ?: $current_user->user_login;
            $chopped_date = $this->db->get_lagos_date();
            $chopped_time = $this->db->get_lagos_time();
            $remarks = sanitize_textarea_field($post_data['remarks'] ?? '');
            
            $chopped_table = $wpdb->prefix . 'eseabasi_chopped';
            $current_values_table = $wpdb->prefix . 'eseabasi_current_values';
            
            $updated_items = array();
            $packs_gotten_updates = array();
            
            // Process each product
            if (isset($post_data['products']) && is_array($post_data['products'])) {
                foreach ($post_data['products'] as $product_data) {
                    $product_id = intval($product_data['id']);
                    $opening_whole = floatval($product_data['opening_whole']);
                    $import_whole = floatval($product_data['import_whole']);
                    $prepared_whole = floatval($product_data['prepared_whole']);
                    $packs_gotten = floatval($product_data['packs_gotten']);
                    
                    // Recalculate closing whole to ensure accuracy
                    $calculated_closing = $opening_whole + $import_whole - $prepared_whole;
                    
                    // Insert chopped record
                    $result = $wpdb->insert(
                        $chopped_table,
                        array(
                            'product_id' => $product_id,
                            'opening_whole' => $opening_whole,
                            'import_whole' => $import_whole,
                            'prepared_whole' => $prepared_whole,
                            'closing_whole' => $calculated_closing,
                            'packs_gotten' => $packs_gotten,
                            'staff_name' => $staff_name,
                            'chopped_date' => $chopped_date,
                            'chopped_time' => $chopped_time,
                            'remarks' => $remarks
                        ),
                        array('%d', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s')
                    );
                    
                    if ($result !== false) {
                        // Update current values (preserve import_value from imports)
                        $this->update_chopped_current_values($product_id, $opening_whole, $prepared_whole, $packs_gotten, $chopped_date);
                        
                        // Track packs gotten for stock integration
                        if ($packs_gotten > 0) {
                            $packs_gotten_updates[] = array(
                                'product_id' => $product_id,
                                'packs_gotten' => $packs_gotten
                            );
                        }
                        
                        $updated_items[] = array(
                            'product_id' => $product_id,
                            'product_name' => $product_data['name'],
                            'closing_whole' => $calculated_closing,
                            'packs_gotten' => $packs_gotten
                        );
                    }
                }
            }
            
            // Process packs gotten integration to stock form
            if (!empty($packs_gotten_updates)) {
                $this->process_packs_gotten_integration($packs_gotten_updates, $chopped_date);
            }
            
            if (empty($updated_items)) {
                return array(
                    'success' => false,
                    'message' => __('No valid chopped data provided.', 'eseabasi-inventory')
                );
            }
            
            return array(
                'success' => true,
                'message' => sprintf(__('Successfully updated chopped data for %d items.', 'eseabasi-inventory'), count($updated_items)),
                'data' => array('updated_items' => $updated_items)
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => __('An error occurred while processing the chopped update.', 'eseabasi-inventory') . ' ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Update chopped current values (preserve import_value from imports)
     */
    private function update_chopped_current_values($product_id, $opening_whole, $prepared_whole, $packs_gotten, $date) {
        global $wpdb;
        
        $current_values_table = $wpdb->prefix . 'eseabasi_current_values';
        
        // Get existing values
        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $current_values_table 
            WHERE product_id = %d AND form_type = 'chopped' AND current_date = %s
        ", $product_id, $date));
        
        if ($existing) {
            // Update existing record (preserve import_value from imports)
            $new_closing_value = $opening_whole + $existing->import_value - $prepared_whole;
            
            $wpdb->update(
                $current_values_table,
                array(
                    'opening_value' => $opening_whole,
                    'prepared_value' => $prepared_whole,
                    'closing_value' => $new_closing_value,
                    'packs_gotten_value' => $packs_gotten
                ),
                array(
                    'product_id' => $product_id,
                    'form_type' => 'chopped',
                    'current_date' => $date
                ),
                array('%f', '%f', '%f', '%f'),
                array('%d', '%s', '%s')
            );
        } else {
            // Create new record
            $wpdb->insert(
                $current_values_table,
                array(
                    'product_id' => $product_id,
                    'form_type' => 'chopped',
                    'current_date' => $date,
                    'opening_value' => $opening_whole,
                    'import_value' => 0,
                    'prepared_value' => $prepared_whole,
                    'closing_value' => $opening_whole - $prepared_whole,
                    'packs_gotten_value' => $packs_gotten
                ),
                array('%d', '%s', '%s', '%f', '%f', '%f', '%f', '%f')
            );
        }
    }
    
    /**
     * Process packs gotten integration to stock form
     */
    private function process_packs_gotten_integration($packs_gotten_updates, $date) {
        foreach ($packs_gotten_updates as $update) {
            $this->update_stock_added_value($update['product_id'], $update['packs_gotten'], $date);
        }
    }
}