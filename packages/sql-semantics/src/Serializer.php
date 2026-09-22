<?php

declare(strict_types=1);

namespace SqlSemantics;

use SqlSemantics\Model\BoundStatement;

/**
 * Writes a statement's structure as SQL in a chosen layout.
 *
 * @example Working with SQL structure
 *     $serializer = new \SqlSemantics\SimpleSerializer();
 *     $serializer instanceof \SqlSemantics\Serializer // => true
 *
 * @visibility public
 */
interface Serializer
{
    /**
     * Preserves SQL meaning while choosing whitespace and layout.
     */
    public function serialize(BoundStatement $statement): string;
}
