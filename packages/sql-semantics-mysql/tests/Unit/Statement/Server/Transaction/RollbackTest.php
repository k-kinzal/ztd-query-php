<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Rollback;

#[CoversClass(Rollback::class)]
#[Medium]
final class RollbackTest extends TestCase
{
    public function testRenderWritesTheCompletionChoices(): void
    {
        self::assertSame('ROLLBACK WORK AND NO CHAIN RELEASE', (new Semantics(Dialect::MySql))->analyze('rollback work and no chain release')->toString());
    }

    public function testDeriveStatementPublishesTheConcreteTransaction(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('ROLLBACK');

        self::assertInstanceOf(Rollback::class, $operation);
        self::assertSame([], $operation->declarations());
    }

    public function testToStringReconstructsTheConcreteOperands(): void
    {
        self::assertSame('ROLLBACK', (new Rollback())->toString());
    }

    public function testProfileRetainsTheSelectedRelease(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql5651);
        self::assertSame($profile, (new Rollback(profile: $profile))->profile());
    }

    public function testDeclarationsIntroducesNoSchemaObjects(): void
    {
        self::assertSame([], (new Rollback())->declarations());
    }
}
