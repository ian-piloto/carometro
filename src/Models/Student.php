<?php

namespace App\Models;

use mysqli;
use Exception;

/**
 * Modelo de dados para a entidade Aluno (Student).
 * Responsável por todas as operações SQL relacionadas aos alunos e logs de acesso.
 */
class Student
{
    private mysqli $db;
    private string $table = "students";

    /**
     * @param mysqli $db Objeto de conexão com o banco de dados.
     */
    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Insere um novo aluno no banco de dados.
     * 
     * @param array $data Dados do aluno (name, registration, email, face_descriptor).
     * @return bool True se inserido com sucesso, False caso contrário.
     */
    public function create(array $data): bool
    {
        $sql = "INSERT INTO {$this->table} (registration_number, name, class_name, email, face_descriptor, status)
                VALUES (?, ?, ?, ?, ?, 'active')";

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $email = $data['email'] ?? null;
        $class_name = $data['class_name'] ?? null;

        $stmt->bind_param(
            "sssss",
            $data['registration'],
            $data['name'],
            $class_name,
            $email,
            $data['face_descriptor']
        );

        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Retorna a lista de todos os alunos, incluindo contagem de acessos diários.
     * Utiliza uma subquery para contar os acessos do dia atual para cada aluno.
     * 
     * @return array Lista de alunos associativos.
     */
    public function getAll(): array
    {
        $today = date('Y-m-d');
        $sql = "SELECT s.id, s.name, s.class_name, s.email, s.status, s.created_at, s.updated_at, s.face_descriptor,
                       (SELECT COUNT(*) FROM access_logs al WHERE al.student_id = s.id AND DATE(al.access_time) = ?) as daily_access_count,
                       (SELECT MAX(access_time) FROM access_logs al WHERE al.student_id = s.id AND DATE(al.access_time) = ? AND al.type = 'entry') as last_entry
                FROM {$this->table} s 
                ORDER BY last_entry DESC, s.name ASC";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("ss", $today, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $data;
    }

    /**
     * Retorna apenas os alunos que estão com status 'active'.
     */
    public function getAllActive(): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE status = 'active' ORDER BY name ASC";
        $result = $this->db->query($sql);

        if (!$result) {
            return [];
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Busca os dados de um aluno específico pelo seu ID único.
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ? AND status = 'active'";
        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        return $result->fetch_assoc();
    }

    /**
     * Busca um aluno através da sua matrícula.
     */
    public function getByRegistration(string $registration): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE registration_number = ? AND status = 'active'";
        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("s", $registration);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        return $result->fetch_assoc();
    }

