/**
 * Lógica do Chat com Inteligência Artificial (LívIA)
 * Gerencia a interface de mensagens e comunicação com o backend.
 */

/**
 * Abre ou fecha a janela flutuante do chat.
 */
function toggleChat() {
    const chatWindow = document.getElementById('ai-chat-window');
    chatWindow.classList.toggle('hidden');

    // Foca no campo de entrada ao abrir para agilizar a interação
    if (!chatWindow.classList.contains('hidden')) {
        document.getElementById('chat-input').focus();
    }
}

/**
 * Detecta a tecla Enter no campo de mensagem para enviar.
 */
function handleChatKey(event) {
    if (event.key === 'Enter') {
        sendMessage();
    }
}

/**
 * Coleta a mensagem do usuário e envia para processamento da IA.
 */
async function sendMessage() {
    const input = document.getElementById('chat-input');
    const message = input.value.trim();

    // Impede envio de mensagens vazias
    if (!message) return;

    // Exibe a mensagem do usuário no chat imediatamente
    appendMessage(message, 'user');
    input.value = '';

    // Adiciona o indicador visual de que a IA está "pensando"
    const typingId = appendTypingIndicator();

    try {
        // Requisição AJAX para a nossa API PHP que conversa com o OpenRouter
        const response = await fetch('api.php?action=chat_ai', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: message })
        });

        const result = await response.json();

        // Remove o indicador de digitação após receber a resposta
        const typingEl = document.getElementById(typingId);
        if (typingEl) typingEl.remove();

        if (result.success) {
            // Exibe a resposta da Lívia
            appendMessage(result.response, 'ai');
        } else {
            appendMessage(result.message || 'Desculpe, tive um problema ao conectar com minha base de dados.', 'ai');
        }
    } catch (err) {
        // Tratamento básico de erro de conexão/rede
        const typingEl = document.getElementById(typingId);
        if (typingEl) typingEl.remove();

        appendMessage('Estou com dificuldades de conexão no momento. Tente novamente em instantes.', 'ai');
        console.error('Chat Error:', err);
    }
}

/**
 * Adiciona um balão de mensagem no histórico do chat.
 * 
 * @param {string} text Conteúdo da mensagem.
 * @param {string} side 'user' ou 'ai' para definir o estilo visual.
 */
function appendMessage(text, side) {
    const container = document.getElementById('chat-messages');
    const msgDiv = document.createElement('div');

    msgDiv.className = `message ${side}`;
    msgDiv.innerText = text;

    container.appendChild(msgDiv);

    // Faz o scroll automático para a última mensagem enviada/recebida
    container.scrollTop = container.scrollHeight;
}

/**
 * Cria e exibe o indicador visual de digitação da assistente.
 * 
 * @returns {string} ID único do elemento para posterior remoção.
 */
function appendTypingIndicator() {
    const container = document.getElementById('chat-messages');
    const id = 'typing-' + Date.now();
    const div = document.createElement('div');

    div.id = id;
    div.className = 'typing-indicator';
    div.innerText = 'Lívia está digitando...';

    container.appendChild(div);
    container.scrollTop = container.scrollHeight;

    return id;
}
