<style>
    @page { margin: 28px 32px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; }
    h1, h2, h3 { margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    .head { border-bottom: 3px solid {{ $school['accent_color'] ?? '#E5A52B' }}; padding-bottom: 8px; margin-bottom: 10px; }
    .head td { vertical-align: middle; }
    .school { font-size: 17px; font-weight: bold; color: {{ $school['primary_color'] ?? '#102B50' }}; }
    .muted { color: #64748b; }
    .doc-title { text-align: center; background: {{ $school['primary_color'] ?? '#102B50' }}; color: #fff; padding: 5px; font-size: 12px; font-weight: bold; letter-spacing: 1px; margin: 8px 0; }
    table.grid th { background: {{ $school['secondary_color'] ?? '#246BCE' }}; color: #fff; padding: 5px 6px; text-align: left; font-size: 9px; }
    table.grid td { border-bottom: 1px solid #e2e8f0; padding: 4px 6px; }
    table.grid tr:nth-child(even) td { background: #f8fafc; }
    .r { text-align: right; } .c { text-align: center; }
    .box { border: 1px solid #cbd5e1; padding: 6px 8px; }
    .label { color: #64748b; font-size: 8px; text-transform: uppercase; }
    .total td { font-weight: bold; border-top: 2px solid {{ $school['primary_color'] ?? '#102B50' }}; }
    .sign { margin-top: 28px; }
    .sign td { width: 33%; text-align: center; padding-top: 22px; }
    .sign span { border-top: 1px solid #94a3b8; display: inline-block; width: 85%; padding-top: 3px; font-size: 8px; color: #64748b; }
    .badge { padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; }
    .paid { background: #dcfce7; color: #166534; } .unpaid { background: #fee2e2; color: #991b1b; } .partial { background: #fef3c7; color: #92400e; }
</style>
