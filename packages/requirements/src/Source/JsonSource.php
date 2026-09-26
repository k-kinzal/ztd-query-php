<?php

declare(strict_types=1);

namespace Requirements\Source;

use Requirements\Model\Source;
use RuntimeException;
use stdClass;

final class JsonSource implements SourceExtension
{
    public function __construct(private readonly ResourceLoader $loader = new ResourceLoader())
    {
    }

    public function select(Source $source, string $selector, string $directory, bool $live): array
    {
        $value = json_decode($this->loader->read($source, $directory, $live), false, 512, JSON_THROW_ON_ERROR);
        if (!str_starts_with($selector, '$')) {
            throw new RuntimeException('JSONPath must start with $.');
        }
        $nodes = [['path' => '', 'value' => $value]];
        $remaining = substr($selector, 1);
        while ($remaining !== '') {
            if (preg_match('/^(\.\.?)([A-Za-z_][A-Za-z0-9_-]*|\*)|^\[(\*|[0-9]+|\x27[^\x27]+\x27|"[^"]+")\]/', $remaining, $match) !== 1) {
                throw new RuntimeException('Supported JSONPath: child names, quoted keys, array indices, wildcards and recursive names.');
            }
            $recursive = $match[1] === '..';
            $key = $match[2] !== '' ? $match[2] : trim($match[3], "\"'");
            $next = [];
            foreach ($nodes as $node) {
                array_push($next, ...$this->children($node['value'], $node['path'], $key, $recursive));
            }
            $nodes = $next;
            $remaining = substr($remaining, strlen($match[0]));
        }
        $units = [];
        foreach ($nodes as $node) {
            $text = is_string($node['value']) ? $node['value'] : json_encode($node['value'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $units[$node['path']] = new Unit('json:' . $node['path'], Unit::normalize($text));
        }
        return array_values($units);
    }

    /** @return list<array{path: string, value: mixed}> */
    private function children(mixed $value, string $path, string $key, bool $recursive): array
    {
        if ($value instanceof stdClass) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $name => $child) {
            $childPath = $path . '/' . str_replace(['~', '/'], ['~0', '~1'], (string) $name);
            if ($key === '*' || (string) $name === $key) {
                $result[] = ['path' => $childPath, 'value' => $child];
            }
            if ($recursive) {
                array_push($result, ...$this->children($child, $childPath, $key, true));
            }
        }
        return $result;
    }
}
