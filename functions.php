<?php
function ler_json($arquivo) {
    if (!file_exists($arquivo)) return [];
    $fp = fopen($arquivo, 'r');
    if (!$fp) return [];
    flock($fp, LOCK_SH);
    $conteudo = fread($fp, max(1, filesize($arquivo)));
    flock($fp, LOCK_UN);
    fclose($fp);
    $dados = json_decode($conteudo, true);
    return is_array($dados) ? $dados : [];
}

function salvar_json($arquivo, $dados) {
    $fp = fopen($arquivo, 'c+');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

function verificar_sessao() {
    if (empty($_SESSION['logado'])) {
        header('Location: login.php'); exit;
    }
}

function formatar_moeda($valor) {
    return 'R$ ' . number_format(floatval($valor), 2, ',', '.');
}

function gerar_id() {
    return uniqid('', true);
}
?>