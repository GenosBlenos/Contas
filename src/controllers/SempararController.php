<?php
require_once __DIR__ . '/../models/Semparar.php';
require_once __DIR__ . '/BaseController.php';

class SempararController extends BaseController {
    public function __construct() {
        $model = new Semparar();
        parent::__construct($model, 'semparar');
    }

    public function index() {
        // Chama o método index do BaseController para obter a lógica padrão (filtros, paginação, etc.)
        parent::index();

        // Calcula os totais específicos para a página de Sem Parar
        $totalPendente = $this->model->sum('total_a_pagar', ['status' => 'pendente']);
        $totalMulta = $this->model->sum('multa');
        $mediaPassagens = $this->model->avg('passagens');

        // Adiciona os novos dados aos dados que já serão enviados para a view
        $this->viewData['totalPendente'] = $totalPendente;
        $this->viewData['totalMulta'] = $totalMulta;
        $this->viewData['mediaPassagens'] = $mediaPassagens;
    }

    protected function getFields(): array {
        return [
            'mes' => null,
            'unidade' => null,
            'total_a_pagar' => 0,
            'multa' => 0,
            'total' => 0,
            'status' => 'pendente',
            'data_vencimento' => null,
            'secretaria' => null,
            'placa_veiculo' => null,
            'passagens' => 0,
            'observacao' => null
        ];
    }
}