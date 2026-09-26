<?php

declare(strict_types=1);

namespace Requirements\Config;

use JsonException;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use stdClass;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads configuration and definition documents written in YAML or Markdown.
 *
 * A file ending in .md or .markdown is read as Markdown cards; any other file as YAML. Every
 * document is validated against its bundled schema and any local schema it declares.
 */
final class DocumentReader
{
    /**
     * @param MarkdownDocument $markdown Reads Markdown definitions and remembers how they were written
     */
    public function __construct(public readonly MarkdownDocument $markdown = new MarkdownDocument())
    {
    }

    /**
     * Reads and validates a document.
     *
     * @param string $path The document file
     * @param string $kind "config" or "definition", naming the schema
     * @param array<string, mixed> $markdown The markdown options
     * @param string|null $directory The configuration directory; the file's directory when null
     *
     * @return stdClass The document
     *
     * @throws InvalidInputException When the document breaks its schema or is not a mapping
     * @throws JsonException When a schema cannot be decoded
     * @throws ParseException When a YAML document is malformed
     */
    public function read(string $path, string $kind, array $markdown = [], ?string $directory = null): stdClass
    {
        $data = self::isMarkdown($path)
            ? $this->markdown->read($path, $markdown, $directory)
            : Yaml::parseFile($path, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_OBJECT_FOR_MAP);
        (new SchemaValidator())->validate($data, $kind, $path);
        if (!$data instanceof stdClass) {
            throw new InvalidInputException("$path: expected a document mapping.");
        }
        return $data;
    }

    /**
     * Tells whether a file is read as Markdown.
     *
     * @param string $path The file
     *
     * @return bool True for the .md and .markdown extensions, in any case
     */
    public static function isMarkdown(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['md', 'markdown'], true);
    }

    /**
     * Converts a read document to a mapping.
     *
     * @param stdClass $document The document
     * @param string $file The document file, used in the error message
     *
     * @return array<string, mixed> The document as nested arrays
     *
     * @throws InvalidInputException When the document is not a mapping
     * @throws JsonException When the document cannot be converted
     */
    public static function mapping(stdClass $document, string $file): array
    {
        return Fields::mapping(json_decode(json_encode($document, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR), $file);
    }

    /**
     * Writes data as a formatted YAML document.
     *
     * @param mixed $data The document
     *
     * @return string The YAML text
     */
    public static function yaml(mixed $data): string
    {
        return Yaml::dump($data, 12, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
    }
}
