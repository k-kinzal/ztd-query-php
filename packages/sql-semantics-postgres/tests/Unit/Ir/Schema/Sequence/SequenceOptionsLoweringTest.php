<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceOptionsLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SequenceOptionsLowering::class)]
final class SequenceOptionsLoweringTest extends TestCase
{
    public function testLowerTreatsFlagAndExplicitLimitAsOneOption(): void
    {
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A sequence option may be specified only once.');

        (new Semantics())->analyze('CREATE SEQUENCE ticket NO MINVALUE MINVALUE 1');
    }
}