    /**
     * Método placeholder para busca por face descriptor.
     * Na prática, a comparação fina de similaridade ocorre no frontend (JS Euclidean Distance).
     */
    public function getByFaceDescriptor(string $descriptor): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE status = 'active'";
        $result = $this->db->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Atualiza o nome e email de um aluno existente.
     */
    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET name = ?, email = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ssi", $data['name'], $data['email'], $id);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Desativa o registro de um aluno (Soft Delete).
     */
    public function deactivate(int $id): bool
    {
        $sql = "UPDATE {$this->table} SET status = 'inactive' WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("i", $id);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Reativa o registro de um aluno inativo.
     */
    public function activate(int $id): bool
    {
        $sql = "UPDATE {$this->table} SET status = 'active' WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("i", $id);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Remove definitivamente um aluno e todos os seus logs de acesso associados.
     */
    public function delete(int $id): bool
    {
        // 1. Remove os logs de acesso primeiro para garantir integridade referencial
        $sqlLogs = "DELETE FROM access_logs WHERE student_id = ?";
        $stmtLogs = $this->db->prepare($sqlLogs);
        if ($stmtLogs) {
            $stmtLogs->bind_param("i", $id);
            $stmtLogs->execute();
            $stmtLogs->close();
        }

        // 2. Remove o registro do aluno
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("i", $id);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Registra um evento de acesso (entrada ou saída) na tabela de auditoria.
     */
    public function logAccess(int $student_id, string $type = 'entry'): bool
    {
        $sql = "INSERT INTO access_logs (student_id, type) VALUES (?, ?)";
        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("is", $student_id, $type);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Registra ou atualiza a presença do aluno para o dia atual na tabela attendance_logs.
     * Se já houver registro do dia, atualiza o horário. Caso contrário, insere.
     */
    public function markAttendanceToday(int $student_id): bool
    {
        $today = date('Y-m-d');
        $now = date('H:i:s');

        // Verifica se já existe registro de presença para hoje
        $check = $this->db->prepare("SELECT id FROM attendance_logs WHERE student_id = ? AND data = ?");
        if (!$check) {
            return false;
        }

        $check->bind_param("is", $student_id, $today);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();

        if ($exists) {
            // Atualiza horário do registro existente
            $stmt = $this->db->prepare(
                "UPDATE attendance_logs SET status = 'presente', horario_registro = ? WHERE student_id = ? AND data = ?"
            );
            if (!$stmt)
                return false;
            $stmt->bind_param("sis", $now, $student_id, $today);
        }
        else {
            // Cria novo registro de presença
            $stmt = $this->db->prepare(
                "INSERT INTO attendance_logs (student_id, data, status, horario_registro) VALUES (?, ?, 'presente', ?)"
            );
            if (!$stmt)
                return false;
            $stmt->bind_param("iss", $student_id, $today, $now);
        }

        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }


    /**
     * Consolida dados para exibição no dashboard de fluxo de pessoas.
     * Retorna o total de acessos de hoje e a distribuição por hora (8h-17h).
     */
    public function getDashboardStats(): array
    {
        $today = date('Y-m-d');

        // Contabiliza o total de entradas/saídas do dia
        $sqlTotal = "SELECT COUNT(*) as total FROM access_logs WHERE DATE(access_time) = ?";
        $stmtTotal = $this->db->prepare($sqlTotal);
        $totalAcessos = 0;

        if ($stmtTotal) {
            $stmtTotal->bind_param("s", $today);
            $stmtTotal->execute();
            $resultTotal = $stmtTotal->get_result();
            if ($row = $resultTotal->fetch_assoc()) {
                $totalAcessos = (int)$row['total'];
            }
            $stmtTotal->close();
        }

        // Agrupa o fluxo de pessoas por hora para o gráfico
        $sqlFlow = "SELECT HOUR(access_time) as hour, COUNT(*) as count 
                    FROM access_logs 
                    WHERE DATE(access_time) = ? 
                    GROUP BY HOUR(access_time) 
                    ORDER BY hour ASC";
        $stmtFlow = $this->db->prepare($sqlFlow);

        // Inicializa o array do gráfico com 0 para todas as horas comerciais (8h às 17h)
        $flowData = array_fill(8, 10, 0);

        if ($stmtFlow) {
            $stmtFlow->bind_param("s", $today);
            $stmtFlow->execute();
            $resultFlow = $stmtFlow->get_result();

            while ($row = $resultFlow->fetch_assoc()) {
                $hour = (int)$row['hour'];
                if ($hour >= 8 && $hour <= 17) {
                    $flowData[$hour] = (int)$row['count'];
                }
            }
            $stmtFlow->close();
        }

        return [
            'total_acessos' => $totalAcessos,
            'flow_data' => array_values($flowData) // Re-indexa para um array JSON limpo
        ];
    }

    /**
     * Retorna estatísticas de acesso consolidadas por períodos (Dia, Semana, Mês).
     * 
     * @return array Array associativo com as contagens.
     */
    public function getAccessStatsByPeriod(): array
    {
        $stats = [
            'hoje' => 0,
            'semana' => 0,
            'mes' => 0
        ];

        // Hoje
        $sqlToday = "SELECT COUNT(*) as total FROM access_logs WHERE DATE(access_time) = CURDATE()";
        $resToday = $this->db->query($sqlToday);
        $stats['hoje'] = (int)($resToday->fetch_assoc()['total'] ?? 0);

        // Esta Semana (Calculado de Segunda a Domingo)
        $sqlWeek = "SELECT COUNT(*) as total FROM access_logs WHERE YEARWEEK(access_time, 1) = YEARWEEK(CURDATE(), 1)";
        $resWeek = $this->db->query($sqlWeek);
        $stats['semana'] = (int)($resWeek->fetch_assoc()['total'] ?? 0);

        // Este Mês
        $sqlMonth = "SELECT COUNT(*) as total FROM access_logs WHERE MONTH(access_time) = MONTH(CURDATE()) AND YEAR(access_time) = YEAR(CURDATE())";
        $resMonth = $this->db->query($sqlMonth);
        $stats['mes'] = (int)($resMonth->fetch_assoc()['total'] ?? 0);

        return $stats;
    }

    /**
     * Gera um resumo textual do estado do sistema.
     * Utilizado para alimentar o contexto da IA (Lívia).
     */
    public function getSystemSummaryForAI(): string
    {
        $today = date('Y-m-d');

        // 1. Total de Alunos cadastrados
        $res = $this->db->query("SELECT COUNT(*) as total FROM students WHERE status = 'active'");
        $totalAlunos = $res->fetch_assoc()['total'] ?? 0;

        // 2. Lista os primeiros 15 alunos e seus acessos do dia
        $alunosData = $this->getAll();
        $listaAlunos = [];
        foreach ($alunosData as $a) {
            $hora = $a['last_entry'] ? date('H:i', strtotime($a['last_entry'])) : 'N/A';
            $listaAlunos[] = "- {$a['name']} (Turma: {$a['class_name']}): {$a['daily_access_count']} acessos hoje (Última entrada: {$hora})";
        }
        $strAlunos = implode("\n", array_slice($listaAlunos, 0, 15));

        // 3. Obtém os 5 eventos de acesso mais recentes
        $sqlRecent = "SELECT s.name, al.access_time, al.type 
                      FROM access_logs al 
                      JOIN students s ON al.student_id = s.id 
                      ORDER BY al.access_time DESC LIMIT 5";
        $resRecent = $this->db->query($sqlRecent);
        $recentes = [];
        while ($row = $resRecent->fetch_assoc()) {
            $tipo = $row['type'] === 'entry' ? 'Entrada' : 'Saída';
            $hora = date('H:i', strtotime($row['access_time']));
            $recentes[] = "- {$row['name']} ({$tipo} às {$hora})";
        }
        $strRecentes = implode("\n", $recentes);

        // 4. Estatísticas de fluxo total de hoje e períodos
        $periodStats = $this->getAccessStatsByPeriod();

        // Retorna o bloco formatado de texto
        return "ESTATÍSTICAS GERAIS:
        - Total de alunos ativos: {$totalAlunos}
        - Total de acessos HOJE: {$periodStats['hoje']}
        - Total de acessos NESTA SEMANA: {$periodStats['semana']}
        - Total de acessos NESTE MÊS: {$periodStats['mes']}

        ÚLTIMOS ACESSOS AGORA:
        {$strRecentes}

        LISTA DE ALUNOS (E ACESSOS HOJE):
        {$strAlunos}";
    }
}
