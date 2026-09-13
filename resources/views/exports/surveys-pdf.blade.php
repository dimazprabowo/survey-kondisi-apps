<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Data Survey Kondisi</title>
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
        .badge-draft { background-color: #f3f4f6; color: #374151; }
        .badge-in_progress { background-color: #fef3c7; color: #92400e; }
        .badge-completed { background-color: #dcfce7; color: #166534; }
        .badge-cancelled { background-color: #fee2e2; color: #991b1b; }
        .footer { text-align: right; margin-top: 15px; font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name', 'Boilerplate') }}</h1>
        <p>Laporan Data Survey Kondisi &mdash; {{ now()->format('d F Y, H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 12%;">Nomor Survey</th>
                <th style="width: 14%;">Kapal</th>
                <th style="width: 12%;">Template</th>
                <th style="width: 10%;">Tanggal Survey</th>
                <th style="width: 12%;">Surveyor</th>
                <th style="width: 12%;">Lokasi</th>
                <th style="width: 8%;">CAP Score</th>
                <th style="width: 8%;">Status</th>
                <th style="width: 8%;">Dibuat Oleh</th>
            </tr>
        </thead>
        <tbody>
            @foreach($surveys as $index => $survey)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><strong>{{ $survey->survey_number }}</strong></td>
                    <td>{{ $survey->ship?->name ?? '-' }}</td>
                    <td>{{ $survey->template?->name ?? '-' }}</td>
                    <td>{{ $survey->survey_date?->format('d M Y') ?? '-' }}</td>
                    <td>{{ $survey->surveyor ?? '-' }}</td>
                    <td>{{ $survey->location ?? '-' }}</td>
                    <td>{{ $survey->overall_cap_score !== null ? number_format((float) $survey->overall_cap_score, 2) : '-' }}</td>
                    <td>
                        @php
                            $badgeClass = match($survey->status->value) {
                                'draft' => 'badge-draft',
                                'in_progress' => 'badge-in_progress',
                                'completed' => 'badge-completed',
                                'cancelled' => 'badge-cancelled',
                                default => 'badge-draft',
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">
                            {{ $survey->status->label() }}
                        </span>
                    </td>
                    <td>{{ $survey->creator?->name ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Dicetak oleh: {{ auth()->user()->name }} &mdash; {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>
