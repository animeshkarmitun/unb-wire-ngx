<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access Restricted — UNB Wire</title>
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
        .icon {
            width: 52px; height: 52px; margin: 0 auto 18px;
            border-radius: 13px; background: #fdecec; color: #e5484d;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; font-weight: 700;
        }
        h1 {
            font-family: 'Fraunces', serif;
            font-size: 21px; font-weight: 600; margin-bottom: 8px;
        }
        p { font-size: 14px; color: #5d6172; line-height: 1.6; margin-bottom: 22px; }
        a {
            display: inline-block; padding: 10px 26px; border-radius: 9px;
            background: #1c1f2e; color: #fff; text-decoration: none;
            font-size: 13.5px; font-weight: 600;
        }
        a:hover { background: #2c3050; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">!</div>
        <h1>You don't have access to this page</h1>
        <p>Your role doesn't include permission for this area. Contact your administrator if you believe you should have access.</p>
        <a href="{{ url('/admin') }}">Back to dashboard</a>
    </div>
</body>
</html>
