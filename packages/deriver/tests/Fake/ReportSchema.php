<?php

declare(strict_types=1);

namespace Tests\Fake;

use JsonException;
use Opis\JsonSchema\Validator;
use RuntimeException;
use stdClass;

/**
 * Validates serialized reports against the published JSON Schema independently.
 * @visibility root
 */
final class ReportSchema
{
    /**
     * @param string $json Candidate report
     * @return bool Whether the document satisfies the published schema
     * @throws JsonException If either JSON document is malformed
     * @throws RuntimeException If the packaged schema cannot be read
     */
    public static function accepts(string $json): bool
    {
        $schema = file_get_contents(dirname(__DIR__, 2) . '/resources/schema/result-v1.json');
        if ($schema === false) {
            throw new RuntimeException('The packaged result schema is missing.');
        }
        $document = json_decode($schema, false, 512, JSON_THROW_ON_ERROR);
        if (!$document instanceof stdClass) {
            throw new RuntimeException('The packaged result schema must be an object.');
        }
        return (new Validator())->validate(json_decode($json, false, 512, JSON_THROW_ON_ERROR), $document)->isValid();
    }
}
