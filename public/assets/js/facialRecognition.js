/**
 * Módulo de Reconhecimento Facial (Vision Deep Core)
 * Utiliza face-api.js para detecção, landmarks e extração de biometria.
 */

console.log('SISTEMA_LIBRARY_VISION: Módulo de reconhecimento facial pronto.');

// Controle de estado global do hardware e software de biometria
window.localStream = null;          // Objeto do stream da câmera
window.modelsLoaded = false;        // Status do carregamento dos redes neurais
window.lastFaceDescriptor = null;   // Última biometria facial capturada (128 floats)
window.recognitionInterval = null;  // Identificador do loop de monitoramento

/**
 * Helper para acessar os elementos de câmera do DOM de forma consistente.
 */
function getCamElements() {
    return {
        video: document.getElementById('video'),
        canvas: document.getElementById('overlay'),
        status: document.getElementById('vision-status'),
        placeholder: document.getElementById('camera-placeholder')
    };
}

/**
 * Solicita acesso à webcam e inicia a exibição do vídeo.
 */
window.startVideo = async function () {
    const el = getCamElements();

    // Impede múltiplas inicializações se a câmera já estiver rodada
    if (window.localStream) return;

    try {
        if (!navigator.mediaDevices?.getUserMedia) {
            throw new Error('Câmera não suportada neste navegador.');
        }

        // Configura a resolução ideal para performance equilibrada
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { width: 640, height: 480, facingMode: "user" }
        });

        if (el.video) {
            el.video.srcObject = stream;
            window.localStream = stream;

            el.video.onloadedmetadata = () => {
                // Esconde a mensagem de "Iniciando..."
                if (el.placeholder) el.placeholder.classList.add('hidden');
                updateCameraButton(true);

                // Se as IAs já estiverem carregadas, inicia o monitoramento imediato
                if (window.modelsLoaded) {
                    setVisionStatus("Sistema Online", "var(--success)");
                    if (!window.recognitionInterval) startRecognitionLoop();
                } else {
                    setVisionStatus("Carregando IA...", "#ffc107");
                    loadModels();
                }
            };
        }
    } catch (err) {
        console.error('Camera Error:', err);
        showToast('Falha crítica ao acessar a câmera.', 'error');
    }
}

/**
 * Desliga a webcam e para todos os processos de IA associados.
 */
window.stopVideo = function () {
    const el = getCamElements();

    // Para o loop de reconhecimento
    if (window.recognitionInterval) {
        clearInterval(window.recognitionInterval);
        window.recognitionInterval = null;
    }

    // Para o elemento de vídeo e limpa o stream
    if (el.video) {
        el.video.pause();
        el.video.srcObject = null;
    }

    if (window.localStream) {
        window.localStream.getTracks().forEach(track => track.stop());
        window.localStream = null;
    }

    updateCameraButton(false);

    // Limpa os desenhos faciais do canvas
    if (el.canvas) {
        el.canvas.getContext('2d').clearRect(0, 0, el.canvas.width, el.canvas.height);
    }

    setVisionStatus("Câmera Desligada", "#666");
    if (el.placeholder) el.placeholder.classList.remove('hidden');
}

/**
 * Helper para atualizar visualmente o status da visão.
 */
function setVisionStatus(text, color) {
    const status = document.getElementById('vision-status');
    if (status) {
        status.innerText = text;
        status.style.backgroundColor = color;
    }
}

/**
 * Atualiza o texto e estilo do botão de alternância da câmera.
 */
function updateCameraButton(active) {
    const btn = document.getElementById('btn-toggle-camera');
    if (btn) {
        btn.innerText = active ? 'Desligar Câmera' : 'Ligar Câmera';
        btn.style.backgroundColor = active ? '#333' : 'var(--primary)';
    }
}

/**
 * Alterna entre ligar e desligar a câmera.
 */
window.apiToggleCamera = async () => {
    if (window.localStream) {
        window.stopVideo();
    } else {
        await window.startVideo();
    }
};

/**
 * Carrega as redes neurais da face-api.js via CDN.
 */
async function loadModels() {
    // URL dos modelos via CDN (pode ser alterado para local se preferir baixar os arquivos)
    const MODEL_URL = 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights';

    if (typeof faceapi === 'undefined') {
        console.error('SISTEMA_LIBRARY_VISION: Dependência face-api.js não encontrada!');
        setVisionStatus("Erro: Lib não carregada", "#dc3545");
        return;
    }

    try {
        console.log('SISTEMA_LIBRARY_VISION: Carregando modelos neurais...');

        // Carrega 3 modelos essenciais: Detector, Pontos Faciais e Reconhecimento
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
        ]);

        window.modelsLoaded = true;
        console.log('SISTEMA_LIBRARY_VISION: Modelos carregados com sucesso.');
        setVisionStatus("Sistema Online", "var(--success)");
        startRecognitionLoop();

    } catch (err) {
        console.error('Model Load Error:', err);
        setVisionStatus("Erro ao Carregar IA", "#dc3545");
        showToast('Falha ao carregar modelos de IA da nuvem.', 'error');
    }
}

