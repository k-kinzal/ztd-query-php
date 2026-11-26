<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\DropSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\DropSequenceLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(DropSequenceLowering::class)]
final class DropSequenceLoweringTest extends TestCase
{
    public function testLowerResolvesEquivalentSelectorsToTheSameObject(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE SEQUENCE public.ticket', 'CREATE TEMP SEQUENCE ticket']);
        $drop = $semantics->analyze('DROP SEQUENCE ticket, pg_temp.ticket CASCADE', $context);

        self::assertInstanceOf(DropSequence::class, $drop);
        self::assertEquals($drop->targets[0], $drop->targets[1]);
        self::assertSame('DROP SEQUENCE "pg_temp"."ticket", "pg_temp"."ticket" CASCADE', $drop->toString());
    }
}
