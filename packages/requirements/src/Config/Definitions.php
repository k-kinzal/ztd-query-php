<?php

declare(strict_types=1);

namespace Requirements\Config;

use Requirements\Model\Item;
use Requirements\Model\Source;

/**
 * The items and sources of every definition file of a project.
 */
final class Definitions
{
    /**
     * @param array<string, Item> $items Every item by ID
     * @param array<string, Source> $sources Every declared source by ID
     * @param list<string> $files The definition files in path order
     */
    public function __construct(public readonly array $items, public readonly array $sources, public readonly array $files)
    {
    }
}
