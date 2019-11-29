<?php
/*
Plugin Name: Recharge to Woocommerce Subscriptions
Plugin URI: https://apexdevs.io/
Description: Export Subsscriptions to Woocommerce Subscriptions
Version: 1.0.0
Author: Tex0gen
Author URI: https://apexdevs.io
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

class ReCharge_Exporter {
  function __construct() {
    add_action( 'admin_menu', array( $this, 'admin_menu' ) );
    add_action( 'admin_init', array( $this, 'register_settings' ) );
  }

  function admin_menu() {
    add_options_page( 'ReCharge2WCS', 'ReCharge2WCS', 'manage_options', 'rc-to-wcs', array( $this, 'admin_page') );
  }

  // Register settings
  function register_settings() {
    register_setting( 'rc2wcs', 'rc2wcs_key' );
  }

  // Admin Setting Page
  function admin_page() {
    ?>
    <div class="wrap">
      <form method="post" action="options.php">
        <?php settings_fields( 'rc2wcs' ); ?>
        <?php do_settings_sections( 'rc2wcs' ); ?>
        <h1>ReCharge to WC Subscriptions</h1>
        <p></p>
        <table class="form-table">
          <tr valign="top">
            <th scope="row">API Key</th>
            <td>
              <input type="text" name="rc2wcs_key" value="<?= (get_option('rc2wcs_key')) ? esc_attr( get_option('rc2wcs_key') ):''; ?>" />
            </td>
          </tr>
        </table>
        <?php submit_button('Generate CSV'); ?>
      </form>
    </div>
    <?php

    self::data_factory();
  }

  private function recharge_call($endpoint, $opts = "") {
    $key = get_option('rc2wcs_key');

    $ch = curl_init();

    $header = array();
    $header[] = 'X-Recharge-Access-Token: ' . $key;
    $header[] = 'Content-Type: application/json';

    curl_setopt($ch, CURLOPT_URL, $endpoint . $opts);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $result = curl_exec($ch);

    curl_close($ch);

    return json_decode($result);
  }

  private function get_subscriptions() {
    $subscription_call = self::recharge_call('https://api.rechargeapps.com/subscriptions', '?limit=250&page=1');
    $subscriptions[] = $subscription_call->subscriptions;
    $subscription_count = count($subscription_call->subscriptions);
    $i = 1;

    if ($subscription_count > 0) {
      while ($subscription_count === 250) {
        $subscription_call = self::recharge_call('https://api.rechargeapps.com/subscriptions', '?limit=250&page='.$i++);
        array_push($subscriptions, $subscription_call->subscriptions);
        $subscription_count = count($subscription_call->subscriptions);
      }
    }

    return $subscriptions;
  }

  private function get_customers() {
    $customer_call = self::recharge_call('https://api.rechargeapps.com/customers', '?limit=250&page=1');
    $customers[] = $customer_call->customers;
    $customer_count = count($customer_call->customers);
    $i = 1;

    if ($customer_count > 0) {
      while ($customer_count === 250) {
        $customer_call = self::recharge_call('https://api.rechargeapps.com/customers', '?limit=250&page='.$i++);
        array_push($customers, $customer_call->customers);
        $customer_count = count($customer_call->customers);
      }
    }

    return $customers;
  }

  private function get_charges() {
    $charges_call = self::recharge_call('https://api.rechargeapps.com/charges', '?date_min='.date('Y-m-d').'T00:00:00&limit=250&page=1');
    $charges[] = $charges_call->charges;
    $charges_count = count($charges_call->charges);
    $i = 1;

    if ($charges_count > 0) {
      while ($charges_count === 250) {
        $charges_call = self::recharge_call('https://api.rechargeapps.com/charges', '?date_min='.date('Y-m-d').'T00:00:00&limit=250&page='.$i++);
        array_push($charges, $charges_call->charges);
        $charges_count = count($charges_call->charges);
      }
    }

    return $charges;
  }

  private function data_factory() {
    $customers = self::get_customers();
    // $subscriptions = self::get_subscriptions();
    // $charges = self::get_charges();

    $new_array = array();

    /*
    customer_id
    customer_username
    customer_email
    subscription_status
    start_date
    trial_end_date
    next_payment_date
    last_payment_date end_date
    billing_period
    billing_interval
    order_shipping
    order_shipping_tax
    order_tax
    cart_discount
    cart_discount_tax
    order_total
    order_currency
    payment_method
    payment_method_title
    payment_method_post_meta
    payment_method_user_meta
    shipping_method
    billing_first_name
    billing_last_name
    billing_email
    billing_phone
    billing_address_1
    billing_address_2
    billing_postcode
    billing_city
    billing_state
    billing_country
    billing_company
    shipping_first_name
    shipping_last_name
    shipping_address_1
    shipping_address_2
    shipping_postcode
    shipping_city
    shipping_state
    shipping_country
    shipping_company
    customer_note
    order_items
    order_notes
    coupon_items
    fee_items
    tax_items
    download_permissions
    */

    if ($customers) {
      // var_dump($customers);
      foreach ($customers as $cust_outer) {
        foreach ($cust_outer as $key => $customer) {
          // var_dump($customer);
          // Remove IF statement if you want ALL customers with or without an active subscription
          if ($customer->status === "ACTIVE") {
            $new_array[$customer->shopify_customer_id]['customer_id'] = $customer->shopify_customer_id;
            $new_array[$customer->shopify_customer_id]['customer_email'] = $customer->email;
            $new_array[$customer->shopify_customer_id]['subscription_status'] = ($customer->status === "ACTIVE") ? 'wc-active':'wc-cancelled';
            $new_array[$customer->shopify_customer_id]['start_date'] = strtotime('Y-m-d h:i:s', $customer->created_at);
            $new_array[$customer->shopify_customer_id]['billing_period'] = 'month';
            $new_array[$customer->shopify_customer_id]['billing_interval'] = '1';
            $new_array[$customer->shopify_customer_id]['order_currency'] = 'GBP';
            $new_array[$customer->shopify_customer_id]['payment_method'] = 'stripe';
            $new_array[$customer->shopify_customer_id]['payment_method_title'] = 'Credit card (Stripe)';
            $new_array[$customer->shopify_customer_id]['payment_method_post_meta'] = '_stripe_customer_id:'.$customer->stripe_customer_token.'|_stripe_source_id:';
            $new_array[$customer->shopify_customer_id]['billing_first_name'] = $customer->first_name;
            $new_array[$customer->shopify_customer_id]['billing_last_name'] = $customer->last_name;
            $new_array[$customer->shopify_customer_id]['billing_email'] = $customer->email;
            $new_array[$customer->shopify_customer_id]['billing_phone'] = $customer->billing_phone;
            $new_array[$customer->shopify_customer_id]['billing_address_1'] = $customer->billing_address1;
            $new_array[$customer->shopify_customer_id]['billing_address_2'] = $customer->billing_address2;
            $new_array[$customer->shopify_customer_id]['billing_postcode'] = $customer->billing_zip;
            $new_array[$customer->shopify_customer_id]['billing_city'] = $customer->billing_city;
          }
        }
      }
    }

    var_dump($new_array);

    // if ($subscriptions) {
    //   foreach ($subscriptions as $sub_outer) {
    //     foreach ($sub_outer as $key => $sub) {
    //       var_dump($sub);
    //       $new_array['customer_id'] = '';
    //       $new_array['customer_email'] = '';
    //     }
    //   }
    // }

    // if ($charges) {

    // }
    // var_dump($charges);
  }

  private function create_csv() {
    
  }
}

new ReCharge_Exporter;