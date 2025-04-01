<?php
class ExamplePlugin extends BasePlugin {
    public function header() {
        echo "<!-- Example Plugin Header -->";
    }

    public function footer() {
        echo "<p style='text-align: center;'>Example Plugin by Claunt</p>";
    }

    public function add_product_form() {
        echo "<label>Plugin Field: <input type='text' name='plugin_field'></label>";
    }

    public function update_product_form($data) {
        $product = $data['product'];
        echo "<label>Plugin Field: <input type='text' name='plugin_field' value='Example for {$product['name']}'></label>";
    }

    public function product_display($data) {
        $product = $data['product'];
        echo "<p style='color: blue;'>Plugin Note: Special offer for {$product['name']}!</p>";
    }

    public function before_action($data) {
        // Example: Log actions
        error_log("Before action: " . $data['post']['action']);
    }

    public function after_action($data) {
        // Example: Modify message
        if (isset($data['message'])) {
            $data['message'] .= " (Processed by Example Plugin)";
        }
        return $data;
    }
}