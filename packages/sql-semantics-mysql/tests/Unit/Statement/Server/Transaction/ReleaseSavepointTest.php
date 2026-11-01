<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\ReleaseSavepoint;

#[CoversClass(ReleaseSavepoint::class)]
#[Medium]
final class ReleaseSavepointTest extends TestCase
{
    public function testRenderWritesTheSavepoint(): void
    {
        self::assertSame('RELEASE SAVEPOINT s', (new Semantics(Dialect::MySql))->analyze('release savepoint s')->toString());
    }

    public function testDeriveStatementPublishesTheConcreteTransaction(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('RELEASE SAVEPOINT s');

        self::assertInstanceOf(ReleaseSavepoint::class, $operation);
        self::assertSame([], $operation->declarations());
    }

    public function testNameWarningOwnsTheNameWarningWithoutAnAnalysisIndex(): void
    {
        $statement = new ReleaseSavepoint(new \SqlSemantics\Statement\Identifier\Name('fUlL', \SqlSemantics\Statement\Identifier\NameQuote::Bare));
        self::assertNotNull($statement->nameWarning);
        self::assertSame(4119, $statement->nameWarning->code());
        self::assertNull((new ReleaseSavepoint(new \SqlSemantics\Statement\Identifier\Name('FULL')))->nameWarning);
    }

    public function testToStringReconstructsTheConcreteOperands(): void
    {
        self::assertSame('RELEASE SAVEPOINT `s p`', (new ReleaseSavepoint(new \SqlSemantics\Statement\Identifier\Name('s p')))->toString());
    }

    public function testProfileRetainsTheSelectedRelease(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql5651);
        self::assertSame($profile, (new ReleaseSavepoint(new \SqlSemantics\Statement\Identifier\Name('s p'), profile: $profile))->profile());
    }

    public function testDeclarationsIntroducesNoSchemaObjects(): void
    {
        self::assertSame([], (new ReleaseSavepoint(new \SqlSemantics\Statement\Identifier\Name('s p')))->declarations());
    }
}
