/**
 * Lógica Central do Front-end — Sistema de Presença Facial
 * Gerencia: navegação, relógio, chamada de aula, alunos e integração com API.
 */

let activeSessionId = null;    // ID da sessão de aula ativa
let attendanceInterval = null;  // Polling da chamada
let classStartTime = null;    // Timestamp de início de aula
let timerInterval = null;    // Timer da aula

document.addEventListener('DOMContentLoaded', () => {
    updateClock();
    setInterval(updateClock, 1000);

    loadStudents();
    checkActiveSession(); // Verifica se já existe aula em andamento ao carregar

    const activeSection = document.querySelector('.nav-links li.active');
    if (activeSection) {
        const onclick = activeSection.getAttribute('onclick');
        const match = onclick ? onclick.match(/'([^']+)'/) : null;
        if (match) showSection(match[1]);
    }
});

// ── Relógio ──────────────────────────────────────────────────────
function updateClock() {
    const el = document.getElementById('clock');
    if (el) el.innerText = new Date().toLocaleTimeString('pt-BR');
}

// ── Navegação SPA ────────────────────────────────────────────────
function showSection(sectionId) {
    document.querySelectorAll('section').forEach(s => s.classList.add('hidden'));
    document.querySelectorAll('.nav-links li').forEach(l => l.classList.remove('active'));

    document.getElementById(`sec-${sectionId}`)?.classList.remove('hidden');

    const activeLi = Array.from(document.querySelectorAll('.nav-links li')).find(li =>
        li.getAttribute('onclick')?.includes(`'${sectionId}'`)
    );
    if (activeLi) activeLi.classList.add('active');

    const titles = { vision: 'Scanner de Acesso', chamada: 'Chamada da Aula', students: 'Gestão de Alunos' };
    document.getElementById('page-title').innerText = titles[sectionId] || '';

    if (sectionId === 'vision') {
        window.startVideo?.();
    } else {
        window.stopVideo?.();
    }

    if (sectionId === 'chamada' && activeSessionId) {
        loadAttendance(activeSessionId);
    }
}

// ── Iniciar / Encerrar Aula ──────────────────────────────────────

async function checkActiveSession() {
    try {
        const res = await fetch('api.php?action=get_active_session');
        const data = await res.json();
        if (data.active && data.session) {
            setActiveSession(data.session.id, new Date(data.session.data_aula + ' ' + data.session.hora_inicio));
        }
    } catch (e) { /* ignora */ }
}

async function toggleClass() {
    if (activeSessionId) {
        await endClass();
    } else {
        await startClass();
    }
}

async function startClass() {
    try {
        const res = await fetch('api.php?action=start_class', { method: 'POST' });
        const data = await res.json();

        if (data.success) {
            showToast(`✅ ${data.message}`, 'success');
            setActiveSession(data.session_id, new Date());
            showSection('chamada');
            loadAttendance(data.session_id);
        } else {
            showToast(data.message, 'error');
            // Se já existe sessão ativa, recupera o ID
            if (data.session_id) {
                setActiveSession(data.session_id, new Date());
            }
        }
    } catch (e) {
        showToast('Erro ao iniciar aula.', 'error');
    }
}

async function endClass() {
    if (!activeSessionId) return;
    if (!confirm('Deseja encerrar a aula? A chamada será finalizada.')) return;

    try {
        const res = await fetch('api.php?action=end_class', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: activeSessionId })
        });
        const data = await res.json();

        if (data.success) {
            showToast('Aula encerrada!', 'success');
            clearActiveSession();
            // Exibe chamada final (somente leitura)
            loadAttendance(activeSessionId);
        } else {
            showToast(data.message, 'error');
        }
    } catch (e) {
        showToast('Erro ao encerrar aula.', 'error');
    }
}

function setActiveSession(sessionId, startTime) {
    activeSessionId = sessionId;
    window.activeSessionId = sessionId; // Disponibiliza para facialRecognition.js
    classStartTime = startTime;

    // Atualiza botão da sidebar
    const btn = document.getElementById('btn-start-class');
    if (btn) {
        btn.classList.add('active');
        btn.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>
            </svg>
            Encerrar Aula`;
    }

    // Mostra card de status da aula no painel
    const card = document.getElementById('active-class-card');
    if (card) card.style.display = 'block';

    // Inicia timer
    if (timerInterval) clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        const diff = Math.floor((Date.now() - classStartTime.getTime()) / 1000);
        const m = String(Math.floor(diff / 60)).padStart(2, '0');
        const s = String(diff % 60).padStart(2, '0');
        const el = document.getElementById('class-timer');
        if (el) el.textContent = `${m}:${s}`;
    }, 1000);

    // Polling automático da chamada a cada 4s
    if (attendanceInterval) clearInterval(attendanceInterval);
    attendanceInterval = setInterval(() => {
        if (activeSessionId) loadAttendance(activeSessionId);
    }, 4000);
}

function clearActiveSession() {
    activeSessionId = null;
    window.activeSessionId = null;
    classStartTime = null;

    if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
    if (attendanceInterval) { clearInterval(attendanceInterval); attendanceInterval = null; }

    const btn = document.getElementById('btn-start-class');
    if (btn) {
        btn.classList.remove('active');
        btn.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/>
            </svg>
            Iniciar Aula`;
    }

    const card = document.getElementById('active-class-card');
    if (card) card.style.display = 'none';
}

