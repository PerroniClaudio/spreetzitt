<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>

@include('components.style')

<body>
    <table width="100%">
      <tr>
        <td>
          <h1 style="font-size: 22px;">Modulo di assegnazione software all'utente</h1>
        </td>
        <td style="text-align: right;">
          @if (isset($logo_url) && !empty($logo_url) && file_exists($logo_url))
            <img src="data:image/png;base64,{{ base64_encode(file_get_contents($logo_url)) }}"
              alt="iftlogo" 
              style="max-height: 100px; max-width: 200px;"
            >
          @endif
        </td>
      </tr>
    </table>
    <hr>

    <div class="box" style="margin-bottom: 8px; border-color: #44403c;">
      <p class="box-heading"><b>{{ \App\Models\TenantTerm::getCurrentTenantTerm('azienda', 'Azienda') }}</b></p>
      <div>
        <p style="font-size: 14px;"><b>Denominazione:</b> {{ $software->company->name }}</p>
      </div>
    </div>

    <div class="box" style="margin-bottom: 8px; border-color: #44403c;">
        <p class="box-heading"><b>Software</b></p>
        <div>
          <p style="font-size: 14px;"><b>Fornitore:</b> {{ $software->vendor }}</p>
          <p style="font-size: 14px;"><b>Nome prodotto:</b> {{ $software->product_name }}</p>
          @if (isset($software->version))
            <p style="font-size: 14px;"><b>Versione:</b> {{ $software->version }}</p>
          @endif
          @if (isset($software->softwareType))
            <p style="font-size: 14px;"><b>Tipo:</b> {{ $software->softwareType->name }}</p>
          @endif
          @if (isset($software->activation_key))
            <p style="font-size: 14px;"><b>Chiave di attivazione:</b> {{ $software->activation_key }}</p>
          @endif
          @if (isset($software->company_asset_number))
            <p style="font-size: 14px;"><b>Cespite aziendale:</b> {{ $software->company_asset_number }}</p>
          @endif
          @if (isset($software->license_type))
            <p style="font-size: 14px;"><b>Tipo licenza:</b> {{ $software->license_type }}</p>
          @endif
          @if (isset($software->max_installations))
            <p style="font-size: 14px;"><b>Numero massimo installazioni:</b> {{ $software->max_installations }}</p>
          @endif
          @if (isset($software->purchase_date))
            <p style="font-size: 14px;"><b>Data d'acquisto:</b> {{ \Carbon\Carbon::parse($software->purchase_date)->format('d/m/Y') }}</p>
          @endif
          @if (isset($software->expiration_date))
            <p style="font-size: 14px;"><b>Data scadenza:</b> {{ \Carbon\Carbon::parse($software->expiration_date)->format('d/m/Y') }}</p>
          @endif
          @if (isset($software->support_expiration_date))
            <p style="font-size: 14px;"><b>Data scadenza supporto:</b> {{ \Carbon\Carbon::parse($software->support_expiration_date)->format('d/m/Y') }}</p>
          @endif
        </div>
    </div>
    
    <div class="box" style="margin-bottom: 8px; border-color: #44403c;">
        <p class="box-heading"><b>Utente</b></p>
        <div>
          <p style="font-size: 14px;"><b>Nome:</b> {{ $user->name }}</p>
          <p style="font-size: 14px;"><b>Cognome:</b> {{ $user->surname }}</p>
          <p style="font-size: 14px;"><b>Email:</b> {{ $user->email }}</p>
        </div>
    </div>
    
    <div class="box" style="margin-bottom: 8px; border-color: #44403c;">
        <p class="box-heading"><b>Dettaglio associazione</b></p>
        <div>
          @if (isset($relation))
            <p style="font-size: 14px;"><b>Assegnato in data:</b> {{ isset($relation->pivot->created_at) ? $relation->pivot->created_at->format('d/m/Y H:i') : '' }}</p>
            @php
              $responsibleUser = App\Models\User::find($relation->pivot->responsible_user_id);
            @endphp
            <p style="font-size: 14px;"><b>Responsabile assegnazione:</b> {{ $responsibleUser ? ($responsibleUser->name . ($responsibleUser->surname ? ' ' . $responsibleUser->surname : '')) : '' }}</p>
            
          @else
            <p>Associazione non trovata</p>
          @endif
        </div>
    </div>

    <div>
      <br>
      <p style="font-size: 14px;">
        Data: {{ now() ? now()->format('d/m/Y') : '' }}
      </p>
      <br>
      <p style="font-size: 14px;">
        {{ $user->name . ' ' . $user->surname }}: ______________________________________ 
      </p>
      <br>
      <p style="font-size: 14px;">
        {{ isset($responsibleUser) ? ($responsibleUser->name . ($responsibleUser->surname ? ' ' . $responsibleUser->surname : '') . ': ') : '' }}______________________________________ 
      </p>
    </div>

</body>

</html>
