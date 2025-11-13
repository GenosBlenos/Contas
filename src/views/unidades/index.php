<?php
ob_start();

function get_query_params($new_params = []) {
    $params = array_merge($_GET, $new_params);
    return http_build_query($params);
}
?>

<div class="container mx-auto px-4 sm:px-8">
    <div class="py-8">
        <div>
            <h2 class="text-2xl font-semibold leading-tight">Unidades</h2>
        </div>
        
        <div class="my-2 flex sm:flex-row flex-col">
            <div class="flex flex-row mb-1 sm:mb-0">
                <a href="unidade_form.php?module=<?= htmlspecialchars($module ?? '') ?>"
                    class="bg-[#147cac] hover:bg-[#106191] text-white font-bold py-2 px-4 rounded">
                    Nova Unidade
                </a>
            </div>
            
            <div class="block relative sm:ml-auto">
                <form action="unidades.php" method="GET">
                    <span class="h-full absolute inset-y-0 left-0 flex items-center pl-2">
                        <svg viewBox="0 0 24 24" class="h-4 w-4 fill-current text-gray-500">
                            <path
                                d="M10 4a6 6 0 100 12 6 6 0 000-12zm-8 6a8 8 0 1114.32 4.906l5.387 5.387a1 1 0 01-1.414 1.414l-5.387-5.387A8 8 0 012 10z">
                            </path>
                        </svg>
                    </span>
                    <?php if (!empty($module)): ?>
                        <input type="hidden" name="module" value="<?= htmlspecialchars($module) ?>">
                    <?php endif; ?>
                    <input placeholder="Pesquisar..." name="search"
                        value="<?= htmlspecialchars($search ?? '') ?>"
                        class="appearance-none rounded border border-gray-400 border-b block pl-8 pr-6 py-2 w-full bg-white text-sm placeholder-gray-400 text-gray-700 focus:bg-white focus:placeholder-gray-600 focus:text-gray-700 focus:outline-none" />
                </form>
            </div>
        </div>

        <div class="-mx-4 sm:-mx-8 px-4 sm:px-8 py-4 overflow-x-auto">
            <div class="inline-block min-w-full shadow rounded-lg overflow-hidden">
                <table class="min-w-full leading-normal">
                    <thead>
                        <tr>
                            <th
                                class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Nome
                            </th>
                            <th
                                class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Endereço
                            </th>
                            <th
                                class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Responsável
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registros)): ?>
                            <tr>
                                <td colspan="4" class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center">
                                    <p class="text-gray-900 whitespace-no-wrap">
                                        Nenhum registro encontrado.
                                    </p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($registros as $unidade): ?>
                                <tr>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <p class="text-gray-900 whitespace-no-wrap"><?= htmlspecialchars($unidade['nome']) ?>
                                        </p>
                                    </td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <p class="text-gray-900 whitespace-no-wrap">
                                            <?= htmlspecialchars($unidade['endereco']) ?></p>
                                    </td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <p class="text-gray-900 whitespace-no-wrap">
                                            <?= htmlspecialchars($unidade['responsavel']) ?></p>
                                    </td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-right">
                                        <a href="unidade_form.php?id=<?= $unidade['id'] ?>&module=<?= htmlspecialchars($module ?? '') ?>"
                                            class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                        <form action="unidades.php" method="POST" class="inline-block"
                                            onsubmit="return confirm('Tem certeza que deseja excluir esta unidade?');">
                                            <input type="hidden" name="id" value="<?= $unidade['id'] ?>">
                                            <input type="hidden" name="action" value="destroy">
                                            <button type="submit" class="text-red-600 hover:text-red-900 ml-4">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="px-5 py-5 bg-white border-t flex flex-col xs:flex-row items-center xs:justify-between">
            <div class="flex items-center gap-1 flex-wrap">
                <?php if ($page > 1): ?>
                    <a href="?<?= get_query_params(['page' => 1]) ?>"
                        class="px-3 py-2 text-sm bg-gray-200 text-gray-800 hover:bg-gray-300 rounded">
                        Primeira
                    </a>
                    <a href="?<?= get_query_params(['page' => $page - 1]) ?>"
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

                <?php 
                // Calcula o intervalo de páginas a exibir (5 páginas ao redor da atual)
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                // Se estamos no início, mostrar mais páginas no final
                if ($start_page == 1) {
                    $end_page = min($total_pages, 5);
                }
                // Se estamos no final, mostrar mais páginas no início
                if ($end_page == $total_pages) {
                    $start_page = max(1, $total_pages - 4);
                }
                
                // Mostrar "..." se há páginas no início
                if ($start_page > 1): ?>
                    <span class="px-2 py-2 text-sm text-gray-600">...</span>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?<?= get_query_params(['page' => $i]) ?>"
                        class="px-3 py-2 text-sm 
                        <?php if ($i == $page): ?>
                            bg-[#147cac] text-white rounded
                        <?php else: ?>
                            bg-gray-200 text-gray-800 hover:bg-gray-300 rounded
                        <?php endif; ?>
                        ">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php 
                // Mostrar "..." se há páginas no final
                if ($end_page < $total_pages): ?>
                    <span class="px-2 py-2 text-sm text-gray-600">...</span>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?<?= get_query_params(['page' => $page + 1]) ?>"
                        class="px-3 py-2 text-sm bg-gray-200 text-gray-800 hover:bg-gray-300 rounded">
                        Próximo →
                    </a>
                    <a href="?<?= get_query_params(['page' => $total_pages]) ?>"
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
                Página <?= $page ?> de <?= $total_pages ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../includes/template.php';
?>