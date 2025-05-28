<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastro de Produto</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php
// produto.php - Tela simples para cadastro, edição e compra de produtos
session_start();
require 'config.php';
require 'utils.php';

// Função para buscar variações e estoques
function getVariacoes($pdo, $produto_id) {
    $stmt = $pdo->prepare('SELECT * FROM estoques WHERE produto_id = ?');
    $stmt->execute([$produto_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// CRUD Produto e CRUD Cupom
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cadastro de Produto
    if (isset($_POST['nome'])) {
        $nome = $_POST['nome'] ?? '';
        $preco = $_POST['preco'] ?? 0;
        $variacoes = $_POST['variacao'] ?? [];
        $estoques = $_POST['estoque'] ?? [];
        $produto_id = $_POST['produto_id'] ?? null;
        if ($produto_id) {
            $stmt = $pdo->prepare('UPDATE produtos SET nome=?, preco=? WHERE id=?');
            $stmt->execute([$nome, $preco, $produto_id]);
            foreach ($variacoes as $i => $var) {
                $est = $estoques[$i] ?? 0;
                if (!empty($_POST['estoque_id'][$i])) {
                    $eid = $_POST['estoque_id'][$i];
                    $stmt = $pdo->prepare('UPDATE estoques SET variacao=?, quantidade=? WHERE id=?');
                    $stmt->execute([$var, $est, $eid]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO estoques (produto_id, variacao, quantidade) VALUES (?, ?, ?)');
                    $stmt->execute([$produto_id, $var, $est]);
                }
            }
        } else {
            $stmt = $pdo->prepare('INSERT INTO produtos (nome, preco) VALUES (?, ?)');
            $stmt->execute([$nome, $preco]);
            $produto_id = $pdo->lastInsertId();
            foreach ($variacoes as $i => $var) {
                $est = $estoques[$i] ?? 0;
                $stmt = $pdo->prepare('INSERT INTO estoques (produto_id, variacao, quantidade) VALUES (?, ?, ?)');
                $stmt->execute([$produto_id, $var, $est]);
            }
        }
        header('Location: produto.php?edit=' . $produto_id);
        exit;
    }
    // Cadastro de Cupom
    if (isset($_POST['codigo_cupom'])) {
        $codigo = $_POST['codigo_cupom'];
        $desconto = $_POST['desconto'] ?? 0;
        $validade = $_POST['validade'] ?? '';
        $valor_minimo = $_POST['valor_minimo'] ?? 0;
        if (!empty($_POST['cupom_id'])) {
            $cupom_id = $_POST['cupom_id'];
            $stmt = $pdo->prepare('UPDATE cupons SET codigo=?, desconto=?, validade=?, valor_minimo=? WHERE id=?');
            $stmt->execute([$codigo, $desconto, $validade, $valor_minimo, $cupom_id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO cupons (codigo, desconto, validade, valor_minimo) VALUES (?, ?, ?, ?)');
            $stmt->execute([$codigo, $desconto, $validade, $valor_minimo]);
        }
        header('Location: produto.php?cupons=1');
        exit;
    }
    // Exclusão de Cupom
    if (isset($_POST['excluir_cupom'])) {
        $cupom_id = $_POST['excluir_cupom'];
        $stmt = $pdo->prepare('DELETE FROM cupons WHERE id=?');
        $stmt->execute([$cupom_id]);
        header('Location: produto.php?cupons=1');
        exit;
    }
}

// Adiciona ao carrinho
if (isset($_POST['comprar'])) {
    $produto_id = $_POST['produto_id'];
    $variacao_id = $_POST['variacao_id'];
    $qtd = $_POST['qtd'] ?? 1;
    $stmt = $pdo->prepare('SELECT p.*, e.variacao, e.quantidade as estoque, e.id as estoque_id FROM produtos p JOIN estoques e ON p.id = e.produto_id WHERE p.id=? AND e.id=?');
    $stmt->execute([$produto_id, $variacao_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($item && $item['estoque'] >= $qtd) {
        // Se já existe no carrinho, soma a quantidade
        $found = false;
        if (!isset($_SESSION['carrinho'])) $_SESSION['carrinho'] = [];
        foreach ($_SESSION['carrinho'] as &$carrinho_item) {
            if ($carrinho_item['produto_id'] == $produto_id && $carrinho_item['variacao_id'] == $variacao_id) {
                $carrinho_item['qtd'] += $qtd;
                $found = true;
                break;
            }
        }
        unset($carrinho_item);
        if (!$found) {
            $_SESSION['carrinho'][] = [
                'produto_id' => $produto_id,
                'variacao_id' => $variacao_id,
                'nome' => $item['nome'],
                'variacao' => $item['variacao'],
                'preco' => $item['preco'],
                'qtd' => $qtd
            ];
        }
        // Atualiza estoque
        $stmt = $pdo->prepare('UPDATE estoques SET quantidade = quantidade - ? WHERE id = ?');
        $stmt->execute([$qtd, $variacao_id]);
    }
    header('Location: produto.php?edit=' . $produto_id);
    exit;
}

// Remover item do carrinho
if (isset($_GET['remover'])) {
    $remover = $_GET['remover'];
    if (isset($_SESSION['carrinho'][$remover])) {
        // Devolve o estoque
        $item = $_SESSION['carrinho'][$remover];
        $stmt = $pdo->prepare('UPDATE estoques SET quantidade = quantidade + ? WHERE id = ?');
        $stmt->execute([$item['qtd'], $item['variacao_id']]);
        unset($_SESSION['carrinho'][$remover]);
        $_SESSION['carrinho'] = array_values($_SESSION['carrinho']);
    }
    header('Location: produto.php?edit=' . ($_GET['edit'] ?? ''));
    exit;
}

// Finalizar pedido com cupom, endereço e envio de e-mail
if (isset($_POST['finalizar_pedido'])) {
    if (!empty($_SESSION['carrinho'])) {
        $cupom_id = null;
        $cupom_aplicado = null;
        $desconto = 0;
        $endereco_cliente = $_POST['endereco'] ?? '';
        // Aplicação de cupom
        if (!empty($_POST['cupom'])) {
            $codigo = $_POST['cupom'];
            $stmt = $pdo->prepare('SELECT * FROM cupons WHERE codigo=? AND validade >= CURDATE()');
            $stmt->execute([$codigo]);
            $cupom_aplicado = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cupom_aplicado && $subtotal >= $cupom_aplicado['valor_minimo']) {
                $cupom_id = $cupom_aplicado['id'];
                $desconto = $cupom_aplicado['desconto'];
                $total = $total - ($subtotal * ($desconto/100));
            } else {
                $msg_pedido = 'Cupom inválido, expirado ou não atinge o valor mínimo!';
            }
        }
        if (!isset($msg_pedido)) {
            $stmt = $pdo->prepare('INSERT INTO pedidos (valor_total, cupom_id, status_id) VALUES (?, ?, 1)');
            $stmt->execute([$total, $cupom_id]);
            $pedido_id = $pdo->lastInsertId();
            foreach ($_SESSION['carrinho'] as $item) {
                $stmt = $pdo->prepare('INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)');
                $stmt->execute([$pedido_id, $item['produto_id'], $item['qtd'], $item['preco']]);
            }
            $_SESSION['carrinho'] = [];
            $msg_pedido = 'Pedido finalizado com sucesso!';
            // Envio de e-mail (simples)
            $to = $_POST['email'] ?? '';
            if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $subject = 'Confirmação de Pedido';
                $message = "Seu pedido #$pedido_id foi realizado com sucesso!\nEndereço de entrega: $endereco_cliente";
                @mail($to, $subject, $message);
            }
        }
    }
}

// Busca produto para edição
$produto = null;
$variacoes = [];
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM produtos WHERE id=?');
    $stmt->execute([$_GET['edit']]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
    $variacoes = getVariacoes($pdo, $_GET['edit']);
}

// Busca carrinho
$carrinho = $_SESSION['carrinho'] ?? [];
$subtotal = 0;
foreach ($carrinho as $item) {
    $subtotal += $item['preco'] * $item['qtd'];
}
if ($subtotal >= 52 && $subtotal <= 166.59) {
    $frete = 15;
} elseif ($subtotal > 200) {
    $frete = 0;
} else {
    $frete = 20;
}
$total = $subtotal + $frete;

?>

<div class="container">

<div style="max-width:700px;margin:40px auto 32px auto;background:linear-gradient(135deg,#e3eaf2 0%,#f9fbff 100%);border-radius:18px;box-shadow:0 6px 32px #0002;padding:40px 50px;">
    <h2 style="text-align:center;color:#0d47a1;margin-bottom:32px;letter-spacing:1.5px;text-shadow:0 2px 8px #1976d220;">Cadastro de Produto</h2>
    <form method="post">
        <input type="hidden" name="produto_id" value="<?= $produto['id'] ?? '' ?>">
        <div style="display:flex;gap:32px;flex-wrap:wrap;align-items:flex-end;margin-bottom:24px;">
            <div style="flex:1 1 260px;min-width:220px;">
                <label style="font-weight:700;color:#0d47a1;">Nome do Produto</label><br>
                <input type="text" name="nome" value="<?= $produto['nome'] ?? '' ?>" required style="width:100%;padding:12px 14px;border:2px solid #90caf9;border-radius:8px;font-size:1.08em;background:#f7fbff;">
            </div>
            <div style="flex:1 1 140px;min-width:120px;">
                <label style="font-weight:700;color:#0d47a1;">Preço (R$)</label><br>
                <input type="number" step="0.01" name="preco" value="<?= $produto['preco'] ?? '' ?>" required style="width:100%;padding:12px 14px;border:2px solid #90caf9;border-radius:8px;font-size:1.08em;background:#f7fbff;">
            </div>
        </div>
        <h4 style="margin-top:24px;color:#1976d2;letter-spacing:1px;">Variações e Estoque</h4>
        <div id="variacoes">
            <table style="width:100%;border-collapse:separate;border-spacing:0 10px;background:transparent;">
                <tr style="background:#bbdefb;color:#0d47a1;">
                    <th style="padding:16px 14px;border-radius:12px 0 0 12px;font-size:1.12em;text-align:center;">Variação</th>
                    <th style="padding:16px 14px;text-align:center;">Estoque</th>
                    <th style="padding:16px 14px;border-radius:0 12px 12px 0;text-align:center;">Ação</th>
                </tr>
                <?php foreach ($variacoes as $i => $v): ?>
                    <tr style="background:linear-gradient(90deg,#f7fbff 60%,#e3eaf2 100%);box-shadow:0 2px 10px #1976d210;">
                        <td style="padding:14px 14px;vertical-align:middle;">
                            <input type="hidden" name="estoque_id[]" value="<?= $v['id'] ?>">
                            <input type="text" name="variacao[]" value="<?= $v['variacao'] ?>" style="width:100%;padding:12px 14px;border:2px solid #90caf9;border-radius:8px;font-size:1.08em;background:#fff;">
                        </td>
                        <td style="padding:14px 14px;vertical-align:middle;text-align:center;">
                            <input type="number" name="estoque[]" value="<?= $v['quantidade'] ?>" style="width:90px;padding:12px 14px;border:2px solid #90caf9;border-radius:8px;font-size:1.08em;background:#fff;text-align:center;">
                        </td>
                        <td style="padding:14px 14px;text-align:center;vertical-align:middle;">
                            <button type="submit" name="atualizar_estoque" value="<?= $i ?>" style="background:linear-gradient(90deg,#1976d2 60%,#64b5f6 100%);color:#fff;border:none;border-radius:8px;padding:10px 28px;font-weight:700;cursor:pointer;box-shadow:0 2px 8px #1976d220;letter-spacing:0.5px;">Atualizar</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background:#f3f6fa;">
                    <td style="padding:14px 14px;">
                        <input type="hidden" name="estoque_id[]" value="">
                        <input type="text" name="variacao[]" placeholder="Nova variação" style="width:100%;padding:12px 14px;border:2px solid #90caf9;border-radius:8px;font-size:1.08em;background:#fff;">
                    </td>
                    <td style="padding:14px 14px;text-align:center;">
                        <input type="number" name="estoque[]" placeholder="Qtd" style="width:90px;padding:12px 14px;border:2px solid #90caf9;border-radius:8px;font-size:1.08em;background:#fff;text-align:center;">
                    </td>
                    <td style="padding:14px 14px;text-align:center;">
                        <button type="submit" style="background:linear-gradient(90deg,#43a047 60%,#81c784 100%);color:#fff;border:none;border-radius:8px;padding:10px 28px;font-weight:700;cursor:pointer;box-shadow:0 2px 8px #43a04720;letter-spacing:0.5px;">Adicionar</button>
                    </td>
                </tr>
            </table>
        </div>
        <div style="margin-top:24px;display:flex;justify-content:center;align-items:center;gap:24px;">
            <button type="submit" style="background:#1976d2;color:#fff;border:none;border-radius:8px;padding:12px 36px;font-size:1.08em;font-weight:700;cursor:pointer;letter-spacing:0.5px;">Salvar Produto</button>
            <?php if ($produto): ?>
                <form method="post" style="display:inline;margin:0;">
                    <input type="hidden" name="produto_id" value="<?= $produto['id'] ?>">
                    <input type="hidden" name="variacao_id" value="<?= isset($variacoes[0]['id']) ? $variacoes[0]['id'] : '' ?>">
                    <input type="hidden" name="qtd" value="1">
                    <button type="submit" name="comprar" style="background:#43a047;color:#fff;border:none;border-radius:8px;padding:12px 36px;font-size:1.08em;font-weight:700;cursor:pointer;letter-spacing:0.5px;">Comprar</button>
                </form>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Tela de gerenciamento de cupons -->
<?php if (isset($_GET['cupons'])): ?>
    <div style="max-width:700px;margin:40px auto;background:linear-gradient(135deg,#e3eaf2 0%,#f9fbff 100%);border-radius:18px;box-shadow:0 6px 32px #0002;padding:40px 50px;">
        <h2 style="text-align:center;color:#0d47a1;margin-bottom:32px;letter-spacing:1.5px;text-shadow:0 2px 8px #1976d220;">Gerenciar Cupons</h2>
        <form method="post" style="display:flex;flex-wrap:wrap;gap:28px 28px;align-items:flex-end;justify-content:center;margin-bottom:36px;">
            <input type="hidden" name="cupom_id" value="">
            <div style="flex:1 1 140px;min-width:140px;margin-bottom:12px;">
                <label style="font-weight:700;color:#0d47a1;">Código</label><br>
                <input type="text" name="codigo_cupom" required style="width:100%;padding:9px 11px;border:2px solid #90caf9;border-radius:8px;font-size:1.08em;background:#f7fbff;">
            </div>
            <div style="flex:1 1 110px;min-width:110px;margin-bottom:12px;">
                <label style="font-weight:700;color:#0d47a1;">Desconto (%)</label><br>
                <input type="number" step="0.01" name="desconto" required style="width:100%;padding:9px 11px;border:2px solid #90caf9;border-radius:8px;font-size:1.08em;background:#f7fbff;">
            </div>
            <div style="flex:1 1 150px;min-width:150px;margin-bottom:24px;">
                <label style="font-weight:700;color:#0d47a1;">Validade</label><br>
                <input type="date" name="validade" required style="width:100%;padding:9px 11px;border:2px solid #90caf9;border-radius:5px;font-size:1.08em;background:#f7fbff;">
            </div>
            <div style="flex:1 1 130px;min-width:130px;margin-bottom:12px;">
                <label style="font-weight:700;color:#0d47a1;">Valor Mínimo</label><br>
                <input type="number" step="0.01" name="valor_minimo" required style="width:100%;padding: 9px 11px;border:2px solid #90caf9;border-radius:8px;font-size:1.08em;background:#f7fbff;">
            </div>
            <div style="flex:1 1 120px;min-width:120px;align-self:flex-end;margin-bottom:12px;">
                <button type="submit" style="width:100%;background:linear-gradient(90deg,#1976d2 60%,#64b5f6 100%);color:#fff;border:none;border-radius:8px;padding:14px 0;font-size:1.08em;font-weight:700;cursor:pointer;box-shadow:0 2px 8px #1976d220;letter-spacing:0.5px;">Salvar Cupom</button>
            </div>
        </form>
        <table style="width:100%;border-collapse:separate;border-spacing:0 8px;background:transparent;">
            <tr style="background:#bbdefb;color:#0d47a1;">
                <th style="padding:16px 12px;border-radius:10px 0 0 10px;font-size:1.1em;">Código</th>
                <th style="padding:16px 12px;">Desconto (%)</th>
                <th style="padding:16px 12px;">Validade</th>
                <th style="padding:16px 12px;">Valor Mínimo</th>
                <th style="padding:16px 12px;border-radius:0 10px 10px 0;">Ações</th>
            </tr>
            <?php $cupons = $pdo->query('SELECT * FROM cupons')->fetchAll(PDO::FETCH_ASSOC); foreach ($cupons as $cupom): ?>
                <tr style="background:#fff;box-shadow:0 2px 10px #1976d210;transition:box-shadow 0.2s;">
                    <td style="padding:14px 12px;font-weight:700;letter-spacing:0.5px;color:#1976d2;white-space:nowrap;"> <?= htmlspecialchars($cupom['codigo']) ?> </td>
                    <td style="padding:14px 12px;"> <?= number_format($cupom['desconto'],2,',','.') ?>% </td>
                    <td style="padding:14px 12px;"> <?= date('d/m/Y', strtotime($cupom['validade'])) ?> </td>
                    <td style="padding:14px 12px;"> R$<?= number_format($cupom['valor_minimo'],2,',','.') ?> </td>
                    <td style="padding:14px 12px;">
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="excluir_cupom" value="<?= $cupom['id'] ?>">
                            <button type="submit" style="background:linear-gradient(90deg,#c62828 60%,#ef5350 100%);color:#fff;border:none;border-radius:6px;padding:10px 22px;font-weight:700;cursor:pointer;transition:background 0.2s;letter-spacing:0.5px;" onmouseover="this.style.background='#b71c1c'" onmouseout="this.style.background='linear-gradient(90deg,#c62828 60%,#ef5350 100%)'" onclick="return confirm('Excluir cupom?')">Excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <div style="text-align:center;margin-top:36px;">
            <a href="produto.php" style="color:#0d47a1;font-weight:bold;text-decoration:underline;font-size:1.15em;">Voltar</a>
        </div>
    </div>
<?php endif; ?>


<?php if ($produto): ?>
    <div style="max-width:700px;margin:40px auto 32px auto;background:linear-gradient(135deg,#e3eaf2 0%,#f9fbff 100%);border-radius:18px;box-shadow:0 6px 32px #0002;padding:32px 40px;">
        <h3 style="text-align:center;color:#1976d2;margin-bottom:24px;letter-spacing:1px;">Comprar Produto</h3>
        <form method="post" style="display:flex;flex-wrap:wrap;gap:32px 32px;align-items:flex-end;justify-content:center;">
            <input type="hidden" name="produto_id" value="<?= $produto['id'] ?>">
            <div style="flex:1 1 220px;min-width:220px;">
                <label style="font-weight:700;color:#0d47a1;">Variação</label><br>
                <select name="variacao_id" style="width:100%;padding:8px 7px;border:2px solid #90caf9;border-radius:8px;font-size:1.08em;background:#f7fbff;">
                    <?php foreach ($variacoes as $v): ?>
                        <option value="<?= $v['id'] ?>"><?= $v['variacao'] ?> (<?= $v['quantidade'] ?> em estoque)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1 1 120px;min-width:120px;">
                <label style="font-weight:700;color:#0d47a1;">Qtd</label><br>
                <input type="number" name="qtd" value="1" min="1" style="width:100%;padding:8px 7px;border:2px solid #90caf9;border-radius:8px;font-size:1.08em;background:#f7fbff;">
            </div>
            <div style="flex: 1 1 100px;min-width:100px;">
                <button type="submit" name="comprar" style="width:100%;background:linear-gradient(90deg,#43a047 40%,#81c784 100%);color:#fff;border:none;border-radius:8px;padding:14px 0;font-size:1.08em;font-weight:700;cursor:pointer;box-shadow:0 5px 8px #43a04720;letter-spacing:0.5px;">Comprar</button>
            </div>
        </form>
    </div>
<?php endif; ?>



<div style="max-width:700px;margin:40px auto 32px auto;background:linear-gradient(135deg,#e3eaf2 0%,#f9fbff 100%);border-radius:18px;box-shadow:0 6px 32px #0002;padding:32px 40px;">
    <h3 style="margin-top:0;text-align:center;color:#1976d2;letter-spacing:1px;">Carrinho</h3>
    <table style="width:100%;border-collapse:separate;border-spacing:0 8px;background:transparent;">
        <tr style="background:#bbdefb;color:#0d47a1;">
            <th style="padding:14px 12px;border-radius:10px 0 0 10px;font-size:1.08em;">Produto</th>
            <th style="padding:14px 12px;">Variação</th>
            <th style="padding:14px 12px;">Preço</th>
            <th style="padding:14px 12px;">Qtd</th>
            <th style="padding:14px 12px;">Subtotal</th>
            <th style="padding:14px 12px;border-radius:0 10px 10px 0;">Ação</th>
        </tr>
        <?php foreach ($carrinho as $i => $item): ?>
            <tr style="background:#fff;box-shadow:0 2px 10px #1976d210;">
                <td style="padding:12px 12px;"><?= htmlspecialchars($item['nome']) ?></td>
                <td style="padding:12px 12px;"><?= htmlspecialchars($item['variacao']) ?></td>
                <td style="padding:12px 12px;">R$<?= number_format($item['preco'],2,',','.') ?></td>
                <td style="padding:12px 12px;"><?= $item['qtd'] ?></td>
                <td style="padding:12px 12px;">R$<?= number_format($item['preco'] * $item['qtd'],2,',','.') ?></td>
                <td style="padding:12px 12px;text-align:center;">
                    <a href="?remover=<?= $i ?>&edit=<?= $produto['id'] ?? '' ?>" onclick="return confirm('Remover item do carrinho?')" style="background:#c62828;color:#fff;border:none;border-radius:6px;padding:8px 18px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block;">Remover</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <div class="frete" style="margin-top:18px;font-size:1.12em;text-align:right;">
        <b>Subtotal:</b> R$<?= number_format($subtotal,2,',','.') ?><br>
        <b>Frete:</b> R$<?= number_format($frete,2,',','.') ?><br>
        <b>Total:</b> R$<?= number_format($total,2,',','.') ?><br>
    </div>
</div>




<div style="margin: 20px 0;">
    <a href="?cupons=1" style="font-weight:bold; color:#1976d2; text-decoration:underline;">Gerenciar Cupons</a>
</div>

<?php if (!empty($carrinho)): ?>
    <form method="post">
        
        <label>Endereço de entrega:</label> <input type="text" name="endereco" placeholder="Digite o endereço de entrega" required>
        <label>Email:</label> <input type="email" name="email" placeholder="Digite seu email" required><br>
        <label>Cupom:</label> <input type="text" name="cupom" placeholder="Digite o código do cupom"><br>
        <input type="hidden" name="total" value="<?= $total ?>">
        <input type="hidden" name="subtotal" value="<?= $subtotal ?>"><br>
        <button type="submit" name="finalizar_pedido" class="finalizar-btn">Finalizar Pedido</button>
    </form>
<?php endif; ?>


<?php if (isset($msg_pedido)): ?>
    <div class="msg-sucesso"> <?= $msg_pedido ?> </div>
<?php endif; ?>

</div>

</div>

<div style="display: flex; flex-direction: column; align-items: center; margin-top: 30px;">
    <h3 style="text-align: center;">Verificação de CEP</h3>
    <form method="get" class="cep-form" style="display: flex; gap: 8px; justify-content: center; align-items: center;">
        <input type="text" name="cep" placeholder="Digite o CEP">
        <button type="submit">Buscar</button>
    </form>
    <?php
    if (isset($_GET['cep'])) {
        $cep = $_GET['cep'];
        $endereco = buscarCep($cep);
        if (isset($endereco['erro'])) {
            echo '<div class="msg-sucesso" style="color:#c62828;background:#fdecea;border-color:#f5c6cb; margin-top:10px;">CEP não encontrado!</div>';
        } else {
            echo '<div class="msg-sucesso" style="margin-top:10px;">Endereço: ' . $endereco['logradouro'] . ', ' . $endereco['bairro'] . ', ' . $endereco['localidade'] . ' - ' . $endereco['uf'] . '</div>';
        }
    }
    ?>
</div>
</body>
</html>
</body>
</html>
