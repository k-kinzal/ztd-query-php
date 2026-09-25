<?php

declare(strict_types=1);

namespace Requirements\Markdown;

use InvalidArgumentException;
use Requirements\Model\Item;

final class Reference
{
    public function __construct(public readonly string $id, public readonly string $url, public readonly string $file)
    {
    }

    /** @param array<string, Item> $items */
    public function validate(array $items): void
    {
        $parts = parse_url($this->url);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['query'])) {
            throw new InvalidArgumentException("$this->file: reference '$this->id' must link to a local definition file.");
        }
        $path = rawurldecode($parts['path'] ?? '');
        $file = $path === '' ? $this->file : dirname($this->file) . '/' . $path;
        if (!isset($items[$this->id]) || realpath($file) !== realpath($items[$this->id]->file)) {
            throw new InvalidArgumentException("$this->file: link to '$this->id' does not point to its loaded definition file.");
        }
        if (isset($parts['fragment']) && strtolower(rawurldecode($parts['fragment'])) !== Nodes::anchor($this->id)) {
            throw new InvalidArgumentException("$this->file: link to '$this->id' has the wrong heading fragment.");
        }
    }
}
