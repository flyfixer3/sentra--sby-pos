<?php

namespace Modules\ActivityLog\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\Activitylog\Models\Activity;
use App\Models\User;
use Carbon\Carbon;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
  
    public function index(Request $request)
    {
        // ✅ eager load supaya tidak N+1 (lebih cepat, tidak ubah fitur)
        $query = Activity::query()->with(['causer', 'subject']);

        // ✅ Filter by User
        if ($request->has('user') && $request->user != '') {
            $query->where('causer_id', $request->user);
        }

        // ✅ Filter by Table (subject_type)
        // input table dari dropdown adalah class_basename(subject_type)
        if ($request->has('table') && $request->table != '') {
            $query->where('subject_type', 'like', "%{$request->table}%");
        }

        // ✅ Filter by Date Range
        if ($request->has('date_range') && $request->date_range != '') {
            $dates = explode(' to ', $request->date_range);
            if (count($dates) == 2) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[1])->endOfDay();
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }
        }

        $logs = $query->latest()->paginate(10)->withQueryString();

        $users = User::orderBy('name')->get();

        // ✅ ambil table list yang unique + rapi
        $tables = Activity::selectRaw('DISTINCT(subject_type) as subject_type')
            ->pluck('subject_type')
            ->map(function ($item) {
                return class_basename($item);
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        return view('activitylog::index', compact('logs', 'users', 'tables'));
    }
    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('activitylog::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        return view('activitylog::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        return view('activitylog::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        //
    }
}
