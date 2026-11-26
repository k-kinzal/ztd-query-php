<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\RenameSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\RenameSequenceLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(RenameSequenceLowering::class)]
final class RenameSequenceLoweringTest extends TestCase
{
    public function testLowerKeepsTheTemporarySequenceThatShadowsThePersistentOne(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE SEQUENCE public.ticket', 'CREATE TEMP SEQUENCE ticket']);
        $rename = $semantics->analyze('ALTER SEQUENCE ticket RENAME TO "new.棚"', $context);

        self::assertInstanceOf(RenameSequence::class, $rename);
        self::assertSame('pg_temp', $rename->source->schema?->value);
        self::assertSame('ALTER SEQUENCE "pg_temp"."ticket" RENAME TO "new.棚"', $rename->toString());
    }
}
