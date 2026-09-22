<?php

declare(strict_types=1);

namespace Requirements\Source;

use Requirements\Model\Source;
use RuntimeException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final class ResourceLoader
{
    /** @var array<string, string> */
    private array $cache = [];

    /** @var array<string, RuntimeException> */
    private array $failures = [];

    public function __construct(private readonly ?HttpClientInterface $client = null)
    {
    }

    public function read(Source $source, string $directory, bool $live): string
    {
        $snapshot = !$live && $source->snapshot !== null;
        $uri = $snapshot ? $source->snapshot : $source->uri;
        if (!str_contains($uri, '://') && !str_starts_with($uri, '/')) {
            $uri = $directory . '/' . $uri;
        }
        if (str_contains($uri, '://') && !str_starts_with($uri, 'https://') && !str_starts_with($uri, 'http://')) {
            throw new RuntimeException('Built-in sources accept HTTP(S) URLs or local paths only.');
        }
        $cachePath = $snapshot && !str_contains($uri, '://') ? $uri : null;
        if ($cachePath !== null && !is_file($cachePath)) {
            $content = $this->read(new Source($source->id, $source->uri, $source->format, $source->selector, sha256: $source->sha256), $directory, false);
            $parent = dirname($cachePath);
            if (!is_dir($parent) && !@mkdir($parent, 0755, true) && !is_dir($parent)) {
                throw new RuntimeException("Cannot create source cache directory: $parent");
            }
            $temporary = tempnam($parent, '.source-');
            if ($temporary === false) {
                throw new RuntimeException("Cannot create source cache: $cachePath");
            }
            try {
                if (file_put_contents($temporary, $content) === false || !rename($temporary, $cachePath)) {
                    throw new RuntimeException("Cannot populate source cache: $cachePath");
                }
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
        }
        if (isset($this->failures[$uri])) {
            throw $this->failures[$uri];
        }
        if (!isset($this->cache[$uri])) {
            try {
                $content = str_contains($uri, '://') ? $this->download($uri) : @file_get_contents($uri, false, null, 0, 16777217);
                if ($content === false || strlen($content) > 16777216) {
                    throw new RuntimeException('Unreadable resource or 16 MiB size limit exceeded.');
                }
                $this->cache[$uri] = $content;
            } catch (Throwable $error) {
                $this->failures[$uri] = new RuntimeException("Cannot read source $uri: " . $error->getMessage(), 0, $error);
                throw $this->failures[$uri];
            }
        }
        $content = $this->cache[$uri];
        if ($source->sha256 !== null && (!$live || $source->snapshot === null) && !hash_equals($source->sha256, hash('sha256', $content))) {
            throw new RuntimeException("$source->id: source SHA-256 mismatch.");
        }
        return $content;
    }

    private function download(string $uri): string
    {
        $client = $this->client ?? HttpClient::create();
        $response = $client->request('GET', $uri, ['timeout' => 20, 'max_duration' => 20, 'max_redirects' => 5, 'buffer' => false, 'headers' => ['User-Agent' => 'requirements/1']]);
        try {
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw new RuntimeException("HTTP $status");
            }
            $content = '';
            foreach ($client->stream($response) as $chunk) {
                $content .= $chunk->getContent();
                if (strlen($content) > 16777216) {
                    throw new RuntimeException('16 MiB size limit exceeded.');
                }
            }
            return $content;
        } finally {
            $response->cancel();
        }
    }

}
