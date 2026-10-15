<?php
// Salve este arquivo em: pages/cadastro_ferramentas.php
require __DIR__ . '/../config/exigir_login.php';

$msg    = $_SESSION['msg_ferramenta'] ?? '';
$status = $_SESSION['status_ferramenta'] ?? '';
unset($_SESSION['msg_ferramenta'], $_SESSION['status_ferramenta']);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar ferramenta · RFID Tools</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Alata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style_sistema.css">
</head>

<body class="com-menu">

    <aside class="menu-lateral">
        <nav class="menu">
            <a href="index_sistema.php">Painel</a>
            <a href="cadastro_ferramentas.php" class="ativo">Cadastrar ferramenta</a>
            <a href="cadastro_categorias.php">Categorias</a>
            <a href="cadastro_zonas.php">Zonas</a>
        </nav>
    </aside>

    <main class="tela">
        <h1 class="titulo">RFID TOOLS</h1>
        <p class="subtitulo">Cadastro de ferramenta</p>

        <section class="container">
            <h2>Nova ferramenta</h2>
            <p class="descricao">Aproxime a etiqueta do leitor para preencher a tag automaticamente.</p>

            <?php if ($msg): ?>
                <div class="mensagem <?= htmlspecialchars($status) ?>" role="alert">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <form id="formulario_ferramenta" action="../controller/cadastro_ferramenta.php" method="POST">

                <label for="nome">Nome da ferramenta</label>
                <input type="text" id="nome" name="nome" placeholder="Ex.: Furadeira" required autofocus>

                <label for="tag_rfid">Tag RFID</label>
                <input type="text" id="tag_rfid" name="tag_rfid" placeholder="Aproxime a etiqueta" autocomplete="off" required>

                <button type="submit" class="botao-primario">Cadastrar ferramenta</button>
            </form>

            <a class="botao-secundario" href="index_sistema.php" style="margin-top: 12px">Voltar ao painel</a>
        </section>
    </main>

</body>

</html>