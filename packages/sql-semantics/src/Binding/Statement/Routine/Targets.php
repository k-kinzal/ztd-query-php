<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Classifies overloaded routine identities and the three aggregate signature forms.
 * @visibility SqlSemantics
 */
final class Targets
{
    /**
     * Distinguishes a missing overload signature from a zero-argument signature.
     */
    public static function routine(Node $source, QueryContext $context): Routine\RoutineByName|Routine\RoutineBySignature
    {
        $name = self::name(Tree::child($source, ['func_name']) ?? $source, $context);
        $arguments = Tree::child($source, ['func_args']);
        return $arguments === null ? new Routine\RoutineByName($name) : new Routine\RoutineBySignature($name, array_map(static fn (Node $argument): Routine\RoutineParameter => Parameters::routine($argument, $context), Tree::outer($arguments, ['func_arg'])));
    }

    /**
     * Requires identifier components instead of treating subscript syntax as a routine name, and at most catalog,
     * schema and routine, as PostgreSQL's name reader does.
     * @throws InvalidSql
     */
    public static function name(Node $source, QueryContext $context): QualifiedName
    {
        foreach (Tree::outer($source, ['indirection_el']) as $component) {
            if (Tree::child($component, ['attr_name']) === null) {
                throw new InvalidSql(InputViolation::RoutineName, $source);
            }
        }
        $parts = $context->tables->identifiers->parts($source);
        if (count($parts) > 3) {
            throw new InvalidSql(InputViolation::CatalogObjectName, $source);
        }
        return new QualifiedName($parts);
    }

    /**
     * Preserves the boundary between direct and ordered aggregate inputs.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function aggregate(Node $source, QueryContext $context): Routine\ZeroArgumentAggregate|Routine\OrdinaryAggregate|Routine\OrderedSetAggregate
    {
        $name = self::name(Tree::child($source, ['func_name']) ?? throw new UnclassifiedSql('An aggregate requires its name.'), $context);
        $arguments = Tree::child($source, ['aggr_args']) ?? throw new UnclassifiedSql('An aggregate requires its signature.');
        $lists = Tree::outer($arguments, ['aggr_args_list']);
        if ($lists === []) {
            return new Routine\ZeroArgumentAggregate($name);
        }
        $parameters = array_map(static fn (Node $list): array => array_map(static fn (Node $argument): Routine\AggregateParameter => Parameters::aggregate($argument, $context), Tree::outer($list, ['func_arg'])), $lists);
        $ordered = false;
        foreach ($arguments->children as $child) {
            $ordered = $ordered || ($child instanceof \SqlParser\Lexer\Token && strtoupper($child->text) === 'ORDER');
        }
        if (!$ordered) {
            return new Routine\OrdinaryAggregate($name, Collections::nonEmpty($parameters[0]));
        }
        try {
            return new Routine\OrderedSetAggregate($name, count($parameters) === 2 ? $parameters[0] : [], Collections::nonEmpty($parameters[count($parameters) - 1]));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::AggregateVariadicSignature, $arguments, $error);
        }
    }
}
