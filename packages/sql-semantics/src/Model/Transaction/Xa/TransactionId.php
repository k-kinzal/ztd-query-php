<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction\Xa;

use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * An XA global transaction identifier with an optional explicit branch.
 * @visibility public
 * @example Inspecting the global identifier
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("XA START X'6162'");
 *     $statement->transactionId->global->text // => "X'6162'"
 */
final class TransactionId
{
    /**
     * An omitted branch requests the empty qualifier and format identifier 1.
     * @throws InvalidStructure
     */
    public function __construct(public readonly Literal $global, public readonly ?BranchIdentifier $branch = null)
    {
        IdentifierBytes::check($global);
    }
}
