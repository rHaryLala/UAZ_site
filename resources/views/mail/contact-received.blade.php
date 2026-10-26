Nouveau message reçu via le formulaire de contact du site.

Nom : {{ $contact->name }}
E-mail : {{ $contact->email }}
@if ($contact->phone)
Téléphone : {{ $contact->phone }}
@endif
Sujet : {{ $contact->subject }}

{{ $contact->message }}

--
Message n° {{ $contact->id }} — consultable dans le back-office (Communauté › Messages de contact).
