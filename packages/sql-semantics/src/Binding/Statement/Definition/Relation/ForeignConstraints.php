<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Relation;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Diagnoses constraints that a foreign table cannot declare: keys, foreign keys, and exclusions.
 * @visibility SqlSemantics
 */
final class ForeignConstraints
{
    /**
     * Keywords that open a constraint the server rejects on foreign tables.
     */
    public const REJECTED = ['UNIQUE', 'PRIMARY', 'FOREIGN', 'REFERENCES', 'EXCLUDE'];

    /**
     * Throws when any table or column constraint of the statement is a key, a foreign key, or an exclusion.
     * @throws InvalidSql
     */
    public static function check(Node $source): void
    {
        foreach (Tree::outer($source, ['ConstraintElem', 'ColConstraintElem']) as $constraint) {
            if (in_array(strtoupper($constraint->tokens()[0]->text), self::REJECTED, true)) {
                throw new InvalidSql(InputViolation::ForeignTableConstraint, $constraint);
            }
        }
    }
}
