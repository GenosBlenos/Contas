<?php
require_once __DIR__ . '/../src/includes/session_config.php';
session_start();
require_once __DIR__ . '/../src/includes/auth.php';
require_once __DIR__ . '/../src/includes/header.php';

if (!isset($_SESSION['logado']) || !$_SESSION['logado']) {
    header('Location: /Contas/login.php');
    exit;
}

$pageTitle = 'Suporte';

$content = <<<HTML
    <div class="space-y-6 md:space-y-8">
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Como Usar o Sistema</h2>
            <div class="prose max-w-none">
                <p class="text-gray-600">Este sistema foi desenvolvido para ajudar no controle e gerenciamento de despesas da prefeitura. Abaixo você encontrará instruções sobre como utilizar cada módulo:</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
            <!-- Água -->
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                <div class="flex items-center mb-4">
                    <img src="../assets/water.png" alt="Água" class="w-8 h-8 mr-3">
                    <h3 class="text-lg font-semibold text-gray-800">Água Predial</h3>
                </div>
                <p class="text-gray-600">Módulo para controle das contas de água dos prédios públicos. Registre consumo, valores e mantenha um histórico completo.</p>
            </div>

            <!-- Energia -->
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                <div class="flex items-center mb-4">
                    <img src="../assets/flash.png" alt="Energia" class="w-8 h-8 mr-3">
                    <h3 class="text-lg font-semibold text-gray-800">Energia Elétrica</h3>
                </div>
                <p class="text-gray-600">Controle do consumo de energia elétrica, incluindo demanda contratada e consumo em horário de ponta.</p>
            </div>

            <!-- Sem Parar -->
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                <div class="flex items-center mb-4">
                    <img src="../assets/car.png" alt="Sem Parar" class="w-8 h-8 mr-3">
                    <h3 class="text-lg font-semibold text-gray-800">Sem Parar</h3>
                </div>
                <p class="text-gray-600">Controle de despesas com pedágios e estacionamentos dos veículos oficiais equipados com Sem Parar.</p>
            </div>

            <!-- Telefone -->
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                <div class="flex items-center mb-4">
                    <img src="../assets/phone.png" alt="Telefone" class="w-8 h-8 mr-3">
                    <h3 class="text-lg font-semibold text-gray-800">Telefonia Fixa</h3>
                </div>
                <p class="text-gray-600">Gerenciamento das linhas telefônicas, controle de gastos e monitoramento do consumo por setor.</p>
            </div>

            <!-- Internet
            <div class="bg-gray-100 rounded-lg shadow-lg p-4 sm:p-6 opacity-75">
                    <div class="flex items-center mb-4">
                        <img src="../assets/wifi.png" alt="Internet" class="w-8 h-8 mr-3">
                        <h3 class="text-lg font-semibold text-gray-800">Internet Predial (Inativo)</h3>
                    </div>
                <p class="text-gray-600">Gerenciamento dos links de internet, controle de valores e monitoramento da disponibilidade por prédio.</p>
            </div> -->

            <!-- Relatórios -->
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                <div class="flex items-center mb-4">
                    <img src="../assets/report.png" alt="Relatórios" class="w-8 h-8 mr-3">
                    <h3 class="text-lg font-semibold text-gray-800">Relatórios</h3>
                </div>
                <p class="text-gray-600">Gerar relatórios detalhados sobre as despesas, consumo e variações mensais para análise e tomada de decisões.</p>
            </div>

            <!-- Recomendações -->
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                <div class="flex items-center mb-4">
                    <img src="../assets/recommendation.png" alt="Recomendações" class="w-8 h-8 mr-3">
                    <h3 class="text-lg font-semibold text-gray-800">Recomendações</h3>
                </div>
                <p class="text-gray-600">Análise de dados para identificar oportunidades de economia e otimização dos recursos.</p>
            </div>

            <!-- Unidades -->
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                <div class="flex items-center mb-4">
                    <img src="../assets/casa.png" alt="Unidades" class="w-8 h-8 mr-3">
                    <h3 class="text-lg font-semibold text-gray-800">Unidades</h3>
                </div>
                <p class="text-gray-600">Gerencie as unidades administrativas e prédios públicos vinculados ao sistema para melhor organização das despesas.</p>
            </div>

            <!-- Documentos -->
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                <div class="flex items-center mb-4">
                    <img src="../assets/documents.png" alt="Documentos" class="w-8 h-8 mr-3">
                    <h3 class="text-lg font-semibold text-gray-800">Documentos</h3>
                </div>
                <p class="text-gray-600">Gerencie os documentos relacionados às despesas, como faturas e contratos, para fácil acesso e organização.</p>
            </div>

            <!-- Paínel de Controle -->
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                <div class="flex items-center mb-4">
                    <img src="../assets/dashboard.png" alt="Painel de Controle" class="w-8 h-8 mr-3">
                    <h3 class="text-lg font-semibold text-gray-800">Painel de Controle</h3>
                </div>
                <p class="text-gray-600">Acesso rápido às principais métricas e status do sistema para monitoramento eficiente.</p>
            </div>

            <!-- Possíveis Futuros Módulos -->
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                <div class="flex items-center mb-4">
                    <img src="../assets/future.png" alt="Futuros Módulos" class="w-8 h-8 mr-3">
                    <h3 class="text-lg font-semibold text-gray-800">Possíveis Futuros Módulos</h3>
                </div>
                <p class="text-gray-600">Estamos sempre buscando melhorias. Futuramente, podemos adicionar módulos para verificar e apontar gastos desnecessários.</p> 
        </div>
    </div>
        <!-- Suporte -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-4">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Precisa de Ajuda?</h2>
            <div class="prose max-w-none">
                <p class="text-gray-600">Em caso de dúvidas ou problemas, entre em contato com o suporte técnico:</p>
                <ul class="list-disc list-inside mt-4 space-y-2 text-gray-600">
                    <li>Email: matheus_progra.ti@salto.sp.gov.br</li>
                    <li>Telefone: (11) 4602-8500 (Ramal 202)</li>
                    <li>Horário de atendimento: Segunda a Sexta, das 8h às 17h</li>
                </ul>
            </div>
        </div>

HTML;

require_once __DIR__ . '/../src/includes/template.php';
?>