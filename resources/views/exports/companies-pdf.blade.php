<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Data Perusahaan</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #2563eb; padding-bottom: 10px; }
        .header h1 { font-size: 18px; color: #2563eb; margin: 0 0 4px; }
        .header p { font-size: 11px; color: #6b7280; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #2563eb; color: #ffffff; padding: 8px 6px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 7px 6px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
        tr:nth-child(even) { background-color: #f9fafb; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: 600; }
        .badge-active { background-color: #dcfce7; color: #166534; }
        .badge-inactive { background-color: #f3f4f6; color: #374151; }
        .badge-suspended { background-color: #fee2e2; color: #991b1b; }
        .footer { text-align: right; margin-top: 15px; font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ app_name() }}</h1>
        <p>Laporan Data Perusahaan &mdash; {{ now()->format('d F Y, H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 8%;">Kode</th>
                <th style="width: 18%;">Nama Perusahaan</th>
                <th style="width: 13%;">Email</th>
                <th style="width: 9%;">Telepon</th>
                <th style="width: 12%;">Nama PIC</th>
                <th style="width: 13%;">Email PIC</th>
                <th style="width: 9%;">Telp PIC</th>
                <th style="width: 6%;">User</th>
                <th style="width: 8%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($companies as $index => $company)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><strong>{{ $company->code }}</strong></td>
                    <td>{{ $company->name }}</td>
                    <td>{{ $company->email ?? '-' }}</td>
                    <td>{{ $company->phone ?? '-' }}</td>
                    <td>{{ $company->pic_name ?? '-' }}</td>
                    <td>{{ $company->pic_email ?? '-' }}</td>
                    <td>{{ $company->pic_phone ?? '-' }}</td>
                    <td>{{ $company->users_count }}</td>
                    <td>
                        @php
                            $badgeClass = match($company->status->value) {
                                'active' => 'badge-active',
                                'inactive' => 'badge-inactive',
                                'suspended' => 'badge-suspended',
                                default => 'badge-inactive',
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">
                            {{ $company->status->label() }}
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
