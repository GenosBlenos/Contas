<?php
require_once __DIR__ . '/../src/includes/session_config.php';
session_start();
require_once __DIR__ . '/../src/includes/auth.php';
require_once __DIR__ . '/../src/includes/header.php';
require_once __DIR__ . '/../src/controllers/DocumentosController.php';
$pageTitle = 'Dashboard';

// 1. Ponto de Entrada e Autenticação
require_once __DIR__ . '/../src/includes/Logger.php';
require_once __DIR__ . '/../src/includes/SecurityManager.php';

$pageTitle = 'Documentos - Gerenciamento de Faturas';

// Obtém o módulo da URL, se existir
$module = $_GET['module'] ?? null;
$controller = new DocumentosController();

// Processa as ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ação de exclusão
    if (isset($_POST['excluir'])) {
        $id = $_POST['id'];
        if ($controller->destroy($id)) {
            flashMessage('success', 'Documento excluído com sucesso.');
        } else {
            flashMessage('error', 'Erro ao excluir o documento.');
        }
    }
    
    // Ação de renomear arquivo
    if (isset($_POST['renomear_arquivo'])) {
        $id = $_POST['id'];
        $novoNome = trim($_POST['novo_nome']);
        
        if (empty($novoNome)) {
            flashMessage('error', 'O novo nome não pode estar vazio.');
        } else {
            if ($controller->renomearArquivo($id, $novoNome)) {
                flashMessage('success', 'Arquivo renomeado com sucesso.');
            } else {
                flashMessage('error', 'Erro ao renomear o arquivo.');
            }
        }
    }
    
    // Ação de atualizar dados da fatura
    if (isset($_POST['atualizar_fatura'])) {
        $id = $_POST['id'];
        $dadosAtualizados = [
            'modulo' => $_POST['modulo'] ?? null,
            'mes_referencia' => $_POST['mes_referencia'] ?? null,
            'ano_referencia' => $_POST['ano_referencia'] ?? null,
            'codigo_instalacao' => $_POST['codigo_instalacao'] ?? null,
            'numero_fatura' => $_POST['numero_fatura'] ?? null,
            'vencimento' => $_POST['vencimento'] ?? null,
            'total_a_pagar' => $_POST['total_a_pagar'] ?? null
        ];
        
        if ($controller->atualizarDadosFatura($id, $dadosAtualizados)) {
            flashMessage('success', 'Dados da fatura atualizados com sucesso.');
        } else {
            flashMessage('error', 'Erro ao atualizar os dados da fatura.');
        }
    }
    
    // Redireciona para evitar reenvio do formulário
    header("Location: documentos.php?module=" . urlencode($module ?? ''));
    exit;
}

// Obtém os documentos
$documentos = $controller->index($module);

