<?php
require_once __DIR__ . '/../src/includes/config.php';
require_once __DIR__ . '/../src/includes/auth.php';
require_once __DIR__ . '/../src/includes/session_config.php';
require_once __DIR__ . '/../src/includes/header.php';
require_once __DIR__ . '/../src/includes/helpers.php'; // Adicionado para garantir que a função gerarCSRFToken esteja disponível
$pageTitle = 'Cadastro de Fatura PDF';

// Redireciona para a página de login se o usuário não estiver autenticado
if (!isset($_SESSION['logado']) || !$_SESSION['logado']) {
    header('Location: /compras/login.php');
    exit;
}

// Define o módulo atual para que o menu lateral possa destacá-lo, se aplicável.
$_GET['module'] = 'cad_fatura_pdf'; 

// Inicia o buffer de saída para capturar o conteúdo HTML
ob_start();
?>

<div class="space-y-6">
    <?php
    // Exibe a mensagem da sessão, se houver
    if (isset($_SESSION['msg'])) {
        echo $_SESSION['msg'];
        unset($_SESSION['msg']);
    }
    ?>

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-xl font-semibold text-gray-800 mb-2">Cadastro de Conta por PDF</h2>
        <p class="text-gray-600 mb-6">Envie uma Nota Fiscal ou comprovante em PDF para cadastrar a conta automaticamente.</p>
        
        <form id="pdfUploadForm" action="processa_pdf.php" method="post" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(gerarCSRFToken()); ?>">
            <div>
                <label for="pdfFile" class="block text-sm font-medium text-gray-700">Arquivo PDF</label>
                <input type="file" name="pdfFile" id="pdfFile" accept=".pdf" required 
                       class="mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-[#4a90e2] file:text-white hover:file:bg-[#2563eb]">
            </div>

            <div class="flex items-center justify-end space-x-2 pt-4">
                <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-md shadow-sm">
                    Voltar
                </a>
                <button type="submit" name="salvar" id="submitBtn" class="bg-[#4a90e2] hover:bg-[#2563eb] text-white font-bold py-2 px-4 rounded-md shadow-sm">
                    Processar e Cadastrar
                </button>
            </div>
        </form>
        <div id="status" class="mt-4"></div>
    </div>
</div>

<script>
/**
 * Função para gerar dinamicamente o HTML dos campos do formulário
 * com base nos dados extraídos e usando as classes do seu CSS de exemplo.
 */
function gerarCamposHtml(details) {
    let html = '';
    // Usa 'form-floating' se as classes 'form-control' e 'form-label' estiverem presentes
    const useFloatingLabels = true; 

    for (const [key, value] of Object.entries(details)) {
        // Converte 'numero_ligacao' para 'Numero Ligacao'
        const labelText = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        
        const inputId = `modal_${key}`;
        
        html += `
            <div class="profile-form-group ${useFloatingLabels ? 'form-floating' : ''} col-span-1">
                <input 
                    type="text" 
                    class="profile-form-input ${useFloatingLabels ? 'form-control' : ''}" 
                    id="${inputId}" 
                    name="${key}" 
                    value="${value || ''}" 
                    ${useFloatingLabels ? `placeholder="${labelText}"` : ''}
                >
                <label 
                    for="${inputId}" 
                    class="profile-form-label ${useFloatingLabels ? 'form-label' : ''}"
                >
                    ${labelText}
                </label>
            </div>
        `;
    }
    // Retorna o HTML envolvido em um grid para layout de 2 colunas
    return `<div class="grid grid-cols-1 md:grid-cols-2 gap-4">${html}</div>`;
}

/**
 * Função para abrir o modal de edição (SweetAlert) com os dados da fatura.
 */
