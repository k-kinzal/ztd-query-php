<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Semantics;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * The type and reference information of one value occurrence.
 *
 * Construction supplies this information once, before publishing the statement.
 * It is not an index into an enclosing operation or an execution-time resolver.
 *
 * @visibility SqlSemantics
 */
trait ScalarSemantics
{
    private ?ScalarFact $valueInformation = null;

    /**
     * Completes this occurrence during semantic construction.
     */
    public function initializeScalarInformation(ScalarFact $information): void
    {
        if (isset($this->valueInformation)) {
            Check::input($this->valueInformation === $information, 'A scalar occurrence is resolved once.');
            return;
        }
        $this->valueInformation = $information;
    }

    /**
     * Returns the information owned by this occurrence.
     */
    public function scalarInformation(): ScalarFact
    {
        Check::input(isset($this->valueInformation), 'A scalar occurrence must be resolved before use.');
        return $this->valueInformation;
    }

    /**
     * Returns the value type before assignment conversion.
     */
    public function dataType(): TypeFact
    {
        return $this->scalarInformation()->type;
    }

    /**
     * Returns whether evaluating this occurrence can produce NULL.
     */
    public function nullability(): Nullability
    {
        return $this->scalarInformation()->nullability;
    }

    /**
     * Reports whether construction supplied this occurrence's information.
     */
    public function hasScalarInformation(): bool
    {
        return isset($this->valueInformation);
    }
}
