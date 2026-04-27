<?php

namespace Modules\Crm\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Crm\Entities\CtaClick;

class CtaClicksController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'session_id' => ['required', 'string', 'max:128'],
            'ref_code' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:100'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:150'],
            'utm_term' => ['nullable', 'string', 'max:150'],
            'utm_content' => ['nullable', 'string', 'max:150'],
            'landing_page_url' => ['nullable', 'string', 'max:2048'],
            'cta_type' => ['nullable', 'string', 'max:50'],
            'target_whatsapp_number' => ['nullable', 'string', 'max:50'],
        ]);

        $data = $this->normalizePayload($data, $request);
        $windowStart = now()->subMinutes(30);

        $existing = CtaClick::query()
            ->where('session_id', $data['session_id'])
            ->where('cta_type', $data['cta_type'])
            ->where('target_whatsapp_number', $data['target_whatsapp_number'])
            ->where('last_clicked_at', '>=', $windowStart)
            ->when($data['ref_code'], fn ($query) => $query->where('ref_code', $data['ref_code']))
            ->when(!$data['ref_code'] && $data['source'], fn ($query) => $query->where('source', $data['source']))
            ->when(!$data['ref_code'] && !$data['source'], fn ($query) => $query->whereNull('ref_code')->whereNull('source'))
            ->latest('id')
            ->first();

        if ($existing) {
            $existing->forceFill([
                'click_count' => $existing->click_count + 1,
                'last_clicked_at' => now(),
                'landing_page_url' => $data['landing_page_url'] ?: $existing->landing_page_url,
                'ip_address' => $data['ip_address'],
                'user_agent' => $data['user_agent'],
            ])->save();

            return response()->json([
                'success' => true,
                'deduped' => true,
                'id' => $existing->id,
            ], 200);
        }

        $click = CtaClick::create(array_merge($data, [
            'first_clicked_at' => now(),
            'last_clicked_at' => now(),
        ]));

        return response()->json([
            'success' => true,
            'deduped' => false,
            'id' => $click->id,
        ], 201);
    }

    public function index(Request $request)
    {
        abort_if(Gate::denies('show_crm_leads'), 403);

        $limit = min(max((int) $request->query('limit', 20), 1), 200);
        $page  = max((int) $request->query('page', 1), 1);
        $search    = trim((string) $request->query('search', ''));
        $refCode   = trim((string) $request->query('ref_code', ''));
        $source    = trim((string) $request->query('source', ''));
        $converted = trim((string) $request->query('converted', '')); // 'yes' | 'no'
        $todayOnly = (bool) $request->query('today', false);

        $baseQuery = CtaClick::query()
            ->when($refCode !== '', fn ($q) => $q->where('ref_code', 'like', '%' . $refCode . '%'))
            ->when($source !== '', fn ($q) => $q->where('source', 'like', '%' . $source . '%'))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('ref_code', 'like', '%' . $search . '%')
                          ->orWhere('source', 'like', '%' . $search . '%')
                          ->orWhere('utm_source', 'like', '%' . $search . '%')
                          ->orWhere('utm_campaign', 'like', '%' . $search . '%');
                });
            })
            ->when($converted === 'yes', fn ($q) => $q->whereNotNull('lead_id'))
            ->when($converted === 'no',  fn ($q) => $q->whereNull('lead_id'))
            ->when($todayOnly, fn ($q) => $q->whereDate('last_clicked_at', now()->toDateString()));

        $total = (clone $baseQuery)->count();

        $rows = (clone $baseQuery)
            ->latest('last_clicked_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->with('lead:id,contact_name,status')
            ->get();

        // Summary counts (independent of current filters, only search applied)
        $summaryBase = CtaClick::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('ref_code', 'like', '%' . $search . '%')
                          ->orWhere('source', 'like', '%' . $search . '%')
                          ->orWhere('utm_source', 'like', '%' . $search . '%')
                          ->orWhere('utm_campaign', 'like', '%' . $search . '%');
                });
            });

        $unconvertedCount = (clone $summaryBase)->whereNull('lead_id')->count();
        $todayCount       = (clone $summaryBase)->whereDate('last_clicked_at', now()->toDateString())->count();

        return response()->json([
            'data'              => $rows,
            'total'             => $total,
            'unconverted_count' => $unconvertedCount,
            'today_count'       => $todayCount,
        ]);
    }

    protected function normalizePayload(array $data, Request $request): array
    {
        foreach ([
            'ref_code',
            'source',
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
            'landing_page_url',
            'cta_type',
            'target_whatsapp_number',
        ] as $key) {
            $data[$key] = $data[$key] ?? null;
        }

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value);
            }
            if ($data[$key] === '') {
                $data[$key] = null;
            }
        }

        $data['session_id'] = (string) $data['session_id'];
        $data['cta_type'] = Str::lower($data['cta_type'] ?: 'cta');
        $data['target_whatsapp_number'] = isset($data['target_whatsapp_number'])
            ? preg_replace('/\D+/', '', $data['target_whatsapp_number'])
            : null;
        $data['utm_source'] = $data['utm_source'] ?? null;
        $data['source'] = $data['utm_source'] ?: ($data['source'] ?? null);
        $data['ip_address'] = $request->ip();
        $data['user_agent'] = Str::limit((string) $request->userAgent(), 500, '');

        return $data;
    }
}
