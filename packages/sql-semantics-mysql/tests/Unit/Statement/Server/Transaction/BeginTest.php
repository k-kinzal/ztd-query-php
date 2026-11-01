<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Begin;

#[CoversClass(Begin::class)]
#[Medium]
final class BeginTest extends TestCase
{
    public function testRenderWritesBegin(): void
    {
        self::assertSame('BEGIN WORK', (new Semantics(Dialect::MySql))->analyze('begin work')->toString());
    }

    public function testDeriveStatementPublishesTheConcreteTransaction(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('BEGIN');

        self::assertInstanceOf(Begin::class, $operation);
        self::assertSame([], $operation->declarations());
    }

    public function testToStringReconstructsTheConcreteOperands(): void
    {
        self::assertSame('BEGIN', (new Begin())->toString());
    }

    public function testProfileRetainsTheSelectedRelease(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql5651);
        self::assertSame($profile, (new Begin(profile: $profile))->profile());
    }

    public function testDeclarationsIntroducesNoSchemaObjects(): void
    {
        self::assertSame([], (new Begin())->declarations());
    }
}
