<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Data User</title>
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
        .badge-inactive { background-color: #fee2e2; color: #991b1b; }
        .footer { text-align: right; margin-top: 15px; font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ app_name() }}</h1>
        <p>Laporan Data User &mdash; {{ now()->format('d F Y, H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 20%;">Nama</th>
                <th style="width: 20%;">Email</th>
                <th style="width: 10%;">Telepon</th>
                <th style="width: 10%;">Role</th>
                <th style="width: 18%;">Perusahaan</th>
                <th style="width: 8%;">Status</th>
                <th style="width: 9%;">Tgl Dibuat</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $index => $user)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $user->name }}</strong>
                        @if($user->position)
                            <br><span style="color: #6b7280;">{{ $user->position }}</span>
                        @endif
                    </td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->phone ?? '-' }}</td>
                    <td>{{ ucfirst($user->getRoleNames()->join(', ') ?: 'No Role') }}</td>
                    <td>{{ $user->company->name ?? '-' }}</td>
                    <td>
                        <span class="badge {{ $user->is_active ? 'badge-active' : 'badge-inactive' }}">
                            {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                    <td>{{ $user->created_at->format('d/m/Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Dicetak oleh: {{ auth()->user()->name }} &mdash; {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>
