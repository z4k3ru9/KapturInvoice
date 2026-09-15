<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $proposal->title }}</title>
    <style>
        @include('pdf.partials.page-footer')
        {{-- dompdf has limited CSS support (no flexbox/grid) — plain
             block/table layout only, same as resources/views/pdf/invoice.blade.php. --}}
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1f2937; }
        .header { width: 100%; margin-bottom: 24px; }
        .header td { vertical-align: top; }
        .company-name { font-size: 18px; font-weight: bold; }
        .doc-title { font-size: 20px; font-weight: bold; text-align: right; }
        .doc-meta { text-align: right; color: #6b7280; }
        .muted { color: #6b7280; }
        .logo { max-height: 48px; max-width: 220px; margin-bottom: 6px; }
        .proposal-content { margin-top: 24px; }
    </style>
    {{-- Author-supplied CSS from the proposal/template (a full stylesheet,
         not scoped declarations) — same trust level as the html body
         below: admin-authored content, not user/client input. --}}
    @if ($proposal->css)
        <style>{!! $proposal->css !!}</style>
    @endif
</head>
<body>
    <table class="header">
        <tr>
            <td width="50%">
                @if ($logoDataUri = $proposal->company->getLogoDataUri())
                    <img class="logo" src="{{ $logoDataUri }}" alt="{{ $proposal->company->name }}">
                @endif
                <div class="company-name">{{ $proposal->company->name }}</div>
                @if ($proposal->company->email)
                    <div class="muted">{{ $proposal->company->email }}</div>
                @endif
            </td>
            <td width="50%">
                <div class="doc-title">PROPOSAL</div>
                @if ($proposal->client)
                    <div class="doc-meta">{{ $proposal->client->name }}</div>
                @endif
                @if ($proposal->valid_until)
                    <div class="doc-meta">Valid until: {{ $proposal->valid_until->toFormattedDateString() }}</div>
                @endif
            </td>
        </tr>
    </table>

    <h2>{{ $proposal->title }}</h2>

    <div class="proposal-content">
        {!! $proposal->html !!}
    </div>
</body>
</html>
