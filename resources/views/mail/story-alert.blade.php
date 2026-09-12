<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $story->headline }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333333; line-height: 1.6; margin: 0; padding: 20px; background-color: #f9f9f9;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 4px; border: 1px solid #e0e0e0;">
        
        <div style="margin-bottom: 20px; border-bottom: 2px solid #333333; padding-bottom: 10px;">
            <h2 style="margin: 0; color: #333333;">UNB Wire</h2>
            @if($isBreaking)
                <span style="display: inline-block; background-color: #cc0000; color: #ffffff; font-size: 12px; font-weight: bold; padding: 3px 8px; border-radius: 3px; margin-top: 10px;">BREAKING</span>
            @endif
        </div>

        <h1 style="font-size: 24px; font-weight: bold; color: #111111; margin-top: 0;">{{ $story->headline }}</h1>
        
        <div style="font-size: 14px; color: #666666; margin-bottom: 20px;">
            @if($story->category)
                <strong>{{ $story->category->name_en ?? $story->category->name ?? $story->category->slug }}</strong> &bull;
            @endif
            {{ $story->published_at ? $story->published_at->format('F j, Y g:i A') : 'Unpublished' }}
            <br>
            By {{ $story->owner ? $story->owner->name : 'Staff Reporter' }}
        </div>

        <div style="font-size: 16px; margin-bottom: 30px;">
            {{ $story->brief }}
        </div>

        @if($story->media && $story->media->count() > 0)
            <div style="background-color: #f0f7ff; border-left: 4px solid #0066cc; padding: 10px 15px; margin-bottom: 30px; font-size: 14px;">
                {{ $story->media->count() }} media attachment(s) available.
            </div>
        @endif

        <div style="border-top: 1px solid #eeeeee; padding-top: 20px; margin-top: 30px; font-size: 12px; color: #888888;">
            <p style="margin: 0 0 10px 0;">You are receiving this alert because {{ $clientName }} subscribes to UNB Wire.</p>
            <p style="margin: 0;">To update your alert preferences, contact your UNB account manager.</p>
        </div>
    </div>
</body>
</html>
