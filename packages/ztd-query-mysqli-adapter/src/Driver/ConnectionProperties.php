<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli\Driver;

/**
 * What a mysqli connection answers about itself.
 *
 * A ZTD connection extends mysqli without ever connecting the parent, so its
 * own properties hold nothing and reading one raises rather than answers. Every
 * read goes through here instead, against whichever connection is the real one.
 */
interface ConnectionProperties
{
    /**
     * Answers the connection's property under that name.
     *
     * @param string $name Property as it was written
     *
     * @return mixed What the connection has under that name, or null where mysqli has no such property
     */
    public function named(string $name): mixed;
}
