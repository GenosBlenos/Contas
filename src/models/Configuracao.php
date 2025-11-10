<?php
require_once __DIR__ . '/../includes/Model.php';
class Configuracao extends Model {
    protected $table = 'configuracoes';
    protected $fillable = ['chave', 'valor'];
    protected $orderBy = 'id';
}
