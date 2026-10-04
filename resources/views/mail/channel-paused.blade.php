<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery channel auto-paused</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333333; line-height: 1.6; margin: 0; padding: 20px; background-color: #f9f9f9;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 4px; border: 1px solid #e0e0e0;">

        <div style="margin-bottom: 20px; border-bottom: 2px solid #333333; padding-bottom: 10px;">
            <h2 style="margin: 0; color: #333333;">UNB Wire</h2>
            <span style="display: inline-block; background-color: #d97706; color: #ffffff; font-size: 12px; font-weight: bold; padding: 3px 8px; border-radius: 3px; margin-top: 10px;">DELIVERY PAUSED</span>
        </div>

        <h1 style="font-size: 24px; font-weight: bold; color: #111111; margin-top: 0;">Your delivery channel was auto-paused</h1>

        <div style="font-size: 14px; color: #666666; margin-bottom: 20px;">
            {{ $clientName }} &bull; {{ strtoupper($channelType) }} channel &bull; {{ now()->format('F j, Y g:i A') }}
        </div>

        <div style="font-size: 16px; margin-bottom: 30px;">
            After several consecutive delivery failures, the UNB Wire dispatch engine
            <b>paused your {{ strtoupper($channelType) }} delivery channel</b> to avoid
            repeated failed attempts. Stories will keep accumulating for redelivery.
        </div>

        <div style="font-size: 16px;">
            Please check your receiving endpoint and contact UNB Wire support to
            resume the channel.
        </div>

    </div>
</body>
</html>
