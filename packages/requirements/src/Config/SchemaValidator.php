<?php

declare(strict_types=1);

namespace Requirements\Config;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Requirements\Input\InvalidInputException;
use stdClass;

final class SchemaValidator
{
    public const BASE = 'https://raw.githubusercontent.com/k-kinzal/ztd-query-php/main/packages/requirements/schemas/';

    public function validate(mixed $data, string $kind, string $file): void
    {
        $schema = self::path($kind . '.schema.json');
        $this->against($data, $schema, $file);
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
            $this->against($data, str_starts_with($declared, '/') ? $declared : dirname($file) . '/' . $declared, $file);
        }
    }

    public static function path(string $name): string
    {
        return dirname(__DIR__, 2) . '/schemas/' . $name;
    }

    private function against(mixed $data, string $schema, string $file): void
    {
        $contents = is_file($schema) ? file_get_contents($schema) : false;
        if ($contents === false) {
            throw new InvalidInputException("$file: cannot read schema $schema");
        }
        $validator = new Validator();
        $document = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
        if (!is_object($document) && !is_bool($document)) {
            throw new InvalidInputException("$file: expected an object or Boolean schema in $schema");
        }
        $error = $validator->validate($data, $document)->error();
        if ($error !== null) {
            throw new InvalidInputException("$file: schema validation failed: " . json_encode((new ErrorFormatter())->format($error), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }
    }
}
