<?php

function convertTimeRequiredToSeconds($timeRequired) {
    $unit = substr($timeRequired, -1);
    $value = intval(substr($timeRequired, 0, -1));
    
    if ($unit === 'h') {
        return $value * 3600; // hours to seconds
    } elseif ($unit === 'd') {
        return $value * 86400; // days to seconds
    }
    return 0;
}

function calculatePeriodScore($period_start, $period_end, $preferences) {
    $score = 0;
    foreach ($preferences as $pref) {
        $pref_start = strtotime($pref['start_date']);
        $pref_end = strtotime($pref['end_date']);
        
        // Check if preference completely contains the period
        if ($pref_start <= $period_start && $pref_end >= $period_end) {
            $score += intval($pref['preference_score']);
        }
    }
    return $score;
}

function doPeriodsOverlap($period1, $period2) {
    return ($period1['start'] <= $period2['end'] && $period2['start'] <= $period1['end']);
}

function mergePeriods($period1, $period2) {
    return [
        'start' => min($period1['start'], $period2['start']),
        'end' => max($period1['end'], $period2['end']),
        'score' => $period1['score'] // scores are equal for merging
    ];
}

function mergeOverlappingPeriods($periods) {
    if (empty($periods)) {
        return [];
    }

    // Sort periods by start time
    usort($periods, function($a, $b) {
        return $a['start'] - $b['start'];
    });

    $merged = [];
    $current = $periods[0];

    for ($i = 1; $i < count($periods); $i++) {
        if (doPeriodsOverlap($current, $periods[$i]) && $current['score'] === $periods[$i]['score']) {
            // Merge overlapping periods with equal scores
            $current = mergePeriods($current, $periods[$i]);
        } else {
            $merged[] = $current;
            $current = $periods[$i];
        }
    }
    $merged[] = $current;

    return $merged;
}

function findPerfectTime($timeRequired, $preferences) {
    if (empty($preferences)) {
        return null;
    }

    $duration = convertTimeRequiredToSeconds($timeRequired);
    if ($duration === 0) {
        return null;
    }

    // Sort preferences by start date
    usort($preferences, function($a, $b) {
        return strtotime($a['start_date']) - strtotime($b['start_date']);
    });

    $periods = [];
    $latest_end = max(array_map(function($pref) {
        return strtotime($pref['end_date']);
    }, $preferences));

    // For each preference start time, calculate period score
    foreach ($preferences as $pref) {
        $period_start = strtotime($pref['start_date']);
        $period_end = $period_start + $duration;

        // Don't process if period extends beyond the latest preference
        if ($period_end > $latest_end) {
            continue;
        }

        $score = calculatePeriodScore($period_start, $period_end, $preferences);
        
        if ($score > 0) {  // Only include periods that have at least one complete preference
            $periods[] = [
                'start' => $period_start,
                'end' => $period_end,
                'score' => $score
            ];
        }
    }

    // Sort periods by score (descending)
    usort($periods, function($a, $b) {
        if ($b['score'] !== $a['score']) {
            return $b['score'] - $a['score'];
        }
        // If scores are equal, sort by start time
        return $a['start'] - $b['start'];
    });

    // Merge overlapping periods with equal scores
    $merged_periods = [];
    $current_score = null;
    $current_group = [];

    foreach ($periods as $period) {
        if ($current_score === null) {
            $current_score = $period['score'];
            $current_group = [$period];
        } elseif ($period['score'] === $current_score) {
            $current_group[] = $period;
        } else {
            // Merge and add the current group
            $merged_periods = array_merge($merged_periods, mergeOverlappingPeriods($current_group));
            $current_score = $period['score'];
            $current_group = [$period];
        }
    }
    
    // Don't forget to merge the last group
    if (!empty($current_group)) {
        $merged_periods = array_merge($merged_periods, mergeOverlappingPeriods($current_group));
    }

    return $merged_periods;
}

function formatPeriodDateTime($timestamp) {
    return date('Y-m-d H:i', $timestamp);
}
?> 