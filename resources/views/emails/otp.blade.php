@component('mail::message', ['previewText' => $code])
    <h2> Codice OTP </h2>
    <p>Utilizza il seguente codice per l'accesso al portale.</p>
    <h1>{{ $code }}</h1>
@endcomponent
