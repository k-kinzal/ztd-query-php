<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ConditionalColumn values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Reference\Column\ConditionalColumn>
 */
final class ConditionalColumnGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Reference\Column\ConditionalColumn> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Reference\Column\ResolvedColumn>> $candidates Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Relation>> $relations Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Reference\Missing\MissingInput>> $missing Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $candidates, ErisGenerator $relations, ErisGenerator $missing)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Reference\Column\ConditionalColumn {
            return new \SqlSemantics\Statement\Reference\Column\ConditionalColumn(name: $parts[0], candidates: $parts[1], relations: $parts[2], missing: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $candidates,
            $relations,
            $missing
        ));
    }
    /**
     * Generates a write followed by a read captured with incomplete declarations.
     * Binding against the installed table must resolve the same selected column.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Table\CreateTable, \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows, \SqlSemantics\Platform\MySql\Statement\QueryRoot, \SqlSemantics\Platform\MySql\Statement\QueryRoot}>
     */
    public static function incompleteReads(): ErisGenerator
    {
        return \Tests\Generator\Operands::bind(\Tests\Generator\MySql\Statement\QueryRootGenerator::declaredTableSequence(), self::fromSequence(...));
    }

    /**
     * Composes the unresolved read from the generated definition and reference query.
     *
     * @param array{\SqlSemantics\Platform\MySql\Statement\Table\CreateTable, \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows, \SqlSemantics\Platform\MySql\Statement\QueryRoot} $sequence The generated prerequisite statements
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Table\CreateTable, \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows, \SqlSemantics\Platform\MySql\Statement\QueryRoot, \SqlSemantics\Platform\MySql\Statement\QueryRoot}>
     */
    public static function fromSequence(array $sequence): ErisGenerator
    {
        [$create, $insert, $expected] = $sequence;
        $table = $create->declarations()[0];
        $field = $expected->field(0);
        \SqlSemantics\Diagnostic\Check::invariant($field->name !== null, 'The generated dependent column has a name.');
        $incomplete = new \Tests\Generator\Semantics\Statement\Declaration\TableGenerator(Generators::constant($table->name), Generators::constant($table->profile), Generators::constant([]), complete: Generators::constant(false));
        $missing = new \Tests\Generator\Semantics\Statement\Reference\Missing\IncompleteMembersGenerator($incomplete);
        return \Tests\Generator\Operands::bind($missing, static function (\SqlSemantics\Statement\Reference\Missing\IncompleteMembers $missing) use ($create, $insert, $expected, $field): ErisGenerator {
            $relation = new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference($missing->table->name, relationInformation: new \SqlSemantics\Statement\Fact\RelationFact(new \SqlSemantics\Statement\Shape\RowShape([], [$missing]), new \SqlSemantics\Statement\Reference\Table\DeclaredTable($missing->table)));
            $bindings = new self(Generators::constant($field->name), Generators::constant([]), Generators::constant([$relation]), Generators::constant([$missing]));
            return Generators::map(static function (\SqlSemantics\Statement\Reference\Column\ConditionalColumn $binding) use ($create, $insert, $expected, $relation): array {
                $type = new \SqlSemantics\Statement\Type\Dependent($binding->missing);
                $expression = new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse($binding->name, scalarInformation: new \SqlSemantics\Statement\Fact\ScalarFact($type, \SqlSemantics\Statement\Type\Nullability::Dependent, $binding));
                $output = new \SqlSemantics\Statement\Fact\QueryFact([new \SqlSemantics\Statement\Shape\Field(0, new \SqlSemantics\Statement\Shape\OutputSlot($binding->name, $type, \SqlSemantics\Statement\Type\Nullability::Dependent), $expression, $binding)], \SqlSemantics\Statement\Identifier\Comparison::AsciiInsensitive);
                $query = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($expression)], $relation, queryInformation: $output), new \SqlSemantics\Platform\MySql\Statement\StatementSemantics($expected->profile(), output: $output, connection: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_unicode_ci')));
                return [$create, $insert, $query, $expected];
            }, $bindings);
        });
    }
}
