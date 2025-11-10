function editarRegistro(id) {
    if (confirm('Deseja editar este registro?')) {
        // Implementar lógica de edição
        document.querySelector('form').action = `?edit=${id}`;
        // Carregar dados do registro no formulário
    }
}

function excluirRegistro(id) {
    if (confirm('Tem certeza que deseja excluir este registro?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="id" value="${id}">
            <input type="hidden" name="excluir" value="1">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Função para inicializar o gráfico de consumo
function inicializarGraficoConsumo(dadosConsumo) {
    const ctx = document.getElementById('consumoChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: dadosConsumo.map(item => item.mes),
            datasets: [{
                label: 'Consumo (m³)',
                data: dadosConsumo.map(item => item.total_consumo),
                borderColor: 'rgb(99, 102, 241)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Função para formatar valores monetários
function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(valor);
}

// Função para formatar números com decimais
function formatarNumero(numero, decimais = 2) {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: decimais,
        maximumFractionDigits: decimais
    }).format(numero);
}

// Função para formatar datas
function formatarData(data) {
    return new Date(data).toLocaleDateString('pt-BR');
}
