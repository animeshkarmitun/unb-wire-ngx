<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reset Your Password</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333333; line-height: 1.6; margin: 0; padding: 20px; background-color: #f9f9f9;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 4px; border: 1px solid #e0e0e0;">

        <div style="margin-bottom: 20px; border-bottom: 2px solid #333333; padding-bottom: 10px;">
            <h2 style="margin: 0; color: #333333;">UNB Wire</h2>
        </div>

        <h1 style="font-size: 20px; font-weight: bold; color: #111111; margin-top: 0;">Reset Your Password</h1>

        <p style="font-size: 14px; color: #555555;">
            You are receiving this email because a password reset was requested for your account.
        </p>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ config('app.url') }}/portal/reset-password?token={{ $token }}&email={{ urlencode($email) }}"
               style="display: inline-block; background-color: #000000; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: bold;">
                Reset Password
            </a>
        </div>

        <p style="font-size: 13px; color: #888888;">
            This link will expire in 60 minutes. If you did not request a password reset, no further action is required.
        </p>

        <div style="border-top: 1px solid #eeeeee; padding-top: 20px; margin-top: 30px; font-size: 12px; color: #888888;">
            <p style="margin: 0;">UNB Wire — Client Portal</p>
        </div>
    </div>
</body>
</html>
