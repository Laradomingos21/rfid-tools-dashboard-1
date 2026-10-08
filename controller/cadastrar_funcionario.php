<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Só aceita requisições vindas de um POST (do formulário)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/cadastro_funcionario.php');
    exit;
}

require __DIR__ . '/../config/conexao.php';

// Pega os dados do formulário e remove espaços extras
$nome         = trim($_POST['nome'] ?? '');
$email        = trim($_POST['email'] ?? '');
$matricula    = trim($_POST['matricula'] ?? '');
$setor        = trim($_POST['setor'] ?? '');
$senha        = $_POST['senha'] ?? '';

// ===== VALIDAÇÕES BÁSICAS =====
if ($nome === '' || $email === '' || $matricula === '' || $setor === '' || $senha === '') {
    $_SESSION['msg_func'] = 'Preencha todos os campos.';
    $_SESSION['status_func'] = 'erro';
    header('Location: ../pages/cadastro_funcionario.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['msg_func'] = 'Digite um email válido.';
    $_SESSION['status_func'] = 'erro';
    header('Location: ../pages/cadastro_funcionario.php');
    exit;
}

if (strlen($senha) < 6) {
    $_SESSION['msg_func'] = 'A senha precisa ter no mínimo 6 caracteres.';
    $_SESSION['status_func'] = 'erro';
    header('Location: ../pages/cadastro_funcionario.php');
    exit;
}

try {
    // Verifica se email, matrícula ou tag RFID já existem (evita duplicados)
    $verifica = $pdo->prepare('SELECT id FROM funcionarios WHERE email = ? OR matricula = ?');
    $verifica->execute([$email, $matricula]);

    if ($verifica->fetch()) {
        $_SESSION['msg_func'] = 'Já existe um funcionário com esse email ou matrícula.';
        $_SESSION['status_func'] = 'erro';
        header('Location: ../pages/cadastro_funcionario.php');
        exit;
    }

    // Nunca salve a senha em texto puro — sempre use password_hash
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $inserir = $pdo->prepare('
        INSERT INTO funcionarios (nome, email, matricula, setor, Senha, ativo)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $inserir->execute([$nome, $email, $matricula, $setor, $senhaHash, 1]);

    // Sucesso: manda direto para a tela de login com a mensagem de confirmação
    $_SESSION['msg_login'] = 'Cadastro realizado com sucesso! Faça login para continuar.';
    $_SESSION['status_login'] = 'sucesso';
    header('Location: ../pages/index_cadastro.php');
    exit;
} catch (PDOException $e) {
    // Erro: volta para o formulário com a mensagem
    error_log($e->getMessage());
    $_SESSION['msg_func'] = 'Erro ao cadastrar. Tente novamente.';
    $_SESSION['status_func'] = 'erro';
    header('Location: ../pages/cadastro_funcionario.php');
    exit;
}