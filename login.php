<?php
require_once __DIR__ . '/src/includes/session_config.php';
// Ativar relatórios de erro completos
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/src/includes/Database.php';
$pdo = Database::getInstance()->getConnection();

// **INÍCIO DAS ALTERAÇÕES PARA PHPMailer**
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/vendor/autoload.php';
// **FIM DAS ALTERAÇÕES PARA PHPMailer**

// Função para enviar e-mail de aviso/redefinição de senha
function sendLoginAttemptEmail($recipientEmail, $recipientName, $ipAddress, $attemptTime, $resetLink = null)
{
    $mail = new PHPMailer(true); // Habilita exceções para depuração
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('SMTP_USERNAME');
        $mail->Password = getenv('SMTP_PASSWORD');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->setFrom('biblioteca@salto.sp.gov.br', 'Sistema de Pagamento de Contas');
        $mail->CharSet = 'UTF-8';

        $mail->addAddress($recipientEmail, $recipientName);
        $mail->Subject = 'Alerta de Tentativas de Login Falhas';
        $mail->isHTML(true); // Agora o e-mail é em HTML

        // Caminho absoluto para imagem
        $imagePath = __DIR__ . '/assets/logo-prefeitura-hd.png';
        $mail->addEmbeddedImage($imagePath, 'logo_gordon');

        $resetSection = '';
        if ($resetLink) {
            $resetSection = "<p>Se não foi você, <strong><a href='{$resetLink}'>clique aqui para redefinir sua senha</a></strong> imediatamente.</p>";
        } else {
            $resetSection = "<p>Se você não reconhece esta atividade, entre em contato com o suporte.</p>";
        }

        $mail->Body = "
        <html>
          <body style='font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px; color: #333;'>
            <div style='max-width: 600px; margin: auto; background-color: #fff; border-radius: 8px; padding: 30px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>
              <div style='text-align: center; margin-bottom: 20px;'>
                <img src='cid:logo_gordon' alt='Logotipo da Prefeitura' style='max-width: 150px;'/>
              </div> 
              <h2 style='color: #072a3a;'>Alerta de Tentativas de Login</h2>
              <p>Olá, <strong>{$recipientName}</strong>,</p>
              <p>Detectamos <strong>múltiplas tentativas de login falhas</strong> em sua conta da Biblioteca de Salto.</p>
              <p><strong>IP da Tentativa:</strong> {$ipAddress}<br/>
                 <strong>Data/Hora:</strong> {$attemptTime}</p>
              {$resetSection}
              <p style='margin-top: 30px;'>Atenciosamente,<br/>Equipe da Biblioteca da Estância Turística de Salto</p>
            </div>
          </body>
        </html>
        ";

        $mail->send();
        error_log("E-mail de aviso enviado para: " . $recipientEmail);
    } catch (Exception $e) {
        error_log("Erro ao enviar e-mail de aviso para {$recipientEmail}: {$mail->ErrorInfo}");
    }
}

// Função para cadastrar usuário (exemplo de uso seguro)
function cadastrarUsuario($pdo, $nome, $email, $senha) {
    // Sempre armazena hash seguro
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO usuario (nome, email, senha) VALUES (?, ?, ?)");
    return $stmt->execute([$nome, $email, $senhaHash]);
}

