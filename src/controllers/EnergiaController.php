<?php
require_once __DIR__ . '/../models/Energia.php';
require_once __DIR__ . '/BaseController.php';

class EnergiaController extends BaseController {

    protected $viewName = 'energia';
    protected $model;

    public function __construct() {
        $model = new Energia();
        parent::__construct($model, 'energia');
    }

    public function index() {
        // Chama o método index do BaseController para obter a lógica padrão (filtros, paginação, etc.)
        $data = parent::index();

        // Calcula os totais específicos para a página de energia
        $totalPendente = $this->model->sum('valor_final', ['status' => 'pendente']);
        $mediaConsumo = $this->model->avg('consumo_kwh');
        $totalMultaAtraso = $this->model->sum('multa_atraso');

        // Adiciona os novos dados aos dados que já serão enviados para a view
        $data['totalPendente'] = $totalPendente;
        $data['mediaConsumo'] = $mediaConsumo;
        $data['totalMultaAtraso'] = $totalMultaAtraso;

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFields(): array {
        return [
            'mes' =>  null,
            'unidade' => null,
            'consumo_kwh' => 0,
            'total_a_pagar' => 0,
            'total' => 0,
            'status' => 'pendente',
            'data_vencimento' => null,
            'secretaria' => null,
            'tipo_consumo' => null,
            'num_instalacao' => null,
            'observacao' => null,
            'demanda_contratada' => null,
            'demanda_registrada' => null,
            'demanda_faturada' => null,
            'fator_potencia' => null,
            'grupo_tarifario' => null,
            'fat_impostos' => 0,
            'fat_distribuidora' => 0,
            'multa_atraso' => 0,
            'imposto_retido_total' => 0,
            'imposto_retido_irrf' => 0,
            'valor_final' => 'total_a_pagar' + 'multa_atraso' + 'imposto_retido_total' + 'fat_impostos'
        ];
    }

    protected function prepareDataFromPost(array $postData): array {
         $data = parent::prepareDataFromPost($postData);
         $data['valor_final'] = (float)($data['total_a_pagar'] ?? 0) + (float)($data['multa_atraso'] ?? 0);

        // Example:  Use num_instalacao to populate the 'unidade' field
        $num_instalacao = $data['num_instalacao'] ?? null;
        if ($num_instalacao) {
            //  Here, you would ideally query a 'unidades' table to get the unidade name
            //  based on the num_instalacao.  Since we don't have a unidades table,
            //  I'm just setting the unidade to the num_instalacao for demonstration.
            $data['unidade'] = $num_instalacao;
        } else {
            $data['unidade'] = null; // Or some default value
        }

         return $data;
    }
}