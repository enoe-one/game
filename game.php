<?php
session_start();

if (!isset($_SESSION['username']) || !isset($_GET['room'])) {
    header('Location: index.php');
    exit;
}

$roomId = $_GET['room'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partie en cours</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="game-page">
    <div class="game-container">
        <header class="game-header">
            <div class="game-info">
                <h2 id="game-title">Chargement...</h2>
                <div class="game-status" id="game-status"></div>
            </div>
            <div class="player-info">
                <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="lobby.php" class="btn btn-small btn-danger" id="leave-game">Quitter</a>
            </div>
        </header>

        <div class="game-content">
            <!-- Zone de jeu - sera remplie dynamiquement -->
            <div id="game-area" class="game-area">
                <div class="loading-game">
                    <p>Chargement de la partie...</p>
                </div>
            </div>

            <!-- Panneau des joueurs et scores -->
            <aside class="game-sidebar">
                <div class="panel">
                    <h3>🏆 Scores</h3>
                    <div id="scores-list" class="scores-list"></div>
                </div>

                <div class="panel">
                    <h3>👥 Joueurs</h3>
                    <div id="game-players-list" class="players-list-game"></div>
                </div>

                <div class="panel chat-panel">
                    <h3>💬 Chat</h3>
                    <div id="chat-messages" class="chat-messages"></div>
                    <div class="chat-input">
                        <input type="text" id="chat-input" placeholder="Message...">
                        <button id="chat-send">📤</button>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <script>
        const roomId = '<?= $roomId ?>';
        const userId = '<?= $_SESSION['user_id'] ?>';
        const username = '<?= htmlspecialchars($_SESSION['username']) ?>';
        
        let currentGame = null;
        let currentRoom = null;
        let gameInterval = null;

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            startGameLoop();
            setupEventListeners();
        });

        function setupEventListeners() {
            document.getElementById('leave-game').addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('Êtes-vous sûr de vouloir quitter la partie ?')) {
                    leaveRoom();
                }
            });

            document.getElementById('chat-send').addEventListener('click', sendChatMessage);
            document.getElementById('chat-input').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') sendChatMessage();
            });
        }

        function startGameLoop() {
            updateGame();
            gameInterval = setInterval(updateGame, 1000);
        }

        async function updateGame() {
            try {
                // Récupérer les infos de la room
                const roomResponse = await fetch(`api.php?action=get_room&room_id=${roomId}`);
                const roomData = await roomResponse.json();
                
                if (roomData.error) {
                    window.location.href = 'lobby.php';
                    return;
                }
                
                currentRoom = roomData.room;
                updateScores();
                updatePlayersList();

                // Si le jeu a commencé
                if (currentRoom.status === 'playing') {
                    const gameResponse = await fetch(`api.php?action=get_game&room_id=${roomId}`);
                    const gameData = await gameResponse.json();
                    
                    if (!gameData.error) {
                        currentGame = gameData.game;
                        renderGame();
                    }
                } else {
                    // Afficher la salle d'attente
                    renderWaitingRoom();
                }
            } catch (error) {
                console.error('Error updating game:', error);
            }
        }

        function renderWaitingRoom() {
            const gameArea = document.getElementById('game-area');
            const isHost = currentRoom.host === userId;
            const playerCount = Object.keys(currentRoom.players).length;
            const canStart = playerCount >= 2;

            let html = `
                <div class="waiting-room">
                    <h2>🎮 Salle d'attente</h2>
                    <p class="game-type">${getGameName(currentRoom.game_type)}</p>
                    <p class="player-count">${playerCount} / ${currentRoom.max_players} joueurs</p>
                    
                    <div class="players-waiting">
                        ${Object.values(currentRoom.players).map(p => `
                            <div class="player-card ${p.ready ? 'ready' : ''}">
                                <span class="player-name">${p.username}</span>
                                ${p.id === currentRoom.host ? '<span class="host-badge">👑 Hôte</span>' : ''}
                                ${p.ready ? '<span class="ready-badge">✅ Prêt</span>' : '<span class="not-ready-badge">⏳ Pas prêt</span>'}
                            </div>
                        `).join('')}
                    </div>
                    
                    <div class="waiting-actions">
                        ${!isHost ? `
                            <button class="btn btn-primary" onclick="toggleReady()">
                                ${currentRoom.players[userId].ready ? '❌ Annuler' : '✅ Je suis prêt'}
                            </button>
                        ` : `
                            <button class="btn btn-success ${canStart ? '' : 'disabled'}" 
                                    onclick="startGame()" 
                                    ${canStart ? '' : 'disabled'}>
                                🚀 Lancer la partie
                            </button>
                            ${!canStart ? '<p class="warning">Minimum 2 joueurs requis</p>' : ''}
                        `}
                    </div>
                </div>
            `;
            
            gameArea.innerHTML = html;
            document.getElementById('game-title').textContent = currentRoom.name;
            document.getElementById('game-status').textContent = 'En attente des joueurs...';
        }

        function renderGame() {
            document.getElementById('game-title').textContent = getGameName(currentGame.type);
            
            switch(currentGame.type) {
                case 'chifoumi':
                    renderChifoumi();
                    break;
                case 'quiz':
                    renderQuiz();
                    break;
                case 'typing':
                    renderTyping();
                    break;
                case 'memory':
                    renderMemory();
                    break;
                case 'drawing':
                    renderDrawing();
                    break;
                default:
                    document.getElementById('game-area').innerHTML = '<p>Jeu non implémenté</p>';
            }

            if (currentGame.status === 'finished') {
                showGameResults();
            }
        }

        function renderChifoumi() {
            const hasChosen = currentGame.choices && currentGame.choices[userId];
            const allChosen = Object.keys(currentGame.choices || {}).length === Object.keys(currentGame.players).length;
            
            let html = `
                <div class="chifoumi-game">
                    <h3>Manche ${currentGame.round} / ${currentGame.max_rounds}</h3>
                    ${currentGame.last_results ? `
                        <div class="last-results">
                            <h4>Résultat précédent:</h4>
                            ${currentGame.last_results.tie ? 
                                '<p>🤝 Égalité !</p>' : 
                                `<p>🎉 ${currentGame.last_results.winning_play} gagne !</p>`}
                        </div>
                    ` : ''}
                    
                    ${!hasChosen && !allChosen ? `
                        <div class="chifoumi-choices">
                            <button class="choice-btn" onclick="makeChoice('rock')">
                                <span class="choice-icon">✊</span>
                                <span>Pierre</span>
                            </button>
                            <button class="choice-btn" onclick="makeChoice('paper')">
                                <span class="choice-icon">✋</span>
                                <span>Feuille</span>
                            </button>
                            <button class="choice-btn" onclick="makeChoice('scissors')">
                                <span class="choice-icon">✌️</span>
                                <span>Ciseaux</span>
                            </button>
                        </div>
                    ` : `
                        <div class="waiting-others">
                            <p>⏳ En attente des autres joueurs...</p>
                            <p>${Object.keys(currentGame.choices || {}).length} / ${Object.keys(currentGame.players).length} ont choisi</p>
                        </div>
                    `}
                </div>
            `;
            
            document.getElementById('game-area').innerHTML = html;
            document.getElementById('game-status').textContent = hasChosen ? 'Choix effectué !' : 'Faites votre choix !';
        }

        function renderQuiz() {
            if (!currentGame.questions || currentGame.current_question >= currentGame.questions.length) {
                return;
            }

            const question = currentGame.questions[currentGame.current_question];
            const hasAnswered = currentGame.answers && currentGame.answers[userId];
            
            let html = `
                <div class="quiz-game">
                    <div class="quiz-header">
                        <h3>Question ${currentGame.current_question + 1} / ${currentGame.questions.length}</h3>
                    </div>
                    
                    <div class="quiz-question">
                        <p class="question-text">${question.q}</p>
                    </div>
                    
                    ${!hasAnswered ? `
                        <div class="quiz-options">
                            ${question.options.map((opt, idx) => `
                                <button class="quiz-option" onclick="answerQuiz(${idx})">
                                    ${opt}
                                </button>
                            `).join('')}
                        </div>
                    ` : `
                        <div class="waiting-others">
                            <p>✅ Réponse enregistrée !</p>
                            <p>⏳ En attente des autres joueurs...</p>
                            <p>${Object.keys(currentGame.answers).length} / ${Object.keys(currentGame.players).length} ont répondu</p>
                        </div>
                    `}
                </div>
            `;
            
            document.getElementById('game-area').innerHTML = html;
            document.getElementById('game-status').textContent = hasAnswered ? 'Réponse envoyée' : 'Répondez vite !';
        }

        function renderTyping() {
            const currentWord = currentGame.words[currentGame.progress[userId] || 0];
            const progress = (currentGame.progress[userId] || 0) / currentGame.words.length * 100;
            
            let html = `
                <div class="typing-game">
                    <h3>⌨️ Tapez les mots le plus vite possible !</h3>
                    
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${progress}%"></div>
                        <span class="progress-text">${currentGame.progress[userId] || 0} / ${currentGame.words.length}</span>
                    </div>
                    
                    <div class="word-display">
                        <h2>${currentWord || '🎉 Terminé !'}</h2>
                    </div>
                    
                    <div class="typing-input">
                        <input type="text" id="typing-input" placeholder="Tapez ici..." 
                               ${currentGame.winner ? 'disabled' : ''} autofocus>
                    </div>
                    
                    ${currentGame.winner ? `
                        <div class="winner-announcement">
                            🏆 ${currentGame.winner === userId ? 'Vous avez' : currentGame.players[currentGame.winner].username + ' a'} gagné !
                        </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('game-area').innerHTML = html;
            
            if (!currentGame.winner) {
                document.getElementById('typing-input').addEventListener('input', function(e) {
                    if (e.target.value.toLowerCase() === currentWord.toLowerCase()) {
                        typeWord();
                        e.target.value = '';
                    }
                });
            }
            
            document.getElementById('game-status').textContent = currentGame.winner ? 'Partie terminée !' : 'Tapez vite !';
        }

        function renderMemory() {
            const board = currentGame.board;
            const isMyTurn = currentGame.current_player === userId;
            
            let html = `
                <div class="memory-game">
                    <div class="memory-info">
                        <h3>${isMyTurn ? '🎯 C\'est votre tour !' : '⏳ Tour de ' + currentGame.players[currentGame.current_player].username}</h3>
                    </div>
                    
                    <div class="memory-board">
                        ${board.map((icon, idx) => {
                            const isRevealed = currentGame.revealed && currentGame.revealed.includes(idx);
                            const isMatched = currentGame.matched && currentGame.matched.includes(idx);
                            return `
                                <div class="memory-card ${isRevealed || isMatched ? 'revealed' : ''} ${isMatched ? 'matched' : ''}"
                                     onclick="${isMyTurn && !isMatched && !isRevealed ? `flipCard(${idx})` : ''}">
                                    <div class="card-front">${isRevealed || isMatched ? icon : '❓'}</div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
            
            document.getElementById('game-area').innerHTML = html;
            document.getElementById('game-status').textContent = isMyTurn ? 'Votre tour' : 'Attendez votre tour';
        }

        function renderDrawing() {
            const isDrawer = currentGame.current_drawer === userId;
            
            let html = `
                <div class="drawing-game">
                    <div class="drawing-header">
                        <h3>Manche ${currentGame.round} / ${currentGame.max_rounds}</h3>
                        ${isDrawer ? 
                            `<p class="word-to-draw">🎨 Dessinez: <strong>${currentGame.word}</strong></p>` :
                            `<p class="guess-prompt">🤔 Devinez ce que dessine ${currentGame.players[currentGame.current_drawer].username}</p>`
                        }
                    </div>
                    
                    <div class="drawing-area">
                        <canvas id="drawing-canvas" width="800" height="600"></canvas>
                        ${isDrawer ? `
                            <div class="drawing-tools">
                                <button onclick="clearCanvas()">🗑️ Effacer</button>
                                <input type="color" id="color-picker" value="#000000">
                                <input type="range" id="brush-size" min="1" max="20" value="3">
                            </div>
                        ` : ''}
                    </div>
                    
                    ${!isDrawer ? `
                        <div class="guess-input">
                            <input type="text" id="guess-input" placeholder="Votre réponse...">
                            <button onclick="submitGuess()">Envoyer</button>
                        </div>
                    ` : ''}
                    
                    <div class="guesses-list">
                        ${(currentGame.guesses || []).map(g => `
                            <div class="guess-item ${g.correct ? 'correct' : ''}">
                                <strong>${g.username}:</strong> ${g.guess}
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            
            document.getElementById('game-area').innerHTML = html;
            
            if (isDrawer) {
                setupDrawingCanvas();
            }
        }

        // Actions de jeu
        async function toggleReady() {
            await fetch('api.php?action=toggle_ready', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({room_id: roomId})
            });
        }

        async function startGame() {
            await fetch('api.php?action=start_game', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({room_id: roomId})
            });
        }

        async function makeChoice(choice) {
            await fetch('api.php?action=game_action', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    room_id: roomId,
                    action_type: 'choose',
                    choice: choice
                })
            });
        }

        async function answerQuiz(answer) {
            await fetch('api.php?action=game_action', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    room_id: roomId,
                    action_type: 'answer',
                    answer: answer
                })
            });
        }

        async function typeWord() {
            await fetch('api.php?action=game_action', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    room_id: roomId,
                    action_type: 'word_typed'
                })
            });
        }

        async function leaveRoom() {
            await fetch('api.php?action=leave_room', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({room_id: roomId})
            });
            window.location.href = 'lobby.php';
        }

        // Utilitaires
        function updateScores() {
            const scoresList = document.getElementById('scores-list');
            if (!currentGame || !currentGame.scores) {
                scoresList.innerHTML = '<p class="no-scores">Pas encore de scores</p>';
                return;
            }
            
            const sortedScores = Object.entries(currentGame.scores)
                .map(([id, score]) => ({
                    id,
                    username: currentGame.players[id].username,
                    score
                }))
                .sort((a, b) => b.score - a.score);
            
            scoresList.innerHTML = sortedScores.map((player, idx) => `
                <div class="score-item ${player.id === userId ? 'current-user' : ''}">
                    <span class="rank">${idx + 1}.</span>
                    <span class="player-name">${player.username}</span>
                    <span class="score">${player.score}</span>
                </div>
            `).join('');
        }

        function updatePlayersList() {
            const playersList = document.getElementById('game-players-list');
            if (!currentRoom) return;
            
            playersList.innerHTML = Object.values(currentRoom.players).map(p => `
                <div class="player-item ${p.id === userId ? 'current-user' : ''}">
                    <span class="player-name">${p.username}</span>
                    ${p.id === currentRoom.host ? '<span class="badge">👑</span>' : ''}
                </div>
            `).join('');
        }

        function getGameName(type) {
            const names = {
                'chifoumi': '✊ Chifoumi Royale',
                'quiz': '🧠 Quiz Rapide',
                'typing': '⌨️ Course de Mots',
                'memory': '🃏 Memory Battle',
                'drawing': '🎨 Dessin Délirant',
                'bomber': '💣 Bomber Battle',
                'werewolf': '🐺 Loup-Garou',
                'aim': '🎯 Vise Juste'
            };
            return names[type] || type;
        }

        function showGameResults() {
            // Afficher les résultats finaux
            const sortedScores = Object.entries(currentGame.scores)
                .map(([id, score]) => ({
                    id,
                    username: currentGame.players[id].username,
                    score
                }))
                .sort((a, b) => b.score - a.score);
            
            const winner = sortedScores[0];
            
            const resultsHtml = `
                <div class="game-results">
                    <h2>🎉 Partie terminée !</h2>
                    <div class="winner-announcement">
                        <h3>🏆 ${winner.username} remporte la partie !</h3>
                        <p class="winner-score">${winner.score} points</p>
                    </div>
                    
                    <div class="final-rankings">
                        <h4>Classement final :</h4>
                        ${sortedScores.map((player, idx) => `
                            <div class="ranking-item rank-${idx + 1}">
                                <span class="rank-badge">${idx + 1}</span>
                                <span class="player-name">${player.username}</span>
                                <span class="player-score">${player.score} pts</span>
                            </div>
                        `).join('')}
                    </div>
                    
                    <div class="results-actions">
                        <a href="lobby.php" class="btn btn-primary">Retour au lobby</a>
                    </div>
                </div>
            `;
            
            document.getElementById('game-area').innerHTML = resultsHtml;
            document.getElementById('game-status').textContent = 'Partie terminée';
        }

        function sendChatMessage() {
            const input = document.getElementById('chat-input');
            const message = input.value.trim();
            if (message) {
                // Ici on ajouterait l'envoi du message au serveur
                input.value = '';
            }
        }

        // Canvas de dessin
        let canvas, ctx, isDrawing = false;
        
        function setupDrawingCanvas() {
            canvas = document.getElementById('drawing-canvas');
            ctx = canvas.getContext('2d');
            
            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', stopDrawing);
            canvas.addEventListener('mouseout', stopDrawing);
            
            document.getElementById('color-picker').addEventListener('change', (e) => {
                ctx.strokeStyle = e.target.value;
            });
            
            document.getElementById('brush-size').addEventListener('change', (e) => {
                ctx.lineWidth = e.target.value;
            });
            
            ctx.lineWidth = 3;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#000000';
        }
        
        function startDrawing(e) {
            isDrawing = true;
            const rect = canvas.getBoundingClientRect();
            ctx.beginPath();
            ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
        }
        
        function draw(e) {
            if (!isDrawing) return;
            const rect = canvas.getBoundingClientRect();
            ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
            ctx.stroke();
        }
        
        function stopDrawing() {
            isDrawing = false;
        }
        
        function clearCanvas() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
    </script>
</body>
</html>