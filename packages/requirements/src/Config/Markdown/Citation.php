<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown;

use Requirements\Input\InvalidInputException;
use Requirements\Model\Source;
use Requirements\Source\TextFragment;
use Requirements\Source\Unit;

final class Citation
{
    public function __construct(private readonly Source $source, private readonly string $file, private readonly string $directory)
    {
    }

    public function selector(string $url, string $quote, ?string $selector): string
    {
        $resource = explode('#', $url, 2)[0];
        $source = explode('#', $this->source->uri, 2)[0];
        $matches = preg_match('~^[a-z][a-z0-9+.-]*:~i', $source) === 1
            ? $resource === $source
            : $this->path(dirname($this->file) . '/' . rawurldecode($resource)) === $this->path($this->directory . '/' . $source);
        if ($resource === '' || !$matches) {
            throw new InvalidInputException('An evidence citation must link to this definition\'s source resource.');
        }
        $fragment = '#' . (explode('#', $url, 2)[1] ?? '');
        if (TextFragment::isFragment($fragment)) {
            if ($this->source->format !== 'html' || TextFragment::text($fragment) !== Unit::normalize($quote)) {
                throw new InvalidInputException('An exact Text Fragment citation must identify the complete quoted HTML unit.');
            }
            return $selector ?? $fragment;
        }
        if ($selector !== null) {
            if (self::isId($selector) && $fragment !== '#' && rawurldecode($fragment) !== $selector) {
                throw new InvalidInputException('The citation anchor disagrees with the evidence selector.');
            }
            return $selector;
        }
        if (in_array($this->source->format, ['html', 'xml', 'ietf', 'markdown'], true) && self::isId(rawurldecode($fragment))) {
            return rawurldecode($fragment);
        }
        throw new InvalidInputException('Supply a selector comment, an element-ID citation, or an exact Text Fragment citation.');
    }

    public function validate(string $selector, string $quote): void
    {
        if (TextFragment::isFragment($selector) && ($this->source->format !== 'html' || TextFragment::text($selector) !== Unit::normalize($quote))) {
            throw new InvalidInputException('An exact Text Fragment selector must identify the complete quoted HTML unit.');
        }
    }

    public function url(string $selector, string $quote): string
    {
        $uri = explode('#', $this->source->uri, 2)[0];
        if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $uri) !== 1) {
            $source = explode('/', $this->path($this->directory . '/' . $uri));
            $document = explode('/', $this->path(dirname($this->file)));
            while ($source !== [] && $document !== [] && $source[0] === $document[0]) {
                array_shift($source);
                array_shift($document);
            }
            $uri = str_repeat('../', count($document)) . implode('/', array_map(rawurlencode(...), $source));
        }
        if ($this->source->format === 'html' && TextFragment::isFragment($selector)) {
            return $uri . $selector;
        }
        if (in_array($this->source->format, ['html', 'xml', 'ietf', 'markdown'], true) && self::isId($selector)) {
            return $uri . '#' . rawurlencode(substr($selector, 1));
        }
        return $uri . ($this->source->format === 'html' ? TextFragment::create($quote) : '');
    }

    public static function isId(string $selector): bool
    {
        return preg_match('/\A#[A-Za-z_][A-Za-z0-9_-]*\z/', $selector) === 1;
    }

    private function path(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '' && $part !== '.') {
                $parts[] = $part;
            }
        }
        return implode('/', $parts);
    }
}