ob_start();
?>
<div class="container mx-auto px-4 py-8">
    <!-- Cabeçalho e Botões -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Gerenciamento de Documentos</h1>
            <?php if ($module): ?>
                <p class="text-gray-600">Filtrando por: <span class="font-semibold capitalize"><?= htmlspecialchars($module) ?></span></p>
            <?php endif; ?>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="documento_form.php?module=<?= htmlspecialchars($module ?? '') ?>" 
               class="bg-[#147cac] hover:bg-[#106191] text-white font-bold py-2 px-4 rounded-lg transition-colors">
                📄 Novo Documento
            </a>
            <?php if ($module): ?>
                <a href="documentos.php" 
                   class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition-colors">
                    🔄 Remover Filtro
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros por Módulo -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <h2 class="text-lg font-semibold mb-3">Filtrar por Tipo</h2>
        <div class="flex flex-wrap gap-2">
            <a href="documentos.php" 
               class="px-3 py-1 rounded-full text-sm <?= !$module ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                Todos
            </a>
            <a href="documentos.php?module=agua" 
               class="px-3 py-1 rounded-full text-sm <?= $module === 'agua' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                💧 Água
            </a>
            <a href="documentos.php?module=energia" 
               class="px-3 py-1 rounded-full text-sm <?= $module === 'energia' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                ⚡ Energia
            </a>
            <a href="documentos.php?module=telefone" 
               class="px-3 py-1 rounded-full text-sm <?= $module === 'telefone' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                📞 Telefone
            </a>
            <a href="documentos.php?module=semparar" 
               class="px-3 py-1 rounded-full text-sm <?= $module === 'semparar' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                🚗 Sem Parar
            </a>
        </div>
    </div>

    <?php if (empty($documentos)): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-lg shadow">
            <p class="font-semibold">Nenhum documento encontrado.</p>
            <p class="text-sm mt-1">Clique em "Novo Documento" para adicionar o primeiro documento.</p>
        </div>
    <?php else: ?>
        <!-- Tabela de Documentos -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Arquivo</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Módulo</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($documentos as $documento): 
                            $dadosFatura = ($module);
                            $nomeAtual = pathinfo($documento['arquivo_pdf'], PATHINFO_FILENAME);
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($documento['arquivo_pdf']); ?></div>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <a href="<?= htmlspecialchars($documento['arquivo_pdf']); ?>" 
                                       target="_blank" 
                                       class="text-blue-600 hover:text-blue-800 hover:underline flex items-center gap-1">
                                        📎 <?= htmlspecialchars($documento['arquivo_pdf']); ?>
                                    </a>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Nome atual: <?= htmlspecialchars($nomeAtual); ?></div>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    <?= $dadosFatura === 'agua' ? 'bg-blue-100 text-blue-800' : '' ?>
                                    <?= $dadosFatura === 'energia' ? 'bg-yellow-100 text-yellow-800' : '' ?>
                                    <?= $dadosFatura === 'telefone' ? 'bg-green-100 text-green-800' : '' ?>
                                    <?= $dadosFatura === 'internet' ? 'bg-purple-100 text-purple-800' : '' ?>
                                    <?= $dadosFatura === 'semparar' ? 'bg-red-100 text-red-800' : '' ?>
                                    capitalize">
                                    <?= htmlspecialchars($dadosFatura ?? 'Não definido'); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-500">
                                <?= date('d/m/Y H:i', strtotime($documento['criado_em'])); ?>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex flex-wrap gap-1">
                                    <!-- Botão Visualizar -->
                                    <a href="<?= htmlspecialchars($documento['arquivo_pdf']); ?>" 
                                       target="_blank"
                                       class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs hover:bg-blue-200 transition-colors">
                                        👁️ Visualizar
                                    </a>
                                    
                                    <!-- Botão Editar -->
                                    <a href="documento_form.php?id=<?= $documento['id'] ?>&module=<?= htmlspecialchars($module ?? '') ?>"
                                       class="inline-flex items-center px-2 py-1 bg-indigo-100 text-indigo-700 rounded text-xs hover:bg-indigo-200 transition-colors">
                                        ✏️ Editar
                                    </a>
                                    
                                    <!-- Botão Renomear -->
                                    <button onclick="abrirModalRenomear(<?= $documento['id'] ?>, '<?= htmlspecialchars($nomeAtual) ?>')"
                                            class="inline-flex items-center px-2 py-1 bg-green-100 text-green-700 rounded text-xs hover:bg-green-200 transition-colors">
                                        📝 Renomear
                                    </button>
                                    
                                    <!-- Botão Dados Fatura -->
                                    <button onclick="abrirModalDadosFatura(<?= $documento['id'] ?>)"
                                            class="inline-flex items-center px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs hover:bg-purple-200 transition-colors">
                                        💰 Dados Fatura
                                    </button>
                                    
                                    <!-- Botão Excluir -->
                                    <button onclick="excluirRegistro(<?= $documento['id'] ?>)"
                                            class="inline-flex items-center px-2 py-1 bg-red-100 text-red-700 rounded text-xs hover:bg-red-200 transition-colors">
                                        🗑️ Excluir
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal para Renomear Arquivo -->
<div id="modalRenomear" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Renomear Arquivo</h3>
            <form id="formRenomear" method="POST">
                <input type="hidden" name="id" id="renomearId">
                <div class="mb-4">
                    <label for="novoNome" class="block text-sm font-medium text-gray-700 mb-1">Novo nome do arquivo:</label>
                    <input type="text" 
                           name="novo_nome" 
                           id="novoNome" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Digite o novo nome (sem extensão)"
                           required>
                    <p class="text-xs text-gray-500 mt-1">A extensão .pdf será mantida automaticamente</p>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" 
                            onclick="fecharModalRenomear()"
                            class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium">
                        Cancelar
                    </button>
                    <button type="submit" 
                            name="renomear_arquivo" 
                            value="1"
                            class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 font-medium transition-colors">
                        Renomear
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Editar Dados da Fatura -->
<div id="modalDadosFatura" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Editar Dados da Fatura</h3>
            <form id="formDadosFatura" method="POST">
                <input type="hidden" name="id" id="dadosFaturaId">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="modulo" class="block text-sm font-medium text-gray-700 mb-1">Módulo:</label>
                        <select name="modulo" id="modulo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Selecione...</option>
                            <option value="agua">Água</option>
                            <option value="energia">Energia</option>
                            <option value="telefone">Telefone</option>
                            <option value="internet">Internet</option>
                            <option value="semparar">Sem Parar</option>
                        </select>
                    </div>
                    <div>
                        <label for="mes_referencia" class="block text-sm font-medium text-gray-700 mb-1">Mês Referência:</label>
                        <input type="text" 
                               name="mes_referencia" 
                               id="mes_referencia" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Ex: AGO, SET, OUT">
                    </div>
                    <div>
                        <label for="ano_referencia" class="block text-sm font-medium text-gray-700 mb-1">Ano Referência:</label>
                        <input type="text" 
                               name="ano_referencia" 
                               id="ano_referencia" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Ex: 2024, 2025">
                    </div>
                    <div>
                        <label for="codigo_instalacao" class="block text-sm font-medium text-gray-700 mb-1">Código Instalação:</label>
                        <input type="text" 
                               name="codigo_instalacao" 
                               id="codigo_instalacao" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Código único">
                    </div>
                    <div>
                        <label for="numero_fatura" class="block text-sm font-medium text-gray-700 mb-1">Número Fatura:</label>
                        <input type="text" 
                               name="numero_fatura" 
                               id="numero_fatura" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Número da fatura">
                    </div>
                    <div>
                        <label for="vencimento" class="block text-sm font-medium text-gray-700 mb-1">Vencimento:</label>
                        <input type="date" 
                               name="vencimento" 
                               id="vencimento" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div class="md:col-span-2">
                        <label for="total_a_pagar" class="block text-sm font-medium text-gray-700 mb-1">Total a Pagar:</label>
                        <input type="number" 
                               name="total_a_pagar" 
                               id="total_a_pagar" 
                               step="0.01"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="0.00">
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" 
                            onclick="fecharModalDadosFatura()"
                            class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium">
                        Cancelar
                    </button>
                    <button type="submit" 
                            name="atualizar_fatura" 
                            value="1"
                            class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 font-medium transition-colors">
                        Atualizar Dados
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Funções para o modal de renomear
function abrirModalRenomear(id, nomeAtual) {
    document.getElementById('renomearId').value = id;
    document.getElementById('novoNome').value = nomeAtual;
    document.getElementById('modalRenomear').classList.remove('hidden');
    document.getElementById('novoNome').focus();
}

