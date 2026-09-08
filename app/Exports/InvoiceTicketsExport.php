<?php

namespace App\Exports;

use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\TicketStage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class InvoiceTicketsExport implements WithMultipleSheets
{
    public function __construct(private Invoice $invoice) {}

    public function sheets(): array
    {
        return [
            new InvoiceTicketsSheet($this->invoice),
            new InvoiceSummarySheet($this->invoice),
        ];
    }
}

class InvoiceTicketsSheet implements FromArray, WithColumnFormatting, WithEvents, WithTitle
{
    public function __construct(private Invoice $invoice) {}

    public function array(): array
    {
        $closedStageId = TicketStage::query()->where('system_key', 'closed')->value('id');
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        $rows = [[
            'Numero ticket',
            'Link admin',
            'Link utente',
            'Azienda',
            'Categoria',
            'Tipologia',
            'Data apertura',
            'Descrizione',
            'Data chiusura',
            'Aperto da',
        ]];

        $this->invoice->tickets()
            ->with([
                'company:id,name',
                'ticketType.category:id,name',
                'user:id,name,surname,is_admin,is_superadmin,is_company_admin',
                'statusUpdates' => function ($query): void {
                    $query->where('type', 'closing')->orderBy('created_at', 'desc');
                },
            ])
            ->orderBy('created_at')
            ->get()
            ->each(function (Ticket $ticket) use (&$rows, $closedStageId, $frontendUrl): void {
                $closingUpdate = $ticket->stage_id === $closedStageId
                    ? $ticket->statusUpdates->first()
                    : null;

                $rows[] = [
                    $ticket->id,
                    $frontendUrl.'/support/admin/ticket/'.$ticket->id,
                    $frontendUrl.'/support/user/ticket/'.$ticket->id,
                    $ticket->company?->name,
                    $ticket->ticketType?->category?->name,
                    $ticket->ticketType?->name,
                    $ticket->created_at,
                    $this->sanitizeCellText($ticket->description),
                    $closingUpdate?->created_at,
                    $this->ticketAuthorLabel($ticket),
                ];
            });

        return $rows;
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_DATE_DATETIME,
            'H' => NumberFormat::FORMAT_TEXT,
            'I' => NumberFormat::FORMAT_DATE_DATETIME,
            'J' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:J1')->getFont()->setBold(true);
                $sheet->getStyle('H')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle('A:J')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getColumnDimension('A')->setWidth(16);
                $sheet->getColumnDimension('B')->setWidth(54);
                $sheet->getColumnDimension('C')->setWidth(54);
                $sheet->getColumnDimension('D')->setWidth(32);
                $sheet->getColumnDimension('E')->setWidth(24);
                $sheet->getColumnDimension('F')->setWidth(32);
                $sheet->getColumnDimension('G')->setWidth(22);
                $sheet->getColumnDimension('H')->setWidth(80);
                $sheet->getColumnDimension('I')->setWidth(22);
                $sheet->getColumnDimension('J')->setWidth(34);

                foreach (['B', 'C'] as $column) {
                    for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
                        $url = $sheet->getCell($column.$row)->getValue();
                        if ($url) {
                            $sheet->getCell($column.$row)->setValueExplicit($url, DataType::TYPE_STRING);
                            $sheet->getCell($column.$row)->getHyperlink()->setUrl($url);
                        }
                    }
                }
            },
        ];
    }

    public function title(): string
    {
        return $this->worksheetTitle('Ticket fattura '.$this->invoice->number);
    }

    private function worksheetTitle(string $title): string
    {
        return Str::limit(preg_replace('/[\\\\\/\?\*\:\[\]]+/', '_', $title) ?: 'Ticket fattura', 31, '');
    }

    private function ticketAuthorLabel(Ticket $ticket): string
    {
        $user = $ticket->user;

        if (! $user || $user->is_admin || $user->is_superadmin) {
            return 'Supporto';
        }

        return trim($user->id.' - '.$user->name.' '.$user->surname);
    }

    private function sanitizeCellText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return str_replace(["\r\n", "\r"], "\n", $value);
    }
}

class InvoiceSummarySheet implements FromArray, WithColumnFormatting, WithEvents, WithTitle
{
    public function __construct(private Invoice $invoice) {}

    public function array(): array
    {
        return [
            ['Campo', 'Valore'],
            ['Numero', $this->invoice->number],
            ['Azienda', $this->invoice->company?->name],
            ['Data emissione', $this->invoice->invoice_date?->toDateString()],
            ['Descrizione', $this->invoice->description],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B4' => NumberFormat::FORMAT_DATE_DDMMYYYY,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:B1')->getFont()->setBold(true);
                $sheet->getStyle('B')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getColumnDimension('A')->setWidth(22);
                $sheet->getColumnDimension('B')->setWidth(80);
            },
        ];
    }

    public function title(): string
    {
        return $this->worksheetTitle('Fattura '.$this->invoice->number);
    }

    private function worksheetTitle(string $title): string
    {
        return Str::limit(preg_replace('/[\\\\\/\?\*\:\[\]]+/', '_', $title) ?: 'Fattura', 31, '');
    }
}
