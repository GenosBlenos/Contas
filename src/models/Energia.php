<?php
require_once __DIR__ . '/../includes/Model.php';

class Energia extends Model {
    protected $table = 'energia';
    protected $orderBy = 'id_energia';
    protected $fillable = [
        'mes',
        'unidade',
        'consumo_kwh',
        'valor_fatura',
        'multa',
        'total',
        'status',
        'data_vencimento',
        'secretaria',
        'tipo_consumo',
        'num_instalacao',
        'observacao',
        'demanda_contratada',
        'demanda_registrada',
        'demanda_faturada',
        'fator_potencia',
        'grupo_tarifario',
        'criado_por',
        'atualizado_por',
        'fat_impostos',
        'fat_distribuidora',
        'multa_atraso',
        'imposto_retido_total',
        'imposto_retido_irrf',
        'valor_original'
    ];
    public function buscarComFiltros($filtros = []) {
        try {
            $where = [];
            $params = [];
            
            if (!empty($filtros['secretaria'])) {
                $where[] = 'secretaria LIKE ?';
                $params[] = '%' . $filtros['secretaria'] . '%';
            }
            
            if (!empty($filtros['tipo_consumo'])) {
                $where[] = 'tipo_consumo LIKE ?';
                $params[] = '%' . $filtros['tipo_consumo'] . '%';
            }
            
            if (!empty($filtros['num_instalacao'])) {
                $where[] = 'num_instalacao LIKE ?';
                $params[] = '%' . $filtros['num_instalacao'] . '%';
            }
            
            if (!empty($filtros['data_vencimento'])) {
                $where[] = 'data_vencimento = ?';
                $params[] = $filtros['data_vencimento'];
            }

            $sql = "SELECT * FROM {$this->table}";
            if ($where) {
                $sql .= " WHERE " . implode(' AND ', $where);
            }
            $sql .= " ORDER BY {$this->orderBy} DESC";
            
            $stmt = $this->pdo->prepare($sql);
            if ($stmt) {
                $stmt->execute($params);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            return [];
        } catch (Exception $e) {
            $this->logger->error("Erro ao buscar registros com filtros", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    public function getConsumoMensal() {
        try {
            $sql = "SELECT
                        DATE_FORMAT(data_vencimento, '%Y-%m') as mes,
                        SUM(consumo_kwh) as total_consumo,
                        SUM(valor_fatura) as total_valor
                    FROM {$this->table}
                    GROUP BY mes
                    ORDER BY mes DESC
                    LIMIT 12";

            $stmt = $this->pdo->query($sql);
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Exception $e) {
            $this->logger->error("Erro ao buscar consumo mensal", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    public function getMediaConsumo() {
        try {
            $sql = "SELECT AVG(consumo_kwh) as media_consumo
                    FROM {$this->table}
                    WHERE data_vencimento >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)";

            $stmt = $this->pdo->query($sql);
            $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            return $result ? $result['media_consumo'] : 0;
        } catch (Exception $e) {
            $this->logger->error("Erro ao calcular média de consumo", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }

    public function getTotalPendente() {
        try {
            $sql = "SELECT SUM(valor_fatura) as total 
                    FROM {$this->table} 
                    WHERE status = 'pendente'";
                    
            $stmt = $this->pdo->query($sql);
            $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            return $result ? $result['total'] : 0;
        } catch (Exception $e) {
            $this->logger->error("Erro ao calcular total pendente", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }
}