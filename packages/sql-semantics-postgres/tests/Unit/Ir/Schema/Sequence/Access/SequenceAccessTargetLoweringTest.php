<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceAccessTargetLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SequenceAccessTargetLowering::class)]
final class SequenceAccessTargetLoweringTest extends TestCase
{
    public function testLowerRejectsAnUnresolvedSchemaIdentity(): void
    {
        $semantics = new Semantics();
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('Sequence privileges require a declared schema.');
        $semantics->analyze('GRANT USAGE ON ALL SEQUENCES IN SCHEMA missing_schema TO PUBLIC');
    }
}
