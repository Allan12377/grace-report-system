<?php
declare(strict_types=1);

function default_school_requirements(array $classes): array
{
    $schedule = [];
    foreach ($classes as $class) {
        $classId = (int)$class['id'];
        $schedule[$classId] = [
            'day' => [
                ['label' => 'School fee', 'value' => 'UGX 200,000'],
                ['label' => 'Brooms', 'value' => '2'],
                ['label' => 'Toilet paper', 'value' => '6'],
            ],
            'boarding' => [
                ['label' => 'School fee', 'value' => 'UGX 480,000'],
                ['label' => 'Brooms', 'value' => '2'],
                ['label' => 'Toilet paper', 'value' => '6'],
            ],
        ];
    }
    return $schedule;
}

function decode_school_requirements(string $json, array $classes): array
{
    $defaults = default_school_requirements($classes);
    $saved = json_decode($json, true);
    if (!is_array($saved)) {
        return $defaults;
    }

    foreach ($classes as $class) {
        $classId = (int)$class['id'];
        foreach (['day', 'boarding'] as $attendanceType) {
            $items = $saved[$classId][$attendanceType] ?? null;
            if (!is_array($items)) {
                continue;
            }
            $normalized = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $label = trim((string)($item['label'] ?? ''));
                if ($label !== '') {
                    $normalized[] = [
                        'label' => $label,
                        'value' => trim((string)($item['value'] ?? '')),
                    ];
                }
            }
            $defaults[$classId][$attendanceType] = $normalized;
        }
    }
    return $defaults;
}

function parse_school_requirements(string $text): array
{
    $items = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        [$label, $value] = array_pad(explode(':', $line, 2), 2, '');
        $label = trim($label);
        if ($label === '') {
            continue;
        }
        $items[] = ['label' => $label, 'value' => trim($value)];
    }
    return $items;
}

function format_school_requirements(array $items): string
{
    return implode("\n", array_map(
        static fn(array $item): string => $item['label'] . ': ' . $item['value'],
        $items
    ));
}
