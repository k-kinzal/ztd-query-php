<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\BodyFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\AtomicBody;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind;

#[CoversClass(BodyFacts::class)]
#[Medium]
final class BodyFactsTest extends TestCase
{
    public function testDeriveReportsUtilityStatements(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('CREATE FUNCTION f() RETURNS int4 BEGIN ATOMIC DROP TABLE t; SELECT 1 INTO x; (SELECT 1); END', []);
        self::assertSame(['Relation t does not exist.'], [$operation->facts->diagnostics[1]->message()]);
        self::assertEquals([new RoutineProblem(RoutineProblemKind::UtilityInBody), new RoutineProblem(RoutineProblemKind::UtilityInBody)], [$operation->facts->diagnostics[0], $operation->facts->diagnostics[2]]);
        self::assertCount(3, $operation->facts->diagnostics);
        self::assertSame([], $operation->declarations());
    }

    public function testFirstFindsTheSelectionOfANestedQuery(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('CREATE FUNCTION f() RETURNS int4 BEGIN ATOMIC (SELECT 1 UNION SELECT 2); END');
        $statement = $operation->statement;
        self::assertInstanceOf(CreateFunction::class, $statement);
        self::assertInstanceOf(AtomicBody::class, $statement->body);
        $query = $statement->body->statements[0];
        self::assertInstanceOf(ParenthesizedQuery::class, $query);
        self::assertInstanceOf(SetOperation::class, $query->query);
        self::assertSame($query->query->left, (new BodyFacts())->first($query));
    }
}
