<?php
// --- BACKEND API LOGIC ---
$leaderboardsDir = 'leaderboards/';$quizzesDir = 'quizzes/';

// Auto-create directories if they don't exist
if (!is_dir($leaderboardsDir)) mkdir($leaderboardsDir, 0755, true);
if (!is_dir($quizzesDir)) mkdir($quizzesDir, 0755, true);

if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    // 1. List available quizzes
    if ($_GET['api'] === 'list_quizzes') {$availableQuizzes = [];
        foreach (glob($quizzesDir . '*.json') as$file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['title'])) {$availableQuizzes[] = [
                    'file' => basename($file),
                    'id' => $data['id'],
                    'title' => $data['title'],
                    'description' => $data['description'] ?? 'Test your knowledge!'
                ];
            }
        }
        echo json_encode($availableQuizzes);
        exit;
    }

    // 2. Fetch specific quiz data
    if ($_GET['api'] === 'get_quiz' && isset($_GET['file'])) {$file = $quizzesDir . basename($_GET['file']);
        echo file_exists($file) ? file_get_contents($file) : json_encode(['error' => 'Not found']);
        exit;
    }

    // 3. Fetch leaderboard for a specific quiz
    if ($_GET['api'] === 'leaderboard' && isset($_GET['quiz_id'])) {$lbFile = $leaderboardsDir . 'lb_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['quiz_id']) . '.json';
        echo file_exists($lbFile) ? file_get_contents($lbFile) : '[]';
        exit;
    }
}

