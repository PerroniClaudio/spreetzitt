@component('mail::message', ['previewText' => 'Promemoria Fatturazione - Riepilogo contatori'])

<h2>Promemoria Fatturazione - Spreetzitt</h2>

<p>Buongiorno,</p>

<p>Di seguito il riepilogo dei contatori di fatturazione che richiedono attenzione:</p>
    
<div style="background-color: #f8f9fa; padding: 15px; border-left: 4px solid #e74c3c; margin-bottom: 15px;">
<h4 style="color: #2c3e50; margin-top: 0;">Fatturabilità Mancante</h4>
<div style="display: flex; justify-content: space-between; margin: 5px 0;">
    <span><strong>Totale:</strong></span>
    <span style="font-weight: bold; text-align: right; min-width: 60px;">{{ number_format($counters['billable_missing'] ?? 0, 0, ',', '.') }} ticket</span>
</div>
<div style="display: flex; justify-content: space-between; margin: 5px 0;">
    <span><strong>Di cui aperti:</strong></span>
    <span style="font-weight: bold; text-align: right; min-width: 60px;">{{ number_format($counters['open_billable_missing'] ?? 0, 0, ',', '.') }} ticket</span>
</div>
</div>

<div style="background-color: #f8f9fa; padding: 15px; border-left: 4px solid #f39c12; margin-bottom: 15px;">
<h4 style="color: #2c3e50; margin-top: 0;">Validazione Fatturazione Mancante</h4>
<div style="display: flex; justify-content: space-between; margin: 5px 0;">
    <span><strong>Totale:</strong></span>
    <span style="font-weight: bold; text-align: right; min-width: 60px;">{{ number_format($counters['billing_validation_missing'] ?? 0, 0, ',', '.') }} ticket</span>
</div>
<div style="display: flex; justify-content: space-between; margin: 5px 0;">
    <span><strong>Di cui aperti:</strong></span>
    <span style="font-weight: bold; text-align: right; min-width: 60px;">{{ number_format($counters['open_billing_validation_missing'] ?? 0, 0, ',', '.') }} ticket</span>
</div>
</div>

<div style="background-color: #f8f9fa; padding: 15px; border-left: 4px solid #3498db; margin-bottom: 15px;">
<h4 style="color: #2c3e50; margin-top: 0;">Non Ancora Fatturati</h4>
<div style="display: flex; justify-content: space-between; margin: 5px 0;">
    <span><strong>Totale:</strong></span>
    <span style="font-weight: bold; text-align: right; min-width: 60px;">{{ number_format($counters['billed_missing'] ?? 0, 0, ',', '.') }} ticket</span>
</div>
<div style="display: flex; justify-content: space-between; margin: 5px 0;">
    <span><strong>Di cui aperti:</strong></span>
    <span style="font-weight: bold; text-align: right; min-width: 60px;">{{ number_format($counters['open_billed_missing'] ?? 0, 0, ',', '.') }} ticket</span>
</div>
</div>

<div style="background-color: #f8f9fa; padding: 15px; border-left: 4px solid #9b59b6; margin-bottom: 15px;">
<h4 style="color: #2c3e50; margin-top: 0;">ID Fattura Mancante</h4>
<div style="display: flex; justify-content: space-between; margin: 5px 0;">
    <span><strong>Totale:</strong></span>
    <span style="font-weight: bold; text-align: right; min-width: 60px;">{{ number_format($counters['billed_bill_identification_missing'] ?? 0, 0, ',', '.') }} ticket</span>
</div>
<div style="display: flex; justify-content: space-between; margin: 5px 0;">
    <span><strong>Di cui aperti:</strong></span>
    <span style="font-weight: bold; text-align: right; min-width: 60px;">{{ number_format($counters['open_billed_bill_identification_missing'] ?? 0, 0, ',', '.') }} ticket</span>
</div>
</div>

<div style="background-color: #f8f9fa; padding: 15px; border-left: 4px solid #1abc9c; margin-bottom: 15px;">
<h4 style="color: #2c3e50; margin-top: 0;">Data Fattura Mancante</h4>
<div style="display: flex; justify-content: space-between; margin: 5px 0;">
    <span><strong>Totale:</strong></span>
    <span style="font-weight: bold; text-align: right; min-width: 60px;">{{ number_format($counters['billed_bill_date_missing'] ?? 0, 0, ',', '.') }} ticket</span>
</div>
<div style="display: flex; justify-content: space-between; margin: 5px 0;">
    <span><strong>Di cui aperti:</strong></span>
    <span style="font-weight: bold; text-align: right; min-width: 60px;">{{ number_format($counters['open_billed_bill_date_missing'] ?? 0, 0, ',', '.') }} ticket</span>
</div>
</div>
</div>

<div style="margin: 20px 0; padding: 15px; background-color: #e8f4fd; border: 1px solid #3498db; border-radius: 4px;">
<h4 style="color: #2c3e50; margin-top: 0;">📊 Riepilogo</h4>
<p style="margin: 0;">
<strong>Totale ticket che richiedono attenzione:</strong> 
{{ number_format(
    ($counters['billable_missing'] ?? 0) + 
    ($counters['billing_validation_missing'] ?? 0) + 
    ($counters['billed_missing'] ?? 0) + 
    ($counters['billed_bill_identification_missing'] ?? 0) + 
    ($counters['billed_bill_date_missing'] ?? 0), 
    0, ',', '.'
) }}
</p>
<p style="margin: 5px 0 0 0;">
<strong>Di cui aperti:</strong> 
{{ number_format(
    ($counters['open_billable_missing'] ?? 0) + 
    ($counters['open_billing_validation_missing'] ?? 0) + 
    ($counters['open_billed_missing'] ?? 0) + 
    ($counters['open_billed_bill_identification_missing'] ?? 0) + 
    ($counters['open_billed_bill_date_missing'] ?? 0), 
    0, ',', '.'
) }}
</p>
</div>

<p>Si prega di accedere al sistema per gestire i ticket in sospeso.</p>

<p>
    Cordiali saluti,<br>
    <strong>Sistema Spreetzitt</strong>
</p>

<hr style="margin: 30px 0; border: none; border-top: 1px solid #ecf0f1;">

<p style="font-size: 12px; color: #7f8c8d; margin: 0;">
    Questa è una email automatica generata dal sistema. Data invio: {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}
</p>

@endcomponent