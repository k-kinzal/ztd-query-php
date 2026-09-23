<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\ColumnBinding;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Reads declared routine arguments, including schema-derived column types.
 * @visibility SqlSemantics
 */
final class Parameters
{
    /**
     * Keeps explicit and omitted parameter modes distinct for routine lookup.
     * @throws UnclassifiedSql
     */
    public static function routine(Node $argument, QueryContext $context): Routine\RoutineParameter
    {
        $modeNode = Tree::child($argument, ['arg_class']);
        $mode = $modeNode === null ? Routine\ParameterMode::Implicit : Routine\ParameterMode::from(str_replace('IN OUT', 'INOUT', strtoupper(Tree::text($modeNode))));
        $nameNode = Tree::child($argument, ['param_name']);
        $name = $nameNode === null ? null : $context->tables->identifiers->name($nameNode->tokens()[0]);
        $type = Tree::child($argument, ['func_type']) ?? throw new UnclassifiedSql('A routine argument requires its declared type.');
        $declaration = Tree::child($type, ['Typename']) ?? $type;
        $setOf = strtoupper($declaration->tokens()[0]->text ?? '') === 'SETOF';
        return new Routine\RoutineParameter(self::type($type, $context), $mode, $name, $setOf);
    }

    /**
     * Aggregate arguments expose only input and variadic modes.
     * @throws InvalidSql
     */
    public static function aggregate(Node $argument, QueryContext $context): Routine\AggregateParameter
    {
        $parameter = self::routine($argument, $context);
        $mode = Routine\AggregateInputMode::tryFrom($parameter->mode->value);
        if ($mode === null) {
            throw new InvalidSql(InputViolation::AggregateArgumentMode, $argument);
        }
        return new Routine\AggregateParameter($parameter->type, $mode, $parameter->name, $parameter->setOf);
    }

    /**
     * Resolves a type declaration or a column-type reference without reading values.
     */
    public static function type(Node $source, QueryContext $context): TypeDescriptor|Routine\ColumnTypeReference
    {
        $declaration = Tree::child($source, ['Typename']);
        if ($declaration !== null) {
            return (new TypeReader(Dialect::PostgreSql))->read($declaration);
        }
        $parts = [];
        foreach (Tree::outer($source, ['type_function_name', 'attr_name']) as $part) {
            $parts[] = $context->tables->identifiers->name($part->tokens()[0]);
        }
        $name = new QualifiedName($parts);
        $column = $parts[count($parts) - 1];
        $table = $context->tables->resolve(array_slice($parts, 0, -1), $source);
        foreach ($table->columns as $definition) {
            if ($definition->name === $column) {
                return new Routine\ColumnTypeReference($name, new ColumnBinding($context->ids->relation(), $table, $definition));
            }
        }
        if ($table->resolved) {
            $context->tables->diagnostics->report('unknown-column', 'Cannot resolve column type: ' . implode('.', $parts), $source);
        }
        return new Routine\ColumnTypeReference($name, null);
    }
}
