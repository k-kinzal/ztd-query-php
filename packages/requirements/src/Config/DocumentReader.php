<?php

declare(strict_types=1);

namespace Requirements\Config;

use InvalidArgumentException;
use stdClass;
use Symfony\Component\Yaml\Yaml;

final class DocumentReader
{
    public function __construct(public readonly MarkdownDocument $markdown = new MarkdownDocument())
    {
    }

    /** @param array<string, mixed> $markdown */
    public function read(string $path, string $kind, array $markdown = [], ?string $directory = null): stdClass
    {
        $data = self::isMarkdown($path)
            ? $this->markdown->read($path, $markdown, $directory)
            : Yaml::parseFile($path, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_OBJECT_FOR_MAP);
        (new SchemaValidator())->validate($data, $kind, $path);
        if (!$data instanceof stdClass) {
            throw new InvalidArgumentException("$path: expected a document mapping.");
        }
        return $data;
    }

    public static function isMarkdown(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['md', 'markdown'], true);
    }

    public static function yaml(mixed $data): string
    {
        return Yaml::dump($data, 12, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
    }
}
