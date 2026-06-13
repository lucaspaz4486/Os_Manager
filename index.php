<?php
session_start();
require 'functions.php';
verificar_sessao();

$usuario_id = $_SESSION['usuario_id'];
$is_admin = in_array($usuario_id, ['user_1', '6a2cd0df8e1404.73139125']);

$db = ler_json('db.json');
$erro_os = '';
$erro_perfil = '';
$sucesso_perfil = '';

if (!isset($db['ordens'])) $db['ordens'] = [];
if (!isset($db['usuarios'])) $db['usuarios'] = [];

function renderStatusBadge($status) {
    $badges = [
        'disponivel' => '<span class="badge" style="background:rgba(16, 185, 129, 0.15); color:#10b981; border: 1px solid rgba(16, 185, 129, 0.3);">🟢 Disponível</span>',
        'ocupado'    => '<span class="badge" style="background:rgba(239, 68, 68, 0.15); color:#ef4444; border: 1px solid rgba(239, 68, 68, 0.3);">🔴 Ocupado</span>',
        'pausa'      => '<span class="badge" style="background:rgba(148, 163, 184, 0.15); color:#94a3b8; border: 1px solid rgba(148, 163, 184, 0.3);">☕ Em Pausa</span>',
        'banheiro'   => '<span class="badge" style="background:rgba(168, 85, 247, 0.15); color:#a855f7; border: 1px solid rgba(168, 85, 247, 0.3);">🧻 Troca de Óleo</span>',
        'ausente'    => '<span class="badge" style="background:rgba(71, 85, 105, 0.2); color:#cbd5e1; border: 1px solid rgba(71, 85, 105, 0.4);">⚫ Offline / Ausente</span>'
    ];
    return $badges[$status] ?? $badges['ausente'];
}

