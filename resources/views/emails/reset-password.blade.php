<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            padding: 40px 20px;
            line-height: 1.6;
            min-height: 100vh;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            padding: 50px 30px;
            text-align: center;
            color: #ffffff;
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 15s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ffffff 0%, #f0f0f0 100%);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 1;
        }
        
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 12px;
        }
        
        .header h1 {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 12px;
            letter-spacing: -1px;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.95;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .greeting {
            font-size: 22px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 24px;
        }
        
        .message {
            font-size: 16px;
            color: #4b5563;
            margin-bottom: 32px;
            line-height: 1.8;
        }
        
        .button-container {
            text-align: center;
            margin: 35px 0;
        }
        
        .reset-button {
            display: inline-block;
            padding: 18px 48px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 700;
            font-size: 17px;
            letter-spacing: 0.5px;
            box-shadow: 0 10px 30px rgba(37, 99, 235, 0.4);
            transition: all 0.3s ease;
            text-transform: uppercase;
        }
        
        .reset-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(37, 99, 235, 0.5);
        }
        
        .info-box {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 16px 20px;
            border-radius: 8px;
            margin: 25px 0;
        }
        
        .info-box p {
            font-size: 14px;
            color: #1e40af;
            margin: 0;
            line-height: 1.6;
        }
        
        .info-box p:first-child {
            margin-bottom: 8px;
        }
        
        .info-box strong {
            font-weight: 600;
        }
        
        .alternative-link {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #e5e7eb;
        }
        
        .alternative-link p {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 12px;
        }
        
        .link-box {
            background-color: #f9fafb;
            padding: 12px 16px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            word-break: break-all;
            font-size: 13px;
            color: #3b82f6;
            font-family: 'Courier New', monospace;
        }
        
        .footer {
            background-color: #f9fafb;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        
        .footer p {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        
        .footer-links {
            margin-top: 15px;
        }
        
        .footer-links a {
            color: #3b82f6;
            text-decoration: none;
            font-size: 13px;
            margin: 0 10px;
        }
        
        .footer-links a:hover {
            text-decoration: underline;
        }
        
        .security-notice {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-left: 4px solid #f59e0b;
            padding: 18px 22px;
            border-radius: 10px;
            margin: 25px 0;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.1);
        }
        
        .security-notice p {
            font-size: 14px;
            color: #92400e;
            margin: 0;
            line-height: 1.6;
        }
        
        .security-notice p:first-child {
            margin-bottom: 8px;
        }
        
        .security-notice strong {
            font-weight: 700;
            color: #78350f;
        }
        
        @media only screen and (max-width: 600px) {
            body {
                padding: 10px;
            }
            
            .content {
                padding: 30px 20px;
            }
            
            .header {
                padding: 30px 20px;
            }
            
            .header h1 {
                font-size: 20px;
            }
            
            .reset-button {
                padding: 14px 32px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <img src="{{ $message->embed(public_path('images/bki-logo.png')) }}" alt="BKI Logo" style="width: 100%; height: 100%; object-fit: contain; border-radius: 12px;">
            </div>
            <h1>Reset Password</h1>
            <p>{{ app_name() }}</p>
        </div>
        
        <!-- Content -->
        <div class="content">
            <div class="greeting">
                Halo, {{ $userName }}
            </div>
            
            <div class="message">
                Kami menerima permintaan untuk mereset password akun Anda di <strong>{{ app_name() }}</strong>.
                Jika Anda yang melakukan permintaan ini, silakan klik tombol di bawah untuk membuat password baru.
            </div>
            
            <div class="button-container">
                <a href="{{ $resetUrl }}" class="reset-button">Reset Password Saya</a>
            </div>
            
            <div class="info-box">
                <p><strong>⏱️ Link ini akan kedaluwarsa dalam 60 menit</strong></p>
                <p>Untuk keamanan akun Anda, pastikan untuk mereset password sebelum link kedaluwarsa.</p>
            </div>
            
            <div class="security-notice">
                <p><strong>🔒 Keamanan Akun</strong></p>
                <p>Jika Anda tidak melakukan permintaan reset password, abaikan email ini. Password Anda tetap aman dan tidak akan berubah.</p>
            </div>
            
            <div class="alternative-link">
                <p>Jika tombol di atas tidak berfungsi, salin dan tempel link berikut ke browser Anda:</p>
                <div class="link-box">
                    {{ $resetUrl }}
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p><strong>{{ app_name() }}</strong></p>
            <p style="margin-top: 15px; font-size: 12px;">
                Email ini dikirim secara otomatis, mohon tidak membalas email ini.
            </p>
            <div class="footer-links">
                <a href="#">Bantuan</a>
                <a href="#">Kebijakan Privasi</a>
                <a href="#">Hubungi Kami</a>
            </div>
            <p style="margin-top: 20px; font-size: 12px; color: #9ca3af;">
                © {{ date('Y') }} {{ app_name() }}. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
