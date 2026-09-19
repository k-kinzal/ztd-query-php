<?php

declare(strict_types=1);

namespace SqlParser\Resource;

use RuntimeException;

/**
 * Downloads an upstream grammar or keyword source, keeping a copy for the next build.
 *
 * @visibility root
 */
final class SourceFetcher
{
    /**
     * @param string|null $cacheDirectory Where downloaded files are kept, or null to keep none
     */
    public function __construct(private readonly ?string $cacheDirectory = null)
    {
    }

    /**
     * Answers the contents of a URL, from the cache when it was fetched before.
     *
     * @param string $url Where the source is published
     *
     * @return string The contents
     *
     * @throws RuntimeException When the source cannot be fetched
     */
    public function fetch(string $url): string
    {
        $cached = $this->cachePath($url);
        if ($cached !== null && is_file($cached)) {
            $contents = file_get_contents($cached);
            if ($contents !== false) {
                return $contents;
            }
        }
        $contents = $this->download($url);
        if ($contents === null || $contents === '') {
            throw new RuntimeException("Failed to fetch {$url}");
        }
        if ($cached !== null) {
            if (!is_dir(dirname($cached))) {
                mkdir(dirname($cached), 0777, true);
            }
            file_put_contents($cached, $contents);
        }

        return $contents;
    }

    /**
     * Downloads a URL, quietly answering null when it cannot be reached.
     *
     * @param string $url Where the source is published
     *
     * @return string|null The contents, or null when the download fails
     */
    public function download(string $url): ?string
    {
        $context = stream_context_create(['http' => ['timeout' => 120, 'user_agent' => 'sql-parser/1.0']]);
        set_error_handler(static fn (): bool => true);
        try {
            $contents = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }

        return $contents === false ? null : $contents;
    }

    /**
     * Answers where the copy of a URL is kept.
     *
     * @param string $url Where the source is published
     *
     * @return string|null The cache path, or null when nothing is kept
     */
    public function cachePath(string $url): ?string
    {
        if ($this->cacheDirectory === null) {
            return null;
        }

        return $this->cacheDirectory . '/' . preg_replace('/[^A-Za-z0-9._-]+/', '_', substr($url, strlen('https://')));
    }
}
