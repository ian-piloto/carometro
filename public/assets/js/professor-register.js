/**
 * Professor Registration Module - Captures student's face via the professor's webcam for registration.
 */

let regStream = null, regInterval = null, isRegScanning = false;

async function openRegistrationModal() {
    document.getElementById('modal-register').classList.add('active');
    ['reg-name', 'reg-matricula', 'reg-descriptor'].forEach(id => document.getElementById(id).value = '');
    const btn = document.getElementById('btn-save-reg'); btn.disabled = true; btn.innerText = "Aguardando Rosto...";
    await startRegCamera();
}

function closeRegistrationModal() { document.getElementById('modal-register').classList.remove('active'); stopRegCamera(); }

async function startRegCamera() {
    const video = document.getElementById('video-register'), status = document.getElementById('reg-camera-status');
    try {
        regStream = await navigator.mediaDevices.getUserMedia({ video: { width: 400, height: 300 } });
        video.srcObject = regStream;

        // Aguarda carregar as IAs se ainda não estiverem na memória
        if (!window.modelsLoaded) {
            status.innerText = "Carregando Face-API...";
            const MODEL_URL = 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights';
            await Promise.all([faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL), faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL), faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)]);
            window.modelsLoaded = true;
        }
        status.innerText = "Analizando rosto..."; isRegScanning = true;

        if (video.readyState >= 2) startRegScanLoop(); else video.onloadedmetadata = () => startRegScanLoop();

    } catch (e) { status.innerText = "Câmera indisponível."; }
}

function stopRegCamera() {
    isRegScanning = false; clearInterval(regInterval);
    if (regStream) { regStream.getTracks().forEach(t => t.stop()); regStream = null; }
    document.getElementById('video-register').srcObject = null;
    const canvas = document.getElementById('overlay-register');
    if (canvas) canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
}

function startRegScanLoop() {
    const video = document.getElementById('video-register'), canvas = document.getElementById('overlay-register'), status = document.getElementById('reg-camera-status'), btn = document.getElementById('btn-save-reg'), input = document.getElementById('reg-descriptor');

    regInterval = setInterval(async () => {
        if (!isRegScanning || !video || video.paused) return;

        const size = { width: video.offsetWidth || 400, height: video.offsetHeight || 300 };
        faceapi.matchDimensions(canvas, size);

        try {
            const det = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ scoreThreshold: 0.4 })).withFaceLandmarks().withFaceDescriptor();
            const ctx = canvas.getContext('2d'); ctx.clearRect(0, 0, canvas.width, canvas.height);

            if (det) {
                // Desenha caixa de Sucesso (Verde)
                new faceapi.draw.DrawBox(faceapi.resizeResults(det, size).detection.box, { label: 'Capturado ✓', lineWidth: 3, boxColor: '#28a745' }).draw(canvas);

                status.innerText = "Biometria Capturada ✓";
                status.style.backgroundColor = "#28a745";

                // Converte Float32Array para Array e dps JSON para salvar no BD
                input.value = JSON.stringify(Array.from(det.descriptor));

                btn.disabled = false;
                btn.innerText = "Salvar Cadastro";
            } else {
                status.innerText = "Rosto não detectado.";
                status.style.backgroundColor = "rgba(0,0,0,0.6)";
            }
        } catch (e) { }
    }, 1000); // 1 FPS para não pesar o input
}

async function saveRegistration() {
    const name = document.getElementById('reg-name').value, matricula = document.getElementById('reg-matricula').value, desc = document.getElementById('reg-descriptor').value;
    if (!name || !matricula || !desc) return showToast('Dados incompletos.', 'error');
    const data = await apiFetch('register_student', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name, registration: matricula, face_descriptor: desc }) });
    if (data.success) { showToast(`Salvo!`); loadStudents(); closeRegistrationModal(); } else showToast(data.message, 'error');
}
