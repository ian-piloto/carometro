/**
 * Módulo de Reconhecimento Facial (Tablet Aluno)
 */

window.localStream = null;
window.modelsLoaded = false;
window.recognitionInterval = null;
window.knownStudentsCache = [];

async function initTablet() {
    loadStudents();
    await loadModels();
    await startVideo();

    // Atualiza a cache de alunos de tempos em tempos
    setInterval(loadStudents, 30000);
}

function getSessionId() {
    // Busca a aula ativa na API (pode ser pollings tbm)
    return fetch('api.php?action=get_active_session')
        .then(res => res.json())
        .then(data => {
            if (data.active && data.session) return data.session.id;
            return null;
        }).catch(() => null);
}

async function loadStudents() {
    try {
        const res = await fetch('api.php?action=list_students');
        const students = await res.json();
        if (Array.isArray(students)) {
            window.knownStudentsCache = students;
        }
    } catch (e) { }
}

async function loadModels() {
    const MODEL_URL = 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights';
    try {
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
        ]);
        window.modelsLoaded = true;
    } catch (err) {
        document.getElementById('vision-status').innerText = "Erro ao Carregar IA";
    }
}

async function startVideo() {
    const video = document.getElementById('video');
    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { width: 640, height: 480, facingMode: "user" }
        });
        video.srcObject = stream;
        window.localStream = stream;

        video.onloadedmetadata = () => {
            document.getElementById('vision-status').innerText = "Aguardando rosto...";
            startRecognitionLoop();
        };
    } catch (err) {
        document.getElementById('vision-status').innerText = "Sem sinal de Câmera";
    }
}

function startRecognitionLoop() {
    const video = document.getElementById('video');
    const canvas = document.getElementById('overlay');

    window.recognitionInterval = setInterval(async () => {
        if (!video || video.paused || !window.localStream) return;

        const dynamicSize = { width: video.offsetWidth || 640, height: video.offsetHeight || 480 };
        faceapi.matchDimensions(canvas, dynamicSize);

        try {
            const detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({ scoreThreshold: 0.3 }))
                .withFaceLandmarks()
                .withFaceDescriptors();

            const blurOverlay = document.getElementById('portrait-blur');

            if (detections.length > 0) {
                // Seleciona o primeiro rosto (o mais visível)
                let mainFace = detections[0];
                const box = mainFace.detection.box;
                const centerX = box.x + (box.width / 2);
                const centerY = box.y + (box.height / 2);

                if (blurOverlay) {
                    blurOverlay.style.setProperty('--face-x', `${(centerX / dynamicSize.width) * 100}%`);
                    blurOverlay.style.setProperty('--face-y', `${(centerY / dynamicSize.height) * 100}%`);
                    blurOverlay.style.setProperty('--face-r', `${Math.max(25, (box.width / dynamicSize.width) * 100)}%`);
                }

                if (window.isProcessing) return;
                window.isProcessing = true;

                try {
                    if (!window.knownStudentsCache || window.knownStudentsCache.length === 0) return;

                    let bestMatch = null;
                    let minDistance = 0.50; // Limiar

                    window.knownStudentsCache.forEach(student => {
                        if (student.face_descriptor) {
                            const studentDesc = new Float32Array(JSON.parse(student.face_descriptor));
                            const distance = faceapi.euclideanDistance(mainFace.descriptor, studentDesc);
                            if (distance < minDistance) {
                                minDistance = distance;
                                bestMatch = student;
                            }
                        }
                    });

                    if (bestMatch) {
                        const cooldownKey = `cooldown_${bestMatch.id}`;
                        if (!window[cooldownKey]) {
                            document.getElementById('vision-status').innerText = "Identificando...";
                            await logPresence(bestMatch, cooldownKey);
                        } else {
                            document.getElementById('vision-status').innerText = "Aguarde (Cooldown)";
                        }
                    } else {
                        document.getElementById('vision-status').innerText = "Rosto Desconhecido";
                    }
                } finally {
                    window.isProcessing = false;
                }
            } else {
                if (blurOverlay) blurOverlay.style.setProperty('--face-r', `0%`);
                document.getElementById('vision-status').innerText = "Aguardando rosto...";
            }

            // Desenhar detecção
            if (canvas) {
                const resizedDetections = faceapi.resizeResults(detections, dynamicSize);
                canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
                faceapi.draw.drawDetections(canvas, resizedDetections);
            }

        } catch (e) { }
    }, 1000);
}

async function logPresence(student, cooldownKey) {
    window[cooldownKey] = true;

    // Busca a ID da sessão de aula ativa na hora que a pessoa apareceu
    const sessionId = await getSessionId();

    if (sessionId) {
        try {
            await fetch('api.php?action=mark_present', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: sessionId, student_id: student.id })
            });
        } catch (e) { }
    }

    // Sempre exibe Feedback de sucesso na tela para o aluno
    const overlay = document.getElementById('access-overlay');
    const nameEl = document.getElementById('access-name-display');
    const displayName = student.nome || 'Aluno';
    nameEl.textContent = displayName;

    overlay.classList.add('show');

    setTimeout(() => {
        overlay.classList.remove('show');
        document.getElementById('vision-status').innerText = "Aguardando rosto...";
    }, 3000);

    // 15 seconds cooldown
    setTimeout(() => { window[cooldownKey] = false; }, 15000);
}

document.addEventListener('DOMContentLoaded', initTablet);
