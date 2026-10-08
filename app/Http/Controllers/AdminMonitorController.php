<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\OptionsMonitoring;
use App\Models\OptionsServer;
use App\Models\ServerCheck;
use App\Models\ServerIncident;
use App\Services\ServerMonitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminMonitorController extends Controller
{
    public function index(ServerMonitor $monitor)
    {
        $settings = OptionsMonitoring::current();
        $servers = [];
        $incidents = collect();
        $lastRun = null;

        try {
            $uptime = $monitor->uptimeAll();
            foreach (OptionsServer::all() as $s) {
                $key = ServerMonitor::serverKey($s);
                $last = ServerCheck::where('server_key', $key)->latest('created_at')->first();
                $servers[] = [
                    'key'    => $key,
                    'name'   => $s->server_name,
                    'last'   => $last,
                    'fresh'  => $last && $last->created_at->gt(now()->subMinutes(5)),
                    'uptime' => $uptime[$key] ?? ['h24' => null, 'd7' => null, 'd30' => null],
                    'series' => $monitor->series($key),
                ];
            }
            $incidents = ServerIncident::orderByDesc('started_at')->limit(50)->get();
            $lastRun = ServerCheck::max('created_at');
        } catch (\Throwable $e) {
            Log::warning('AdminMonitorController@index: ' . $e->getMessage());
        }

        return view('admin.monitor', compact('settings', 'servers', 'incidents', 'lastRun'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'offline_after_minutes' => 'required|integer|min:1|max:120',
            'latency_threshold_ms'  => 'required|integer|min:100|max:10000',
            'latency_after_minutes' => 'required|integer|min:1|max:120',
            'empty_after_minutes'   => 'required|integer|min:0|max:1440',
            'reminder_minutes'      => 'required|integer|min:5|max:1440',
            'webhook_url'           => ['nullable', 'url', 'max:500', 'regex:#^https?://#i'],
        ]);
        $data['enabled'] = $request->boolean('enabled');
        $data['webhook_url'] = $data['webhook_url'] ?? null;

        $settings = OptionsMonitoring::current();
        $settings->fill($data)->save();
        // L'URL du webhook est un secret : on ne la consigne pas dans l'audit.
        AuditLog::record('monitor.settings', $settings, collect($data)->except('webhook_url')->all());

        return redirect()->route('admin.monitor')->with('success', __('messages.flash.monitor_saved'));
    }

    public function test(ServerMonitor $monitor)
    {
        $ok = $monitor->sendTest();
        AuditLog::record('monitor.test', null, ['sent' => $ok]);

        return redirect()->route('admin.monitor')->with(
            $ok ? 'success' : 'error',
            $ok ? __('messages.flash.monitor_test_sent') : __('messages.flash.monitor_test_failed')
        );
    }
}
