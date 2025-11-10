<?php
require_once __DIR__ . '/../includes/Model.php';

class Telefone extends Model {
    protected $table = 'telefone';
    protected $orderBy = 'id_telefone';
    protected $fillable = [
        'mes',
        'unidade',
        'numero_linha',
        'valor_fatura',
        'multa',
        'total',
        'status',
        'data_vencimento',
        'secretaria',
        'tipo_servico',
        'plano',
        'minutos_utilizados',
        'dados_utilizados',
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
            
            if (!empty($filtros['tipo_servico'])) {
                $where[] = 'tipo_servico LIKE ?';
                $params[] = '%' . $filtros['tipo_servico'] . '%';
            }
            
            if (!empty($filtros['numero_linha'])) {
                $where[] = 'numero_linha LIKE ?';
                $params[] = '%' . $filtros['numero_linha'] . '%';
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
                        SUM(minutos_utilizados) as total_minutos,
                        SUM(dados_utilizados) as total_dados,
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
            $sql = "SELECT 
                        AVG(minutos_utilizados) as media_minutos,
                        AVG(dados_utilizados) as media_dados
                    FROM {$this->table}
                    WHERE data_vencimento >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)";

            $stmt = $this->pdo->query($sql);
            $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            return $result ? [
                'media_minutos' => $result['media_minutos'] ?? 0,
                'media_dados' => $result['media_dados'] ?? 0
            ] : ['media_minutos' => 0, 'media_dados' => 0];
        } catch (Exception $e) {
            $this->logger->error("Erro ao calcular média de consumo", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['media_minutos' => 0, 'media_dados' => 0];
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