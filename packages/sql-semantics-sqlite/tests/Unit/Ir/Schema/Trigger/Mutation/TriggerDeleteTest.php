<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerDelete;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerEquality;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\RowImage;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(TriggerDelete::class)]
#[Small]
final class TriggerDeleteTest extends TestCase
{
    public function testPublishesTheExactLocalAndOldImageCondition(): void
    {
        $semantics = new Semantics();
        $declarations = $semantics->context(['CREATE TABLE watched (id, marker)', 'CREATE TABLE audit (marker, id)']);
        $result = $semantics->analyze('CREATE TRIGGER tr DELETE ON watched BEGIN DELETE FROM audit WHERE OLD.marker = audit.marker; END', $declarations);

        self::assertInstanceOf(CreateTrigger::class, $result);
        $step = $result->program[0];
        self::assertInstanceOf(TriggerDelete::class, $step);
        self::assertInstanceOf(TriggerEquality::class, $step->where);
        self::assertSame(0, $step->where->target->column);
        self::assertSame(1, $step->where->image->column);
        self::assertSame(RowImage::Old, $step->where->image->image);
        self::assertFalse($step->where->targetFirst);
    }
}
