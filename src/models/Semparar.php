<?php
require_once __DIR__ . '/../includes/Model.php';
class Semparar extends Model {
    protected $table = 'semparar';
    protected $orderBy = 'id_semparar';
    protected $fillable = [
        'mes',
        'unidade',
        'valor_fatura',
        'multa',
        'total',
        'status',
        'data_vencimento',
        'secretaria',
        'placa_veiculo',
        'num_tag',
        'num_eixos',
        'observacao',
        'criado_por',
        'atualizado_por'
    ];

    public function buscarComFiltros($filtros = []) {
        try {
            $where = [];
            $params = [];
            
            if (!empty($filtros['secretaria'])) {
                $where[] = 'secretaria LIKE ?';
                $params[] = '%' . $filtros['secretaria'] . '%';
            }
            
            if (!empty($filtros['placa_veiculo'])) {
                $where[] = 'placa_veiculo LIKE ?';
                $params[] = '%' . $filtros['placa_veiculo'] . '%';
            }
            
            if (!empty($filtros['num_tag'])) {
                $where[] = 'num_tag LIKE ?';
                $params[] = '%' . $filtros['num_tag'] . '%';
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
                        COUNT(*) as total_transacoes,
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

    public function getMediaMensal() {
        try {
            $sql = "SELECT 
                        AVG(valor_fatura) as media_valor,
                        COUNT(*)/COUNT(DISTINCT DATE_FORMAT(data_vencimento, '%Y-%m')) as media_transacoes
                    FROM {$this->table}
                    WHERE data_vencimento >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)";

            $stmt = $this->pdo->query($sql);
            $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            return $result ? [
                'media_valor' => $result['media_valor'] ?? 0,
                'media_transacoes' => $result['media_transacoes'] ?? 0
            ] : ['media_valor' => 0, 'media_transacoes' => 0];
        } catch (Exception $e) {
            $this->logger->error("Erro ao calcular média mensal", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['media_valor' => 0, 'media_transacoes' => 0];
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

    public function getTotalDoMes() {
        try {
            $sql = "SELECT SUM(valor_fatura) as total 
                    FROM {$this->table} 
                    WHERE MONTH(data_vencimento) = MONTH(CURRENT_DATE()) 
                    AND YEAR(data_vencimento) = YEAR(CURRENT_DATE())
                    AND status = 'pago'";
            
            $stmt = $this->pdo->query($sql);
            $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            return $result ? $result['total'] : 0;
        } catch (Exception $e) {
            $this->logger->error("Erro ao calcular total do mês", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }

    public function getMediaPorFatura() {
        try {
            $sql = "SELECT AVG(valor_fatura) as media 
                    FROM {$this->table} 
                    WHERE data_vencimento >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)";
            
            $stmt = $this->pdo->query($sql);
            $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            return $result ? $result['media'] : 0;
        } catch (Exception $e) {
            $this->logger->error("Erro ao calcular média por fatura", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }

    public function getTotalAnual() {
        try {
            $sql = "SELECT SUM(valor_fatura) as total 
                    FROM {$this->table} 
                    WHERE YEAR(data_vencimento) = YEAR(CURRENT_DATE())
                    AND status = 'pago'";
            
            $stmt = $this->pdo->query($sql);
            $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            return $result ? $result['total'] : 0;
        } catch (Exception $e) {
            $this->logger->error("Erro ao calcular total anual", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }
}