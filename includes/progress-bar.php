<?php
/**
 * Order Status Progress Display Component
 * Maps real database status and progress to visual stages
 */

function getOrderProgress($status, $progress_db = null) {
    $status_lower = strtolower((string)$status);
    $progress_db = !is_null($progress_db) ? max(0, min(100, (int)$progress_db)) : null;
    
    // Priority 1: Check for final statuses
    if (strpos($status_lower, 'complet') !== false || strpos($status_lower, 'partial') !== false) {
        return [
            'percent' => 100,
            'stage' => 'completed',
            'label' => 'Kumalizika',
            'color' => '#10b981',
            'status_text' => (string)$status
        ];
    }
    
    if (strpos($status_lower, 'cancel') !== false || strpos($status_lower, 'fail') !== false 
        || strpos($status_lower, 'refund') !== false || strpos($status_lower, 'error') !== false) {
        return [
            'percent' => 0,
            'stage' => 'canceled',
            'label' => 'Imeghairiwa',
            'color' => '#ef4444',
            'status_text' => (string)$status
        ];
    }
    
    // Priority 2: Use database progress value if available
    if (!is_null($progress_db)) {
        if ($progress_db >= 90) {
            $stage = 'processing';
            $label = 'Karibu kumalizika';
            $color = '#3b82f6';
        } elseif ($progress_db >= 50) {
            $stage = 'processing';
            $label = 'Inakadiriwa';
            $color = '#3b82f6';
        } else {
            $stage = 'pending';
            $label = 'Inasubiri';
            $color = '#f59e0b';
        }
        
        return [
            'percent' => $progress_db,
            'stage' => $stage,
            'label' => $label,
            'color' => $color,
            'status_text' => (string)$status
        ];
    }
    
    // Priority 3: Map status text to progress
    if (strpos($status_lower, 'process') !== false || strpos($status_lower, 'in progress') !== false) {
        return [
            'percent' => 66,
            'stage' => 'processing',
            'label' => 'Inakadiriwa',
            'color' => '#3b82f6',
            'status_text' => (string)$status
        ];
    }
    
    // Default: pending
    return [
        'percent' => 33,
        'stage' => 'pending',
        'label' => 'Inasubiri',
        'color' => '#f59e0b',
        'status_text' => (string)$status
    ];
}

function renderProgressBar($status, $progress_db = null) {
    $prog = getOrderProgress($status, $progress_db);
    $percent = $prog['percent'];
    $stage = $prog['stage'];
    $label = $prog['label'];
    $color = $prog['color'];
    
    $colorMap = [
        'pending' => '#fbbf24',
        'processing' => '#60a5fa', 
        'completed' => '#34d399',
        'canceled' => '#f87171'
    ];
    
    $gradient = $colorMap[$stage] ?? '#667eea';
    
    return <<<HTML
    <div class="order-progress">
        <div class="progress-track">
            <div class="progress-fill" style="width: {$percent}%; background: linear-gradient(90deg, {$gradient}, {$color})"></div>
        </div>
        <div class="progress-info">
            <span class="progress-label" style="color: {$color}"><strong>{$label}</strong></span>
            <span class="progress-percent">{$percent}%</span>
        </div>
        <div class="progress-stages">
            <div class="pstage" style="color: #fbbf24"><i class="bi bi-hourglass-split"></i> Inasubiri</div>
            <div class="pstage" style="color: #60a5fa"><i class="bi bi-arrow-repeat"></i> Inakadiriwa</div>
            <div class="pstage" style="color: #34d399"><i class="bi bi-check-circle-fill"></i> Kumalizika</div>
        </div>
    </div>
    HTML;
}
?>
