<?php
session_start();
require 'functions.php';

if (!empty($_SESSION['logado'])) {
    header('Location: index.php');
    exit;
}

$db = ler_json('db.json');
if (!isset($db['usuarios'])) {
    $db['usuarios'] = [];
}

$erro = '';
$sucesso = '';
$aba_ativa = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($acao === 'cadastrar') {
        if (empty($email) || empty($senha)) {
            $erro = 'Preencha todos os campos para cadastrar.';
            $aba_ativa = 'register';
        } else {
            $existe = false;
            foreach ($db['usuarios'] as $u) {
                if ($u['email'] === $email) { $existe = true; break; }
            }

            if ($existe) {
                $erro = 'Este e-mail já está em uso. Tente fazer login.';
                $aba_ativa = 'register';
            } else {
                $novo_id = gerar_id();
                $nome_base = explode('@', $email)[0];
                
                $db['usuarios'][] = [
                    'id' => $novo_id,
                    'email' => $email,
                    'nome' => ucfirst($nome_base),
                    'senha' => password_hash($senha, PASSWORD_DEFAULT)
                ];
                salvar_json('db.json', $db);
                $sucesso = 'Conta criada com sucesso! Digite sua senha abaixo para entrar.';
                $aba_ativa = 'login';
            }
        }
    } 
    elseif ($acao === 'login') {
        $logado = false;
        
        // Admin Master Hardcoded
        if ($email === 'admin' && $senha === 'admin123') {
            $_SESSION['logado'] = true;
            $_SESSION['usuario_id'] = 'user_1';
            $_SESSION['usuario_nome'] = 'Técnico Admin';
            header('Location: index.php'); exit;
        }

        foreach ($db['usuarios'] as $u) {
            if ($u['email'] === $email && password_verify($senha, $u['senha'])) {
                $_SESSION['logado'] = true;
                $_SESSION['usuario_id'] = $u['id'];
                $_SESSION['usuario_nome'] = $u['nome'];
                header('Location: index.php'); exit;
            }
        }
        
        if (!$logado) {
            $erro = 'E-mail ou senha incorretos.';
            $aba_ativa = 'login';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>L.P. TechOS</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🔧</text></svg>">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; }
    body { display: flex; min-height: 100vh; background: #0a0f1a; color: #f1f5f9; }
    
    .col-left { flex: 1; background: linear-gradient(135deg, #0a0f1a 0%, #020617 100%); display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px; border-right: 1px solid #1e293b; }
    .col-left .brand-icon { font-size: 4rem; margin-bottom: 18px; text-shadow: 0 0 15px rgba(0,229,255,0.4); }
    .col-left h1 { font-size: 2.6rem; font-weight: 800; letter-spacing: -1px; margin-bottom: 16px; text-align: center; }
    .col-left p { font-size: 1.05rem; color: #94a3b8; text-align: center; max-width: 360px; line-height: 1.6; }
    
    .col-left .features { margin-top: 36px; display: flex; flex-direction: column; gap: 14px; }
    .col-left .feature { display: flex; align-items: center; gap: 12px; font-size: 0.95rem; color: #cbd5e1; font-weight: 500; }
    .col-left .feature span.dot { width: 8px; height: 8px; border-radius: 50%; background: #00e5ff; flex-shrink: 0; box-shadow: 0 0 8px rgba(0, 229, 255, 0.5); }

    .col-right { flex: 1; display: flex; justify-content: center; align-items: center; padding: 40px; background: #0a0f1a; }
    .auth-container { background-color: #1e293b; width: 100%; max-width: 420px; border-radius: 12px; border: 1px solid #334155; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    
    .tabs { display: flex; border-bottom: 1px solid #334155; }
    .tab-btn { flex: 1; padding: 18px; background: transparent; border: none; color: #94a3b8; font-size: 1rem; font-weight: 600; cursor: pointer; transition: 0.3s; border-bottom: 2px solid transparent; }
    .tab-btn:hover { color: #f8fafc; }
    .tab-btn.active { color: #00e5ff; border-bottom: 2px solid #00e5ff; }

    .form-wrapper { padding: 30px; }
    .header-text { text-align: center; margin-bottom: 24px; }
    .header-text h2 { font-size: 1.4rem; color: #f8fafc; margin-bottom: 6px; }
    .header-text p { font-size: 0.85rem; color: #94a3b8; }

    .msg-erro { background: rgba(239, 68, 68, 0.1); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3); padding: 10px; border-radius: 6px; font-size: 0.85rem; margin-bottom: 15px; text-align: center; }
    .msg-sucesso { background: rgba(34, 197, 94, 0.1); color: #86efac; border: 1px solid rgba(34, 197, 94, 0.3); padding: 10px; border-radius: 6px; font-size: 0.85rem; margin-bottom: 15px; text-align: center; }

    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: #cbd5e1; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
    
    .input-password-wrapper { position: relative; display: flex; align-items: center; }
    .input-password-wrapper input, .form-group input[type="text"], .form-group input[type="email"] { width: 100%; background-color: #0f172a; border: 1px solid #334155; color: #f8fafc; padding: 12px 45px 12px 14px; border-radius: 6px; font-size: 0.95rem; outline: none; transition: border 0.3s; }
    .input-password-wrapper input:focus, .form-group input:focus { border-color: #00e5ff; }
    
    .btn-toggle-password { position: absolute; right: 14px; background: transparent; border: none; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 4px; transition: color 0.2s; }
    .btn-toggle-password:hover { color: #cbd5e1; }
    .btn-toggle-password svg { width: 20px; height: 20px; }

    .password-strength-wrapper { margin-top: 6px; display: flex; flex-direction: column; gap: 4px; }
    .strength-text { font-size: 0.78rem; font-weight: 600; color: #64748b; transition: color 0.3s; }
    .strength-bar-container { width: 100%; height: 4px; background-color: #0f172a; border-radius: 2px; overflow: hidden; }
    .strength-bar { width: 0%; height: 100%; transition: width 0.3s, background-color: 0.3s; border-radius: 2px; }

    .btn-submit { width: 100%; background-color: #334155; color: #94a3b8; border: none; padding: 14px; border-radius: 6px; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: 0.3s; margin-top: 10px; }
    .btn-submit:hover { background-color: #475569; color: #f8fafc; }

    .btn-primary { background-color: transparent; color: #00e5ff; border: 1.5px solid #00e5ff; }
    .btn-primary:hover { background-color: rgba(0, 229, 255, 0.1); box-shadow: 0 0 10px rgba(0, 229, 255, 0.2); color: #00e5ff; }

    .hidden { display: none; }

    @media(max-width: 900px) {
        body { flex-direction: column; }
        .col-left { padding: 30px 20px; border-right: none; border-bottom: 1px solid #1e293b; }
        .col-left h1 { font-size: 2rem; }
        .col-right { padding: 30px 20px; }
    }
</style>
</head>
<body>

<div class="col-left">
    <div class="brand-icon">🔧</div>
    <h1><span style="color:#fff;">L.P. Tech</span><span style="color:#00e5ff;">OS</span></h1>
    <p>Plataforma avançada para gestão de ordens de serviço e assistência técnica.</p>
    <div class="features">
        <div class="feature"><span class="dot"></span> Controle de clientes e equipamentos</div>
        <div class="feature"><span class="dot"></span> Gestão de status de manutenção</div>
        <div class="feature"><span class="dot"></span> Histórico de serviços prestados</div>
        <div class="feature"><span class="dot"></span> Cálculo automático de faturamento</div>
    </div>
</div>

<div class="col-right">
    <div class="auth-container">
        <div class="tabs">
            <button class="tab-btn <?= $aba_ativa === 'login' ? 'active' : '' ?>" id="btn-tab-login" onclick="switchTab('login')">Acesso Técnico</button>
            <button class="tab-btn <?= $aba_ativa === 'register' ? 'active' : '' ?>" id="btn-tab-register" onclick="switchTab('register')">Cadastrar</button>
        </div>

        <div class="form-wrapper">
            <?php if ($erro): ?><div class="msg-erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
            <?php if ($sucesso): ?><div class="msg-sucesso"><?= htmlspecialchars($sucesso) ?></div><?php endif; ?>

            <form id="form-login" method="POST" action="login.php" class="<?= $aba_ativa === 'login' ? '' : 'hidden' ?>">
                <input type="hidden" name="acao" value="login">
                <div class="header-text">
                    <h2>Área do Colaborador</h2>
                    <p>Entre no sistema para gerenciar suas OS.</p>
                </div>
                <div class="form-group">
                    <label>E-mail Corporativo</label>
                    <input type="text" name="email" placeholder="tecnico@lptech.com" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label>Senha de Acesso</label>
                    <div class="input-password-wrapper">
                        <input type="password" name="senha" id="login-password" placeholder="••••••••" required autocomplete="current-password">
                        <button type="button" class="btn-toggle-password" onclick="togglePasswordVisibility('login-password')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-submit">Acessar Sistema</button>
            </form>

            <form id="form-register" method="POST" action="login.php" class="<?= $aba_ativa === 'register' ? '' : 'hidden' ?>">
                <input type="hidden" name="acao" value="cadastrar">
                <div class="header-text">
                    <h2>Novo Técnico</h2>
                    <p>Cadastre-se para acessar a plataforma.</p>
                </div>
                <div class="form-group">
                    <label>E-mail</label>
                    <input type="email" name="email" placeholder="tecnico@email.com" required autocomplete="email">
                </div>
                <div class="form-group">
                    <label>Senha Segura</label>
                    <div class="input-password-wrapper">
                        <input type="password" name="senha" id="register-password" placeholder="Crie uma senha forte" required autocomplete="new-password" oninput="checkPasswordStrength(this.value)">
                        <button type="button" class="btn-toggle-password" onclick="togglePasswordVisibility('register-password')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <div class="password-strength-wrapper">
                        <span class="strength-text" id="strength-label">Força da senha</span>
                        <div class="strength-bar-container">
                            <div class="strength-bar" id="strength-bar"></div>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn-submit btn-primary">Registrar Técnico</button>
            </form>
        </div>
    </div>
</div>

<script>
    function switchTab(tab) {
        const formLogin = document.getElementById('form-login');
        const formRegister = document.getElementById('form-register');
        const btnLogin = document.getElementById('btn-tab-login');
        const btnRegister = document.getElementById('btn-tab-register');

        const msgs = document.querySelectorAll('.msg-erro, .msg-sucesso');
        msgs.forEach(msg => msg.style.display = 'none');

        if (tab === 'login') {
            formLogin.classList.remove('hidden');
            formRegister.classList.add('hidden');
            btnLogin.classList.add('active');
            btnRegister.classList.remove('active');
        } else {
            formLogin.classList.add('hidden');
            formRegister.classList.remove('hidden');
            btnLogin.classList.remove('active');
            btnRegister.classList.add('active');
        }
    }

    function togglePasswordVisibility(inputId) {
        const input = document.getElementById(inputId);
        const button = input.nextElementSibling;
        if (input.type === 'password') {
            input.type = 'text';
            button.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 19c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`;
        } else {
            input.type = 'password';
            button.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
        }
    }

    function checkPasswordStrength(password) {
        const bar = document.getElementById('strength-bar');
        const label = document.getElementById('strength-label');
        if (password.length === 0) { bar.style.width = '0%'; label.innerText = 'Força da senha'; label.style.color = '#64748b'; return; }
        let score = 0;
        if (password.length >= 6) score++; 
        if (password.length >= 10) score++; 
        if (/[A-Z]/.test(password)) score++; 
        if (/[0-9]/.test(password)) score++; 
        if (/[^A-Za-z0-9]/.test(password)) score++; 
        if (score <= 2) { bar.style.width = '33%'; bar.style.backgroundColor = '#ef4444'; label.innerText = 'Senha Fraca'; label.style.color = '#ef4444'; } 
        else if (score === 3 || score === 4) { bar.style.width = '66%'; bar.style.backgroundColor = '#f59e0b'; label.innerText = 'Senha Média'; label.style.color = '#f59e0b'; } 
        else { bar.style.width = '100%'; bar.style.backgroundColor = '#10b981'; label.innerText = 'Senha Forte'; label.style.color = '#10b981'; }
    }
</script>
</body>
</html>