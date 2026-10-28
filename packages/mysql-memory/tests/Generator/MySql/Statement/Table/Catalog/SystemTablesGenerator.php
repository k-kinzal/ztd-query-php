<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Catalog;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Composition;

/**
 * Composes SystemTables values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables>
 */
final class SystemTablesGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables> */
    use Composition;

    /**
     * @param ErisGenerator<list<M\Table\Catalog\SystemTable>> $tables Generated constructor operand
     */
    public function __construct(ErisGenerator $tables)
    {
        $this->delegate = Generators::map(static function (array $parts): M\Table\Catalog\SystemTables {
            return new M\Table\Catalog\SystemTables(tables: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $tables
        ));
    }

    /**
     * Generates an installed catalog with dependent SHOW and column metadata reads.
     *
     * @return ErisGenerator<array{M\Table\Catalog\SystemTables, M\Utility\Show\Schema\ShowColumns, M\QueryRoot}>
     */
    public static function inspections(GrammarRelease $release = GrammarRelease::MySql847): ErisGenerator
    {
        return \Tests\Generator\Operands::bind(Generators::elements(M\Table\Catalog\SystemTables::of($release)->tables), static function (M\Table\Catalog\SystemTable $table) use ($release): ErisGenerator {
            $remaining = array_values(array_filter(M\Table\Catalog\SystemTables::of($release)->tables, static fn (M\Table\Catalog\SystemTable $entry): bool => $entry->schema !== $table->schema || $entry->name !== $table->name));
            $catalog = new M\Table\Catalog\SystemTables([$table, ...$remaining]);
            $show = new M\Utility\Show\Schema\ShowColumns(new M\Utility\Show\InspectedTable($table->declaration->name), M\Utility\Show\ShowListing::Full);
            $fields = ['DATA_TYPE', 'CHARACTER_MAXIMUM_LENGTH', 'CHARACTER_OCTET_LENGTH', 'NUMERIC_PRECISION', 'NUMERIC_SCALE', 'DATETIME_PRECISION', 'COLUMN_COMMENT'];
            $items = array_map(static fn (string $name): M\Query\SelectExpression => new M\Query\SelectExpression(new M\Name\ColumnUse(new Name($name))), $fields);
            $where = new M\Expression\Logical(
                M\Expression\LogicalOperator::And,
                new M\Expression\Comparison(M\Expression\ComparisonOperator::Equal, new M\Name\ColumnUse(new Name('TABLE_SCHEMA')), new M\Literal\StringLiteral([$table->schema])),
                new M\Expression\Comparison(M\Expression\ComparisonOperator::Equal, new M\Name\ColumnUse(new Name('TABLE_NAME')), new M\Literal\StringLiteral([$table->name])),
            );
            $query = new M\QueryRoot(new M\Query\Select([], $items, new M\Relation\TableReference(new QualifiedName(new Name('COLUMNS'), new Name('information_schema'))), $where, orderBy: [new M\Query\OrderItem(new M\Name\ColumnUse(new Name('ORDINAL_POSITION')))]));
            $columns = $catalog->find('information_schema', 'COLUMNS');
            \SqlSemantics\Diagnostic\Check::invariant($columns !== null, 'A generated catalog retains its column metadata relation.');
            $context = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, $release->value))->context($table === $columns ? [$table->declaration] : [$table->declaration, $columns->declaration]);
            $show = \SqlSemantics\Facade\Semantics::fromStatement($context, $show);
            $query = \SqlSemantics\Facade\Semantics::fromStatement($context, $query);
            \SqlSemantics\Diagnostic\Check::invariant($show instanceof M\Utility\Show\Schema\ShowColumns && $query instanceof M\QueryRoot, 'Catalog inspections retain their concrete statement kinds.');
            return \Tests\Generator\Operands::bind(new self(Generators::vector(1, SystemTableGenerator::catalog($release, Generators::constant($table)))), static function (M\Table\Catalog\SystemTables $selected) use ($remaining, $show, $query): ErisGenerator {
                return Generators::constant([new M\Table\Catalog\SystemTables([$selected->tables[0], ...$remaining]), $show, $query]);
            });
        });
    }

}
