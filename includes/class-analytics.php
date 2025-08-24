<?php
/**
 * Analytics dashboard class
 *
 * @package EseabasiInventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class Eseabasi_Analytics {
    
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Eseabasi_Database();
    }
    
    /**
     * Render analytics dashboard
     */
    public function render_analytics_dashboard() {
        $analytics_data = $this->get_analytics_data();
        
        ob_start();
        ?>
        <div class="analytics-cards">
            <div class="analytics-card">
                <div class="analytics-card-icon">📦</div>
                <div class="analytics-card-content">
                    <h3><?php echo number_format($analytics_data['total_imports']); ?></h3>
                    <p><?php echo esc_html__('Total Import Records', 'eseabasi-inventory'); ?></p>
                </div>
            </div>
            
            <div class="analytics-card">
                <div class="analytics-card-icon">📊</div>
                <div class="analytics-card-content">
                    <h3><?php echo number_format($analytics_data['total_stock_records']); ?></h3>
                    <p><?php echo esc_html__('Total Stock Records', 'eseabasi-inventory'); ?></p>
                </div>
            </div>
            
            <div class="analytics-card">
                <div class="analytics-card-icon">🔪</div>
                <div class="analytics-card-content">
                    <h3><?php echo number_format($analytics_data['total_chopped_records']); ?></h3>
                    <p><?php echo esc_html__('Total Chopped Records', 'eseabasi-inventory'); ?></p>
                </div>
            </div>
            
            <div class="analytics-card">
                <div class="analytics-card-icon">🛑</div>
                <div class="analytics-card-content">
                    <h3><?php echo number_format($analytics_data['low_stock_count']); ?></h3>
                    <p><?php echo esc_html__('Low Stock Items', 'eseabasi-inventory'); ?></p>
                </div>
            </div>
        </div>
        
        <?php if (!empty($analytics_data['low_stock_items'])): ?>
        <div class="analytics-section">
            <h3><?php echo esc_html__('Low Stock Alert', 'eseabasi-inventory'); ?></h3>
            <div class="low-stock-grid">
                <?php foreach ($analytics_data['low_stock_items'] as $item): ?>
                <div class="low-stock-item">
                    <div class="item-name"><?php echo esc_html($item->name); ?></div>
                    <div class="item-stock"><?php echo number_format($item->closing_value ?: 0, 2); ?> packs</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="analytics-section">
            <h3><?php echo esc_html__('Recent Activity', 'eseabasi-inventory'); ?></h3>
            <div class="recent-activity">
                <?php echo $this->render_recent_activity(); ?>
            </div>
        </div>
        
        <div class="analytics-section">
            <h3><?php echo esc_html__('Daily Summary', 'eseabasi-inventory'); ?></h3>
            <div class="daily-summary">
                <?php echo $this->render_daily_summary(); ?>
            </div>
        </div>
        
        <style>
        .analytics-section {
            background: white;
            margin: 30px 0;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .analytics-section h3 {
            color: #FF0000;
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.5em;
            border-bottom: 2px solid #FF0000;
            padding-bottom: 10px;
        }
        
        .low-stock-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .low-stock-item {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
        }
        
        .item-name {
            font-weight: bold;
            color: #856404;
            margin-bottom: 5px;
        }
        
        .item-stock {
            color: #856404;
            font-size: 0.9em;
        }
        
        .recent-activity-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .recent-activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-info {
            flex: 1;
        }
        
        .activity-type {
            font-weight: bold;
            color: #FF0000;
        }
        
        .activity-details {
            color: #666;
            font-size: 0.9em;
            margin-top: 4px;
        }
        
        .activity-time {
            color: #999;
            font-size: 0.85em;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #FF0000, #CC0000);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .summary-card h4 {
            margin: 0 0 10px 0;
            font-size: 1.2em;
        }
        
        .summary-card .number {
            font-size: 2em;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .summary-card .label {
            opacity: 0.9;
            font-size: 0.9em;
        }
        
        @media (max-width: 768px) {
            .low-stock-grid {
                grid-template-columns: 1fr;
            }
            
            .summary-grid {
                grid-template-columns: 1fr;
            }
            
            .recent-activity-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get analytics data
     */
    private function get_analytics_data() {
        global $wpdb;
        
        $imports_table = $wpdb->prefix . 'eseabasi_imports';
        $stock_table = $wpdb->prefix . 'eseabasi_stock';
        $chopped_table = $wpdb->prefix . 'eseabasi_chopped';
        $products_table = $wpdb->prefix . 'eseabasi_products';
        $current_values_table = $wpdb->prefix . 'eseabasi_current_values';
        
        // Get basic counts
        $total_imports = $wpdb->get_var("SELECT COUNT(*) FROM $imports_table");
        $total_stock_records = $wpdb->get_var("SELECT COUNT(*) FROM $stock_table");
        $total_chopped_records = $wpdb->get_var("SELECT COUNT(*) FROM $chopped_table");
        
        // Get low stock threshold
        $threshold = get_option('eseabasi_low_stock_threshold', 10);
        $current_date = $this->db->get_lagos_date();
        
        // Get low stock items
        $low_stock_items = $wpdb->get_results($wpdb->prepare("
            SELECT p.name, cv.closing_value 
            FROM $products_table p 
            LEFT JOIN $current_values_table cv ON p.id = cv.product_id 
            WHERE p.type = 'stock' AND p.status = 'active' 
            AND (cv.closing_value < %d OR cv.closing_value IS NULL) 
            AND (cv.current_date = %s OR cv.current_date IS NULL)
        ", $threshold, $current_date));
        
        return array(
            'total_imports' => intval($total_imports),
            'total_stock_records' => intval($total_stock_records),
            'total_chopped_records' => intval($total_chopped_records),
            'low_stock_count' => count($low_stock_items),
            'low_stock_items' => $low_stock_items
        );
    }
    
    /**
     * Render recent activity
     */
    private function render_recent_activity() {
        global $wpdb;
        
        $imports_table = $wpdb->prefix . 'eseabasi_imports';
        $stock_table = $wpdb->prefix . 'eseabasi_stock';
        $chopped_table = $wpdb->prefix . 'eseabasi_chopped';
        $products_table = $wpdb->prefix . 'eseabasi_products';
        
        // Get recent imports
        $recent_imports = $wpdb->get_results("
            SELECT 'import' as type, i.staff_name, p.name as product_name, i.quantity, i.created_at
            FROM $imports_table i 
            LEFT JOIN $products_table p ON i.product_id = p.id 
            ORDER BY i.created_at DESC 
            LIMIT 5
        ");
        
        // Get recent stock updates
        $recent_stock = $wpdb->get_results("
            SELECT 'stock' as type, s.staff_name, p.name as product_name, s.closing_packs as quantity, s.created_at
            FROM $stock_table s 
            LEFT JOIN $products_table p ON s.product_id = p.id 
            ORDER BY s.created_at DESC 
            LIMIT 5
        ");
        
        // Get recent chopped updates
        $recent_chopped = $wpdb->get_results("
            SELECT 'chopped' as type, c.staff_name, p.name as product_name, c.packs_gotten as quantity, c.created_at
            FROM $chopped_table c 
            LEFT JOIN $products_table p ON c.product_id = p.id 
            ORDER BY c.created_at DESC 
            LIMIT 5
        ");
        
        // Combine and sort all activities
        $all_activities = array_merge($recent_imports, $recent_stock, $recent_chopped);
        usort($all_activities, function($a, $b) {
            return strtotime($b->created_at) - strtotime($a->created_at);
        });
        
        $activities = array_slice($all_activities, 0, 10);
        
        ob_start();
        ?>
        <?php if (!empty($activities)): ?>
            <?php foreach ($activities as $activity): ?>
            <div class="recent-activity-item">
                <div class="activity-info">
                    <div class="activity-type">
                        <?php 
                        switch ($activity->type) {
                            case 'import':
                                echo '📦 ' . esc_html__('Import', 'eseabasi-inventory');
                                break;
                            case 'stock':
                                echo '📊 ' . esc_html__('Stock Update', 'eseabasi-inventory');
                                break;
                            case 'chopped':
                                echo '🔪 ' . esc_html__('Chopped Update', 'eseabasi-inventory');
                                break;
                        }
                        ?>
                    </div>
                    <div class="activity-details">
                        <?php echo esc_html($activity->staff_name); ?> updated 
                        <strong><?php echo esc_html($activity->product_name); ?></strong>
                        <?php if ($activity->quantity): ?>
                            (<?php echo number_format($activity->quantity, 2); ?>)
                        <?php endif; ?>
                    </div>
                </div>
                <div class="activity-time">
                    <?php echo human_time_diff(strtotime($activity->created_at), current_time('timestamp')); ?> ago
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p><?php echo esc_html__('No recent activity found.', 'eseabasi-inventory'); ?></p>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render daily summary
     */
    private function render_daily_summary() {
        global $wpdb;
        
        $current_date = $this->db->get_lagos_date();
        $imports_table = $wpdb->prefix . 'eseabasi_imports';
        $stock_table = $wpdb->prefix . 'eseabasi_stock';
        $chopped_table = $wpdb->prefix . 'eseabasi_chopped';
        
        // Today's imports
        $today_imports = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $imports_table WHERE import_date = %s
        ", $current_date));
        
        $today_import_quantity = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(quantity) FROM $imports_table WHERE import_date = %s
        ", $current_date));
        
        // Today's stock updates
        $today_stock_updates = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $stock_table WHERE stock_date = %s
        ", $current_date));
        
        // Today's chopped updates
        $today_chopped_updates = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $chopped_table WHERE chopped_date = %s
        ", $current_date));
        
        $today_packs_gotten = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(packs_gotten) FROM $chopped_table WHERE chopped_date = %s
        ", $current_date));
        
        ob_start();
        ?>
        <div class="summary-grid">
            <div class="summary-card">
                <h4><?php echo esc_html__("Today's Imports", 'eseabasi-inventory'); ?></h4>
                <div class="number"><?php echo number_format($today_imports); ?></div>
                <div class="label">
                    <?php echo number_format($today_import_quantity ?: 0, 2); ?> total quantity
                </div>
            </div>
            
            <div class="summary-card">
                <h4><?php echo esc_html__('Stock Updates', 'eseabasi-inventory'); ?></h4>
                <div class="number"><?php echo number_format($today_stock_updates); ?></div>
                <div class="label"><?php echo esc_html__('Records updated today', 'eseabasi-inventory'); ?></div>
            </div>
            
            <div class="summary-card">
                <h4><?php echo esc_html__('Chopped Updates', 'eseabasi-inventory'); ?></h4>
                <div class="number"><?php echo number_format($today_chopped_updates); ?></div>
                <div class="label"><?php echo esc_html__('Records updated today', 'eseabasi-inventory'); ?></div>
            </div>
            
            <div class="summary-card">
                <h4><?php echo esc_html__('Packs Produced', 'eseabasi-inventory'); ?></h4>
                <div class="number"><?php echo number_format($today_packs_gotten ?: 0, 2); ?></div>
                <div class="label"><?php echo esc_html__('From chopped fruits', 'eseabasi-inventory'); ?></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get export data for analytics
     */
    public function get_export_data($type, $format = 'csv', $filters = array()) {
        global $wpdb;
        
        $products_table = $wpdb->prefix . 'eseabasi_products';
        
        switch ($type) {
            case 'import':
                return $this->export_import_data($format, $filters);
            case 'stock':
                return $this->export_stock_data($format, $filters);
            case 'chopped':
                return $this->export_chopped_data($format, $filters);
            default:
                return array(
                    'success' => false,
                    'message' => __('Invalid export type.', 'eseabasi-inventory')
                );
        }
    }
    
    /**
     * Export import data
     */
    private function export_import_data($format, $filters) {
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
        ";
        
        if (!empty($params)) {
            $records = $wpdb->get_results($wpdb->prepare($query, $params));
        } else {
            $records = $wpdb->get_results($query);
        }
        
        if ($format === 'csv') {
            $csv_data = "Product Name,Quantity,Staff Name,Import Date,Import Time,Created At\n";
            
            foreach ($records as $record) {
                $csv_data .= sprintf(
                    '"%s","%s","%s","%s","%s","%s"' . "\n",
                    $record->product_name,
                    $record->quantity,
                    $record->staff_name,
                    $record->import_date,
                    $record->import_time,
                    $record->created_at
                );
            }
            
            return array(
                'success' => true,
                'content' => $csv_data,
                'filename' => 'import_history_' . date('Y-m-d') . '.csv'
            );
        }
        
        return array(
            'success' => false,
            'message' => __('Unsupported export format.', 'eseabasi-inventory')
        );
    }
    
    /**
     * Export stock data
     */
    private function export_stock_data($format, $filters) {
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
        ";
        
        if (!empty($params)) {
            $records = $wpdb->get_results($wpdb->prepare($query, $params));
        } else {
            $records = $wpdb->get_results($query);
        }
        
        if ($format === 'csv') {
            $csv_data = "Product Name,Opening Packs,Added Packs,Used Packs,Closing Packs,Staff Name,Stock Date,Remarks,Created At\n";
            
            foreach ($records as $record) {
                $csv_data .= sprintf(
                    '"%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                    $record->product_name,
                    $record->opening_packs,
                    $record->added_packs,
                    $record->used_packs,
                    $record->closing_packs,
                    $record->staff_name,
                    $record->stock_date,
                    $record->remarks,
                    $record->created_at
                );
            }
            
            return array(
                'success' => true,
                'content' => $csv_data,
                'filename' => 'stock_history_' . date('Y-m-d') . '.csv'
            );
        }
        
        return array(
            'success' => false,
            'message' => __('Unsupported export format.', 'eseabasi-inventory')
        );
    }
    
    /**
     * Export chopped data
     */
    private function export_chopped_data($format, $filters) {
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
        ";
        
        if (!empty($params)) {
            $records = $wpdb->get_results($wpdb->prepare($query, $params));
        } else {
            $records = $wpdb->get_results($query);
        }
        
        if ($format === 'csv') {
            $csv_data = "Fruit Name,Opening Whole,Import Whole,Prepared Whole,Closing Whole,Packs Gotten,Staff Name,Chopped Date,Remarks,Created At\n";
            
            foreach ($records as $record) {
                $csv_data .= sprintf(
                    '"%s","%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                    $record->product_name,
                    $record->opening_whole,
                    $record->import_whole,
                    $record->prepared_whole,
                    $record->closing_whole,
                    $record->packs_gotten,
                    $record->staff_name,
                    $record->chopped_date,
                    $record->remarks,
                    $record->created_at
                );
            }
            
            return array(
                'success' => true,
                'content' => $csv_data,
                'filename' => 'chopped_history_' . date('Y-m-d') . '.csv'
            );
        }
        
        return array(
            'success' => false,
            'message' => __('Unsupported export format.', 'eseabasi-inventory')
        );
    }
}