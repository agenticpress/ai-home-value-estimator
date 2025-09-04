<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class AgenticPress_Lookups_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'Property Lookup',
            'plural'   => 'Property Lookups',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'            => '<input type="checkbox" />',
            'full_address'  => 'Address',
            'cma_status'    => 'CMA Status',
            'property_type' => 'Property Type',
            'avm_value'     => 'AVM',
            'bedrooms'      => 'Beds',
            'bathrooms'     => 'Baths',
            'last_sale_price' => 'Last Sale Price',
            'lookup_time'   => 'Lookup Date'
        ];
    }

    protected function get_sortable_columns() {
        return [
            'full_address'  => ['full_address', false],
            'property_type' => ['property_type', false],
            'avm_value'     => ['avm_value', false],
            'last_sale_price' => ['last_sale_price', false],
            'lookup_time'   => ['lookup_time', true]
        ];
    }

    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'avm_value':
            case 'last_sale_price':
                return $item[$column_name] ? '$' . number_format($item[$column_name]) : 'N/A';
            case 'lookup_time':
                return date('Y/m/d H:i:s', strtotime($item[$column_name]));
            default:
                return $item[$column_name] ? esc_html($item[$column_name]) : 'N/A';
        }
    }

    protected function column_cma_status($item) {
        if (!empty($item['gform_entry_id']) && class_exists('GFAPI')) {
            $cma_form_id = get_option('agenticpress_hv_gf_cma_form');
            if ($cma_form_id) {
                $url = admin_url('admin.php?page=gf_entries&view=entry&id=' . $cma_form_id . '&lid=' . $item['gform_entry_id']);
                return sprintf('<a href="%s" class="button button-small" target="_blank" rel="noopener noreferrer">View Entry</a>', esc_url($url));
            }
        }
        return 'Address Only';
    }

    protected function column_full_address($item) {
        $actions = [
            'view' => sprintf('<a href="#" class="view-details" data-id="%s">View Details</a>', $item['id']),
        ];

        return sprintf('%1$s %2$s',
            esc_html($item['full_address']),
            $this->row_actions($actions)
        );
    }

    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="lookup[]" value="%s" />', $item['id']
        );
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'agenticpress_properties';
        $per_page = 20;

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $paged = $this->get_pagenum();
        $offset = ($paged - 1) * $per_page;

        // Whitelist allowed orderby columns to prevent SQL injection
        $allowed_orderby = [
            'full_address' => 'full_address',
            'property_type' => 'property_type', 
            'avm_value' => 'avm_value',
            'last_sale_price' => 'last_sale_price',
            'lookup_time' => 'lookup_time'
        ];
        
        $orderby_input = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'lookup_time';
        $orderby = isset($allowed_orderby[$orderby_input]) ? $allowed_orderby[$orderby_input] : 'lookup_time';
        
        $order_input = isset($_GET['order']) ? strtolower(sanitize_key($_GET['order'])) : 'desc';
        $order = in_array($order_input, ['asc', 'desc']) ? $order_input : 'desc';

        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        
        // Build the query safely
        if ($search) {
            $search_sql = $wpdb->prepare("WHERE full_address LIKE %s OR owner_name LIKE %s", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%');
            
            $total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_name WHERE full_address LIKE %s OR owner_name LIKE %s", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%'));
            
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name WHERE full_address LIKE %s OR owner_name LIKE %s ORDER BY $orderby $order LIMIT %d OFFSET %d",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                $per_page, 
                $offset
            );
        } else {
            $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
            
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY $orderby $order LIMIT %d OFFSET %d",
                $per_page, 
                $offset
            );
        }

        $this->items = $wpdb->get_results($query, ARRAY_A);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
}