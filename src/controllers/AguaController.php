<?php
require_once __DIR__ . '/../models/Agua.php';
require_once __DIR__ . '/BaseController.php';

class AguaController extends BaseController {

    protected $viewData = [];

    public function __construct() {
        $model = new Agua();
        parent::__construct($model, 'agua');
    }

    public function index() {
        // Chama o método index do BaseController para obter a lógica padrão (filtros, paginação, etc.)
        parent::index();

        // Calcula os totais específicos para a página de agua
        $totalPendente = $this->model->sum('valor_fatura', ['status' => 'pendente']);
        $mediaConsumo = $this->model->avg('consumo_m3');
        $totalMulta = $this->model->sum('multa');

        // Adiciona os novos dados aos dados que já serão enviados para a view
        $this->viewData['totalPendente'] = $totalPendente;
        $this->viewData['mediaConsumo'] = $mediaConsumo;
        $this->viewData['totalMulta'] = $totalMulta;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFields(): array {
        return [
            'mes' => null,
            'unidade' => null,
            'consumo_m3' => 0,
            'total_a_pagar' => 0,
            'multa' => 0,
            'total' => 0,
            'status' => 'pendente',
            'data_vencimento' => null,
            'secretaria' => null,
            'tipo_consumo' => null,
            'num_instalacao' => null,
            'observacao' => null,
        ];
    }
}