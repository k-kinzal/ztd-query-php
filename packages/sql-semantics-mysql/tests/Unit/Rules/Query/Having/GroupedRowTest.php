<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Having;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Rules\Query\Having\GroupedRow;
use SqlSemantics\Rendering\Output;

#[CoversClass(GroupedRow::class)]
#[Small]
final class GroupedRowTest extends TestCase
{
    public function testDeriveRelationAnswersARowWithoutColumns(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile(null, null, ParameterStyle::Native), null, [], false));
        $row = new GroupedRow([], [], true);
        $fact = $row->deriveRelation($derivation, $derivation->environment());

        self::assertSame([], $fact->shape->slots);
        self::assertTrue($fact->shape->complete());
        self::assertNull($fact->table);
        self::assertTrue($row->grouped);
        self::assertSame([], $row->undecided);
    }

    public function testRenderWritesNothing(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new GroupedRow([], [], false))->render($out);

        self::assertSame([], $out->pieces());
    }
}
