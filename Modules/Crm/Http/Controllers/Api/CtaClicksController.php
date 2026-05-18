<?php

namespace Modules\Crm\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Crm\Entities\CtaClick;

class CtaClicksController extends Controller
{
    // ── Fraud detection ──────────────────────────────────────────────────────

    protected function detectFraud(array $data): array
    {
        $flags = [];
        $score = 0;

        $ip        = $data['ip_address'] ?? null;
        $ua        = $data['user_agent'] ?? '';
        $sessionId = $data['session_id'] ?? '';
        $window24h = now()->subHours(24);
        $window1h  = now()->subHour();

        // 1. Bot user-agent patterns
        $botPatterns = [
            'HeadlessChrome', 'PhantomJS', 'Selenium', 'WebDriver',
            'python-requests', 'curl/', 'wget/', 'Go-http-client',
            'scrapy', 'crawler', 'spider', 'bot', 'facebookexternalhit',
        ];
        foreach ($botPatterns as $pattern) {
            if (stripos($ua, $pattern) !== false) {
                $flags[] = 'bot_useragent';
                $score  += 3;
                break;
            }
        }

        // 2. IP velocity: same IP → many different sessions in last 24h
        // Suggests one person (or tool) cycling through fake sessions
        if ($ip && Schema::hasColumn('crm_cta_clicks', 'suspicious_score')) {
            $sessionsFromIp = CtaClick::where('ip_address', $ip)
                ->where('created_at', '>=', $window24h)
                ->distinct('session_id')
                ->count('session_id');

            if ($sessionsFromIp >= 10) {
                $flags[] = 'ip_velocity_high';
                $score  += 3;
            } elseif ($sessionsFromIp >= 5) {
                $flags[] = 'ip_velocity_medium';
                $score  += 1;
            }
        }

        // 3. Session velocity: same session → many clicks in last 1h
        if ($sessionId && Schema::hasColumn('crm_cta_clicks', 'suspicious_score')) {
            $clicksFromSession = CtaClick::where('session_id', $sessionId)
                ->where('last_clicked_at', '>=', $window1h)
                ->sum('click_count');

            if ($clicksFromSession >= 20) {
                $flags[] = 'session_velocity_high';
                $score  += 2;
            } elseif ($clicksFromSession >= 10) {
                $flags[] = 'session_velocity_medium';
                $score  += 1;
            }
        }

        // 4. No user agent at all (bare HTTP client)
        if (empty(trim($ua))) {
            $flags[] = 'no_useragent';
            $score  += 2;
        }

        // 5. IP is localhost / private range (testing or internal)
        if ($ip && (
            str_starts_with($ip, '127.') ||
            str_starts_with($ip, '10.')  ||
            str_starts_with($ip, '192.168.') ||
            $ip === '::1'
        )) {
            // Don't flag — this is the owner testing
            $score = 0;
            $flags = [];
        }

        return [
            'suspicious_score' => min($score, 10), // cap at 10
            'suspicious_flags' => $flags ?: null,
        ];
    }

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
            'gclid' => ['nullable', 'string', 'max:150'],
            'gbraid' => ['nullable', 'string', 'max:150'],
            'wbraid' => ['nullable', 'string', 'max:150'],
            'landing_page_url' => ['nullable', 'string', 'max:2048'],
            'page_path' => ['nullable', 'string', 'max:2048'],
            'referrer_url' => ['nullable', 'string', 'max:2048'],
            'cta_type' => ['nullable', 'string', 'max:50'],
            'cta_source' => ['nullable', 'string', 'max:100'],
            'target_whatsapp_number' => ['nullable', 'string', 'max:50'],
            'device_type' => ['nullable', 'string', 'max:50'],
            'browser_name' => ['nullable', 'string', 'max:100'],
            'browser_version' => ['nullable', 'string', 'max:50'],
            'os_name' => ['nullable', 'string', 'max:100'],
            'os_version' => ['nullable', 'string', 'max:50'],
            'screen_width' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'screen_height' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'language' => ['nullable', 'string', 'max:50'],
            'timezone' => ['nullable', 'string', 'max:100'],
        ]);

        $data = $this->normalizePayload($data, $request);
        $availableOptionalColumns = $this->availableOptionalColumns();
        $data = $this->filterUnavailableOptionalColumns($data, $availableOptionalColumns);
        $windowStart = now()->subMinutes(30);

        $existing = CtaClick::query()
            ->where('session_id', $data['session_id'])
            ->where('cta_type', $data['cta_type'])
            ->where('target_whatsapp_number', $data['target_whatsapp_number'])
            ->where('last_clicked_at', '>=', $windowStart)
            ->when($data['cta_source'], fn ($query) => $query->where('cta_source', $data['cta_source']))
            ->when(!$data['cta_source'], fn ($query) => $query->whereNull('cta_source'))
            ->when($data['ref_code'], fn ($query) => $query->where('ref_code', $data['ref_code']))
            ->when(!$data['ref_code'] && $data['source'], fn ($query) => $query->where('source', $data['source']))
            ->when(!$data['ref_code'] && !$data['source'], fn ($query) => $query->whereNull('ref_code')->whereNull('source'))
            ->latest('id')
            ->first();

        $fraud = $this->detectFraud($data);

        if ($existing) {
            $update = [
                'click_count'    => $existing->click_count + 1,
                'last_clicked_at' => now(),
                'landing_page_url' => $data['landing_page_url'] ?: $existing->landing_page_url,
                'cta_source'     => $existing->cta_source ?: $data['cta_source'],
                'ip_address'     => $data['ip_address'],
                'user_agent'     => $data['user_agent'],
            ];

            foreach ($availableOptionalColumns as $column) {
                if (!array_key_exists($column, $data)) {
                    continue;
                }

                $isAttributionIdentifier = in_array($column, ['gclid', 'gbraid', 'wbraid'], true);
                if ($isAttributionIdentifier && !empty($existing->{$column})) {
                    continue;
                }

                if ($data[$column] !== null) {
                    $update[$column] = $data[$column];
                }
            }

            if (Schema::hasColumn('crm_cta_clicks', 'suspicious_score')) {
                // Re-evaluate fraud score on every click update
                $update['suspicious_score'] = max($existing->suspicious_score ?? 0, $fraud['suspicious_score']);
                if (!empty($fraud['suspicious_flags'])) {
                    $merged = array_values(array_unique(array_merge(
                        (array) ($existing->suspicious_flags ?? []),
                        $fraud['suspicious_flags'],
                    )));
                    $update['suspicious_flags'] = $merged;
                }
            }

            $existing->forceFill($update)->save();

            return response()->json([
                'success'          => true,
                'deduped'          => true,
                'id'               => $existing->id,
                'suspicious_score' => $existing->suspicious_score ?? 0,
            ], 200);
        }

        $clickData = array_merge($data, [
            'first_clicked_at' => now(),
            'last_clicked_at'  => now(),
        ]);

        if (Schema::hasColumn('crm_cta_clicks', 'suspicious_score')) {
            $clickData['suspicious_score'] = $fraud['suspicious_score'];
            $clickData['suspicious_flags'] = $fraud['suspicious_flags'];
        }

        $click = CtaClick::create($clickData);

        return response()->json([
            'success' => true,
            'deduped' => false,
            'id' => $click->id,
        ], 201);
    }

    public function index(Request $request)
    {
        abort_if(Gate::denies('show_crm_leads'), 403);

        $limit = min(max((int) $request->query('limit', 20), 1), 50);
        $search = trim((string) $request->query('search', ''));
        $refCode = trim((string) $request->query('ref_code', ''));
        $source = trim((string) $request->query('source', ''));
        $ctaSource = trim((string) $request->query('cta_source', ''));

        $rows = CtaClick::query()
            ->when($refCode !== '', fn ($query) => $query->where('ref_code', 'like', '%' . $refCode . '%'))
            ->when($source !== '', fn ($query) => $query->where('source', 'like', '%' . $source . '%'))
            ->when($ctaSource !== '', fn ($query) => $query->where('cta_source', 'like', '%' . $ctaSource . '%'))
            ->when($request->query('suspicious') === 'only', fn ($q) => $q->where('suspicious_score', '>=', 3))
            ->when($request->query('suspicious') === 'hide', fn ($q) => $q->where(fn($q2) => $q2->where('suspicious_score', '<', 3)->where('manually_flagged', false)))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('ref_code', 'like', '%' . $search . '%')
                        ->orWhere('source', 'like', '%' . $search . '%')
                        ->orWhere('cta_source', 'like', '%' . $search . '%')
                        ->orWhere('utm_source', 'like', '%' . $search . '%')
                        ->orWhere('utm_campaign', 'like', '%' . $search . '%');
                });
            })
            ->latest('last_clicked_at')
            ->limit($limit)
            ->with([
                'lead' => fn ($query) => $query->without('media')->select('id', 'contact_name', 'status', 'created_at', 'created_by'),
                'lead.creator' => fn ($query) => $query->without('media')->select('id', 'name'),
            ])
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function flag(Request $request, int $id)
    {
        abort_if(Gate::denies('show_crm_leads'), 403);

        $click = CtaClick::findOrFail($id);

        $data = $request->validate([
            'flagged' => ['required', 'boolean'],
        ]);

        $click->update(['manually_flagged' => $data['flagged']]);

        return response()->json(['message' => $data['flagged'] ? 'Ditandai mencurigakan' : 'Flag dihapus', 'id' => $id]);
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
            'gclid',
            'gbraid',
            'wbraid',
            'landing_page_url',
            'page_path',
            'referrer_url',
            'cta_type',
            'cta_source',
            'target_whatsapp_number',
            'device_type',
            'browser_name',
            'browser_version',
            'os_name',
            'os_version',
            'language',
            'timezone',
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
        $data['cta_source'] = $data['cta_source'] ? Str::lower($data['cta_source']) : null;
        $data['target_whatsapp_number'] = isset($data['target_whatsapp_number'])
            ? preg_replace('/\D+/', '', $data['target_whatsapp_number'])
            : null;
        $data['utm_source'] = $data['utm_source'] ?? null;
        $data['source'] = $data['utm_source'] ?: ($data['source'] ?? null);
        $data['ip_address'] = $request->ip();
        $data['user_agent'] = Str::limit((string) $request->userAgent(), 500, '');
        $data['ip_city'] = $this->headerValue($request, 'CF-IPCity', 120);
        $data['ip_region'] = $this->headerValue($request, 'CF-Region', 120);
        $data['ip_country'] = $this->headerValue($request, 'CF-IPCountry', 2);

        return $data;
    }

    protected function availableOptionalColumns(): array
    {
        return array_values(array_filter([
            'gclid',
            'gbraid',
            'wbraid',
            'page_path',
            'referrer_url',
            'device_type',
            'browser_name',
            'browser_version',
            'os_name',
            'os_version',
            'screen_width',
            'screen_height',
            'language',
            'timezone',
            'ip_city',
            'ip_region',
            'ip_country',
        ], fn ($column) => Schema::hasColumn('crm_cta_clicks', $column)));
    }

    protected function filterUnavailableOptionalColumns(array $data, array $availableColumns): array
    {
        $available = array_flip($availableColumns);

        foreach ([
            'gclid',
            'gbraid',
            'wbraid',
            'page_path',
            'referrer_url',
            'device_type',
            'browser_name',
            'browser_version',
            'os_name',
            'os_version',
            'screen_width',
            'screen_height',
            'language',
            'timezone',
            'ip_city',
            'ip_region',
            'ip_country',
        ] as $column) {
            if (!isset($available[$column])) {
                unset($data[$column]);
            }
        }

        return $data;
    }

    protected function headerValue(Request $request, string $header, int $limit): ?string
    {
        $value = trim((string) $request->headers->get($header, ''));

        return $value === '' ? null : Str::limit($value, $limit, '');
    }
}
