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
          <h1 style="font-size: 22px;">Modulo di assegnazione hardware all'utente</h1>
        </td>
        <td style="text-align: right;">
          @if (isset($logo_url))
            {{-- Tra il logo dell'azienda cliente e quello del gestionale penso abbia più senso quello del gestionale, e si evitano problematiche di utilizzo di loghi altrui. --}}
            {{-- <img src="data:image;base64, {{ base64_encode(file_get_contents($logoUrl)) }}" alt="Company Logo" style="max-height: 100px; max-width: 200px;"> --}}
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
        {{-- <p style="font-size: 14px;"><b>ID:</b> {{ $hardware->company->id }}</p> --}}
        <p style="font-size: 14px;"><b>Denominazione:</b> {{ $hardware->company->name }}</p>
      </div>
    </div>

    <div class="box" style="margin-bottom: 8px; border-color: #44403c;">
        <p class="box-heading"><b>Hardware</b></p>
        <div>
          {{-- <p style="font-size: 14px;"><b>ID:</b> {{ $hardware->id }}</p> --}}
          <p style="font-size: 14px;"><b>Marca:</b> {{ $hardware->make }}</p>
          <p style="font-size: 14px;"><b>Modello:</b> {{ $hardware->model }}</p>
          <p style="font-size: 14px;"><b>Seriale:</b> {{ $hardware->serial_number }}</p>
          @if (isset($hardware->hardwareType))
            <p style="font-size: 14px;"><b>Tipo:</b> {{ $hardware->hardwareType->name }}</p>
          @endif
          @if (isset($hardware->company_asset_number))
            <p style="font-size: 14px;"><b>Cespite aziendale:</b> {{ $hardware->company_asset_number }}</p>
          @endif
          @if (isset($hardware->support_label))
            <p style="font-size: 14px;"><b>Identificativo:</b> {{ $hardware->support_label }}</p>
          @endif
          @if (isset($hardware->ownership_type))
            <p style="font-size: 14px;"><b>Proprietà:</b> {{ config('app.hardware_ownership_types')[$hardware->ownership_type] ?? $hardware->ownership_type }}</p>
          @endif
          @if (isset($hardware->ownership_type_note))
            <p style="font-size: 14px;"><b>Specifica proprietà:</b> {{ $hardware->ownership_type_note }}</p>
          @endif
          @if (isset($hardware->notes))
            <p style="font-size: 14px;"><b>Note:</b> {{ $hardware->notes }}</p>
          @endif
        </div>
    </div>
    <div class="box" style="margin-bottom: 8px; border-color: #44403c;">
        <p class="box-heading"><b>Utente</b></p>
        <div>
          {{-- <p style="font-size: 14px;"><b>ID:</b> {{ $user->id }}</p> --}}
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
            {{-- <p style="font-size: 14px;"><b>ID responsabile assegnazione:</b> {{ $relation->pivot->created_by }}</p> --}}
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