<?php
require_once __DIR__ . '/../includes/Model.php';
class Kpi extends Model {
    protected $table = 'kpis';
    protected $fillable = ['nome', 'valor', 'referencia'];
    protected $orderBy = 'referencia';
}
