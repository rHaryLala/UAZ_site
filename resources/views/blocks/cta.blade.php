@php
    $url = $data['button_url'] ?? '#';
    // Seuls les chemins du site et les adresses http(s)/mailto/tel sont autorisés (pas de javascript:).
    $safeUrl = preg_match('#^(/|https?://|mailto:|tel:)#i', $url) ? $url : '#';
@endphp

<x-cta-banner :title="$data['title'] ?? ''" :text="$data['text'] ?? null" :primary="['label' => $data['button_label'] ?? 'En savoir plus', 'url' => $safeUrl]" />
