<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

/**
 * The classified type identity carried by a semantic expression or column.
 * @visibility public
 */
interface TypeIdentity
{
    /**
     * Returns the canonical type name used in diagnostics.
     */
    public function name(): string;
}
