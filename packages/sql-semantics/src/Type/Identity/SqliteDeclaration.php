<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

use Override;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A SQLite declared type name and its optional numeric size parameters.
 * @visibility public
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

    #[Override]
    public function name(): string
    {
        return $this->declaredName;
    }
}