/**
 * Ciclo principal de processamento de imagem e reconhecimento.
 */
function startRecognitionLoop() {
    const el = getCamElements();

    // Loop infinito que executa a cada 1 segundo (balanceado para não travar o PC)
    window.recognitionInterval = setInterval(async () => {
        const currentEl = getCamElements();

        // Verifica se o vídeo está pronto para processar
        if (!currentEl.video || currentEl.video.paused || !window.localStream) return;

        const dynamicSize = {
            width: currentEl.video.offsetWidth || 640,
            height: currentEl.video.offsetHeight || 480
        };

        // Redimensiona o canvas para bater exatamente com o vídeo
        if (currentEl.canvas) {
            faceapi.matchDimensions(currentEl.canvas, dynamicSize);
        }

        try {
            // DETECÇÃO: Encontra rostos e extrai os traços únicos (descriptors)
            // TinyFaceDetector é leve para mobile/web, inputSize 160 melhora detecção à distância
            const detections = await faceapi.detectAllFaces(currentEl.video, new faceapi.TinyFaceDetectorOptions({ inputSize: 160, scoreThreshold: 0.3 }))
                .withFaceLandmarks()
                .withFaceDescriptors();

            const blurOverlay = document.getElementById('portrait-blur');

            // Desenha as caixas ao redor dos rostos detectados
            if (currentEl.canvas) {
                const resizedDetections = faceapi.resizeResults(detections, dynamicSize);
                currentEl.canvas.getContext('2d').clearRect(0, 0, currentEl.canvas.width, currentEl.canvas.height);
                faceapi.draw.drawDetections(currentEl.canvas, resizedDetections);
            }

            // Esconde efeito retrato se ninguém estiver na frente
            if (detections.length === 0 && blurOverlay) {
                blurOverlay.style.setProperty('--face-r', `0%`);
            }

            if (detections.length > 0) {
                // Seleciona o rosto que estiver MAIS PERTO DO CENTRO do enquadramento
                let mainFace = detections[0];
                if (detections.length > 1) {
                    const videoCenter = { x: dynamicSize.width / 2, y: dynamicSize.height / 2 };
                    let minDistanceToCenter = Infinity;

                    detections.forEach(det => {
                        const detCenter = {
                            x: det.detection.box.x + (det.detection.box.width / 2),
                            y: det.detection.box.y + (det.detection.box.height / 2)
                        };
                        const dist = Math.sqrt(Math.pow(detCenter.x - videoCenter.x, 2) + Math.pow(detCenter.y - videoCenter.y, 2));
                        if (dist < minDistanceToCenter) {
                            minDistanceToCenter = dist;
                            mainFace = det;
                        }
                    });
                }

                const box = mainFace.detection.box;
                const centerX = box.x + (box.width / 2);
                const centerY = box.y + (box.height / 2);

                // --- Regras de Validação para Reconhecimento Seguro ---

                // 1. TAMANHO: O rosto deve estar perto o suficiente da câmera
                if (box.width < (dynamicSize.width * 0.15)) {
                    setVisionStatus("Chegue mais perto", "#ffc107");
                    return;
                }

                // 2. ENQUADRAMENTO: O rosto deve estar no centro (área 20%-80%)
                if (centerX < dynamicSize.width * 0.2 || centerX > dynamicSize.width * 0.8 ||
                    centerY < dynamicSize.height * 0.2 || centerY > dynamicSize.height * 0.8) {
                    setVisionStatus("Centralize o Rosto", "#ffc107");
                    return;
                }

                // Atualiza o efeito de Portrait Blur acompanhando o rosto
                if (blurOverlay) {
                    const pctX = (centerX / dynamicSize.width) * 100;
                    const pctY = (centerY / dynamicSize.height) * 100;
                    const radius = Math.max(25, (box.width / dynamicSize.width) * 100);

                    blurOverlay.style.setProperty('--face-x', `${pctX}%`);
                    blurOverlay.style.setProperty('--face-y', `${pctY}%`);
                    blurOverlay.style.setProperty('--face-r', `${radius}%`);
                }

                const descriptor = mainFace.descriptor;
                window.lastFaceDescriptor = descriptor;

                // Previne processamento simultâneo pesado
                if (window.isProcessing) return;
                window.isProcessing = true;

                try {
                    // COMPARAÇÃO: Busca no cache de alunos se algum tem biometria similar
                    if (!window.knownStudentsCache) return;

                    let bestMatch = null;
                    let minDistance = 0.50; // Limiar de segurança (quanto menor, mais rigoroso)

                    window.knownStudentsCache.forEach(student => {
                        if (student.face_descriptor) {
                            const studentDesc = new Float32Array(JSON.parse(student.face_descriptor));
                            const distance = faceapi.euclideanDistance(descriptor, studentDesc);

                            // Menor distância = maior similaridade
                            if (distance < minDistance) {
                                minDistance = distance;
                                bestMatch = student;
                            }
                        }
                    });

                    // Resultado do Reconhecimento
                    if (bestMatch) {
                        // Suporte a schema antigo (name) e novo (nome)
                        const displayName = bestMatch.nome || bestMatch.name || 'Desconhecido';
                        setVisionStatus("Identificado", "var(--success)");
                        document.getElementById('vision-msg').innerHTML =
                            `<span style='color:var(--success)'>Bem-vindo: ${displayName}</span>`;

                        // Cooldown individual por pessoa (não bloqueia identificar outra pessoa)
                        const cooldownKey = `cooldown_${bestMatch.id}`;
                        if (!window[cooldownKey]) {
                            showAccessGranted({ ...bestMatch, name: displayName });
                            await sendAccessLog(bestMatch);
                        }
                    } else {
                        // ROSTO DESCONHECIDO
                        setVisionStatus("Rosto Desconhecido", "#dc3545");

                        // Buffer de Detecção: Evita abrir cadastro com sombras ou rostos rápidos
                        if (!window.detectionBuffer) window.detectionBuffer = 0;
                        window.detectionBuffer++;

                        // Se um rosto desconhecido ficar parado por 3 segundos, abre cadastro
                        if (window.detectionBuffer >= 3 && !window.registrationCooldown) {
                            openRegistration(descriptor);
                            window.detectionBuffer = 0;
                        }
                    }
                } finally {
                    window.isProcessing = false;
                }
            } else {
                // NENHUM ROSTO NO QUADRO
                window.detectionBuffer = 0;
                setVisionStatus("Sistema Online", "var(--success)");
            }
        } catch (err) {
            console.error('Vision Loop Error:', err);
        }
    }, 1000);
}

