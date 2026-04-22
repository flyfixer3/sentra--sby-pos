<?php
namespace Modules\Crm\Http\Controllers\Api;

use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Crm\Entities\Lead;
use Modules\Crm\Entities\LeadComment;
use Modules\Crm\Entities\CrmNotification;

class LeadCommentsController extends Controller
{
    protected function requireBranch(): int
    {
        $id = BranchContext::id();
        if ($id === null) {
            abort(422, "Please select a specific branch first (not 'All Branch').");
        }
        return (int) $id;
    }

    protected function assertLeadInBranch(int $leadId, int $branchId): Lead
    {
        $lead = Lead::findOrFail($leadId);
        abort_if((int) $lead->branch_id !== $branchId, 403);
        return $lead;
    }

    public function index(Request $request, int $leadId)
    {
        abort_if(Gate::denies('show_crm_leads'), 403);
        $branchId = $this->requireBranch();
        $this->assertLeadInBranch($leadId, $branchId);
        $rows = LeadComment::where('lead_id', $leadId)
            ->orderBy('id', 'desc')
            ->paginate((int) $request->query('limit', 20));
        return response()->json($rows);
    }

    public function store(Request $request, int $leadId)
    {
        abort_if(Gate::denies('comment_crm_leads'), 403);
        $branchId = $this->requireBranch();
        $this->assertLeadInBranch($leadId, $branchId);

        $data = $request->validate([
            'content' => ['required', 'string'],
            'mentions' => ['nullable', 'array'],
            'mentions.*' => ['integer'],
        ]);

        // Sanitize mentions to valid user IDs without failing the request
        $raw = is_array($data['mentions'] ?? null) ? $data['mentions'] : [];
        $ids = array_values(array_unique(array_map('intval', $raw)));
        $validMentions = $ids ? \App\Models\User::whereIn('id', $ids)->pluck('id')->map(fn ($v) => (int) $v)->all() : [];

        $row = LeadComment::create([
            'branch_id' => $branchId,
            'lead_id' => (int) $leadId,
            'user_id' => (int) auth()->id(),
            'content' => $data['content'],
            'mentions' => $validMentions ?: null,
        ]);

        foreach ($validMentions as $userId) {
            if ((int) $userId === (int) auth()->id()) {
                continue;
            }

            CrmNotification::create([
                'branch_id' => $branchId,
                'user_id' => (int) $userId,
                'lead_id' => (int) $leadId,
                'type' => 'lead_mention',
                'title' => 'Anda disebut di komentar',
                'message' => mb_strimwidth($data['content'], 0, 160, '...'),
                'data' => [
                    'lead_id' => (int) $leadId,
                    'comment_id' => (int) $row->id,
                ],
            ]);
        }

        if (function_exists('activity')) {
            activity()->performedOn($this->assertLeadInBranch($leadId, $branchId))
                ->causedBy(auth()->user())
                ->withProperties(['type' => 'comment'])
                ->log('lead_commented');
        }

        return response()->json($row, 201);
    }

    public function destroy(int $leadId, int $commentId)
    {
        abort_if(Gate::denies('comment_crm_leads'), 403);
        $branchId = $this->requireBranch();
        $this->assertLeadInBranch($leadId, $branchId);

        $row = LeadComment::where('id', $commentId)->where('lead_id', $leadId)->firstOrFail();
        abort_if((int) $row->branch_id !== $branchId, 403);
        $row->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
