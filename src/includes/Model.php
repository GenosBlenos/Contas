<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';

abstract class Model {
    protected $pdo;
    protected $table;
    protected $fillable = [];
    protected $orderBy = 'id'; // coluna padrão para ordenação
    protected $logger;

    public function __construct() {
        try {
            // Get the PDO connection from our Database wrapper class
            $this->pdo = Database::getInstance()->getConnection();
            $this->logger = Logger::getInstance();
            
            if (!$this->pdo) {
                throw new Exception("Não foi possível estabelecer conexão com o banco de dados");
            }
        } catch (Exception $e) {
            if (isset($this->logger)) {
                $this->logger->error("Erro ao instanciar Model: " . $e->getMessage());
            } else {
                error_log("Erro ao instanciar Model: " . $e->getMessage());
            }
            throw $e;
        }
    }

    public function create(array $data) {
        $data = $this->filterData($data);
        if (empty($data)) {
            return false;
        }
        $fields = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$this->table} ({$fields}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        
        if ($stmt->execute(array_values($data))) {
            return $this->pdo->lastInsertId();
        }
        
        return false;
    }

    public function update($id, array $data) {
        $data = $this->filterData($data);
        if (empty($data)) {
            return false;
        }
        $fields = array_map(fn($field) => "{$field} = ?", array_keys($data));
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        
        $values = array_values($data);
        $values[] = $id;
        
        return $stmt->execute($values);
    }

    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }

    public function find($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function all() {
        $orderBy = $this->isAllowedField($this->orderBy) ? $this->orderBy : 'id';
        $sql = "SELECT * FROM {$this->table} ORDER BY {$orderBy} DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function filterData(array $data) {
        return array_intersect_key($data, array_flip($this->fillable));
    }

    public function where($field, $value) {
        if (!$this->isAllowedField($field)) {
            throw new \Exception("Campo de busca inválido: " . htmlspecialchars($field));
        }
        $sql = "SELECT * FROM {$this->table} WHERE {$field} = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$value]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findWhere(array $conditions) {
        if (empty($conditions)) {
            return [];
        }

        $where = [];
        foreach (array_keys($conditions) as $field) {
            if (!$this->isAllowedField($field)) {
                throw new \Exception("Campo de busca inválido: " . htmlspecialchars($field));
            }
            $where[] = "{$field} = ?";
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($conditions));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function isAllowedField(string $field): bool {
        $defaultAllowed = ['id', 'criado_em', 'atualizado_em']; 
        $allowed = array_merge($this->fillable, $defaultAllowed);
        if (!empty($this->orderBy)) {
            $allowed[] = $this->orderBy;
        }
        return in_array($field, array_unique($allowed));
    }

    public function sum(string $column, array $conditions = []) {
        try {
            if (!$this->isAllowedField($column)) {
                throw new \Exception("Coluna inválida para soma: " . htmlspecialchars($column));
            }
    
            $sql = "SELECT SUM({$column}) as total FROM {$this->table}";
    
            $where = [];
            $params = [];
            if (!empty($conditions)) {
                foreach ($conditions as $field => $value) {
                    if (!$this->isAllowedField($field)) {
                        throw new \Exception("Campo de condição inválido: " . htmlspecialchars($field));
                    }
                    $where[] = "{$field} = ?";
                    $params[] = $value;
                }
                $sql .= " WHERE " . implode(' AND ', $where);
            }
    
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (float)$result['total'] : 0;
        } catch (Exception $e) {
            $this->logger->error("Erro ao calcular a soma da coluna {$column}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }

    /**
     * Calcula a média de uma coluna com base em condições.
     *
     * @param string $column O nome da coluna para calcular a média.
     * @param array $where Um array associativo de condições WHERE (ex: ['status' => 'pendente']).
     * @return float A média calculada ou 0 em caso de erro ou nenhum resultado.
     */
    public function avg(string $column, array $where = []): float
    {
        try {
            if (!$this->isAllowedField($column)) {
                throw new \Exception("Coluna inválida para média: " . htmlspecialchars($column));
            }

            $sql = "SELECT AVG({$column}) as average FROM {$this->table}";
            $params = [];
    
            if (!empty($where)) {
                $conditions = [];
                foreach ($where as $key => $value) {
                    if (!$this->isAllowedField($key)) {
                        throw new \Exception("Campo de condição inválido: " . htmlspecialchars($key));
                    }
                    $conditions[] = "{$key} = ?";
                    $params[] = $value;
                }
                $sql .= " WHERE " . implode(' AND ', $conditions);
            }
    
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
            return $result ? (float)$result['average'] : 0;
        } catch (Exception $e) {
            $this->logger->error("Erro ao calcular a média da coluna {$column}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }
}
