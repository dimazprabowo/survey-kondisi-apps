<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Email</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7fa;
            padding: 20px;
            line-height: 1.6;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            padding: 40px 30px;
            text-align: center;
            color: #ffffff;
        }
        
        .logo {
            width: 60px;
            height: 60px;
            background-color: #ffffff;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.95;
            font-weight: 400;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 20px;
        }
        
        .message {
            font-size: 15px;
            color: #4b5563;
            margin-bottom: 30px;
            line-height: 1.7;
        }
        
        .button-container {
            text-align: center;
            margin: 35px 0;
        }
        
        .verify-button {
            display: inline-block;
            padding: 16px 40px;
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
            transition: all 0.3s ease;
        }
        
        .verify-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(5, 150, 105, 0.4);
        }
        
        .info-box {
            background-color: #ecfdf5;
            border-left: 4px solid #10b981;
            padding: 16px 20px;
            border-radius: 8px;
            margin: 25px 0;
        }
        
        .info-box p {
            font-size: 14px;
            color: #065f46;
            margin: 0;
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
            color: #10b981;
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
            color: #10b981;
            text-decoration: none;
            font-size: 13px;
            margin: 0 10px;
        }
        
        .footer-links a:hover {
            text-decoration: underline;
        }
        
        .benefits {
            background-color: #f0fdf4;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
        }
        
        .benefits h3 {
            font-size: 16px;
            color: #065f46;
            margin-bottom: 12px;
            font-weight: 600;
        }
        
        .benefits ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .benefits li {
            font-size: 14px;
            color: #047857;
            padding: 6px 0;
            padding-left: 24px;
            position: relative;
        }
        
        .benefits li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #10b981;
            font-weight: bold;
            font-size: 16px;
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
            
            .verify-button {
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
                <img src="{{ $message->embed(public_path('images/bki-logo.png')) }}" alt="BKI Logo" style="width: 100%; height: 100%; object-fit: contain;">
            </div>
            <h1>Verifikasi Email Anda</h1>
            <p>{{ app_name() }}</p>
        </div>
        
        <!-- Content -->
        <div class="content">
            <div class="greeting">
                Halo, {{ $userName }}
            </div>
            
            <div class="message">
                Terima kasih telah mendaftar di <strong>{{ app_name() }}</strong>! 
                Untuk melanjutkan, kami perlu memverifikasi alamat email Anda. 
                Silakan klik tombol di bawah untuk mengkonfirmasi bahwa alamat email ini milik Anda.
            </div>
            
            <div class="button-container">
                <a href="{{ $verificationUrl }}" class="verify-button">Verifikasi Email Saya</a>
            </div>
            
            <div class="benefits">
                <h3>Setelah verifikasi, Anda dapat:</h3>
                <ul>
                    <li>Mengakses dashboard aplikasi</li>
                    <li>Mengelola profil dan pengaturan akun</li>
                    <li>Menggunakan seluruh fitur yang tersedia</li>
                </ul>
            </div>
            
            <div class="info-box">
                <p><strong>⏱️ Link ini akan kedaluwarsa dalam 60 menit</strong></p>
                <p>Untuk keamanan akun Anda, pastikan untuk verifikasi email sebelum link kedaluwarsa.</p>
            </div>
            
            <div class="alternative-link">
                <p>Jika tombol di atas tidak berfungsi, salin dan tempel link berikut ke browser Anda:</p>
                <div class="link-box">
                    {{ $verificationUrl }}
                </div>
            </div>
            
            <div class="message" style="margin-top: 30px; padding-top: 25px; border-top: 1px solid #e5e7eb;">
                <p style="font-size: 13px; color: #6b7280;">
                    Jika Anda tidak membuat akun di {{ app_name() }}, abaikan email ini.
                </p>
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
