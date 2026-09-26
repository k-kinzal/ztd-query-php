<?php

declare(strict_types=1);

namespace Requirements\Model;

use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;

/**
 * A source document a definition quotes, and the scope of units that coverage counts.
 *
 * The URI is an HTTP(S) URL or a path relative to the configuration; a pinned snapshot is a
 * local copy verified by its SHA-256 digest. The format names the source extension and the
 * selector declares the coverage scope in that extension's syntax.
 *
 * @visibility public
 *
 * @example Declaring an HTML scope
 *     $source = new \Requirements\Model\Source('manual', 'https://example.org/manual.html', 'html', 'main p');
 *     [$source->id, $source->format, $source->selector, $source->snapshot] // => ['manual', 'html', 'main p', null]
 * @example Reading the source of a definition
 *     $source = \Requirements\Model\Source::from(['id' => 'notes', 'uri' => 'notes.txt', 'format' => 'text', 'selector' => 'lines:1-3', 'options' => ['encoding' => 'utf-8']]);
 *     [$source->uri, $source->options] // => ['notes.txt', ['encoding' => 'utf-8']]
 * @example Rejecting a snapshot without its digest
 *     \Requirements\Model\Source::from(['id' => 'manual', 'uri' => 'https://example.org/', 'format' => 'html', 'selector' => 'p', 'snapshot' => 'cache/manual.html']) // throws \Requirements\Input\InvalidInputException: SHA-256
 */
final class Source
{
    /**
     * @param string $id The source identifier used by coverage thresholds and reports
     * @param string $uri The HTTP(S) URL or local path of the document
     * @param string $format The source extension that reads the document
     * @param string $selector The coverage scope in the extension's selector syntax
     * @param string|null $snapshot The local copy read instead of the URI unless live
     * @param string|null $sha256 The lowercase SHA-256 digest of the document
     * @param array<string, mixed> $options Extension-specific options
     */
    public function __construct(
        public readonly string $id,
        public readonly string $uri,
        public readonly string $format,
        public readonly string $selector,
        public readonly ?string $snapshot = null,
        public readonly ?string $sha256 = null,
        public readonly array $options = [],
    ) {
    }

    /**
     * Reads the source mapping of a definition.
     *
     * @param mixed $value The decoded source mapping
     *
     * @return self The source
     *
     * @throws InvalidInputException When fields are unknown or missing, or a snapshot lacks a valid digest
     */
    public static function from(mixed $value): self
    {
        $data = Fields::mapping($value, 'source');
        Fields::keys($data, ['id', 'uri', 'format', 'selector', 'snapshot', 'sha256', 'options'], 'source');
        $snapshot = isset($data['snapshot']) ? Fields::text($data, 'snapshot') : null;
        $hash = isset($data['sha256']) ? Fields::text($data, 'sha256') : null;
        if (($snapshot !== null && $hash === null) || ($hash !== null && preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1)) {
            throw new InvalidInputException('A snapshot requires a lowercase SHA-256 digest.');
        }
        return new self(Fields::text($data, 'id'), Fields::text($data, 'uri'), Fields::text($data, 'format'), Fields::text($data, 'selector'), $snapshot, $hash, Fields::mapping($data['options'] ?? [], 'source.options'));
    }
}
