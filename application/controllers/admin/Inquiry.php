<?php

use app\services\utilities\Date;

defined('BASEPATH') or exit('No direct script access allowed');

class Inquiry extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('inquiries_model');
    }

    /* List all knowledgebase articles */
    public function index()
    {
        $data['results']     = $this->inquiries_model->get();
        $this->load->view('admin/inquiry/view', $data);
        return;
    }
}
