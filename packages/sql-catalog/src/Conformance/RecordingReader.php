<?php

declare(strict_types=1);

namespace SqlCatalog\Conformance;

use JsonException;

/**
 * Reads a recording of what a program sent to a database.
 *
 * The recording is a JSON document holding an `observations` array, each entry
 * naming the statement, the values bound to it and where it came from. That is
 * what a driver wrapper, a query log or a test harness can hand over.
 *
 * @visibility public
 * @example Reading a recording of one statement
 *     $reader = new \SqlCatalog\Conformance\RecordingReader();
 *     $observed = $reader->read('{"observations":[{"sql":"SELECT 1","positional":[],"named":[],"source":"a.php"}]}');
 *     $observed[0]->normalized() // => 'SELECT 1'
 */
final class RecordingReader
{
    /**
     * The statements a recording holds.
     *
     * @return list<ObservedStatement>
     * @throws JsonException When the recording is not valid JSON
     */
    public function read(string $json): array
    {
        $document = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        $rows = is_array($document) ? ($document['observations'] ?? []) : [];
        if (!is_array($rows)) {
            return [];
        }

        $observations = [];
        foreach ($rows as $row) {
            $observation = is_array($row) ? $this->readOne($row) : null;
            if ($observation !== null) {
                $observations[] = $observation;
            }
        }

        return $observations;
    }

    /**
     * One recorded statement, or null when the entry does not name one.
     *
     * @param array<array-key, mixed> $row
     */
    public function readOne(array $row): ?ObservedStatement
    {
        $sql = $row['sql'] ?? null;
        if (!is_string($sql)) {
            return null;
        }
        $source = $row['source'] ?? '';

        return new ObservedStatement(
            $sql,
            $this->scalars($row['positional'] ?? []),
            $this->keyedScalars($row['named'] ?? []),
            is_string($source) ? $source : '',
        );
    }

    /**
     * The scalar values of a recorded list, in order.
     *
     * @return list<string|int|float|bool|null>
     */
    public function scalars(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }
        $scalars = [];
        foreach ($values as $value) {
            $scalars[] = is_scalar($value) || $value === null ? $value : null;
        }

        return $scalars;
    }

    /**
     * The scalar values of a recorded map, keyed the way they were bound.
     *
     * @return array<string, string|int|float|bool|null>
     */
    public function keyedScalars(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }
        $scalars = [];
        foreach ($values as $key => $value) {
            $scalars[(string) $key] = is_scalar($value) || $value === null ? $value : null;
        }

        return $scalars;
    }
}
