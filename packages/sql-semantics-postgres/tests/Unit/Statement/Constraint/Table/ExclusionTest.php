<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\Exclusion::class)]
#[Medium]
final class ExclusionTest extends TestCase
{
    public function testKindIsExclusion(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, EXCLUDE (a WITH =))', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class, $n2);
        $n3 = $n2->elements[1];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\Exclusion::class, $n3);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind::Exclusion, $n3->kind());
    }

    public function testDeriveClauseDerivesTheElementsAndThePredicate(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, EXCLUDE (zz WITH =) INCLUDE (yy) WHERE (a) NOT VALID)', []);
        self::assertSame([
          0 => 'Column zz does not exist.',
          1 => 'column "yy" named in key does not exist',
          2 => 'argument of WHERE must be type boolean',
          3 => 'EXCLUDE constraints cannot be marked NOT VALID',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, CONSTRAINT x EXCLUDE USING gist (a WITH =, (a + 1) WITH OPERATOR(pg_catalog.=)) INCLUDE (a) WITH (fillfactor = 70) USING INDEX TABLESPACE s WHERE (a > 0) DEFERRABLE)', []);
        self::assertSame('CREATE TABLE t (a INT, CONSTRAINT x EXCLUDE USING gist (a WITH =, (a + 1) WITH OPERATOR (pg_catalog.=)) INCLUDE (a) WITH (fillfactor = 70) USING INDEX TABLESPACE s WHERE (a > 0) DEFERRABLE)', $statement->toString());
    }
}
