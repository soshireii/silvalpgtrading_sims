<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reports) {}

    public function index(Request $request)
    {
        $f = $this->reports->filters($request->query());
        $data = $this->reports->dataset($f);

        return view('admin.reports.index', [
            'f' => $f,
            'types' => ReportService::TYPES,
            'presets' => $this->reports->presets(),
            'products' => $data['products'],
            'base' => $this->reports->baseQuery($f),
            'report' => $this->reports->report($f, $data),
        ]);
    }

    /**
     * PDF export. ?report=sales (etc.) exports just that report,
     * ?report=all exports every report in one document.
     */
    public function export(Request $request)
    {
        $f = $this->reports->filters($request->query());
        $data = $this->reports->dataset($f);
        $all = $request->query('report') === 'all';

        $sections = $all ? $this->reports->everything($f, $data) : [$this->reports->report($f, $data)];
        $name = 'silva-lpg-' . ($all ? 'full-report' : $f['type'] . '-report') . "-{$f['from']}-to-{$f['to']}.pdf";

        return Pdf::loadView('admin.reports.pdf', compact('f', 'sections', 'all'))
            ->setPaper('a4', 'landscape')
            ->download($name);
    }
}
