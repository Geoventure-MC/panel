<?php

namespace App\Http\Controllers;

use App\Models\LauncherDiagnostic;
use Illuminate\Http\Request;

/** Rapports « Diagnostic › Envoyer à l'équipe » reçus des launchers des joueurs. */
class AdminDiagnosticController extends Controller
{
    public function index(Request $request)
    {
        $query = LauncherDiagnostic::query()->orderByDesc('created_at');
        if ($request->query('show') !== 'all') {
            $query->where('handled', false);
        }
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where('summary', 'like', '%' . addcslashes($search, '%_\\') . '%');
        }
        $reports = $query->select(['id', 'launcher_version', 'instance', 'os', 'summary', 'handled', 'created_at'])->paginate(30)->withQueryString();

        return view('admin.diagnostics', [
            'reports' => $reports,
            'search' => $search,
            'showAll' => $request->query('show') === 'all',
            'pending' => LauncherDiagnostic::where('handled', false)->count(),
        ]);
    }

    public function show(LauncherDiagnostic $diagnostic)
    {
        return view('admin.diagnostic-show', ['report' => $diagnostic]);
    }

    public function download(LauncherDiagnostic $diagnostic)
    {
        return response($diagnostic->report, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="diagnostic-' . $diagnostic->id . '.txt"',
        ]);
    }

    public function toggle(LauncherDiagnostic $diagnostic)
    {
        $diagnostic->update(['handled' => ! $diagnostic->handled]);
        return back()->with('success', __('messages.flash.diagnostic_updated'));
    }

    public function destroy(LauncherDiagnostic $diagnostic)
    {
        $diagnostic->delete();
        return redirect()->route('admin.diagnostics')->with('success', __('messages.flash.diagnostic_deleted'));
    }
}
