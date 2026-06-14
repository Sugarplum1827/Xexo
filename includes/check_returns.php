<?php
/**
 * Auto-generate return requests for expired budget allocations and inventory.
 * Called once per page load from header.php — lightweight, uses indexed queries.
 */
function autoGenerateReturnRequests($conn) {

    // ── 1. BUDGET ALLOCATIONS: expired and not yet have a return request ──
    $expiredAllocs = $conn->query("
        SELECT ba.*
        FROM budget_allocations ba
        WHERE ba.admin_approval_status = 'approved'
          AND ba.end_datetime IS NOT NULL
          AND ba.end_datetime <= NOW()
          AND NOT EXISTS (
              SELECT 1 FROM return_requests rr
              WHERE rr.budget_allocation_id = ba.id
                AND rr.return_type = 'budget'
          )
    ");

    if ($expiredAllocs) {
        while ($alloc = $expiredAllocs->fetch_assoc()) {
            $remaining   = (float)$alloc['allocated_amount'] - (float)$alloc['amount_used'];
            $returnAmt   = max(0, $remaining);
            $purpose     = $conn->real_escape_string($alloc['purpose'] ?? $alloc['allocation_title'] ?? '');
            $due         = $conn->real_escape_string($alloc['end_datetime']);
            $alloc_id    = (int)$alloc['id'];

            // For shared allocations: one return request with encoder_id = the creator
            // For personal: encoder_id = the assigned encoder
            $enc_id = $alloc['is_shared'] ? (int)$alloc['created_by'] : (int)$alloc['encoder_id'];
            if (!$enc_id) $enc_id = (int)$alloc['created_by'];

            $conn->query("
                INSERT INTO return_requests
                    (return_type, encoder_id, budget_allocation_id, original_purpose,
                     return_amount, return_status, due_datetime, created_at)
                VALUES
                    ('budget', $enc_id, $alloc_id, '$purpose',
                     $returnAmt, 'not_yet_returned', '$due', NOW())
            ");
        }
    }

    // ── 2. ENCODER INVENTORY: expired and not yet have a return request ──
    $expiredInv = $conn->query("
        SELECT ei.*
        FROM encoder_inventory ei
        WHERE ei.end_datetime IS NOT NULL
          AND ei.end_datetime <= NOW()
          AND NOT EXISTS (
              SELECT 1 FROM return_requests rr
              WHERE rr.encoder_inventory_id = ei.id
                AND rr.return_type = 'inventory'
          )
    ");

    if ($expiredInv) {
        while ($ei = $expiredInv->fetch_assoc()) {
            $remaining  = (float)$ei['quantity_assigned'] - (float)$ei['quantity_consumed'];
            $returnQty  = max(0, $remaining);
            $purpose    = $conn->real_escape_string($ei['purpose'] ?? '');
            $due        = $conn->real_escape_string($ei['end_datetime']);
            $ei_id      = (int)$ei['id'];
            $enc_id     = (int)$ei['encoder_id'];

            $conn->query("
                INSERT INTO return_requests
                    (return_type, encoder_id, encoder_inventory_id, original_purpose,
                     return_quantity, return_status, due_datetime, created_at)
                VALUES
                    ('inventory', $enc_id, $ei_id, '$purpose',
                     $returnQty, 'not_yet_returned', '$due', NOW())
            ");
        }
    }
}
