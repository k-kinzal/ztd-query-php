<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

use Override;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A SQLite declared type name and its optional numeric size parameters.
 * @visibility public
  * @example Inspecting SqliteDeclaration
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE users (code VARCHAR(20))')->tables[0];
 *     $table->columns[0]->type->identity instanceof \SqlSemantics\Type\Identity\SqliteDeclaration // => true
 */
final class SqliteDeclaration implements TypeIdentity
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly string $declaredName,
        public readonly StorageAffinity $affinity,
        public readonly ?Numeric\NumericParameter $size = null,
        public readonly ?Numeric\NumericParameter $scale = null,
    ) {
        if ($scale !== null && $size === null) {
            throw new InvalidStructure('A second SQLite type parameter requires the first parameter.');
        }
    }

    /**
     * Returns the canonical database type name represented by this identity.
     */
    #[Override]
    public function name(): string
    {
        return $this->declaredName;
    }
}
