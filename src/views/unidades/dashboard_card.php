<?php
require_once __DIR__ . '/../../controllers/UnidadesController.php';

$unidadesController = new UnidadesController();
$unidades = $unidadesController->index();
?>

<div class="module module-unidades">
    <h2 class="module-title">🏢 Unidades</h2>
    <div class="unidades-list">
        <?php if (empty($unidades)) : ?>
            <p>Nenhuma unidade cadastrada.</p>
        <?php else : ?>
            <ul>
                <?php foreach ($unidades as $unidade) : ?>
                    <li>
                        <strong><?= htmlspecialchars($unidade['nome']) ?>:</strong>
                        <span><?= htmlspecialchars($unidade['endereco']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <div class="module-actions">
        <a href="unidade_form.php" class="btn-add">+ Adicionar Unidade</a>
    </div>
</div>

<style>
    .module-unidades {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        padding: 20px;
    }
    .module-unidades .module-title {
        font-size: 1.5em;
        font-weight: 600;
        margin: 0 0 20px 0;
        padding-bottom: 10px;
        border-bottom: 2px solid #6c757d;
    }
    .unidades-list ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .unidades-list li {
        padding: 8px 0;
        border-bottom: 1px solid #eee;
    }
    .unidades-list li:last-child {
        border-bottom: none;
    }
    .module-actions {
        margin-top: 20px;
        text-align: right;
    }
    .btn-add {
        background-color: #147cac;
        color: white;
        padding: 10px 15px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: bold;
    }
    .btn-add:hover {
        background-color: #106191;
    }
</style>
