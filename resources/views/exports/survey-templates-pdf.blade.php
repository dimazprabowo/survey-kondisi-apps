<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Data Template Survey</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #2563eb; padding-bottom: 10px; }
        .header h1 { font-size: 18px; color: #2563eb; margin: 0 0 4px; }
        .header p { font-size: 11px; color: #6b7280; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #2563eb; color: #ffffff; padding: 8px 6px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 7px 6px; border-bottom: 1px solid #e5e7eb; font-size: 10px; vertical-align: top; }
        tr:nth-child(even) { background-color: #f9fafb; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: 600; }
        .badge-active { background-color: #dcfce7; color: #166534; }
        .badge-inactive { background-color: #f3f4f6; color: #374151; }
        .badge-default { background-color: #dbeafe; color: #1e40af; }
        .footer { text-align: right; margin-top: 15px; font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name', 'Boilerplate') }}</h1>
        <p>Laporan Data Template Survey &mdash; {{ now()->format('d F Y, H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 10%;">Kode</th>
                <th style="width: 22%;">Nama Template</th>
                <th style="width: 28%;">Deskripsi</th>
                <th style="width: 8%;">Kategori</th>
                <th style="width: 8%;">Survey</th>
                <th style="width: 8%;">Default</th>
                <th style="width: 12%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($templates as $index => $template)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><strong>{{ $template->code }}</strong></td>
                    <td>{{ $template->name }}</td>
                    <td>{{ $template->description ?? '-' }}</td>
                    <td>{{ $template->categories_count }}</td>
                    <td>{{ $template->surveys_count }}</td>
                    <td>
                        @if($template->is_default)
                            <span class="badge badge-default">Default</span>
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @php
                            $badgeClass = $template->is_active ? 'badge-active' : 'badge-inactive';
                        @endphp
                        <span class="badge {{ $badgeClass }}">
                            {{ $template->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Dicetak oleh: {{ auth()->user()->name }} &mdash; {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>
