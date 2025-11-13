<?php
/**
 * Componente de Paginação Reutilizável
 * Uso: include 'pagination_component.php';
 * 
 * Variáveis esperadas:
 * - $pagination: instância de PaginationHelper
 */

if (!isset($pagination) || !($pagination instanceof PaginationHelper)) {
    return;
}

$range = $pagination->getPaginationRange(2);
?>

<?php if ($pagination->getTotalPages() > 1): ?>
<div class="px-5 py-5 bg-white border-t flex flex-col xs:flex-row items-center xs:justify-between">
    <div class="flex items-center gap-1 flex-wrap">
        <?php if ($pagination->hasPreviousPage()): ?>
            <a href="?<?= PaginationHelper::buildQueryParams(['page' => 1]) ?>"
                class="px-3 py-2 text-sm bg-gray-200 text-gray-800 hover:bg-gray-300 rounded">
                Primeira
            </a>
            <a href="?<?= PaginationHelper::buildQueryParams(['page' => $pagination->getCurrentPage() - 1]) ?>"
                class="px-3 py-2 text-sm bg-gray-200 text-gray-800 hover:bg-gray-300 rounded">
                ← Anterior
            </a>
        <?php else: ?>
            <span class="px-3 py-2 text-sm bg-gray-100 text-gray-500 rounded cursor-not-allowed">
                Primeira
            </span>
            <span class="px-3 py-2 text-sm bg-gray-100 text-gray-500 rounded cursor-not-allowed">
                ← Anterior
            </span>
        <?php endif; ?>

        <?php if ($range['has_previous_gap']): ?>
            <span class="px-2 py-2 text-sm text-gray-600">...</span>
        <?php endif; ?>

        <?php for ($i = $range['start']; $i <= $range['end']; $i++): ?>
            <a href="?<?= PaginationHelper::buildQueryParams(['page' => $i]) ?>"
                class="px-3 py-2 text-sm 
                <?php if ($i == $pagination->getCurrentPage()): ?>
                    bg-[#147cac] text-white rounded
                <?php else: ?>
                    bg-gray-200 text-gray-800 hover:bg-gray-300 rounded
                <?php endif; ?>
                ">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($range['has_next_gap']): ?>
            <span class="px-2 py-2 text-sm text-gray-600">...</span>
        <?php endif; ?>

        <?php if ($pagination->hasNextPage()): ?>
            <a href="?<?= PaginationHelper::buildQueryParams(['page' => $pagination->getCurrentPage() + 1]) ?>"
                class="px-3 py-2 text-sm bg-gray-200 text-gray-800 hover:bg-gray-300 rounded">
                Próximo →
            </a>
            <a href="?<?= PaginationHelper::buildQueryParams(['page' => $pagination->getTotalPages()]) ?>"
                class="px-3 py-2 text-sm bg-gray-200 text-gray-800 hover:bg-gray-300 rounded">
                Última
            </a>
        <?php else: ?>
            <span class="px-3 py-2 text-sm bg-gray-100 text-gray-500 rounded cursor-not-allowed">
                Próximo →
            </span>
            <span class="px-3 py-2 text-sm bg-gray-100 text-gray-500 rounded cursor-not-allowed">
                Última
            </span>
        <?php endif; ?>
    </div>
    <div class="mt-3 xs:mt-0 text-sm text-gray-600">
        Página <?= $pagination->getCurrentPage() ?> de <?= $pagination->getTotalPages() ?>
    </div>
</div>
<?php endif; ?>
