<?php

declare(strict_types=1);

namespace Requirements\Config;

use JsonException;
use Requirements\Input\InvalidInputException;
use stdClass;

/**
 * Validates documents against the bundled JSON Schemas and any local schema they declare.
 *
 * A declared $schema is either the bundled schema's published URI, which resolves offline, or
 * a local JSON Schema path that adds constraints to the bundled schema.
 */
final class SchemaValidator
{
    /**
     * The published location of the bundled schemas, accepted as $schema without a download.
     */
    public const BASE = 'https://raw.githubusercontent.com/k-kinzal/ztd-query-php/main/packages/requirements/schemas/';

    /**
     * Validates a document.
     *
     * @param mixed $data The decoded document
     * @param string $kind "config" or "definition", naming the bundled schema
     * @param string $file The document file
     *
     * @throws InvalidInputException When the document breaks a schema, or declares a remote or unreadable one
     * @throws JsonException When a schema is not JSON
     */
    public function validate(mixed $data, string $kind, string $file): void
    {
        $schema = new JsonSchemaFile();
        $schema->validate($data, self::path($kind . '.schema.json'), $file);
        if ($data instanceof stdClass && isset($data->{'$schema'})) {
            $declared = $data->{'$schema'};
            if (!is_string($declared)) {
                throw new InvalidInputException("$file: \$schema must be a string.");
            }
            if ($declared === self::BASE . $kind . '.schema.json' || ($kind === 'definition' && DocumentReader::isMarkdown($file) && $declared === self::BASE . 'definition.document.yaml')) {
                return;
            }
            if (str_contains($declared, '://')) {
                throw new InvalidInputException("$file: unknown \$schema '$declared'; use the bundled schema URI or a local JSON Schema path.");
            }
            $schema->validate($data, str_starts_with($declared, '/') ? $declared : dirname($file) . '/' . $declared, $file);
        }
    }

    /**
     * Returns the path of a bundled schema.
     *
     * @param string $name The schema file name
     *
     * @return string The path within the package's schemas directory
     */
    public static function path(string $name): string
    {
        return dirname(__DIR__, 2) . '/schemas/' . $name;
    }
}
