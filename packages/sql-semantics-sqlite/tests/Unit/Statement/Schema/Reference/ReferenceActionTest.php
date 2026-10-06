<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rendering\Codec;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ReferenceAction;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ReferenceEvent;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ReferenceReaction;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(ReferenceAction::class)]
#[Small]
final class ReferenceActionTest extends TestCase
{
    public function testRenderWritesTheEventAndTheReaction(): void
    {
        $out = new Output(new Codec());
        (new ReferenceAction(ReferenceEvent::Delete, ReferenceReaction::SetNull))->render($out);

        self::assertSame('ON DELETE SET NULL', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesAnInsertEventTheGrammarAccepts(): void
    {
        $out = new Output(new Codec());
        (new ReferenceAction(ReferenceEvent::Insert, ReferenceReaction::NoAction))->render($out);

        self::assertSame('ON INSERT NO ACTION', (new Lexical())->join($out->pieces()));
    }
}
