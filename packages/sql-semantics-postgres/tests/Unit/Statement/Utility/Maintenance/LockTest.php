<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Lock;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\LockMode;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;

#[CoversClass(Lock::class)]
#[Medium]
final class LockTest extends TestCase
{
    public function testEffectiveModeIsTheWrittenModeOrAccessExclusive(): void
    {
        $written = (new Semantics(Dialect::PostgreSql))->analyze('LOCK t IN SHARE MODE')->statement;
        $default = (new Semantics(Dialect::PostgreSql))->analyze('LOCK t')->statement;
        self::assertInstanceOf(Lock::class, $written);
        self::assertInstanceOf(Lock::class, $default);
        self::assertSame([LockMode::Share, LockMode::AccessExclusive], [$written->effectiveMode(), $default->effectiveMode()]);
    }

    public function testDeriveStatementResolvesEachTable(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('LOCK TABLE a, s.b *');
        self::assertInstanceOf(Lock::class, $operation->statement);
        self::assertInstanceOf(UndeclaredTable::class, $operation->facts->relation($operation->statement->tables[1])->table);
    }

    public function testRenderDropsTheWordTable(): void
    {
        self::assertSame('LOCK ONLY a, b IN SHARE UPDATE EXCLUSIVE MODE NOWAIT', (new Semantics(Dialect::PostgreSql))->analyze('LOCK TABLE ONLY a, b IN SHARE UPDATE EXCLUSIVE MODE NOWAIT')->toString());
    }

    public function testDeriveStatementReportsARelationThatCannotBeLocked(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE VIEW v AS SELECT 1 AS a'), $semantics->analyze('CREATE SEQUENCE s')];
        self::assertSame(['cannot lock relation "s"'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('LOCK v, s', $context)->facts->diagnostics));
    }
}
