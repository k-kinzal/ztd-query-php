<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Recursion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\CommonTableExpression;
use SqlSemantics\Model\Query\Recursion\CycleClause;
use SqlSemantics\Model\Query\Recursion\RecursionRules;
use SqlSemantics\Model\Query\Recursion\SearchClause;
use SqlSemantics\Model\Query\Recursion\SearchOrder;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RecursionRules::class)]
#[Medium]
final class RecursionRulesTest extends TestCase
{
    public function testValidateAcceptsNewColumnNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH RECURSIVE r(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM r) CYCLE n SET c USING p SELECT n FROM r');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $definition = $statement->ctes?->definitions[0];
        self::assertInstanceOf(CommonTableExpression::class, $definition);
        RecursionRules::validate($definition);
        self::assertSame(['n'], $definition->visibleColumns());
    }

    public function testValidateRejectsAnAddedColumnThatTheQueryAlreadyNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH RECURSIVE r(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM r) SELECT n FROM r');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $definition = $statement->ctes?->definitions[0];
        self::assertInstanceOf(CommonTableExpression::class, $definition);
        $this->expectException(InvalidStructure::class);
        new CommonTableExpression($definition->name, $definition->query, $definition->columns, search: new SearchClause(SearchOrder::DepthFirst, ['n'], 'n'));
    }

    public function testValidateRejectsAListedColumnThatTheQueryDoesNotName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH RECURSIVE r(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM r) SELECT n FROM r');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $definition = $statement->ctes?->definitions[0];
        self::assertInstanceOf(CommonTableExpression::class, $definition);
        $this->expectException(InvalidStructure::class);
        new CommonTableExpression($definition->name, $definition->query, $definition->columns, cycle: new CycleClause(['x'], 'c', null, null, 'p'));
    }

    public function testValidateRejectsOutsidePostgreSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('WITH RECURSIVE r(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM r) SELECT n FROM r');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $definition = $statement->ctes?->definitions[0];
        self::assertInstanceOf(CommonTableExpression::class, $definition);
        $this->expectException(InvalidStructure::class);
        new CommonTableExpression($definition->name, $definition->query, $definition->columns, search: new SearchClause(SearchOrder::DepthFirst, ['n'], 'ord'));
    }

    public function testValidateDiagnosesInvalidInputAsInvalidSql(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RecursiveQueryClause->message());
        $binder->bind('WITH RECURSIVE r(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM r) CYCLE n SET c USING c SELECT n FROM r');
    }

    public function testValidateRequiresARecursiveWithClause(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RecursiveQueryClause->message());
        $binder->bind('WITH r(n) AS (SELECT 1) SEARCH DEPTH FIRST BY n SET ord SELECT n FROM r');
    }
}
