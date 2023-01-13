<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Invoice extends ClientsController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library("session");
        $this->load->helper('url');
    }

    public function index($id, $hash)
    {
        check_invoice_restrictions($id, $hash);
        $invoice = $this->invoices_model->get($id);

        $invoice = hooks()->apply_filters('before_client_view_invoice', $invoice);

        if (!is_client_logged_in()) {
            load_client_language($invoice->clientid);
        }

        // Handle Invoice PDF generator
        if ($this->input->post('invoicepdf')) {
            try {
                $pdf = invoice_pdf($invoice);
            } catch (Exception $e) {
                echo $e->getMessage();
                die;
            }

            $invoice_number = format_invoice_number($invoice->id);
            $companyname    = get_option('invoice_company_name');
            if ($companyname != '') {
                $invoice_number .= '-' . mb_strtoupper(slug_it($companyname), 'UTF-8');
            }
            $pdf->Output(mb_strtoupper(slug_it($invoice_number), 'UTF-8') . '.pdf', 'D');
            die();
        }

        // Handle $_POST payment
        if ($this->input->post('make_payment')) {
            // echo "asdasdasd";
            // return;
            $this->load->model('payments_model');
            if (!$this->input->post('paymentmode')) {
                set_alert('warning', _l('invoice_html_payment_modes_not_selected'));
                redirect(site_url('invoice/' . $id . '/' . $hash));
            } elseif ((!$this->input->post('amount') || $this->input->post('amount') == 0) && get_option('allow_payment_amount_to_be_modified') == 1) {
                set_alert('warning', _l('invoice_html_amount_blank'));
                redirect(site_url('invoice/' . $id . '/' . $hash));
            }
            $this->payments_model->process_payment($this->input->post(), $id);
        }

        if ($this->input->post('paymentpdf')) {
            $payment = $this->payments_model->get($this->input->post('paymentpdf'));
            // Confirm that the payment is related to the invoice.
            if ($payment->invoiceid == $id) {
                $payment->invoice_data = $this->invoices_model->get($payment->invoiceid);
                $paymentpdf            = payment_pdf($payment);
                $paymentpdf->Output(mb_strtoupper(slug_it(_l('payment') . '-' . $payment->paymentid), 'UTF-8') . '.pdf', 'D');
                die;
            }
        }

        $this->app_scripts->theme('sticky-js', 'assets/plugins/sticky/sticky.js');
        $this->load->library('app_number_to_word', [
            'clientid' => $invoice->clientid,
        ], 'numberword');
        $this->load->model('payment_modes_model');
        $this->load->model('payments_model');
        $data['payments']      = $this->payments_model->get_invoice_payments($id);
        $data['payment_modes'] = $this->payment_modes_model->get();
        $data['title']         = format_invoice_number($invoice->id);
        $this->disableNavigation();
        $this->disableSubMenu();
        $data['hash']      = $hash;
        $data['invoice']   = hooks()->apply_filters('invoice_html_pdf_data', $invoice);
        $data['bodyclass'] = 'viewinvoice';
        $this->data($data);
        $this->view('invoicehtml');
        add_views_tracking('invoice', $id);
        hooks()->do_action('invoice_html_viewed', $id);
        no_index_customers_area();
        $this->layout();
    }

    public function addCardInit($invoice_id, $invoice_hash)
    {

        $stripe_secret_key = get_instance()->encryption->decrypt(get_option('paymentmethod_stripe_api_secret_key'));
        $stripe_publishable_key = get_option('paymentmethod_stripe_api_publishable_key');

        // $stripe_secret_key = $this->config->item('stripe_secret');
        // $stripe_publishable_key = $this->config->item('stripe_key');

        // Commenting for now because don't know where to get dynamic user details
        // $invoice = $this->invoices_model->get($invoice_id);

        \Stripe\Stripe::setApiKey($stripe_secret_key);
        $stripe = new \Stripe\StripeClient($stripe_secret_key);

        $customer = \Stripe\Customer::create([
            'name' => 'Dynamic name here ',
            'email' => 'dynamic@email.here',
        ]);

        $setupIntent =  $stripe->setupIntents->create(
            [
                'customer' => $customer->id,
                'payment_method_types' => ['card'],
            ]
        );

        $data = [
            'stripe_secret_key' => $stripe_secret_key,
            'stripe_publishable_key' => $stripe_publishable_key,
            'client_secret' => $setupIntent->client_secret,
            'customer_id' => $customer->id,
            // 'invoice_id' => $invoice_id,
            // 'invoice_hash' => $invoice_hash,
            'return_url' => base_url('card-added/' . $invoice_id . '/' . $invoice_hash)
        ];

        return $this->load->view('\themes\perfex\views\invoice_payment_create_customer', $data);
        // $this->data($data);
        // $this->view('invoice_payment_create_customer',$data);
        // $this->layout();
        // print_r($setupIntent);




        // $invoice_id = $this->input->post('invoice_id');
        // $invoice = $this->invoices_model->get($invoice_id);

        // require_once('application/libraries/stripe-php/init.php');

        // \Stripe\Stripe::setApiKey($this->config->item('stripe_secret'));
        // $stripeChargeData = [
        //     "amount" => 100 * 120,
        //     "currency" => "usd",
        //     "source" => $this->input->post('stripeToken'),
        //     "description" => "Payment for invoice". $invoice->id,

        // ];
        // $response = \Stripe\Charge::create($stripeChargeData);
        // echo "<pre>";
        // print_r([
        //     // "INVOICE RESPONSE " => $invoice,
        //     "PAYMENT RESPONE " => $response,
        // ]);
        // return;
        // if($response['stauts'] == "succeeded"){
        //     $this->session->set_flashdata('success', 'Payment has been successful.');
        // }else{
        //     $this->session->set_flashdata('success', 'Payment has been successful.');
        // }


        // $this->payments_model->process_payment($this->input->post(), 2);
        // echo "<pre>";
        // print_r(
        //     $response
        // );
        // return;
        redirect($_SERVER['HTTP_REFERER']);
    }

    public function addCardComplete($invoice_id, $invoice_hash)
    {
        $stripe_secret_key = get_instance()->encryption->decrypt(get_option('paymentmethod_stripe_api_secret_key'));
        $stripe_publishable_key = get_option('paymentmethod_stripe_api_publishable_key');

        // $stripe_secret_key = $this->config->item('stripe_secret');
        // $stripe_publishable_key = $this->config->item('stripe_key');

        $setup_intent_id = $this->input->get('setup_intent');
        $setup_intent_client_secret = $this->input->get('setup_intent_client_secret');

        $invoice = $this->invoices_model->get($invoice_id);

        $stripe = new \Stripe\StripeClient($stripe_secret_key);
        $setup_intent = $stripe->setupIntents->retrieve(
            $setup_intent_id,
            []
        );

        $customer_id = $setup_intent->customer;

        $payment_mehods =  $stripe->paymentMethods->all(['customer' => $customer_id, 'type' => 'card']);

        $payment_method_id = $payment_mehods['data'][0]->id;

        // UPDATE CLIENT AND SAVE CEDENTIALS
        $dataToUpdate = [
            'stripe_customer_id' => $customer_id,
            'stripe_payment_method_id' => $payment_method_id,
        ];
        $where = [
            'userid' => $invoice->client->userid
        ];
        $status = $this->db->update('tblclients', $dataToUpdate, $where);
        if ($status) {
            $this->session->set_flashdata('success', 'Card added successfully.');
        } else {
            $this->session->set_flashdata('error', 'Something went wrong please try again.');
        }
        $redirecting_route = "invoice/" . $invoice_id . '/' . $invoice_hash;
        redirect($redirecting_route);
    }

    public function paymentProcess($invoice_id, $invoice_hash)
    {

        $stripe_secret_key = get_instance()->encryption->decrypt(get_option('paymentmethod_stripe_api_secret_key'));
        $stripe_publishable_key = get_option('paymentmethod_stripe_api_publishable_key');

        // $stripe_secret_key = $this->config->item('stripe_secret');
        // $stripe_publishable_key = $this->config->item('stripe_key');

        $successUrl = site_url('gateways/stripe/success/' . $invoice_id . '/' . $invoice_hash);
        $cancelUrl  = site_url('invoice/' . $invoice_id . '/' . $invoice_hash);

        $invoice = $this->invoices_model->get($invoice_id);
        $client = $invoice->client;

        // CREATE PAYMENT USING STRIP FUTURE PAYMENT
        \Stripe\Stripe::setApiKey($stripe_secret_key);
        $data = [
            "invoiceid" => $invoice_id,
            "invoice" => $invoice,
        ];

        // $this->stripe_gateway->process_payment($data);
        // return;
        try {
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $invoice->total * 100,
                'currency' => 'usd',
                'customer' => $client->stripe_customer_id,
                'payment_method' => $client->stripe_payment_method_id,
                'off_session' => true,
                'confirm' => true,
            ]);
            // MARK INVOICE AS PAID
            $this->db->update('tblinvoices', ['status ' => 2], ['id' => $invoice_id]);

            // ADD PAYMENT RECORD`
            $this->db->insert('tblinvoicepaymentrecords', [
                'invoiceid' => $invoice_id,
                'amount' => $invoice->total,
                'paymentmode' => 'stripe',
                'date' => date('Y-m-d'),
                'daterecorded' => date('Y-m-d H:i:s'),
                'transactionid' => $paymentIntent['id']
            ]);
            randomizeMerchant();
            redirect($successUrl);
        } catch (\Stripe\Exception\CardException $e) {
            // Error code will be authentication_required if authentication is needed
            echo 'Error code is:' . $e->getError()->code;
            $payment_intent_id = $e->getError()->payment_intent->id;
            $payment_intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
            redirect($cancelUrl);
        }
    }
}
