<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Table;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CommonTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Reference\Table\CommonTable>
 */
final class CommonTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Reference\Table\CommonTable> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Node> $definition Generated constructor operand
     */
    public function __construct(ErisGenerator $definition)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Reference\Table\CommonTable {
            return new \SqlSemantics\Statement\Reference\Table\CommonTable(definition: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $definition
        ));
    }
    /**
     * Composes one CTE definition and a reference to that exact definition.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\QueryRoot>
     */
    public static function queries(): ErisGenerator
    {
        $queries = Generators::map(static fn (\SqlSemantics\Platform\MySql\Statement\QueryRoot $statement): \SqlSemantics\Statement\Query => $statement->query, \Tests\Generator\MySql\Statement\QueryRootGenerator::projection());
        $definitions = new \Tests\Generator\MySql\Statement\Query\With\CommonTableExpressionGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), Generators::constant([]), $queries);
        return Generators::map(static function (\SqlSemantics\Statement\Reference\Table\CommonTable $binding): \SqlSemantics\Platform\MySql\Statement\QueryRoot {
            $definition = $binding->definition;
            \SqlSemantics\Diagnostic\Check::invariant($definition instanceof \SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression, 'The generated common table binds its CTE definition.');
            $field = $definition->query->queryInformation()->projection[0];
            \SqlSemantics\Diagnostic\Check::invariant($field instanceof \SqlSemantics\Statement\Shape\Field && $field->name !== null, 'The generated CTE has a named scalar output.');
            $relation = new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName($definition->name), relationInformation: new \SqlSemantics\Statement\Fact\RelationFact(new \SqlSemantics\Statement\Shape\RowShape([$field->slot]), $binding));
            $resolution = new \SqlSemantics\Statement\Reference\Column\ResolvedColumn($relation, $field->slot);
            $expression = new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse($field->name, scalarInformation: new \SqlSemantics\Statement\Fact\ScalarFact($field->type, $field->nullability, $resolution));
            $output = new \SqlSemantics\Statement\Fact\QueryFact([new \SqlSemantics\Statement\Shape\Field(0, $field->slot, $expression, $resolution)], \SqlSemantics\Statement\Identifier\Comparison::AsciiInsensitive);
            $body = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($expression)], $relation, queryInformation: $output);
            $query = new \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression(new \SqlSemantics\Platform\MySql\Statement\Query\With\With(false, [$definition]), $body, queryInformation: $output);
            return new \SqlSemantics\Platform\MySql\Statement\QueryRoot($query, new \SqlSemantics\Platform\MySql\Statement\StatementSemantics(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), output: $output, connection: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_unicode_ci')));
        }, new self($definitions));
    }

}
