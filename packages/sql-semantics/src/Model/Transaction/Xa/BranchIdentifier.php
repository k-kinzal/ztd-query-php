<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction\Xa;

use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * An explicit XA branch qualifier and its optional format identifier.
 * @visibility public
 * @example Inspecting a branch
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("XA PREPARE 'global', 'branch', 42");
 *     $statement->transactionId->branch->format->spelling // => '42'
 */
final class BranchIdentifier
{
    /**
     * A format identifier cannot exist without its required branch qualifier.
     * @throws InvalidStructure
     */
    public function __construct(public readonly Literal $qualifier, public readonly ?FormatIdentifier $format = null)
    {
        IdentifierBytes::check($qualifier);
    }
}
