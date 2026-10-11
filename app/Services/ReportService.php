<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds every report on Admin -> Reports and in the PDF export.
 *
 * Layer 1 (touches the database):  filters(), dataset()
 * Layer 2 (plain PHP, no database): report(), everything()
 *
 * Definitions used everywhere:
 *   Sales       = every order that is NOT cancelled (same as the old report)
 *   Collected   = sales whose payment_status is "paid_in_full"
 *   Outstanding = sales - collected (e.g. cash orders still waiting for payment)
 */
class ReportService
{
    public const TYPES = [
        'overview'  => 'Overview',
        'sales'     => 'Sales by period',
        'gas'       => 'Sales by gas',
        'cylinder'  => 'Refill vs new cylinder',
        'cancelled' => 'Cancelled orders',
        'payments'  => 'Payments',
        'delivery'  => 'Pick-up & delivery',
        'expenses'  => 'Expenses',
        'inventory' => 'Inventory',
        'customers' => 'Customers & peak times',
    ];

    /* =====================================================================
     |  LAYER 1 — request filters + database loading
     * ===================================================================*/

    /** Clean the query string into safe filters. Never throws on bad input. */
    public function filters(array $input): array
    {
        $to = $this->validDate($input['to'] ?? null) ?? date('Y-m-d');
        $from = $this->validDate($input['from'] ?? null) ?? date('Y-m-d', strtotime($to . ' -29 days'));
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $days = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;

        $requested = $input['group'] ?? 'auto';
        $requested = is_string($requested) && in_array($requested, ['day', 'week', 'month'], true) ? $requested : 'auto';
        $group = $requested !== 'auto' ? $requested : ($days <= 31 ? 'day' : ($days <= 120 ? 'week' : 'month'));
        if ($group === 'day' && $days > 92) {
            $group = 'week';   // keep tables readable on long ranges
        }
        if ($group === 'week' && $days > 400) {
            $group = 'month';
        }

        $type = $input['report'] ?? 'overview';
        if (! is_string($type) || ! isset(self::TYPES[$type])) {
            $type = 'overview';
        }

        $product = $input['product'] ?? null;
        $product = is_scalar($product) && ctype_digit((string) $product) && (int) $product > 0 ? (int) $product : null;

        $prevTo = date('Y-m-d', strtotime($from . ' -1 day'));
        $prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' days'));

        return [
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'group' => $group,
            'group_requested' => $requested,
            'group_label' => ['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly'][$group],
            'type' => $type,
            'product' => $product,
            'prev_from' => $prevFrom,
            'prev_to' => $prevTo,
        ];
    }

    /** Query-string values to keep when switching tabs. */
    public function baseQuery(array $f): array
    {
        return array_filter([
            'from' => $f['from'],
            'to' => $f['to'],
            'group' => $f['group_requested'] === 'auto' ? null : $f['group_requested'],
            'product' => $f['product'],
        ], fn($v) => $v !== null);
    }

    public function presets(): array
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        return [
            ['label' => 'Today', 'from' => $today, 'to' => $today],
            ['label' => 'Yesterday', 'from' => $yesterday, 'to' => $yesterday],
            ['label' => 'Last 7 days', 'from' => date('Y-m-d', strtotime('-6 days')), 'to' => $today],
            ['label' => 'Last 30 days', 'from' => date('Y-m-d', strtotime('-29 days')), 'to' => $today],
            ['label' => 'This month', 'from' => date('Y-m-01'), 'to' => $today],
            ['label' => 'Last month', 'from' => date('Y-m-d', strtotime('first day of last month')), 'to' => date('Y-m-d', strtotime('last day of last month'))],
            ['label' => 'This year', 'from' => date('Y-01-01'), 'to' => $today],
        ];
    }

    /** Load every raw row the reports need for the chosen date range. */
    public function dataset(array $f): array
    {
        $from = $f['from'];
        $to = $f['to'];

        $orders = DB::table('orders')
            ->select('orders.*')
            ->selectRaw(Order::GRAND_TOTAL_SQL . ' as report_total')
            ->selectRaw('orders.created_at as ordered_at')
            ->whereDate('orders.order_date', '>=', $from)
            ->whereDate('orders.order_date', '<=', $to)
            ->orderBy('orders.order_date')->orderBy('orders.id')
            ->get()->map(fn($r) => (array) $r)->all();

        // Cylinder size comes from the order line when it has one, otherwise from the gas product.
        $cylinderColumn = Schema::hasColumn('order_items', 'cylinder_id') ? 'order_items.cylinder_id' : 'products.cylinder_id';

        $items = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('cylinders', 'cylinders.id', '=', $cylinderColumn)
            ->whereDate('orders.order_date', '>=', $from)
            ->whereDate('orders.order_date', '<=', $to)
            ->select([
                'order_items.order_id',
                'order_items.product_id',
                'order_items.quantity',
                'order_items.has_own_cylinder',
                'order_items.unit_gas_price',
                'order_items.unit_cylinder_fee',
                DB::raw('(order_items.unit_gas_price + order_items.unit_cylinder_fee) * order_items.quantity as line_total'),
                'orders.status as order_status',
                'orders.order_date',
                'products.name as product_name',
                'cylinders.name as cylinder_name',
            ])
            ->get()->map(fn($r) => (array) $r)->all();

        $expenses = DB::table('expenses')
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereDate('expenses.expense_date', '>=', $from)
            ->whereDate('expenses.expense_date', '<=', $to)
            ->select('expenses.id', 'expenses.amount', 'expenses.expense_date', 'expenses.note')
            ->selectRaw("COALESCE(expense_categories.name, 'Uncategorized') as category")
            ->orderBy('expenses.expense_date')
            ->get()->map(fn($r) => (array) $r)->all();

        // Names come from the models so first_name/last_name accessors keep working.
        $customerIds = array_values(array_unique(array_filter(array_column($orders, 'customer_id'))));
        $customers = $customerIds
            ? Customer::whereIn('id', $customerIds)->get()
            ->mapWithKeys(fn($c) => [$c->id => ['name' => $c->name, 'phone' => $c->phone]])->all()
            : [];

        $userIds = array_values(array_unique(array_filter(array_merge(
            array_column($orders, 'assigned_delivery_id'),
            array_column($orders, 'cancelled_by'),
        ))));
        $users = $userIds
            ? User::whereIn('id', $userIds)->get()->mapWithKeys(fn($u) => [$u->id => $u->name])->all()
            : [];

        $products = Product::with('cylinder')->orderBy('name')->get()->map(fn($p) => [
            'id' => (int) $p->id,
            'name' => $p->name,
            'cylinder' => $p->cylinder?->name,
            'current_stock' => (int) $p->current_stock,
            'max_capacity' => (int) $p->max_capacity,
            'reorder_level' => (int) $p->reorder_level,
            'status' => $p->stock_status,
            'label' => $p->stock_label,
            'is_active' => (bool) $p->is_active,
        ])->all();

        $proofs = $orders
            ? DB::table('order_proofs')->where('kind', 'delivery')
            ->whereIn('order_id', array_column($orders, 'id'))
            ->select('order_id', 'status')->get()->map(fn($r) => (array) $r)->all()
            : [];

        $movements = DB::table('stock_movements')
            ->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
            ->select('product_id', 'quantity_change', 'reason', 'created_at')
            ->get()->map(fn($r) => (array) $r)->all();

        $previousOrders = DB::table('orders')
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(' . Order::GRAND_TOTAL_SQL . '), 0) as t')
            ->where('status', '!=', 'cancelled')
            ->whereDate('order_date', '>=', $f['prev_from'])
            ->whereDate('order_date', '<=', $f['prev_to'])
            ->first();

        return [
            'orders' => $orders,
            'items' => $items,
            'expenses' => $expenses,
            'customers' => $customers,
            'users' => $users,
            'products' => $products,
            'proofs' => $proofs,
            'movements' => $movements,
            'previous' => [
                'orders' => (int) $previousOrders->c,
                'sales' => (float) $previousOrders->t,
                'expenses' => (float) DB::table('expenses')
                    ->whereDate('expense_date', '>=', $f['prev_from'])
                    ->whereDate('expense_date', '<=', $f['prev_to'])
                    ->sum('amount'),
            ],
        ];
    }

    /* =====================================================================
     |  LAYER 2 — pure PHP report builders (no database below this line)
     * ===================================================================*/

    public function report(array $f, array $d): array
    {
        return $this->section($f['type'], $this->prepare($f, $d));
    }

    /** Every report, in tab order (used by the full PDF). */
    public function everything(array $f, array $d): array
    {
        $c = $this->prepare($f, $d);

        return array_map(fn($type) => $this->section($type, $c), array_keys(self::TYPES));
    }

    private function section(string $type, array $c): array
    {
        $built = $this->{'build' . ucfirst($type)}($c);

        return ['key' => $type, 'title' => self::TYPES[$type]] + $built + [
            'description' => '',
            'kpis' => [],
            'insights' => [],
            'charts' => [],
            'tables' => [],
        ];
    }

    private function prepare(array $f, array $d): array
    {
        $orders = $d['orders'];
        $sales = array_values(array_filter($orders, fn($o) => $this->isSale($o)));
        $items = $d['items'];

        return [
            'f' => $f,
            'd' => $d,
            'orders' => $orders,
            'sales' => $sales,
            'cancelled' => array_values(array_filter($orders, fn($o) => ! $this->isSale($o))),
            'saleItems' => array_values(array_filter($items, fn($i) => ($i['order_status'] ?? '') !== 'cancelled')),
            'cancelledItems' => array_values(array_filter($items, fn($i) => ($i['order_status'] ?? '') === 'cancelled')),
            'keys' => $this->periodKeys($f),
            'salesTotal' => $this->sumBy($sales, fn($o) => $this->amt($o)),
            'collected' => $this->sumBy($sales, fn($o) => $this->isPaid($o) ? $this->amt($o) : 0),
            'expensesTotal' => $this->sumBy($d['expenses'], fn($e) => (float) $e['amount']),
        ];
    }

    /* ---------- small helpers ---------- */

    private function validDate(mixed $v): ?string
    {
        if (! is_string($v)) {
            return null;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $v);

        return $d && $d->format('Y-m-d') === $v ? $v : null;
    }

    private function amt(array $o): float
    {
        return (float) ($o['report_total'] ?? 0);
    }
    private function isSale(array $o): bool
    {
        return ($o['status'] ?? '') !== 'cancelled';
    }
    private function isPaid(array $o): bool
    {
        return ($o['payment_status'] ?? '') === 'paid_in_full';
    }
    private function isPickup(array $o): bool
    {
        return ($o['fulfillment_type'] ?? 'delivery') === 'pickup';
    }
    private function qty(array $i): int
    {
        return (int) ($i['quantity'] ?? 0);
    }
    private function own(array $i): bool
    {
        return (bool) (int) ($i['has_own_cylinder'] ?? 1);
    }
    private function gasAmt(array $i): float
    {
        return (float) ($i['unit_gas_price'] ?? 0) * $this->qty($i);
    }
    private function cylAmt(array $i): float
    {
        return (float) ($i['unit_cylinder_fee'] ?? 0) * $this->qty($i);
    }
    private function lineAmt(array $i): float
    {
        return (float) ($i['line_total'] ?? 0);
    }
    private function day(mixed $v): string
    {
        return substr((string) $v, 0, 10);
    }

    private function sumBy(array $rows, callable $fn): float
    {
        $t = 0.0;
        foreach ($rows as $r) {
            $t += $fn($r);
        }

        return $t;
    }

    private function money(float $v): string
    {
        return ($v < 0 ? '-' : '') . '₱' . number_format(abs($v), 2);
    }
    private function money0(float $v): string
    {
        return abs($v) < 0.005 ? '—' : $this->money($v);
    }
    private function num(int|float $v): string
    {
        return number_format($v);
    }
    private function num0(int|float $v): string
    {
        return $v == 0 ? '—' : number_format($v);
    }
    private function pct(float $part, float $whole): string
    {
        return $whole > 0 ? number_format($part / $whole * 100, 1) . '%' : '—';
    }
    private function human(?string $s): string
    {
        return $s === null || $s === '' ? '—' : ucfirst(str_replace('_', ' ', strtolower($s)));
    }
    private function badge(string $text, string $tone): array
    {
        return ['t' => $text, 'tone' => $tone];
    }

    private function delta(float $cur, float $prev): ?string
    {
        if ($prev > 0) {
            $p = ($cur - $prev) / $prev * 100;

            return ($p >= 0 ? '▲ ' : '▼ ') . number_format(abs($p), 1) . '% vs previous period';
        }

        return $cur > 0 ? 'Nothing recorded in the previous period' : null;
    }

    private function kpi(string $label, string $value, ?string $sub = null, string $tone = 'default'): array
    {
        return ['label' => $label, 'value' => $value, 'sub' => $sub, 'tone' => $tone];
    }

    private function cols(array $labels): array
    {
        return array_map(
            fn($l) => str_starts_with($l, '>') ? ['label' => substr($l, 1), 'align' => 'right'] : ['label' => $l, 'align' => 'left'],
            $labels
        );
    }

    private function table(string $title, array $labels, array $rows, ?array $footer = null, ?string $note = null): array
    {
        return ['title' => $title, 'columns' => $this->cols($labels), 'rows' => $rows, 'footer' => $footer, 'note' => $note];
    }

    /** $datasets: [['label'=>, 'data'=>[], 'tone'=>'maroon|green|amber|gray|red', 'type'=>'line'(optional)]] */
    private function chart(string $title, string $type, array $labels, array $datasets, string $unit = 'count', array $opts = []): array
    {
        $values = array_merge(...array_map(fn($ds) => $ds['data'], $datasets ?: [['data' => []]]));
        $hasData = (bool) array_filter($values, fn($v) => (float) $v != 0.0);

        return ['title' => $title, 'type' => $type, 'labels' => array_values($labels), 'datasets' => $datasets, 'unit' => $unit, 'empty' => ! $hasData] + $opts;
    }

    /* ---------- period buckets (day / week / month) ---------- */

    private function periodKey(string $date, string $group): string
    {
        $ts = strtotime(substr($date, 0, 10));

        return match ($group) {
            'week' => date('o-\WW', $ts),
            'month' => date('Y-m', $ts),
            default => date('Y-m-d', $ts),
        };
    }

    private function periodLabel(string $key, string $group, bool $short = false): string
    {
        if ($group === 'month') {
            return date('M Y', strtotime($key . '-01'));
        }
        if ($group === 'week') {
            [$y, $w] = array_map('intval', explode('-W', $key));
            $monday = (new \DateTimeImmutable())->setISODate($y, $w);

            return $short ? $monday->format('M j') : 'Week of ' . $monday->format('M j, Y');
        }
        $ts = strtotime($key);

        return $short ? date('M j', $ts) : date('D, M j, Y', $ts);
    }

    /** Every period between from and to, including empty ones, so charts have no gaps. */
    private function periodKeys(array $f): array
    {
        $keys = [];
        $end = strtotime($f['to']);
        for ($t = strtotime($f['from']); $t <= $end; $t = strtotime('+1 day', $t)) {
            $keys[$this->periodKey(date('Y-m-d', $t), $f['group'])] = true;
        }

        return $keys;
    }

    private function shortLabels(array $c): array
    {
        return array_map(fn($k) => $this->periodLabel($k, $c['f']['group'], true), array_keys($c['keys']));
    }

    /** One accumulator row per period: orders, units, gas/cylinder sales, collected, expenses... */
    private function periodRows(array $c): array
    {
        $g = $c['f']['group'];
        $acc = [];
        foreach ($c['keys'] as $k => $_) {
            $acc[$k] = [
                'orders' => 0,
                'units' => 0,
                'gas' => 0.0,
                'cyl' => 0.0,
                'total' => 0.0,
                'paid' => 0.0,
                'cancelled' => 0,
                'expenses' => 0.0,
                'entries' => 0
            ];
        }
        foreach ($c['orders'] as $o) {
            $k = $this->periodKey($o['order_date'], $g);
            if (! isset($acc[$k])) {
                continue;
            }
            if ($this->isSale($o)) {
                $acc[$k]['orders']++;
                $acc[$k]['total'] += $this->amt($o);
                if ($this->isPaid($o)) {
                    $acc[$k]['paid'] += $this->amt($o);
                }
            } else {
                $acc[$k]['cancelled']++;
            }
        }
        foreach ($c['saleItems'] as $i) {
            $k = $this->periodKey($i['order_date'], $g);
            if (isset($acc[$k])) {
                $acc[$k]['units'] += $this->qty($i);
                $acc[$k]['gas'] += $this->gasAmt($i);
                $acc[$k]['cyl'] += $this->cylAmt($i);
            }
        }
        foreach ($c['d']['expenses'] as $e) {
            $k = $this->periodKey($e['expense_date'], $g);
            if (isset($acc[$k])) {
                $acc[$k]['expenses'] += (float) $e['amount'];
                $acc[$k]['entries']++;
            }
        }

        return $acc;
    }

    /** Sale items, narrowed to one gas when the "Gas" filter is used. */
    private function scopedItems(array $c): array
    {
        $p = $c['f']['product'];

        return $p ? array_values(array_filter($c['saleItems'], fn($i) => (int) ($i['product_id'] ?? 0) === $p)) : $c['saleItems'];
    }

    private function productName(array $c, int $id): string
    {
        foreach ($c['d']['products'] as $p) {
            if ($p['id'] === $id) {
                return $p['name'];
            }
        }

        return 'Gas #' . $id;
    }

    private function rangeText(array $c): string
    {
        return date('M j, Y', strtotime($c['f']['from'])) . ' – ' . date('M j, Y', strtotime($c['f']['to']));
    }
    /** Weekday names keyed 1 (Monday) to 7 (Sunday), the same numbers date('N') returns. */
    private function weekdayNames(): array
    {
        return [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
    }

    /** Orders and sales per weekday: [1 => ['n' => , 'total' => ], ... 7 => ...]  (1 = Monday ... 7 = Sunday). */
    private function weekdayStats(array $sales): array
    {
        $wd = [];
        for ($i = 1; $i <= 7; $i++) {
            $wd[$i] = ['n' => 0, 'total' => 0.0];
        }
        foreach ($sales as $o) {
            $w = (int) date('N', strtotime($this->day($o['order_date'])));
            $wd[$w]['n']++;
            $wd[$w]['total'] += $this->amt($o);
        }

        return $wd;
    }

    /** Weekday number (1-7) with the most orders; a tie goes to the higher sales. Null when there are no orders. */
    private function busiestWeekday(array $wd): ?int
    {
        $best = null;
        foreach ($wd as $w => $r) {
            if ($r['n'] === 0) {
                continue;
            }
            if ($best === null || [$r['n'], $r['total']] > [$wd[$best]['n'], $wd[$best]['total']]) {
                $best = $w;
            }
        }

        return $best;
    }
    /* =====================================================================
     |  1. OVERVIEW
     * ===================================================================*/
    private function buildOverview(array $c): array
    {
        $d = $c['d'];
        $prev = $d['previous'];
        $salesTotal = $c['salesTotal'];
        $collected = $c['collected'];
        $outstanding = $salesTotal - $collected;
        $expenses = $c['expensesTotal'];
        $orderCount = count($c['sales']);
        $unpaidCount = count(array_filter($c['sales'], fn($o) => ! $this->isPaid($o)));
        $units = array_sum(array_map(fn($i) => $this->qty($i), $c['saleItems']));
        $newCyl = array_sum(array_map(fn($i) => $this->own($i) ? 0 : $this->qty($i), $c['saleItems']));
        $cancelCount = count($c['cancelled']);
        $cancelValue = $this->sumBy($c['cancelled'], fn($o) => $this->amt($o));
        $net = $salesTotal - $expenses;

        $kpis = [
            $this->kpi('Total sales', $this->money($salesTotal), $this->delta($salesTotal, $prev['sales'])),
            $this->kpi('Collected', $this->money($collected), $this->pct($collected, $salesTotal) . ' of sales', 'green'),
            $this->kpi('Outstanding', $this->money($outstanding), $unpaidCount . ' order(s) waiting for payment', $outstanding > 0.005 ? 'yellow' : 'green'),
            $this->kpi('Expenses', $this->money($expenses), $this->delta($expenses, $prev['expenses'])),
            $this->kpi('Net income', $this->money($net), 'Cash basis: ' . $this->money($collected - $expenses), $net >= 0 ? 'green' : 'red'),
            $this->kpi('Orders', $this->num($orderCount), $orderCount ? 'Average ' . $this->money($salesTotal / $orderCount) . ' per order' : null),
            $this->kpi('Gas units sold', $this->num($units), $this->num($newCyl) . ' sold with a new cylinder'),
            $this->kpi('Cancelled orders', $this->num($cancelCount), $this->pct($cancelCount, count($c['orders'])) . ' of all orders · ' . $this->money($cancelValue) . ' lost', $cancelCount ? 'red' : 'green'),
        ];

        // ----- quick insights -----
        $insights = [];
        $perDay = [];
        foreach ($c['sales'] as $o) {
            $k = $this->day($o['order_date']);
            $perDay[$k] = ($perDay[$k] ?? 0) + $this->amt($o);
        }
        if ($perDay) {
            arsort($perDay);
            $insights[] = 'Best day: ' . date('D, M j, Y', strtotime((string) array_key_first($perDay))) . ' with ' . $this->money(reset($perDay)) . ' in sales.';
        }
        $byGas = $this->revenueByGas($c['saleItems']);
        if ($byGas) {
            $top = array_key_first($byGas);
            $insights[] = 'Top-selling gas by revenue: ' . $top . ' (' . $this->money($byGas[$top]) . ', ' . $this->pct($byGas[$top], array_sum($byGas)) . ' of item sales).';
        }
        $methods = [];
        foreach ($c['sales'] as $o) {
            $m = strtoupper((string) ($o['payment_method'] ?? '?'));
            $methods[$m] = ($methods[$m] ?? 0) + 1;
        }
        if ($methods) {
            arsort($methods);
            $insights[] = 'Most used payment method: ' . array_key_first($methods) . ' (' . reset($methods) . ' of ' . $orderCount . ' orders).';
        }
        $wd = $this->weekdayStats($c['sales']);
        $busy = $this->busiestWeekday($wd);
        if ($busy !== null) {
            $insights[] = 'Busiest day of the week: ' . $this->weekdayNames()[$busy] . ' (' . $wd[$busy]['n'] . ' orders, ' . $this->money($wd[$busy]['total']) . ').';
        }
        if ($cancelCount) {
            $insights[] = $cancelCount . ' order(s) were cancelled (' . $this->money($cancelValue) . '). Open the "Cancelled orders" tab to see why.';
        }
        if ($outstanding > 0.005) {
            $insights[] = $this->money($outstanding) . ' is still waiting to be collected from ' . $unpaidCount . ' order(s).';
        }
        $low = count(array_filter($d['products'], fn($p) => $p['is_active'] && $p['status'] !== 'green'));
        if ($low) {
            $insights[] = $low . ' gas product(s) are at or below the reorder level. Open the "Inventory" tab.';
        }

        // ----- charts -----
        $rows = $this->periodRows($c);
        $statusCounts = ['pending' => 0, 'out_for_delivery' => 0, 'completed' => 0, 'cancelled' => 0];
        foreach ($c['orders'] as $o) {
            $s = $o['status'] ?? 'pending';
            $statusCounts[$s] = ($statusCounts[$s] ?? 0) + 1;
        }
        $topGas = array_slice($byGas, 0, 6, true);

        return [
            'description' => 'A one-page summary of how the business did in this period, compared with the period just before it.',
            'kpis' => $kpis,
            'insights' => $insights,
            'charts' => [
                $this->chart('Sales vs expenses (' . strtolower($c['f']['group_label']) . ')', 'bar', $this->shortLabels($c), [
                    ['label' => 'Sales', 'data' => array_column($rows, 'total'), 'tone' => 'maroon'],
                    ['label' => 'Expenses', 'data' => array_column($rows, 'expenses'), 'tone' => 'amber', 'type' => 'line'],
                ], 'money', ['wide' => true]),
                $this->chart(
                    'Orders by status',
                    'doughnut',
                    array_map(fn($s) => $this->human($s), array_keys($statusCounts)),
                    [['label' => 'Orders', 'data' => array_values($statusCounts)]]
                ),
                $this->chart(
                    'Top gas by revenue',
                    'hbar',
                    array_keys($topGas),
                    [['label' => 'Revenue', 'data' => array_values($topGas), 'tone' => 'maroon']],
                    'money'
                ),
            ],
            'tables' => [],
        ];
    }

    /** [gas name => total revenue], biggest first. */
    private function revenueByGas(array $items): array
    {
        $by = [];
        foreach ($items as $i) {
            $n = $i['product_name'] ?? 'Deleted product';
            $by[$n] = ($by[$n] ?? 0) + $this->lineAmt($i);
        }
        arsort($by);

        return $by;
    }

    /* =====================================================================
     |  2. SALES BY PERIOD (daily / weekly / monthly)
     * ===================================================================*/
    private function buildSales(array $c): array
    {
        $g = $c['f']['group'];
        $rows = $this->periodRows($c);
        $t = ['orders' => 0, 'units' => 0, 'gas' => 0.0, 'cyl' => 0.0, 'total' => 0.0, 'paid' => 0.0, 'expenses' => 0.0, 'cancelled' => 0];
        $out = [];
        foreach ($rows as $k => $r) {
            foreach ($t as $key => $_) {
                $t[$key] += $r[$key];
            }
            $out[] = [
                $this->periodLabel($k, $g),
                $this->num0($r['orders']),
                $this->num0($r['units']),
                $this->money0($r['gas']),
                $this->money0($r['cyl']),
                $this->money0($r['total']),
                $this->money0($r['paid']),
                $this->money0($r['total'] - $r['paid']),
                $this->money0($r['expenses']),
                $this->money0($r['total'] - $r['expenses']),
                $this->num0($r['cancelled']),
            ];
        }
        $footer = [
            'Total',
            $this->num($t['orders']),
            $this->num($t['units']),
            $this->money($t['gas']),
            $this->money($t['cyl']),
            $this->money($t['total']),
            $this->money($t['paid']),
            $this->money($t['total'] - $t['paid']),
            $this->money($t['expenses']),
            $this->money($t['total'] - $t['expenses']),
            $this->num($t['cancelled'])
        ];

        $best = null;
        foreach ($rows as $k => $r) {
            if ($best === null || $r['total'] > $rows[$best]['total']) {
                $best = $k;
            }
        }
        $active = count(array_filter($rows, fn($r) => $r['orders'] > 0));

        return [
            'description' => $c['f']['group_label'] . ' sales, collections, expenses and net income. Change "Group by" above to switch between daily, weekly and monthly.',
            'kpis' => [
                $this->kpi('Total sales', $this->money($t['total'])),
                $this->kpi('Orders', $this->num($t['orders']), $t['orders'] ? 'Average ' . $this->money($t['total'] / $t['orders']) : null),
                $this->kpi(
                    'Best ' . $g,
                    $best !== null && $rows[$best]['total'] > 0 ? $this->periodLabel($best, $g, true) : '—',
                    $best !== null && $rows[$best]['total'] > 0 ? $this->money($rows[$best]['total']) : null,
                    'green'
                ),
                $this->kpi('Active ' . $g . 's', $active . ' of ' . count($rows), 'with at least one order'),
            ],
            'charts' => [
                $this->chart('Sales vs expenses', 'bar', $this->shortLabels($c), [
                    ['label' => 'Sales', 'data' => array_column($rows, 'total'), 'tone' => 'maroon'],
                    ['label' => 'Expenses', 'data' => array_column($rows, 'expenses'), 'tone' => 'amber', 'type' => 'line'],
                ], 'money', ['wide' => true]),
                $this->chart('Orders vs cancelled', 'bar', $this->shortLabels($c), [
                    ['label' => 'Orders', 'data' => array_column($rows, 'orders'), 'tone' => 'maroon'],
                    ['label' => 'Cancelled', 'data' => array_column($rows, 'cancelled'), 'tone' => 'red'],
                ]),
            ],
            'tables' => [
                $this->table(
                    $c['f']['group_label'] . ' summary',
                    ['Period', '>Orders', '>Gas units', '>Gas sales', '>Cylinder sales', '>Total sales', '>Collected', '>Outstanding', '>Expenses', '>Net', '>Cancelled'],
                    $out,
                    $footer,
                    'Net = total sales − expenses for that period. A dash means zero.'
                ),
            ],
        ];
    }

    /* =====================================================================
     |  3. SALES BY GAS  (all gas, or one gas with the "Gas" filter)
     * ===================================================================*/
    private function buildGas(array $c): array
    {
        $f = $c['f'];
        $g = $f['group'];
        $items = $this->scopedItems($c);
        $blank = fn($name) => ['name' => $name, 'orders' => [], 'units' => 0, 'refill' => 0, 'new' => 0, 'gas' => 0.0, 'cyl' => 0.0, 'total' => 0.0];

        $by = [];
        foreach ($items as $i) {
            $k = (int) ($i['product_id'] ?? 0);
            $by[$k] ??= $blank($i['product_name'] ?? 'Deleted product');
            $q = $this->qty($i);
            $by[$k]['orders'][(int) $i['order_id']] = true;
            $by[$k]['units'] += $q;
            $by[$k][$this->own($i) ? 'refill' : 'new'] += $q;
            $by[$k]['gas'] += $this->gasAmt($i);
            $by[$k]['cyl'] += $this->cylAmt($i);
            $by[$k]['total'] += $this->lineAmt($i);
        }
        if (! $f['product']) {   // show active gas with zero sales too, so slow sellers are visible
            foreach ($c['d']['products'] as $p) {
                if ($p['is_active'] && ! isset($by[$p['id']])) {
                    $by[$p['id']] = $blank($p['name']);
                }
            }
        }
        uasort($by, fn($a, $b) => [$b['total'], $a['name']] <=> [$a['total'], $b['name']]);

        $grand = array_sum(array_column($by, 'total'));
        $sum = fn($key) => array_sum(array_column($by, $key));
        $rows = [];
        foreach ($by as $r) {
            $rows[] = [
                $r['name'],
                $this->num0(count($r['orders'])),
                $this->num0($r['units']),
                $this->num0($r['refill']),
                $this->num0($r['new']),
                $this->money0($r['gas']),
                $this->money0($r['cyl']),
                $this->money0($r['total']),
                $this->pct($r['total'], $grand),
                $r['units'] ? $this->money($r['gas'] / $r['units']) : '—'
            ];
        }
        $orderIds = [];
        foreach ($by as $r) {
            $orderIds += $r['orders'];
        }
        $footer = [
            'Total',
            $this->num(count($orderIds)),
            $this->num($sum('units')),
            $this->num($sum('refill')),
            $this->num($sum('new')),
            $this->money($sum('gas')),
            $this->money($sum('cyl')),
            $this->money($grand),
            $grand > 0 ? '100.0%' : '—',
            $sum('units') ? $this->money($sum('gas') / $sum('units')) : '—'
        ];

        // units per period (all gas, or the selected gas)
        $periods = [];
        foreach ($c['keys'] as $k => $_) {
            $periods[$k] = ['units' => 0, 'refill' => 0, 'new' => 0, 'gas' => 0.0, 'cyl' => 0.0, 'total' => 0.0];
        }
        $perProduct = [];
        foreach ($items as $i) {
            $k = $this->periodKey($i['order_date'], $g);
            $q = $this->qty($i);
            if (! isset($periods[$k])) {
                continue;
            }
            $periods[$k]['units'] += $q;
            $periods[$k][$this->own($i) ? 'refill' : 'new'] += $q;
            $periods[$k]['gas'] += $this->gasAmt($i);
            $periods[$k]['cyl'] += $this->cylAmt($i);
            $periods[$k]['total'] += $this->lineAmt($i);
            $perProduct[$i['product_name'] ?? 'Deleted product'][$k] = ($perProduct[$i['product_name'] ?? 'Deleted product'][$k] ?? 0) + $q;
        }
        $periodRows = [];
        foreach ($periods as $k => $r) {
            $periodRows[] = [
                $this->periodLabel($k, $g),
                $this->num0($r['units']),
                $this->num0($r['refill']),
                $this->num0($r['new']),
                $this->money0($r['gas']),
                $this->money0($r['cyl']),
                $this->money0($r['total'])
            ];
        }
        $pf = [
            'Total',
            $this->num(array_sum(array_column($periods, 'units'))),
            $this->num(array_sum(array_column($periods, 'refill'))),
            $this->num(array_sum(array_column($periods, 'new'))),
            $this->money(array_sum(array_column($periods, 'gas'))),
            $this->money(array_sum(array_column($periods, 'cyl'))),
            $this->money(array_sum(array_column($periods, 'total')))
        ];

        // charts
        $selected = $f['product'] ? $this->productName($c, $f['product']) : null;
        $charts = [];
        if ($selected) {
            $charts[] = $this->chart('Units sold (' . strtolower($f['group_label']) . ')', 'bar', $this->shortLabels($c), [
                ['label' => 'Refill only', 'data' => array_column($periods, 'refill'), 'tone' => 'maroon'],
                ['label' => 'With new cylinder', 'data' => array_column($periods, 'new'), 'tone' => 'amber'],
            ], 'count', ['stacked' => true, 'wide' => true]);
            $charts[] = $this->chart(
                'Revenue (' . strtolower($f['group_label']) . ')',
                'bar',
                $this->shortLabels($c),
                [['label' => 'Revenue', 'data' => array_column($periods, 'total'), 'tone' => 'maroon']],
                'money'
            );
        } else {
            $withSales = array_filter($by, fn($r) => $r['total'] > 0);
            $charts[] = $this->chart(
                'Revenue by gas',
                'hbar',
                array_column($withSales, 'name'),
                [['label' => 'Revenue', 'data' => array_column($withSales, 'total'), 'tone' => 'maroon']],
                'money'
            );
            $withUnits = array_filter($by, fn($r) => $r['units'] > 0);
            $charts[] = $this->chart(
                'Share of units sold',
                'doughnut',
                array_column($withUnits, 'name'),
                [['label' => 'Units', 'data' => array_column($withUnits, 'units')]]
            );
            // stacked units per period: top 6 gas + "Others"
            $totals = array_map('array_sum', $perProduct);
            arsort($totals);
            $top = array_slice(array_keys($totals), 0, 6);
            $series = [];
            foreach ($top as $name) {
                $series[] = ['label' => $name, 'data' => array_map(fn($k) => $perProduct[$name][$k] ?? 0, array_keys($c['keys']))];
            }
            $others = array_diff(array_keys($totals), $top);
            if ($others) {
                $series[] = ['label' => 'Others', 'data' => array_map(
                    fn($k) => array_sum(array_map(fn($n) => $perProduct[$n][$k] ?? 0, $others)),
                    array_keys($c['keys'])
                )];
            }
            $charts[] = $this->chart('Units sold per ' . $g . ', by gas', 'bar', $this->shortLabels($c), $series, 'count', ['stacked' => true, 'wide' => true]);
        }

        $units = array_sum(array_column($by, 'units'));
        $best = $by ? reset($by) : null;

        return [
            'description' => $selected
                ? 'Sales of ' . $selected . ' only, with refill and new-cylinder sales split out. Pick "All gas" in the filter to compare every gas.'
                : 'How much of each gas was sold, how it was sold (refill or with a new cylinder) and how much money it made. Use the "Gas" filter to look at one gas.',
            'kpis' => [
                $this->kpi($selected ? $selected . ' revenue' : 'Gas revenue', $this->money($grand)),
                $this->kpi('Units sold', $this->num($units), $this->num($sum('refill')) . ' refill · ' . $this->num($sum('new')) . ' with new cylinder'),
                $this->kpi('Best seller', $best && $best['total'] > 0 ? $best['name'] : '—', $best && $best['total'] > 0 ? $this->money($best['total']) : null, 'green'),
                $this->kpi('Average gas price', $units ? $this->money($sum('gas') / $units) : '—', 'per unit, before cylinder fees'),
            ],
            'charts' => $charts,
            'tables' => [
                $this->table(
                    $selected ? 'Sales of ' . $selected : 'Sales by gas',
                    ['Gas', '>Orders', '>Units', '>Refill only', '>New cylinder', '>Gas sales', '>Cylinder sales', '>Total', '>% of sales', '>Avg gas price'],
                    $rows,
                    $footer,
                    'Refill only = customer exchanged their own cylinder. New cylinder = customer also bought the cylinder.'
                ),
                $this->table(($selected ? $selected . ' — ' : 'All gas — ') . strtolower($f['group_label']) . ' breakdown',
                    ['Period', '>Units', '>Refill only', '>New cylinder', '>Gas sales', '>Cylinder sales', '>Total'],
                    $periodRows,
                    $pf
                ),
            ],
        ];
    }

    /* =====================================================================
     |  4. REFILL ONLY vs WITH A NEW CYLINDER
     * ===================================================================*/
    private function buildCylinder(array $c): array
    {
        $f = $c['f'];
        $g = $f['group'];
        $items = $this->scopedItems($c);
        $selected = $f['product'] ? $this->productName($c, $f['product']) : null;

        $byGas = [];
        $bySize = [];
        $periods = [];
        foreach ($c['keys'] as $k => $_) {
            $periods[$k] = ['refill' => 0, 'new' => 0, 'cyl' => 0.0];
        }
        $t = ['r_units' => 0, 'r_gas' => 0.0, 'n_units' => 0, 'n_gas' => 0.0, 'n_cyl' => 0.0];

        foreach ($items as $i) {
            $q = $this->qty($i);
            $own = $this->own($i);
            $name = $i['product_name'] ?? 'Deleted product';
            $size = $i['cylinder_name'] ?? 'Unknown size';
            $byGas[$name] ??= ['r_units' => 0, 'r_gas' => 0.0, 'n_units' => 0, 'n_gas' => 0.0, 'n_cyl' => 0.0];
            $bySize[$size] ??= ['sold' => 0, 'revenue' => 0.0, 'refills' => 0];
            $pk = $this->periodKey($i['order_date'], $g);

            if ($own) {
                $byGas[$name]['r_units'] += $q;
                $byGas[$name]['r_gas'] += $this->gasAmt($i);
                $bySize[$size]['refills'] += $q;
                $t['r_units'] += $q;
                $t['r_gas'] += $this->gasAmt($i);
                if (isset($periods[$pk])) {
                    $periods[$pk]['refill'] += $q;
                }
            } else {
                $byGas[$name]['n_units'] += $q;
                $byGas[$name]['n_gas'] += $this->gasAmt($i);
                $byGas[$name]['n_cyl'] += $this->cylAmt($i);
                $bySize[$size]['sold'] += $q;
                $bySize[$size]['revenue'] += $this->cylAmt($i);
                $t['n_units'] += $q;
                $t['n_gas'] += $this->gasAmt($i);
                $t['n_cyl'] += $this->cylAmt($i);
                if (isset($periods[$pk])) {
                    $periods[$pk]['new'] += $q;
                    $periods[$pk]['cyl'] += $this->cylAmt($i);
                }
            }
        }
        uasort($byGas, fn($a, $b) => ($b['r_units'] + $b['n_units']) <=> ($a['r_units'] + $a['n_units']));
        uasort($bySize, fn($a, $b) => $b['sold'] <=> $a['sold']);

        $gasRows = [];
        foreach ($byGas as $name => $r) {
            $units = $r['r_units'] + $r['n_units'];
            $gasRows[] = [
                $name,
                $this->num0($r['r_units']),
                $this->money0($r['r_gas']),
                $this->num0($r['n_units']),
                $this->money0($r['n_gas']),
                $this->money0($r['n_cyl']),
                $this->money0($r['n_gas'] + $r['n_cyl']),
                $this->money0($r['r_gas'] + $r['n_gas'] + $r['n_cyl']),
                $this->pct($r['n_units'], $units)
            ];
        }
        $allUnits = $t['r_units'] + $t['n_units'];
        $gasFooter = [
            'Total',
            $this->num($t['r_units']),
            $this->money($t['r_gas']),
            $this->num($t['n_units']),
            $this->money($t['n_gas']),
            $this->money($t['n_cyl']),
            $this->money($t['n_gas'] + $t['n_cyl']),
            $this->money($t['r_gas'] + $t['n_gas'] + $t['n_cyl']),
            $this->pct($t['n_units'], $allUnits)
        ];

        $sizeRows = [];
        foreach ($bySize as $size => $r) {
            $sizeRows[] = [$size, $this->num0($r['sold']), $this->money0($r['revenue']), $r['sold'] ? $this->money($r['revenue'] / $r['sold']) : '—', $this->num0($r['refills'])];
        }
        $sizeFooter = [
            'Total',
            $this->num(array_sum(array_column($bySize, 'sold'))),
            $this->money(array_sum(array_column($bySize, 'revenue'))),
            '',
            $this->num(array_sum(array_column($bySize, 'refills')))
        ];

        $periodRows = [];
        foreach ($periods as $k => $r) {
            $periodRows[] = [$this->periodLabel($k, $g), $this->num0($r['refill']), $this->num0($r['new']), $this->money0($r['cyl']), $this->pct($r['new'], $r['refill'] + $r['new'])];
        }

        return [
            'description' => ($selected ? 'Sales of ' . $selected . ' — ' : '')
                . '"Refill only" is a customer exchanging their own empty cylinder (gas price only). "New cylinder" is a customer who also bought the cylinder (gas price + cylinder fee).',
            'kpis' => [
                $this->kpi('Refill only (units)', $this->num($t['r_units']), $this->pct($t['r_units'], $allUnits) . ' of units · ' . $this->money($t['r_gas']) . ' gas sales'),
                $this->kpi('With new cylinder (units)', $this->num($t['n_units']), $this->pct($t['n_units'], $allUnits) . ' of units · ' . $this->money($t['n_gas']) . ' gas sales', 'yellow'),
                $this->kpi('Cylinder revenue', $this->money($t['n_cyl']), 'Cylinders sold to customers', 'green'),
                $this->kpi('Revenue per unit', $allUnits ? $this->money(($t['r_gas'] + $t['n_gas'] + $t['n_cyl']) / $allUnits) : '—', 'average of both kinds'),
            ],
            'charts' => [
                $this->chart(
                    'Units: refill vs new cylinder',
                    'doughnut',
                    ['Refill only', 'With new cylinder'],
                    [['label' => 'Units', 'data' => [$t['r_units'], $t['n_units']]]]
                ),
                $this->chart('By gas (units)', 'bar', array_keys($byGas), [
                    ['label' => 'Refill only', 'data' => array_column($byGas, 'r_units'), 'tone' => 'maroon'],
                    ['label' => 'With new cylinder', 'data' => array_column($byGas, 'n_units'), 'tone' => 'amber'],
                ]),
                $this->chart('Per ' . $g . ' (units)', 'bar', $this->shortLabels($c), [
                    ['label' => 'Refill only', 'data' => array_column($periods, 'refill'), 'tone' => 'maroon'],
                    ['label' => 'With new cylinder', 'data' => array_column($periods, 'new'), 'tone' => 'amber'],
                ], 'count', ['stacked' => true]),
            ],
            'tables' => [
                $this->table(
                    'Refill vs new cylinder, by gas',
                    ['Gas', '>Refill units', '>Refill gas sales', '>New-cyl units', '>New-cyl gas sales', '>Cylinder sales', '>New-cyl total', '>All sales', '>% new cylinder'],
                    $gasRows,
                    $gasFooter
                ),
                $this->table('Cylinders sold, by size', ['Cylinder size', '>Cylinders sold', '>Cylinder revenue', '>Avg price', '>Refills on own cylinder'], $sizeRows, $sizeFooter),
                $this->table(ucfirst($g) . ' breakdown', ['Period', '>Refill only', '>New cylinder', '>Cylinder revenue', '>% new cylinder'], $periodRows),
            ],
        ];
    }

    /* =====================================================================
     |  5. CANCELLED ORDERS — to understand WHY orders get cancelled
     * ===================================================================*/

    /** Reads the reason from a dedicated column if you have one, otherwise from the "Cancelled: ..." note. */
    private function cancelReason(array $o): string
    {
        foreach (['cancel_reason', 'cancellation_reason', 'cancelled_reason'] as $col) {
            if (! empty($o[$col])) {
                return trim((string) $o[$col]);
            }
        }
        if (! empty($o['notes']) && preg_match('/cancell?ed\s*:\s*(.+)$/is', (string) $o['notes'], $m)) {
            return trim($m[1]);
        }

        return '';
    }

    private function buildCancelled(array $c): array
    {
        $d = $c['d'];
        $g = $c['f']['group'];
        $orders = $c['cancelled'];
        $n = count($orders);
        $all = count($c['orders']);
        $value = $this->sumBy($orders, fn($o) => $this->amt($o));

        $paid = array_filter($orders, fn($o) => $this->isPaid($o));
        $dispatched = array_filter($orders, fn($o) => ! empty($o['assigned_delivery_id']));

        // ----- reasons (grouped so "Customer changed mind" and "customer changed mind." count together) -----
        $reasons = [];
        $noReason = 0;
        foreach ($orders as $o) {
            $raw = $this->cancelReason($o);
            if ($raw === '') {
                $noReason++;
            }
            $label = $raw === '' ? 'No reason recorded' : $raw;
            $norm = mb_strtolower(trim((string) preg_replace('/[\s.!]+$/u', '', (string) preg_replace('/\s+/u', ' ', $label))));
            $reasons[$norm] ??= ['label' => ucfirst($label), 'n' => 0, 'value' => 0.0];
            $reasons[$norm]['n']++;
            $reasons[$norm]['value'] += $this->amt($o);
        }
        uasort($reasons, fn($a, $b) => [$b['n'], $b['value']] <=> [$a['n'], $a['value']]);
        $reasonRows = [];
        foreach ($reasons as $r) {
            $reasonRows[] = [$r['label'], $this->num($r['n']), $this->money($r['value']), $this->pct($r['n'], $n)];
        }

        // ----- what was in the cancelled orders -----
        $itemsByOrder = [];
        $gasCancelled = [];
        foreach ($c['cancelledItems'] as $i) {
            $name = $i['product_name'] ?? 'Deleted product';
            $itemsByOrder[(int) $i['order_id']][] = $this->qty($i) . '× ' . $name . ($this->own($i) ? '' : ' + cylinder');
            $gasCancelled[$name] ??= ['orders' => [], 'units' => 0, 'value' => 0.0];
            $gasCancelled[$name]['orders'][(int) $i['order_id']] = true;
            $gasCancelled[$name]['units'] += $this->qty($i);
            $gasCancelled[$name]['value'] += $this->lineAmt($i);
        }
        uasort($gasCancelled, fn($a, $b) => $b['value'] <=> $a['value']);
        $gasRows = [];
        foreach ($gasCancelled as $name => $r) {
            $gasRows[] = [$name, $this->num(count($r['orders'])), $this->num($r['units']), $this->money($r['value'])];
        }

        // ----- profile: where do cancellations happen? -----
        $profile = [
            'Delivery orders' => fn($o) => ! $this->isPickup($o),
            'Pick-up orders' => fn($o) => $this->isPickup($o),
            'Paid with cash' => fn($o) => strtolower((string) ($o['payment_method'] ?? '')) === 'cash',
            'Paid with GCash' => fn($o) => strtolower((string) ($o['payment_method'] ?? '')) === 'gcash',
            'Already paid (check refund)' => fn($o) => $this->isPaid($o),
            'Not yet paid' => fn($o) => ! $this->isPaid($o),
            'After a driver was assigned' => fn($o) => ! empty($o['assigned_delivery_id']),
            'Before dispatch' => fn($o) => empty($o['assigned_delivery_id']),
        ];
        $profileRows = [];
        foreach ($profile as $label => $test) {
            $match = array_filter($orders, $test);
            $profileRows[] = [$label, $this->num0(count($match)), $this->money0($this->sumBy($match, fn($o) => $this->amt($o))), $this->pct(count($match), $n)];
        }

        // ----- the list of cancelled orders (newest first) -----
        $sorted = $orders;
        usort($sorted, fn($a, $b) => [$b['order_date'], $b['id']] <=> [$a['order_date'], $a['id']]);
        $listRows = [];
        foreach (array_slice($sorted, 0, 200) as $o) {
            $cust = $d['customers'][$o['customer_id'] ?? 0]['name'] ?? '—';
            $method = strtoupper((string) ($o['payment_method'] ?? '?'));
            $payment = $this->isPaid($o) ? $this->badge($method . ' · Paid', 'yellow') : $this->badge($method . ' · Unpaid', 'gray');
            $when = ! empty($o['cancelled_at']) ? date('M j, g:i A', strtotime((string) $o['cancelled_at'])) : '';
            $by = $d['users'][$o['cancelled_by'] ?? 0] ?? '';
            $reason = $this->cancelReason($o);
            $listRows[] = [
                $o['order_code'] ?? ('#' . $o['id']),
                date('M j, Y', strtotime($this->day($o['order_date']))),
                $cust,
                $this->isPickup($o) ? 'Pick-up' : 'Delivery',
                $payment,
                implode(', ', $itemsByOrder[(int) $o['id']] ?? ['—']),
                $this->money($this->amt($o)),
                $d['users'][$o['assigned_delivery_id'] ?? 0] ?? (empty($o['assigned_delivery_id']) ? 'Not assigned' : 'Staff #' . $o['assigned_delivery_id']),
                $reason === '' ? $this->badge('No reason', 'red') : $reason,
                trim($when . ($by ? ' · ' . $by : '')) ?: '—',
            ];
        }

        // ----- insights -----
        $insights = [];
        $topReason = $reasons ? reset($reasons) : null;
        if ($topReason && $n) {
            $insights[] = 'Most common reason: "' . $topReason['label'] . '" (' . $topReason['n'] . ' of ' . $n . ' cancellations, ' . $this->pct($topReason['n'], $n) . ').';
        }
        if ($noReason) {
            $insights[] = $noReason . ' cancellation(s) have no reason recorded. Ask staff to always pick a reason so this report can explain the pattern.';
        }
        if ($paid) {
            $insights[] = count($paid) . ' cancelled order(s) were already paid (' . $this->money($this->sumBy($paid, fn($o) => $this->amt($o))) . '). Confirm those customers were refunded or credited.';
        }
        if ($dispatched) {
            $insights[] = count($dispatched) . ' order(s) were cancelled after a delivery staff was already assigned. These cost the most time.';
        }
        if ($gasCancelled) {
            $insights[] = 'Gas most often cancelled (by value): ' . array_key_first($gasCancelled) . '.';
        }

        $rows = $this->periodRows($c);
        $topReasons = array_slice($reasons, 0, 8);

        return [
            'description' => 'Every cancelled order in this period, with the reason given, so you can spot patterns (stock problems, slow delivery, payment trouble, customers changing their mind).',
            'kpis' => [
                $this->kpi('Cancelled orders', $this->num($n), $this->pct($n, $all) . ' of ' . $this->num($all) . ' orders', $n ? 'red' : 'green'),
                $this->kpi('Value lost', $this->money($value), $n ? 'Average ' . $this->money($value / $n) . ' each' : null, $n ? 'red' : 'green'),
                $this->kpi('Already paid', $this->num(count($paid)), $paid ? 'Check refunds: ' . $this->money($this->sumBy($paid, fn($o) => $this->amt($o))) : null, $paid ? 'yellow' : 'green'),
                $this->kpi('After dispatch', $this->num(count($dispatched)), 'Driver already assigned', $dispatched ? 'yellow' : 'green'),
            ],
            'insights' => $insights,
            'charts' => [
                $this->chart(
                    'Why orders were cancelled',
                    'hbar',
                    array_column($topReasons, 'label'),
                    [['label' => 'Orders', 'data' => array_column($topReasons, 'n'), 'tone' => 'red']]
                ),
                $this->chart(
                    'Cancellations per ' . $g,
                    'bar',
                    $this->shortLabels($c),
                    [['label' => 'Cancelled', 'data' => array_column($rows, 'cancelled'), 'tone' => 'red']],
                    'count',
                    ['wide' => true]
                ),
            ],
            'tables' => [
                $this->table(
                    'Reasons',
                    ['Reason', '>Orders', '>Value', '>% of cancelled'],
                    $reasonRows,
                    null,
                    'Reasons are grouped ignoring capital letters and trailing full stops.'
                ),
                $this->table('Where cancellations happen', ['Group', '>Orders', '>Value', '>% of cancelled'], $profileRows),
                $this->table('Gas in cancelled orders', ['Gas', '>Orders', '>Units', '>Value'], $gasRows),
                $this->table(
                    'Cancelled orders',
                    ['Order', 'Order date', 'Customer', 'Type', 'Payment', 'Items', '>Amount', 'Delivery staff', 'Reason', 'Cancelled'],
                    $listRows,
                    null,
                    $n > 200 ? 'Showing the newest 200 of ' . $n . ' cancelled orders.' : null
                ),
            ],
        ];
    }

    /* =====================================================================
     |  6. PAYMENTS
     * ===================================================================*/
    private function buildPayments(array $c): array
    {
        $d = $c['d'];
        $g = $c['f']['group'];
        $sales = $c['sales'];
        $total = $c['salesTotal'];

        $byMethod = [];
        $byStatus = [];
        foreach ($sales as $o) {
            $m = strtolower((string) ($o['payment_method'] ?? 'unknown'));
            $byMethod[$m] ??= ['n' => 0, 'total' => 0.0, 'paid' => 0.0];
            $byMethod[$m]['n']++;
            $byMethod[$m]['total'] += $this->amt($o);
            if ($this->isPaid($o)) {
                $byMethod[$m]['paid'] += $this->amt($o);
            }
            $s = (string) ($o['payment_status'] ?? 'unknown');
            $byStatus[$s] ??= ['n' => 0, 'total' => 0.0];
            $byStatus[$s]['n']++;
            $byStatus[$s]['total'] += $this->amt($o);
        }
        ksort($byMethod);

        $methodRows = [];
        foreach ($byMethod as $m => $r) {
            $methodRows[] = [strtoupper($m), $this->num($r['n']), $this->money($r['total']), $this->money($r['paid']), $this->money0($r['total'] - $r['paid']), $this->pct($r['total'], $total)];
        }
        $methodFooter = ['Total', $this->num(count($sales)), $this->money($total), $this->money($c['collected']), $this->money0($total - $c['collected']), $total > 0 ? '100.0%' : '—'];

        $statusRows = [];
        foreach ($byStatus as $s => $r) {
            $statusRows[] = [$s === 'paid_in_full' ? $this->badge('Paid in full', 'green') : $this->badge($this->human($s), 'yellow'), $this->num($r['n']), $this->money($r['total']), $this->pct($r['total'], $total)];
        }

        // unpaid orders, oldest first
        $unpaid = array_values(array_filter($sales, fn($o) => ! $this->isPaid($o)));
        usort($unpaid, fn($a, $b) => [$a['order_date'], $a['id']] <=> [$b['order_date'], $b['id']]);
        $today = strtotime(date('Y-m-d'));
        $agingRows = [];
        foreach (array_slice($unpaid, 0, 50) as $o) {
            $days = max(0, (int) round(($today - strtotime($this->day($o['order_date']))) / 86400));
            $agingRows[] = [
                $o['order_code'] ?? ('#' . $o['id']),
                date('M j, Y', strtotime($this->day($o['order_date']))),
                $d['customers'][$o['customer_id'] ?? 0]['name'] ?? '—',
                strtoupper((string) ($o['payment_method'] ?? '?')),
                $this->isPickup($o) ? 'Pick-up' : 'Delivery',
                $this->human($o['status'] ?? ''),
                $this->money($this->amt($o)),
                $days === 0 ? 'Today' : $days . ' day(s)',
            ];
        }

        // per-period split by method
        $perMethod = [];
        foreach ($sales as $o) {
            $k = $this->periodKey($o['order_date'], $g);
            $m = strtolower((string) ($o['payment_method'] ?? 'unknown'));
            $perMethod[$m][$k] = ($perMethod[$m][$k] ?? 0) + $this->amt($o);
        }
        $series = [];
        $tones = ['cash' => 'maroon', 'gcash' => 'green'];
        foreach ($perMethod as $m => $byPeriod) {
            $series[] = ['label' => strtoupper($m), 'tone' => $tones[$m] ?? 'gray', 'data' => array_map(fn($k) => $byPeriod[$k] ?? 0, array_keys($c['keys']))];
        }

        $cash = $byMethod['cash']['total'] ?? 0.0;
        $gcash = $byMethod['gcash']['total'] ?? 0.0;
        $outstanding = $total - $c['collected'];

        return [
            'description' => 'How customers paid (cash or GCash), how much has actually been collected, and which orders are still waiting for payment.',
            'kpis' => [
                $this->kpi('Collected', $this->money($c['collected']), $this->pct($c['collected'], $total) . ' of sales', 'green'),
                $this->kpi('Outstanding', $this->money($outstanding), count($unpaid) . ' order(s) not paid yet', $outstanding > 0.005 ? 'yellow' : 'green'),
                $this->kpi('Cash sales', $this->money($cash), $this->pct($cash, $total) . ' of sales'),
                $this->kpi('GCash sales', $this->money($gcash), $this->pct($gcash, $total) . ' of sales'),
            ],
            'charts' => [
                $this->chart(
                    'Sales by payment method',
                    'doughnut',
                    array_map('strtoupper', array_keys($byMethod)),
                    [['label' => 'Sales', 'data' => array_column($byMethod, 'total')]],
                    'money'
                ),
                $this->chart('Sales per ' . $g . ' by payment method', 'bar', $this->shortLabels($c), $series, 'money', ['stacked' => true, 'wide' => true]),
            ],
            'tables' => [
                $this->table('By payment method', ['Method', '>Orders', '>Sales', '>Collected', '>Outstanding', '>% of sales'], $methodRows, $methodFooter),
                $this->table('By payment status', ['Status', '>Orders', '>Amount', '>% of sales'], $statusRows),
                $this->table(
                    'Orders waiting for payment',
                    ['Order', 'Order date', 'Customer', 'Method', 'Type', 'Order status', '>Amount', '>Waiting'],
                    $agingRows,
                    null,
                    'Oldest first. Cash on delivery stays here until the delivery photo is approved.' . (count($unpaid) > 50 ? ' Showing 50 of ' . count($unpaid) . '.' : '')
                ),
            ],
        ];
    }

    /* =====================================================================
     |  7. PICK-UP & DELIVERY
     * ===================================================================*/
    private function buildDelivery(array $c): array
    {
        $d = $c['d'];
        $users = $d['users'];

        // ----- pick-up vs delivery -----
        $types = [
            'delivery' => ['label' => 'Delivery', 'placed' => 0, 'completed' => 0, 'open' => 0, 'cancelled' => 0, 'sales' => 0.0],
            'pickup' => ['label' => 'Pick-up', 'placed' => 0, 'completed' => 0, 'open' => 0, 'cancelled' => 0, 'sales' => 0.0],
        ];
        foreach ($c['orders'] as $o) {
            $k = $this->isPickup($o) ? 'pickup' : 'delivery';
            $types[$k]['placed']++;
            if (! $this->isSale($o)) {
                $types[$k]['cancelled']++;
                continue;
            }
            $types[$k]['sales'] += $this->amt($o);
            if ($o['status'] === 'completed') {
                $types[$k]['completed']++;
            } else {
                $types[$k]['open']++;
            }
        }
        $typeRows = [];
        foreach ($types as $r) {
            $net = $r['placed'] - $r['cancelled'];
            $typeRows[] = [
                $r['label'],
                $this->num0($r['placed']),
                $this->num0($r['completed']),
                $this->num0($r['open']),
                $this->num0($r['cancelled']),
                $this->money0($r['sales']),
                $net ? $this->money($r['sales'] / $net) : '—'
            ];
        }

        // ----- staff performance + late list -----
        $staff = [];
        $late = [];
        $timed = 0;
        $onTime = 0;
        foreach ($c['orders'] as $o) {
            $sid = (int) ($o['assigned_delivery_id'] ?? 0);
            if (! $sid) {
                continue;
            }
            $staff[$sid] ??= ['assigned' => 0, 'completed' => 0, 'active' => 0, 'cancelled' => 0, 'timed' => 0, 'ontime' => 0, 'hours' => 0.0, 'hn' => 0, 'sales' => 0.0];
            $staff[$sid]['assigned']++;
            $status = $o['status'] ?? '';
            if ($status === 'cancelled') {
                $staff[$sid]['cancelled']++;
            } elseif ($status === 'out_for_delivery') {
                $staff[$sid]['active']++;
            } elseif ($status === 'completed') {
                $staff[$sid]['completed']++;
                $staff[$sid]['sales'] += $this->amt($o);
                if (! empty($o['delivered_at'])) {
                    $start = $o['ordered_at'] ?? $o['order_date'];
                    $h = (strtotime((string) $o['delivered_at']) - strtotime((string) $start)) / 3600;
                    if ($h >= 0) {
                        $staff[$sid]['hours'] += $h;
                        $staff[$sid]['hn']++;
                    }
                    if (! empty($o['scheduled_delivery_date'])) {
                        $staff[$sid]['timed']++;
                        $timed++;
                        if ($this->day($o['delivered_at']) <= $this->day($o['scheduled_delivery_date'])) {
                            $staff[$sid]['ontime']++;
                            $onTime++;
                        } else {
                            $late[] = $o;
                        }
                    }
                }
            }
        }
        uasort($staff, fn($a, $b) => $b['completed'] <=> $a['completed']);
        $staffRows = [];
        $staffNames = [];
        $staffDone = [];
        foreach ($staff as $sid => $r) {
            $name = $users[$sid] ?? ('Staff #' . $sid);
            $staffNames[] = $name;
            $staffDone[] = $r['completed'];
            $staffRows[] = [
                $name,
                $this->num($r['assigned']),
                $this->num0($r['completed']),
                $this->num0($r['active']),
                $this->num0($r['cancelled']),
                $this->pct($r['ontime'], $r['timed']),
                $r['hn'] ? number_format($r['hours'] / $r['hn'], 1) . ' h' : '—',
                $this->money0($r['sales'])
            ];
        }

        $lateRows = [];
        usort($late, fn($a, $b) => strcmp((string) $b['delivered_at'], (string) $a['delivered_at']));
        foreach (array_slice($late, 0, 25) as $o) {
            $days = (int) round((strtotime($this->day($o['delivered_at'])) - strtotime($this->day($o['scheduled_delivery_date']))) / 86400);
            $lateRows[] = [
                $o['order_code'] ?? ('#' . $o['id']),
                $d['customers'][$o['customer_id'] ?? 0]['name'] ?? '—',
                date('M j, Y', strtotime($this->day($o['scheduled_delivery_date']))),
                date('M j, Y', strtotime($this->day($o['delivered_at']))),
                $users[$o['assigned_delivery_id'] ?? 0] ?? '—',
                $days . ' day(s)'
            ];
        }

        // ----- delivery photos -----
        $proofs = [];
        foreach ($d['proofs'] as $p) {
            $s = (string) ($p['status'] ?? 'pending');
            $proofs[$s] = ($proofs[$s] ?? 0) + 1;
        }
        $proofRows = [];
        foreach ($proofs as $s => $n) {
            $proofRows[] = [$this->badge($this->human($s), $s === 'approved' ? 'green' : ($s === 'pending' ? 'yellow' : 'red')), $this->num($n)];
        }

        $yesterday = strtotime('-1 day');
        $delayed = count(array_filter($c['sales'], fn($o) => in_array($o['status'], ['pending', 'out_for_delivery'], true) && strtotime($this->day($o['order_date'])) < $yesterday));
        $outNow = count(array_filter($c['orders'], fn($o) => ($o['status'] ?? '') === 'out_for_delivery'));

        return [
            'description' => 'Pick-up vs delivery orders, how each delivery staff is doing, and which deliveries were late compared with the scheduled date.',
            'kpis' => [
                $this->kpi('Delivery orders', $this->num($types['delivery']['placed'] - $types['delivery']['cancelled']), $this->money($types['delivery']['sales']) . ' in sales'),
                $this->kpi('Pick-up orders', $this->num($types['pickup']['placed'] - $types['pickup']['cancelled']), $this->money($types['pickup']['sales']) . ' in sales'),
                $this->kpi('On-time deliveries', $this->pct($onTime, $timed), $timed ? $onTime . ' of ' . $timed . ' with a scheduled date' : 'No scheduled deliveries completed', $timed && $onTime < $timed ? 'yellow' : 'green'),
                $this->kpi('Out for delivery now', $this->num($outNow), $delayed . ' open order(s) older than a day', $delayed ? 'yellow' : 'default'),
            ],
            'charts' => [
                $this->chart('Pick-up vs delivery', 'doughnut', ['Delivery', 'Pick-up'], [['label' => 'Orders', 'data' => [
                    $types['delivery']['placed'] - $types['delivery']['cancelled'],
                    $types['pickup']['placed'] - $types['pickup']['cancelled']
                ]]]),
                $this->chart('Completed deliveries by staff', 'hbar', $staffNames, [['label' => 'Completed', 'data' => $staffDone, 'tone' => 'maroon']]),
            ],
            'tables' => [
                $this->table('Pick-up vs delivery', ['Type', '>Placed', '>Completed', '>Still open', '>Cancelled', '>Sales', '>Avg order'], $typeRows),
                $this->table(
                    'Delivery staff performance',
                    ['Staff', '>Assigned', '>Completed', '>Out now', '>Cancelled', '>On-time', '>Avg time', '>Sales delivered'],
                    $staffRows,
                    null,
                    'Avg time = from the order being taken to the delivery being approved.'
                ),
                $this->table('Late deliveries', ['Order', 'Customer', 'Scheduled', 'Delivered', 'Staff', '>Late by'], $lateRows),
                $this->table('Delivery photos', ['Review status', '>Photos'], $proofRows),
            ],
        ];
    }

    /* =====================================================================
     |  8. EXPENSES
     * ===================================================================*/
    private function buildExpenses(array $c): array
    {
        $g = $c['f']['group'];
        $expenses = $c['d']['expenses'];
        $total = $c['expensesTotal'];
        $sales = $c['salesTotal'];
        $n = count($expenses);

        $byCat = [];
        foreach ($expenses as $e) {
            $byCat[$e['category']] ??= ['n' => 0, 'total' => 0.0];
            $byCat[$e['category']]['n']++;
            $byCat[$e['category']]['total'] += (float) $e['amount'];
        }
        uasort($byCat, fn($a, $b) => $b['total'] <=> $a['total']);
        $catRows = [];
        foreach ($byCat as $name => $r) {
            $catRows[] = [$name, $this->num($r['n']), $this->money($r['total']), $this->pct($r['total'], $total), $this->money($r['total'] / $r['n'])];
        }
        $catFooter = ['Total', $this->num($n), $this->money($total), $total > 0 ? '100.0%' : '—', $n ? $this->money($total / $n) : '—'];

        $rows = $this->periodRows($c);
        $periodRows = [];
        foreach ($rows as $k => $r) {
            $periodRows[] = [$this->periodLabel($k, $g), $this->num0($r['entries']), $this->money0($r['expenses']), $this->money0($r['total']), $this->pct($r['expenses'], $r['total'])];
        }

        $largest = $expenses;
        usort($largest, fn($a, $b) => (float) $b['amount'] <=> (float) $a['amount']);
        $largestRows = [];
        foreach (array_slice($largest, 0, 10) as $e) {
            $largestRows[] = [date('M j, Y', strtotime($this->day($e['expense_date']))), $e['category'], $this->money((float) $e['amount']), ($e['note'] ?? '') !== '' ? $e['note'] : '—'];
        }

        $insights = [];
        if ($byCat) {
            $top = array_key_first($byCat);
            $insights[] = 'Biggest expense category: ' . $top . ' (' . $this->money($byCat[$top]['total']) . ', ' . $this->pct($byCat[$top]['total'], $total) . ' of all expenses).';
        }
        if ($sales > 0) {
            $insights[] = 'Expenses used ' . $this->pct($total, $sales) . ' of total sales in this period.';
        }
        if ($total > $sales && $total > 0) {
            $insights[] = 'Expenses are higher than sales in this period.';
        }

        $topCat = $byCat ? array_key_first($byCat) : null;

        return [
            'description' => 'Where the money went: expenses by category and by period, compared with sales.',
            'kpis' => [
                $this->kpi('Total expenses', $this->money($total), $this->delta($total, $c['d']['previous']['expenses'])),
                $this->kpi('Entries', $this->num($n), $n ? 'Average ' . $this->money($total / $n) : null),
                $this->kpi('% of sales', $this->pct($total, $sales), 'Sales: ' . $this->money($sales), $sales > 0 && $total > $sales ? 'red' : 'default'),
                $this->kpi('Biggest category', $topCat ?? '—', $topCat ? $this->money($byCat[$topCat]['total']) : null, 'yellow'),
            ],
            'insights' => $insights,
            'charts' => [
                $this->chart('Expenses by category', 'doughnut', array_keys($byCat), [['label' => 'Expenses', 'data' => array_column($byCat, 'total')]], 'money'),
                $this->chart('Expenses per ' . $g, 'bar', $this->shortLabels($c), [['label' => 'Expenses', 'data' => array_column($rows, 'expenses'), 'tone' => 'amber']], 'money', ['wide' => true]),
            ],
            'tables' => [
                $this->table('By category', ['Category', '>Entries', '>Total', '>% of expenses', '>Average'], $catRows, $catFooter),
                $this->table(ucfirst($g) . ' breakdown', ['Period', '>Entries', '>Expenses', '>Sales', '>Expenses % of sales'], $periodRows),
                $this->table('Largest expenses', ['Date', 'Category', '>Amount', 'Note'], $largestRows),
            ],
        ];
    }

    /* =====================================================================
     |  9. INVENTORY
     * ===================================================================*/
    private function buildInventory(array $c): array
    {
        $d = $c['d'];
        $days = max(1, $c['f']['days']);
        $names = [];
        foreach ($d['products'] as $p) {
            $names[$p['id']] = $p['name'];
        }

        $sold = [];
        foreach ($c['saleItems'] as $i) {
            $k = (int) ($i['product_id'] ?? 0);
            $sold[$k] = ($sold[$k] ?? 0) + $this->qty($i);
        }

        $stockRows = [];
        $labels = [];
        $inStock = [];
        $reorder = [];
        $low = 0;
        $out = 0;
        foreach ($d['products'] as $p) {
            if (! $p['is_active']) {
                continue;
            }
            $units = $sold[$p['id']] ?? 0;
            $perDay = $units / $days;
            $left = $perDay > 0 ? (string) (int) floor($p['current_stock'] / $perDay) : '—';
            $tone = $p['status'] === 'green' ? 'green' : ($p['status'] === 'yellow' ? 'yellow' : 'red');
            if ($p['status'] !== 'green') {
                $low++;
            }
            if ($p['current_stock'] <= 0) {
                $out++;
            }
            $stockRows[] = [
                $p['name'],
                $p['cylinder'] ?? '—',
                $this->num($p['current_stock']),
                $this->num($p['max_capacity']),
                $this->num($p['reorder_level']),
                $this->badge($p['label'], $tone),
                $this->num0($units),
                $perDay > 0 ? number_format($perDay, 1) : '—',
                $left === '—' ? '—' : $left . ' day(s)'
            ];
            $labels[] = $p['name'];
            $inStock[] = $p['current_stock'];
            $reorder[] = $p['reorder_level'];
        }

        // ----- stock movements in the period -----
        $byGas = [];
        $byReason = [];
        $otherOut = 0;
        foreach ($d['movements'] as $m) {
            $q = (int) $m['quantity_change'];
            $reason = (string) ($m['reason'] ?? 'other');
            $k = (int) ($m['product_id'] ?? 0);
            $byGas[$k] ??= ['in' => 0, 'sale' => 0, 'other' => 0];
            $byReason[$reason] ??= ['in' => 0, 'out' => 0];
            if ($q >= 0) {
                $byGas[$k]['in'] += $q;
                $byReason[$reason]['in'] += $q;
            } else {
                $byGas[$k][$reason === 'sale' ? 'sale' : 'other'] += -$q;
                $byReason[$reason]['out'] += -$q;
                if ($reason !== 'sale') {
                    $otherOut += -$q;
                }
            }
        }
        $moveRows = [];
        foreach ($byGas as $k => $r) {
            $moveRows[] = [$names[$k] ?? 'Other', $this->num0($r['in']), $this->num0($r['sale']), $this->num0($r['other']), ($r['in'] - $r['sale'] - $r['other'] >= 0 ? '+' : '') . $this->num($r['in'] - $r['sale'] - $r['other'])];
        }
        $reasonRows = [];
        $reasonLabels = [];
        $reasonQty = [];
        uasort($byReason, fn($a, $b) => ($b['in'] + $b['out']) <=> ($a['in'] + $a['out']));
        foreach ($byReason as $reason => $r) {
            $reasonRows[] = [$this->human($reason), $this->num0($r['in']), $this->num0($r['out'])];
            $reasonLabels[] = $this->human($reason);
            $reasonQty[] = $r['in'] + $r['out'];
        }

        $insights = [];
        foreach ($d['products'] as $p) {
            if ($p['is_active'] && $p['current_stock'] > 0 && ($sold[$p['id']] ?? 0) > 0) {
                $left = $p['current_stock'] / (($sold[$p['id']]) / $days);
                if ($left <= 7) {
                    $insights[] = $p['name'] . ' may run out in about ' . max(1, (int) floor($left)) . ' day(s) at the current selling pace.';
                }
            }
            if ($p['is_active'] && $p['current_stock'] <= 0) {
                $insights[] = $p['name'] . ' is out of stock.';
            }
        }
        if ($otherOut > 0) {
            $insights[] = $otherOut . ' unit(s) left stock for reasons other than a sale (damaged, lost, returned or corrected). Check the movement table.';
        }

        return [
            'description' => 'Current stock against reorder levels, how fast each gas sells, and every stock movement (stock in, sales, damage, corrections) in this period.',
            'kpis' => [
                $this->kpi('Gas products', $this->num(count($stockRows)), 'active'),
                $this->kpi('Need restocking', $this->num($low), 'at or below reorder level', $low ? 'yellow' : 'green'),
                $this->kpi('Out of stock', $this->num($out), null, $out ? 'red' : 'green'),
                $this->kpi('Stock out, not sold', $this->num($otherOut), 'damaged / lost / returned / corrected', $otherOut ? 'yellow' : 'green'),
            ],
            'insights' => $insights,
            'charts' => [
                $this->chart('Stock vs reorder level', 'bar', $labels, [
                    ['label' => 'In stock', 'data' => $inStock, 'tone' => 'maroon'],
                    ['label' => 'Reorder level', 'data' => $reorder, 'tone' => 'amber'],
                ], 'count', ['wide' => true]),
                $this->chart('Stock movement by reason', 'hbar', $reasonLabels, [['label' => 'Units', 'data' => $reasonQty, 'tone' => 'maroon']]),
            ],
            'tables' => [
                $this->table(
                    'Stock levels',
                    ['Gas', 'Cylinder', '>In stock', '>Max', '>Reorder at', 'Status', '>Sold (period)', '>Avg / day', '>Days left'],
                    $stockRows,
                    null,
                    'Days left = current stock ÷ average units sold per day in this period.'
                ),
                $this->table('Stock movement by gas', ['Gas', '>Stock in', '>Sold', '>Other stock out', '>Net change'], $moveRows),
                $this->table('Stock movement by reason', ['Reason', '>Stock in', '>Stock out'], $reasonRows),
            ],
        ];
    }

    /* =====================================================================
     |  10. CUSTOMERS & PEAK TIMES
     * ===================================================================*/
    private function buildCustomers(array $c): array
    {
        $d = $c['d'];
        $sales = $c['sales'];
        $total = $c['salesTotal'];

        // Customers are matched by phone number, so the same person counts once even if the
        // system saved them as separate customer records.
        $cust = [];
        foreach ($sales as $o) {
            $info = $d['customers'][$o['customer_id'] ?? 0] ?? null;
            $phone = preg_replace('/\D+/', '', (string) ($info['phone'] ?? ''));
            $key = $phone !== '' ? $phone : 'id' . ($o['customer_id'] ?? 0);
            $cust[$key] ??= ['name' => $info['name'] ?? 'Unknown', 'phone' => $info['phone'] ?? '—', 'orders' => 0, 'total' => 0.0, 'last' => ''];
            $cust[$key]['orders']++;
            $cust[$key]['total'] += $this->amt($o);
            $cust[$key]['last'] = max($cust[$key]['last'], $this->day($o['order_date']));
        }
        uasort($cust, fn($a, $b) => $b['total'] <=> $a['total']);
        $unique = count($cust);
        $repeat = count(array_filter($cust, fn($r) => $r['orders'] >= 2));

        $topRows = [];
        foreach (array_slice($cust, 0, 15) as $r) {
            $topRows[] = [$r['name'], $r['phone'], $this->num($r['orders']), $this->money($r['total']), $this->money($r['total'] / $r['orders']), date('M j, Y', strtotime($r['last']))];
        }

        // ----- weekday (1 = Monday ... 7 = Sunday) -----
        // Always look weekdays up by their real number (1-7). Never use array_column()/array_search()
        // on $wd: they renumber the keys 0-6 and the weekday name ends up one day off.
        $names = $this->weekdayNames();
        $wd = $this->weekdayStats($sales);
        $busy = $this->busiestWeekday($wd);                  // most orders
        $topSalesDay = null;                                  // most money
        foreach ($wd as $w => $r) {
            if ($r['n'] > 0 && ($topSalesDay === null || $r['total'] > $wd[$topSalesDay]['total'])) {
                $topSalesDay = $w;
            }
        }
        $wdRows = [];
        foreach ($names as $n => $label) {
            $wdRows[] = [
                $label,
                $this->num0($wd[$n]['n']),
                $this->money0($wd[$n]['total']),
                $this->pct($wd[$n]['n'], count($sales)),
                $this->pct($wd[$n]['total'], $total)
            ];
        }
        $wdOrders = array_map(fn($n) => $wd[$n]['n'], array_keys($names));
        $wdSales = array_map(fn($n) => $wd[$n]['total'], array_keys($names));

        // ----- hour of day (hours 0-23, keys are the hours themselves) -----
        $hours = array_fill(0, 24, 0);
        foreach ($sales as $o) {
            if (! empty($o['ordered_at'])) {
                $hours[(int) date('G', strtotime((string) $o['ordered_at']))]++;
            }
        }
        $hourLabels = array_map(fn($h) => date('ga', strtotime(sprintf('%02d:00', $h))), array_keys($hours));
        $busyHour = array_sum($hours) > 0 ? array_search(max($hours), $hours, true) : null;

        $insights = [];
        if ($busy !== null) {
            $insights[] = 'Most orders: ' . $names[$busy] . ' (' . $wd[$busy]['n'] . ' orders, ' . $this->money($wd[$busy]['total']) . ').';
        }
        if ($topSalesDay !== null && $topSalesDay !== $busy) {
            $insights[] = 'Most sales money: ' . $names[$topSalesDay] . ' (' . $this->money($wd[$topSalesDay]['total']) . ' from ' . $wd[$topSalesDay]['n']
                . ' orders), even though ' . $names[$busy] . ' had the most orders.';
        }
        if ($repeat) {
            $insights[] = $repeat . ' of ' . $unique . ' customers ordered more than once in this period (' . $this->pct($repeat, $unique) . ').';
        }
        if ($cust) {
            $first = reset($cust);
            $insights[] = 'Top customer: ' . $first['name'] . ' with ' . $this->money($first['total']) . ' over ' . $first['orders'] . ' order(s).';
        }
        if ($busyHour !== null) {
            $insights[] = 'Orders are taken most often around ' . $hourLabels[$busyHour] . '.';
        }

        return [
            'description' => 'Who buys the most, how many customers come back, and which days and hours are the busiest. '
                . 'Hours use the time each order was saved (server timezone: ' . date_default_timezone_get() . ').',
            'kpis' => [
                $this->kpi('Customers', $this->num($unique), 'matched by phone number'),
                $this->kpi('Repeat customers', $this->num($repeat), $this->pct($repeat, $unique) . ' ordered 2+ times', 'green'),
                $this->kpi('Orders per customer', $unique ? number_format(count($sales) / $unique, 1) : '—'),
                $this->kpi(
                    'Busiest weekday',
                    $busy !== null ? $names[$busy] : '—',
                    $busy !== null ? $wd[$busy]['n'] . ' orders · ' . $this->money($wd[$busy]['total']) : null
                ),
            ],
            'insights' => $insights,
            'charts' => [
                $this->chart('Orders by weekday', 'bar', array_values($names), [['label' => 'Orders', 'data' => $wdOrders, 'tone' => 'maroon']]),
                $this->chart('Sales by weekday', 'bar', array_values($names), [['label' => 'Sales', 'data' => $wdSales, 'tone' => 'green']], 'money'),
                $this->chart('New vs repeat customers', 'doughnut', ['Ordered once', 'Ordered 2+ times'], [['label' => 'Customers', 'data' => [$unique - $repeat, $repeat]]]),
                $this->chart('Orders by hour of day', 'bar', $hourLabels, [['label' => 'Orders', 'data' => array_values($hours), 'tone' => 'amber']], 'count', ['wide' => true]),
            ],
            'tables' => [
                $this->table('Top customers', ['Customer', 'Phone', '>Orders', '>Total spent', '>Avg order', '>Last order'], $topRows, null, 'Top 15 by amount spent in this period.'),
                $this->table('Orders by weekday', ['Day', '>Orders', '>Sales', '>% of orders', '>% of sales'], $wdRows),
            ],
        ];
    }
}
