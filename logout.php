<?php
session_start();
require 'functions.php';

// Se o usuário estiver logado, muda o status dele para 'ausente' no banco antes de sair
if (isset($_SESSION['usuario_id'])) {
    $db = ler_json('db.json');
    $uid = $_SESSION['usuario_id'];
    
    if (isset($db['usuarios'])) {
        foreach ($db['usuarios'] as &$u) {
            if ($u['id'] === $uid) {
                $u['status_operacional'] = 'ausente';
                break;
            }
        }
        salvar_json('db.json', $db);
    }
}

// Destrói a sessão e manda pro login
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
?>