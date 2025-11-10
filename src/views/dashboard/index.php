
<div id="secretPanel" class="hidden mt-8 bg-gray-800 text-white p-6 rounded-lg shadow-lg">
    <h2 class="text-xl font-bold mb-4">🔧 Painel de Desenvolvedor</h2>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-gray-700 p-4 rounded">
            <h3 class="font-bold mb-2">Status do Sistema</h3>
            <ul class="text-sm">
                <li>🟢 Serviços Principais: Online</li>
                <li>🟡 Banco de Dados: Conexão Estável</li>
                <li>🔵 Último Backup: <?php echo date('d/m/Y H:i'); ?></li>
            </ul>
        </div>
        <div class="bg-gray-700 p-4 rounded flex flex-col">
            <h3 class="font-bold mb-2">Ações Rápidas</h3>
            <div class="flex flex-col space-y-2 flex-grow">
                <button class="btn-action bg-blue-500 hover:bg-blue-600 px-4 py-2 rounded text-sm font-bold h-full flex items-center justify-center" data-url="../logs/system.log">
                    Logs
                </button>
            </div>
        </div>
        <div class="bg-gray-700 p-4 rounded flex flex-col">
            <h3 class="font-bold mb-2">Cadastro</h3>
            <div class="flex flex-col space-y-2 flex-grow">
                <button class="btn-action bg-red-500 hover:bg-red-600 px-4 py-2 rounded text-sm font-bold h-full flex items-center justify-center" data-url="../public/index-a.php">
                    Cadastrar Novo Usuário
                </button>
            </div>
        </div>
        <div class="bg-gray-700 p-4 rounded flex flex-col">
            <h3 class="font-bold mb-2">Abrir SQL Server</h3>
            <div class="flex flex-col space-y-2 flex-grow">
                <button class="btn-action bg-cyan-500 hover:bg-cyan-600 px-4 py-2 rounded text-sm font-bold h-full flex items-center justify-center" data-url="http://localhost/phpmyadmin/index.php?route=/database/structure&db=compras">
                    localhost:1433
                </button>
            </div>
        </div>
    </div>
</div>

