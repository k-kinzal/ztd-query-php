<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Leaf\FlagRule;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\CreateTrigger;

#[CoversClass(FlagRule::class)]
#[Medium]
final class FlagRuleTest extends TestCase
{
    public function testTemporaryIsTrueForTempAndTemporaryAndFalseWithoutEither(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $plain = $semantics->analyze('CREATE TRIGGER tr INSERT ON t BEGIN SELECT 1; END')->statement;
        $temp = $semantics->analyze('CREATE TEMP TRIGGER tr INSERT ON t BEGIN SELECT 1; END')->statement;
        $temporary = $semantics->analyze('CREATE TEMPORARY TRIGGER tr INSERT ON t BEGIN SELECT 1; END')->statement;

        self::assertInstanceOf(CreateTrigger::class, $plain);
        self::assertInstanceOf(CreateTrigger::class, $temp);
        self::assertInstanceOf(CreateTrigger::class, $temporary);
        self::assertFalse($plain->temporary);
        self::assertTrue($temp->temporary);
        self::assertTrue($temporary->temporary);
    }

    public function testTemporaryRendersAsTemp(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('create temporary trigger tr insert on t begin select 1; end');

        self::assertSame('CREATE TEMP TRIGGER tr INSERT ON t BEGIN SELECT 1; END', $operation->toString());
    }

    public function testIfNotExistsIsTrueOnlyWhenTheClauseIsWritten(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $plain = $semantics->analyze('CREATE TRIGGER tr INSERT ON t BEGIN SELECT 1; END')->statement;
        $guarded = $semantics->analyze('CREATE TRIGGER IF NOT EXISTS tr INSERT ON t BEGIN SELECT 1; END')->statement;

        self::assertInstanceOf(CreateTrigger::class, $plain);
        self::assertInstanceOf(CreateTrigger::class, $guarded);
        self::assertFalse($plain->ifNotExists);
        self::assertTrue($guarded->ifNotExists);
        self::assertSame('CREATE TRIGGER IF NOT EXISTS tr INSERT ON t BEGIN SELECT 1; END', $semantics->analyze('CREATE TRIGGER IF NOT EXISTS tr INSERT ON t BEGIN SELECT 1; END')->toString());
    }
}
