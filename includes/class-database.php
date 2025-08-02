<?php
/**
 * Database management class
 *
 * @package EseabasiInventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class Eseabasi_Database {
    
    /**
     * Create all required database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Products table
        $products_table = $wpdb->prefix . 'eseabasi_products';
        $sql_products = "CREATE TABLE $products_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type enum('import','stock','chopped') NOT NULL,
            is_fruit tinyint(1) DEFAULT 0,
            status enum('active','inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type_status (type, status)
        ) $charset_collate;";
        
        // Imports table
        $imports_table = $wpdb->prefix . 'eseabasi_imports';
        $sql_imports = "CREATE TABLE $imports_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id mediumint(9) NOT NULL,
            quantity decimal(10,2) NOT NULL,
            staff_name varchar(255) NOT NULL,
            import_date date NOT NULL,
            import_time time NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_date (product_id, import_date),
            FOREIGN KEY (product_id) REFERENCES $products_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Stock table
        $stock_table = $wpdb->prefix . 'eseabasi_stock';
        $sql_stock = "CREATE TABLE $stock_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id mediumint(9) NOT NULL,
            opening_packs decimal(10,2) DEFAULT 0,
            added_packs decimal(10,2) DEFAULT 0,
            used_packs decimal(10,2) DEFAULT 0,
            closing_packs decimal(10,2) DEFAULT 0,
            staff_name varchar(255) NOT NULL,
            stock_date date NOT NULL,
            stock_time time NOT NULL,
            remarks text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_date (product_id, stock_date),
            FOREIGN KEY (product_id) REFERENCES $products_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Chopped table
        $chopped_table = $wpdb->prefix . 'eseabasi_chopped';
        $sql_chopped = "CREATE TABLE $chopped_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id mediumint(9) NOT NULL,
            opening_whole decimal(10,2) DEFAULT 0,
            import_whole decimal(10,2) DEFAULT 0,
            prepared_whole decimal(10,2) DEFAULT 0,
            closing_whole decimal(10,2) DEFAULT 0,
            packs_gotten decimal(10,2) DEFAULT 0,
            staff_name varchar(255) NOT NULL,
            chopped_date date NOT NULL,
            chopped_time time NOT NULL,
            remarks text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_date (product_id, chopped_date),
            FOREIGN KEY (product_id) REFERENCES $products_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Current values table (for daily form values)
        $current_values_table = $wpdb->prefix . 'eseabasi_current_values';
        $sql_current = "CREATE TABLE $current_values_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id mediumint(9) NOT NULL,
            form_type enum('stock','chopped') NOT NULL,
            current_date date NOT NULL,
            opening_value decimal(10,2) DEFAULT 0,
            added_value decimal(10,2) DEFAULT 0,
            used_value decimal(10,2) DEFAULT 0,
            closing_value decimal(10,2) DEFAULT 0,
            import_value decimal(10,2) DEFAULT 0,
            prepared_value decimal(10,2) DEFAULT 0,
            packs_gotten_value decimal(10,2) DEFAULT 0,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_product_form_date (product_id, form_type, current_date),
            FOREIGN KEY (product_id) REFERENCES $products_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Integration meta table (to track processed imports)
        $integration_meta_table = $wpdb->prefix . 'eseabasi_integration_meta';
        $sql_integration = "CREATE TABLE $integration_meta_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            import_id mediumint(9) NOT NULL,
            processed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_import (import_id),
            FOREIGN KEY (import_id) REFERENCES $imports_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_products);
        dbDelta($sql_imports);
        dbDelta($sql_stock);
        dbDelta($sql_chopped);
        dbDelta($sql_current);
        dbDelta($sql_integration);
    }
    
    /**
     * Insert default products
     */
    public function insert_default_products() {
        global $wpdb;
        
        $products_table = $wpdb->prefix . 'eseabasi_products';
        
        // Check if products already exist
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM $products_table");
        if ($existing > 0) {
            return;
        }
        
        // All products for import and stock forms
        $all_products = array(
            'Almond', 'Apple', 'Baking Powder', 'Banana', 'Blueberry', 'Cake', 
            'Caramel/Chocolate Syrup', 'Cashew Nut', 'Cherry', 'Chia Seed', 
            'Cinnamon', 'Cocoa Powder', 'Coconut Flakes', 'Coffee', 'Condensed Milk', 
            'Cucumber', 'Dates', 'Egg', 'Evaporated Milk', 'Fresh Coconut', 
            'Ginger', 'Granola', 'Grape', 'Groundnuts', 'Honey', 'Ice Cream', 
            'Kiwi', 'Lemon', 'Lime', 'Maca Powder', 'Nut Packed', 'Nutri Choco', 
            'Oat', 'Oranges', 'Paw Paw', 'Peanut Butter', 'Peanuts', 'Pineapple', 
            'Powdered Milk', 'Pumpkin Seed', 'Raisin', 'Rapha Yoghurt', 'Strawberry', 
            'Sugar', 'Sunflower Seed', 'Tiger Nut', 'Watermelon', 'Whey Protein'
        );
        
        // Fruits for chopped form
        $fruits_for_chopped = array(
            'Almond', 'Cucumber', 'Dates', 'Fresh Coconut', 'Ginger', 'Grape', 
            'Ice Cream', 'Kiwi', 'Lemon', 'Lime', 'Paw Paw', 'Pineapple', 
            'Tiger Nut', 'Watermelon'
        );
        
        // Insert products for import form
        foreach ($all_products as $product) {
            $is_fruit = in_array($product, $fruits_for_chopped) ? 1 : 0;
            
            $wpdb->insert(
                $products_table,
                array(
                    'name' => $product,
                    'type' => 'import',
                    'is_fruit' => $is_fruit,
                    'status' => 'active'
                ),
                array('%s', '%s', '%d', '%s')
            );
        }
        
        // Insert products for stock form
        foreach ($all_products as $product) {
            $is_fruit = in_array($product, $fruits_for_chopped) ? 1 : 0;
            
            $wpdb->insert(
                $products_table,
                array(
                    'name' => $product,
                    'type' => 'stock',
                    'is_fruit' => $is_fruit,
                    'status' => 'active'
                ),
                array('%s', '%s', '%d', '%s')
            );
        }
        
        // Insert products for chopped form (only fruits)
        foreach ($fruits_for_chopped as $fruit) {
            $wpdb->insert(
                $products_table,
                array(
                    'name' => $fruit,
                    'type' => 'chopped',
                    'is_fruit' => 1,
                    'status' => 'active'
                ),
                array('%s', '%s', '%d', '%s')
            );
        }
    }
    
    /**
     * Get products by type
     */
    public function get_products_by_type($type, $status = 'active') {
        global $wpdb;
        
        $products_table = $wpdb->prefix . 'eseabasi_products';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $products_table WHERE type = %s AND status = %s ORDER BY name ASC",
            $type, $status
        ));
    }
    
    /**
     * Get current date in Lagos timezone
     */
    public function get_lagos_date() {
        $timezone = new DateTimeZone('Africa/Lagos');
        $date = new DateTime('now', $timezone);
        return $date->format('Y-m-d');
    }
    
    /**
     * Get current time in Lagos timezone
     */
    public function get_lagos_time() {
        $timezone = new DateTimeZone('Africa/Lagos');
        $date = new DateTime('now', $timezone);
        return $date->format('H:i:s');
    }
    
    /**
     * Get current datetime in Lagos timezone
     */
    public function get_lagos_datetime() {
        $timezone = new DateTimeZone('Africa/Lagos');
        $date = new DateTime('now', $timezone);
        return $date->format('Y-m-d H:i:s');
    }
}