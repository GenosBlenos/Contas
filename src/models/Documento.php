<?php
require_once __DIR__ . '/../includes/Model.php';

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

    public function findByModule($module)
    {
        $sql = "SELECT * FROM {$this->table} WHERE modulo = ? ORDER BY criado_em DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$module]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca documentos por código de instalação
     */
    public function findByInstalacao($codigoInstalacao)
    {
        $sql = "SELECT * FROM {$this->table} WHERE codigo_instalacao = ? ORDER BY criado_em DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$codigoInstalacao]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca documentos por período de referência
     */
    public function findByPeriodo($mes, $ano)
    {
        $sql = "SELECT * FROM {$this->table} WHERE mes_referencia = ? AND ano_referencia = ? ORDER BY criado_em DESC";
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