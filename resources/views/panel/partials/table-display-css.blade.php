{{-- Styles of the printable table display (A6 card). Shared by the print page and the panel preview. --}}
<style>
    .td { position: relative; box-sizing: border-box; width: 105mm; height: 148mm; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3.5mm; padding: 8mm 10mm 14mm; text-align: center; font-family: 'DM Sans', system-ui, sans-serif; background: var(--td-bg, #fff); color: var(--td-fg, #1a1815); overflow: hidden; }
    .td * { box-sizing: border-box; }
    .td-logo { width: 17mm; height: 17mm; border-radius: 50%; object-fit: cover; }
    .td-name { margin: 0; font-weight: 400; font-size: 20pt; line-height: 1.1; font-family: var(--td-font, 'DM Serif Display'), serif; }
    .td-qr { width: 58mm; height: 58mm; border-radius: 3mm; overflow: hidden; }
    .td-qr svg { display: block; width: 100%; height: 100%; }
    .td-cta { margin: 0; font-weight: 700; font-size: 11pt; line-height: 1.25; font-family: var(--td-font, 'DM Sans'), system-ui, sans-serif; }
    .td-sub { margin: 0; font-size: 9pt; line-height: 1.3; opacity: .65; }
    /* Table number: a tag in the top corner, so it never crowds the content or our logo at the bottom. */
    .td-table { position: absolute; top: 4mm; right: 4mm; padding: 1.2mm 3.2mm; border-radius: 999px; font-weight: 700; font-size: 10pt; line-height: 1.2; background: var(--td-tag-bg, var(--td-accent)); color: var(--td-tag-fg, var(--td-on-accent)); }
    .td-brand { position: absolute; left: 0; right: 0; bottom: 3.5mm; display: flex; align-items: center; justify-content: center; gap: 1.6mm; opacity: .6; }
    .td-brand img { display: block; height: 5.5mm; width: auto; filter: var(--td-brand-filter, none); }
    .td-brand span { font-family: 'Imperial Script', cursive; font-size: 13pt; line-height: 1; }


    /* Clássico: white card with a colored band on top. */
    .td--classico { border-top: 6mm solid var(--td-accent); }
    .td--classico .td-table { top: 9mm; }

    /* Moldura colorida: the whole card in the restaurant color, the QR on a white panel. */
    .td--moldura .td-qr { width: 62mm; height: 62mm; padding: 3.5mm; background: #fff; border-radius: 5mm; overflow: visible; }
    .td--moldura .td-logo { box-shadow: 0 0 0 1.2mm #fff; }

    /* Escuro: dark card, the QR on a white panel, the color only as a detail. */
    .td--escuro { border-top: 3mm solid var(--td-accent); }
    .td--escuro .td-table { top: 7mm; }
    .td--escuro .td-qr { width: 62mm; height: 62mm; padding: 3.5mm; background: #fff; border-radius: 5mm; overflow: visible; }
    .td--escuro .td-logo { box-shadow: 0 0 0 0.8mm var(--td-accent); }
</style>
