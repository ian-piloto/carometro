require_once __DIR__ . '/config/autoload.php';

use App\Config\Database;

header('Content-Type: text/plain; charset=utf-8');

echo "--- Diagnóstico de Conexão ---\n";

try {
$database = new Database();
$db = $database->getConnection();
echo "✅ Conexão estabelecida com sucesso!\n";

// Verificar tabelas
$result = $db->query("SHOW TABLES");
$tables = [];
while ($row = $result->fetch_array()) {
$tables[] = $row[0];
}

echo "Tabelas encontradas: " . (empty($tables) ? "NENHUMA" : implode(", ", $tables)) . "\n";

if (in_array('students', $tables)) {
$res = $db->query("SELECT COUNT(*) as total FROM students");
$count = $res->fetch_assoc()['total'];
echo "Total de alunos cadastrados: $count\n";

echo "\nEstrutura da tabela 'students':\n";
$res = $db->query("DESCRIBE students");
while ($col = $res->fetch_assoc()) {
echo " - {$col['Field']} ({$col['Type']})\n";
}
} else {
echo "❌ ERRO: Tabela 'students' não encontrada. Você executou o arquivo SQL?\n";
}

if (in_array('access_logs', $tables)) {
echo "\nEstrutura da tabela 'access_logs':\n";
$res = $db->query("DESCRIBE access_logs");
while ($col = $res->fetch_assoc()) {
echo " - {$col['Field']} ({$col['Type']})\n";
}
} else {
echo "❌ ERRO: Tabela 'access_logs' não encontrada.\n";
}
} catch (Exception $e) {
echo "❌ FALHA NA CONEXÃO: " . $e->getMessage() . "\n";
echo "DICA: Verifique se o MySQL no XAMPP está REALMENTE na porta 3308.\n";
}