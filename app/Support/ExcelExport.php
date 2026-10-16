<?php

namespace App\Support;

use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Builds a real Excel (.xlsx) download: real dates and numbers Excel can sort and add up, columns wide
 * enough for their contents (no "########"), a bold header that stays on screen, and a Total row.
 */
class ExcelExport
{
    /** Excel number format per column type. */
    private const FORMATS = [
        'date' => 'mmm d, yyyy (ddd)',
        'time' => 'h:mm AM/PM',
        'int' => '#,##0',
        'decimal' => '#,##0.00',
        'money' => '#,##0.00;-#,##0.00',
        'rate' => '#,##0.00',
    ];

    /** Types summed in the Total row. */
    private const SUMMED = ['int', 'decimal', 'money'];

    /**
     * @param  list<array{0: string, 1: string}>  $columns  [header, type]; type is text, date, time, int, decimal, money or rate (a number left out of the Total row)
     * @param  list<list<mixed>>  $rows  one value per column; money is in pesos, dates are DateTimeInterface
     * @param  list<bool>|null  $countsInTotal  per row: include it in the Total row (null = every row)
     */
    public function __construct(
        private readonly string $sheetName,
        private readonly array $columns,
        private readonly array $rows,
        private readonly ?array $countsInTotal = null,
    ) {}

    public function download(string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'frt-xlsx-');
        $this->writeTo($path);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    public function writeTo(string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName(mb_substr($this->sheetName, 0, 31));
        $sheet->setSheetView((new SheetView)->setFreezeRow(2));

        $lastColumn = count($this->columns) - 1;
        $sheet->setAutoFilter(new AutoFilter(0, 1, $lastColumn, max(1, count($this->rows) + 1)));

        foreach ($this->columns as $index => [$header, $type]) {
            $sheet->setColumnWidth($this->width($index), $index + 1);
        }

        $headerStyle = (new Style)->setFontBold()->setBackgroundColor('DBEAFE')->setShouldWrapText()
            ->setCellVerticalAlignment('center');
        $writer->addRow(Row::fromValues(array_column($this->columns, 0), $headerStyle));

        foreach ($this->rows as $row) {
            $writer->addRow($this->row($row));
        }

        $writer->addRow($this->totalRow());
        $writer->close();
    }

    /**
     * @param  list<mixed>  $values
     */
    private function row(array $values, bool $bold = false): Row
    {
        $cells = [];

        foreach ($this->columns as $index => [, $type]) {
            $value = $values[$index] ?? null;
            $style = new Style;

            if ($bold) {
                $style->setFontBold()->setBackgroundColor('F1F5F9');
            }
            if (isset(self::FORMATS[$type]) && $value !== null && $value !== '') {
                $style->setFormat(self::FORMATS[$type]);
            }
            if ($type === 'date' || $type === 'time') {
                $style->setCellAlignment(CellAlignment::LEFT);
            }

            $cells[] = Cell::fromValue($this->cellValue($value, $type), $style);
        }

        return new Row($cells);
    }

    private function totalRow(): Row
    {
        $totals = ['Total'];

        foreach ($this->columns as $index => [, $type]) {
            if ($index === 0) {
                continue;
            }
            if (! in_array($type, self::SUMMED, true)) {
                $totals[] = null;

                continue;
            }

            $sum = 0;
            foreach ($this->rows as $rowIndex => $row) {
                if ($this->countsInTotal === null || $this->countsInTotal[$rowIndex]) {
                    $sum += (float) ($row[$index] ?? 0);
                }
            }
            $totals[] = $type === 'int' ? (int) $sum : round($sum, 2);
        }

        if ($this->countsInTotal !== null && in_array(false, $this->countsInTotal, true)) {
            $totals[0] = 'Total (valid only)';
        }

        // The first column may be a date; the Total label is text.
        $row = $this->row($totals, bold: true);
        $row->getCellAtIndex(0)?->getStyle()->setFormat('@');

        return $row;
    }

    private function cellValue(mixed $value, string $type): null|bool|DateTimeInterface|float|int|string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match (true) {
            $value instanceof DateTimeInterface => $value,
            $type === 'int' && is_numeric($value) => (int) $value,
            in_array($type, ['decimal', 'money', 'rate'], true) && is_numeric($value) => round((float) $value, 2),
            default => (string) $value,
        };
    }

    /** Wide enough for the longest value as Excel shows it (header text may wrap onto two lines). */
    private function width(int $index): float
    {
        [$header, $type] = $this->columns[$index];
        $longest = 0;

        foreach ($this->rows as $row) {
            $longest = max($longest, mb_strlen($this->shown($row[$index] ?? null, $type)));
        }

        // Short headers sit on one line; long ones get room for two full lines of bold text.
        $headerWidth = mb_strlen($header) <= 22 ? mb_strlen($header) : (int) ceil(mb_strlen($header) / 2) + 6;
        $headerWidth = max($headerWidth, ...array_map(mb_strlen(...), explode(' ', $header)));

        return min(60, max(8, $longest + 3, $headerWidth + 3, $type === 'date' ? 20 : 0));
    }

    private function shown(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match (true) {
            $value instanceof DateTimeInterface => $type === 'time' ? $value->format('h:i A') : $value->format('M j, Y (D)'),
            in_array($type, ['decimal', 'money', 'rate'], true) && is_numeric($value) => number_format((float) $value, 2),
            $type === 'int' && is_numeric($value) => number_format((int) $value),
            default => (string) $value,
        };
    }
}
