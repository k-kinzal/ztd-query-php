<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Savepoint;

#[CoversClass(Savepoint::class)]
#[Medium]
final class SavepointTest extends TestCase
{
    public function testRenderWritesTheSavepoint(): void
    {
        self::assertSame('SAVEPOINT s', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('savepoint s')->toString());
    }

    public function testDeriveStatementPublishesTheConcreteTransaction(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SAVEPOINT s');

        self::assertInstanceOf(Savepoint::class, $operation);
        self::assertSame([], $operation->declarations());
    }

    public function testNameWarningOwnsTheNameWarningWithoutAnAnalysisIndex(): void
    {
        $statement = new Savepoint(new \SqlSemantics\Statement\Identifier\Name('fUlL', \SqlSemantics\Statement\Identifier\NameQuote::Bare));
        self::assertNotNull($statement->nameWarning);
        self::assertSame(4119, $statement->nameWarning->code());
        self::assertNull((new Savepoint(new \SqlSemantics\Statement\Identifier\Name('FULL')))->nameWarning);
    }

    public function testToStringReconstructsTheConcreteOperands(): void
    {
        self::assertSame('SAVEPOINT `s p`', (new Savepoint(new \SqlSemantics\Statement\Identifier\Name('s p')))->toString());
    }

    public function testProfileRetainsTheSelectedRelease(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql5651);
        self::assertSame($profile, (new Savepoint(new \SqlSemantics\Statement\Identifier\Name('s p'), profile: $profile))->profile());
    }

    public function testDeclarationsIntroducesNoSchemaObjects(): void
    {
        self::assertSame([], (new Savepoint(new \SqlSemantics\Statement\Identifier\Name('s p')))->declarations());
    }
}
