<?php

declare(strict_types=1);

namespace Requirements\Config;

use JsonException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Requirements\Input\InvalidInputException;

/**
 * Validates a document against a JSON Schema read from a file.
 */
final class JsonSchemaFile
{
    /**
     * Validates a document.
     *
     * @param mixed $data The decoded document
     * @param string $schema The schema file
     * @param string $file The document file, used in error messages
     *
     * @throws InvalidInputException When the schema cannot be read or is neither an object nor a Boolean, or the document breaks it
     * @throws JsonException When the schema is not JSON
     */
    public function validate(mixed $data, string $schema, string $file): void
    {
        $contents = is_file($schema) ? file_get_contents($schema) : false;
        if ($contents === false) {
            throw new InvalidInputException("$file: cannot read schema $schema");
        }
        $document = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
        if (!is_object($document) && !is_bool($document)) {
            throw new InvalidInputException("$file: expected an object or Boolean schema in $schema");
        }
        $error = (new Validator())->validate($data, $document)->error();
        if ($error !== null) {
            throw new InvalidInputException("$file: schema validation failed: " . json_encode((new ErrorFormatter())->format($error), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }
    }
}
