<?php
require_once __DIR__ . '/../includes/Model.php';
class Fatura extends Model {
    protected $table = 'faturas';
    protected $fillable = ['descricao', 'valor', 'vencimento', 'status'];
    protected $orderBy = 'vencimento';
}
