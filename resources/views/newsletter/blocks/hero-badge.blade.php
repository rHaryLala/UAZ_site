{{-- Badge rond de « Photo en fond » (un tampon « Nouveau », « Rentrée 2026 »…). Les messageries qui ne savent pas arrondir (Outlook) le montrent carré : il reste lisible. --}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="{{ ($align ?? 'left') === 'center' ? 'center' : 'right' }}" style="display:inline-table;">
    <tr>
        <td width="96" height="96" align="center" valign="middle" bgcolor="{{ $badgeBg }}"
            style="width:96px;height:96px;border-radius:50%;background:{{ $badgeBg }};padding:8px;font-family:{{ $headingFont }};font-size:14px;font-weight:700;line-height:1.15;text-transform:uppercase;letter-spacing:.04em;color:{{ $badgeInk }};text-align:center;">{{ $d['badge'] }}</td>
    </tr>
</table>
