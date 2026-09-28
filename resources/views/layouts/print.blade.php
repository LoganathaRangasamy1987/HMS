<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · CareDesk</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; background: #eef2f6; color: #172033; font: 14px/1.45 Arial, sans-serif; }
        .toolbar { margin: 20px auto 0; width: min(900px, calc(100% - 32px)); display: flex; justify-content: space-between; }
        .toolbar a, .toolbar button { border: 1px solid #aeb8c5; border-radius: 6px; background: white; color: #172033; padding: 9px 14px; text-decoration: none; cursor: pointer; }
        .document { box-sizing: border-box; margin: 16px auto 40px; width: min(900px, calc(100% - 32px)); min-height: 1120px; padding: 48px; background: white; box-shadow: 0 8px 30px rgba(23, 32, 51, .1); }
        .header { display: flex; justify-content: space-between; gap: 30px; border-bottom: 2px solid #172033; padding-bottom: 22px; }
        h1, h2, p { margin-top: 0; } h1 { margin-bottom: 6px; font-size: 25px; } h2 { margin: 28px 0 10px; font-size: 17px; }
        .meta { text-align: right; } .muted { color: #5d6877; } .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; margin-top: 24px; }
        table { width: 100%; border-collapse: collapse; } th, td { padding: 10px 8px; border-bottom: 1px solid #dfe4ea; text-align: left; vertical-align: top; } th { font-size: 12px; text-transform: uppercase; color: #5d6877; }
        .number { text-align: right; white-space: nowrap; } .totals { margin: 24px 0 0 auto; width: min(380px, 100%); } .totals td:first-child { color: #5d6877; } .grand td { border-top: 2px solid #172033; font-size: 17px; font-weight: bold; }
        .footer { margin-top: 50px; border-top: 1px solid #dfe4ea; padding-top: 14px; color: #5d6877; font-size: 12px; }
        @media print { @page { size: A4; margin: 12mm; } body { background: white; } .toolbar { display: none; } .document { margin: 0; width: 100%; min-height: auto; padding: 0; box-shadow: none; } }
    </style>
</head>
<body>
<div class="toolbar"><a href="@yield('back')">← Back</a><button type="button" onclick="window.print()">Print</button></div>
<main class="document">@yield('content')</main>
</body>
</html>
