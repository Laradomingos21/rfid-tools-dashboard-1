<?php
require __DIR__ . '/../config/exigir_login.php';
require __DIR__ . '/../config/conexao.php';

// ===== Busca as ferramentas e monta os contadores =====
$ferramentas = $pdo->query("
    SELECT f.id, f.nome, f.codigo, f.tag_rfid,
           c.nome AS categoria,
           z.nome AS localizacao,
           CASE WHEN f.status = 'em_uso' THEN 'emprestada'
                ELSE COALESCE(f.status, 'disponivel') END AS status
    FROM ferramentas f
    LEFT JOIN categorias c ON c.id = f.categoria_id
    LEFT JOIN zonas z      ON z.id = f.zona_atual_id
    ORDER BY f.nome
")->fetchAll();

$totais = ['disponivel' => 0, 'emprestada' => 0, 'manutencao' => 0, 'perdida' => 0];
foreach ($ferramentas as $f) {
    $totais[$f['status']]++;
}
$totalGeral = count($ferramentas);

// ===== Últimas 12 movimentações, com nome do funcionário e da ferramenta =====
$movimentacoes = $pdo->query('
    SELECT m.observacao AS tipo, m.movido_em AS data_hora, f.nome AS ferramenta_nome, fu.nome AS funcionario_nome
    FROM movimentacoes m
    JOIN ferramentas f  ON f.id  = m.ferramenta_id
    JOIN funcionarios fu ON fu.id = m.funcionario_id
    ORDER BY m.movido_em DESC
    LIMIT 12
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel · RFID Tools</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Alata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style_sistema.css">
</head>

<body class="com-menu">

    <header class="topbar">
        <div class="marca">
            <h1>RFID TOOLS</h1>
            <span>Monitoramento de ferramentas</span>
        </div>
        <div class="usuario">
            <span>Olá, <strong><?= htmlspecialchars($_SESSION['funcionario_nome']) ?></strong></span>
            <a href="../controller/logout.php" class="botao-sair">Sair</a>
        </div>
    </header>

    <!-- Menu lateral + painel lado a lado -->
    <div class="layout">

        <aside class="menu-lateral">
            <nav class="menu">
                <a href="index_sistema.php" class="ativo">Painel</a>
                <a href="funcionario.php">Cadastrar funcionário</a>
                <a href="cadastro_ferramentas.php">Cadastrar ferramenta</a>
                <a href="cadastro_categorias.php">Categorias</a>
                <a href="cadastro_zonas.php">Zonas</a>
            </nav>
        </aside>

        <main class="painel">

            <!-- Barra de leitura de tag: ação principal do sistema -->
            <div class="scan-bar">
                <div class="scan-label">
                    <strong>Leitor RFID</strong>
                    <small>Aproxime ou digite a tag</small>
                </div>
                <form id="form-scan">
                    <input type="text" id="input-tag" name="tag_rfid" placeholder="Ex: TAG-FRM-0001" autocomplete="off">
                    <button type="submit">Registrar leitura</button>
                </form>
            </div>
            <div id="scan-feedback"></div>

            <!-- Estatísticas -->
            <section class="stats">
                <div class="stat-card total">
                    <div class="valor"><?= $totalGeral ?></div>
                    <div class="rotulo">Ferramentas cadastradas</div>
                </div>
                <div class="stat-card disponivel">
                    <div class="valor"><?= $totais['disponivel'] ?></div>
                    <div class="rotulo">Disponíveis</div>
                </div>
                <div class="stat-card emprestada">
                    <div class="valor"><?= $totais['emprestada'] ?></div>
                    <div class="rotulo">Emprestadas agora</div>
                </div>
                <div class="stat-card manutencao">
                    <div class="valor"><?= $totais['manutencao'] ?></div>
                    <div class="rotulo">Em manutenção</div>
                </div>
            </section>

            <!-- Tabela + histórico -->
            <section class="conteudo">

                <div class="painel-secao">
                    <h2>Ferramentas</h2>
                    <p class="subinfo">Status atualiza automaticamente a cada leitura de tag</p>

                    <?php if (empty($ferramentas)): ?>
                        <p class="vazio">Nenhuma ferramenta cadastrada ainda. Adicione registros na tabela <code>ferramentas</code>.</p>
                    <?php else: ?>
                        <table class="ferramentas">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Tag</th>
                                    <th>Local</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ferramentas as $f): ?>
                                    <tr data-ferramenta-id="<?= $f['id'] ?>">
                                        <td><?= htmlspecialchars($f['nome']) ?></td>
                                        <td class="tag"><?= htmlspecialchars((string) $f['tag_rfid']) ?></td>
                                        <td><?= htmlspecialchars((string) ($f['localizacao'] ?? '—')) ?></td>
                                        <td>
                                            <span class="status-badge <?= $f['status'] ?>">
                                                <?= ['disponivel' => 'Disponível', 'emprestada' => 'Emprestada', 'manutencao' => 'Manutenção', 'perdida' => 'Perdida'][$f['status']] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="painel-secao">
                    <h2>Últimas movimentações</h2>
                    <p class="subinfo">Empréstimos e devoluções mais recentes</p>

                    <ul class="feed-movimentacoes" id="lista-movimentacoes">
                        <?php if (empty($movimentacoes)): ?>
                            <li class="vazio">Nenhuma movimentação registrada ainda.</li>
                        <?php else: ?>
                            <?php foreach ($movimentacoes as $m): ?>
                                <li>
                                    <div class="linha-topo">
                                        <span class="<?= $m['tipo'] === 'emprestimo' ? 'tipo-emprestimo' : 'tipo-devolucao' ?>">
                                            <?= $m['tipo'] === 'emprestimo' ? 'Retirou' : 'Devolveu' ?>
                                        </span>
                                        <span class="detalhe"><?= date('d/m H:i', strtotime($m['data_hora'])) ?></span>
                                    </div>
                                    <div class="detalhe"><?= htmlspecialchars($m['funcionario_nome']) ?> · <?= htmlspecialchars($m['ferramenta_nome']) ?></div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

            </section>
        </main>

    </div>

    <script src="main_sistema.js"></script>
</body>
</html>