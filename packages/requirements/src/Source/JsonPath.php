<?php

declare(strict_types=1);

namespace Requirements\Source;

use RuntimeException;
use stdClass;

/**
 * Evaluates the supported JSONPath subset: child names, quoted keys, array indices, wildcards
 * and recursive names.
 */
final class JsonPath
{
    /**
     * Selects the values a path matches.
     *
     * @param mixed $value The decoded document
     * @param string $path The JSONPath, starting with $
     *
     * @return list<array{path: string, value: mixed}> The matched values with their JSON Pointers
     *
     * @throws RuntimeException When the path does not start with $ or uses unsupported syntax
     */
    public function select(mixed $value, string $path): array
    {
        if (!str_starts_with($path, '$')) {
            throw new RuntimeException('JSONPath must start with $.');
        }
        $nodes = [['path' => '', 'value' => $value]];
        $remaining = substr($path, 1);
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
        return $nodes;
    }

    /**
     * Selects the children of a value named by one path step.
     *
     * @param mixed $value The value
     * @param string $path The JSON Pointer of the value
     * @param string $key The child name, index or * for every child
     * @param bool $recursive Whether descendants are searched as well
     *
     * @return list<array{path: string, value: mixed}> The matched children with their JSON Pointers
     */
    public function children(mixed $value, string $path, string $key, bool $recursive): array
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
