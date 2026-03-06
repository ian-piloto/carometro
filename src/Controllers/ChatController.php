<?php

namespace App\Controllers;

use App\Models\Student;
use mysqli;
use Exception;

/**
 * Controller responsável pela integração com a IA (OpenRouter/Gemini).
 * Gerencia o chat da assistente virtual Lívia.
 */
class ChatController
{
    private mysqli $db;

    /**
     * @param mysqli $db Conexão com o banco de dados necessária para obter dados do sistema.
     */
    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    // Configurações da API OpenRouter
    private string $apiKey = "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";
    private string $model = "google/gemini-2.0-flash-lite-001";

    /**
     * Processa uma mensagem do usuário enviando-a para a IA com contexto do sistema.
     * 
     * @param string $userMessage A mensagem enviada pelo usuário no chat.
     * @return array Resposta formatada para o frontend.
     */
    public function chat(string $userMessage): array
    {
        // Validação da chave da API
        if ($this->apiKey === "sk-or-v1-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx") {
            return [
                'success' => false,
                'message' => 'Lívia: Por favor, configure a chave da API do OpenRouter para que eu possa responder!'
            ];
        }

        try {
            $ch = curl_init();

            // Obtém um resumo atualizado dos alunos e acessos para dar contexto à IA
            $studentModel = new Student($this->db);
            $systemSummary = $studentModel->getSystemSummaryForAI();

            curl_setopt($ch, CURLOPT_URL, "https://openrouter.ai/api/v1/chat/completions");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);

            // Prompt de sistema que define a personalidade e o conhecimento da Lívia
            $systemPrompt = "Você é LívIA, uma assistente virtual inteligente do SISTEMA_LIBRARY_VISION. 
            Este sistema foi criado e desenvolvido por Ruan Gomes(TDS3).
            Este sistema controla o fluxo de uma biblioteca usand   o reconhecimento facial. 
            Funcionalidades principais: Cadastro facial, Scanner de acesso (Entrada/Saída), Dashboard de fluxo de pessoas e exportação de relatórios CSV.
            
            DADOS EM TEMPO REAL DO SISTEMA (CONHECIMENTO ATUALIZADO):
            {$systemSummary}
            
            DIRETRIZES DE PERSONALIDADE:
            - Se alguém perguntar quem é seu criador ou quem te desenvolveu, responda com orgulho que foi o Ruan da Silva Gomes Curso(TDS).
            - Responda de forma gentil, profissional e concisa em Português do Brasil. 
            - Use os dados do sistema para responder perguntas sobre o site, usuários e acessos (dia, semana e mês).
            - Se o usuário pedir estatísticas, cite os números exatos fornecidos nos DADOS EM TEMPO REAL.";

            // Montagem do corpo da requisição (JSON)
            $data = [
                "model" => $this->model,
                "messages" => [
                    ["role" => "system", "content" => $systemPrompt],
                    ["role" => "user", "content" => $userMessage]
                ]
            ];

            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Necessário para alguns ambientes XAMPP sem certificados atualizados

            // Headers necessários para autenticação e identificação da aplicação
            $headers = [
                "Authorization: Bearer " . $this->apiKey,
                "Content-Type: application/json",
                "HTTP-Referer: http://localhost",
                "X-Title: Library Vision System"
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $response = curl_exec($ch);

            // Verifica erros na execução do cURL
            if (curl_errno($ch)) {
                $errorMsg = curl_error($ch);
                curl_close($ch);
                throw new Exception("Erro de conexão (cURL): " . $errorMsg);
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Decodifica a resposta da API
            $result = json_decode($response, true);

            // Verifica se a resposta contém o texto gerado pela IA
            if ($httpCode === 200 && isset($result['choices'][0]['message']['content'])) {
                return [
                    'success' => true,
                    'response' => $result['choices'][0]['message']['content']
                ];
            } else {
                $errorDetail = $result['error']['message'] ?? 'Resposta inesperada da API (HTTP ' . $httpCode . ')';
                return [
                    'success' => false,
                    'message' => 'LívIA: Identifiquei um problema na minha rede neural. Detalhe: ' . $errorDetail
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro na comunicação com a IA: ' . $e->getMessage()
            ];
        }
    }
}
