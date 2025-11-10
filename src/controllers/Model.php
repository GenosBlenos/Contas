<?php
// File: includes/Model.php
class Model
{
    protected $table;
    protected $fillable = [];
    protected $orderBy = 'id';
    protected $db;

    public function __construct()
    {
        try {
            $this->db = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }

    public function all()
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY {$this->orderBy} DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function find($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function create($data)
    {
        $filteredData = array_intersect_key($data, array_flip($this->fillable));
        $columns = implode(', ', array_keys($filteredData));
        $placeholders = ':' . implode(', :', array_keys($filteredData));
        
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($filteredData);
    }

    public function update($id, $data)
    {
        $filteredData = array_intersect_key($data, array_flip($this->fillable));
        $setClause = [];
        
        foreach (array_keys($filteredData) as $column) {
            $setClause[] = "{$column} = :{$column}";
        }
        
        $setClause = implode(', ', $setClause);
        $filteredData['id'] = $id;
        
        $sql = "UPDATE {$this->table} SET {$setClause}, atualizado_em = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($filteredData);
    }

    public function delete($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
}