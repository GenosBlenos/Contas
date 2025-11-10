<?php
require_once __DIR__ . '/../includes/Model.php';

class Agua extends Model {
    protected $table = 'agua';
    protected $orderBy = 'id_agua';
    protected $fillable = [
        'mes',
        'unidade',
        'consumo_m3',
        'valor_fatura',
        'multa',
        'total',
        'status',
        'vencimento',
        'secretaria',
        'tipo_consumo',
        'num_instalacao',
        'observacao',
        'criado_por',
        'atualizado_por',
        'unidade_id'
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
            
            if (!empty($filtros['vencimento'])) {
                $where[] = 'vencimento = ?';
                $params[] = $filtros['vencimento'];
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
                        DATE_FORMAT(vencimento, '%Y-%m') as mes,
                        SUM(consumo_m3) as total_consumo,
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
            $sql = "SELECT AVG(consumo_m3) as media_consumo
                    FROM {$this->table}
                    WHERE vencimento >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)";

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

    public function getContasAtrasadas() {
        try {
            $sql = "SELECT COUNT(*) as total
                    FROM {$this->table}
                    WHERE vencimento < CURDATE() AND status = 'pendente'";

            $stmt = $this->pdo->query($sql);
            $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            return $result ? $result['total'] : 0;
        } catch (Exception $e) {
            $this->logger->error("Erro ao buscar contas atrasadas", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }
}