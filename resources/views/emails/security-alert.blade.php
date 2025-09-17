<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Avviso di Sicurezza</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: #dc3545;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .content {
            padding: 30px;
        }
        .alert-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        .attempt-details {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin: 10px 0;
        }
        .attempt-details h4 {
            margin-top: 0;
            color: #495057;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .details-table td {
            padding: 5px 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .details-table td:first-child {
            font-weight: bold;
            width: 30%;
        }
        .footer {
            padding: 20px;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
            border-top: 1px solid #dee2e6;
        }
        .timestamp {
            color: #6c757d;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 AVVISO DI SICUREZZA</h1>
            <p>Rilevati multipli tentativi di accesso falliti</p>
        </div>
        
        <div class="content">
            <div class="alert-box">
                <p><strong>⚠️ ATTENZIONE:</strong> Sono stati rilevati <strong>{{ $totalAttempts }}</strong> tentativi di accesso falliti per l'email <strong>{{ $email }}</strong> nelle ultime 24 ore.</p>
            </div>

            <h3>📊 Riepilogo dell'attività sospetta:</h3>
            <table class="details-table">
                <tr>
                    <td>Email interessata:</td>
                    <td>{{ $email }}</td>
                </tr>
                <tr>
                    <td>Numero totale tentativi:</td>
                    <td>{{ $totalAttempts }}</td>
                </tr>
                <tr>
                    <td>Periodo:</td>
                    <td>Ultime 24 ore</td>
                </tr>
                <tr>
                    <td>Data/Ora rilevazione:</td>
                    <td>{{ now()->format('d/m/Y H:i:s') }}</td>
                </tr>
            </table>

            <h3>🔍 Dettagli degli ultimi tentativi:</h3>
            @foreach($failedAttempts as $attempt)
                <div class="attempt-details">
                    <h4>Tentativo {{ $loop->iteration }}</h4>
                    <table class="details-table">
                        <tr>
                            <td>Data/Ora:</td>
                            <td>{{ $attempt->created_at->format('d/m/Y H:i:s') }}</td>
                        </tr>
                        <tr>
                            <td>Indirizzo IP:</td>
                            <td>{{ $attempt->ip_address }}</td>
                        </tr>
                        <tr>
                            <td>User Agent:</td>
                            <td>{{ Str::limit($attempt->user_agent, 100) ?? 'N/D' }}</td>
                        </tr>
                        <tr>
                            <td>Tipo tentativo:</td>
                            <td>
                                @switch($attempt->attempt_type)
                                    @case('invalid_credentials')
                                        Password errata
                                        @break
                                    @case('non_existent_user')
                                        Utente inesistente
                                        @break
                                    @case('unverified_user')
                                        Utente non verificato
                                        @break
                                    @case('disabled_user')
                                        Utente disabilitato
                                        @break
                                    @case('invalid_otp')
                                        OTP non valido
                                        @break
                                    @case('expired_otp')
                                        OTP scaduto
                                        @break
                                    @default
                                        {{ $attempt->attempt_type }}
                                @endswitch
                            </td>
                        </tr>
                    </table>
                </div>
            @endforeach

            <div class="alert-box">
                <h4>🛡️ Azioni consigliate:</h4>
                <ul>
                    <li>Verificare se questi tentativi sono legittimi</li>
                    <li>Considerare il blocco dell'IP se l'attività persiste</li>
                    <li>Informare l'utente se necessario</li>
                    <li>Monitorare ulteriori attività sospette</li>
                </ul>
            </div>
        </div>

        <div class="footer">
            <p>Questo è un messaggio automatico generato dal sistema di sicurezza di {{ config('app.name') }}.</p>
            <p class="timestamp">Generato il {{ now()->format('d/m/Y H:i:s') }}</p>
        </div>
    </div>
</body>
</html>