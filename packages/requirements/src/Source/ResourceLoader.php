<?php

declare(strict_types=1);

namespace Requirements\Source;

use Requirements\Model\Source;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads source documents from HTTP(S) URLs or local paths, with pinned snapshots.
 *
 * Reads are limited to 16 MiB and remembered per URI, failures included. A missing local
 * snapshot is fetched from the source URI, verified against the digest and cached; a
 * document whose digest differs is rejected.
 *
 * @visibility public
 *
 * @example Reading a local document relative to the configuration directory
 *     $directory = sys_get_temp_dir() . '/requirements-example-' . bin2hex(random_bytes(4));
 *     mkdir($directory);
 *     file_put_contents($directory . '/notes.txt', 'Names start with a letter.');
 *     (new \Requirements\Source\ResourceLoader())->read(new \Requirements\Model\Source('notes', 'notes.txt', 'text', 'lines:1'), $directory, false) // => 'Names start with a letter.'
 * @example Rejecting a document whose digest differs
 *     $directory = sys_get_temp_dir() . '/requirements-example-' . bin2hex(random_bytes(4));
 *     mkdir($directory);
 *     file_put_contents($directory . '/notes.txt', 'Names start with a digit.');
 *     (new \Requirements\Source\ResourceLoader())->read(new \Requirements\Model\Source('notes', 'notes.txt', 'text', 'lines:1', sha256: hash('sha256', 'Names start with a letter.')), $directory, false) // throws \RuntimeException: SHA-256 mismatch
 */
final class ResourceLoader
{
    /**
     * @var array<string, string>
     */
    private array $cache = [];

    /**
     * @var array<string, RuntimeException>
     */
    private array $failures = [];

    /**
     * @param HttpClientInterface|null $client The client for HTTP(S) sources; a default client when null
     */
    public function __construct(private readonly ?HttpClientInterface $client = null)
    {
    }

    /**
     * Reads the document of a source.
     *
     * @param Source $source The source declaration
     * @param string $directory The configuration directory that relative paths resolve against
     * @param bool $live Whether to read the source URI instead of its pinned snapshot
     *
     * @return string The document
     *
     * @throws RuntimeException When the document cannot be read, exceeds the limit or its digest differs
     */
    public function read(Source $source, string $directory, bool $live): string
    {
        $snapshot = !$live && $source->snapshot !== null;
        $uri = (new ResourceLocation())->resolve($snapshot ? $source->snapshot : $source->uri, $directory);
        if ($snapshot && !str_contains($uri, '://') && !is_file($uri)) {
            (new SnapshotCache())->write($uri, $this->read(new Source($source->id, $source->uri, $source->format, $source->selector, sha256: $source->sha256), $directory, false));
        }
        $content = $this->fetch($uri);
        if ($source->sha256 !== null && (!$live || $source->snapshot === null) && !hash_equals($source->sha256, hash('sha256', $content))) {
            throw new RuntimeException("$source->id: source SHA-256 mismatch.");
        }
        return $content;
    }

    /**
     * Reads a resolved URI once and remembers the document or the failure.
     *
     * @param string $uri An HTTP(S) URL or an absolute local path
     *
     * @return string The document
     *
     * @throws RuntimeException When the resource cannot be read or exceeds 16 MiB
     */
    public function fetch(string $uri): string
    {
        if (isset($this->failures[$uri])) {
            throw $this->failures[$uri];
        }
        if (!isset($this->cache[$uri])) {
            try {
                $content = str_contains($uri, '://') ? (new Download($this->client))->get($uri) : (new LocalFile())->read($uri);
                $this->cache[$uri] = $content;
            } catch (RuntimeException|ExceptionInterface $error) {
                $this->failures[$uri] = new RuntimeException("Cannot read source $uri: " . $error->getMessage(), 0, $error);
                throw $this->failures[$uri];
            }
        }
        return $this->cache[$uri];
    }
}