function fecharModalRenomear() {
    document.getElementById('modalRenomear').classList.add('hidden');
    document.getElementById('formRenomear').reset();
}

// Funções para o modal de dados da fatura
function abrirModalDadosFatura(id) {
    document.getElementById('dadosFaturaId').value = id;
    
    // Aqui você pode fazer uma requisição AJAX para buscar os dados atuais
    // Por enquanto, vamos apenas abrir o modal
    document.getElementById('modalDadosFatura').classList.remove('hidden');
}

function fecharModalDadosFatura() {
    document.getElementById('modalDadosFatura').classList.add('hidden');
    document.getElementById('formDadosFatura').reset();
}

// Função de exclusão
function excluirRegistro(id) {
    if (confirm('Tem certeza que deseja excluir este documento? O arquivo também será removido permanentemente.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'documentos.php?module=<?= htmlspecialchars($module ?? '') ?>';
        form.innerHTML = `
            <input type="hidden" name="id" value="${id}">
            <input type="hidden" name="excluir" value="1">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Fechar modais ao clicar fora
document.addEventListener('click', function(event) {
    const modalRenomear = document.getElementById('modalRenomear');
    const modalDadosFatura = document.getElementById('modalDadosFatura');
    
    if (event.target === modalRenomear) {
        fecharModalRenomear();
    }
    if (event.target === modalDadosFatura) {
        fecharModalDadosFatura();
    }
});

// Fechar modais com ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        fecharModalRenomear();
        fecharModalDadosFatura();
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../src/includes/template.php';