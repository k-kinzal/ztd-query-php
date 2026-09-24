<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Identification;

use SqlSemantics\Model\Scalar\Value\Literal;

/**
 * MySQL 5.6 and 5.7 IDENTIFIED BY PASSWORD 'hash': an already encoded credential.
 * @visibility public
 * @example Reading the encoded credential spelling
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("CREATE USER u IDENTIFIED BY PASSWORD '*hash'");
 *     $statement->accounts[0]->identification->hash->text // => "'*hash'"
 */
final class HashIdentification
{
    /**
     * Keeps the encoded value as a literal; it is never decoded.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly Literal $hash)
    {
        IdentificationOperands::secret($hash);
    }
}
