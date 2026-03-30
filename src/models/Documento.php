<?php
require_once __DIR__ . '/../includes/Model.php';
require_once __DIR__ . '/../includes/Database.php';

class Documento extends Model
{
    protected $table = 'documentos';
    protected $fillable = [
        'titulo', 
        'arquivo', 
        'modulo', 
        'mes_referencia', 
        'ano_referencia', 
        'codigo_instalacao', 
        'numero_fatura', 
        'vencimento', 
        'total_a_pagar'
    ];
    protected $orderBy = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    public function findByModule($module, $limit = null, $offset = null)
    {
        $pdo = Database::getInstance()->getConnection();
        // Some module tables don't have created timestamps; order by id
        $sql = "SELECT * FROM {$module} ORDER BY id DESC";
        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            if ($offset !== null) {
                $sql .= " OFFSET :offset";
            }
        }

        $stmt = $pdo->prepare($sql);
        if ($limit !== null) {
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            if ($offset !== null) {
                $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            }
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conta quantos registros existem em uma tabela de módulo
     */
    public function countByModule($module)
    {
        $pdo = Database::getInstance()->getConnection();
        $sql = "SELECT COUNT(*) as cnt FROM {$module}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Busca documentos por código de instalação
     */
    public function findByInstalacao($codigoInstalacao)
    {
        $sql = "SELECT * FROM {$this->table} WHERE codigo_instalacao = ? ORDER BY id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$codigoInstalacao]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca documentos por período de referência
     */
    public function findByPeriodo($mes, $ano)
    {
        $sql = "SELECT * FROM {$this->table} WHERE mes_referencia = ? AND ano_referencia = ? ORDER BY id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$mes, $ano]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Atualiza apenas os dados da fatura
     */
    public function atualizarDadosFatura($id, $dados)
    {
        $campos = [];
        $valores = [];
        
        foreach ($dados as $campo => $valor) {
            if (in_array($campo, $this->fillable)) {
                $campos[] = "{$campo} = ?";
                $valores[] = $valor;
            }
        }
        
        if (empty($campos)) {
            return false;
        }
        
        $valores[] = $id;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $campos) . ", atualizado_em = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($valores);
    }
}