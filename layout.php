<?php
function renderHeader() {
    echo '<header class="lp-header">';
    echo '  <div class="lp-container">';
    echo '    <div class="lp-brand">';
    echo '      <span class="lp-icon">🔧</span> <span style="color:#fff;">L.P. Tech</span><span class="lp-highlight">OS</span>';
    echo '    </div>';
    echo '    <nav class="lp-nav">';
    echo '      <a href="#dashboard">Dashboard</a>';
    echo '      <a href="#nova-os">Nova OS</a>';
    echo '      <a href="#lista-os">Painel de Serviços</a>';
    echo '      <a href="#equipe">Especialidades da Equipe</a>';
    echo '    </nav>';
    echo '    <a href="logout.php" class="lp-btn-outline">Sair do Sistema</a>';
    echo '  </div>';
    echo '</header>';
}

function renderFooter() {
    echo '<footer class="app-footer" style="margin-top: 40px; padding-bottom: 20px;">Desenvolvido por: Lucas | L.P. TechOS Colaborativo</footer>';
}
?>