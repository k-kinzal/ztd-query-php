<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Table;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes TableResolution values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Reference\Table\TableResolution>
 */
final class TableResolutionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Reference\Table\TableResolution> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Statement\Reference\Table\TableResolution>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = \Tests\Generator\Operands::oneOf($alternatives);
    }
    /**
     * Generates old ambiguous or conditional declarations and their installed replacement.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Table\CreateTable, \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows, \SqlSemantics\Platform\MySql\Statement\QueryRoot, \SqlSemantics\Platform\MySql\Statement\QueryRoot}>
     */
    public static function reboundReads(): ErisGenerator
    {
        return \Tests\Generator\Operands::bind(\Tests\Generator\Operands::tuple(\Tests\Generator\MySql\Statement\QueryRootGenerator::declaredTableSequence(), \Tests\Generator\MySql\Statement\QueryRootGenerator::projection()), self::fromSequence(...));
    }

    /**
     * Composes the unresolved read from the generated definition and reference query.
     *
     * @param array{array{\SqlSemantics\Platform\MySql\Statement\Table\CreateTable, \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows, \SqlSemantics\Platform\MySql\Statement\QueryRoot}, \SqlSemantics\Platform\MySql\Statement\QueryRoot} $parts The generated prerequisite statements
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Table\CreateTable, \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows, \SqlSemantics\Platform\MySql\Statement\QueryRoot, \SqlSemantics\Platform\MySql\Statement\QueryRoot}>
     */
    public static function fromSequence(array $parts): ErisGenerator
    {
        [[$create, $insert, $selected], $projection] = $parts;
        $table = $create->declarations()[0];
        $unqualified = new \SqlSemantics\Statement\Identifier\QualifiedName($table->name->name);
        $earlier = new \SqlSemantics\Statement\Declaration\Table($table->name, $table->profile, [$table->columns[0]]);
        $bindings = new self([
            new ConditionalTableGenerator(Generators::constant([$table]), new \Tests\Generator\Semantics\Statement\Reference\Missing\UndeclaredRelationGenerator(Generators::constant($unqualified))),
            new ConflictingTablesGenerator(Generators::constant($unqualified), Generators::constant([$earlier, $table])),
        ]);
        return Generators::map(static function (\SqlSemantics\Statement\Reference\Table\TableResolution $binding) use ($create, $insert, $selected, $projection, $unqualified): array {
            \SqlSemantics\Diagnostic\Check::invariant($projection->query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select && $selected->query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select, 'The generated source and projection are SELECT blocks.');
            $missing = $binding instanceof \SqlSemantics\Statement\Reference\Table\ConditionalTable ? [$binding->missing] : [];
            $diagnostics = $binding instanceof \SqlSemantics\Statement\Reference\Table\ConflictingTables ? [$binding] : [];
            $relation = new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference($unqualified, relationInformation: new \SqlSemantics\Statement\Fact\RelationFact(new \SqlSemantics\Statement\Shape\RowShape([], $missing), $binding));
            $output = $projection->query->queryInformation();
            $input = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], $projection->query->items, $relation, queryInformation: $output), new \SqlSemantics\Platform\MySql\Statement\StatementSemantics($projection->profile(), diagnostics: $diagnostics, output: $output, connection: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_unicode_ci')));
            $expected = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], $projection->query->items, $selected->query->from, queryInformation: $output), $projection->statementInformation());
            return [$create, $insert, $input, $expected];
        }, $bindings);
    }
}
