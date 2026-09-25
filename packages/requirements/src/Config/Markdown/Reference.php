<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown;

use Requirements\Input\InvalidInputException;
use Requirements\Model\Item;

/**
 * A Markdown link from one item to another, checked once every definition is loaded.
 */
final class Reference
{
    /**
     * @param string $id The linked item ID
     * @param string $url The link target
     * @param string $file The Markdown file holding the link
     */
    public function __construct(public readonly string $id, public readonly string $url, public readonly string $file)
    {
    }

    /**
     * Checks that the link points to the file and heading defining the item.
     *
     * @param array<string, Item> $items Every loaded item by ID
     *
     * @throws InvalidInputException When the link is not local, points to another file or has the wrong fragment
     */
    public function validate(array $items): void
    {
        $parts = parse_url($this->url);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['query'])) {
            throw new InvalidInputException("$this->file: reference '$this->id' must link to a local definition file.");
        }
        $path = rawurldecode($parts['path'] ?? '');
        $file = $path === '' ? $this->file : dirname($this->file) . '/' . $path;
        if (!isset($items[$this->id]) || realpath($file) !== realpath($items[$this->id]->file)) {
            throw new InvalidInputException("$this->file: link to '$this->id' does not point to its loaded definition file.");
        }
        if (isset($parts['fragment']) && strtolower(rawurldecode($parts['fragment'])) !== Nodes::anchor($this->id)) {
            throw new InvalidInputException("$this->file: link to '$this->id' has the wrong heading fragment.");
        }
    }
}
