<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Policy;

/**
 * Dialect-specific insertion behavior beyond the required row source.
 *
 * @visibility public
 */
interface InsertPolicy
{
    /**
     * Identifies the language in which this policy is meaningful.
     */
    public function dialect(): \SqlSemantics\Dialect;
}
