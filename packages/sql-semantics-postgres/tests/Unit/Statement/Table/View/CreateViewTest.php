<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateView::class)]
#[Medium]
final class CreateViewTest extends TestCase
{
    public function testCreatedSchemaIsTheWrittenSchema(): void
    {
        $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
        $statement = $semantics->analyze('CREATE VIEW s.v AS SELECT 1', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateView::class, $n1);
        self::assertSame('s', $n1->createdSchema()?->value);
    }

    public function testRecursiveQueryIsTheQuery(): void
    {
        $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
        $statement = $semantics->analyze('CREATE RECURSIVE VIEW v (n) AS SELECT 1 UNION ALL SELECT n + 1 FROM v WHERE n < 5', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateView::class, $n1);
        $n2 = $n1->recursiveQuery();
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation::class, $n2);
        $n3 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateView::class, $n3);
        $n4 = $n3->query;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation::class, $n4);
        self::assertSame(true, $n2 === $n4);
    }

    public function testRecursiveColumnsAreTheColumnNames(): void
    {
        $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
        $statement = $semantics->analyze('CREATE RECURSIVE VIEW v (n) AS SELECT 1 UNION ALL SELECT n + 1 FROM v WHERE n < 5', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateView::class, $n1);
        self::assertSame('n', $n1->recursiveColumns()[0]->value);
    }

    public function testDeriveStatementDeclaresTheView(): void
    {
        $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE VIEW v (x) AS SELECT a, b FROM t', $context);
        self::assertSame([
          0 => 'x integer NotNull',
          1 => 'b integer Nullable',
        ], array_map(static fn ($column): string => $column->name->value . ' ' . $column->type->name() . ' ' . $column->nullability->name, $statement->declarations()[0]->columns));
    }

    public function testDeriveElementDeclaresTheViewInTheSchema(): void
    {
        $statement = (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('CREATE VIEW v AS SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\SchemaElement::class, $statement->statement);
        $derivation = new \SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation($statement->context);
        $statement->statement->deriveElement($derivation, new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('s'));
        self::assertSame('s', $derivation->facts()->declarations[0]->name->schema?->value);
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
        $statement = $semantics->analyze('CREATE OR REPLACE TEMP RECURSIVE VIEW v (n) WITH (security_barrier) AS SELECT 1 UNION ALL SELECT n + 1 FROM v WHERE n < 5 WITH CASCADED CHECK OPTION', []);
        self::assertSame('CREATE OR REPLACE TEMP RECURSIVE VIEW v (n) WITH (security_barrier) AS SELECT 1 UNION ALL SELECT n + 1 FROM v WHERE n < 5 WITH CASCADED CHECK OPTION', $statement->toString());
    }

    public function testRejectsACheckOptionAfterAnOpenIsJsonTest(): void
    {
        $query = (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('SELECT a FROM t WHERE b IS JSON')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Select::class, $query);
        $this->expectExceptionMessage('A query ending in IS JSON without a uniqueness clause would take WITH CHECK OPTION; group it.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateView(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('v')), $query, checkOption: \SqlSemantics\Platform\PostgreSql\Statement\Table\View\CheckOption::Local);
    }

    public function testDerivesViewOptionModifiers(): void
    {
        $sql = 'CREATE VIEW v WITH (opt = custom_type (2)) AS SELECT 1';
        $statement = (new \SqlSemantics\Platform\PostgreSql\Semantics())->inspect($sql);
        self::assertSame($sql, $statement->toString());
    }
}
