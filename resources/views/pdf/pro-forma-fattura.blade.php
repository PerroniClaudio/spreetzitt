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
        <div>Periodo: {{ $bill->data_inizio }} - {{ $bill->data_fine }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID Ticket</th>
                <th>Categoria</th>
                <th>Tipo</th>
                <th>Tempo lavorazione (minuti)</th>
            </tr>
        </thead>
        <tbody>
        @foreach($tickets as $ticket)
            <tr>
                <td>{{ $ticket->id }}</td>
                <td>{{ $ticket->ticketType->category->name ?? '-' }}</td>
                <td>{{ $ticket->ticketType->name ?? '-' }}</td>
                <td>{{ $ticket->actual_processing_time ?? '-' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="footer">
        Documento generato automaticamente da Spreetzitt - {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
