<?php
require_once __DIR__ . '/../includes/Model.php';

class Internet extends Model
{
    protected $table = 'internet';
    protected $orderBy = 'id_internet';
    protected $fillable = [
        'mes',
        'unidade',
        'valor_fatura',
        'multa',
        'total',
        'status',
        'data_vencimento',
        'secretaria',
        'tipo_plano',
        'velocidade',
        'ip_fixo',
        'num_contrato',
        'observacao',
        'criado_por',
        'atualizado_por'
    ];

    // create, update, delete, find are inherited from Model

    public function buscarComFiltros($filtros = []) {
        try {
            $where = [];
            $params = [];
            
            if (!empty($filtros['secretaria'])) {
                $where[] = 'secretaria LIKE ?';
                $params[] = '%' . $filtros['secretaria'] . '%';
            }
            
            if (!empty($filtros['tipo_plano'])) {
                $where[] = 'tipo_plano LIKE ?';
                $params[] = '%' . $filtros['tipo_plano'] . '%';
            }
            
            if (!empty($filtros['num_contrato'])) {
                $where[] = 'num_contrato LIKE ?';
                $params[] = '%' . $filtros['num_contrato'] . '%';
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
                        velocidade,
                        SUM(valor_fatura) as total_valor
                    FROM {$this->table}
                    GROUP BY mes, velocidade
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

    public function getMediaVelocidade() {
        try {
            $sql = "SELECT AVG(
                        CASE 
                            WHEN velocidade LIKE '%Gbps%' OR velocidade LIKE '%Giga%' 
                            THEN CAST(REPLACE(REPLACE(velocidade, 'Gbps', ''), 'Giga', '') AS DECIMAL(10,2)) * 1000
                            WHEN velocidade LIKE '%Mbps%' OR velocidade LIKE '%Mega%'
                            THEN CAST(REPLACE(REPLACE(velocidade, 'Mbps', ''), 'Mega', '') AS DECIMAL(10,2))
                            ELSE 0
                        END
                    ) as media_velocidade
                    FROM {$this->table}
                    WHERE data_vencimento >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)";

            $stmt = $this->pdo->query($sql);
            $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            return $result ? $result['media_velocidade'] : 0;
        } catch (Exception $e) {
            $this->logger->error("Erro ao calcular média de velocidade", [
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