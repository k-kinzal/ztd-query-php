<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Rules\Routine\EventClauses;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Completion;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\EventStatus;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(EventClauses::class)]
#[Medium]
final class EventClausesTest extends TestCase
{
    public function testWriteWritesTheClausesInGrammarOrder(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql910));
        (new EventClauses())->write($out, Completion::NotPreserve, EventStatus::DisableOnReplica, new Text('c'));

        self::assertSame("ON COMPLETION NOT PRESERVE DISABLE ON REPLICA COMMENT 'c'", (new Lexical())->join($out->pieces()));
    }

    public function testWriteKeepsEachStatusSpellingAndOmitsAbsentClauses(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql910));
        (new EventClauses())->write($out, Completion::Preserve, EventStatus::DisableOnSlave, null);
        (new EventClauses())->write($out, null, EventStatus::Enable, null);
        (new EventClauses())->write($out, null, EventStatus::Disable, null);
        (new EventClauses())->write($out, null, null, null);

        self::assertSame('ON COMPLETION PRESERVE DISABLE ON SLAVE ENABLE DISABLE', (new Lexical())->join($out->pieces()));
    }

    public function testWriteRendersTheClausesOfCreateAndAlterEvent(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame("CREATE EVENT db.e ON SCHEDULE EVERY 1 DAY ON COMPLETION NOT PRESERVE DISABLE ON REPLICA COMMENT 'c' DO SELECT 1", $semantics->analyze("create event db.e on schedule every 1 day on completion not preserve disable on replica comment 'c' do select 1")->toString());
        self::assertSame("ALTER EVENT e ON COMPLETION PRESERVE DISABLE ON SLAVE COMMENT 'x'", $semantics->analyze("alter event e on completion preserve disable on slave comment 'x'")->toString());
        self::assertSame('ALTER EVENT e ENABLE', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('alter event e enable')->toString());
    }
}
