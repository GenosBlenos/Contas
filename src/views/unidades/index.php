<?php
// Views

ob_start();
?>

<div class="container mx-auto px-4 sm:px-8">
    <div class="py-8">
        <div>
            <h2 class="text-2xl font-semibold leading-tight">Unidades</h2>
        </div>
        <div class="my-2 flex sm:flex-row flex-col">
            <div class="flex flex-row mb-1 sm:mb-0">
                <a href="unidade_form.php?module=<?= htmlspecialchars($module ?? '') ?>" class="bg-[#147cac] hover:bg-[#106191] text-white font-bold py-2 px-4 rounded">
                    Nova Unidade
                </a>
            </div>
        </div>
        <div class="-mx-4 sm:-mx-8 px-4 sm:px-8 py-4 overflow-x-auto">
            <div class="inline-block min-w-full shadow rounded-lg overflow-hidden">
                <table class="min-w-full leading-normal">
                    <thead>
                        <tr>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Nome
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Endereço
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Responsável
                            </th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registros as $unidade) : ?>
                            <tr>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap"><?= htmlspecialchars($unidade['nome']) ?></p>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap"><?= htmlspecialchars($unidade['endereco']) ?></p>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap"><?= htmlspecialchars($unidade['responsavel']) ?></p>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-right">
                                    <a href="unidade_form.php?id=<?= $unidade['id'] ?>&module=<?= htmlspecialchars($module ?? '') ?>" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                    <form action="unidades.php" method="POST" class="inline-block" onsubmit="return confirm('Tem certeza que deseja excluir esta unidade?');">
                                        <input type="hidden" name="id" value="<?= $unidade['id'] ?>">
                                        <input type="hidden" name="action" value="destroy">
                                        <button type="submit" class="text-red-600 hover:text-red-900 ml-4">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../includes/template.php';
?>