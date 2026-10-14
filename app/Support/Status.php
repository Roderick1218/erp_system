<?php

namespace App\Support;

/**
 * One place that decides the colour of every status pill in the system.
 * Tones map to CSS classes .badge-{tone} in public/css/app.css.
 */
class Status
{
    private const TONES = [
        'success' => ['active', 'approved', 'paid', 'completed', 'won', 'received', 'done', 'accepted', 'closed', 'finalized', 'in', 'present'],
        'info' => ['in_progress', 'scheduled', 'sent', 'ordered', 'inspection', 'quotation', 'doing', 'investigating', 'proforma'],
        'warning' => ['pending', 'partial', 'on_hold', 'negotiation', 'draft', 'open', 'todo', 'late', 'medium', 'high', 'expiring'],
        'danger' => ['rejected', 'cancelled', 'lost', 'void', 'overdue', 'expired', 'blacklist', 'urgent', 'critical', 'absent', 'resigned', 'suspended'],
        'neutral' => ['inactive', 'new', 'planning', 'low', 'normal'],
    ];

    public static function tone(?string $status): string
    {
        foreach (self::TONES as $tone => $list) {
            if (in_array($status, $list, true)) {
                return $tone;
            }
        }

        return 'neutral';
    }

    public static function label(?string $status): string
    {
        return __(ucwords(str_replace('_', ' ', (string) $status)));
    }
}
