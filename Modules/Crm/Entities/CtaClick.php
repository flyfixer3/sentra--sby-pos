<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CtaClick extends Model
{
    protected $table = 'crm_cta_clicks';

    protected $guarded = [];

    protected $appends = [
        'cta_source_label',
        'cta_type_label',
    ];

    protected $casts = [
        'first_clicked_at'  => 'datetime',
        'last_clicked_at'   => 'datetime',
        'click_count'       => 'integer',
        'suspicious_score'  => 'integer',
        'suspicious_flags'  => 'array',
        'manually_flagged'  => 'boolean',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function getCtaSourceLabelAttribute(): string
    {
        return static::formatCtaSourceLabel($this->cta_source);
    }

    public function getCtaTypeLabelAttribute(): string
    {
        $type = trim((string) ($this->cta_type ?? ''));

        if ($type === '') {
            return '-';
        }

        return match ($type) {
            'whatsapp' => 'WhatsApp',
            'phone' => 'Telepon',
            'maps' => 'Google Maps',
            default => Str::headline(str_replace(['_', '-'], ' ', $type)),
        };
    }

    public static function formatCtaSourceLabel(?string $ctaSource): string
    {
        $value = trim((string) ($ctaSource ?? ''));

        if ($value === '') {
            return '-';
        }

        $map = [
            'wa_hero_primary' => 'Hero - Tombol Utama',
            'wa_hero_home_service' => 'Hero - Home Service',
            'wa_floating' => 'Floating WhatsApp',
            'wa_header' => 'Header',
            'wa_footer' => 'Footer',
            'wa_article' => 'Artikel',
            'wa_home_service_banner' => 'Banner Home Service',
            'wa_process' => 'Proses Layanan',
            'wa_social_proof' => 'Social Proof',
            'wa_reviews' => 'Ulasan',
            'wa_faq' => 'FAQ',
        ];

        if (isset($map[$value])) {
            return $map[$value];
        }

        if (Str::startsWith($value, 'wa_locations_')) {
            $location = Str::after($value, 'wa_locations_');
            return 'Lokasi ' . Str::headline(str_replace(['_', '-'], ' ', $location));
        }

        if (Str::startsWith($value, 'wa_services_bottom_')) {
            $service = Str::after($value, 'wa_services_bottom_');
            return 'Layanan Bawah - ' . Str::headline(str_replace(['_', '-'], ' ', $service));
        }

        if (Str::startsWith($value, 'wa_services_')) {
            $service = Str::after($value, 'wa_services_');
            return 'Layanan - ' . Str::headline(str_replace(['_', '-'], ' ', $service));
        }

        return Str::headline(str_replace(['_', '-'], ' ', $value));
    }
}