/**
 * Exibe o overlay fullscreen de "ACESSO LIBERADO" por 3 segundos.
 * @param {object} student Objeto com name e class_name do aluno identificado.
 */
function showAccessGranted(student) {
    const overlay = document.getElementById('access-overlay');
    const nameEl = document.getElementById('access-name-display');
    const classEl = document.getElementById('access-class-display');
    const barEl = document.getElementById('access-progress-bar');

    if (!overlay) return;

    const displayName = student.nome || student.name || 'Identificado';
    if (nameEl) nameEl.textContent = displayName;
    if (classEl) classEl.textContent = student.course_nome ? `Curso: ${student.course_nome}` : '';

    // Reinicia a animação da barra de progresso removendo e readicionando o elemento
    if (barEl) {
        barEl.style.animation = 'none';
        // Força reflow para reiniciar a animação CSS
        void barEl.offsetWidth;
        barEl.style.animation = '';
    }

    // Exibe o overlay
    overlay.classList.add('show');

    // Remove após 3 segundos
    setTimeout(() => {
        overlay.classList.remove('show');
    }, 3000);
}

/**
 * Registra acesso e, se houver aula ativa, marca presença na chamada.
 * Cooldown individual por aluno (não bloqueia outros alunos).
 */
async function sendAccessLog(student) {
    const cooldownKey = `cooldown_${student.id}`;
    window[cooldownKey] = true;
    const displayName = student.nome || student.name || 'Aluno';

    try {
        // Se há uma sessão de aula ativa: marca presença na chamada
        if (window.activeSessionId) {
            const res = await fetch('api.php?action=mark_present', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: window.activeSessionId, student_id: student.id })
            });
            const data = await res.json();
            if (data.updated) {
                showToast(`✓ Presença de ${displayName} registrada!`, 'success');
            }
        } else {
            showToast(`Bem-vindo, ${displayName}!`, 'success');
        }
    } catch (err) {
        console.error('Log Error:', err);
    } finally {
        // Cooldown de 15s apenas para a MESMA pessoa
        setTimeout(() => { window[cooldownKey] = false; }, 15000);
    }
}