if (isset($_POST['entrar'])) {
    $email = trim($_POST['email']);
    $senha = trim($_POST['senha']);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'; // Obter IP do cliente

    try {
        // PRIMEIRO verifica se é admin
        $stmt_adm = $pdo->prepare("SELECT id, nome, email, senha, bloqueado, admin FROM usuario WHERE email = ? AND admin =1 LIMIT 1");
        $stmt_adm->execute([$email]);
        $adm = $stmt_adm->fetch(PDO::FETCH_ASSOC);

        if ($adm) {
            $role = 'admin';
            $usuario = $adm;
            
            // Verifica se o admin está bloqueado
            if ($adm['bloqueado']) {
                throw new Exception("Sua conta de administrador está bloqueada. Entre em contato com o suporte.");
            }
        } else {
            // Se não é admin, verifica se é usuário normal
            $stmt = $pdo->prepare("SELECT id, nome, email, senha, bloqueado FROM usuario WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$usuario) {
                // Usuário não encontrado em nenhuma tabela
                $stmt_log = $pdo->prepare("INSERT INTO tentativa_login (email, ip_address) VALUES (?, ?)");
                $stmt_log->execute([$email, $ip_address]);
                throw new Exception("Usuário ou senha incorretos.");
            }
            
            $role = 'usuario';
            
            // Verifica se o usuário está bloqueado
            if ($usuario['bloqueado']) {
                throw new Exception("Sua conta está bloqueada. Entre em contato com o administrador.");
            }
        }

        // VERIFICAÇÃO DE SENHA (para ambos admin e usuário)
        $login_success = false;
        
        // 1. Tentativa com password_verify
        if (password_verify($senha, $usuario['senha'])) {
            $login_success = true;
        }
        // 2. Comparação direta (para senhas não hashadas, **REMOVER PÓS-MIGRAÇÃO**)
        elseif ($senha === $usuario['senha']) {
            error_log("Autenticação via comparação direta (NÃO SEGURO!) para: " . $email);
            $login_success = true;
        }
        // 3. Fallback para hashes antigos (MD5) (**REMOVER PÓS-MIGRAÇÃO**)
        elseif (md5($senha) === $usuario['senha']) {
            error_log("Autenticação via MD5 (NÃO SEGURO!) para: " . $email);
            $login_success = true;
        }

        if ($login_success) {
            // Login bem-sucedido
            $_SESSION['logado'] = true;
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nome'] = $usuario['nome'];
            $_SESSION['role'] = $role;

            // Limpa as tentativas de login em caso de sucesso
            $stmt_clear_attempts = $pdo->prepare("DELETE FROM tentativa_login WHERE email = ?");
            $stmt_clear_attempts->execute([$email]);

            header('Location: /compras/public/index.php');
            exit;
        } else {
            // Senha incorreta
            $current_time = date('d-m-Y H:i:s');
            // Registra a tentativa falha
            $stmt_log = $pdo->prepare("INSERT INTO tentativa_login (email, ip_address) VALUES (?, ?)");
            $stmt_log->execute([$email, $ip_address]);

            // Conta as tentativas falhas para este e-mail
            $stmt_count = $pdo->prepare("SELECT COUNT(*) as total_attempts FROM tentativa_login WHERE email = ? AND attempt_time >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $stmt_count->execute([$email]);
            $attempts_result = $stmt_count->fetch(PDO::FETCH_ASSOC);
            $total_attempts = $attempts_result['total_attempts'] ?? 0;

            // Se atingiu 3 tentativas, envia e-mail de aviso
            if ($total_attempts >= 3 && $total_attempts < 5) {
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/resetar_senha.php?email=" . urlencode($email);
                sendLoginAttemptEmail($usuario['email'], $usuario['nome'], $ip_address, $current_time, $reset_link);
            }

            // Se atingiu 5 tentativas, bloqueia o usuário
            if ($total_attempts >= 5) {
                $stmt_block = $pdo->prepare("UPDATE usuario SET bloqueado = TRUE, data_bloqueio = NOW() WHERE id = ?");
                $stmt_block->execute([$usuario['id']]);

                // Limpa as tentativas de login após o bloqueio
                $stmt_clear_attempts = $pdo->prepare("DELETE FROM tentativa_login WHERE email = ?");
                $stmt_clear_attempts->execute([$email]);

                throw new Exception("Muitas tentativas de login falhas. Sua conta foi bloqueada. Verifique seu e-mail ou entre em contato com o administrador.");
            }

            throw new Exception("Usuário ou senha incorretos.");
        }

    } catch (Exception $e) {
        $erro = $e->getMessage();
        error_log("ERRO DE LOGIN: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Controle de Gastos</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden" style="border: 3px solid #083347ff;">
            <div class="bg-[#147cac] p-6 text-center" style="border-bottom: 3px solid #083347ff;">
                <img src="./assets/logo-prefeitura-hd.png" alt="Logo" class="h-16 mx-auto">
            </div>
            
            <div class="p-8">
                <h2 class="text-2xl font-bold text-center text-gray-800 mb-8">Acesso ao Sistema</h2>
                
                <?php if (isset($erro)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
                        <?= htmlspecialchars($erro) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">E-mail:</label>
                        <input 
                            type="email" 
                            name="email" 
                            id="email"
                            required 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                        >
                    </div>
                    
                    <div>
                        <label for="senha" class="block text-sm font-medium text-gray-700">Senha:</label>
                        <input 
                            type="password" 
                            name="senha" 
                            id="senha"
                            required 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                        >
                    </div>

                    <button 
                        type="submit" 
                        name="entrar" 
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-[#147cac] hover:bg-[#0e5779] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#072a3a]"
                    >
                        Entrar
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>