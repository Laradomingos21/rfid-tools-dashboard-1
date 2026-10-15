<?php
// Salve este arquivo em: pages/funcionarios.php
require __DIR__ . '/../config/exigir_login.php';
require __DIR__ . '/../config/conexao.php';

// ===== Funcionários =====
$funcionarios = $pdo->query('
    SELECT id, nome, matricula, setor, email
    FROM funcionarios
    ORDER BY nome
')->fetchAll();

// ===== Retiradas e ferramentas ativas por funcionário =====
// (dentro de try: se alguma tabela/coluna for diferente, a página continua abrindo)
$retiradas = [];
$ativas    = [];

try {
    // total de retiradas (empréstimos) de cada funcionário
    $linhas = $pdo->query("
        SELECT funcionario_id, COUNT(*) AS total
        FROM movimentacoes
        WHERE observacao = 'emprestimo'
        GROUP BY funcionario_id
    ")->fetchAll();
    foreach ($linhas as $l) {
        $retiradas[$l['funcionario_id']] = (int) $l['total'];
    }

    // ferramentas que estão em uso agora, com quem fez a última movimentação
    $linhas = $pdo->query("
        SELECT m.funcionario_id, COUNT(*) AS total
        FROM ferramentas f
        JOIN movimentacoes m ON m.id = (
            SELECT m2.id
            FROM movimentacoes m2
            WHERE m2.ferramenta_id = f.id
            ORDER BY m2.movido_em DESC, m2.id DESC
            LIMIT 1
        )
        WHERE f.status IN ('em_uso', 'emprestada')
        GROUP BY m.funcionario_id
    ")->fetchAll();
    foreach ($linhas as $l) {
        $ativas[$l['funcionario_id']] = (int) $l['total'];
    }
} catch (PDOException $e) {
    // mantém os contadores zerados
}

// ===== Números do topo =====
$totalFuncionarios = count($funcionarios);
$comFerramentas    = count(array_filter($ativas));
$totalRetiradas    = array_sum($retiradas);

// ===== Setores para os filtros =====
$setores = [];
foreach ($funcionarios as $f) {
    $setor = trim((string) ($f['setor'] ?? ''));
    if ($setor !== '') {
        $setores[$setor] = true;
    }
}
$setores = array_keys($setores);
sort($setores);

// Iniciais do nome (ex.: "Eduardo Bruscagim" -> "EB")
function iniciais(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome));
    $letras = mb_substr($partes[0], 0, 1);
    if (count($partes) > 1) {
        $letras .= mb_substr(end($partes), 0, 1);
    }
    return mb_strtoupper($letras);
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Funcionários · RFID Tools</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Alata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style_sistema.css">
</head>

<body class="com-menu">

    <aside class="menu-lateral">
        <nav class="menu">
            <a href="index_sistema.php">Painel</a>
            <a href="funcionarios.php" class="ativo">Funcionários</a>
            <a href="cadastro_ferramentas.php">Cadastrar ferramenta</a>
            <a href="cadastro_categorias.php">Categorias</a>
            <a href="cadastro_zonas.php">Zonas</a>
        </nav>
    </aside>

    <div class="layout">
        <main class="painel">

            <!-- Título + botão que abre a tela de cadastro -->
            <header class="pagina-cabecalho">
                <div>
                    <h1>Funcionários</h1>
                    <p>Gestão de usuários e permissões</p>
                </div>
                <a class="botao-novo" href="cadastro_funcionario.php">+ Novo funcionário</a>
            </header>

            <!-- Números -->
            <section class="stats">
                <div class="stat-card total">
                    <div class="valor"><?= $totalFuncionarios ?></div>
                    <div class="rotulo">Total de funcionários</div>
                </div>
                <div class="stat-card emprestada">
                    <div class="valor"><?= $comFerramentas ?></div>
                    <div class="rotulo">Com ferramentas ativas</div>
                </div>
                <div class="stat-card disponivel">
                    <div class="valor"><?= $totalRetiradas ?></div>
                    <div class="rotulo">Total de retiradas</div>
                </div>
            </section>

            <!-- Busca e filtro por setor -->
            <section class="func-filtros">
                <input
                    type="search"
                    id="busca-func"
                    class="func-busca"
                    placeholder="Buscar por nome, matrícula ou e-mail..."
                    autocomplete="off"
                >
                <div class="chips" id="chips-setor">
                    <button type="button" class="chip ativo" data-setor="">Todos</button>
                    <?php foreach ($setores as $setor): ?>
                        <button type="button" class="chip" data-setor="<?= htmlspecialchars(mb_strtolower($setor)) ?>">
                            <?= htmlspecialchars($setor) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Cards -->
            <?php if (empty($funcionarios)): ?>
                <p class="vazio">Nenhum funcionário cadastrado ainda. Clique em <strong>+ Novo funcionário</strong>.</p>
            <?php else: ?>
                <section class="func-grid" id="func-grid">
                    <?php foreach ($funcionarios as $f):
                        $setor    = trim((string) ($f['setor'] ?? '')) ?: 'Sem setor';
                        $nAtivas  = $ativas[$f['id']] ?? 0;
                        $nRetira  = $retiradas[$f['id']] ?? 0;
                        $busca    = mb_strtolower($f['nome'] . ' ' . $f['matricula'] . ' ' . $f['email']);
                    ?>
                        <article
                            class="func-card"
                            data-setor="<?= htmlspecialchars(mb_strtolower($setor)) ?>"
                            data-busca="<?= htmlspecialchars($busca) ?>"
                        >
                            <span class="func-ponto <?= $nAtivas > 0 ? 'ativo' : '' ?>"
                                  title="<?= $nAtivas > 0 ? 'Está com ferramenta' : 'Sem ferramentas no momento' ?>"></span>

                            <div class="func-topo">
                                <div class="func-avatar cor-<?= $f['id'] % 6 ?>"><?= htmlspecialchars(iniciais($f['nome'])) ?></div>
                                <div>
                                    <h3><?= htmlspecialchars($f['nome']) ?></h3>
                                    <span class="func-mat"><?= htmlspecialchars((string) $f['matricula']) ?></span>
                                </div>
                            </div>

                            <dl class="func-info">
                                <div><dt>Setor</dt><dd><?= htmlspecialchars($setor) ?></dd></div>
                                <div><dt>E-mail</dt><dd><?= htmlspecialchars((string) $f['email']) ?></dd></div>
                            </dl>

                            <div class="func-rodape">
                                <span><strong><?= $nAtivas ?></strong>ativas</span>
                                <span><strong><?= $nRetira ?></strong>retiradas</span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>

                <p class="func-sem-resultado" id="sem-resultado" hidden>Nenhum funcionário encontrado.</p>
            <?php endif; ?>

        </main>
    </div>

    <script>
        const campoBusca = document.getElementById('busca-func');
        const chips      = document.querySelectorAll('#chips-setor .chip');
        const cards      = document.querySelectorAll('.func-card');
        const semResult  = document.getElementById('sem-resultado');

        let setorAtivo = '';

        // tira acentos e põe em minúsculas, para "joao" achar "João"
        const normalizar = (t) =>
            t.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

        function filtrar() {
            const termo = normalizar(campoBusca.value.trim());
            let visiveis = 0;

            cards.forEach((card) => {
                const bateSetor = setorAtivo === '' || card.dataset.setor === setorAtivo;
                const bateBusca = normalizar(card.dataset.busca).includes(termo);
                card.hidden = !(bateSetor && bateBusca);
                if (!card.hidden) visiveis++;
            });

            if (semResult) semResult.hidden = visiveis !== 0;
        }

        campoBusca.addEventListener('input', filtrar);

        chips.forEach((chip) => {
            chip.addEventListener('click', () => {
                chips.forEach((c) => c.classList.remove('ativo'));
                chip.classList.add('ativo');
                setorAtivo = chip.dataset.setor;
                filtrar();
            });
        });
    </script>

</body>

</html>