// 4. Save Score
if ($_SERVER['REQUEST_METHOD'] === 'POST') {$input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['username']) && isset($input['score']) && isset($input['quiz_id'])) {
        $lbFile =$leaderboardsDir . 'lb_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $input['quiz_id']) . '.json';$scores = file_exists($lbFile) ? json_decode(file_get_contents($lbFile), true) : [];
        if (!is_array($scores)) $scores = [];$scores[] = [
            'username' => htmlspecialchars(substr($input['username'], 0, 20)), 'score' => (int)$input['score'],
            'correct' => (int)$input['correct'],
            'total' => (int)$input['total'],
            'time' => (int)$input['time'],
            'date' => date('M j, g:i a')
        ];
        
        usort($scores, function($a,$b) {
            if ($b['score'] ===$a['score']) return $a['time'] <=>$b['time'];
            return $b['score'] <=>$a['score'];
        });
        
        file_put_contents($lbFile, json_encode(array_slice($scores, 0, 50)));
        echo json_encode(['success' => true]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Trivia Night</title>
    <style>
        /* --- CSS ANIMATIONS & STYLING --- */
        :root { --primary: #bb86fc; --bg: #121212; --card-bg: #1e1e1e; --success: #4caf50; --danger: #f44336; }
        body { font-family: system-ui, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background-color: var(--bg); color: #e0e0e0; line-height: 1.6; overflow-x: hidden; }
        
        /* Animations */
        @keyframes slideInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes shake { 0%, 100% { transform: translateX(0); } 20%, 60% { transform: translateX(-5px); } 40%, 80% { transform: translateX(5px); } }
        @keyframes pulseSuccess { 0% { box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.7); } 70% { box-shadow: 0 0 0 15px rgba(76, 175, 80, 0); } 100% { box-shadow: 0 0 0 0 rgba(76, 175, 80, 0); } }
        
        .animated-slide { animation: slideInUp 0.4s ease-out forwards; }
        .animated-fade { animation: fadeIn 0.3s ease-out forwards; }
        
        /* Header */
        .header-bar { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        h1 { color: var(--primary); margin: 0; font-size: 1.8em; text-shadow: 0 2px 10px rgba(187, 134, 252, 0.2); }
        .timer { font-size: 1.5em; font-family: monospace; color: #03dac6; background: var(--card-bg); padding: 5px 15px; border-radius: 8px; transition: color 0.3s; }
        .timer.warning { color: var(--danger); animation: shake 0.5s infinite; }

        /* Quiz Selection Grid */
        .quiz-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px; }
        .quiz-card { background: var(--card-bg); padding: 20px; border-radius: 12px; border: 2px solid #333; cursor: pointer; transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); }
        .quiz-card:hover { transform: translateY(-5px) scale(1.02); border-color: var(--primary); box-shadow: 0 10px 20px rgba(0,0,0,0.4); }
        .quiz-card h3 { margin: 0 0 10px 0; color: #fff; }
        
        /* Question Card */
        .question-card { background: var(--card-bg); padding: 30px; border-radius: 12px; box-shadow: 0 8px 16px rgba(0,0,0,0.3); }
        .question-text { font-size: 1.3em; font-weight: bold; margin-top: 0; color: #ffffff; }
        
        /* Options */
        .options label { display: block; padding: 15px; margin: 12px 0; background: #2d2d2d; border-radius: 8px; cursor: pointer; border: 2px solid transparent; transition: all 0.2s; position: relative; overflow: hidden; }
        .options label:hover:not(.disabled) { background: #3d3d3d; transform: translateX(5px); }
        .options label.disabled { cursor: not-allowed; opacity: 0.7; }
        .options label.correct { background: #1b4332; border-color: var(--success); color: #fff; animation: pulseSuccess 1s ease-out; transform: scale(1.02); }
        .options label.incorrect { background: #4a1919; border-color: var(--danger); color: #fff; animation: shake 0.4s; }
        input[type="radio"] { display: none; } /* Hide default radio */
        
        /* Buttons & Inputs */
        .action-btn { display: block; width: 100%; padding: 15px; background: var(--primary); color: #000; font-size: 1.2em; font-weight: bold; border: none; border-radius: 8px; cursor: pointer; margin-top: 20px; transition: all 0.2s; text-transform: uppercase; letter-spacing: 1px; }
        .action-btn:hover:not(:disabled) { background: #9965f4; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(187, 134, 252, 0.4); }
        .action-btn:active:not(:disabled) { transform: translateY(1px); }
        .action-btn:disabled { background: #555; color: #888; cursor: not-allowed; transform: none; box-shadow: none; }
        .secondary-btn { background: #333; color: #fff; border: 1px solid #555; }
        .secondary-btn:hover { background: #444; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.4); }
        
        input[type="text"] { width: 100%; box-sizing: border-box; padding: 15px; font-size: 1.2em; background: #2d2d2d; border: 2px solid #333; color: #fff; border-radius: 8px; margin-bottom: 20px; transition: border-color 0.3s; }
        input[type="text"]:focus { outline: none; border-color: var(--primary); }
        
        /* Layout Containers */
        .container-box { text-align: center; padding: 30px; background: var(--card-bg); border-radius: 12px; }
        #quiz-container, #results-container, #leaderboard-container, #player-setup { display: none; }
        
        /* Tables */
        .leaderboard-table { width: 100%; border-collapse: collapse; margin-top: 20px; text-align: left; }
        .leaderboard-table th, .leaderboard-table td { padding: 12px; border-bottom: 1px solid #333; }
        .rank-1 { color: #ffeb3b; font-weight: bold; font-size: 1.2em; text-shadow: 0 0 10px rgba(255,235,59,0.3); }
        .rank-2 { color: #e0e0e0; font-weight: bold; }
        .rank-3 { color: #ff9800; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header-bar">
        <h1 style="cursor:pointer;" onclick="location.reload()">Trivia Night</h1>
        <div class="timer" id="timer-display">00:00</div>
    </div>
    
    <!-- Step 1: Select Quiz -->
    <div id="start-container" class="container-box animated-slide">
        <h2>Select a Quiz</h2>
        <div id="quiz-list" class="quiz-grid">Loading quizzes...</div>
        <button class="action-btn secondary-btn" onclick="showLeaderboardMenu()" style="margin-top: 30px;">🏆 View Leaderboards</button>
    </div>

    <!-- Step 2: Enter Name -->
    <div id="player-setup" class="container-box">
        <h2 id="selected-quiz-title">Quiz</h2>
        <p>Questions are timed. Faster answers yield higher scores.</p>
        <input type="text" id="username" placeholder="Enter your player name..." maxlength="20" required>
        <button class="action-btn" onclick="startQuiz()">Begin Quiz</button>
        <button class="action-btn secondary-btn" onclick="location.reload()">Back</button>
    </div>

    <!-- Step 3: Active Quiz -->
    <div id="quiz-container">
        <div style="text-align: center; color: #888; margin-bottom: 15px;" id="progress-display"></div>
        <div class="question-card" id="question-wrapper"></div>
        <div id="feedback" style="text-align: center; font-size: 1.3em; font-weight: bold; margin-top: 20px; min-height: 30px;"></div>
        <button id="main-btn" class="action-btn" onclick="handleButtonClick()" disabled>Submit Answer</button>
    </div>

    <!-- Step 4: Results -->
    <div id="results-container" class="container-box">
        <h2>Quiz Complete!</h2>
        <div id="score-display" style="font-size: 4em; font-weight: bold; color: #03dac6; margin: 10px 0;"></div>
        <p id="stats-display" style="color: #bbb; font-size: 1.2em;"></p>
        <button class="action-btn" onclick="viewCurrentLeaderboard()">View Leaderboard</button>
    </div>

    <!-- Step 5: Leaderboard -->
    <div id="leaderboard-container" class="container-box">
        <h2 id="lb-title">🏆 Top Scores</h2>
        <div id="lb-select-wrapper" style="display:none; margin-bottom: 20px;">
            <select id="lb-quiz-select" onchange="fetchLeaderboard(this.value)" style="padding: 10px; border-radius: 5px; background: #333; color: white; border: none; font-size: 1.1em;"></select>
        </div>
        <table class="leaderboard-table">
            <thead><tr><th>Rank</th><th>Player</th><th>Score</th><th>Correct</th><th>Time</th></tr></thead>
            <tbody id="leaderboard-body"></tbody>
        </table>
        <button onclick="location.reload()" class="action-btn secondary-btn" style="margin-top: 30px;">Main Menu</button>
    </div>

    <script>
        let currentQuizData = null;
        let selectedQuizFile = null;
        let playerName = "";
        let currentIndex = 0;
        let correctCount = 0;
        let state = 'answering'; 
        let startTime, timerInterval, secondsElapsed = 0;
        let allQuizzes = [];

        // Initialization: Fetch available quizzes
        fetch('index.php?api=list_quizzes')
            .then(res => res.json())
            .then(data => {
                allQuizzes = data;
                const grid = document.getElementById('quiz-list');
                const lbSelect = document.getElementById('lb-quiz-select');
                grid.innerHTML = '';
                
                if (data.length === 0) {
                    grid.innerHTML = '<p>No quizzes found. Please add JSON files to the quizzes/ folder.</p>';
                    return;
                }

                data.forEach(q => {
                    // Populate Quiz Cards
                    const card = document.createElement('div');
                    card.className = 'quiz-card';
                    card.innerHTML = `<h3>${q.title}</h3><p style="color:#aaa; font-size:0.9em; margin:0;">${q.description}</p>`;
                    card.onclick = () => setupQuiz(q);
                    grid.appendChild(card);
                    
                    // Populate Leaderboard Dropdown
                    const opt = document.createElement('option');
                    opt.value = q.id; opt.textContent = q.title;
                    lbSelect.appendChild(opt);
                });
            });

        function formatTime(totalSeconds) {
            const m = Math.floor(totalSeconds / 60).toString().padStart(2, '0');
            const s = (totalSeconds % 60).toString().padStart(2, '0');
            return `${m}:${s}`;
        }

        function updateTimer() {
            secondsElapsed = Math.floor((Date.now() - startTime) / 1000);
            const display = document.getElementById('timer-display');
            display.textContent = formatTime(secondsElapsed);
            
            // Pulse timer if taking too long (e.g., > 30s per question average)
            if (secondsElapsed > (currentIndex + 1) * 30) {
                display.classList.add('warning');
            } else {
                display.classList.remove('warning');
            }
        }

        function setupQuiz(quizMeta) {
            selectedQuizFile = quizMeta.file;
            document.getElementById('start-container').style.display = 'none';
            document.getElementById('selected-quiz-title').textContent = quizMeta.title;
            const setup = document.getElementById('player-setup');
            setup.style.display = 'block';
            setup.classList.add('animated-slide');
            setTimeout(() => document.getElementById('username').focus(), 100);
        }

        async function startQuiz() {
            const input = document.getElementById('username');
            if (input.value.trim() === '') {
                input.style.borderColor = 'var(--danger)';
                input.classList.add('warning');
                setTimeout(() => input.classList.remove('warning'), 500);
                return;
            }
            playerName = input.value.trim();
            
            // Fetch the actual quiz questions
            const res = await fetch(`index.php?api=get_quiz&file=${selectedQuizFile}`);
            currentQuizData = await res.json();
            
            document.getElementById('player-setup').style.display = 'none';
            document.getElementById('quiz-container').style.display = 'block';
            
            startTime = Date.now();
            timerInterval = setInterval(updateTimer, 100); 
            renderQuestion();
        }

        function renderQuestion() {
            state = 'answering';
            const q = currentQuizData.questions[currentIndex];
            const wrapper = document.getElementById('question-wrapper');
            const feedback = document.getElementById('feedback');
            const mainBtn = document.getElementById('main-btn');
            
            feedback.textContent = '';
            mainBtn.textContent = 'Submit Answer';
            mainBtn.disabled = true;
            document.getElementById('progress-display').textContent = `Question ${currentIndex + 1} of ${currentQuizData.questions.length}`;

            // Reset animation by triggering reflow
            wrapper.classList.remove('animated-fade');
            void wrapper.offsetWidth; 
            wrapper.classList.add('animated-fade');

            let optionsHtml = '';
            for (const [key, val] of Object.entries(q.options)) {
                optionsHtml += `
                    <label id="label-${key}" onclick="selectOption('${key}')">
                        <input type="radio" name="current_q" value="${key}">
                        <strong style="color:var(--primary); margin-right:5px;">${key})</strong> ${val}
                    </label>
                `;
            }

            wrapper.innerHTML = `
                <p class="question-text">${q.q}</p>
                <div class="options">${optionsHtml}</div>
            `;
        }

        function selectOption(key) {
            if (state !== 'answering') return;
            document.querySelectorAll('.options label').forEach(l => l.style.borderColor = 'transparent');
            
            const selectedLabel = document.getElementById(`label-${key}`);
            selectedLabel.style.borderColor = 'var(--primary)';
            document.querySelector(`input[value="${key}"]`).checked = true;
            
            const btn = document.getElementById('main-btn');
            btn.disabled = false;
        }

        function handleButtonClick() {
            if (state === 'answering') checkAnswer();
            else if (state === 'reviewing') nextQuestion();
        }

        function checkAnswer() {
            const selectedRadio = document.querySelector('input[name="current_q"]:checked');
            if (!selectedRadio) return;

            const selectedAnswer = selectedRadio.value;
            const correctAnswer = currentQuizData.questions[currentIndex].answer;
            const feedback = document.getElementById('feedback');

            for (const key of Object.keys(currentQuizData.questions[currentIndex].options)) {
                const label = document.getElementById(`label-${key}`);
                label.classList.add('disabled');
                label.style.borderColor = 'transparent'; // clear selection border
                
                if (key === correctAnswer) {
                    label.classList.add('correct');
                } else if (key === selectedAnswer) {
                    label.classList.add('incorrect');
                }
            }

            if (selectedAnswer === correctAnswer) {
                correctCount++;
