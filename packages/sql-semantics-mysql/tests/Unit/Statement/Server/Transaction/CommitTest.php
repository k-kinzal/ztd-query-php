<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Commit;

#[CoversClass(Commit::class)]
#[Medium]
final class CommitTest extends TestCase
{
    public function testRenderWritesTheCompletionChoices(): void
    {
        self::assertSame('COMMIT AND CHAIN NO RELEASE', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('commit and chain no release')->toString());
    }

    public function testDeriveStatementPublishesTheConcreteTransaction(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('COMMIT');

        self::assertInstanceOf(Commit::class, $operation);
        self::assertSame([], $operation->declarations());
    }

    public function testChoicesAreNullWhenAbsent(): void
    {
        $commit = new Commit();

        self::assertSame([null, null], [$commit->chain, $commit->release]);
    }

    public function testToStringReconstructsTheConcreteOperands(): void
    {
        self::assertSame('COMMIT', (new Commit())->toString());
    }

    public function testProfileRetainsTheSelectedRelease(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql5651);
        self::assertSame($profile, (new Commit(profile: $profile))->profile());
    }

    public function testDeclarationsIntroducesNoSchemaObjects(): void
    {
        self::assertSame([], (new Commit())->declarations());
    }
}
