<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerEvent;

#[CoversClass(TriggerEvent::class)]
#[Medium]
final class TriggerEventTest extends TestCase
{
    public function testCasesSpellTheThreeEvents(): void
    {
        self::assertSame(['DELETE', 'INSERT', 'UPDATE'], array_map(static fn (TriggerEvent $event): string => $event->value, TriggerEvent::cases()));
        self::assertSame(TriggerEvent::Update, TriggerEvent::from('UPDATE'));
    }

    public function testCasesAreReadFromTheEventWordWhateverItsSpelling(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $delete = $semantics->analyze('create trigger tr delete on t begin select 1; end');
        $insert = $semantics->analyze('create trigger tr after Insert on t begin select 1; end');

        self::assertInstanceOf(CreateTrigger::class, $delete->statement);
        self::assertSame(TriggerEvent::Delete, $delete->statement->event);
        self::assertSame('CREATE TRIGGER tr DELETE ON t BEGIN SELECT 1; END', $delete->toString());
        self::assertInstanceOf(CreateTrigger::class, $insert->statement);
        self::assertSame(TriggerEvent::Insert, $insert->statement->event);
        self::assertSame('CREATE TRIGGER tr AFTER INSERT ON t BEGIN SELECT 1; END', $insert->toString());
    }
}
