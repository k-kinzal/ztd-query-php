<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Binding\TriggerRaiseLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Program\TriggerRaise;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Program\TriggerRaisePolicy;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\RowImage;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(TriggerRaiseLowering::class)]
#[Small]
final class TriggerRaiseLoweringTest extends TestCase
{
    public function testLowerResolvesMessageAndGuardToSeparateImageSlots(): void
    {
        $semantics = new Semantics();
        $declarations = $semantics->context(['CREATE TABLE watched (id, message, guard)']);
        $result = $semantics->analyze('CREATE TRIGGER tr UPDATE ON watched BEGIN SELECT RAISE(ABORT, NEW.message) WHERE OLD.guard IS NOT NULL; END', $declarations);

        self::assertInstanceOf(CreateTrigger::class, $result);
        $step = $result->program[0];
        self::assertInstanceOf(TriggerRaise::class, $step);
        self::assertSame(TriggerRaisePolicy::Abort, $step->policy);
        self::assertInstanceOf(TriggerField::class, $step->message);
        self::assertSame(RowImage::New, $step->message->image);
        self::assertSame(1, $step->message->column);
        self::assertNotNull($step->where);
        self::assertFalse($step->where->isNull);
        self::assertSame(RowImage::Old, $step->where->field->image);
        self::assertSame(2, $step->where->field->column);
    }
}
