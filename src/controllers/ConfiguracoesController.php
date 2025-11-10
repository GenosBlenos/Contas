<?php
require_once __DIR__ . '/../models/Configuracao.php';
class ConfiguracoesController {
    public function index() {
        $model = new Configuracao();
        return $model->all();
    }
}
