<x-mail::message>
# Atur ulang kata sandi

Halo {{ $name }},

Ada permintaan untuk mengatur ulang kata sandi akun {{ config('app.name') }} Anda. Tekan tombol di bawah untuk membuat kata sandi baru.

<x-mail::button :url="$url">
Atur ulang kata sandi
</x-mail::button>

Tautan ini berlaku {{ $minutes }} menit. Jika Anda tidak memintanya, abaikan saja email ini: kata sandi Anda tidak berubah.

Salam,<br>
{{ config('app.name') }}

<x-slot:subcopy>
Jika tombol "Atur ulang kata sandi" tidak bisa ditekan, salin alamat ini ke browser: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>
