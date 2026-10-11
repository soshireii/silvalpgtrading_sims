<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 26px 28px;
        }

        /* DejaVu Sans ships with DomPDF and contains the peso sign (₱). Helvetica does not. */
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #1f2937;
            font-size: 8px;
        }

        h1 {
            font-size: 15px;
            color: #7a1f24;
            margin: 0 0 2px 0;
        }

        h2 {
            font-size: 12px;
            color: #7a1f24;
            border-bottom: 2px solid #7a1f24;
            padding-bottom: 3px;
            margin: 0 0 4px 0;
        }

        h3 {
            font-size: 9px;
            color: #111827;
            margin: 12px 0 3px 0;
        }

        .subtitle {
            color: #6b7280;
            margin: 0 0 12px 0;
        }

        .desc {
            color: #6b7280;
            margin: 0 0 8px 0;
        }

        .section {
            page-break-before: always;
        }

        .section.first {
            page-break-before: auto;
        }

        table.kpi {
            width: 100%;
            border-collapse: separate;
            border-spacing: 4px;
            margin: 0 -4px 6px -4px;
        }

        table.kpi td {
            width: 25%;
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            vertical-align: top;
        }

        .kpi .label {
            font-size: 7px;
            color: #6b7280;
            text-transform: uppercase;
        }

        .kpi .value {
            font-size: 12px;
            font-weight: bold;
            color: #111827;
            margin: 2px 0;
        }

        .kpi .sub {
            font-size: 7px;
            color: #9ca3af;
        }

        ul.insights {
            margin: 4px 0 6px 14px;
            padding: 0;
        }

        ul.insights li {
            margin-bottom: 2px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        table.data thead {
            display: table-header-group;
        }

        table.data th {
            background: #7a1f24;
            color: #ffffff;
            padding: 4px 5px;
            font-size: 7.5px;
            text-align: left;
        }

        table.data td {
            padding: 4px 5px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 7.5px;
        }

        table.data tr:nth-child(even) td {
            background: #faf7f7;
        }

        table.data tfoot td {
            font-weight: bold;
            background: #f3f4f6;
            border-top: 1px solid #9ca3af;
        }

        .r {
            text-align: right;
        }

        .note {
            color: #9ca3af;
            font-style: italic;
            margin: 0 0 4px 0;
        }

        .empty {
            color: #9ca3af;
            font-style: italic;
            padding: 4px 0;
        }

        .footer {
            margin-top: 14px;
            font-size: 7px;
            color: #9ca3af;
        }
    </style>
</head>

<body>
    <h1>Silva LPG Trading — {{ $all ? 'Full Business Report' : $sections[0]['title'] . ' Report' }}</h1>
    <p class="subtitle">
        {{ \Carbon\Carbon::parse($f['from'])->format('F j, Y') }} to {{ \Carbon\Carbon::parse($f['to'])->format('F j, Y') }}
        &middot; {{ $f['days'] }} day(s) &middot; grouped {{ strtolower($f['group_label']) }}
        &middot; Generated {{ now()->format('F j, Y g:i A') }}<br>
        "Sales" means every order that is not cancelled. "Collected" means orders marked paid in full.
    </p>

    @foreach($sections as $s)
    <div class="section {{ $loop->first ? 'first' : '' }}">
        <h2>{{ $s['title'] }}</h2>
        @if($s['description'])
        <p class="desc">{{ $s['description'] }}</p>
        @endif

        @if(!empty($s['kpis']))
        <table class="kpi">
            @foreach(array_chunk($s['kpis'], 4) as $row)
            <tr>
                @foreach($row as $kpi)
                <td>
                    <div class="label">{{ $kpi['label'] }}</div>
                    <div class="value">{{ $kpi['value'] }}</div>
                    @if($kpi['sub'])<div class="sub">{{ $kpi['sub'] }}</div>@endif
                </td>
                @endforeach
                @for($pad = count($row); $pad < 4; $pad++)<td style="border: none;">
                    </td>@endfor
            </tr>
            @endforeach
        </table>
        @endif

        @if(!empty($s['insights']))
        <h3>Quick insights</h3>
        <ul class="insights">
            @foreach($s['insights'] as $line)<li>{{ $line }}</li>@endforeach
        </ul>
        @endif

        @foreach($s['tables'] as $table)
        <h3>{{ $table['title'] }}</h3>
        @if($table['note'])<p class="note">{{ $table['note'] }}</p>@endif

        @if(empty($table['rows']))
        <p class="empty">No records in this period.</p>
        @else
        <table class="data">
            <thead>
                <tr>
                    @foreach($table['columns'] as $col)
                    <th class="{{ $col['align'] === 'right' ? 'r' : '' }}">{{ $col['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($table['rows'] as $row)
                <tr>
                    @foreach($row as $ci => $cell)
                    <td class="{{ $table['columns'][$ci]['align'] === 'right' ? 'r' : '' }}">{{ is_array($cell) ? $cell['t'] : $cell }}</td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
            @if(!empty($table['footer']))
            <tfoot>
                <tr>
                    @foreach($table['footer'] as $ci => $cell)
                    <td class="{{ $table['columns'][$ci]['align'] === 'right' ? 'r' : '' }}">{{ $cell }}</td>
                    @endforeach
                </tr>
            </tfoot>
            @endif
        </table>
        @endif
        @endforeach

        @if(!empty($s['charts']))
        <p class="note">Charts for this report are shown on the Reports page in the system.</p>
        @endif
    </div>
    @endforeach

    <p class="footer">Silva LPG Trading — Order, Sales, Expense, and Inventory Management System</p>
</body>

</html>