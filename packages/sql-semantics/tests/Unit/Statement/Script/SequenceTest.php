<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Script;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Script\Sequence;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Transaction\Begin;
use SqlSemantics\Statement\Transaction\Rollback;

#[CoversClass(Sequence::class)]
#[Small]
final class SequenceTest extends TestCase
{
    public function testToStringKeepsOperationOrderAndBoundaries(): void
    {
        $begin = new Begin();
        $rollback = new Rollback();
        $script = new Sequence($begin, $rollback);
        self::assertSame([$begin, $rollback], $script->operations);
        self::assertSame('BEGIN; ROLLBACK;', $script->toString());
        self::assertTrue((new SemanticGraph())->isSemanticOperation($script));
    }

    public function testToStringRepresentsAnInputWithoutCommands(): void
    {
        $script = new Sequence();
        self::assertSame([], $script->operations);
        self::assertSame(';', $script->toString());
        self::assertTrue((new SemanticGraph())->isSemanticOperation($script));
    }
}
