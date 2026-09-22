<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Column;

/**
 * Describes the source of a column value without evaluating it.
 *
 * @visibility public
 */
interface Generation
{
    /**
     * @return list<\SqlSemantics\Model\Expression>
     */
    public function expressions(): array;
}
