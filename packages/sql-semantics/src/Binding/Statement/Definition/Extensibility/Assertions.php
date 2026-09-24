<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Extensibility;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Definition\Assertion\CreateAssertionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Schema\Constraint\CheckingTime;

/**
 * Binds CREATE ASSERTION with its condition and checking time.
 * @visibility SqlSemantics
 */
final class Assertions
{
    /**
     * CREATE ASSERTION name CHECK (condition) [[NOT] DEFERRABLE] [INITIALLY {IMMEDIATE | DEFERRED}].
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function create(Origin $origin, Node $source, QueryContext $context): CreateAssertionStatement
    {
        $name = ObjectAddresses::name(Tree::child($source, ['any_name']) ?? throw new UnclassifiedSql('An assertion requires its name.'), $context, 3);
        $condition = (new ExpressionBinder())->bind(Tree::child($source, ['a_expr']) ?? throw new UnclassifiedSql('An assertion requires its condition.'), new Scope($context->tables->identifiers, queries: $context));
        return new CreateAssertionStatement($origin, $name, $condition, self::checking($source));
    }

    /**
     * Reads the checking time; NOT VALID and NO INHERIT do not apply and conflicting attributes are rejected.
     * @throws InvalidSql
     */
    public static function checking(Node $source): CheckingTime
    {
        $attributes = array_map(static fn (Node $element): string => strtoupper(Tree::text($element)), Tree::outer($source, ['ConstraintAttributeElem']));
        $has = static fn (string $attribute): bool => in_array($attribute, $attributes, true);
        if (array_diff($attributes, ['DEFERRABLE', 'NOT DEFERRABLE', 'INITIALLY DEFERRED', 'INITIALLY IMMEDIATE']) !== []
            || ($has('DEFERRABLE') && $has('NOT DEFERRABLE'))
            || ($has('INITIALLY DEFERRED') && ($has('INITIALLY IMMEDIATE') || $has('NOT DEFERRABLE')))) {
            throw new InvalidSql(InputViolation::ConstraintAttribute, $source);
        }
        if ($has('INITIALLY DEFERRED')) {
            return CheckingTime::DeferrableDeferred;
        }
        return $has('DEFERRABLE') ? CheckingTime::DeferrableImmediate : CheckingTime::Immediate;
    }
}
