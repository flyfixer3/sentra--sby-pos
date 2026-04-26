<?php

namespace Modules\Crm\Http\Controllers\Api;

use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Crm\Entities\Lead;
use Modules\Crm\Entities\LeadComment;
use Spatie\Activitylog\Models\Activity;

class LeadTimelineController extends Controller
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
        abort_if((int)$lead->branch_id !== $branchId, 403);
        return $lead;
    }

    public function index(Request $request, int $leadId)
    {
        abort_if(Gate::denies('show_crm_leads'), 403);
        $branchId = $this->requireBranch();
        $lead = $this->assertLeadInBranch($leadId, $branchId);

        $comments = LeadComment::where('lead_id',$leadId)->orderBy('id','desc')->limit(100)->get()
            ->map(function($c){
                return [
                    'id' => 'c-'.$c->id,
                    'type' => 'comment',
                    'user_id' => (int)$c->user_id,
                    'content' => (string)$c->content,
                    'mentions' => $c->mentions,
                    'created_at' => $c->created_at?->toISOString(),
                ];
            });

        $activities = Activity::where('subject_type', get_class($lead))
            ->where('subject_id', $leadId)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function($a){
                return [
                    'id' => 'a-'.$a->id,
                    'type' => 'activity',
                    'event' => (string)$a->description,
                    'properties' => $a->properties,
                    'causer_id' => (int)($a->causer_id ?? 0),
                    'user_id' => (int)($a->causer_id ?? 0),
                    'created_at' => $a->created_at?->toISOString(),
                ];
            });

        $merged = $comments->concat($activities)->sortByDesc('created_at')->values();
        return response()->json(['data' => $merged]);
    }
}
