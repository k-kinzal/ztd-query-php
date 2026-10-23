<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Explainable;
use Tests\Generator\Catalogue;

/**
 * Constructor roles restrict inputs before SQL derivation or execution.
 */
#[CoversNothing]
#[Medium]
final class StatementRolesProperties extends TestCase
{
    /**
     * @throws JsonException When the semantic inventory cannot be loaded
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesExplainableInventoryContainsOnlyQueriesAndDataManipulation(): void
    {
        self::assertEqualsCanonicalizing([
            \SqlSemantics\Platform\MySql\Statement\QueryRoot::class,
            InsertQuery::class, InsertRows::class, InsertSet::class, Update::class, Delete::class, MultipleDelete::class,
        ], (new Catalogue())->implementations(Explainable::class));
    }

    /**
     * Query-body and statement variants have disjoint grammatical roles.
     *
     * @throws JsonException When the semantic inventory cannot be loaded
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesQueryBodiesDoNotImplementTheStatementRole(): void
    {
        $catalogue = new Catalogue();

        self::assertSame([], array_values(array_intersect($catalogue->implementations(\SqlSemantics\Statement\Query::class), $catalogue->implementations(\SqlSemantics\Statement\Statement::class))));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerFilteredOperands(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 12; $seed++) {
                $choices = new \Tests\Generator\Choices('query-roles:' . $seed);
                yield $release . '/' . $seed => [$release, $choices->one(['0', '1', '42', '2147483648']), $choices->one(['0', '1'])];
            }
        }
    }

    /**
     * Valid no-FROM query operands remain executable in their supported grammar profiles.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerFilteredOperands')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesFilteredQueryOperandsExecuteWithoutStatementRestrictions(string $release, string $value, string $condition): void
    {
        $body = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral($value))], where: new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral($condition));
        $options = str_starts_with($release, '5.') ? [\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Cache] : [];
        $root = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select($options, [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery($body))]));
        $result = (new \Tests\Fake\MemoryClient())->run($root, $release);

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([[$condition === '0' ? null : $value]], $result->rows);
    }

}
