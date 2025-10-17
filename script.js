// Variables globales
let updateInterval;
let currentRoomId = null;

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    initializeLobby();
});

function initializeLobby() {
    // Ping régulier pour rester actif
    pingServer();
    setInterval(pingServer, 3000);
    
    // Mise à jour des listes
    updatePlayers();
    updateRooms();
    setInterval(updatePlayers, 2000);
    setInterval(updateRooms, 2000);
    
    // Gestionnaires d'événements
    setupEventListeners();
}

function setupEventListeners() {
    const createRoomBtn = document.getElementById('create-room-btn');
    const modal = document.getElementById('create-room-modal');
    const closeBtn = modal.querySelector('.close');
    const form = document.getElementById('create-room-form');
    
    createRoomBtn.addEventListener('click', () => {
        modal.classList.add('active');
    });
    
    closeBtn.addEventListener('click', () => {
        modal.classList.remove('active');
    });
    
    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });
    
    form.addEventListener('submit', handleCreateRoom);
    
    // Ajuster le max players selon le jeu
    document.getElementById('game-type').addEventListener('change', function() {
        const maxPlayersSelect = document.getElementById('max-players');
        const gameType = this.value;
        
        // Restrictions selon le jeu
        const restrictions = {
            'bomber': {min: 2, max: 4},
            'memory': {min: 2, max: 4},
            'werewolf': {min: 4, max: 8},
            'typing': {min: 2, max: 6},
            'aim': {min: 2, max: 6}
        };
        
        if (restrictions[gameType]) {
            const options = maxPlayersSelect.options;
            for (let i = 0; i < options.length; i++) {
                const value = parseInt(options[i].value);
                options[i].disabled = value < restrictions[gameType].min || value > restrictions[gameType].max;
            }
            // Sélectionner une valeur valide
            maxPlayersSelect.value = restrictions[gameType].min;
        } else {
            // Réactiver toutes les options
            for (let option of maxPlayersSelect.options) {
                option.disabled = false;
            }
        }
    });
}

// Communication serveur
async function pingServer() {
    try {
        await fetch('api.php?action=ping');
    } catch (error) {
        console.error('Ping failed:', error);
    }
}

async function updatePlayers() {
    try {
        const response = await fetch('api.php?action=get_players');
        const data = await response.json();
        
        const playersList = document.getElementById('players-list');
        const playerCount = document.getElementById('player-count');
        
        if (data.players && data.players.length > 0) {
            playerCount.textContent = data.players.length;
            
            playersList.innerHTML = data.players.map(player => `
                <div class="player-item">
                    <span class="player-name">${player.username}</span>
                    <span class="badge">🟢</span>
                </div>
            `).join('');
        } else {
            playerCount.textContent = '0';
            playersList.innerHTML = '<p class="loading">Aucun joueur connecté</p>';
        }
    } catch (error) {
        console.error('Error updating players:', error);
    }
}

async function updateRooms() {
    try {
        const response = await fetch('api.php?action=get_rooms');
        const data = await response.json();
        
        const roomsList = document.getElementById('rooms-list');
        
        if (data.rooms && data.rooms.length > 0) {
            roomsList.innerHTML = data.rooms.map(room => createRoomCard(room)).join('');
            
            // Ajouter les gestionnaires de clic
            document.querySelectorAll('.room-card').forEach(card => {
                card.addEventListener('click', () => {
                    const roomId = card.dataset.roomId;
                    joinRoom(roomId);
                });
            });
        } else {
            roomsList.innerHTML = '<p class="no-rooms">Aucune partie en cours. Sois le premier à en créer une !</p>';
        }
    } catch (error) {
        console.error('Error updating rooms:', error);
    }
}

function createRoomCard(room) {
    const playerCount = Object.keys(room.players).length;
    const isFull = playerCount >= room.max_players;
    const isPlaying = room.status === 'playing';
    
    return `
        <div class="room-card ${isFull || isPlaying ? 'disabled' : ''}" data-room-id="${room.id}">
            <div class="room-header">
                <div>
                    <div class="room-name">${room.name}</div>
                    <div class="room-game-type">${getGameName(room.game_type)}</div>
                </div>
                <span class="room-status ${room.status}">${getStatusText(room.status)}</span>
            </div>
            <div class="room-info">
                <div class="room-players">
                    <span>👥 ${playerCount} / ${room.max_players}</span>
                </div>
                <span>Hôte: ${room.host_name}</span>
            </div>
            ${isFull ? '<p style="color: #e74c3c; margin-top: 10px;">🔒 Partie complète</p>' : ''}
            ${isPlaying ? '<p style="color: #0984e3; margin-top: 10px;">🎮 Partie en cours</p>' : ''}
        </div>
    `;
}

function getGameName(type) {
    const names = {
        'drawing': '🎨 Dessin Délirant',
        'quiz': '🧠 Quiz Rapide',
        'bomber': '💣 Bomber Battle',
        'chifoumi': '✊ Chifoumi Royale',
        'memory': '🃏 Memory Battle',
        'typing': '⌨️ Course de Mots',
        'werewolf': '🐺 Loup-Garou',
        'aim': '🎯 Vise Juste'
    };
    return names[type] || type;
}

function getStatusText(status) {
    const texts = {
        'waiting': '⏳ En attente',
        'playing': '🎮 En cours',
        'finished': '✅ Terminée'
    };
    return texts[status] || status;
}

// Actions
async function handleCreateRoom(e) {
    e.preventDefault();
    
    const roomName = document.getElementById('room-name').value;
    const gameType = document.getElementById('game-type').value;
    const maxPlayers = document.getElementById('max-players').value;
    
    if (!roomName || !gameType) {
        alert('Veuillez remplir tous les champs');
        return;
    }
    
    try {
        const response = await fetch('api.php?action=create_room', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                name: roomName,
                game_type: gameType,
                max_players: parseInt(maxPlayers)
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Fermer le modal
            document.getElementById('create-room-modal').classList.remove('active');
            
            // Réinitialiser le formulaire
            document.getElementById('create-room-form').reset();
            
            // Rediriger vers la page de jeu
            window.location.href = `game.php?room=${data.room_id}`;
        } else {
            alert('Erreur lors de la création de la partie: ' + (data.error || 'Erreur inconnue'));
        }
    } catch (error) {
        console.error('Error creating room:', error);
        alert('Erreur lors de la création de la partie');
    }
}

async function joinRoom(roomId) {
    try {
        const response = await fetch('api.php?action=join_room', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                room_id: roomId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.location.href = `game.php?room=${roomId}`;
        } else {
            alert('Impossible de rejoindre la partie: ' + (data.error || 'Erreur inconnue'));
        }
    } catch (error) {
        console.error('Error joining room:', error);
        alert('Erreur lors de la tentative de rejoindre la partie');
    }
}
            