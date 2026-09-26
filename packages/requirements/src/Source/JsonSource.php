<?php

declare(strict_types=1);

namespace Requirements\Source;

use JsonException;
use Override;
use Requirements\Model\Source;
use RuntimeException;

/**
 * Selects values of a JSON source with a subset of JSONPath.
 *
 * A string value is its own text; any other value is its JSON encoding. Units are located by
 * their JSON Pointer.
 */
final class JsonSource implements SourceExtension
{
    /**
     * @param ResourceLoader $loader Reads the source document
     */
    public function __construct(private readonly ResourceLoader $loader = new ResourceLoader())
    {
    }

    /**
     * Selects the values a JSONPath matches.
     *
     * @param Source $source The source declaration
     * @param string $selector The JSONPath
     * @param string $directory The configuration directory
     * @param bool $live Whether to read the current URI instead of a pinned snapshot
     *
     * @return list<Unit> One unit per matched value, located as json:POINTER
     *
     * @throws JsonException When the source is not JSON
     * @throws RuntimeException When the path is unsupported or the source cannot be read
     */
    #[Override]
    public function select(Source $source, string $selector, string $directory, bool $live): array
    {
        $value = json_decode($this->loader->read($source, $directory, $live), false, 512, JSON_THROW_ON_ERROR);
        $units = [];
        foreach ((new JsonPath())->select($value, $selector) as $node) {
            $text = is_string($node['value']) ? $node['value'] : json_encode($node['value'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $units[$node['path']] = new Unit('json:' . $node['path'], Unit::normalize($text));
        }
        return array_values($units);
    }
}
