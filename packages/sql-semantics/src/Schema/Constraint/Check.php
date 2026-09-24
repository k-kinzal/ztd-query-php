<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Constraint;

use Override;

/**
 * A mandatory predicate evaluated for each affected row.
 *
 * @visibility public
 * @example Reading a named check constraint
 *     $constraint = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, CONSTRAINT positive CHECK (a > 0) NO INHERIT)')->tables[0]->constraints[0];
 *     $constraint instanceof \SqlSemantics\Schema\Constraint\Check // => true
 *     $constraint->name // => 'positive'
 *     $constraint->enforced // => true
 *     $constraint->predicate->structure()->toString() // => '("a" > 0)'
 */
final class Check extends \SqlSemantics\Schema\TableConstraint
{
    /**
     * Constructs a valid declaration.

     */
    public function __construct(
        public readonly \SqlSemantics\Model\Expression $predicate,
        public readonly bool $enforced = true,
        public readonly bool $noInherit = false,
        ?string $name = null,
        \SqlParser\Parser\Node $source = new \SqlParser\Parser\Node('constraint', 0, []),
    ) {
        parent::__construct($name, $source);
    }

    #[Override]
    protected function operation(): \SqlSemantics\Schema\ConstraintKind
    {
        return \SqlSemantics\Schema\ConstraintKind::Check;
    }

    /**
     * Returns the local column names constrained by this declaration.
     * @return list<string>
     */
    #[Override]
    public function localColumns(): array
    {
        return [];
    }
}
