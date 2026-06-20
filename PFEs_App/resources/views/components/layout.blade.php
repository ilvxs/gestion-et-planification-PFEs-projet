<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Gestion PFEs' }}</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f3f6fb;
            font-family: Arial, sans-serif;
        }

        .app-wrapper {
            min-height: 100vh;
            display: flex;
        }

        .sidebar {
            width: 250px;
            background: #0f172a;
            color: white;
            padding: 24px 18px;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
        }

        .sidebar .brand {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 28px;
        }

        .sidebar a {
            display: block;
            color: #cbd5e1;
            text-decoration: none;
            padding: 11px 12px;
            border-radius: 10px;
            margin-bottom: 8px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #2563eb;
            color: white;
        }

        .main-content {
            margin-left: 250px;
            width: calc(100% - 250px);
            padding: 32px;
        }

        .page-card {
            background: white;
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        }

        .step-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            background: #e0edff;
            color: #1d4ed8;
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 10px;
        }

        .table th {
            background: #f8fafc;
        }

        .btn-main {
            background: #2563eb;
            color: white;
        }

        .btn-main:hover {
            background: #1d4ed8;
            color: white;
        }

        @media (max-width: 768px) {
            .sidebar {
                position: static;
                width: 100%;
            }

            .app-wrapper {
                display: block;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <aside class="sidebar">
            <div class="brand">Gestion PFEs</div>

            <nav>
                <a href="{{ route('imports.index') }}" class="{{ request()->routeIs('imports.*') ? 'active' : '' }}">
                    Importation
                </a>

                <a href="{{ route('dashboard.index') }}" class="{{ request()->routeIs('dashboard.*') ? 'active' : '' }}">
                    Dashboard
                </a>

                <a href="{{ route('planning.viewer') }}" class="{{ request()->routeIs('planning.viewer') ? 'active' : '' }}">
                    Planning interactif
                </a>

                <a href="{{ route('export.index') }}" class="{{ request()->routeIs('export.*') || request()->routeIs('documents.*') ? 'active' : '' }}">
                    Exportation
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-card">
                {{ $slot }}
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
