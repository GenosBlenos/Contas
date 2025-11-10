<?php
require_once __DIR__ . '/../models/Internet.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../includes/header.php';

class InternetController extends BaseController {
    public function __construct() {
        $model = new Internet();
        parent::__construct($model, 'internet');
    }

    public function index() {
        try {
            $data = [];
            $filtros = [];
            
            // Mapeamento de filtros GET para colunas do DB
            $filtroMap = [
                'filtro_secretaria' => 'secretaria',
                'filtro_velocidade' => 'velocidade',
                'filtro_data_vencimento' => 'data_vencimento'
            ];

            foreach ($filtroMap as $param => $coluna) {
                if (!empty($_GET[$param])) {
                    $filtros[$coluna] = $_GET[$param];
                }
            }

            $data['registros'] = $this->model->buscarComFiltros($filtros);
            $data['totalPendente'] = $this->model->getTotalPendente();
            $data['mediaVelocidade'] = $this->model->getMediaVelocidade();
            $data['consumoMensal'] = $this->model->getConsumoMensal();
            $data['totalAnual'] = $this->model->getTotalAnual();
            
            return $data;
        } catch (Exception $e) {
            error_log("Erro no método index do controller Internet: " . $e->getMessage());
            $_SESSION['error'] = "Erro ao carregar os dados. Por favor, tente novamente.";
            return [
                'registros' => [],
                'totalPendente' => 0,
                'mediaVelocidade' => '0 Mbps',
                'consumoMensal' => [],
                'totalAnual' => 0
            ];
        }
    }

    protected function getFields(): array {
        return [
            'mes' => null,
            'unidade' => null,
            'valor_fatura' => 0,
            'multa' => 0,
            'total' => 0,
            'status' => 'pendente',
            'data_vencimento' => null,
            'secretaria' => null,
            'velocidade' => null,
            'dados_utilizados' => 0,
            'observacao' => null
        ];
    }
}
