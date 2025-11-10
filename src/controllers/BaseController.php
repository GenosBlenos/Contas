<?php

require_once __DIR__ . '/../includes/helpers.php';

abstract class BaseController {
    protected $model;
    protected $moduleName;

    public function __construct($model, string $moduleName) {
        $this->model = $model;
        $this->moduleName = $moduleName;
    }

    /**
     * Define os campos esperados do POST e seus valores padrão.
     * @return array
     */
    abstract protected function getFields(): array;

    public function index() {
        try {
            $data = [];
            $filtros = [];
            
            // Mapeamento de filtros GET para colunas do DB
            $filtroMap = [
                'filtro_secretaria' => 'secretaria',
                'filtro_tipo_consumo' => 'tipo_consumo',
                'filtro_num_instalacao' => 'num_instalacao',
                'filtro_data_vencimento' => 'data_vencimento'
            ];

            foreach ($filtroMap as $param => $coluna) {
                if (!empty($_GET[$param])) {
                    $filtros[$coluna] = $_GET[$param];
                }
            }

            $data['registros'] = $this->model->buscarComFiltros($filtros) ?? [];

            // total pendente (método padrão)
            $data['totalPendente'] = method_exists($this->model, 'getTotalPendente') ? $this->model->getTotalPendente() : 0;

            // mediaConsumo: suporta diferentes formas de retorno entre modelos
            $mediaConsumo = 0;
            if (method_exists($this->model, 'getMediaConsumo')) {
                $mc = $this->model->getMediaConsumo();
                if (is_array($mc)) {
                    // Tenta extrair um valor numérico conhecido
                    if (isset($mc['media_minutos'])) {
                        $mediaConsumo = (float)$mc['media_minutos'];
                    } elseif (isset($mc['media_valor'])) {
                        $mediaConsumo = (float)$mc['media_valor'];
                    } elseif (isset($mc['media_dados'])) {
                        $mediaConsumo = (float)$mc['media_dados'];
                    } else {
                        // Pega o primeiro valor numérico encontrado
                        foreach ($mc as $v) {
                            if (is_numeric($v)) { $mediaConsumo = (float)$v; break; }
                        }
                    }
                } else {
                    $mediaConsumo = (float)$mc;
                }
            } elseif (method_exists($this->model, 'getMediaMensal')) {
                // Alguns modelos usam nome diferente (ex: Semparar)
                $mm = $this->model->getMediaMensal();
                if (is_array($mm)) {
                    // Busca médias de transações ou valor
                    $mediaConsumo = (float)($mm['media_transacoes'] ?? $mm['media_valor'] ?? 0);
                    // Expor também mediaTransacoes quando disponível (usada por Semparar view)
                    if (isset($mm['media_transacoes'])) {
                        $data['mediaTransacoes'] = $mm['media_transacoes'];
                    }
                } else {
                    $mediaConsumo = (float)$mm;
                }
            }
            $data['mediaConsumo'] = $mediaConsumo;

            // consumo mensal: tenta métodos com nomes alternativos
            if (method_exists($this->model, 'getConsumoMensal')) {
                $data['consumoMensal'] = $this->model->getConsumoMensal() ?? [];
            } elseif (method_exists($this->model, 'getConsumoMensalizado')) {
                $data['consumoMensal'] = $this->model->getConsumoMensalizado() ?? [];
            } else {
                $data['consumoMensal'] = [];
            }

            // total anual / outros indicadores opcionais
            if (method_exists($this->model, 'getTotalAnual')) {
                $data['totalAnual'] = $this->model->getTotalAnual();
            }
            
            return $data;
        } catch (Exception $e) {
            error_log("Erro no método index do controller {$this->moduleName}: " . $e->getMessage());
            $_SESSION['error'] = "Erro ao carregar os dados. Por favor, tente novamente.";
            return [
                'registros' => [],
                'totalPendente' => 0,
                'mediaConsumo' => 0,
                'consumoMensal' => []
            ];
        }
    }

    public function edit($id) {
        return $this->model->find($id);
    }

    public function store() {
        if (!$this->validateCSRF()) {
            return;
        }

        $data = $this->prepareDataFromPost($_POST);
        $data['criado_por'] = $_SESSION['usuario_id'];

        if ($this->model->create($data)) {
            gerarLog(ucfirst($this->moduleName) . ' cadastrado(a)', json_encode($data));
            $_SESSION['success'] = "Registro criado com sucesso!";
        } else {
            gerarLog('Erro cadastro ' . $this->moduleName, json_encode($data));
            $_SESSION['error'] = "Erro ao criar registro.";
        }
        $this->redirect();
    }

    public function update() {
        if (!$this->validateCSRF()) {
            return;
        }

        $id = sanitizeInput($_POST['id']);
        $data = $this->prepareDataFromPost($_POST);

        if ($this->model->update($id, $data)) {
            gerarLog(ucfirst($this->moduleName) . ' atualizado(a)', json_encode($data));
            $_SESSION['success'] = "Registro atualizado com sucesso!";
        } else {
            gerarLog('Erro atualização ' . $this->moduleName, json_encode($data));
            $_SESSION['error'] = "Erro ao atualizar registro.";
        }
        $this->redirect();
    }

    public function destroy() {
        // Adicionar validação CSRF para destroy também é uma boa prática
        $id = sanitizeInput($_POST['id']);

        if ($this->model->delete($id)) {
            $_SESSION['success'] = "Registro excluído com sucesso!";
        } else {
            $_SESSION['error'] = "Erro ao excluir registro.";
        }
        $this->redirect();
    }

    protected function prepareDataFromPost(array $postData): array {
        $data = [];
        $fields = $this->getFields();
        foreach ($fields as $field => $defaultValue) {
            $data[$field] = sanitizeInput($postData[$field] ?? $defaultValue);
        }
        return $data;
    }

    protected function validateCSRF(): bool {
        if (!isset($_POST['csrf_token']) || !validarCSRFToken($_POST['csrf_token'])) {
            gerarLog('Falha CSRF', 'Tentativa de ação em ' . $this->moduleName . ' sem token CSRF válido.');
            $_SESSION['error'] = 'Erro de segurança. Tente novamente.';
            $this->redirect();
            return false;
        }
        return true;
    }

    protected function redirect(?string $location = null): void {
        $location = $location ?? $this->moduleName . '.php';
        header("Location: {$location}");
        exit;
    }
}
