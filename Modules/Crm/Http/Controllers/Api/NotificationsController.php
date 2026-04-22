<?php

namespace Modules\Crm\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Crm\Entities\CrmNotification;

class NotificationsController extends Controller
{
    public function index(Request $request)
    {
        $limit = min(max((int) $request->query('limit', 20), 1), 50);

        $rows = CrmNotification::query()
            ->where('user_id', auth()->id())
            ->latest('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $rows,
            'unread_count' => CrmNotification::where('user_id', auth()->id())->whereNull('read_at')->count(),
        ]);
    }

    public function markRead(int $id)
    {
        $row = CrmNotification::where('user_id', auth()->id())->findOrFail($id);
        $row->update(['read_at' => now()]);
        return response()->json(['message' => 'Read']);
    }

    public function markAllRead()
    {
        CrmNotification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Read']);
    }
}
