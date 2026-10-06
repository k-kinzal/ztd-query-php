<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\IndexConstraint::class)]
#[Medium]
final class IndexConstraintTest extends TestCase
{
    public function testKindIsUniqueOrPrimaryKey(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER TABLE t ADD UNIQUE USING INDEX i', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTable::class, $n1);
        $n2 = $n1->commands[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AddConstraint::class, $n2);
        $n3 = $n2->constraint;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\IndexConstraint::class, $n3);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind::Unique, $n3->kind());
    }

    public function testDeriveClauseChecksTheAttributes(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER TABLE t ADD PRIMARY KEY USING INDEX i NOT VALID', []);
        self::assertSame([
          0 => 'Relation t does not exist.',
          1 => 'PRIMARY KEY constraints cannot be marked NOT VALID',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER TABLE t ADD CONSTRAINT k PRIMARY KEY USING INDEX i DEFERRABLE', []);
        self::assertSame('ALTER TABLE t ADD CONSTRAINT k PRIMARY KEY USING INDEX i DEFERRABLE', $statement->toString());
    }
}
