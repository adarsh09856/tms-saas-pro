<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin:0;background:#f3f5f2;color:#20342b;font-family:Arial,sans-serif;line-height:1.6">
    <main style="max-width:600px;margin:32px auto;padding:32px;background:#fff;border:1px solid #dce4de;border-radius:12px">
        {{ $slot }}
    </main>
</body>
</html>
