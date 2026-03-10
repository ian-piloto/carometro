/**
 * Lógica Central do Front-end — Professor Area
 */

let activeSessionId = null, classStartTime = null, attendanceInterval = null, timerInterval = null;

document.addEventListener('DOMContentLoaded', () => {
    setInterval(() => {
        const el = document.getElementById('clock');
        if (el) el.innerText = new Date().toLocaleTimeString('pt-BR');
    }, 1000);

    loadStudents();
    checkActiveSession();

    const activeSection = document.querySelector('.nav-links li.active');
    if (activeSection) {
        const onclick = activeSection.getAttribute('onclick');
        const match = onclick ? onclick.match(/'([^']+)'/) : null;
        if (match) showSection(match[1]);
    }
});

// ── Relógio ──────────────────────────────────────────────────────
// The updateClock function is now inlined in DOMContentLoaded

// ── Navegação SPA ────────────────────────────────────────────────
async function apiFetch(action, options = {}) {
    try {
        const res = await fetch(`api.php?action=${action}`, options);
        return await res.json();
    } catch (e) { return { success: false, message: 'Erro na API' }; }
}

function showSection(id) {
    document.querySelectorAll('section').forEach(s => s.classList.toggle('hidden', s.id !== `sec-${id}`));
    document.querySelectorAll('.nav-links li').forEach(l => l.classList.toggle('active', l.getAttribute('onclick')?.includes(`'${id}'`)));
    const titles = { chamada: 'Chamada', students: 'Alunos', history: 'Histórico' };
    document.getElementById('page-title').innerText = titles[id] || '';
    if (id === 'chamada' && activeSessionId) loadAttendance();
    if (id === 'history') loadHistory();
}

// ── Iniciar / Encerrar Aula ──────────────────────────────────────

async function checkActiveSession() {
    const data = await apiFetch('get_active_session');
    if (data.active) setActiveSession(data.session.id, new Date(data.session.data_aula + ' ' + data.session.hora_inicio));
}

async function toggleClass() { activeSessionId ? await endClass() : await startClass(); }

async function startClass() {
    const nome = prompt("Nome da aula:");
    if (!nome) return showToast("Obrigatório!", 'error');
    const data = await apiFetch('start_class', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ nome_aula: nome }) });
    if (data.success) {
        showToast(data.message);
        setActiveSession(data.session_id, new Date());
        showSection('chamada');
    } else showToast(data.message, 'error');
}

async function endClass() {
    if (!confirm('Encerrar aula?')) return;
    const data = await apiFetch('end_class', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ session_id: activeSessionId }) });
    if (data.success) { showToast('Encerrada!'); clearActiveSession(); loadAttendance(); }
}

function setActiveSession(id, start) {
    activeSessionId = id; classStartTime = start;
    const btn = document.getElementById('btn-start-class');
    if (btn) {
        btn.classList.add('active');
        btn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg> Encerrar`;
    }
    const card = document.getElementById('active-class-card');
    if (card) card.style.display = 'block';

    clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        const diff = Math.floor((Date.now() - classStartTime.getTime()) / 1000);
        const el = document.getElementById('class-timer');
        if (el) el.textContent = `${String(Math.floor(diff / 60)).padStart(2, '0')}:${String(diff % 60).padStart(2, '0')}`;
    }, 1000);

    clearInterval(attendanceInterval);
    attendanceInterval = setInterval(() => activeSessionId && loadAttendance(), 2000);
}

function clearActiveSession() {
    activeSessionId = null; classStartTime = null;
    clearInterval(timerInterval); clearInterval(attendanceInterval);
    const btn = document.getElementById('btn-start-class');
    if (btn) {
        btn.classList.remove('active');
        btn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg> Iniciar`;
    }
    const card = document.getElementById('active-class-card');
    if (card) card.style.display = 'none';
}

// ── Chamada de Presença ──────────────────────────────────────────

