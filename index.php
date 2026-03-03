<?php
// ==========================================
// BACKEND (PHP) - Streaming y Sincronización Segura
// ==========================================
$state_file = __DIR__ . '/sync_state.json';
$movies_dir = __DIR__ . '/peliculas';

if (!is_dir($movies_dir)) {
    @mkdir($movies_dir, 0777, true);
}

$action = $_GET['action'] ?? '';

// 1. Sincronización
if ($action === 'sync') {
    $fp = fopen($state_file, 'c+'); 
    if (flock($fp, LOCK_EX)) { 
        
        $size = filesize($state_file);
        $state_content = $size > 0 ? fread($fp, $size) : '';
        $state = json_decode($state_content, true);
        
        if (!is_array($state)) {
            $state = ['host_id' => null, 'movie' => '', 'time' => 0, 'status' => 'paused', 'clients' => [], 'updated_at' => microtime(true)];
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $client_id = $input['client_id'] ?? 'unknown';
        $client_name = $input['client_name'] ?? 'Espectador';
        
        $state['clients'][$client_id] = [
            'name' => $client_name,
            'last_seen' => time()
        ];

        foreach ($state['clients'] as $cid => $data) {
            if (time() - $data['last_seen'] > 15) unset($state['clients'][$cid]);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($input['take_control'])) {
                $state['host_id'] = $client_id;
            } elseif ($state['host_id'] === $client_id) {
                if (isset($input['movie'])) $state['movie'] = $input['movie'];
                if (isset($input['status'])) $state['status'] = $input['status'];
                if (isset($input['time'])) {
                    $state['time'] = (float)$input['time'];
                    $state['updated_at'] = microtime(true); 
                }
            }
        }

        ftruncate($fp, 0); 
        rewind($fp);      
        fwrite($fp, json_encode($state)); 
        flock($fp, LOCK_UN); 
    }
    fclose($fp);

    header('Content-Type: application/json');
    echo json_encode($state);
    exit;
}

