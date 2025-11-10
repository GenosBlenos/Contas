<?php
require_once __DIR__ . '/../models/Kpi.php';
class KpisController {
    public function index() {
        $model = new Kpi();
        return $model->all();
    }
}
