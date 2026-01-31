<?php
function badge_class(string $text): string {
    $t = strtolower(trim($text));

    // Fleet statuses
    if ($t === 'available') return 'bg-success';
    if ($t === 'in use') return 'bg-primary';
    if ($t === 'maintenance') return 'bg-warning text-dark';

    // Procurement statuses
    if ($t === 'pending') return 'bg-secondary';
    if ($t === 'approved') return 'bg-info text-dark';
    if ($t === 'delivered') return 'bg-success';

    return 'bg-light text-dark border';
}
