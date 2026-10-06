<?php

declare(strict_types=1);

namespace Deriver\Result\Serialization\Decode;

use Deriver\Exception\InvalidInputException;
use stdClass;

/**
 * Narrows JSON fields before they enter the typed report decoder.
 * @visibility root
 */
final class Fields
{
    /**
     * @param stdClass $record JSON object
     * @param string $name Required field
     * @return string String field
     * @throws InvalidInputException If the field is absent or not a string
     */
    public function text(stdClass $record, string $name): string
    {
        $value = get_object_vars($record)[$name] ?? null;
        if (!is_string($value)) {
            throw new InvalidInputException('Expected JSON string field: ' . $name);
        }
        return $value;
    }

    /**
     * @param stdClass $record JSON object
     * @param string $name Required field
     * @return bool Boolean field
     * @throws InvalidInputException If the field is absent or not Boolean
     */
    public function boolean(stdClass $record, string $name): bool
    {
        $value = get_object_vars($record)[$name] ?? null;
        if (!is_bool($value)) {
            throw new InvalidInputException('Expected JSON Boolean field: ' . $name);
        }
        return $value;
    }

    /**
     * @param stdClass $record JSON object
     * @param string $name Required field
     * @return stdClass Object field
     * @throws InvalidInputException If the field is absent or not an object
     */
    public function object(stdClass $record, string $name): stdClass
    {
        $value = get_object_vars($record)[$name] ?? null;
        if (!$value instanceof stdClass) {
            throw new InvalidInputException('Expected JSON object field: ' . $name);
        }
        return $value;
    }

    /**
     * @param stdClass $record JSON object
     * @param string $name Required field
     * @return list<stdClass> Ordered object records
     * @throws InvalidInputException If the field is not an array of objects
     */
    public function objects(stdClass $record, string $name): array
    {
        $values = get_object_vars($record)[$name] ?? null;
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidInputException('Expected JSON list field: ' . $name);
        }
        $records = [];
        foreach ($values as $value) {
            if (!$value instanceof stdClass) {
                throw new InvalidInputException('Expected JSON object in list: ' . $name);
            }
            $records[] = $value;
        }
        return $records;
    }
}
