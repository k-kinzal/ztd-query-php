<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication;

use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One credential of a replication start request, such as USER = 'repl'; the value is kept as written and never used.
 * @visibility public
 * @example Reading a credential
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("START GROUP_REPLICATION USER = 'repl'");
 *     $statement->credentials[0]->value->text // => "'repl'"
 */
final class ReplicationCredential
{
    /**
     * The value is a quoted MySQL string literal.
     * @throws InvalidStructure
     */
    public function __construct(public readonly CredentialOption $option, public readonly Literal $value)
    {
        ReplicationText::check($value, 'A replication ' . $option->value . ' credential');
    }

    /**
     * Returns the decoded credential text.
     * @throws InvalidStructure
     */
    public function text(): string
    {
        return ReplicationText::check($this->value, 'A replication credential');
    }
}
