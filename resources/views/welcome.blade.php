<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supernova Management Software</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #111827;
            color: #f3f4f6;
        }
        .container {
            text-align: center;
            padding: 2rem;
        }
        h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            background: linear-gradient(to right, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        p {
            font-size: 1.25rem;
            color: #9ca3af;
            margin-bottom: 2rem;
        }
        .buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        a {
            display: inline-block;
            padding: 0.75rem 2rem;
            color: white;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
            min-width: 150px;
        }
        .admin-btn {
            background-color: #3b82f6;
        }
        .admin-btn:hover {
            background-color: #2563eb;
        }
        .mobile-btn {
            background-color: #10b981;
        }
        .mobile-btn:hover {
            background-color: #059669;
        }
        .mobile-icon {
            display: inline-block;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Supernova Management</h1>
        <p>Sistema completo per la gestione aziendale e assemblaggio elettronico</p>
        <div class="buttons">
            <a href="/admin" class="admin-btn">
                <span class="mobile-icon">💻</span>
                Pannello Admin
            </a>
            <a href="/mobile" class="mobile-btn">
                <span class="mobile-icon">📱</span>
                App Mobile Scanner
            </a>
        </div>
        <p style="margin-top: 2rem; font-size: 0.875rem; color: #6b7280;">
            <strong>App Mobile:</strong> Scanner ArUco, gestione inventario, checklist assemblaggio<br>
            <strong>Admin Panel:</strong> Gestione completa sistema, configurazione, reports
        </p>
    </div>
</body>
</html>