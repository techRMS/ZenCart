<?php
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}
/*
 * Stages an order to prevent a callback duplicate
 */
class StageOrder{
    // Create all the required variables
    public function __construct($order_id){
        $this->_order_id = $order_id;
        $this->_order = null;
        $this->products = null;
        $this->info = null;
    }
    // Substitute the zen cart order for our temporary order
    public function stage(){
        global $order;
        $this->_order = $order;
        $order = $this;
    }
    /*
     * An order is added before we have the chance to pay with a callback
     * so we substitute this temporary order class to capture it in memory before
     */
    public function create($zf_ot_modules, $zf_mode = 2) {
        return $this->_order_id;
    }
    public function create_add_products($zf_insert_id, $zf_mode = false){
        // Do nothing, we already have the products. Instead try to retrieve them...
        $this->products = $this->_order->products;
        $this->info = $this->_order->info;
    }
    public function send_order_email($zf_insert_id, $zf_mode = FALSE) {
        // Do nothing since these emails have already been sent.
    }
    public function fallback(){
        global $order;
        $order = $this->_order;
    }
}

?>