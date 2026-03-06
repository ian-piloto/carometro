<?php

namespace App\Controllers;

use mysqli;
use Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportController
{
    private mysqli $db;

    // Paleta de cores institucional
    private const COR_PRIMARIA = 'BC0000'; // Vermelho Carmim
    private const COR_PRIMARIA_DARK = '8B0000'; // Vermelho escuro
    private const COR_TEXTO_HEADER = 'FFFFFF'; // Branco
    private const COR_ZEBRA_PAR = 'FFF5F5'; // Rosa muito claro
    private const COR_ZEBRA_IMPAR = 'FFFFFF'; // Branco
    private const COR_BORDA = 'DDDDDD'; // Cinza claro
    private const COR_DESTAQUE = 'FFF3CD'; // Amarelo suave

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    // =========================================================================
    // ENTRY POINT
    // =========================================================================

    public function exportExcel(): void
    {
        try {
            $spreadsheet = new Spreadsheet();
            $spreadsheet->getProperties()
                ->setCreator('Library Vision')
                ->setTitle('Relatório Completo - Library Vision')
                ->setDescription('Relatório gerado automaticamente pelo sistema de controle de acesso.')
                ->setKeywords('biblioteca alunos frequência acessos')
                ->setCategory('Relatório Escolar');

            // --- Constrói cada aba ---
            $this->buildAbaResumo($spreadsheet);
            $this->buildAbaAlunos($spreadsheet);
            $this->buildAbaAcessos($spreadsheet);
            $this->buildAbaFrequencia($spreadsheet);

            // Define a primeira aba como ativa ao abrir
            $spreadsheet->setActiveSheetIndex(0);

            // --- Envia o arquivo para download ---
            $filename = 'relatorio_biblioteca_' . date('Y-m-d') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            $writer = new Xlsx($spreadsheet);
            $writer->setIncludeCharts(true);
            $writer->save('php://output');
            exit;

        }
        catch (Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro na exportação: ' . $e->getMessage()]);
            exit;
        }
    }

    // =========================================================================
    // ABA 1 — RESUMO EXECUTIVO
    // =========================================================================

    private function buildAbaResumo(Spreadsheet $spreadsheet): void
    {
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('Resumo Executivo');

        // Linha 1: título principal — merge exclusivo A1:H1
        $ws->mergeCells('A1:H1');
        $ws->setCellValue('A1', 'LIBRARY VISION - Relatorio Gerencial');
        $this->styleHeader($ws, 'A1:H1', self::COR_PRIMARIA_DARK, 16);
        $ws->getRowDimension(1)->setRowHeight(30);

        // Linha 2: subtítulo — merge exclusivo A2:H2
        $ws->mergeCells('A2:H2');
        $ws->setCellValue('A2', 'Gerado em: ' . date('d/m/Y') . ' as ' . date('H:i:s') . '  |  Sistema de Controle de Acesso');
        $ws->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
        $ws->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle('A2')->getFont()->getColor()->setARGB('888888');

        // Linha 3: espaço visual
        $ws->getRowDimension(3)->setRowHeight(8);

        // -----------------------------------------------
        // ZONA ESQUERDA: colunas A-D  (KPIs + Turmas)
        // -----------------------------------------------

        // Linha 4 col A-D: título KPIs — merge exclusivo A4:D4
        $ws->mergeCells('A4:D4');
        $ws->setCellValue('A4', 'INDICADORES GERAIS');
        $this->applySectionTitleStyle($ws, 'A4:D4');
        $ws->getRowDimension(4)->setRowHeight(20);

        // Linha 5: cabeçalho da tabela KPI
        $ws->mergeCells('A5:B5');
        $ws->setCellValue('A5', 'Indicador');
        $ws->setCellValue('C5', 'Valor');
        $ws->setCellValue('D5', 'Unidade');
        $this->styleSubHeader($ws, 'A5:D5');

        $kpis = $this->fetchKpis();
        $kpiData = [
            ['Total de Alunos Cadastrados', $kpis['total_alunos'], 'alunos'],
            ['Alunos Ativos', $kpis['alunos_ativos'], 'alunos'],
            ['Acessos Hoje', $kpis['acessos_hoje'], 'registros'],
            ['Acessos Esta Semana', $kpis['acessos_semana'], 'registros'],
            ['Acessos Este Mes', $kpis['acessos_mes'], 'registros'],
            ['Hora de Pico (Hoje)', $kpis['hora_pico'], ''],
            ['Turma com mais Presencas', $kpis['turma_top'], ''],
        ];

        $row = 6;
        foreach ($kpiData as $i => [$label, $valor, $unidade]) {
            $ws->mergeCells("A{$row}:B{$row}");
            $ws->setCellValue("A{$row}", $label);
            $ws->setCellValue("C{$row}", $valor);
            $ws->setCellValue("D{$row}", $unidade);

            $bg = ($i % 2 === 0) ?self::COR_ZEBRA_PAR : self::COR_ZEBRA_IMPAR;
            $ws->getStyle("A{$row}:D{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);
            $ws->getStyle("C{$row}")->getFont()->setBold(true)->setSize(12);
            $ws->getStyle("C{$row}")->getFont()->getColor()->setARGB(self::COR_PRIMARIA);
            $ws->getStyle("A{$row}:D{$row}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::COR_BORDA);
            $ws->getRowDimension($row)->setRowHeight(20);
            $row++;
        }

        // Espaço antes da seção de turmas
        $row++;

        // Seção Turmas — merge exclusivo somente dentro de A-D
        $ws->mergeCells("A{$row}:D{$row}");
        $ws->setCellValue("A{$row}", 'ACESSOS POR TURMA (HOJE)');
        $this->applySectionTitleStyle($ws, "A{$row}:D{$row}");
        $ws->getRowDimension($row)->setRowHeight(20);
        $row++;

        $ws->setCellValue("A{$row}", 'Turma');
        $ws->setCellValue("B{$row}", 'Alunos');
        $ws->setCellValue("C{$row}", 'Acessos Hoje');
        $this->styleSubHeader($ws, "A{$row}:C{$row}");
        $row++;

        foreach ($this->fetchAcessosPorTurma() as $i => $t) {
            $ws->setCellValue("A{$row}", $t['class_name'] ?: 'Sem Turma');
            $ws->setCellValue("B{$row}", $t['total_alunos']);
            $ws->setCellValue("C{$row}", $t['acessos_hoje']);
            $bg = ($i % 2 === 0) ?self::COR_ZEBRA_PAR : self::COR_ZEBRA_IMPAR;
            $ws->getStyle("A{$row}:C{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);
            $ws->getStyle("A{$row}:C{$row}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::COR_BORDA);
            $ws->getRowDimension($row)->setRowHeight(18);
            $row++;
        }

        // -----------------------------------------------
        // ZONA DIREITA: colunas F-H  (Fluxo por hora)
        // Linha 4 col F-H — NÃO sobrepõe A4:D4
        // -----------------------------------------------
        $ws->mergeCells('F4:H4');
        $ws->setCellValue('F4', 'FLUXO POR HORA (HOJE)');
        $this->applySectionTitleStyle($ws, 'F4:H4');

        $ws->setCellValue('F5', 'Hora');
        $ws->setCellValue('G5', 'Entradas');
        $ws->setCellValue('H5', 'Saidas');
        $this->styleSubHeader($ws, 'F5:H5');

        $fluxoRow = 6;
        foreach ($this->fetchFluxoPorHora() as $i => $f) {
            $ws->setCellValue("F{$fluxoRow}", $f['hora'] . 'h');
            $ws->setCellValue("G{$fluxoRow}", $f['entradas']);
            $ws->setCellValue("H{$fluxoRow}", $f['saidas']);
            $bg = $f['entradas'] > 0
                ?self::COR_DESTAQUE
                : (($i % 2 === 0) ?self::COR_ZEBRA_PAR : self::COR_ZEBRA_IMPAR);
            $ws->getStyle("F{$fluxoRow}:H{$fluxoRow}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);
            $ws->getStyle("F{$fluxoRow}:H{$fluxoRow}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::COR_BORDA);
            $ws->getStyle("F{$fluxoRow}:H{$fluxoRow}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $ws->getRowDimension($fluxoRow)->setRowHeight(18);
            $fluxoRow++;
        }

        // Larguras
        $ws->getColumnDimension('A')->setWidth(30);
        $ws->getColumnDimension('B')->setWidth(12);
        $ws->getColumnDimension('C')->setWidth(18);
        $ws->getColumnDimension('D')->setWidth(12);
        $ws->getColumnDimension('E')->setWidth(4);
        $ws->getColumnDimension('F')->setWidth(12);
        $ws->getColumnDimension('G')->setWidth(14);
        $ws->getColumnDimension('H')->setWidth(14);

        $ws->freezePane('A6');
    }

    /** Aplica estilo de seção SEM fazer merge (o merge deve ser feito antes externamente). */
    private function applySectionTitleStyle(Worksheet $ws, string $range): void
    {
        $style = $ws->getStyle($range);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('3D3D3D');
        $style->getFont()->setBold(true)->setSize(10);
        $style->getFont()->getColor()->setARGB('FFFFFF');
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }


    // =========================================================================
    // ABA 2 — ALUNOS CADASTRADOS
    // =========================================================================

    private function buildAbaAlunos(Spreadsheet $spreadsheet): void
    {
        $ws = $spreadsheet->createSheet();
        $ws->setTitle('👤 Alunos');

        // Título
        $ws->mergeCells('A1:H1');
        $ws->setCellValue('A1', 'CADASTRO DE ALUNOS — ' . date('d/m/Y'));
        $this->styleHeader($ws, 'A1:H1', self::COR_PRIMARIA, 13);
        $ws->getRowDimension(1)->setRowHeight(26);

        // Cabeçalhos das colunas
        $headers = ['#', 'Matrícula', 'Nome Completo', 'Turma', 'E-mail', 'Status', 'Cadastrado em', 'Total Acessos'];
        $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        foreach ($headers as $i => $h) {
            $ws->setCellValue($cols[$i] . '2', $h);
        }
        $this->styleSubHeader($ws, 'A2:H2');
        $ws->getRowDimension(2)->setRowHeight(20);
        $ws->freezePane('A3');

        // Dados
        $alunos = $this->fetchAlunos();
        $row = 3;
        foreach ($alunos as $i => $a) {
            $ws->setCellValue("A{$row}", $a['id']);
            $ws->setCellValue("B{$row}", $a['registration_number']);
            $ws->setCellValue("C{$row}", $a['name']);
            $ws->setCellValue("D{$row}", $a['class_name'] ?: '—');
            $ws->setCellValue("E{$row}", $a['email'] ?: '—');
            $ws->setCellValue("F{$row}", $a['status'] === 'active' ? 'Ativo ✓' : 'Inativo ✗');
            $ws->setCellValue("G{$row}", date('d/m/Y H:i', strtotime($a['created_at'])));
            $ws->setCellValue("H{$row}", $a['total_acessos']);

            $bg = ($i % 2 === 0) ?self::COR_ZEBRA_PAR : self::COR_ZEBRA_IMPAR;
            $ws->getStyle("A{$row}:H{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);

            // Status: destaque visual
            if ($a['status'] === 'active') {
                $ws->getStyle("F{$row}")->getFont()->getColor()->setARGB('1A7A1A');
            }
            else {
                $ws->getStyle("F{$row}")->getFont()->getColor()->setARGB(self::COR_PRIMARIA);
            }

            $ws->getStyle("A{$row}:H{$row}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::COR_BORDA);
            $ws->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $ws->getStyle("H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $ws->getRowDimension($row)->setRowHeight(18);
            $row++;
        }

        // Rodapé com total
        $ws->setCellValue("A{$row}", 'Total:');
        $ws->setCellValue("C{$row}", count($alunos) . ' alunos');
        $ws->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);
        $ws->getStyle("A{$row}:H{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('F0F0F0');

        // Larguras
        $ws->getColumnDimension('A')->setWidth(6);
        $ws->getColumnDimension('B')->setWidth(15);
        $ws->getColumnDimension('C')->setWidth(35);
        $ws->getColumnDimension('D')->setWidth(15);
        $ws->getColumnDimension('E')->setWidth(28);
        $ws->getColumnDimension('F')->setWidth(12);
        $ws->getColumnDimension('G')->setWidth(18);
        $ws->getColumnDimension('H')->setWidth(15);

        // Filtro automático
        $ws->setAutoFilter("A2:H2");
    }

    // =========================================================================
    // ABA 3 — HISTÓRICO DE ACESSOS
    // =========================================================================

    private function buildAbaAcessos(Spreadsheet $spreadsheet): void
    {
        $ws = $spreadsheet->createSheet();
        $ws->setTitle('📋 Histórico de Acessos');

        $ws->mergeCells('A1:F1');
        $ws->setCellValue('A1', 'HISTÓRICO COMPLETO DE ACESSOS');
        $this->styleHeader($ws, 'A1:F1', self::COR_PRIMARIA, 13);

        $headers = ['#', 'Nome do Aluno', 'Turma', 'Tipo', 'Data', 'Horário'];
        $cols = ['A', 'B', 'C', 'D', 'E', 'F'];
        foreach ($headers as $i => $h) {
            $ws->setCellValue($cols[$i] . '2', $h);
        }
        $this->styleSubHeader($ws, 'A2:F2');
        $ws->freezePane('A3');

        $logs = $this->fetchHistoricoAcessos();
        $row = 3;
        foreach ($logs as $i => $log) {
            $tipo = $log['type'] === 'entry' ? '🟢 Entrada' : '🔴 Saída';
            $timestamp = strtotime($log['access_time']);

            $ws->setCellValue("A{$row}", $i + 1);
            $ws->setCellValue("B{$row}", $log['name']);
            $ws->setCellValue("C{$row}", $log['class_name'] ?: '—');
            $ws->setCellValue("D{$row}", $tipo);
            $ws->setCellValue("E{$row}", date('d/m/Y', $timestamp));
            $ws->setCellValue("F{$row}", date('H:i:s', $timestamp));

            $bg = ($log['type'] === 'entry')
                ? (($i % 2 === 0) ? 'F0FFF0' : 'E8FFE8')
                : (($i % 2 === 0) ? 'FFF0F0' : 'FFE8E8');

            $ws->getStyle("A{$row}:F{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);
            $ws->getStyle("A{$row}:F{$row}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::COR_BORDA);
            $ws->getRowDimension($row)->setRowHeight(17);
            $row++;
        }

        $ws->getColumnDimension('A')->setWidth(7);
        $ws->getColumnDimension('B')->setWidth(35);
        $ws->getColumnDimension('C')->setWidth(15);
        $ws->getColumnDimension('D')->setWidth(14);
        $ws->getColumnDimension('E')->setWidth(14);
        $ws->getColumnDimension('F')->setWidth(12);

        $ws->setAutoFilter('A2:F2');
    }

    // =========================================================================
    // ABA 4 — FREQUÊNCIA DIÁRIA
    // =========================================================================

    private function buildAbaFrequencia(Spreadsheet $spreadsheet): void
    {
        $ws = $spreadsheet->createSheet();
        $ws->setTitle('📅 Frequência Diária');

        // Busca os últimos 14 dias e todos os alunos
        $dias = $this->fetchUltimosDias(14);
        $alunos = $this->fetchAlunosAtivos();
        $freq = $this->fetchFrequencia();

        // Monta índice: [student_id][data] = status
        $freqIndex = [];
        foreach ($freq as $f) {
            $freqIndex[$f['student_id']][$f['data']] = $f['status'];
        }

        // Título
        $totalCols = count($dias) + 3;
        $lastCol = $this->colLetra(min($totalCols, 25)); // máx Z
        $ws->mergeCells("A1:{$lastCol}1");
        $ws->setCellValue('A1', 'FREQUÊNCIA DIÁRIA — ÚLTIMOS 14 DIAS');
        $this->styleHeader($ws, "A1:{$lastCol}1", self::COR_PRIMARIA, 13);

        // Linha de datas (cabeçalho)
        $ws->setCellValue('A2', '#');
        $ws->setCellValue('B2', 'Matrícula');
        $ws->setCellValue('C2', 'Nome do Aluno');

        foreach ($dias as $j => $dia) {
            $col = $this->colLetra(3 + $j);
            $ws->setCellValue($col . '2', date('d/m', strtotime($dia)));
            $ws->getStyle($col . '2')->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setTextRotation(45);
            $ws->getColumnDimension($col)->setWidth(9);
        }
        $this->styleSubHeader($ws, "A2:{$lastCol}2");
        $ws->getRowDimension(2)->setRowHeight(35);
        $ws->freezePane('D3');

        // Dados por aluno
        $row = 3;
        foreach ($alunos as $i => $aluno) {
            $ws->setCellValue("A{$row}", $i + 1);
            $ws->setCellValue("B{$row}", $aluno['registration_number']);
            $ws->setCellValue("C{$row}", $aluno['name']);

            $totalPresente = 0;
            foreach ($dias as $j => $dia) {
                $col = $this->colLetra(3 + $j);
                $status = $freqIndex[$aluno['id']][$dia] ?? null;

                if ($status === 'presente') {
                    $ws->setCellValue($col . $row, '✓');
                    $ws->getStyle($col . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('C8F7C5');
                    $ws->getStyle($col . $row)->getFont()->getColor()->setARGB('1A7A1A');
                    $totalPresente++;
                }
                elseif ($status === 'falta') {
                    $ws->setCellValue($col . $row, '✗');
                    $ws->getStyle($col . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD0D0');
                    $ws->getStyle($col . $row)->getFont()->getColor()->setARGB(self::COR_PRIMARIA);
                }
                else {
                    $ws->setCellValue($col . $row, '—');
                    $ws->getStyle($col . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('F5F5F5');
                    $ws->getStyle($col . $row)->getFont()->getColor()->setARGB('AAAAAA');
                }
                $ws->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            // Linha zebra
            $bg = ($i % 2 === 0) ? 'FAFAFA' : self::COR_ZEBRA_IMPAR;
            $ws->getStyle("A{$row}:C{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);
            $ws->getStyle("A{$row}:{$lastCol}{$row}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::COR_BORDA);
            $ws->getRowDimension($row)->setRowHeight(17);
            $row++;
        }

        // Larguras fixas para colunas de texto
        $ws->getColumnDimension('A')->setWidth(6);
        $ws->getColumnDimension('B')->setWidth(14);
        $ws->getColumnDimension('C')->setWidth(30);

        $ws->setAutoFilter("A2:{$lastCol}2");
    }

    // =========================================================================
    // HELPERS DE STYLE
    // =========================================================================

    private function styleHeader(Worksheet $ws, string $range, string $bgColor, int $fontSize = 12): void
    {
        $style = $ws->getStyle($range);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bgColor);
        $style->getFont()->setBold(true)->setSize($fontSize)->setName('Calibri');
        $style->getFont()->getColor()->setARGB(self::COR_TEXTO_HEADER);
        $style->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $style->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB($bgColor);
    }

    private function styleSubHeader(Worksheet $ws, string $range): void
    {
        $style = $ws->getStyle($range);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COR_PRIMARIA);
        $style->getFont()->setBold(true)->setSize(10)->setName('Calibri');
        $style->getFont()->getColor()->setARGB(self::COR_TEXTO_HEADER);
        $style->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $style->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::COR_PRIMARIA_DARK);
    }

    private function styleSectionTitle(Worksheet $ws, string $range): void
    {
        $style = $ws->getStyle($range);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('3D3D3D');
        $style->getFont()->setBold(true)->setSize(10);
        $style->getFont()->getColor()->setARGB('FFFFFF');
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $ws->mergeCells($range);
    }

    private function colLetra(int $n): string
    {
        // Converte número (1-based) para letra de coluna do Excel (A, B, ..., Z, AA, ...)
        $col = '';
        while ($n > 0) {
            $n--;
            $col = chr(65 + ($n % 26)) . $col;
            $n = (int)($n / 26);
        }
        return $col;
    }

    // =========================================================================
    // QUERIES DE DADOS
    // =========================================================================

    private function fetchKpis(): array
    {
        $today = date('Y-m-d');
        $kpis = [
            'total_alunos' => 0,
            'alunos_ativos' => 0,
            'acessos_hoje' => 0,
            'acessos_semana' => 0,
            'acessos_mes' => 0,
            'hora_pico' => 'N/A',
            'turma_top' => 'N/A',
        ];

        $r = $this->db->query("SELECT COUNT(*) c FROM students");
        $kpis['total_alunos'] = (int)$r->fetch_row()[0];

        $r = $this->db->query("SELECT COUNT(*) c FROM students WHERE status='active'");
        $kpis['alunos_ativos'] = (int)$r->fetch_row()[0];

        $r = $this->db->query("SELECT COUNT(*) c FROM access_logs WHERE DATE(access_time)=CURDATE()");
        $kpis['acessos_hoje'] = (int)$r->fetch_row()[0];

        $r = $this->db->query("SELECT COUNT(*) c FROM access_logs WHERE YEARWEEK(access_time,1)=YEARWEEK(CURDATE(),1)");
        $kpis['acessos_semana'] = (int)$r->fetch_row()[0];

        $r = $this->db->query("SELECT COUNT(*) c FROM access_logs WHERE MONTH(access_time)=MONTH(CURDATE()) AND YEAR(access_time)=YEAR(CURDATE())");
        $kpis['acessos_mes'] = (int)$r->fetch_row()[0];

        $r = $this->db->query("SELECT HOUR(access_time) h, COUNT(*) c FROM access_logs WHERE DATE(access_time)=CURDATE() GROUP BY h ORDER BY c DESC LIMIT 1");
        if ($row = $r->fetch_assoc()) {
            $kpis['hora_pico'] = $row['h'] . ':00 (' . $row['c'] . ' acessos)';
        }

        $r = $this->db->query("SELECT s.class_name, COUNT(*) c FROM access_logs al JOIN students s ON al.student_id=s.id WHERE DATE(al.access_time)=CURDATE() AND s.class_name IS NOT NULL GROUP BY s.class_name ORDER BY c DESC LIMIT 1");
        if ($row = $r->fetch_assoc()) {
            $kpis['turma_top'] = $row['class_name'] . ' (' . $row['c'] . ' acessos)';
        }

        return $kpis;
    }

    private function fetchAcessosPorTurma(): array
    {
        $sql = "SELECT s.class_name,
                       COUNT(DISTINCT s.id) AS total_alunos,
                       COUNT(al.id)         AS acessos_hoje
                FROM students s
                LEFT JOIN access_logs al ON al.student_id = s.id AND DATE(al.access_time) = CURDATE()
                GROUP BY s.class_name
                ORDER BY acessos_hoje DESC";
        $r = $this->db->query($sql);
        return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    }

    private function fetchFluxoPorHora(): array
    {
        $fluxo = [];
        for ($h = 7; $h <= 18; $h++) {
            $fluxo[$h] = ['hora' => $h, 'entradas' => 0, 'saidas' => 0];
        }

        $sql = "SELECT HOUR(access_time) h, type, COUNT(*) c
                FROM access_logs
                WHERE DATE(access_time) = CURDATE()
                GROUP BY h, type";
        $r = $this->db->query($sql);
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $h = (int)$row['h'];
                if (isset($fluxo[$h])) {
                    if ($row['type'] === 'entry')
                        $fluxo[$h]['entradas'] = (int)$row['c'];
                    else
                        $fluxo[$h]['saidas'] = (int)$row['c'];
                }
            }
        }
        return array_values($fluxo);
    }

    private function fetchAlunos(): array
    {
        $sql = "SELECT s.*, 
                       (SELECT COUNT(*) FROM access_logs al WHERE al.student_id = s.id) AS total_acessos
                FROM students s
                ORDER BY s.name ASC";
        $r = $this->db->query($sql);
        return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    }

    private function fetchAlunosAtivos(): array
    {
        $r = $this->db->query("SELECT id, name, registration_number FROM students WHERE status='active' ORDER BY name ASC");
        return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    }

    private function fetchHistoricoAcessos(): array
    {
        $sql = "SELECT al.id, s.name, s.class_name, al.type, al.access_time
                FROM access_logs al
                JOIN students s ON al.student_id = s.id
                ORDER BY al.access_time DESC
                LIMIT 2000";
        $r = $this->db->query($sql);
        return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    }

    private function fetchUltimosDias(int $n): array
    {
        $dias = [];
        for ($i = $n - 1; $i >= 0; $i--) {
            $dias[] = date('Y-m-d', strtotime("-{$i} days"));
        }
        return $dias;
    }

    private function fetchFrequencia(): array
    {
        $sql = "SELECT student_id, data, status FROM attendance_logs ORDER BY data DESC LIMIT 5000";
        $r = $this->db->query($sql);
        return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    }
}
