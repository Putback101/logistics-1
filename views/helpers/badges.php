<?php
function badge_class(string $text): string {
    $t = strtolower(trim($text));

    // Green: approved/received/success states
    if (in_array($t, ['approved', 'received', 'completed', 'done', 'active', 'available', 'pass', 'passed'], true)) {
        return 'bg-success';
    }

    // Orange: in-progress/waiting states
    if (in_array($t, ['pending', 'pending approval', 'in progress', 'in use', 'sent', 'returned', 'delivered', 'maintenance', 'on hold'], true)) {
        return 'bg-warning text-dark';
    }

    // Red: failed/blocked states
    if (in_array($t, ['rejected', 'failed', 'fail', 'cancelled', 'canceled', 'void', 'inactive', 'overdue'], true)) {
        return 'bg-danger';
    }

    // Gray: not started / draft / unknown
    if (in_array($t, ['not started', 'draft', 'new', 'planned'], true)) {
        return 'bg-secondary';
    }

    return 'bg-secondary';
}
