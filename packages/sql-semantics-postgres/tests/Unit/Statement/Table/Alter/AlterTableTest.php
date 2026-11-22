<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTable::class)]
#[Medium]
final class AlterTableTest extends TestCase
{
    public function testDeriveStatementDerivesTheActionsAgainstTheTable(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('ALTER TABLE t ADD d int CHECK (d > a), ALTER zz DROP NOT NULL', $context);
        self::assertSame([
          0 => 'column "zz" does not exist',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testDeriveRelationResolvesTheTable(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('ALTER TABLE t DROP b', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTable::class, $n1);
        $n2 = $statement->facts->relation($n1)->table;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\DeclaredTable::class, $n2);
        self::assertSame(true, $n2->table === $context[0]);
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('ALTER TABLE IF EXISTS ONLY s.t ADD COLUMN d int, DROP COLUMN e', []);
        self::assertSame('ALTER TABLE IF EXISTS ONLY s.t ADD d INT, DROP e', $statement->toString());
    }

    public function testRefusesOnlyForAnIndex(): void
    {
        $this->expectExceptionMessage('ONLY is written for tables and foreign tables.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTable(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTarget::Index, new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('i')), true), [new \SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\TableAction(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\TableActionKind::SetLogged)]);
    }
}
