<?php

define('HOOK_NAME',  'hook_compare_transaction');
class WC_Pace_Cron
{

    /**
     * setup cron
     *
     * @return void
     */
    static function setup()
    {
        // self::handle_add_cron();
        add_action(HOOK_NAME,  'WC_Pace_Cron::compare_transaction');
    }

    /**
     * add next cron in database
     *
     * @return void
     */
    static function handle_add_cron()
    {
        $pace_settings = get_option('woocommerce_pace_settings');
        $time =  isset($pace_settings['interval_cron']) && is_numeric($pace_settings['interval_cron']) ? (int) $pace_settings['interval_cron'] : 300;
        if (function_exists('wp_next_scheduled') &&  function_exists('wp_schedule_single_event')) {
            if (!wp_next_scheduled(HOOK_NAME)) {
                wp_schedule_single_event(time() +  $time, HOOK_NAME);
            }
        }
    }

    /**
     * check order update by system
     *
     * @param  int $order_id
     * @return boolean
     */
    static function check_order_manually_update($order_id, $status = null) {
        //cron update when status is pending
        //pending case
        $order = wc_get_order($order_id);
        
        if ($status == "pending_confirmation" && ($order->get_status() ==  'cancelled' || $order->get_status() == 'failed') ) {
            WC_Pace_Gateway_Payment::cancel_transaction($order);
            return false;
        }
        
        if ($order->get_status() == "pending") {
            return true;
        }

        

        if ($order->get_status() != "cancelled" && $order->get_status() != "failed") {
            return false;
        }

        //  check function wc_get_order_notes exist from woo 
        if (function_exists('wc_get_order_notes')) {
            $notes = wc_get_order_notes(['order_id' => $order_id]) ? wc_get_order_notes(['order_id' => $order_id]) : [];
        } else {
            $notes = self::get_notes_pace($order_id);
        }

        // check system update or person update 
        // so for cancel or on hold case it will base on system or merchant
        // get last note because it order by order id desc we get first index
        foreach ($notes as $note) {
            if ( WC_Pace_Cron::check_note_change_status($note->content) ) {
                return $note->added_by == 'system';
            }
        }

        return true;
    }

    /**
     * format note data from comment
     *
     * @param  mixed $data
     * @return void
     */
    static function format_note_data($data)
    {
        return (object)  array(
            'id'            => (int) $data->comment_ID,
            'date_created'  => wc_string_to_datetime($data->comment_date),
            'content'       => $data->comment_content,
            'customer_note' => (bool) get_comment_meta($data->comment_ID, 'is_customer_note', true),
            'added_by'      => __('WooCommerce', 'woocommerce') === $data->comment_author ? 'system' : $data->comment_author,
        );
    }

    /**
     * get comment by order id
     *
     * @param  mixed $order_id
     * @return void
     */
    static function get_notes_pace($order_id)
    {

        remove_filter('comments_clauses', array('WC_Comments', 'exclude_order_comments'), 10, 1);

        $comments = get_comments([
            "post_id" => $order_id,
            "orderby" => "comment_ID",
            "type" => "order_note"
        ]);

        add_filter('comments_clauses', array('WC_Comments', 'exclude_order_comments'), 10, 1);

        $notes = [];
        if (count($comments) > 0) {
            foreach ($comments as $value) {
                $notes[] = WC_Pace_Cron::format_note_data($value);
            }
        }
        return $notes;
    }


    /**
     * check note change status
     *
     * @param  mixed $note
     * @return void
     */
    static function check_note_change_status($note)
    {
        return preg_match('/Order status changed from/', $note);
    }

    /**
     * compare woo with pace status
     *
     * @return void
     */
    static function compare_transaction()
    {

        $params = [
            "from" =>  date('Y-m-d', strtotime("-1 weeks")),
            "to"    => date('Y-m-d')
        ];
        $pace_settings = get_option('woocommerce_pace_settings');
        $fail_status = !!$pace_settings['transaction_failed'] ? "wc-" . $pace_settings['transaction_failed'] : "wc-cancelled";
        $expired_status = !!$pace_settings['transaction_expired'] ? "wc-" . $pace_settings['transaction_expired'] : "wc-failied";
        $list_transaction = WC_Pace_API::request($params, "checkouts/list");
        if ($list_transaction->items) {
            // remove duplicate order
            $orders = [];
            foreach ($list_transaction->items as $key => $transaction) {
                //sort transaction asc
                usort($transaction, function ($a, $b) {
                    return filter_var($a->transactionID, FILTER_SANITIZE_NUMBER_INT)  -  filter_var($b->transactionID, FILTER_SANITIZE_NUMBER_INT) > 0;
                });
                foreach ($transaction  as $value) {
                    $orders[$value->referenceID] = $value;
                }
            }

            foreach ($orders as $key => $value) {
                $order = wc_get_order($value->referenceID);
                if ($order) {
                    if ($order->get_payment_method() == "pace") {
                        //logic not update order when it has status completed and processing
                        // handle check manual and pending status
                        if (WC_Pace_Cron::check_order_manually_update($value->referenceID, $value->status)) {
                            //compare 4 case with pace
                            switch ($value->status) {
                                case 'cancelled':
                                    if ($order->get_status() != $fail_status) {
                                        WC_Pace_Logger::log("Convert " . $order->get_id() . " from " . $order->get_status()   . " $fail_status");
                                        $order->set_status($fail_status);
                                        $order->save();
                                    }
                                    break;
                                case 'pending_confirmation':
                                    if ($order->get_status() != "pending") {
                                        WC_Pace_Logger::log("Convert " . $order->get_id() . " from " . $order->get_status()   . " wc-pending");
                                        $order->set_status("wc-pending");
                                        $order->save();
                                    }
                                    break;
                                case 'approved':
                                    $order->payment_complete();
                                    break;
                                case 'expired':
                                    if ($order->get_status() != $expired_status) {
                                        WC_Pace_Logger::log("Convert " . $order->get_id() . " from " . $order->get_status() . "$expired_status");
                                        $order->set_status($expired_status);
                                        $order->save();
                                    }
                                    break;
                            }
                        }
                    }
                }
            }
        }
    }
}
