<?php

declare(strict_types=1);

namespace Requirements\Source;

use RuntimeException;

/**
 * Resolves where a source document is read from.
 */
final class ResourceLocation
{
    /**
     * Resolves a URI or path against the configuration directory.
     *
     * @param string $uri An HTTP(S) URL, an absolute path or a path relative to the directory
     * @param string $directory The configuration directory
     *
     * @return string The URL or the absolute path
     *
     * @throws RuntimeException When the URI uses a scheme other than HTTP(S)
     */
    public function resolve(string $uri, string $directory): string
    {
        if (!str_contains($uri, '://') && !str_starts_with($uri, '/')) {
            $uri = $directory . '/' . $uri;
        }
        if (str_contains($uri, '://') && !str_starts_with($uri, 'https://') && !str_starts_with($uri, 'http://')) {
            throw new RuntimeException('Built-in sources accept HTTP(S) URLs or local paths only.');
        }
        return $uri;
    }
}
