<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerTiming;

#[CoversClass(TriggerTiming::class)]
#[Medium]
final class TriggerTimingTest extends TestCase
{
    public function testCasesSpellTheThreeTimings(): void
    {
        self::assertSame(['BEFORE', 'AFTER', 'INSTEAD OF'], array_map(static fn (TriggerTiming $timing): string => $timing->value, TriggerTiming::cases()));
        self::assertSame(TriggerTiming::InsteadOf, TriggerTiming::from('INSTEAD OF'));
    }

    public function testCasesAreReadFromTheTimingWordsOrAbsent(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $instead = $semantics->analyze('create trigger tr instead of insert on v begin select 1; end');
        $before = $semantics->analyze('create trigger tr Before delete on t begin select 1; end');
        $none = $semantics->analyze('create trigger tr delete on t begin select 1; end');

        self::assertInstanceOf(CreateTrigger::class, $instead->statement);
        self::assertSame(TriggerTiming::InsteadOf, $instead->statement->timing);
        self::assertSame('CREATE TRIGGER tr INSTEAD OF INSERT ON v BEGIN SELECT 1; END', $instead->toString());
        self::assertInstanceOf(CreateTrigger::class, $before->statement);
        self::assertSame(TriggerTiming::Before, $before->statement->timing);
        self::assertSame('CREATE TRIGGER tr BEFORE DELETE ON t BEGIN SELECT 1; END', $before->toString());
        self::assertInstanceOf(CreateTrigger::class, $none->statement);
        self::assertNull($none->statement->timing);
    }
}
