@php
// Full class names live here so Tailwind can see them.
$accent = [
'default' => 'border-l-maroon-600',
'green' => 'border-l-status-success',
'yellow' => 'border-l-status-warning',
'red' => 'border-l-status-danger',
];
$showGasFilter = in_array($f['type'], ['gas', 'cylinder'], true);
@endphp

<x-app-layout title="Reports" header="Reports">

    {{-- ============ Filters ============ --}}
    <form method="GET" action="{{ route('admin.reports.index') }}" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-4">
        <input type="hidden" name="report" value="{{ $f['type'] }}">

        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">From</label>
                <input type="date" name="from" value="{{ $f['from'] }}" class="rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">To</label>
                <input type="date" name="to" value="{{ $f['to'] }}" class="rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Group by</label>
                <select name="group" class="rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                    @foreach(['auto' => 'Auto', 'day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $value => $label)
                    <option value="{{ $value }}" @selected($f['group_requested']===$value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if($showGasFilter)
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Gas</label>
                <select name="product" class="rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                    <option value="">All gas</option>
                    @foreach($products as $p)
                    <option value="{{ $p['id'] }}" @selected($f['product']===$p['id'])>{{ $p['name'] }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <button type="submit" class="bg-maroon-600 hover:bg-maroon-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Run Report</button>

            <div class="ml-auto flex flex-wrap gap-2">
                <a href="{{ route('admin.reports.export', $base + ['report' => $f['type']]) }}"
                    class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-3 py-2 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16" />
                    </svg>
                    PDF — this report
                </a>
                <a href="{{ route('admin.reports.export', $base + ['report' => 'all']) }}"
                    class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-3 py-2 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16" />
                    </svg>
                    PDF — all reports
                </a>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 mt-4 pt-3 border-t border-gray-100">
            <span class="text-xs text-gray-400 mr-1">Quick range:</span>
            @foreach($presets as $preset)
            @php $active = $preset['from'] === $f['from'] && $preset['to'] === $f['to']; @endphp
            <a href="{{ route('admin.reports.index', array_filter(['report' => $f['type'], 'from' => $preset['from'], 'to' => $preset['to'], 'product' => $f['product']])) }}"
                class="text-xs font-medium px-3 py-1 rounded-full border {{ $active ? 'bg-maroon-600 border-maroon-600 text-white' : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50' }}">
                {{ $preset['label'] }}
            </a>
            @endforeach
        </div>
    </form>

    {{-- ============ Report tabs ============ --}}
    <div class="flex gap-1 overflow-x-auto border-b border-gray-200 mb-5">
        @foreach($types as $key => $label)
        <a href="{{ route('admin.reports.index', $base + ['report' => $key]) }}"
            class="whitespace-nowrap px-4 py-2.5 text-sm font-medium border-b-2 -mb-px {{ $f['type'] === $key ? 'border-maroon-600 text-maroon-700' : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-300' }}">
            {{ $label }}
        </a>
        @endforeach
    </div>

    {{-- ============ Section header ============ --}}
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
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
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
    <div class="bg-maroon-50 border border-maroon-200 rounded-xl p-4 mb-6">
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
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
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

    {{-- ============ Tables ============ --}}
    @foreach($report['tables'] as $table)
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-6 overflow-hidden">
        <div class="px-4 pt-4">
            <h3 class="text-sm font-semibold text-gray-900">{{ $table['title'] }}</h3>
            @if($table['note'])
            <p class="text-xs text-gray-400 mt-0.5">{{ $table['note'] }}</p>
            @endif
        </div>
        <div class="overflow-x-auto mt-3">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-gray-500 border-y border-gray-100">
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
    </div>
    @endforeach

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