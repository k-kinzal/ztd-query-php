<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\CreateSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceOptionLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SequenceOptionLowering::class)]
final class SequenceOptionLoweringTest extends TestCase
{
    public function testLowerPreservesAnIncrementWiderThanSequenceStorage(): void
    {
        $ir = (new Semantics())->analyze('CREATE SEQUENCE ticket AS smallint INCREMENT BY 9223372036854775807 OWNED BY NONE');

        self::assertInstanceOf(CreateSequence::class, $ir);
        self::assertSame('9223372036854775807', $ir->parameters->increment->decimal);
        self::assertSame('32767', $ir->parameters->maximum->decimal);
    }
}
