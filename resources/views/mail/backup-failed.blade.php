<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Database backup failed</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333333; line-height: 1.6; margin: 0; padding: 20px; background-color: #f9f9f9;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 4px; border: 1px solid #e0e0e0;">

        <div style="margin-bottom: 20px; border-bottom: 2px solid #333333; padding-bottom: 10px;">
            <h2 style="margin: 0; color: #333333;">UNB Wire</h2>
            <span style="display: inline-block; background-color: #cc0000; color: #ffffff; font-size: 12px; font-weight: bold; padding: 3px 8px; border-radius: 3px; margin-top: 10px;">BACKUP FAILED</span>
        </div>

        <h1 style="font-size: 24px; font-weight: bold; color: #111111; margin-top: 0;">Database backup failed</h1>

        <div style="font-size: 14px; color: #666666; margin-bottom: 20px;">
            {{ now()->format('F j, Y g:i A') }} &bull; {{ config('app.url') }}
        </div>

        <div style="font-size: 16px; margin-bottom: 30px;">
            The scheduled database backup run did not complete. The database
            dump was not stored. Please investigate and run
            <code>php artisan backup:run</code> manually once fixed.
        </div>

        <div style="font-family: monospace; font-size: 13px; background-color: #f5f5f5; border: 1px solid #e0e0e0; border-radius: 3px; padding: 12px; white-space: pre-wrap; word-break: break-word;">{{ $error }}</div>

    </div>
</body>
</html>
