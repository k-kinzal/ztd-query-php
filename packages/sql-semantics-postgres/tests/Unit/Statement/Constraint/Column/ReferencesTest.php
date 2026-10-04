<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\References::class)]
#[Medium]
final class ReferencesTest extends TestCase
{
    public function testKindIsForeignKey(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int REFERENCES u)', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class, $n2);
        $n3 = $n2->elements[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition::class, $n3);
        $n4 = $n3->qualifiers[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\References::class, $n4);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind::ForeignKey, $n4->kind());
    }

    public function testDeriveClauseResolvesTheReferencedTable(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE p (id int PRIMARY KEY)')->declarations());
        $statement = $semantics->analyze('CREATE TABLE t (a int REFERENCES p (id))', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class, $n2);
        $n3 = $n2->elements[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition::class, $n3);
        $n4 = $n3->qualifiers[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\References::class, $n4);
        $n5 = $statement->facts->relation($n4)->table;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\DeclaredTable::class, $n5);
        self::assertSame(true, $n5->table === $context[0]);
    }

    public function testDeriveClauseReportsAMissingReferencedColumn(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE p (id int PRIMARY KEY)')->declarations());
        $statement = $semantics->analyze('CREATE TABLE t (a int REFERENCES p (nope) MATCH PARTIAL ON UPDATE SET NULL (a))', $context);
        self::assertSame([
          0 => 'column "nope" referenced in foreign key constraint does not exist',
          1 => 'MATCH PARTIAL not yet implemented',
          2 => 'a column list with SET NULL is only supported for ON DELETE actions',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int CONSTRAINT f REFERENCES s.p (id) MATCH FULL ON DELETE CASCADE ON UPDATE RESTRICT)', []);
        self::assertSame('CREATE TABLE t (a INT CONSTRAINT f REFERENCES s.p (id) MATCH FULL ON DELETE CASCADE ON UPDATE RESTRICT)', $statement->toString());
    }
}
