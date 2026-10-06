<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\ForeignKey::class)]
#[Medium]
final class ForeignKeyTest extends TestCase
{
    public function testKindIsForeignKey(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, FOREIGN KEY (a) REFERENCES u)', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class, $n2);
        $n3 = $n2->elements[1];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\ForeignKey::class, $n3);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind::ForeignKey, $n3->kind());
    }

    public function testDeriveClauseChecksBothSides(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE p (id int PRIMARY KEY)')->declarations());
        $statement = $semantics->analyze('CREATE TABLE t (a int, FOREIGN KEY (zz) REFERENCES p (nope) NO INHERIT)', $context);
        self::assertSame([
          0 => 'column "zz" named in key does not exist',
          1 => 'column "nope" referenced in foreign key constraint does not exist',
          2 => 'FOREIGN KEY constraints cannot be marked NO INHERIT',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, CONSTRAINT f FOREIGN KEY (a) REFERENCES p (id) MATCH SIMPLE ON UPDATE CASCADE ON DELETE SET NULL NOT VALID)', []);
        self::assertSame('CREATE TABLE t (a INT, CONSTRAINT f FOREIGN KEY (a) REFERENCES p (id) MATCH SIMPLE ON UPDATE CASCADE ON DELETE SET NULL NOT VALID)', $statement->toString());
    }
}
