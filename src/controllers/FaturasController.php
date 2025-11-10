<?php
require_once __DIR__ . '/../models/Fatura.php';
class FaturasController {
    public function index() {
        $model = new Fatura();
        $faturas = $model->all();
        return $faturas;
    }
}
