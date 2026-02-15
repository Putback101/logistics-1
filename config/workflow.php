<?php
// logistics-1/config/workflow.php

function wf_po_can_transition(string $from, string $to): bool {
  $map = [
    // Drafting / approval loop
    'Draft' => ['Pending Approval', 'Sent'],
    'Pending Approval' => ['Approved', 'Rejected'],
    'Rejected' => ['Draft', 'Pending Approval'],

    // Fulfillment loop
    'Approved' => ['Sent'],
    'Sent' => ['Received','Returned'],
    'Returned' => ['Sent'],

    // Terminal
    'Received' => [],
  ];

  return in_array($to, $map[$from] ?? [], true);
}

function wf_po_next(string $action): ?string {
  return match ($action) {
    'request_approval' => 'Pending Approval',
    'approve' => 'Approved',
    'reject' => 'Rejected',
    'send_to_supplier' => 'Sent',
    'receive_goods' => 'Received',
    'return_to_supplier' => 'Returned',
    default => null
  };
}