if (isset($_GET['excluir_os'])) {
    if ($is_admin) {
        $id = $_GET['excluir_os'];
        $db['ordens'] = array_values(array_filter($db['ordens'], fn($o) => $o['id'] !== $id));
        salvar_json('db.json', $db);
    }
    header('Location: index.php#lista-os'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'os') {
    $acao = $_POST['acao'] ?? 'novo';
    $cliente = trim($_POST['cliente'] ?? '');
    $equipamento = trim($_POST['equipamento'] ?? '');
    $defeito = trim($_POST['defeito'] ?? '');
    $status = $_POST['status'] ?? 'pendente';
    $valor = floatval(str_replace(',', '.', $_POST['valor'] ?? '0'));
    $data_entrada = $_POST['data_entrada'] ?? date('Y-m-d');

    if (!$cliente || !$equipamento || !$defeito) {
        $erro_os = 'Preencha Cliente, Equipamento e Defeito.';
    } else {
        if ($acao === 'editar') {
            $edit_id = $_POST['edit_id'] ?? '';
            foreach ($db['ordens'] as &$o) {
                if ($o['id'] === $edit_id) {
                    $o['cliente'] = $cliente;
                    $o['equipamento'] = $equipamento;
                    $o['defeito'] = $defeito;
                    $o['valor'] = $valor;
                    $o['data_entrada'] = $data_entrada;
                    
                    if ($status === 'concluida' && ($o['status'] ?? '') !== 'concluida') {
                        $o['tecnico_conclusao_id'] = $usuario_id;
                        $o['tecnico_conclusao_nome'] = $_SESSION['usuario_nome'];
                    } elseif ($status !== 'concluida') {
                        unset($o['tecnico_conclusao_id'], $o['tecnico_conclusao_nome']);
                    }
                    $o['status'] = $status;
                    break;
                }
            }
            unset($o);
        } else {
            $nova_os = [
                'id' => gerar_id(),
                'usuario_id' => $usuario_id,
                'tecnico_abertura' => $_SESSION['usuario_nome'],
                'cliente' => $cliente,
                'equipamento' => $equipamento,
                'defeito' => $defeito,
                'status' => $status,
                'valor' => $valor,
                'data_entrada' => $data_entrada,
            ];
            if ($status === 'concluida') {
                $nova_os['tecnico_conclusao_id'] = $usuario_id;
                $nova_os['tecnico_conclusao_nome'] = $_SESSION['usuario_nome'];
            }
            $db['ordens'][] = $nova_os;
        }
        salvar_json('db.json', $db);
        header('Location: index.php#lista-os'); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'perfil') {
    $novo_nome = trim($_POST['nome'] ?? '');
    $avatar = $_POST['avatar'] ?? '👨‍💻';
    $status_op = $_POST['status_operacional'] ?? 'ausente';
    $especialidades_array = $_POST['especialidades_pre'] ?? [];
    $especialidade_custom = trim($_POST['especialidade_custom'] ?? '');
    
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';

    $todas_esp = $especialidades_array;
    if ($especialidade_custom !== '') { $todas_esp[] = $especialidade_custom; }
    $especialidades_finais = implode(', ', $todas_esp);

    $processar_alteracoes = true;

    foreach ($db['usuarios'] as &$u) {
        if ($u['id'] === $usuario_id) {
            
            if (!empty($senha_atual) || !empty($nova_senha)) {
                if (empty($senha_atual) || empty($nova_senha)) {
                    $_SESSION['msg_erro_perfil'] = 'Para alterar a senha, preencha a senha atual e a nova senha.';
                    $processar_alteracoes = false;
                } elseif (!password_verify($senha_atual, $u['senha'])) {
                    $_SESSION['msg_erro_perfil'] = 'Senha atual incorreta. Alterações abortadas.';
                    $processar_alteracoes = false;
                } else {
                    $u['senha'] = password_hash($nova_senha, PASSWORD_DEFAULT);
                    $_SESSION['msg_sucesso_perfil'] = 'Senha atualizada com sucesso!';
                }
            }

            if ($processar_alteracoes) {
                if ($novo_nome) {
                    $u['nome'] = $novo_nome;
                    $_SESSION['usuario_nome'] = $novo_nome;
                }
                $u['avatar'] = $avatar;
                $u['status_operacional'] = $status_op;
                $u['especialidades'] = $especialidades_finais;
                if (!isset($_SESSION['msg_sucesso_perfil'])) {
                    $_SESSION['msg_sucesso_perfil'] = 'Perfil atualizado com sucesso!';
                }
            }
            break;
        }
    }
    unset($u);
    if ($processar_alteracoes) { salvar_json('db.json', $db); }
    header('Location: index.php?open_profile=1'); exit;
}

if (isset($_SESSION['msg_erro_perfil'])) { $erro_perfil = $_SESSION['msg_erro_perfil']; unset($_SESSION['msg_erro_perfil']); }
if (isset($_SESSION['msg_sucesso_perfil'])) { $sucesso_perfil = $_SESSION['msg_sucesso_perfil']; unset($_SESSION['msg_sucesso_perfil']); }

$todas_ordens = $db['ordens']; 
usort($todas_ordens, fn($a,$b) => strcmp($b['data_entrada'], $a['data_entrada']));

$total_pendentes = 0; $total_andamento = 0; $total_concluidas = 0; $faturamento = 0;
foreach ($todas_ordens as $o) {
    if ($o['status'] === 'pendente') $total_pendentes++;
    if ($o['status'] === 'andamento') $total_andamento++;
    if ($o['status'] === 'concluida') {
        $total_concluidas++;
        $faturamento += $o['valor'];
    }
}

// ==========================================
// CÁLCULO DA CAMPANHA MOTIVACIONAL (GAMIFICAÇÃO)
// ==========================================
$meta_semanal = 15; // Meta de OS concluídas na semana
$concluidas_semana = 0;
$ranking_tecnicos = [];

// Prepara array de técnicos
foreach ($db['usuarios'] as $u) {
    if ($u['email'] !== 'admin') {
        $ranking_tecnicos[$u['id']] = ['nome' => explode(' ', $u['nome'])[0], 'avatar' => $u['avatar'] ?? '👨‍💻', 'pontos' => 0];
    }
}

// Conta as OS concluídas por cada técnico
foreach ($todas_ordens as $o) {
    if ($o['status'] === 'concluida' && !empty($o['tecnico_conclusao_id'])) {
        $concluidas_semana++;
        if (isset($ranking_tecnicos[$o['tecnico_conclusao_id']])) {
            $ranking_tecnicos[$o['tecnico_conclusao_id']]['pontos']++;
        }
    }
}

// Ordena o ranking do maior para o menor e pega o Top 3
usort($ranking_tecnicos, fn($a, $b) => $b['pontos'] <=> $a['pontos']);
$top_tecnicos = array_slice(array_filter($ranking_tecnicos, fn($t) => $t['pontos'] > 0), 0, 3);

$porcentagem_meta = min(100, ($concluidas_semana / $meta_semanal) * 100);
// ==========================================

$editando_os = null;
if (isset($_GET['editar_os'])) {
    $eid = $_GET['editar_os'];
    foreach ($todas_ordens as $o) { if ($o['id'] === $eid) { $editando_os = $o; break; } }
}

$meu_perfil = null;
foreach($db['usuarios'] as $u) { if($u['id'] === $usuario_id) { $meu_perfil = $u; break; } }
$meu_avatar = $meu_perfil['avatar'] ?? '👨‍💻';
$meu_status = $meu_perfil['status_operacional'] ?? 'ausente'; 
$minhas_esps = $meu_perfil['especialidades'] ?? '';
$usuario_nome = $_SESSION['usuario_nome'];

$grafico_labels = []; $grafico_logado = []; $grafico_pausa = [];
foreach ($db['usuarios'] as $u) {
    if ($u['email'] === 'admin') continue;
    $nome_curto = explode(' ', $u['nome'])[0];
    $grafico_labels[] = $nome_curto;
    $grafico_logado[] = rand(5, 8); 
    $grafico_pausa[] = rand(1, 2);  
}
if (count($grafico_labels) < 2) {
    array_push($grafico_labels, 'Matheus', 'Carlos');
    array_push($grafico_logado, 7.25, 6.5);
    array_push($grafico_pausa, 1.5, 0.75); 
}

include 'layout.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>L.P. TechOS — Painel Geral</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🔧</text></svg>">
<link rel="stylesheet" href="style.css">
<style>
/* CSS EXCLUSIVO PARA O RANKING MOTIVACIONAL */
.campanha-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
.meta-bar-bg { background: #0f172a; border-radius: 20px; height: 18px; width: 100%; border: 1px solid #334155; overflow: hidden; margin: 15px 0; position: relative; }
.meta-bar-fill { background: linear-gradient(90deg, #00e5ff, #3b82f6); height: 100%; transition: width 1s ease-in-out; border-radius: 20px; }
.ranking-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: rgba(15,23,42,0.4); border-radius: 8px; margin-bottom: 8px; border: 1px solid #334155; }
.rank-0 { border-color: #fbbf24; background: rgba(251, 191, 36, 0.08); box-shadow: inset 0 0 10px rgba(251, 191, 36, 0.1); }
.rank-1 { border-color: #94a3b8; background: rgba(148, 163, 184, 0.08); }
.rank-2 { border-color: #b45309; background: rgba(180, 83, 9, 0.08); }
.podium-pos { font-size: 1.5rem; width: 40px; text-align: center; }
.premio-badge { font-size: 0.75rem; padding: 4px 8px; border-radius: 4px; font-weight: 700; }
.premio-0 { background: #fef3c7; color: #b45309; }
.premio-1 { background: #f1f5f9; color: #475569; }
.premio-2 { background: #ffedd5; color: #92400e; }
@media (max-width: 900px) { .campanha-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<div class="profile-dropdown-overlay <?= isset($_GET['open_profile']) ? 'active' : '' ?>" id="profile-overlay" onclick="toggleProfilePanel()"></div>
<div class="profile-panel <?= isset($_GET['open_profile']) ? 'active' : '' ?>" id="profile-panel">
    <button class="close-panel" onclick="toggleProfilePanel()">×</button>
    <h2 style="font-size:1.2rem; color:#f8fafc; margin-bottom: 15px; border-bottom: 1px solid #334155; padding-bottom: 10px;">⚙️ Configuração de Perfil</h2>
    
    <?php if ($erro_perfil): ?><div class="alert alert-erro" style="background:rgba(239,68,68,0.1); color:#f87171; padding:8px; border-radius:6px; font-size:0.8rem; margin-bottom:12px; border:1px solid rgba(239,68,68,0.2); text-align:center;"><?= htmlspecialchars($erro_perfil) ?></div><?php endif; ?>
    <?php if ($sucesso_perfil): ?><div class="alert alert-sucesso" style="background:rgba(16,185,129,0.1); color:#34d399; padding:8px; border-radius:6px; font-size:0.8rem; margin-bottom:12px; border:1px solid rgba(16,185,129,0.2); text-align:center;"><?= htmlspecialchars($sucesso_perfil) ?></div><?php endif; ?>

    <form method="POST" action="index.php">
        <input type="hidden" name="form_type" value="perfil">
        <div class="form-group">
            <label>Nome de Exibição</label>
            <input type="text" name="nome" value="<?= htmlspecialchars($usuario_nome) ?>" required>
        </div>
        <div class="form-group" style="margin-top:10px;">
            <label>Status Operacional</label>
            <select name="status_operacional">
                <option value="disponivel" <?= $meu_status==='disponivel'?'selected':'' ?>>🟢 Disponível (Pegando OS)</option>
                <option value="ocupado" <?= $meu_status==='ocupado'?'selected':'' ?>>🔴 Ocupado (Na Bancada)</option>
                <option value="pausa" <?= $meu_status==='pausa'?'selected':'' ?>>☕ Em Pausa (Horário de Almoço)</option>
                <option value="banheiro" <?= $meu_status==='banheiro'?'selected':'' ?>>🧻 Troca de Óleo (Banheiro)</option>
                <option value="ausente" <?= $meu_status==='ausente'?'selected':'' ?>>⚫ Offline / Ausente</option>
            </select>
        </div>

        <div style="margin-top:15px; background:rgba(15,23,42,0.4); border:1px solid #334155; padding:12px; border-radius:8px;">
            <label style="font-size:.75rem; font-weight:700; color:#00e5ff; text-transform:uppercase; display:block; margin-bottom:8px;">🔐 Alterar Senha</label>
            <div class="form-group">
                <label style="font-size:0.7rem; color:#94a3b8;">Senha Atual</label>
                <div class="input-password-wrapper">
                    <input type="password" name="senha_atual" id="profile-old-pass" placeholder="Digite a senha atual" style="background:#0a0f1a;">
                    <button type="button" class="btn-toggle-password" onclick="togglePasswordVisibility('profile-old-pass')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px; height:16px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
            </div>
            <div class="form-group" style="margin-top:8px;">
                <label style="font-size:0.7rem; color:#94a3b8;">Nova Senha</label>
                <div class="input-password-wrapper">
                    <input type="password" name="nova_senha" id="profile-new-pass" placeholder="Mínimo 6 caracteres" style="background:#0a0f1a;" oninput="checkPasswordStrength(this.value)">
                    <button type="button" class="btn-toggle-password" onclick="togglePasswordVisibility('profile-new-pass')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px; height:16px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
                <div class="password-strength-wrapper">
                    <span class="strength-text" id="strength-label" style="font-size:0.7rem;">Força da nova senha</span>
                    <div class="strength-bar-container"><div class="strength-bar" id="strength-bar"></div></div>
                </div>
            </div>
        </div>

        <div class="form-group" style="margin-top:10px;">
            <label>Avatar</label>
            <div class="avatar-selector">
            <?php $avatares = ['👨‍💻', '👩‍💻', '🛠️', '📱', '💻', '🔋', '🤖', '👾']; ?>
            <?php foreach($avatares as $av): ?>
                <div>
                <input type="radio" id="av_<?= $av ?>" name="avatar" value="<?= $av ?>" <?= $meu_avatar===$av?'checked':'' ?>>
                <label for="av_<?= $av ?>"><?= $av ?></label>
                </div>
            <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group" style="margin-top:15px;">
            <label>Minhas Especialidades</label>
            <div class="checkbox-grid">
            <?php 
                $categorias_pre = ['Reparo de Placa-mãe', 'Solda BGA / SMD', 'Troca de Tela / Display', 'Formatação e Software', 'Limpeza Interna', 'Upgrade de Hardware'];
                foreach($categorias_pre as $cat): 
                $checked = strpos($minhas_esps, $cat) !== false ? 'checked' : '';
            ?>
                <label class="checkbox-item">
                <input type="checkbox" name="especialidades_pre[]" value="<?= $cat ?>" <?= $checked ?>>
                <span><?= $cat ?></span>
                </label>
            <?php endforeach; ?>
            </div>
        </div>
        <div class="form-group" style="margin-top:10px;">
            <label>Outras Especialidades</label>
            <input type="text" name="especialidade_custom" placeholder="Ex: Videogames..." value="">
        </div>
        <button type="submit" class="btn btn-primary btn-block" style="background:#00e5ff; color:#0a0f1a; margin-top:20px; width: 100%;">Salvar Alterações</button>
    </form>
</div>

<div class="app-wrapper">
  <?php renderHeader(); ?>

  <div class="main-content">
    <div class="content-area">

      <section id="dashboard">
        <div class="topbar">
          <h1 class="page-title">Métricas Gerais da Oficina</h1>
          
          <div class="profile-trigger" onclick="toggleProfilePanel()" title="Clique para editar seu perfil">
              <span style="font-size:1.8rem;"><?= $meu_avatar ?></span>
              <div style="display:flex; flex-direction:column; align-items:flex-end;">
                  <span style="color:#f8fafc; font-weight:700; font-size: 0.95rem;">
                      <?= htmlspecialchars($usuario_nome) ?> <?= $is_admin ? '👑' : '' ?>
                  </span>
                  <?= renderStatusBadge($meu_status) ?>
              </div>
          </div>
        </div>

        <div class="cards-grid">
          <div class="card" style="border-left-color: #f59e0b;">
            <div class="card-icon" style="background: #fef3c7; color: #d97706;">⌛</div>
            <div class="card-body">
              <span class="card-label">Aparelhos na Fila</span>
              <span class="card-value"><?= $total_pendentes ?></span>
            </div>
          </div>
          <div class="card" style="border-left-color: #00e5ff;">
            <div class="card-icon" style="background: rgba(0,229,255,0.2); color: #00bcd4;">⚙️</div>
            <div class="card-body">
              <span class="card-label">Bancadas Ocupadas</span>
              <span class="card-value"><?= $total_andamento ?></span>
            </div>
          </div>
          
          <?php if ($is_admin): ?>
          <div class="card" style="border-left-color: #10b981;">
            <div class="card-icon" style="background: #d1fae5; color: #059669;">💰</div>
            <div class="card-body">
              <span class="card-label">Faturamento Total</span>
              <span class="card-value"><?= formatar_moeda($faturamento) ?></span>
            </div>
          </div>
          <?php else: ?>
          <div class="card" style="border-left-color: #10b981;">
            <div class="card-icon" style="background: #d1fae5; color: #059669;">✅</div>
            <div class="card-body">
              <span class="card-label">Total Concluídos</span>
              <span class="card-value"><?= $total_concluidas ?> Aparelhos</span>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </section>

      <section id="nova-os">
        <div class="topbar"><h1 class="page-title">Gerenciar Fila de Trabalho</h1></div>
        <div class="panel">
          <div class="panel-header"><?= $editando_os ? 'Atualizar/Validar Serviço' : 'Dar Entrada em Equipamento' ?></div>
          <div class="panel-body">
            <form method="POST" action="index.php#lista-os" class="form-grid">
              <input type="hidden" name="form_type" value="os">
              <input type="hidden" name="acao" value="<?= $editando_os ? 'editar' : 'novo' ?>">
              <?php if ($editando_os): ?><input type="hidden" name="edit_id" value="<?= $editando_os['id'] ?>"><?php endif; ?>
              
              <div class="form-group" style="grid-column: span 2;">
                <label>Nome do Cliente</label>
                <input type="text" name="cliente" required value="<?= htmlspecialchars($editando_os['cliente'] ?? '') ?>">
              </div>
              <div class="form-group" style="grid-column: span 2;">
                <label>Equipamento (Modelo)</label>
                <input type="text" name="equipamento" required value="<?= htmlspecialchars($editando_os['equipamento'] ?? '') ?>">
              </div>
              <div class="form-group" style="grid-column: span 4;">
                <label>Defeito Relatado / Diagnóstico</label>
                <input type="text" name="defeito" required value="<?= htmlspecialchars($editando_os['defeito'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>Status</label>
                <select name="status">
                  <option value="pendente" <?= ($editando_os['status'] ?? '') === 'pendente' ? 'selected' : '' ?>>Pendente (Aguardando)</option>
                  <option value="andamento" <?= ($editando_os['status'] ?? '') === 'andamento' ? 'selected' : '' ?>>Em Manutenção (Bancada)</option>
                  <option value="concluida" <?= ($editando_os['status'] ?? '') === 'concluida' ? 'selected' : '' ?>>Concluído / Reparo Validado</option>
                </select>
              </div>
              <div class="form-group">
                <label>Valor (R$)</label>
                <input type="number" name="valor" step="0.01" value="<?= $editando_os ? number_format($editando_os['valor'], 2, '.', '') : '' ?>">
              </div>
              <div class="form-group">
                <label>Data</label>
                <input type="date" name="data_entrada" required value="<?= htmlspecialchars($editando_os['data_entrada'] ?? date('Y-m-d')) ?>">
              </div>
              <div class="form-group form-actions" style="grid-column: span 4;">
                <button type="submit" class="btn btn-primary" style="background:#00e5ff; color:#0a0f1a;"><?= $editando_os ? 'Salvar Alteração' : 'Adicionar à Fila' ?></button>
                <?php if ($editando_os): ?><a href="index.php#lista-os" class="btn btn-secondary">Cancelar</a><?php endif; ?>
              </div>
            </form>
          </div>
        </div>
      </section>

      <section id="lista-os">
        <div class="topbar"><h1 class="page-title">Painel Geral de Serviços</h1></div>
        <div class="panel">
          <div class="panel-body table-wrap">
            <?php if (empty($todas_ordens)): ?>
              <p class="empty-msg">Nenhum equipamento em manutenção.</p>
            <?php else: ?>
            <table class="data-table">
              <thead>
                <tr>
                    <th>Cliente / Defeito</th>
                    <th>Aparelho</th>
                    <th>Situação</th>
                    <?php if ($is_admin): ?><th>Valor</th><?php endif; ?>
                    <th>Entrada</th>
                    <th>Auditoria (Técnico)</th>
                    <th>Ações</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($todas_ordens as $o): ?>
                <tr>
                  <td style="font-weight:600;"><?= htmlspecialchars($o['cliente']) ?><br><small style="color:#94a3b8; font-weight:normal;"><?= htmlspecialchars($o['defeito']) ?></small></td>
                  <td><?= htmlspecialchars($o['equipamento']) ?></td>
                  <td>
                    <?php 
                      if ($o['status'] === 'pendente') echo '<span class="badge" style="background:#fef3c7; color:#d97706;">Aguardando</span>';
                      if ($o['status'] === 'andamento') echo '<span class="badge" style="background:rgba(0,229,255,0.1); color:#00bcd4;">Na Bancada</span>';
                      if ($o['status'] === 'concluida') echo '<span class="badge" style="background:#d1fae5; color:#059669;">Concluído</span>';
                    ?>
                  </td>
                  
                  <?php if ($is_admin): ?>
                  <td><?= formatar_moeda($o['valor']) ?></td>
                  <?php endif; ?>
                  
                  <td><?= date('d/m/Y', strtotime($o['data_entrada'])) ?></td>
                  <td>
                    <?php if ($o['status'] === 'concluida' && !empty($o['tecnico_conclusao_nome'])): ?>
                      <span style="color:#10b981; font-weight:700;">⚙️ <?= htmlspecialchars($o['tecnico_conclusao_nome']) ?></span>
                    <?php elseif ($o['status'] === 'andamento'): ?>
                      <span style="color:#00e5ff; font-size:0.85rem;">Em reparo...</span>
                    <?php else: ?>
                      <span style="color:#94a3b8; font-size:0.85rem;">Livre na fila</span>
                    <?php endif; ?>
                  </td>
                  <td class="acoes">
                    <a href="index.php?editar_os=<?= urlencode($o['id']) ?>#nova-os" class="btn btn-sm btn-edit">Editar / Assumir</a>
                    <?php if ($is_admin): ?>
                    <a href="index.php?excluir_os=<?= urlencode($o['id']) ?>#lista-os" class="btn btn-sm btn-delete" onclick="return confirm('Deletar Ordem definitivamente?')">Excluir</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section id="graficos-e-metas">
          <div class="panel panel-chart" style="margin-bottom: 20px;">
              <div class="panel-header">Produtividade da Equipe (Tempo de Operação vs Pausas)</div>
              <div class="panel-body" style="padding-top: 30px;">
                  <canvas id="chartProdutividade" height="120"></canvas>
              </div>
          </div>

          <div class="campanha-grid">
              <div class="panel" style="margin-bottom: 0;">
                  <div class="panel-header" style="color: #00e5ff;">🚀 Meta Coletiva da Semana</div>
                  <div class="panel-body">
                      <p style="color:#cbd5e1; font-size:0.95rem; line-height:1.5;">Trabalho em equipe! Se a oficina atingir <strong><?= $meta_semanal ?> aparelhos concluídos</strong> na semana, a empresa paga a rodada de pizza na sexta-feira!</p>
                      
                      <div class="meta-bar-bg">
                          <div class="meta-bar-fill" style="width: <?= $porcentagem_meta ?>%;"></div>
                      </div>
                      
                      <div style="display:flex; justify-content:space-between; align-items:center; font-weight:700;">
                          <span style="color:#3b82f6;"><?= $concluidas_semana ?> Concluídos</span>
                          <span style="color:#94a3b8;">Alvo: <?= $meta_semanal ?></span>
                      </div>

                      <?php if ($concluidas_semana >= $meta_semanal): ?>
                          <div style="margin-top:15px; padding:10px; background:rgba(16, 185, 129, 0.15); border:1px solid #10b981; border-radius:8px; text-align:center; color:#10b981; font-weight:700;">
                              🎉 META BATIDA! Pizza liberada! 🍕
                          </div>
                      <?php endif; ?>
                  </div>
              </div>

              <div class="panel" style="margin-bottom: 0;">
                  <div class="panel-header" style="color: #fbbf24;">🏆 Ranking de Desempenho (Top 3)</div>
                  <div class="panel-body" style="padding: 12px;">
                      <?php 
                        $premios = [
                            'Bônus Pix R$ 150',
                            'Vale iFood R$ 50',
                            'Energético Monster'
                        ];
                        $medalhas = ['🥇', '🥈', '🥉'];
                        
                        if (empty($top_tecnicos)): 
                            echo '<p style="color:#94a3b8; text-align:center; padding:20px;">Nenhum serviço concluído ainda.</p>';
                        else:
                            foreach ($top_tecnicos as $index => $tec):
                      ?>
                      <div class="ranking-item rank-<?= $index ?>">
                          <div style="display:flex; align-items:center; gap:12px;">
                              <span class="podium-pos"><?= $medalhas[$index] ?></span>
                              <span style="font-size:1.8rem;"><?= $tec['avatar'] ?></span>
                              <div>
                                  <div style="color:#f8fafc; font-weight:700; font-size:1rem;"><?= htmlspecialchars($tec['nome']) ?></div>
                                  <div style="color:#94a3b8; font-size:0.8rem;"><?= $tec['pontos'] ?> Aparelhos Consertados</div>
                              </div>
                          </div>
                          <div style="text-align:right;">
                              <span class="premio-badge premio-<?= $index ?>"><?= $premios[$index] ?></span>
                          </div>
                      </div>
                      <?php endforeach; endif; ?>
                  </div>
              </div>
          </div>
      </section>

      <section id="equipe">
        <div class="topbar"><h1 class="page-title">Quadro de Competências da Oficina</h1></div>
        <div class="panel">
          <div class="panel-body table-wrap">
            <table class="data-table">
              <thead>
                <tr><th>Profissional</th><th>Especialidades Cadastradas</th></tr>
              </thead>
              <tbody>
                <?php foreach ($db['usuarios'] as $u): ?>
                  <tr>
                    <td style="width: 250px;">
                      <div class="team-member">
                        <span class="team-avatar"><?= $u['avatar'] ?? '👨‍💻' ?></span>
                        <div class="team-info">
                          <span style="font-weight:700; color:#f8fafc; font-size:1rem;">
                              <?= htmlspecialchars($u['nome']) ?>
                              <?= in_array($u['id'], ['user_1', '6a2cd0df8e1404.73139125']) ? '👑' : '' ?>
                          </span>
                          <?= renderStatusBadge($u['status_operacional'] ?? 'ausente') ?>
                        </div>
                      </div>
                    </td>
                    <td>
                      <small style="color:#cbd5e1; line-height:1.5;">
                        <?= !empty($u['especialidades']) ? htmlspecialchars($u['especialidades']) : '<i>Perfil não preenchido.</i>' ?>
                      </small>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

    </div>
  </div>
</div>

<script>
    function toggleProfilePanel() {
        const panel = document.getElementById('profile-panel');
        const overlay = document.getElementById('profile-overlay');
        panel.classList.toggle('active');
        overlay.classList.toggle('active');
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

    (function(){
        var labels = <?= json_encode($grafico_labels) ?>;
        var logado = <?= json_encode($grafico_logado) ?>;
        var pausa = <?= json_encode($grafico_pausa) ?>;
        
        var canvas = document.getElementById('chartProdutividade');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        
        var W, H, pad, chartW, chartH, maxVal, barrasData = [];

        function initChartVars() {
            W = canvas.parentElement.offsetWidth || 800;
            canvas.width = W; H = 220; canvas.height = H;
            pad = {top:30, right:20, bottom:40, left:40};
            chartW = W - pad.left - pad.right; chartH = H - pad.top - pad.bottom;
            maxVal = Math.max(...logado, ...pausa, 9);
        }

        function desenharGrafico() {
            var n = labels.length; if(n === 0) return;
            ctx.clearRect(0,0,W,H);
            ctx.strokeStyle = '#334155'; ctx.lineWidth = 1;
            for (var i=0; i<=4; i++) {
                var y = pad.top + chartH - (chartH*(i/4));
                ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(W-pad.right, y); ctx.stroke();
                ctx.fillStyle='#94a3b8'; ctx.font='11px sans-serif'; ctx.textAlign='right';
                ctx.fillText((maxVal*(i/4)).toFixed(0) + 'h', pad.left-8, y+4);
            }

            var bw = (chartW / n) * 0.25; var gap = (chartW / n); barrasData = []; 
            for (var i=0; i<n; i++) {
                var x = pad.left + i*gap + gap*0.25;
                var hLog = (logado[i]/maxVal)*chartH; var yLog = pad.top+chartH-hLog;
                ctx.fillStyle = '#00e5ff'; ctx.fillRect(x, yLog, bw, hLog);
                barrasData.push({ x: x, y: yLog, w: bw, h: hLog, val: logado[i], tipo: 'Trabalhado', cor: '#00e5ff', nome: labels[i] });
                
                var hPau = (pausa[i]/maxVal)*chartH; var yPau = pad.top+chartH-hPau;
                ctx.fillStyle = '#a855f7'; ctx.fillRect(x+bw+4, yPau, bw, hPau);
                barrasData.push({ x: x+bw+4, y: yPau, w: bw, h: hPau, val: pausa[i], tipo: 'Em Pausa', cor: '#a855f7', nome: labels[i] });
                
                ctx.fillStyle='#cbd5e1'; ctx.font='12px sans-serif'; ctx.textAlign='center';
                ctx.fillText(labels[i], x+bw+2, H-15);
            }
            ctx.fillStyle='#00e5ff'; ctx.fillRect(pad.left, 5, 12, 10);
            ctx.fillStyle='#cbd5e1'; ctx.textAlign='left'; ctx.fillText('Horas de Operação', pad.left+18, 14);
            ctx.fillStyle='#a855f7'; ctx.fillRect(pad.left+140, 5, 12, 10);
            ctx.fillStyle='#cbd5e1'; ctx.fillText('Tempo de Pausas (Almoço/Banheiro)', pad.left+158, 14);
        }

        window.addEventListener('resize', function() { initChartVars(); desenharGrafico(); });
        initChartVars(); desenharGrafico();

        function formatarTempo(valorDecimal) {
            var h = Math.floor(valorDecimal); var m = Math.round((valorDecimal - h) * 60);
            var textoH = h > 0 ? h + (h === 1 ? ' hora' : ' horas') : '';
            var textoM = m > 0 ? m + (m === 1 ? ' minuto' : ' minutos') : '';
            if (h > 0 && m > 0) return textoH + ' e ' + textoM;
            if (h > 0) return textoH;
            if (m > 0) return textoM;
            return '0 minutos';
        }

        var tooltip = document.createElement('div');
        tooltip.style.position = 'absolute'; tooltip.style.display = 'none'; tooltip.style.background = 'rgba(15, 23, 42, 0.95)';
        tooltip.style.border = '1px solid #334155'; tooltip.style.padding = '10px 14px'; tooltip.style.borderRadius = '8px';
        tooltip.style.color = '#fff'; tooltip.style.fontSize = '12px'; tooltip.style.pointerEvents = 'none'; 
        tooltip.style.boxShadow = '0 10px 25px rgba(0,0,0,0.5)'; tooltip.style.zIndex = '2500'; tooltip.style.transition = 'opacity 0.2s';
        document.body.appendChild(tooltip);

        canvas.addEventListener('mousemove', function(e) {
            var rect = canvas.getBoundingClientRect(); var mouseX = e.clientX - rect.left; var mouseY = e.clientY - rect.top;
            var barraEncontrada = null;
            for (var i=0; i<barrasData.length; i++) {
                var b = barrasData[i];
                if (mouseX >= b.x && mouseX <= b.x + b.w && mouseY >= b.y && mouseY <= b.y + b.h) { barraEncontrada = b; break; }
            }
            if (barraEncontrada) {
                canvas.style.cursor = 'pointer'; tooltip.style.display = 'block'; tooltip.style.opacity = '1';
                tooltip.innerHTML = '<strong style="color:' + barraEncontrada.cor + '; font-size: 14px;">' + barraEncontrada.nome + '</strong><br>' + 
                                    '<span style="color:#94a3b8; font-weight:600; margin-top:5px; display:inline-block;">Tempo ' + barraEncontrada.tipo + ':</span> <span style="font-weight:700;">' + formatarTempo(barraEncontrada.val) + '</span>';
                tooltip.style.left = (e.pageX + 15) + 'px'; tooltip.style.top = (e.pageY - 15) + 'px';
            } else {
                canvas.style.cursor = 'default'; tooltip.style.opacity = '0';
                setTimeout(() => { if(tooltip.style.opacity === '0') tooltip.style.display = 'none'; }, 200);
            }
        });
        canvas.addEventListener('mouseout', function() { tooltip.style.opacity = '0'; canvas.style.cursor = 'default'; });
    })();
</script>
</body>
</html>