<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\OptionsIgnore;
use Illuminate\Http\Request;

class AdminIgnoreController extends Controller
{
    public function index()
    {
        $folders = OptionsIgnore::all();

        $ignoreOptions = OptionsIgnore::first();
        return view('admin.ignore', compact('folders', 'ignoreOptions'));
    }

    public function store(Request $request)
    {
        $ignoreOptions = OptionsIgnore::first();

        if ($ignoreOptions) {
            $ignoreOptions->save();
        }

        if ($request->input('ignored_folders')) {
            $folders = explode(',', $request->input('ignored_folders'));
            foreach ($folders as $folder) {
                OptionsIgnore::create(['folder_name' => trim($folder)]);
            }
        }

        AuditLog::record('ignore.update');

        return redirect()->route('admin.ignore')->with('success', __('messages.flash.ignore_updated'));
    }

    public function destroyFolder($id)
    {
        $folder = OptionsIgnore::findOrFail($id);
        $folder->delete();
        AuditLog::record('ignore.folder.delete', $folder);
        return redirect()->route('admin.ignore')->with('success', __('messages.flash.ignore_deleted'));
    }
}
