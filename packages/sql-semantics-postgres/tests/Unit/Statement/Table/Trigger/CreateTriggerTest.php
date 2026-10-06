<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\CreateTrigger::class)]
#[Medium]
final class CreateTriggerTest extends TestCase
{
    public function testCreatedSchemaIsTheSchemaOfTheTable(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TRIGGER g AFTER INSERT ON s.t EXECUTE FUNCTION f()', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\CreateTrigger::class, $n1);
        self::assertSame('s', $n1->createdSchema()?->value);
    }

    public function testDeriveStatementSeesOldAndNewInARowTriggerCondition(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE TRIGGER g BEFORE UPDATE ON t FOR EACH ROW WHEN (old.a <> new.a OR b > 0) EXECUTE FUNCTION f()', $context);
        self::assertSame([
          0 => 'Column b is ambiguous.',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testDeriveElementLocatesTheTableInTheSchema(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TRIGGER g AFTER INSERT ON t EXECUTE FUNCTION f()');
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\SchemaElement::class, $statement->statement);
        $derivation = new \SqlSemantics\Construction\Derivation($statement->context);
        $statement->statement->deriveElement($derivation, new \SqlSemantics\Statement\Identifier\Name('s'));
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\CreateTrigger::class, $n1);
        $n2 = $derivation->facts()->relation($n1)->table;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\UndeclaredTable::class, $n2);
        self::assertSame('s', $n2->missing->name->schema?->value);
    }

    public function testDeriveRelationResolvesTheTable(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE TRIGGER g AFTER INSERT ON t EXECUTE FUNCTION f()', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\CreateTrigger::class, $n1);
        self::assertSame(3, count($n1->deriveRelation(new \SqlSemantics\Construction\Derivation($statement->context), new \SqlSemantics\Resolution\Environment($statement->context))->shape->slots));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE OR REPLACE TRIGGER g INSTEAD OF DELETE OR TRUNCATE ON v REFERENCING OLD TABLE AS o FOR STATEMENT WHEN (true) EXECUTE FUNCTION s.f(1, 2.5, \'x\', w)', []);
        self::assertSame('CREATE OR REPLACE TRIGGER g INSTEAD OF DELETE OR TRUNCATE ON v REFERENCING OLD TABLE o FOR EACH STATEMENT WHEN (TRUE) EXECUTE FUNCTION s.f(1, 2.5, \'x\', w)', $statement->toString());
    }
}
