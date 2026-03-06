/**
 * Professor Registration Module - Captures student's face via the professor's webcam for registration.
 */

let regStream = null;
let regInterval = null;
let isRegScanning = false;

async function openRegistrationModal() {
    document.getElementById('modal-register').classList.add('active');
    document.getElementById('registration-form').reset();
    document.getElementById('reg-descriptor').value = '';

    const btnSave = document.getElementById('btn-save-reg');
    btnSave.disabled = true;
    btnSave.innerText = "Aguardando Rosto...";

    await startRegCamera();
}

function closeRegistrationModal() {
    document.getElementById('modal-register').classList.remove('active');
    stopRegCamera();
}

async function startRegCamera() {
    const video = document.getElementById('video-register');
    const status = document.getElementById('reg-camera-status');

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { width: 400, height: 300 } });
        video.srcObject = stream;
        regStream = stream;

        // Aguarda carregar as IAs se ainda não estiverem na memória
        if (!window.modelsLoaded) {
            status.innerText = "Carregando Face-API...";
            const MODEL_URL = 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights';
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
            ]);
            window.modelsLoaded = true;
        }

        status.innerText = "Analizando rosto...";
        isRegScanning = true;

        video.onloadedmetadata = () => {
            startRegScanLoop();
        };

    } catch (e) {
        status.innerText = "Câmera bloqueada ou indisponível.";
        console.error(e);
    }
}

function stopRegCamera() {
    isRegScanning = false;
    if (regInterval) {
        clearInterval(regInterval);
        regInterval = null;
    }
    if (regStream) {
        regStream.getTracks().forEach(track => track.stop());
        regStream = null;
    }
    const video = document.getElementById('video-register');
    if (video) video.srcObject = null;

    const canvas = document.getElementById('overlay-register');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }
}

function startRegScanLoop() {
    const video = document.getElementById('video-register');
    const canvas = document.getElementById('overlay-register');
    const status = document.getElementById('reg-camera-status');
    const btnSave = document.getElementById('btn-save-reg');
    const descriptorInput = document.getElementById('reg-descriptor');

    regInterval = setInterval(async () => {
        if (!isRegScanning || !video || video.paused) return;

        const size = { width: video.offsetWidth || 400, height: video.offsetHeight || 300 };
        faceapi.matchDimensions(canvas, size);

        try {
            const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ scoreThreshold: 0.5 }))
                .withFaceLandmarks()
                .withFaceDescriptor();

            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            if (detection) {
                const resized = faceapi.resizeResults(detection, size);
                faceapi.draw.drawDetections(canvas, resized);

                status.innerText = "Biometria Capturada ✓";
                status.style.backgroundColor = "rgba(40, 167, 69, 0.8)";

                // Converte Float32Array para Array e dps JSON para salvar no BD
                descriptorInput.value = JSON.stringify(Array.from(detection.descriptor));

                btnSave.disabled = false;
                btnSave.innerText = "Salvar Cadastro";
            } else {
                status.innerText = "Rosto não detectado.";
                status.style.backgroundColor = "rgba(0, 0, 0, 0.6)";
            }
        } catch (e) { }
    }, 1000); // 1 FPS para não pesar o input
}

async function saveRegistration() {
    const name = document.getElementById('reg-name').value;
    const matricula = document.getElementById('reg-matricula').value;
    const descriptor = document.getElementById('reg-descriptor').value;

    if (!name || !matricula || !descriptor) {
        showToast('Preencha nome, matrícula e aguarde a detecção do rosto.', 'error');
        return;
    }

    const payload = { name, registration: matricula, face_descriptor: descriptor };

    try {
        const res = await fetch('api.php?action=register_student', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast(`Aluno ${name} cadastrado!`, 'success');
            loadStudents();
            closeRegistrationModal();
        } else {
            showToast(data.message, 'error');
        }
    } catch (e) {
        showToast('Erro de conexão.', 'error');
    }
}
