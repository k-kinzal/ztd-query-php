<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Identification;

use SqlSemantics\Model\Scalar\Value\Literal;

/**
 * IDENTIFIED BY 'password': a cleartext credential the server hashes with the default plugin.
 * @visibility public
 * @example Reading the supplied credential spelling
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE USER 'u'@'h' IDENTIFIED BY 'secret'");
 *     $statement->accounts[0]->identification->password->text // => "'secret'"
 */
final class PasswordIdentification
{
    /**
     * Keeps the credential as a literal; it is never interpreted.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly Literal $password)
    {
        IdentificationOperands::secret($password);
    }
}
