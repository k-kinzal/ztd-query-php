<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\CreateSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\CreateSequenceLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePersistence;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(CreateSequenceLowering::class)]
final class CreateSequenceLoweringTest extends TestCase
{
    public function testLowerBindsTheTemporaryAlias(): void
    {
        $value = (new Semantics())->analyze('CREATE SEQUENCE pg_temp."name.棚"');

        self::assertInstanceOf(CreateSequence::class, $value);
        self::assertSame(SequencePersistence::Temporary, $value->persistence);
        self::assertSame('"pg_temp"."name.棚"', $value->name->quoted());
    }
}
