<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema\Copy;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\TableLikeBinder;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Schema\Constraint\Check;
use SqlSemantics\Schema\Constraint\ForeignKey;
use SqlSemantics\Schema\IndexDefinition;
use SqlSemantics\Schema\Table\MySqlProperties;
use SqlSemantics\Schema\TableConstraint;
use SqlSemantics\Schema\TableDefinition;

/**
 * Applies MySQL's definition-copy operation to the supplied schema snapshot.
 * @visibility SqlSemantics
 */
final class MySqlCopy
{
    /**
     * Copies declared storage and integrity information, excluding foreign keys and state counters.
     */
    public static function bind(Node $source, TableResolver $resolver): ?TableDefinition
    {
        $operation = TableLikeBinder::bind(new Origin('declaration', $source, $resolver->schema->dialect), $source, new QueryContext($resolver));
        if ($operation === null) {
            return null;
        }
        $base = $operation->template->declaration;
        $parts = $operation->target->parts;
        $name = $parts[count($parts) - 1];
        $namespace = count($parts) === 2 ? $parts[0] : $resolver->defaultSchema;
        $constraints = array_values(array_filter($base->constraints, static fn (TableConstraint $constraint): bool => !$constraint instanceof ForeignKey));
        $constraints = array_map(static fn (TableConstraint $constraint): TableConstraint => $constraint instanceof Check ? new Check($constraint->predicate, $constraint->enforced, $constraint->noInherit, source: $constraint->source) : $constraint, $constraints);
        $indexes = array_map(static fn (IndexDefinition $index): IndexDefinition => new IndexDefinition($namespace, $index->name, $namespace === '' ? [$name] : [$namespace, $name], $index->elements, $index->unique, $index->method, $index->include, $index->predicate, $index->source, $index->properties), $base->indexes);
        $properties = MySqlOptions::copy($base->properties instanceof MySqlProperties ? $base->properties : new MySqlProperties(), $operation->temporary);
        $table = new TableDefinition($namespace, $name, $base->columns, $constraints, $source, $base->resolved, $indexes, $properties);
        return DeclarationReferences::rebind($table, $base);
    }
}
