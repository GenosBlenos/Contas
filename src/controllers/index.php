<?php
// As variáveis ($recomendacoes, $contas, $filtros, etc.) são fornecidas pelo RecomendacaoController
?>

<!-- Formulário de Filtros -->
<div class="bg-gray-50 p-4 rounded-lg shadow-md mb-8">
    <form action="index.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <input type="hidden" name="page" value="recomendacoes">

        <div>
            <label for="modulo" class="block text-sm font-medium text-gray-700">Módulo</label>
            <select name="modulo" id="modulo" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                <?php foreach ($modulosDisponiveis as $modulo): ?>
                    <option value="<?= htmlspecialchars($modulo) ?>" <?= ($filtroModulo == $modulo) ? 'selected' : '' ?>>
                        <?= ucfirst($modulo) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="instalacao" class="block text-sm font-medium text-gray-700">Instalação</label>
            <select name="instalacao" id="instalacao" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                <option value="todas">Todas as Instalações</option>
                <?php foreach ($instalacoesDisponiveis as $instalacao): ?>
                    <option value="<?= htmlspecialchars($instalacao) ?>" <?= ($filtroInstalacao == $instalacao) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($instalacao) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="mes_ano" class="block text-sm font-medium text-gray-700">Mês/Ano</label>
            <select name="mes_ano" id="mes_ano" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                <option value="todos">Todos os Períodos</option>
                 <?php foreach ($mesesAnosDisponiveis as $mesAno): ?>
                    <option value="<?= htmlspecialchars($mesAno) ?>" <?= ($filtroMesAno == $mesAno) ? 'selected' : '' ?>>
                        <?= date('m/Y', strtotime($mesAno . '-01')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="bg-[#147cac] text-white font-bold py-2 px-4 rounded-md hover:bg-[#106191] transition duration-300">
            Analisar
        </button>
    </form>
</div>

<!-- Seção de Recomendações -->
<div class="space-y-8">
    <?php if (empty($recomendacoes) && !empty($filtroModulo)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
            <p class="font-bold">Tudo Certo!</p>
            <p>Nenhum ponto de atenção crítico foi identificado para os filtros selecionados.</p>
        </div>
    <?php else: ?>
        <?php foreach ($recomendacoes as $instalacao => $listaRecomendacoes): ?>
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">
                    <img src="./assets/pin.png" alt="Local" class="w-5 h-5 inline-block mr-2">
                    Instalação: <?= htmlspecialchars($instalacao) ?>
                </h3>
                <ul class="space-y-3">
                    <?php foreach ($listaRecomendacoes as $rec): ?>
                        <?php
                            $cores = [
                                'alta' => 'border-red-500 bg-red-50',
                                'media' => 'border-yellow-500 bg-yellow-50',
                                'info' => 'border-blue-500 bg-blue-50',
                            ];
                            $cor = $cores[$rec['severidade']] ?? 'border-gray-300';
                        ?>
                        <li class="p-4 rounded-lg border-l-4 <?= $cor ?>">
                            <p class="font-bold text-gray-700"><?= htmlspecialchars($rec['tipo']) ?></p>
                            <p class="text-gray-600"><?= $rec['mensagem'] // Não usar htmlspecialchars aqui para renderizar o <b> ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    // Recarrega a página para atualizar as opções de instalação e período quando o módulo muda.
    document.getElementById('modulo').addEventListener('change', function() {
        const selectedModule = this.value;
        window.location.href = `index.php?page=recomendacoes&modulo=${selectedModule}`;
    });
</script>