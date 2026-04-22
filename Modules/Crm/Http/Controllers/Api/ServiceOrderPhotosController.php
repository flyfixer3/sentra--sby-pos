<?php

namespace Modules\Crm\Http\Controllers\Api;

use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Crm\Entities\ServiceOrder;
use Modules\Crm\Entities\ServiceOrderPhoto;

class ServiceOrderPhotosController extends Controller
{
    protected function requireBranch(): int
    {
        $id = BranchContext::id();
        if ($id === null) {
            return abort(422, "Please select a specific branch first (not 'All Branch').");
        }
        return (int) $id;
    }

    protected function currentUserIsTechnicianOnly(): bool
    {
        $user = auth()->user();
        if (!$user || !method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole('Technician') && Gate::denies('assign_crm_service_orders');
    }

    protected function abortIfTechnicianNotAssigned(ServiceOrder $serviceOrder): void
    {
        if (!$this->currentUserIsTechnicianOnly()) {
            return;
        }

        $assigned = $serviceOrder->technicians()
            ->where('user_id', auth()->id())
            ->exists();

        if (!$assigned) {
            abort(403, 'You are not assigned to this service order.');
        }
    }

    public function index(Request $request, int $id)
    {
        abort_if(Gate::denies('show_crm_service_orders'), 403);
        $this->requireBranch();
        $so = ServiceOrder::findOrFail($id);
        $this->abortIfTechnicianNotAssigned($so);
        $photos = ServiceOrderPhoto::where('service_order_id', $so->id)->with('media')->latest('id')->get();
        return response()->json(['data' => $photos]);
    }

    public function store(Request $request, int $id)
    {
        abort_if(Gate::denies('upload_crm_photos'), 403);
        $branchId = $this->requireBranch();
        $so = ServiceOrder::findOrFail($id);
        $this->abortIfTechnicianNotAssigned($so);

        $data = $request->validate([
            'phase' => ['required', 'in:before,after,other'],
            'caption' => ['nullable', 'string', 'max:255'],
            'file' => ['required_without:photo', 'file', 'max:10240'], // ~10MB
            'photo' => ['required_without:file', 'file', 'max:10240'], // frontend compatibility
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ]);

        $media = $so->addMediaFromRequest($request->hasFile('file') ? 'file' : 'photo')->toMediaCollection('photos');

        $meta = ServiceOrderPhoto::create([
            'branch_id' => $branchId,
            'service_order_id' => $so->id,
            'media_id' => $media->id,
            'phase' => $data['phase'],
            'caption' => $data['caption'] ?? null,
            'uploaded_by' => auth()->id(),
            'uploaded_at' => now(),
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
        ]);

        return response()->json([
            'media' => $media,
            'photo' => $meta,
        ], 201);
    }

    public function destroy(int $photoId)
    {
        abort_if(Gate::denies('delete_crm_photos'), 403);
        $this->requireBranch();
        $photo = ServiceOrderPhoto::findOrFail($photoId);
        $photo->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
