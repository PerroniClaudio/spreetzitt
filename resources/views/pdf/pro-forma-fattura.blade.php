@php($company = $bill->company)
<html>
<head>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; }
        .header { text-align: center; margin-bottom: 30px; }
        .logo { max-width: 180px; margin-bottom: 10px; }
        .footer { position: fixed; bottom: 20px; left: 0; right: 0; text-align: center; color: #888; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
    <div class="header">
        @if($company->logo_path ?? false)
            <img src="{{ public_path($company->logo_path) }}" class="logo" alt="Logo">
        @else
            <h2>{{ $company->name }}</h2>
        @endif
        <h3>Pro-forma Fattura</h3>
        <div>Periodo: {{ $bill->start_date }} - {{ $bill->end_date }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID Ticket</th>
                <th>Categoria</th>
                <th>Tipo</th>
                <th>Tempo lavorazione (minuti)</th>
                <th>Costo (€)</th>
            </tr>
        </thead>
        <tbody>
        <?php $totale = 0; ?>
        @foreach($tickets as $ticket)
            <?php
                $minuti = $ticket->actual_processing_time ?? 0;
                $costo_orario = $ticket->ticketType->hourly_cost ?? 0;
                $costo = round(($minuti / 60) * $costo_orario, 2);
                $totale += $costo;
            ?>
            <tr>
                <td>{{ $ticket->id }}</td>
                <td>{{ $ticket->ticketType->category->name ?? '-' }}</td>
                <td>{{ $ticket->ticketType->name ?? '-' }}</td>
                <td>{{ $minuti }}</td>
                <td>{{ number_format($costo, 2, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" style="text-align:right">Totale</th>
                <th>{{ number_format($totale, 2, ',', '.') }} €</th>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Documento generato automaticamente da Spreetzitt - {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