// 2. Listar películas
if ($action === 'list') {
    $movies = [];
    $allowed_exts = ['mp4', 'webm', 'mkv'];
    
    if (is_dir($movies_dir)) {
        $files = scandir($movies_dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed_exts)) {
                $name_without_ext = pathinfo($file, PATHINFO_FILENAME);
                $has_vtt = file_exists($movies_dir . '/' . $name_without_ext . '.vtt');
                
                $movies[] = [
                    'filename' => $file,
                    'has_vtt' => $has_vtt
                ];
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode($movies);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LovingRoom</title>
    <script src="https://www.youtube.com/iframe_api"></script>
    <style>
        :root { --bg: #121212; --panel: #1e1e1e; --primary: #e50914; --text: #e0e0e0; --success: #4caf50; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 20px; display: flex; flex-direction: column; align-items: center; min-height: 100vh;}
        .container { max-width: 1100px; width: 100%; display: grid; gap: 20px; grid-template-columns: 2fr 1fr; flex: 1;}
        .panel { background: var(--panel); padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.5); }
        h1, h2, h3 { margin-top: 0; color: #fff; }
        .header-title { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 10px; width: 100%; margin-bottom: 20px;}
        .header-title h1 { margin: 0; }
        
        input[type="text"] { width: 100%; padding: 12px; margin: 10px 0; border: none; border-radius: 5px; background: #333; color: #fff; box-sizing: border-box; font-size: 16px;}
        button { padding: 12px 15px; background: var(--primary); color: #fff; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; transition: 0.2s; font-size: 1em;}
        button:hover { filter: brightness(1.2); }
        .host-btn { background: #ff9800; width: 100%; margin-bottom: 10px; color: #000;}
        
        .movie-list { list-style: none; padding: 0; max-height: 400px; overflow-y: auto;}
        .movie-list li { display: flex; justify-content: space-between; align-items: center; padding: 14px 10px; border-bottom: 1px solid #333; cursor: pointer; transition: 0.2s; word-break: break-word;}
        .movie-list li:hover { background: #2a2a2a; border-left: 4px solid var(--primary); }
        .movie-icon { font-size: 1.2em; flex-shrink: 0; margin-left: 10px;}
        
        .video-wrapper { position: relative; border-radius: 10px; overflow: hidden; background: #000; }
        video { width: 100%; display: block; outline: none; aspect-ratio: 16/9; }
        
        #ytPlayerContainer { width: 100%; aspect-ratio: 16/9; background: #000; }
        #ytPlayerContainer iframe { width: 100%; height: 100%; border: none; display: block;}

        #playOverlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.85); display: flex; justify-content: center; align-items: center; z-index: 10; flex-direction: column; gap: 15px; text-align: center; padding: 20px;}
        #playOverlay.hidden { display: none !important; }
        #playOverlay button { font-size: 1.1em; padding: 15px 25px; }

        .dashboard { font-size: 0.9em; color: #aaa; }
        .dashboard span { display: block; padding: 10px; background: #2a2a2a; margin-top: 5px; border-radius: 5px; border-left: 3px solid var(--success);}
        .hidden { display: none !important; }
        #overlay { position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.95); z-index: 1000; display:flex; justify-content:center; align-items:center; flex-direction:column; padding: 20px; box-sizing: border-box;}
        .controls-row { display: flex; gap: 10px; margin-top: 15px; align-items: center; justify-content: space-between;}
        .badge { background: #444; padding: 6px 12px; border-radius: 12px; font-size: 0.8em; margin-left: 10px; white-space: nowrap;}
        .sub-badge { font-size: 0.7em; background: #555; padding: 3px 6px; border-radius: 4px; margin-left: 10px; color: #ddd;}

        .host-only { display: none; }
        .is-host .host-only { display: flex; }

        .dedication-footer { margin-top: 40px; text-align: center; color: #666; font-size: 0.9em; padding-bottom: 20px; width: 100%;}
        .dedication-footer .heart { color: var(--primary); font-size: 1.2em; vertical-align: middle;}
        .dedication-footer .name { color: #eee; font-weight: bold; letter-spacing: 1px;}

        @media (max-width: 768px) { 
            body { padding: 10px; }
            .container { grid-template-columns: 1fr; gap: 15px; }
            .panel { padding: 15px; }
            .controls-row { flex-direction: column; align-items: stretch; }
            .header-title h1 { font-size: 1.6em; }
            .badge { margin-left: 0; margin-top: 5px;}
        }
    </style>
</head>
<body>

    <div id="overlay" class="hidden">
        <div class="panel" style="max-width: 400px; width: 100%; text-align: center;">
            <h2>🍿 Bienvenido a LovingRoom</h2>
            <p>¿Cómo te llamas? (Para saber quién está en la sala)</p>
            <input type="text" id="pref_name" placeholder="Tu nombre o apodo" onkeypress="if(event.key === 'Enter') savePreferences()">
            <button onclick="savePreferences()" style="width: 100%; margin-top: 15px;">Entrar al Cine</button>
        </div>
    </div>

    <div class="header-title">
        <h1>🎬 LovingRoom</h1>
        <span id="roleBadge" class="badge">Conectando...</span>
    </div>

    <div class="container" id="mainContainer">
        <div class="panel">
            <div class="video-wrapper">
                <video id="player" controls playsinline preload="auto">Tu navegador no soporta vídeo HTML5.</video>
                <div id="ytPlayerContainer" class="hidden">
                    <div id="ytPlayer"></div>
                </div>
                
                <div id="playOverlay" class="hidden">
                    <h3 style="margin:0;">El anfitrión ha iniciado el vídeo</h3>
                    <button onclick="forceUserPlay()">▶️ Toca para Sincronizar</button>
                </div>
            </div>
            
            <div class="controls-row">
                <button class="host-btn" onclick="takeControl()" id="btnTakeControl">👑 Tomar el Control (Anfitrión)</button>
            </div>

            <div class="controls-row host-only" style="background: #2a2a2a; padding: 15px; border-radius: 5px; flex-direction: column; align-items: stretch; gap: 10px; border-left: 4px solid var(--primary);">
                <label style="font-size: 0.9em; color: #fff; margin-bottom: 0; font-weight: bold;">🔴 Cargar vídeo de YouTube:</label>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <input type="text" id="ytUrlInput" placeholder="Pega el enlace de YouTube aquí..." style="margin: 0; flex: 1; min-width: 200px;">
                    <button onclick="loadYouTubeURL()" style="white-space: nowrap; flex-shrink: 0;">Cargar YT</button>
                </div>
            </div>

            <div class="controls-row" style="background: #222; padding: 15px; border-radius: 5px;">
                <label style="font-size: 0.9em; color: #aaa; margin-bottom: 5px; display: block;">Subtítulo manual para vídeos locales (.vtt):</label>
                <input type="file" id="sub_upload" accept=".vtt" onchange="loadManualSubtitle(event)" style="font-size: 0.9em; color: #aaa; width: 100%;">
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 20px;">
            <div class="panel">
                <h3 style="margin-bottom: 15px;">📁 Carpeta /peliculas</h3>
                <ul class="movie-list" id="movieList">
                    <li>Cargando servidor...</li>
                </ul>
                <button onclick="fetchMovies()" style="width: 100%; margin-top: 10px; background: #333;">🔄 Actualizar Lista</button>
            </div>

            <div class="panel" id="dashboardPanel">
                <h3>👥 Espectadores Conectados</h3>
                <div class="dashboard" id="clientList">
                    Buscando en la sala...
                </div>
            </div>
        </div>
    </div>

    <footer class="dedication-footer">
        Dedicated to the love of my life, <span class="name">Dafne</span> <span class="heart">❤️</span>
    </footer>

<script>
    function setCookie(name, value, days) {
        let date = new Date();
        date.setTime(date.getTime() + (days*24*60*60*1000));
        document.cookie = name + "=" + encodeURIComponent(value)  + "; expires=" + date.toUTCString() + "; path=/";
    }
    function getCookie(name) {
        let match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        if (match) return decodeURIComponent(match[2]);
        return null;
    }

    let clientName = getCookie('pref_name');
    let clientId = 'client_' + Math.random().toString(36).substr(2, 9);
    let isHost = false;
    let currentMovie = "";
    let isSyncing = false; 
    let currentMediaType = 'html5';
    
    const player = document.getElementById('player');
    const playOverlay = document.getElementById('playOverlay');
    const badge = document.getElementById('roleBadge');
    const mainContainer = document.getElementById('mainContainer');

    // --- YOUTUBE API MEJORADA ---
    let ytPlayer;
    let isYtReady = false;
    let pendingYtVideoId = null; // Cola de espera para cuando cargue lento

    function onYouTubeIframeAPIReady() {
        ytPlayer = new YT.Player('ytPlayer', {
            height: '100%',
            width: '100%',
            videoId: '',
            playerVars: { 'autoplay': 0, 'controls': 1, 'rel': 0, 'disablekb': 0 },
            events: {
                'onReady': function(event) {
                    isYtReady = true;
                    if (pendingYtVideoId) {
                        ytPlayer.loadVideoById(pendingYtVideoId);
                        pendingYtVideoId = null;
                    }
                },
                'onStateChange': onYtStateChange
            }
        });
    }

    function onYtStateChange(event) {
        // Solo lanzamos actualización si el usuario es el host y dio play (1) o pausa (2)
        if (isHost && currentMediaType === 'youtube' && (event.data === 1 || event.data === 2)) {
            clearTimeout(userActionTimeout);
            userActionTimeout = setTimeout(() => {
                if (!isSyncing) syncLoop();
            }, 300);
        }
    }

    // Funciones universales reforzadas
    function getMediaTime() {
        if (currentMediaType === 'youtube' && isYtReady && ytPlayer.getCurrentTime) {
            return ytPlayer.getCurrentTime() || 0;
        }
        return player.currentTime;
    }

    function getMediaPaused() {
        if (currentMediaType === 'youtube' && isYtReady && ytPlayer.getPlayerState) {
            const state = ytPlayer.getPlayerState();
            // Lo consideramos pausado si está Sin Empezar (-1), Pausado (2), o En Cola (5)
            return state === -1 || state === 2 || state === 5;
        }
        return player.paused;
    }

    function setMediaTime(time) {
        if (currentMediaType === 'youtube' && isYtReady && ytPlayer.seekTo) {
            ytPlayer.seekTo(time, true);
        } else {
            player.currentTime = time;
        }
    }

    function playMedia() {
        if (currentMediaType === 'youtube' && isYtReady && ytPlayer.playVideo) {
            ytPlayer.playVideo();
            // Detector de bloqueo de Autoplay para YouTube
            setTimeout(() => {
                const state = ytPlayer.getPlayerState();
                if (state !== 1 && state !== 3) { // Si no está reproduciendo ni cargando buffer
                    if (!isHost) playOverlay.classList.remove('hidden');
                }
            }, 800);
        } else {
            const playPromise = player.play();
            if (playPromise !== undefined) {
                playPromise.catch(() => {
                    if(!isHost) playOverlay.classList.remove('hidden');
                });
            }
        }
    }

    function pauseMedia() {
        if (currentMediaType === 'youtube' && isYtReady && ytPlayer.pauseVideo) {
            ytPlayer.pauseVideo();
        } else {
            player.pause();
        }
    }

    function isMediaReady() {
        if (currentMediaType === 'youtube') {
            // Ya no dependemos de que el estado no sea -1, solo de que el iframe exista
            return isYtReady && typeof ytPlayer.getPlayerState === 'function';
        }
        return player.readyState >= 1;
    }

    // --- LÓGICA DE INICIO ---
    if (!clientName) {
        document.getElementById('overlay').classList.remove('hidden');
        document.getElementById('pref_name').focus();
    } else {
        startApp();
    }

    function savePreferences() {
        const nameInput = document.getElementById('pref_name').value.trim();
        if(nameInput) {
            clientName = nameInput;
            setCookie('pref_name', clientName, 365);
            document.getElementById('overlay').classList.add('hidden');
            startApp();
        }
    }

    function startApp() {
        fetchMovies(); 
        setInterval(syncLoop, 2000); 
    }

    async function takeControl() {
        await fetch('?action=sync', {
            method: 'POST',
            body: JSON.stringify({ client_id: clientId, take_control: true })
        });
        isHost = true;
        updateUI();
        syncLoop();
    }

    async function fetchMovies() {
        const list = document.getElementById('movieList');
        list.innerHTML = '<li>Buscando...</li>';
        try {
            const res = await fetch(`?action=list&_t=${Date.now()}`);
            const movies = await res.json();
            list.innerHTML = '';
            if (movies.length === 0) {
                list.innerHTML = '<li style="color:#aaa;">No hay películas en /peliculas</li>';
                return;
            }
            movies.forEach(m => {
                const li = document.createElement('li');
                li.onclick = () => playMovie(m.filename, m.has_vtt);
                const titleSpan = document.createElement('span');
                titleSpan.textContent = m.filename;
                if (m.has_vtt) {
                    const subBadge = document.createElement('span');
                    subBadge.className = 'sub-badge';
                    subBadge.textContent = 'CC';
                    titleSpan.appendChild(subBadge);
                }
                
                // --- INICIO CÓDIGO AÑADIDO (BOTÓN DE DESCARGA) ---
                const actionsContainer = document.createElement('div');
                actionsContainer.style.display = 'flex';
                actionsContainer.style.alignItems = 'center';

                const icon = document.createElement('span');
                icon.className = 'movie-icon';
                icon.innerHTML = '▶️';

                const downloadBtn = document.createElement('a');
                downloadBtn.href = 'peliculas/' + encodeURIComponent(m.filename);
                downloadBtn.download = m.filename;
                downloadBtn.innerHTML = '⬇️';
                downloadBtn.title = 'Descargar película';
                downloadBtn.style.textDecoration = 'none';
                downloadBtn.style.marginLeft = '15px';
                downloadBtn.style.fontSize = '1.2em';
                downloadBtn.onclick = (e) => e.stopPropagation();

                actionsContainer.appendChild(icon);
                actionsContainer.appendChild(downloadBtn);

                li.appendChild(titleSpan);
                li.appendChild(actionsContainer);
                // --- FIN CÓDIGO AÑADIDO ---

                list.appendChild(li);
            });
        } catch(e) {
            list.innerHTML = '<li style="color:#e50914;">Error al cargar películas</li>';
        }
    }

    function loadYouTubeURL() {
        if (!isHost) return;
        const url = document.getElementById('ytUrlInput').value.trim();
        if (!url) return;
        
        let match = url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))((\w|-){11})/);
        const videoId = (match && match[1]) ? match[1] : null;
        
        if (!videoId) {
            alert("Enlace de YouTube no válido.");
            return;
        }
        
        const mediaStr = 'yt:' + videoId;
        currentMovie = mediaStr;
        loadMediaToPlayer(mediaStr, false);
        document.getElementById('ytUrlInput').value = ''; 
        syncLoop(); 
    }

    function playMovie(filename, hasVtt) {
        if (!isHost) {
            alert("Solo el anfitrión puede cambiar la película.");
            return;
        }
        currentMovie = filename; 
        loadMediaToPlayer(filename, hasVtt);
        syncLoop(); 
    }

    function loadMediaToPlayer(mediaStr, hasVtt) {
        if (mediaStr.startsWith('yt:')) {
            currentMediaType = 'youtube';
            const videoId = mediaStr.substring(3);
            
            player.classList.add('hidden');
            player.pause(); 
            
            document.getElementById('ytPlayerContainer').classList.remove('hidden');
            
            if (isYtReady) {
                ytPlayer.loadVideoById(videoId);
            } else {
                // Si la API tarda un segundo más, lo guardamos en cola
                pendingYtVideoId = videoId;
            }
        } else {
            currentMediaType = 'html5';
            document.getElementById('ytPlayerContainer').classList.add('hidden');
            if (isYtReady && ytPlayer.stopVideo) ytPlayer.stopVideo();
            
            player.classList.remove('hidden');
            player.src = 'peliculas/' + encodeURIComponent(mediaStr);
            player.querySelectorAll('track').forEach(t => t.remove());

            if (hasVtt) {
                const vttFilename = mediaStr.substring(0, mediaStr.lastIndexOf('.')) + '.vtt';
                const track = document.createElement('track');
                track.kind = 'subtitles';
                track.label = 'Servidor';
                track.srclang = 'es';
                track.default = true;
                track.src = 'peliculas/' + encodeURIComponent(vttFilename);
                player.appendChild(track);
            }
            playMedia();
        }
    }

    function forceUserPlay() {
        playOverlay.classList.add('hidden');
        if (currentMediaType === 'youtube' && isYtReady) {
            ytPlayer.playVideo();
        } else {
            player.play().catch(e => console.error("Error al forzar play", e));
        }
    }

    function loadManualSubtitle(event) {
        if (currentMediaType === 'youtube') {
            alert("Los subtítulos locales solo funcionan con películas descargadas, no con YouTube.");
            return;
        }
        const file = event.target.files[0];
        if (file) {
            const track = document.createElement('track');
            track.kind = 'subtitles';
            track.label = 'Local';
            track.srclang = 'es';
            track.src = URL.createObjectURL(file);
            track.default = true;
            player.querySelectorAll('track').forEach(t => t.remove());
            player.appendChild(track);
        }
    }

    async function syncLoop() {
        if (isSyncing) return;
        isSyncing = true;

        try {
            let reqBody = { client_id: clientId, client_name: clientName };
            if (isHost) {
                reqBody.movie = currentMovie;
                reqBody.time = getMediaTime();
                reqBody.status = getMediaPaused() ? 'paused' : 'playing';
            }

            const res = await fetch(`?action=sync&_t=${Date.now()}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(reqBody)
            });

            if (!res.ok) throw new Error("Server response not ok");
            const state = await res.json();
            
            isHost = (state.host_id === clientId);
            updateUI(state);

            if (!isHost && state.host_id !== null) {
                
                if (state.movie && state.movie !== currentMovie) {
                    currentMovie = state.movie;
                    loadMediaToPlayer(currentMovie, true); 
                    isSyncing = false;
                    return; 
                }

                if (isMediaReady() && state.movie === currentMovie) {
                    
                    let targetTime = state.time;
                    
                    if (state.status === 'playing' && state.updated_at) {
                        const nowUnix = Date.now() / 1000;
                        const elapsedSeconds = nowUnix - state.updated_at;
                        if (elapsedSeconds > 0 && elapsedSeconds < 3) {
                            targetTime += elapsedSeconds;
                        }
                    }

                    // ==========================================
                    // ⚙️ AJUSTE MANUAL DE SINCRONIZACIÓN
                    // ==========================================
                    const ajusteManual = 1.0; 
                    targetTime += ajusteManual;
                    // ==========================================

                    const currentMediaTime = getMediaTime();
                    const isMediaPaused = getMediaPaused();
                    const timeDiff = Math.abs(currentMediaTime - targetTime);

                    if (state.status === 'playing' && isMediaPaused) {
                        playMedia();
                        if (timeDiff > 0.5) setMediaTime(targetTime);
                        
                    } else if (state.status === 'paused' && !isMediaPaused) {
                        pauseMedia();
                        playOverlay.classList.add('hidden'); 
                        if (timeDiff > 0.5) setMediaTime(targetTime); 
                        
                    } else {
                        if (timeDiff > 0.5) {
                            setMediaTime(targetTime);
                        }
                    }
                }
            } else if (isHost) {
                currentMovie = state.movie;
            }
        } catch (error) {
            console.warn("Fallo temporal en syncLoop:", error);
            badge.style.background = "#e50914";
            badge.textContent = "Reconectando...";
        }

        isSyncing = false;
    }

    function updateUI(state = null) {
        if (isHost) {
            badge.textContent = "Tú eres el Anfitrión";
            badge.style.background = "#ff9800";
            badge.style.color = "#000";
            document.getElementById('dashboardPanel').style.display = 'block';
            mainContainer.classList.add('is-host');
            document.getElementById('btnTakeControl').style.display = 'none';
        } else {
            badge.textContent = "Sincronizado";
            badge.style.background = "#4caf50";
            badge.style.color = "#fff";
            document.getElementById('dashboardPanel').style.display = 'none';
            mainContainer.classList.remove('is-host');
            document.getElementById('btnTakeControl').style.display = 'block';
        }

        if (isHost && state && state.clients) {
            const list = document.getElementById('clientList');
            list.innerHTML = '';
            let count = 0;
            for (let cid in state.clients) {
                count++;
                const isMe = (cid === clientId);
                const name = state.clients[cid].name;
                list.innerHTML += `<span>👤 ${name} ${isMe ? "<strong>(Tú)</strong>" : ""}</span>`;
            }
            if(count === 1) list.innerHTML += "<br><small>Esperando a Dafne...</small>";
        }
    }

    let userActionTimeout;
    ['play', 'pause', 'seeked'].forEach(evt => {
        player.addEventListener(evt, () => {
            if (isHost && currentMediaType === 'html5') {
                clearTimeout(userActionTimeout);
                userActionTimeout = setTimeout(() => {
                    if (!isSyncing) syncLoop();
                }, 300);
            }
        });
    });
</script>
</body>
</html>