async function loadAttendance() {
    const data = await apiFetch(`get_attendance&session_id=${activeSessionId}`);
    if (!data.success) return;

    const wrap = document.getElementById('chamada-table-wrap');
    const empty = document.getElementById('chamada-empty');
    const tbody = document.getElementById('chamada-tbody');
    const badge = document.getElementById('nav-chamada-badge');

    if (!data.attendance || data.attendance.length === 0) {
        if (wrap) wrap.classList.add('hidden');
        if (empty) empty.style.display = 'block';
        if (badge) badge.classList.add('hidden');
        if (tbody) tbody.innerHTML = ''; // Clear table if no records
        return;
    }

    if (empty) empty.style.display = 'none';
    if (wrap) wrap.classList.remove('hidden');

    const presentes = data.attendance.filter(r => r.status === 'presente').length;
    const total = data.attendance.length;

    if (badge) {
        badge.textContent = `${presentes}/${total}`;
        badge.classList.remove('hidden');
    }

    const subtitle = document.getElementById('chamada-subtitle');
    if (subtitle) {
        subtitle.textContent = (activeSessionId ? 'Ativa: ' : 'Fim: ') + `${presentes}/${total} presentes`;
    }

    if (tbody) tbody.innerHTML = data.attendance.map(r => `<tr><td style="font-weight:600;">${r.nome}</td><td style="color:#888;">${r.matricula}</td><td style="text-align:center;"><span class="attendance-badge ${r.status}"> ${r.status === 'presente' ? '✓' : '✗'} ${r.status}</span></td><td style="text-align:center;">${r.horario_entrada || '—'}</td></tr>`).join('');
}

// ── Logout ───────────────────────────────────────────────────────
async function doLogout() { await apiFetch('logout'); window.location.href = 'login.php'; }

// ── Toast ────────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const t = document.createElement('div'); t.className = 'toast'; t.innerText = msg; t.style.borderLeft = `5px solid ${type === 'error' ? '#dc3545' : '#28a745'}`;
    container.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 500); }, 3000);
}

// ── Listagem de Alunos (Professor Area) ──────────────────────────
async function loadStudents() {
    const data = await apiFetch('list_students');
    const tbody = document.getElementById('student-table-body');
    if (tbody && Array.isArray(data)) tbody.innerHTML = data.map(s => `<tr><td style="text-align:center;color:#888;">${s.id}</td><td>${s.nome}</td><td>${s.matricula}</td><td><button class="btn-action btn-delete" onclick="deleteStudent(${s.id}, '${s.nome}')">Excluir</button></td></tr>`).join('');
}

async function deleteStudent(id, name) {
    if (confirm(`Excluir ${name}?`) && (await apiFetch(`delete_student&id=${id}`)).success) { showToast('Removido'); loadStudents(); }
}

// ── Histórico de Aulas ───────────────────────────────────────────
async function loadHistory() {
    const data = await apiFetch('get_history');
    const tbody = document.getElementById('history-tbody');
    if (!tbody) return;
    if (!data.success || !data.history?.length) return tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#888;">Nenhuma aula encontrada no histórico.</td></tr>';
    tbody.innerHTML = data.history.map(h => {
        const dt = h.data_aula.split('-').reverse().join('/');
        return `<tr><td style="font-weight:600;">${h.nome_aula || '—'}</td><td style="color:#555;">${dt}</td><td style="color:#555;">${h.hora_inicio.slice(0, 5)} às ${h.hora_fim?.slice(0, 5) || '--:--'}</td><td style="text-align:center;"><span style="font-weight:bold;color:var(--primary)">${h.presentes}</span> / ${h.total_alunos}</td><td style="text-align:center;"><span class="attendance-badge ${h.status === 'ativa' ? 'presente' : 'falta'}" style="${h.status !== 'ativa' ? 'background:#e2e3e5;color:#383d41;' : ''}">${h.status === 'ativa' ? 'Em Andamento' : 'Encerrada'}</span></td></tr>`;
    }).join('');
}
