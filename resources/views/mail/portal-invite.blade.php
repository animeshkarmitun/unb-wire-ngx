<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Welcome to UNB Wire Portal</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333333; line-height: 1.6; margin: 0; padding: 20px; background-color: #f9f9f9;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 4px; border: 1px solid #e0e0e0;">

        <div style="margin-bottom: 20px; border-bottom: 2px solid #333333; padding-bottom: 10px;">
            <h2 style="margin: 0; color: #333333;">UNB Wire</h2>
        </div>

        <h1 style="font-size: 20px; font-weight: bold; color: #111111; margin-top: 0;">Welcome to UNB Wire Portal</h1>

        <p style="font-size: 14px; color: #555555;">
            Hello <strong>{{ $user->name }}</strong>,
        </p>

        <p style="font-size: 14px; color: #555555;">
            You have been granted access to the UNB Wire client portal for <strong>{{ $user->client->name ?? 'your organisation' }}</strong>.
            Use the credentials below to log in. You will be prompted to change your password after your first login.
        </p>

        <div style="background-color: #f5f5f5; padding: 16px; border-radius: 4px; margin: 20px 0;">
            <p style="margin: 0 0 8px; font-size: 13px; color: #666666;"><strong>Email:</strong> {{ $user->email }}</p>
            <p style="margin: 0; font-size: 13px; color: #666666;"><strong>One-time password:</strong> <code style="background: #e8e8e8; padding: 2px 6px; border-radius: 3px; font-size: 13px;">{{ $plainPassword }}</code></p>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ config('app.url') }}/portal/login"
               style="display: inline-block; background-color: #000000; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: bold;">
                Login &amp; Change Password
            </a>
        </div>

        <p style="font-size: 13px; color: #888888;">
            If you did not expect this invitation, please ignore this email or contact your administrator.
        </p>

        <div style="border-top: 1px solid #eeeeee; padding-top: 20px; margin-top: 30px; font-size: 12px; color: #888888;">
            <p style="margin: 0;">UNB Wire — Client Portal</p>
        </div>
    </div>
</body>
</html>
