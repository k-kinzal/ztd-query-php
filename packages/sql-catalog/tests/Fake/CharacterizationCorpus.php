<?php

declare(strict_types=1);

namespace Tests\Fake;

use JsonException;
use SqlCatalog\Core\Extension\UnknownExtensionException;
use UnexpectedValueException;

/**
 * Reads the recorded characterization cases.
 *
 * Each file holds a list of cases: `sources` keyed by path, `options` with
 * `extensions`, `dialect`, `budget` and optionally `probe` and
 * `functionModels`, and the recorded `report`.
 */
final class CharacterizationCorpus
{
    /**
     * The cases of every corpus file in a directory, keyed by file name and position.
     *
     * @return array<string, CharacterizationCase>
     * @throws JsonException When a file is not valid JSON
     * @throws UnexpectedValueException When a file does not hold cases
     */
    public static function load(string $directory): array
    {
        $cases = [];
        $files = glob($directory . '/*.json');
        foreach ($files === false ? [] : $files as $file) {
            $contents = file_get_contents($file);
            $decoded = json_decode($contents === false ? '' : $contents, true, 512, JSON_THROW_ON_ERROR);
            foreach (self::listOf($decoded, $file) as $index => $case) {
                $cases[basename($file, '.json') . '#' . $index] = self::caseOf($case, $file . '#' . $index);
            }
        }

        return $cases;
    }

    /**
     * Replaces every recorded report with the one the analyzer produces now, keeping sources and options.
     *
     * Run it only after reviewing the changed reports: it turns the current behavior into the expected one.
     *
     * @throws JsonException When a file is not valid JSON
     * @throws UnexpectedValueException When a file does not hold cases
     * @throws UnknownExtensionException When a case names an extension that is not registered
     */
    public static function record(string $directory): void
    {
        $files = glob($directory . '/*.json');
        foreach ($files === false ? [] : $files as $file) {
            $contents = file_get_contents($file);
            $recorded = [];
            foreach (self::listOf(json_decode($contents === false ? '' : $contents, true, 512, JSON_THROW_ON_ERROR), $file) as $index => $case) {
                $report = json_decode(self::caseOf($case, $file . '#' . $index)->report(), true, 512, JSON_THROW_ON_ERROR);
                $recorded[] = is_array($case) ? array_merge($case, ['report' => $report]) : $case;
            }
            file_put_contents($file, CharacterizationCase::encode($recorded) . "\n");
        }
    }

    /**
     * @return list<mixed>
     * @throws UnexpectedValueException
     */
    public static function listOf(mixed $value, string $where): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new UnexpectedValueException($where . ' must be a list.');
        }

        return $value;
    }

    /**
     * @throws JsonException
     * @throws UnexpectedValueException
     */
    public static function caseOf(mixed $case, string $where): CharacterizationCase
    {
        if (!is_array($case) || !is_array($case['options'] ?? null) || !array_key_exists('report', $case)) {
            throw new UnexpectedValueException($where . ' must hold sources, options and a report.');
        }
        $options = $case['options'];
        $budget = $options['budget'] ?? null;

        return new CharacterizationCase(
            self::stringMap($case['sources'] ?? null, $where),
            $options['extensions'] === null ? ['pdo', 'mysqli'] : self::strings($options['extensions'], $where),
            is_string($options['dialect'] ?? null) ? $options['dialect'] : null,
            $budget === null ? null : self::budget($budget, $where),
            self::probe($options['probe'] ?? [], $where),
            self::stringMap($options['functionModels'] ?? [], $where),
            CharacterizationCase::encode($case['report']),
        );
    }

    /**
     * @return array<string, string>
     * @throws UnexpectedValueException
     */
    public static function stringMap(mixed $value, string $where): array
    {
        $map = [];
        foreach (is_array($value) ? $value : throw new UnexpectedValueException($where . ' expects a map.') as $key => $item) {
            $map[(string) $key] = is_string($item) ? $item : throw new UnexpectedValueException($where . ' expects string values.');
        }

        return $map;
    }

    /**
     * @return list<string>
     * @throws UnexpectedValueException
     */
    public static function strings(mixed $value, string $where): array
    {
        return array_values(self::stringMap($value, $where));
    }

    /**
     * @return array{int, int, int}
     * @throws UnexpectedValueException
     */
    public static function budget(mixed $value, string $where): array
    {
        if (!is_array($value) || !is_int($value[0] ?? null) || !is_int($value[1] ?? null) || !is_int($value[2] ?? null)) {
            throw new UnexpectedValueException($where . ' expects a budget of three integers.');
        }

        return [$value[0], $value[1], $value[2]];
    }

    /**
     * @return list<array{string, string, string|null, string}>
     * @throws UnexpectedValueException
     */
    public static function probe(mixed $value, string $where): array
    {
        $calls = [];
        foreach (self::listOf($value, $where) as $call) {
            $class = is_array($call) ? $call[2] ?? null : null;
            if (!is_array($call) || !is_string($call[0] ?? null) || !is_string($call[1] ?? null) || !is_string($call[3] ?? null) || !(is_string($class) || $class === null)) {
                throw new UnexpectedValueException($where . ' expects probe calls of an ID, a kind, a class and a name.');
            }
            $calls[] = [$call[0], $call[1], $class, $call[3]];
        }

        return $calls;
    }
}
