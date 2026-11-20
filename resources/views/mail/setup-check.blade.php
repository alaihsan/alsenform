<x-mail::message>
# Email {{ config('app.name') }} sudah tersambung

Email ini dikirim dari **{{ $senderAddress }}** oleh perintah `php artisan mail:gmail`.

Mulai sekarang, tautan atur ulang kata sandi dikirim dari alamat itu.

Salam,<br>
{{ config('app.name') }}
</x-mail::message>
