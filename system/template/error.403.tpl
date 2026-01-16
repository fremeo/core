<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>403 – Zugriff verweigert</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Google Font (optional) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;500;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg: #050816;
            --card-bg: #0b1020;
            --accent: #ff4b5c;
            --accent-soft: rgba(255, 75, 92, 0.15);
            --text-main: #f5f5f7;
            --text-muted: #9ca3af;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Inter", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background: radial-gradient(circle at top, #111827 0, #020617 55%, #000 100%);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .wrapper {
            max-width: 640px;
            width: 100%;
        }

        .card {
            background: linear-gradient(145deg, #020617 0%, #020617 40%, #020617 100%);
            border-radius: 24px;
            padding: 32px 28px 28px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.25);
            box-shadow:
                0 24px 60px rgba(15, 23, 42, 0.9),
                0 0 0 1px rgba(15, 23, 42, 0.8);
        }

        .card::before {
            content: "";
            position: absolute;
            inset: -40%;
            background:
                radial-gradient(circle at 0% 0%, rgba(96, 165, 250, 0.16), transparent 55%),
                radial-gradient(circle at 100% 0%, rgba(248, 113, 113, 0.18), transparent 55%);
            opacity: 0.9;
            mix-blend-mode: screen;
            pointer-events: none;
        }

        .card-inner {
            position: relative;
            z-index: 1;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.4);
            color: var(--text-muted);
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .badge-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: var(--accent);
            box-shadow: 0 0 0 6px var(--accent-soft);
        }

        .code {
            font-size: 64px;
            font-weight: 700;
            letter-spacing: 0.08em;
            margin: 18px 0 4px;
            color: #e5e7eb;
        }

        .title {
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 10px;
        }

        .subtitle {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 22px;
            line-height: 1.6;
        }

        .hint {
            font-size: 13px;
            color: #9ca3af;
            margin-top: 10px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 6px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 16px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.18s ease-out;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--accent);
            color: #0b1020;
            box-shadow: 0 12px 30px rgba(248, 113, 113, 0.45);
        }

        .btn-primary:hover {
            background: #ff6b7a;
            transform: translateY(-1px);
            box-shadow: 0 16px 40px rgba(248, 113, 113, 0.55);
        }

        .btn-ghost {
            background: rgba(15, 23, 42, 0.9);
            color: var(--text-main);
            border-color: rgba(148, 163, 184, 0.5);
        }

        .btn-ghost:hover {
            background: rgba(15, 23, 42, 1);
            border-color: rgba(209, 213, 219, 0.9);
            transform: translateY(-1px);
        }

        .btn span.icon {
            font-size: 14px;
        }

        .footer-note {
            margin-top: 18px;
            font-size: 11px;
            color: #6b7280;
        }

        @media (max-width: 480px) {
            .card {
                padding: 24px 18px 20px;
                border-radius: 20px;
            }

            .code {
                font-size: 46px;
            }

            .title {
                font-size: 20px;
            }

            .actions {
                flex-direction: column;
                align-items: stretch;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <div class="card-inner">
            <div class="badge">
                <span class="badge-dot"></span>
                <span>Zugriff verweigert</span>
            </div>

            <div class="code">403</div>

            <h1 class="title">Du hast keine Berechtigung für diese Seite.</h1>

            <p class="subtitle">
                Entweder fehlen dir die notwendigen Rechte, dein Login ist abgelaufen
                oder diese Ressource ist geschützt. Wenn du glaubst, dass es sich um
                einen Fehler handelt, wende dich bitte an den Administrator.
            </p>

            <div class="actions">
                <a href="/" class="btn btn-primary">
                    <span class="icon">←</span>
                    <span>Zur Startseite</span>
                </a>
                <button class="btn btn-ghost" onclick="window.history.back();">
                    <span class="icon">⤺</span>
                    <span>Zurück zur vorherigen Seite</span>
                </button>
            </div>

            <p class="hint">
                Fehlercode: <strong>HTTP 403 – Forbidden</strong>
            </p>

            <p class="footer-note">
                Wenn dieses Problem dauerhaft auftritt, notiere bitte Datum, Uhrzeit und die aufgerufene URL.
            </p>
        </div>
    </div>
</div>
</body>
</html>
