<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Só funciona para quem está logado
if (empty($_SESSION['funcionario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensagem' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}

require __DIR__ . '/../config/conexao.php';

$tagRfid = trim($_POST['tag_rfid'] ?? '');
$funcionarioId = $_SESSION['funcionario_id'];

if ($tagRfid === '') {
    echo json_encode(['ok' => false, 'mensagem' => 'Nenhuma tag foi lida.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Trava a linha da ferramenta durante a transação (evita duas leituras simultâneas conflitarem)
    $stmt = $pdo->prepare('SELECT id, nome, status, zona_atual_id FROM ferramentas WHERE tag_rfid = ? FOR UPDATE');
    $stmt->execute([$tagRfid]);
    $ferramenta = $stmt->fetch();

    if (!$ferramenta) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'mensagem' => "Nenhuma ferramenta cadastrada com a tag \"$tagRfid\"."]);
        exit;
    }

    // No banco o status vem como: disponivel, em_uso, perdida ou manutencao
    $statusAtual = $ferramenta['status'] ?? 'disponivel';

    if ($statusAtual === 'manutencao') {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'mensagem' => $ferramenta['nome'] . ' está em manutenção e não pode ser retirada.']);
        exit;
    }

    if ($statusAtual === 'perdida') {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'mensagem' => $ferramenta['nome'] . ' está marcada como perdida.']);
        exit;
    }

    // Alterna: disponivel -> empréstimo | em_uso -> devolução
    if ($statusAtual === 'disponivel') {
        $statusBanco = 'em_uso';
        $novoStatus = 'emprestada';   // nome usado pelo painel (CSS/JS)
        $tipoMovimentacao = 'emprestimo';
        $funcionarioAtual = $funcionarioId;
        $zonaOrigem = $ferramenta['zona_atual_id'];
        $zonaDestino = null;
    } else {
        $statusBanco = 'disponivel';
        $novoStatus = 'disponivel';
        $tipoMovimentacao = 'devolucao';
        $funcionarioAtual = null;
        $zonaOrigem = null;
        $zonaDestino = $ferramenta['zona_atual_id'];
    }

    $pdo->prepare('UPDATE ferramentas SET status = ?, funcionario_atual_id = ?, atualizado_em = NOW() WHERE id = ?')
        ->execute([$statusBanco, $funcionarioAtual, $ferramenta['id']]);

    // A tabela movimentacoes não tem coluna "tipo": o tipo fica gravado em observacao
    $pdo->prepare('INSERT INTO movimentacoes (ferramenta_id, zona_origem_id, zona_destino_id, funcionario_id, observacao) VALUES (?, ?, ?, ?, ?)')
        ->execute([$ferramenta['id'], $zonaOrigem, $zonaDestino, $funcionarioId, $tipoMovimentacao]);

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'tipo' => $tipoMovimentacao,
        'ferramenta' => $ferramenta['nome'],
        'novo_status' => $novoStatus,
        'ferramenta_id' => $ferramenta['id'],
        'funcionario' => $_SESSION['funcionario_nome'],
        'mensagem' => $tipoMovimentacao === 'emprestimo'
            ? $ferramenta['nome'] . ' retirada com sucesso.'
            : $ferramenta['nome'] . ' devolvida com sucesso.',
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensagem' => 'Erro ao registrar a movimentação.']);
}