<div class="mt-8 bg-white p-6 rounded-lg shadow-lg">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Sistema de Gerenciamento de Contas a Pagar</h2>
    <p class="text-gray-600">
        Bem-vindo ao Sistema de Controle de Gastos. Aqui você pode gerenciar suas contas de água, energia elétrica,
        telefonia fixa, internet predial e serviços de Sem Parar de forma eficiente e organizada.
    </p>
    <p class="mt-2 text-gray-600">
        A seção de <strong>Relatórios</strong> consolida os dados de todos os módulos para uma análise global, enquanto
        a seção de <strong>Recomendações</strong> utiliza inteligência para apontar possíveis economias e otimizações.
    </p>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mt-5">

    <!-- Card Água -->
    <a href="agua.php"
        class="bg-white rounded-lg shadow-lg p-6 flex flex-col items-center justify-center hover:shadow-xl transition-shadow duration-300">
        <img src="../assets/water.png" alt="Água" class="w-16 h-16 mb-4">
        <h3 class="text-lg font-semibold text-gray-800">Água Predial</h3>
    </a>

    <!-- Card Energia -->
    <a href="energia.php"
        class="bg-white rounded-lg shadow-lg p-6 flex flex-col items-center justify-center hover:shadow-xl transition-shadow duration-300">
        <img src="../assets/flash.png" alt="Energia" class="w-16 h-16 mb-4">
        <h3 class="text-lg font-semibold text-gray-800">Energia Elétrica</h3>
    </a>

    <!-- Card Sem Parar -->
    <a href="semparar.php"
        class="bg-white rounded-lg shadow-lg p-6 flex flex-col items-center justify-center hover:shadow-xl transition-shadow duration-300">
        <img src="../assets/car.png" alt="Sem Parar" class="w-16 h-16 mb-4">
        <h3 class="text-lg font-semibold text-gray-800">Sem Parar</h3>
    </a>

    <!-- Card Telefone -->
    <a href="telefone.php"
        class="bg-white rounded-lg shadow-lg p-6 flex flex-col items-center justify-center hover:shadow-xl transition-shadow duration-300">
        <img src="../assets/phone.png" alt="Telefone" class="w-16 h-16 mb-4">
        <h3 class="text-lg font-semibold text-gray-800">Telefonia Fixa</h3>
    </a>


    <!-- Card Relatórios -->
    <a href="relatorios.php"
        class="bg-white rounded-lg shadow-lg p-6 flex flex-col items-center justify-center hover:shadow-xl transition-shadow duration-300">
        <img src="../assets/report.png" alt="Relatórios" class="w-16 h-16 mb-4">
        <h3 class="text-lg font-semibold text-gray-800">Relatórios</h3>
    </a>

    <!-- Card Recomendações -->
    <a href="recomendacoes.php"
        class="bg-white rounded-lg shadow-lg p-6 flex flex-col items-center justify-center hover:shadow-xl transition-shadow duration-300">
        <img src="../assets/recommendation.png" alt="Recomendações" class="w-16 h-16 mb-4">
        <h3 class="text-lg font-semibold text-gray-800">Recomendações</h3>
    </a>

    <!-- Card Internet
    <div class="bg-white rounded-lg shadow-lg p-6 flex flex-col items-center justify-center opacity-50 cursor-not-allowed">
        <img src="../assets/wifi.png" alt="Internet" class="w-16 h-16 mb-4">
        <h3 class="text-lg font-semibold text-gray-800">Internet(Temp. Desativo)</h3>
    </div> -->

    <!-- Card Unidades  -->
    <a href="unidades.php"
        class="bg-white rounded-lg shadow-lg p-6 flex flex-col items-center justify-center hover:shadow-xl transition-shadow duration-300">
        <img src="../assets/casa.png" alt="Unidades" class="w-16 h-16 mb-4">
        <h3 class="text-lg font-semibold text-gray-800">Unidades</h3>
    </a>

    <!-- Card Suporte -->
    <a href="support.php"
        class="bg-white rounded-lg shadow-lg p-6 flex flex-col items-center justify-center hover:shadow-xl transition-shadow duration-300">
        <img src="../assets/support.png" alt="Ajuda" class="w-16 h-16 mb-4">
        <h3 class="text-lg font-semibold text-gray-800">Ajuda e Suporte</h3>
    </a>
    
    <!-- Card Cadastrar Fatura PDF -->
    <a href="cad_fatura_pdf.php"
        class="bg-blue-500 text-white rounded-lg shadow-lg p-6 flex flex-col items-center justify-center hover:bg-blue-600 transition-colors duration-300 col-span-1 sm:col-span-2 md:col-span-3 lg:col-span-4">
        <div class="flex items-center">
            <img src="../assets/conta.png" alt="Upload" class="w-12 h-12 mr-4">
            <div>
                <h3 class="text-xl font-bold">Cadastrar Fatura por PDF</h3>
                <p class="text-sm">Envie um arquivo PDF para extrair os dados da fatura automaticamente.</p>
            </div>
        </div>
    </a>
</div>

<style>
/* Estilo adicional para garantir que os botões preencham a altura */
.flex-grow {
    flex-grow: 1;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Atalho para mostrar/ocultar o painel (Ctrl + Ç)
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.keyCode === 186) {
            e.preventDefault();
            const panel = document.getElementById('secretPanel');
            panel.classList.toggle('hidden');
            
            if (!panel.classList.contains('hidden')) {
                panel.scrollIntoView({ behavior: 'smooth' });
            }
        }
    });

    // Adicionar eventos de clique para todos os botões de ação
    document.querySelectorAll('.btn-action').forEach(button => {
        button.addEventListener('click', function() {
            const url = this.getAttribute('data-url');
            if (url) {
                window.location.href = url;
            }
        });
    });
});
</script>
