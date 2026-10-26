<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\RollbackToSavepoint;

#[CoversClass(RollbackToSavepoint::class)]
#[Medium]
final class RollbackToSavepointTest extends TestCase
{
    public function testRenderWritesTheSavepoint(): void
    {
        self::assertSame('ROLLBACK TO `s 1`', (new Semantics(Dialect::MySql))->analyze('rollback to `s 1`')->toString());
    }

    public function testDeriveStatementPublishesTheConcreteTransaction(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('ROLLBACK WORK TO SAVEPOINT s');

        self::assertInstanceOf(RollbackToSavepoint::class, $operation);
        self::assertSame([], $operation->declarations());
    }

    public function testNameWarningOwnsTheNameWarningWithoutAnAnalysisIndex(): void
    {
        $statement = new RollbackToSavepoint(new \SqlSemantics\Statement\Identifier\Name('fUlL', \SqlSemantics\Statement\Identifier\NameQuote::Bare));
        self::assertNotNull($statement->nameWarning);
        self::assertSame(4119, $statement->nameWarning->code());
        self::assertNull((new RollbackToSavepoint(new \SqlSemantics\Statement\Identifier\Name('FULL')))->nameWarning);
    }

    public function testToStringReconstructsTheConcreteOperands(): void
    {
        self::assertSame('ROLLBACK TO SAVEPOINT `s p`', (new RollbackToSavepoint(new \SqlSemantics\Statement\Identifier\Name('s p')))->toString());
    }

    public function testProfileRetainsTheSelectedRelease(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql5651);
        self::assertSame($profile, (new RollbackToSavepoint(new \SqlSemantics\Statement\Identifier\Name('s p'), profile: $profile))->profile());
    }

    public function testDeclarationsIntroducesNoSchemaObjects(): void
    {
        self::assertSame([], (new RollbackToSavepoint(new \SqlSemantics\Statement\Identifier\Name('s p')))->declarations());
    }
}
