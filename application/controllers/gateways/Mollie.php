<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Mollie extends App_Controller
{
    /**
     * Show message to the customer whether the payment is successfully
     *
     * @return mixed
     */
    public function verify_payment()
    {
        $invoiceid = $this->input->get('invoiceid');
        $hash      = $this->input->get('hash');
        check_invoice_restrictions($invoiceid, $hash);

        $this->db->where('id', $invoiceid);
        $invoice = $this->db->get(db_prefix() . 'invoices')->row();

        $oResponse = $this->mollie_gateway->fetch_payment($invoice->token);

        if ($oResponse->isSuccessful()) {
            $data = $oResponse->getData();

            if ($data['status'] == 'paid') {
                set_alert('success', _l('online_payment_recorded_success'));
            } else {
                set_alert('danger', $data['details']['failureMessage'] ?? '');
            }
        } else {
            set_alert('danger', $oResponse->getMessage());
        }

        redirect(site_url('invoice/' . $invoice->id . '/' . $invoice->hash));
    }

    /**
     * Handle the mollie webhook
     *
     * @param  string $key
     *
     * @return mixed
     */
    public function webhook($key = null)
    {
        $ip = $this->input->ip_address();

        // https://help.mollie.com/hc/en-us/articles/213470829-Which-IP-addresses-does-Mollie-use-From-which-IP-range-can-I-expect-requests-
        $fixedIps = [
            '146.148.31.21', '23.251.137.244',
            '34.89.231.130', '34.90.137.245', '34.90.10.225',
            '35.204.34.167', '35.204.72.248', '35.246.254.59',
        ];

        if (!$key && // Backward compatibility
                !ip_in_range($ip, '87.233.229.26-87.233.229.27') &&
                !ip_in_range($ip, '87.233.217.240-87.233.217.255') &&
                !in_array($ip, $fixedIps)) {
            return false;
        }

        $trans_id  = $this->input->post('id');
        $oResponse = $this->mollie_gateway->fetch_payment($trans_id);

        if ($oResponse->isSuccessful()) {
            $data = $oResponse->getData();

            // log_message('error', json_encode($data));

            // When key is not passed is checked at the top with the ip range
            if (!$key || $data['metadata']['webhookKey'] == $key) {
                if ($data['status'] == 'paid') {
                    $this->db->where('transactionid', $trans_id);
                    $this->db->where('invoiceid', $data['metadata']['order_id']);
                    $payment = $this->db->get(db_prefix() . 'invoicepaymentrecords')->row();

                    if ($data['amount']['value'] == $data['amountRemaining']['value']) {
                        // New payment
                        $this->mollie_gateway->addPayment([
                                'amount'        => $data['amount']['value'],
                                'invoiceid'     => $data['metadata']['order_id'],
                                'paymentmethod' => $data['method'],
                                'transactionid' => $trans_id,
                          ]);
                    } elseif ($data['amount']['value'] == $data['amountRefunded']['value']) {
                        // log_message('error', 'Fully refunded');
                        $this->db->where('id', $payment->id);
                        $this->db->delete(db_prefix() . 'invoicepaymentrecords');
                        update_invoice_status($data['metadata']['order_id']);
                    } elseif ($data['amount']['value'] != $data['amountRemaining']['value']) {
                        // log_message('error', 'Partially refunded');
                        $this->db->where('id', $payment->id);
                        $this->db->update(db_prefix() . 'invoicepaymentrecords', ['amount' => $data['amountRemaining']['value']]);
                        update_invoice_status($data['metadata']['order_id']);
                    }
                }
            }
        }
    }
}
