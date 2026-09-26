<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown\Render;

use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use stdClass;

/**
 * Reads the fields of a record of a decoded definition.
 */
final class Record
{
    /**
     * Returns the fields of a record.
     *
     * @param mixed $value The record
     *
     * @return array<string, mixed> The fields
     *
     * @throws InvalidInputException When the value is not a record
     */
    public static function fields(mixed $value): array
    {
        if (!$value instanceof stdClass) {
            throw new InvalidInputException('Expected a Markdown record.');
        }
        return Fields::mapping(get_object_vars($value), 'record');
    }
}
