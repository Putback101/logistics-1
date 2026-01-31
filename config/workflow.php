<?php
// logistics-1/config/workflow.php

function wf_po_can_transition(string $from, string $to): bool {
  $map = [
    'Draft' => ['Pending Approval'],
    'Pending Approval' => ['Approved', 'Rejected'],
    'Rejected' => ['Draft'],
    'Approved' => ['Sent'],
    'Sent' => ['Received','Returned'],   // NEW: QC can fail after Sent
    'Returned' => ['Sent'],              // NEW: after return, you may re-send
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
    'return_to_supplier' => 'Returned', // NEW
    default => null
  };
}
