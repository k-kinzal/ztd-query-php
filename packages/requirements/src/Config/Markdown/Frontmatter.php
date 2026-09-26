<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown;

use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use stdClass;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Splits a Markdown definition into its YAML frontmatter and its card body.
 *
 * The frontmatter is delimited by --- lines and may hold only $schema, version and source.
 */
final class Frontmatter
{
    /**
     * Reads a Markdown definition file.
     *
     * @param string $file The definition file
     *
     * @return array{stdClass, string} The frontmatter and the body after it
     *
     * @throws InvalidInputException When the file has no frontmatter, it is not a mapping or has unknown keys
     * @throws ParseException When the frontmatter is malformed YAML
     */
    public function read(string $file): array
    {
        $text = file_get_contents($file);
        if ($text === false || preg_match('/\A---\r?\n(.*?)\r?\n---\r?\n(.*)\z/s', $text, $parts) !== 1) {
            throw new InvalidInputException("$file: expected YAML frontmatter delimited by ---.");
        }
        $data = Yaml::parse($parts[1], Yaml::PARSE_OBJECT_FOR_MAP | Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        if (!$data instanceof stdClass) {
            throw new InvalidInputException("$file: frontmatter must be a mapping.");
        }
        Fields::keys(Fields::mapping(get_object_vars($data), 'frontmatter'), ['$schema', 'version', 'source'], "$file frontmatter");
        return [$data, $parts[2]];
    }
}
