<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Semantics;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\QueryFact;

/**
 * The projection, aggregates and conditions belonging to one query body.
 *
 * @visibility SqlSemantics
 */
trait QuerySemantics
{
    private ?QueryFact $outputInformation = null;

    /**
     * Completes this query during semantic construction.
     */
    public function initializeQueryInformation(QueryFact $information): void
    {
        if (isset($this->outputInformation)) {
            Check::input($this->outputInformation === $information, 'A query occurrence is resolved once.');
            return;
        }
        $this->outputInformation = $information;
    }

    /**
     * Returns the output owned by this query.
     */
    public function queryInformation(): QueryFact
    {
        Check::input(isset($this->outputInformation), 'A query occurrence must be resolved before use.');
        return $this->outputInformation;
    }

    /**
     * Reports whether construction supplied this occurrence's information.
     */
    public function hasQueryInformation(): bool
    {
        return isset($this->outputInformation);
    }
}
