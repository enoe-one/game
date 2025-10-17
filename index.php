<?php
session_start();

// Si déjà connecté, rediriger vers le lobby
if (isset($_SESSION['username'])) {
    header('Location: lobby.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['username'])) {
    $_SESSION['username'] = htmlspecialchars(trim($_POST['username']));
    $_SESSION['user_id'] = uniqid();
    $_SESSION['joined_at'] = time();
    header('Location: lobby.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mini-Jeux Entre Amis</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="game-title">
            <h1>🎮 Mini-Jeux Party 🎉</h1>
            <p class="subtitle">Joue avec tes amis à des dizaines de jeux délirants !</p>
        </div>
        
        <form method="POST" class="login-form">
            <div class="form-group">
                <label for="username">Choisis ton pseudo :</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    required 
                    maxlength="20"
                    placeholder="Ton pseudo..."
                    autofocus
                >
            </div>
            <button type="submit" class="btn btn-primary">Entrer dans le lobby 🚀</button>
        </form>
        
        <div class="game-preview">
            <h3>🎲 Jeux disponibles :</h3>
            <div class="games-grid">
                <div class="game-card">🎨 Dessin Délirant</div>
                <div class="game-card">🧠 Quiz Rapide</div>
                <div class="game-card">💣 Bomber Battle</div>
                <div class="game-card">✊ Chifoumi Royale</div>
                <div class="game-card">🃏 Memory Battle</div>
                <div class="game-card">⌨️ Course de Mots</div>
                <div class="game-card">🐺 Loup-Garou</div>
                <div class="game-card">🎯 Vise Juste</div>
            </div>
        </div>
    </div>
</body>
</html>