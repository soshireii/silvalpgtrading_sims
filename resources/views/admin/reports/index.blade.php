@php
// Full class names live here so Tailwind can see them.
$accent = [
'default' => 'border-l-maroon-600',
'green' => 'border-l-status-success',
'yellow' => 'border-l-status-warning',
'red' => 'border-l-status-danger',
];
$showGasFilter = in_array($f['type'], ['gas', 'cylinder'], true);

// Which quick range matches the dates on screen (first match wins).
$activePreset = '';
foreach ($presets as $preset) {
if ($preset['from'] === $f['from'] && $preset['to'] === $f['to']) {
$activePreset = $preset['label'];
break;
}
}
$input = 'rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500';
@endphp

<x-app-layout title="Reports" header="Reports">

    {{-- ============ One compact filter bar ============ --}}
    <form id="report-filters" method="GET" action="{{ route('admin.reports.index') }}"
        class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-5">
        <div class="flex flex-wrap items-end gap-3">

            <div class="w-full sm:w-56">
                <label for="report-type" class="block text-xs font-medium text-gray-600 mb-1">Report</label>
                <select id="report-type" name="report" onchange="this.form.submit()" class="w-full font-medium {{ $input }}">
                    @foreach($types as $key => $label)
                    <option value="{{ $key }}" @selected($f['type']===$key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="range-preset" class="block text-xs font-medium text-gray-600 mb-1">Period</label>
                <select id="range-preset" class="{{ $input }}">
                    <option value="">Custom range</option>
                    @foreach($presets as $preset)
                    <option value="{{ $preset['label'] }}" data-from="{{ $preset['from'] }}" data-to="{{ $preset['to'] }}"
                        @selected($activePreset===$preset['label'])>{{ $preset['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="from" class="block text-xs font-medium text-gray-600 mb-1">From</label>
                <input id="from" type="date" name="from" value="{{ $f['from'] }}" class="{{ $input }}">
            </div>
            <div>
                <label for="to" class="block text-xs font-medium text-gray-600 mb-1">To</label>
                <input id="to" type="date" name="to" value="{{ $f['to'] }}" class="{{ $input }}">
            </div>

            <div>
                <label for="group" class="block text-xs font-medium text-gray-600 mb-1">Group by</label>
                <select id="group" name="group" class="{{ $input }}">
                    @foreach(['auto' => 'Auto', 'day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $value => $label)
                    <option value="{{ $value }}" @selected($f['group_requested']===$value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if($showGasFilter)
            <div>
                <label for="product" class="block text-xs font-medium text-gray-600 mb-1">Gas</label>
                <select id="product" name="product" class="{{ $input }}">
                    <option value="">All gas</option>
                    @foreach($products as $p)
                    <option value="{{ $p['id'] }}" @selected($f['product']===$p['id'])>{{ $p['name'] }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <button type="submit" class="bg-maroon-600 hover:bg-maroon-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Run Report</button>

            {{-- One export menu instead of two buttons --}}
            <div class="relative ml-auto" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                <button type="button" x-on:click="open = !open"
                    class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-3 py-2 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16" />
                    </svg>
                    Export PDF
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div x-show="open" x-cloak class="absolute right-0 z-20 mt-1 w-56 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                    <a href="{{ route('admin.reports.export', $base + ['report' => $f['type']]) }}"
                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">This report only</a>
                    <a href="{{ route('admin.reports.export', $base + ['report' => 'all']) }}"
                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">All reports (full document)</a>
                </div>
            </div>
        </div>
    </form>

    {{-- ============ Title ============ --}}
    <div class="mb-5">
        <h2 class="text-lg font-semibold text-gray-900">{{ $report['title'] }}</h2>
        @if($report['description'])
        <p class="text-sm text-gray-500 mt-0.5">{{ $report['description'] }}</p>
        @endif
        <p class="text-xs text-gray-400 mt-1">
            {{ \Carbon\Carbon::parse($f['from'])->format('M j, Y') }} – {{ \Carbon\Carbon::parse($f['to'])->format('M j, Y') }}
            · {{ $f['days'] }} day(s) · grouped {{ strtolower($f['group_label']) }}
            · "Sales" = every order that is not cancelled
        </p>
    </div>

    {{-- ============ KPI cards ============ --}}
    @if(!empty($report['kpis']))
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        @foreach($report['kpis'] as $kpi)
        <div class="bg-white rounded-xl border border-gray-200 border-l-4 {{ $accent[$kpi['tone']] ?? $accent['default'] }} shadow-sm p-4">
            <p class="text-xs text-gray-500">{{ $kpi['label'] }}</p>
            <p class="text-xl font-semibold text-gray-900 mt-1 break-words">{{ $kpi['value'] }}</p>
            @if($kpi['sub'])
            <p class="text-xs text-gray-400 mt-1">{{ $kpi['sub'] }}</p>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    {{-- ============ Insights ============ --}}
    @if(!empty($report['insights']))
    <div class="bg-maroon-50 border border-maroon-200 rounded-xl p-4 mb-5">
        <h3 class="text-sm font-semibold text-maroon-700 mb-2">Quick insights</h3>
        <ul class="space-y-1 text-sm text-gray-700 list-disc list-inside">
            @foreach($report['insights'] as $line)
            <li>{{ $line }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- ============ Charts (fixed height, so they never grow huge) ============ --}}
    @if(!empty($report['charts']))
    <div id="chart-warning" class="hidden mb-4 rounded-lg border border-yellow-200 bg-status-warning-bg px-4 py-3 text-sm text-status-warning">
        The chart library could not be loaded (it needs internet access to cdnjs.cloudflare.com). The tables below still show all the numbers.
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
        @foreach($report['charts'] as $i => $chart)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 {{ !empty($chart['wide']) ? 'lg:col-span-2' : '' }}">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">{{ $chart['title'] }}</h3>
            @if($chart['empty'])
            <div class="h-60 flex items-center justify-center text-sm text-gray-400 border border-dashed border-gray-200 rounded-lg">
                No data for this period.
            </div>
            @else
            <div class="relative h-60"><canvas id="chart-{{ $i }}"></canvas></div>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    {{-- ============ Tables (click a title to collapse it) ============ --}}
    @foreach($report['tables'] as $table)
    <details open class="group bg-white rounded-xl border border-gray-200 shadow-sm mb-5 overflow-hidden">
        <summary class="flex items-center justify-between gap-3 px-4 py-3 cursor-pointer select-none list-none [&::-webkit-details-marker]:hidden">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">{{ $table['title'] }}</h3>
                @if($table['note'])
                <p class="text-xs text-gray-400 mt-0.5">{{ $table['note'] }}</p>
                @endif
            </div>
            <div class="flex items-center gap-2 text-xs text-gray-400 shrink-0">
                <span>{{ count($table['rows']) }} row(s)</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        </summary>
        <div class="overflow-x-auto border-t border-gray-100">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-gray-500 border-b border-gray-100">
                        @foreach($table['columns'] as $col)
                        <th class="py-2.5 px-4 font-medium whitespace-nowrap {{ $col['align'] === 'right' ? 'text-right' : 'text-left' }}">{{ $col['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($table['rows'] as $row)
                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                        @foreach($row as $ci => $cell)
                        <td class="py-2.5 px-4 {{ $table['columns'][$ci]['align'] === 'right' ? 'text-right whitespace-nowrap' : 'text-left' }} {{ $ci === 0 ? 'text-gray-800 font-medium' : 'text-gray-600' }}">
                            @if(is_array($cell))
                            <x-badge :color="$cell['tone']" :label="$cell['t']" />
                            @else
                            {{ $cell }}
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ count($table['columns']) }}" class="py-8 text-center text-gray-400">No records in this period.</td>
                    </tr>
                    @endforelse
                </tbody>
                @if(!empty($table['footer']) && !empty($table['rows']))
                <tfoot>
                    <tr class="bg-gray-50 font-semibold text-gray-900 border-t border-gray-200">
                        @foreach($table['footer'] as $ci => $cell)
                        <td class="py-2.5 px-4 {{ $table['columns'][$ci]['align'] === 'right' ? 'text-right whitespace-nowrap' : 'text-left' }}">{{ $cell }}</td>
                        @endforeach
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </details>
    @endforeach

    {{-- ============ "Period" dropdown fills in the dates and reloads ============ --}}
    <script>
        (function() {
            const form = document.getElementById('report-filters');
            const preset = document.getElementById('range-preset');
            if (!form || !preset) return;

            preset.addEventListener('change', function() {
                const option = preset.selectedOptions[0];
                if (!option || !option.dataset.from) return; // "Custom range" does nothing
                form.elements['from'].value = option.dataset.from;
                form.elements['to'].value = option.dataset.to;
                form.elements['group'].value = 'auto'; // let the system pick the best grouping
                form.submit();
            });

            // Typing your own dates switches the dropdown back to "Custom range".
            ['from', 'to'].forEach(function(name) {
                form.elements[name].addEventListener('change', function() {
                    preset.value = '';
                });
            });
        })();
    </script>

    {{-- ============ Chart.js ============ --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const charts = @json($report['charts']);
            if (!charts.length) return;
            if (typeof Chart === 'undefined') {
                const warning = document.getElementById('chart-warning');
                if (warning) warning.classList.remove('hidden');
                return;
            }

            const palette = ['#7a1f24', '#b45309', '#16a34a', '#6b7280', '#96494a', '#d89797', '#421114', '#d97706', '#9ca3af'];
            const tones = {
                maroon: '#7a1f24',
                green: '#16a34a',
                amber: '#b45309',
                gray: '#9ca3af',
                red: '#dc2626'
            };
            const money = v => '₱' + Number(v).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            const whole = v => Number(v).toLocaleString();

            charts.forEach(function(c, i) {
                const canvas = document.getElementById('chart-' + i);
                if (!canvas || c.empty) return;

                const isDonut = c.type === 'doughnut';
                const horizontal = c.type === 'hbar';
                const baseType = isDonut ? 'doughnut' : (horizontal ? 'bar' : c.type);
                const format = c.unit === 'money' ? money : whole;

                const datasets = c.datasets.map(function(ds, di) {
                    const color = tones[ds.tone] || palette[di % palette.length];
                    const type = ds.type || baseType;
                    if (isDonut) {
                        return {
                            label: ds.label,
                            data: ds.data,
                            borderWidth: 1,
                            backgroundColor: c.labels.map((_, li) => palette[li % palette.length])
                        };
                    }
                    if (type === 'line') {
                        return {
                            type: 'line',
                            label: ds.label,
                            data: ds.data,
                            borderColor: color,
                            backgroundColor: color,
                            tension: 0.3,
                            pointRadius: 3,
                            borderWidth: 2,
                            fill: false
                        };
                    }
                    return {
                        type: 'bar',
                        label: ds.label,
                        data: ds.data,
                        backgroundColor: color,
                        borderRadius: 4,
                        maxBarThickness: 48
                    };
                });

                const stacked = !!c.stacked;
                const valueTicks = {
                    callback: v => c.unit === 'money' ? '₱' + Number(v).toLocaleString() : whole(v),
                    precision: c.unit === 'money' ? undefined : 0
                };
                const scales = isDonut ? {} : (horizontal ?
                    {
                        x: {
                            beginAtZero: true,
                            stacked: stacked,
                            ticks: valueTicks
                        },
                        y: {
                            stacked: stacked,
                            grid: {
                                display: false
                            }
                        }
                    } :
                    {
                        x: {
                            stacked: stacked,
                            grid: {
                                display: false
                            },
                            ticks: {
                                autoSkip: true,
                                maxTicksLimit: 14,
                                maxRotation: 45
                            }
                        },
                        y: {
                            beginAtZero: true,
                            stacked: stacked,
                            ticks: valueTicks
                        }
                    });

                new Chart(canvas, {
                    type: baseType,
                    data: {
                        labels: c.labels,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false, // the .h-60 wrapper controls the height
                        indexAxis: horizontal ? 'y' : 'x',
                        cutout: isDonut ? '55%' : undefined,
                        plugins: {
                            legend: {
                                display: isDonut || datasets.length > 1,
                                position: 'bottom',
                                labels: {
                                    boxWidth: 12,
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        const v = isDonut ? ctx.parsed : (horizontal ? ctx.parsed.x : ctx.parsed.y);
                                        return (ctx.dataset.label ? ctx.dataset.label + ': ' : '') + format(v);
                                    }
                                }
                            },
                        },
                        scales: scales,
                    },
                });
            });
        });
    </script>

</x-app-layout>