<?php

namespace App\Support;

/** How each notification type is shown: icon, badge, label, colours and link text. */
class NotificationType
{
    /** @return array{icon:string, badge:string, label:string, bg:string, color:string, actionText:string} */
    public static function config(string $type): array
    {
        $type = strtolower($type);

        return match (true) {
            str_contains($type, 'sos_acknowledged') => [
                'icon' => '🚨',
                'badge' => 'badge-warning',
                'label' => 'SOS Acknowledged',
                'bg' => '#fbf6e8',
                'color' => '#9a6700',
                'actionText' => 'View Status'
            ],
            str_contains($type, 'sos_resolved') => [
                'icon' => '🛡️',
                'badge' => 'badge-success',
                'label' => 'SOS Resolved',
                'bg' => '#eef6f1',
                'color' => '#2f6f4e',
                'actionText' => 'View History'
            ],
            str_contains($type, 'sos') => [
                'icon' => '🚨',
                'badge' => 'badge-error',
                'label' => 'Emergency SOS',
                'bg' => '#fdf1f0',
                'color' => '#b42318',
                'actionText' => 'View & Take Action'
            ],
            str_contains($type, 'maintenance_resolved') => [
                'icon' => '✓',
                'badge' => 'badge-success',
                'label' => 'Repair Completed',
                'bg' => '#eef6f1',
                'color' => '#2f6f4e',
                'actionText' => 'View History'
            ],
            str_contains($type, 'maintenance') || str_contains($type, 'repair') => [
                'icon' => '🔧',
                'badge' => 'badge-info',
                'label' => 'Maintenance',
                'bg' => '#eef6f1',
                'color' => '#2f6f4e',
                'actionText' => 'View Details'
            ],
            str_contains($type, 'payment_approved') => [
                'icon' => '✓',
                'badge' => 'badge-success',
                'label' => 'Payment Approved',
                'bg' => '#eef6f1',
                'color' => '#2f6f4e',
                'actionText' => 'View Payment'
            ],
            str_contains($type, 'payment_rejected') => [
                'icon' => '✕',
                'badge' => 'badge-error',
                'label' => 'Payment Rejected',
                'bg' => '#fdf1f0',
                'color' => '#b42318',
                'actionText' => 'Resubmit Payment'
            ],
            str_contains($type, 'rent') || str_contains($type, 'payment') || str_contains($type, 'penalty') => [
                'icon' => '💳',
                'badge' => 'badge-warning',
                'label' => 'Billing Notice',
                'bg' => '#fbf6e8',
                'color' => '#9a6700',
                'actionText' => 'View Details'
            ],
            str_contains($type, 'incident') => [
                'icon' => '📋',
                'badge' => 'badge-neutral',
                'label' => 'Incident Log',
                'bg' => '#f8f7f5',
                'color' => '#555452',
                'actionText' => 'View Details'
            ],
            str_contains($type, 'inquiry') => [
                'icon' => '📋',
                'badge' => 'badge-primary',
                'label' => 'Inquiry',
                'bg' => '#fbf4ef',
                'color' => '#b15f2c',
                'actionText' => 'View Details'
            ],
            default => [
                'icon' => '🔔',
                'badge' => 'badge-neutral',
                'label' => 'System Alert',
                'bg' => '#f1f0ee',
                'color' => '#555452',
                'actionText' => 'View Details'
            ],
        };
    }
}
