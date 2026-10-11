<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="6; url={{ url('/login') }}">
    <title>Page Expired — UNB Wire</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f7f6f3;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1c1f2e;
        }
        .card {
            background: #fff;
            border: 1px solid #eceae5;
            border-radius: 15px;
            padding: 38px 44px;
            max-width: 460px;
            text-align: center;
            box-shadow: 0 2px 12px rgba(28,31,46,0.06);
        }
        .code {
            font-family: 'Fraunces', serif;
            font-size: 15px;
            font-weight: 700;
            color: #d13438;
            letter-spacing: 0.08em;
            margin-bottom: 10px;
        }
        h1 {
            font-family: 'Fraunces', serif;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        p { font-size: 14px; line-height: 1.65; color: #565a68; }
        .hint { margin-top: 14px; font-size: 12.5px; color: #b0b2bc; }
        a.btn {
            display: inline-block;
            margin-top: 22px;
            background: #16204a;
            color: #fff;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 600;
            padding: 10px 22px;
            border-radius: 9px;
            transition: background 0.15s ease;
        }
        a.btn:hover { background: #1c2a5e; }
    </style>
</head>
<body>
    <div class="card">
        <div class="code">419 · SESSION EXPIRED</div>
        <h1>Your session has expired</h1>
        <p>The page you were working on has been inactive for a while, so its security token is out of date. Nothing you already saved is lost.</p>
        <p class="hint">Redirecting you to the login page in a few seconds…</p>
        <a class="btn" href="{{ url('/login') }}">Log in again</a>
    </div>
</body>
</html>