// ── Chamada de Presença ──────────────────────────────────────────

async function loadAttendance(sessionId) {
    try {
        const res = await fetch(`api.php?action=get_attendance&session_id=${sessionId}`);
        const data = await res.json();
        if (!data.success) return;

        renderAttendanceTable(data.attendance);
    } catch (e) { /* ignora */ }
}

function renderAttendanceTable(records) {
    const wrap = document.getElementById('chamada-table-wrap');
    const empty = document.getElementById('chamada-empty');
    const tbody = document.getElementById('chamada-tbody');
    const stats = document.getElementById('class-stats');
    const badge = document.getElementById('nav-chamada-badge');

    if (!records || records.length === 0) {
        if (wrap) wrap.classList.add('hidden');
        if (empty) empty.style.display = 'block';
        return;
    }

    if (empty) empty.style.display = 'none';
    if (wrap) wrap.classList.remove('hidden');

    const presentes = records.filter(r => r.status === 'presente').length;
    const total = records.length;

    // Atualiza stats e badge
    if (stats) stats.textContent = `${presentes} / ${total} presentes`;
    if (badge) {
        badge.textContent = `${presentes}/${total}`;
        badge.classList.remove('hidden');
    }

    // Título da chamada
    const subtitle = document.getElementById('chamada-subtitle');
    const isActive = !!activeSessionId;
    if (subtitle) {
        subtitle.textContent = isActive
            ? `Aula ativa · ${presentes} presentes de ${total} alunos`
            : `Chamada encerrada · ${presentes} presentes de ${total} alunos`;
    }

    if (!tbody) return;
    tbody.innerHTML = '';

    records.forEach(r => {
        const tr = document.createElement('tr');
        const isPresente = r.status === 'presente';

        tr.innerHTML = `
            <td style="font-weight:600;">${r.nome}</td>
            <td style="color:#888;font-size:0.85rem;">${r.matricula}</td>
            <td style="text-align:center;">
                <span class="attendance-badge ${isPresente ? 'presente' : 'falta'}">
                    ${isPresente ? '✓ Presente' : '✗ Falta'}
                </span>
            </td>
            <td style="text-align:center;color:#888;font-size:0.85rem;">
                ${r.horario_entrada || '—'}
            </td>
        `;
        tbody.appendChild(tr);
    });
}

// ── Logout ───────────────────────────────────────────────────────
async function doLogout() {
    await fetch('api.php?action=logout');
    window.location.href = 'login.php';
}

// ── Toast ────────────────────────────────────────────────────────
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.innerText = message;
    toast.style.borderLeft = `5px solid ${type === 'error' ? '#dc3545' : '#28a745'}`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}

// ── Cadastro de Alunos ───────────────────────────────────────────

function openRegistration(descriptor = null) {
    const desc = descriptor || window.lastFaceDescriptor;
    if (!desc) {
        showToast('Aguarde a câmera detectar um rosto.', 'error');
        return;
    }

    document.getElementById('reg-descriptor').value = JSON.stringify(Array.from(desc));
    document.getElementById('face-capture-status').innerText = '[Biometria Capturada ✓]';
    document.getElementById('face-capture-status').style.color = 'var(--success)';
    document.getElementById('modal-register').classList.add('active');
}

function closeRegistration() {
    document.getElementById('modal-register').classList.remove('active');
    window.registrationCooldown = true;
    setTimeout(() => { window.registrationCooldown = false; }, 3000);
}

async function saveRegistration() {
    const name = document.getElementById('reg-name').value;
    const matricula = document.getElementById('reg-matricula').value;
    const descriptor = document.getElementById('reg-descriptor').value;

    if (!name || !matricula || !descriptor) {
        showToast('Preencha todos os campos.', 'error');
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
            window.knownStudentsCache = null;
            loadStudents();
            closeRegistration();
            document.getElementById('registration-form').reset();
        } else {
            showToast(data.message, 'error');
        }
    } catch (e) {
        showToast('Erro de conexão.', 'error');
    }
}

async function loadStudents() {
    try {
        const res = await fetch('api.php?action=list_students');
        const students = await res.json();

        if (Array.isArray(students)) {
            window.knownStudentsCache = students;

            const tbody = document.getElementById('student-table-body');
            if (!tbody) return;
            tbody.innerHTML = '';

            students.forEach(s => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="text-align:center;color:#888;">${s.id}</td>
                    <td>${s.nome}</td>
                    <td style="text-align:center;">${s.matricula}</td>
                    <td>
                        <button class="btn-action btn-delete" onclick="deleteStudent(${s.id}, '${s.nome}')">
                            Excluir
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
    } catch (e) { /* ignora */ }
}

async function deleteStudent(id, name) {
    if (!confirm(`Excluir permanentemente o cadastro de ${name}?`)) return;

    try {
        const res = await fetch(`api.php?action=delete_student&id=${id}`);
        const data = await res.json();

        if (data.success) {
            showToast('Cadastro removido.', 'success');
            window.knownStudentsCache = null;
            loadStudents();
        } else {
            showToast(data.message, 'error');
        }
    } catch (e) {
        showToast('Falha ao excluir.', 'error');
    }
}

// ── Câmera ───────────────────────────────────────────────────────
function retryCamera() {
    window.stopVideo?.();
    setTimeout(() => window.startVideo?.(), 500);
}

function toggleCamera() {
    window.apiToggleCamera?.();
}
