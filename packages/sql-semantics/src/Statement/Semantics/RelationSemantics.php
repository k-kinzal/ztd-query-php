<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Semantics;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\RelationFact;

/**
 * The row shape and table binding of a particular relation occurrence.
 *
 * @visibility SqlSemantics
 */
trait RelationSemantics
{
    private ?RelationFact $inputInformation = null;

    /**
     * Completes this occurrence during semantic construction.
     */
    public function initializeRelationInformation(RelationFact $information): void
    {
        if (isset($this->inputInformation)) {
            Check::input($this->inputInformation === $information, 'A relation occurrence is resolved once.');
            return;
        }
        $this->inputInformation = $information;
    }

    /**
     * Returns the shape and binding owned by this occurrence.
     */
    public function relationInformation(): RelationFact
    {
        Check::input(isset($this->inputInformation), 'A relation occurrence must be resolved before use.');
        return $this->inputInformation;
    }

    /**
     * Reports whether construction supplied this occurrence's information.
     */
    public function hasRelationInformation(): bool
    {
        return isset($this->inputInformation);
    }
}
