<?php
// core/special_destinations.php
// Helper centrali per destinazioni speciali Turnar

if (defined('TURNAR_SPECIAL_DESTINATIONS_LOADED')) {
    return;
}
define('TURNAR_SPECIAL_DESTINATIONS_LOADED', true);

if (!function_exists('special_destination_types')) {
    function special_destination_types(): array
    {
        return [
            'ferie' => [
                'label' => 'Ferie',
                'counts_as_work' => 0,
                'counts_as_absence' => 1,
                'badge_class' => 'badge-warning',
                'description' => 'Assenza programmata per ferie.',
            ],
            'permesso' => [
                'label' => 'Permesso',
                'counts_as_work' => 0,
                'counts_as_absence' => 1,
                'badge_class' => 'badge-warning',
                'description' => 'Assenza per permesso orario o giornaliero.',
            ],
            'malattia' => [
                'label' => 'Malattia',
                'counts_as_work' => 0,
                'counts_as_absence' => 1,
                'badge_class' => 'badge-danger',
                'description' => 'Assenza per malattia.',
            ],
            'corso' => [
                'label' => 'Corso di formazione',
                'counts_as_work' => 1,
                'counts_as_absence' => 0,
                'badge_class' => 'badge-primary',
                'description' => 'Attività formativa conteggiata come presenza lavorativa.',
            ],
            'altro' => [
                'label' => 'Altro speciale',
                'counts_as_work' => 0,
                'counts_as_absence' => 0,
                'badge_class' => 'badge-purple',
                'description' => 'Destinazione speciale personalizzata.',
            ],
        ];
    }
}

if (!function_exists('normalize_special_destination_type')) {
    function normalize_special_destination_type(?string $type): string
    {
        $type = mb_strtolower(trim((string)$type), 'UTF-8');
        $type = str_replace([' ', '_'], '-', $type);

        $aliases = [
            'feria' => 'ferie',
            'holiday' => 'ferie',
            'vacation' => 'ferie',
            'permessi' => 'permesso',
            'permessi-retribuiti' => 'permesso',
            'permesso-retribuito' => 'permesso',
            'sick' => 'malattia',
            'sickness' => 'malattia',
            'malattie' => 'malattia',
            'corsi' => 'corso',
            'formazione' => 'corso',
            'corso-di-formazione' => 'corso',
            'training' => 'corso',
        ];

        if (isset($aliases[$type])) {
            $type = $aliases[$type];
        }

        return array_key_exists($type, special_destination_types()) ? $type : 'altro';
    }
}

if (!function_exists('guess_special_destination_type')) {
    function guess_special_destination_type(?string $name): string
    {
        $name = mb_strtolower(trim((string)$name), 'UTF-8');

        if ($name === '') {
            return 'altro';
        }

        if (str_contains($name, 'ferie') || str_contains($name, 'feria')) {
            return 'ferie';
        }

        if (str_contains($name, 'permess')) {
            return 'permesso';
        }

        if (str_contains($name, 'malatt')) {
            return 'malattia';
        }

        if (str_contains($name, 'corso') || str_contains($name, 'formazione')) {
            return 'corso';
        }

        return 'altro';
    }
}

if (!function_exists('is_special_destination')) {
    function is_special_destination($destination): bool
    {
        if (is_array($destination)) {
            return !empty($destination['is_special']);
        }

        return !empty($destination);
    }
}

if (!function_exists('special_destination_type')) {
    function special_destination_type(array $destination): string
    {
        $type = trim((string)($destination['special_type'] ?? ''));

        if ($type !== '') {
            return normalize_special_destination_type($type);
        }

        if (!empty($destination['is_special'])) {
            return guess_special_destination_type((string)($destination['commessa'] ?? ''));
        }

        return '';
    }
}

if (!function_exists('special_destination_label')) {
    function special_destination_label(?string $type): string
    {
        $type = normalize_special_destination_type($type);
        $types = special_destination_types();
        return (string)($types[$type]['label'] ?? 'Speciale');
    }
}

if (!function_exists('special_destination_badge_class')) {
    function special_destination_badge_class(?string $type): string
    {
        $type = normalize_special_destination_type($type);
        $types = special_destination_types();
        return (string)($types[$type]['badge_class'] ?? 'badge-purple');
    }
}

if (!function_exists('destination_counts_as_absence')) {
    function destination_counts_as_absence(array $destination): bool
    {
        if (array_key_exists('counts_as_absence', $destination)) {
            return !empty($destination['counts_as_absence']);
        }

        if (!is_special_destination($destination)) {
            return false;
        }

        $type = special_destination_type($destination);
        $types = special_destination_types();
        return !empty($types[$type]['counts_as_absence']);
    }
}

if (!function_exists('destination_counts_as_work')) {
    function destination_counts_as_work(array $destination): bool
    {
        if (array_key_exists('counts_as_work', $destination)) {
            return !empty($destination['counts_as_work']);
        }

        if (!is_special_destination($destination)) {
            return true;
        }

        $type = special_destination_type($destination);
        $types = special_destination_types();
        return !empty($types[$type]['counts_as_work']);
    }
}

if (!function_exists('special_destination_defaults_for_type')) {
    function special_destination_defaults_for_type(?string $type): array
    {
        $type = normalize_special_destination_type($type);
        $types = special_destination_types();
        $data = $types[$type] ?? $types['altro'];

        return [
            'special_type' => $type,
            'counts_as_work' => (int)($data['counts_as_work'] ?? 0),
            'counts_as_absence' => (int)($data['counts_as_absence'] ?? 0),
        ];
    }
}

if (!function_exists('destination_kind_label')) {
    function destination_kind_label(array $destination): string
    {
        if (!is_special_destination($destination)) {
            return 'Operativa';
        }

        return special_destination_label(special_destination_type($destination));
    }
}
