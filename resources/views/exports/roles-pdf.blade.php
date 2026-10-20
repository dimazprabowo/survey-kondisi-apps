<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Data Roles & Permissions</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #2563eb; padding-bottom: 10px; }
        .header h1 { font-size: 18px; color: #2563eb; margin: 0 0 4px; }
        .header p { font-size: 11px; color: #6b7280; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #2563eb; color: #ffffff; padding: 8px 6px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 7px 6px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
        tr:nth-child(even) { background-color: #f9fafb; }
        .permission-tag { display: inline-block; padding: 1px 6px; margin: 1px 2px; background-color: #eff6ff; color: #1e40af; border-radius: 4px; font-size: 8px; }
        .footer { text-align: right; margin-top: 15px; font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ app_name() }}</h1>
        <p>Laporan Roles & Permissions &mdash; {{ now()->format('d F Y, H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 15%;">Nama Role</th>
                <th style="width: 10%;">Jml Permission</th>
                <th style="width: 60%;">Permissions</th>
                <th style="width: 10%;">Jml User</th>
            </tr>
        </thead>
        <tbody>
            @foreach($roles as $index => $role)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><strong>{{ ucfirst($role->name) }}</strong></td>
                    <td>{{ $role->permissions->count() }}</td>
                    <td>
                        @foreach($role->permissions as $permission)
                            <span class="permission-tag">{{ str_replace('_', ' ', ucfirst($permission->name)) }}</span>
                        @endforeach
                        @if($role->permissions->isEmpty())
                            <span style="color: #9ca3af;">-</span>
                        @endif
                    </td>
                    <td>{{ $role->users()->count() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Dicetak oleh: {{ auth()->user()->name }} &mdash; {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>