async function abrirModalFatura(data) {
    const { category, details, arquivo_pdf, unidade_id: initial_unidade_id } = data;

    // Função para buscar as unidades
    async function getUnidades() {
        try {
            const response = await fetch('ajax_get_unidades.php');
            if (!response.ok) {
                throw new Error('Não foi possível buscar as unidades.');
            }
            const data = await response.json();
            return data.success ? data.unidades : [];
        } catch (error) {
            console.error('Erro ao buscar unidades:', error);
            return []; // Retorna um array vazio em caso de erro
        }
    }

    function getCategoryIcon(category) {
        const basePath = '../assets/';
        const iconMap = {
            'energia': 'flash.png',
            'agua': 'water.png',
            'internet': 'wifi.png',
            'telefone': 'phone.png',
            'semparar': 'car.png'
        };
        const lowerCaseCategory = category.toLowerCase();
        const iconFile = iconMap[lowerCaseCategory];
        
        if (iconFile) {
            return `<img src="${basePath}${iconFile}" alt="${category}" class="inline-block w-4 h-4 mr-1 mb-1">`;
        }
        return '';
    }

    // Gera o HTML para o dropdown de unidades - CORREÇÃO APLICADA AQUI
    function gerarUnidadesDropdownHtml(unidades, selectedId) {
        let options = unidades.map(unidade => 
            `<option value="${unidade.id}" ${unidade.id == selectedId ? 'selected' : ''}>${unidade.nome}</option>`
        ).join('');

        // CORREÇÃO: Alterado para "Sem Unidade (Automático)" como opção padrão
        return `
            <div class="profile-form-group form-floating col-span-1 md:col-span-2">
                <select id="modal_unidade_id" name="unidade_id" class="profile-form-input form-control">
                    <option value="">Sem Unidade (Automático)</option>
                    ${options}
                </select>
                <label for="modal_unidade_id" class="profile-form-label form-label">Unidade</label>
            </div>
        `;
    }

    // 1. Busca as unidades e gera o HTML dos campos
    const unidades = await getUnidades();
    const camposHtml = gerarCamposHtml(details);
    const unidadesDropdownHtml = gerarUnidadesDropdownHtml(unidades, initial_unidade_id);
    const categoryIconHtml = getCategoryIcon(category);

    const modalStyles = `
        .swal2-popup { border-radius: 15px; padding: 2rem; width: 60% !important; max-width: 1200px !important; }
        @media (max-width: 1024px) { .swal2-popup { width: 80% !important; } }
        @media (max-width: 768px) { .swal2-popup { width: 95% !important; } }
        .profile-form-group { margin-bottom: 1rem; text-align: left; }
        .profile-form-label { display: block; color: var(--first-purple, #4a4a4a); font-weight: 600; margin-bottom: 0.5rem; font-size: 0.95rem; }
        .profile-form-input { width: 100%; padding: 0.75rem; border-radius: 8px; font-size: 1rem; transition: all 0.3s ease; box-sizing: border-box; border: 1px solid #aaaa; }
        .profile-form-input:focus { outline: none; border-color: var(--second-purple, #147cac); box-shadow: 0 0 0 3px #0832452a; }
        h2#swal2-title.swal2-title { padding-top: 0px !important; text-align: left; }
        .swal2-confirm { background: linear-gradient(135deg, var(--second-purple, #147cac), var(--third-purple, #0e5779)) !important; border: none !important; border-radius: 8px !important; padding: 0.75rem 2rem !important; font-weight: 600 !important; transition: all 0.3s ease !important; }
        .swal2-confirm:hover { background: linear-gradient(135deg, var(--first-purple, #0e5779), var(--second-purple, #147cac)) !important; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(45, 27, 105, 0.3) !important; }
        .swal2-cancel { background-color: var(--first-gray, #a0a0a0) !important; border: none !important; border-radius: 8px !important; padding: 0.75rem 2rem !important; font-weight: 600 !important; transition: all 0.3s ease !important; }
        .swal2-cancel:hover { background-color: var(--second-gray, #696969) !important; transform: translateY(-2px); }
        .form-floating { position: relative; }
        .form-floating > .form-control { height: calc(3.5rem + 2px); line-height: 1.25; padding: 1rem 0.75rem; }
        .form-floating > label { position: absolute; top: 0; left: 0; height: 100%; padding: 1rem 0.75rem; pointer-events: none; border: 1px solid transparent; transform-origin: 0 0; transition: opacity .1s ease-in-out,transform .1s ease-in-out; }
        .form-floating > .form-control:not(:placeholder-shown) ~ label { opacity: .65; transform: scale(.85) translateY(-.5rem) translateX(.15rem); }
        .form-floating > .form-control:focus ~ label { opacity: .65; transform: scale(.85) translateY(-.5rem) translateX(.15rem); }
    `;

    // 2. Monta o HTML final para o modal
    const modalHtml = `
        <style>${modalStyles}</style>
        <div class="p-3 bg-blue-50 border border-blue-200 rounded-md mb-4 flex items-center">
            <p class="text-sm text-blue-700 text-left">Categoria detectada: <strong class="uppercase">${category}${categoryIconHtml}</strong></p>
        </div>
        <form id="faturaEditForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                ${unidadesDropdownHtml}
            </div>
            ${camposHtml}
        </form>
    `;

    // 3. Chama o SweetAlert
    Swal.fire({
        title: `
            <div style="display: flex; align-items: center; justify-content: start; gap: .2rem;">
                <strong style="color: var(--first-purple, #333); font-size: 1.2rem;">Confirmação Manual da Fatura</strong>
            </div>
        `,
        html: modalHtml,
        width: '40%',
        showCancelButton: true,
        confirmButtonText: 'Salvar Fatura',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusConfirm: false,
        customClass: {
            popup: 'profile-edit-popup',
        },
        
        // 4. Hook de pré-confirmação - CORREÇÃO APLICADA AQUI
        preConfirm: async () => {
            const form = document.getElementById('faturaEditForm');
            if (!form) {
                Swal.showValidationMessage('Erro: Formulário não encontrado.');
                return false;
            }

            const formData = new FormData(form);
            const unidadeSelect = document.getElementById('modal_unidade_id');
            const unidadeId = unidadeSelect.value;
            
            // CORREÇÃO: Define o nome da unidade corretamente
            const unidadeNome = unidadeId ? unidadeSelect.selectedOptions[0].text : 'Sem Unidade (Automático)';

            const payload = {
                category: category,
                arquivo_pdf: arquivo_pdf,
                unidade_id: unidadeId,
                unidade_nome: unidadeNome, // Usa o nome correto baseado na seleção
                details: {}
            };

            formData.forEach((value, key) => {
                if (key !== 'unidade_id') {
                    payload.details[key] = value;
                }
            });

            try {
                const response = await fetch('salva_fatura.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Erro do servidor: ${response.status} ${response.statusText}. Detalhes: ${errorText}`);
                }

                const resultData = await response.json();

                if (!resultData.success) {
                    throw new Error(resultData.message || 'Erro desconhecido ao salvar.');
                }

                return resultData;

            } catch (error) {
                Swal.showValidationMessage(`Falha ao salvar: ${error.message}`);
                return false;
            }
        },
    }).then((result) => {
        const mainForm = document.getElementById('pdfUploadForm');
        const statusDiv = document.getElementById('status');

        if (result.isConfirmed) {
            // SUCESSO! O preConfirm retornou os dados.
            const data = result.value;
            
            // Exibe a mensagem de sucesso final na página principal
            statusDiv.innerHTML = `<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert"><p>${data.message}</p></div>`;
            mainForm.reset(); // Limpa o formulário de upload

            // Opcional: mostrar um "toast" de sucesso
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Fatura salva!',
                showConfirmButton: false,
                timer: 3000
            });
            
        } else if (result.isDismissed) {
            // Usuário clicou em "Cancelar"
            statusDiv.innerHTML = `<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4" role="alert"><p>Cadastro cancelado. O arquivo não foi salvo.</p></div>`;
            mainForm.reset();
        }
    });
}

