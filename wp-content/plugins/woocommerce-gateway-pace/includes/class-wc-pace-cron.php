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
        self::handle_add_cron();
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
    static function check_order_manually_update($order_id)
    {
        $notes = wc_get_order_notes(['order_id' => $order_id]) ? wc_get_order_notes(['order_id' => $order_id]) : [];
        foreach ($notes as $note) {
            if ($note->added_by != 'system') {
                return false;
            }
        }
        return true;
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
                        if ($order->get_status() != "completed" && $order->get_status() != "processing") {

                            if (WC_Pace_Cron::check_order_manually_update($value->referenceID)) {
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
}
