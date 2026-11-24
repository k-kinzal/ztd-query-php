<?php

declare(strict_types=1);

const CTAS_SCHEMA_NAMES = ['ir_fixture', 'ir_ctas_decoy'];
const CTAS_INPUT_NAMES = ['ir_ctas_source', 'ir_create_target', 'known'];

function validMysqlCreateAsSource(array $fixture, string $target, string $topology): bool
{
    [$schema, $name] = array_pad(explode('.', $fixture['location'], 2), 2, '');
    if (!in_array($schema, CTAS_SCHEMA_NAMES, true) || !in_array($name, CTAS_INPUT_NAMES, true)
        || !in_array($fixture['topology'], ['persistent', 'temporary-only', 'shadow'], true)
        || !in_array($fixture['layout'] ?? 'signed', ['signed', 'text', 'width-edge', 'retained-text'], true)) {
        return false;
    }

    if ($name === 'ir_create_target') {
        return $fixture['location'] === $target && $topology === 'persistent' && $fixture['topology'] === 'persistent';
    }

    return true;
}

function seedMysqlCreateAsInput(PDO $pdo, string $location, bool $temporary, int $base, string $layout): void
{
    $create = 'CREATE ' . ($temporary ? 'TEMPORARY ' : '') . 'TABLE ' . $location;
    if (str_ends_with($location, '.known')) {
        $pdo->exec($create . ' (metric INT NOT NULL DEFAULT 61) ENGINE=InnoDB');
        $pdo->exec('INSERT INTO ' . $location . ' VALUES (3), (3), (8)');

        return;
    }
    if ($layout === 'width-edge') {
        $pdo->exec($create . ' (v VARCHAR(8191) COLLATE utf8mb4_bin, z VARCHAR(0) COLLATE utf8mb4_bin) ENGINE=InnoDB ROW_FORMAT=DYNAMIC');
        $pdo->exec("INSERT INTO " . $location . " VALUES ('edge',''), (NULL,NULL)");

        return;
    }

    $fields = 'n INT NOT NULL DEFAULT ' . ($base + 31) . ', m INT NULL DEFAULT ' . ($base + 57);
    if ($layout !== 'signed') {
        $length = $layout === 'retained-text' ? 5 : 8;
        $wide = $base % 2 === 0 ? 63 : 64;
        $fields .= ", v VARCHAR(" . $length . ") COLLATE utf8mb4_bin DEFAULT 'v" . $base . " ',"
            . " z VARCHAR(0) COLLATE utf8mb4_bin NOT NULL DEFAULT '',"
            . " w VARCHAR(" . $wide . ") COLLATE utf8mb4_bin NOT NULL DEFAULT 'w" . $base . "'";
        $pdo->exec($create . ' (' . $fields . ') ENGINE=InnoDB');
        $pdo->exec("INSERT INTO " . $location . " VALUES (-7,-8,CONCAT('a',CHAR(0)),'','first'),"
            . " (3,NULL,'a','','t'), (3,NULL,'a ','','t'), (3,NULL,'a ','','t'), (8,5,NULL,'','tail'),"
            . " (" . ($base + 22) . ",NULL,'한','','w" . $base . "')");

        return;
    }
    $definition = ' (' . $fields . ') ENGINE=InnoDB';
    $pdo->exec($create . $definition);
    $pdo->exec('INSERT INTO ' . $location . ' VALUES (-7,-8), (3,NULL), (3,NULL), (3,' . ($base + 5)
        . '), (8,' . ($base + 12) . '), (' . ($base + 22) . ',NULL)');
}

function seedMysqlCreateAsSources(PDO $catalog, PDO $owner, array $fixture, int $variant): array
{
    $location = $fixture['location'];
    [, $name] = explode('.', $location, 2);
    $locations = [];
    foreach (CTAS_SCHEMA_NAMES as $index => $schema) {
        $candidate = $schema . '.' . $name;
        if ($name === 'ir_create_target' && $candidate !== $location) {
            continue;
        }
        $locations[] = $candidate;
        $catalog->exec('DROP TABLE IF EXISTS ' . $candidate);
        if ($candidate !== $location || $fixture['topology'] !== 'temporary-only') {
            seedMysqlCreateAsInput($catalog, $candidate, false, 10 + $index * 40 + $variant, $fixture['layout'] ?? 'signed');
        }
        if ($candidate === $location && $fixture['topology'] !== 'persistent') {
            seedMysqlCreateAsInput($owner, $candidate, true, 91 + $index * 40 + $variant, $fixture['layout'] ?? 'signed');
        }
    }

    return $locations;
}