document.getElementById('pdfUploadForm').addEventListener('submit', function(e) {
    e.preventDefault(); // Impede o envio padrão do formulário

    const form = e.target;
    const formData = new FormData(form);
    const statusDiv = document.getElementById('status');
    const submitButton = document.getElementById('submitBtn');

    // Desabilita o botão e mostra mensagem de processamento
    submitButton.disabled = true;
    submitButton.textContent = 'Processando...';
    statusDiv.innerHTML = `<div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4" role="alert"><p>Enviando e processando o arquivo. Isso pode levar alguns instantes...</p></div>`;

    fetch(form.action, { // form.action é 'processa_pdf.php'
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data) {
            // Limpa a mensagem de "processando"
            statusDiv.innerHTML = ''; 
            // Chama o modal SweetAlert com os dados extraídos
            abrirModalFatura(data.data);
            
        } else {
            // Falha na extração (erro no PHP ou Python)
            statusDiv.innerHTML = `<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert"><p><b>Erro na Extração:</b> ${data.message || 'Ocorreu um erro desconhecido.'}</p></div>`;
        }
    })
    .catch(error => {
        statusDiv.innerHTML = `<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
            <p><b>Erro Crítico:</b> Falha na comunicação com o servidor.</p>
            <pre class="text-sm whitespace-pre-wrap">${error.toString()}</pre>
        </div>`;
        console.error('Upload Fetch Error:', error);
    })
    .finally(() => {
        // Reabilita o botão ao final do processo de *extração*
        submitButton.disabled = false;
        submitButton.textContent = 'Processar e Cadastrar';
    });
});
</script>

<?php
// Captura o conteúdo do buffer
$content = ob_get_clean();

// Inclui o arquivo de template principal que montará a página
require_once __DIR__ . '/../src/includes/template.php';
?>