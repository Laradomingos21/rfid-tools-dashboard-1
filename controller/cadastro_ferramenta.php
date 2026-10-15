<?php
// Salve este arquivo em: controller/cadastro_ferramenta.php
session_start();
require __DIR__ . '/../config/conexao.php';

// Volta para a página anterior (o formulário) com uma mensagem
function voltar(string $msg, string $status): void
{
    $_SESSION['msg_ferramenta']    = $msg;
    $_SESSION['status_ferramenta'] = $status;

    $destino = $_SERVER['HTTP_REFERER'] ?? '../pages/cadastro_ferramentas.php';
    header('Location: ' . $destino);
    exit;
}

// só funcionário logado pode cadastrar
if (empty($_SESSION['funcionario_id'])) {
    header('Location: ../pages/index_cadastro.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltar('Requisição inválida.', 'erro');
}

$nome = trim($_POST['nome'] ?? '');
$tag  = trim($_POST['tag_rfid'] ?? '');

if ($nome === '' || $tag === '') {
    voltar('Preencha o nome e a tag RFID.', 'erro');
}

try {
    // evita tag repetida
    $existe = $pdo->prepare('SELECT id FROM ferramentas WHERE tag_rfid = ?');
    $existe->execute([$tag]);
    if ($existe->fetch()) {
        voltar('Essa tag RFID já está cadastrada.', 'erro');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO ferramentas (nome, tag_rfid, status) VALUES (?, ?, 'disponivel')"
    );
    $stmt->execute([$nome, $tag]);

    voltar('Ferramenta cadastrada com sucesso!', 'sucesso');
} catch (PDOException $e) {
    // Durante o desenvolvimento mostra o erro real; em produção, troque por uma mensagem genérica
    voltar('Erro ao cadastrar: ' . $e->getMessage(), 'erro');
}