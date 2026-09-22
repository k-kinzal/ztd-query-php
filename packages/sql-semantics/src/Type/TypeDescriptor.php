<?php

declare(strict_types=1);

namespace SqlSemantics\Type;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Identity\TypeIdentity;
use ValueError;

/**
 * A classified database type; each identity owns its applicable parameters.
 *
 * @example A numeric declaration
 *     $identity = new \SqlSemantics\Type\Identity\Numeric\NumericStorage(\SqlSemantics\Type\Identity\BuiltinIdentity::Numeric, new \SqlSemantics\Type\Identity\Numeric\NumericParameter('10'), new \SqlSemantics\Type\Identity\Numeric\NumericParameter('2'));
 *     $type = new \SqlSemantics\Type\TypeDescriptor(\SqlSemantics\Dialect::PostgreSql, $identity);
 *     $type->identity->scale->spelling // => '2'
 *
 * @visibility public
 */
final class TypeDescriptor
{
    /**
     * Canonical name for diagnostics and type-family comparisons.
     */
    public readonly string $name;

    /**
     * SQLite column affinity, distinct from a runtime storage class.
     */
    public readonly ?Identity\StorageAffinity $affinity;

    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Dialect $dialect, public readonly TypeIdentity $identity)
    {
        if ($identity instanceof Identity\SqliteDeclaration && $dialect !== Dialect::Sqlite) {
            throw new InvalidStructure('A SQLite declared type requires the SQLite dialect.');
        }
        if (($identity instanceof Identity\NamedIdentity || $identity instanceof Identity\ArrayStorage || $identity instanceof Identity\IntervalStorage) && $dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('This type identity requires the PostgreSQL dialect.');
        }
        if (($identity instanceof Identity\Enumeration || $identity instanceof Identity\LabelSet) && $dialect !== Dialect::MySql) {
            throw new InvalidStructure('Label types require the MySQL dialect.');
        }
        $this->name = $identity->name();
        $this->affinity = $identity instanceof Identity\SqliteDeclaration ? $identity->affinity : null;
    }

    /**
     * Resolves an internally selected built-in family without accepting arbitrary type syntax.
     * @throws ValueError
     * @visibility SqlSemantics
     */
    public static function builtin(Dialect $dialect, string $name): self
    {
        return new self($dialect, Identity\BuiltinIdentity::from($name));
    }
}
