<?php
require_once __DIR__ . '/../models/Telefone.php';
require_once __DIR__ . '/BaseController.php';

class TelefoneController extends BaseController {
    public function __construct() {
        $model = new Telefone();
        parent::__construct($model, 'telefone');
    }

    public function index() {
        // Chama o método index do BaseController para obter a lógica padrão (filtros, paginação, etc.)
        parent::index();

        // Calcula os totais específicos para a página de Telefone
        $totalPendente = $this->model->sum('valor_fatura', ['status' => 'pendente']);
        $totalMulta = $this->model->sum('multa');
        $mediaMinutos = $this->model->avg('minutos_utilizados');

        // Adiciona os novos dados aos dados que já serão enviados para a view
        $this->viewData['totalPendente'] = $totalPendente;
        $this->viewData['totalMulta'] = $totalMulta;
        $this->viewData['mediaMinutos'] = $mediaMinutos;
    }

    protected function getFields(): array {
        return [
            'mes' => null,
            'unidade' => null,
            'numero_linha' => null,
            'total_a_pagar' => 0,
            'multa' => 0,
            'total' => 0,
            'status' => 'pendente',
            'data_vencimento' => null,
            'secretaria' => null,
            'tipo_servico' => null,
            'plano' => null,
            'minutos_utilizados' => 0,
            'dados_utilizados' => 0,
            'observacao' => null
        ];
    }
}
