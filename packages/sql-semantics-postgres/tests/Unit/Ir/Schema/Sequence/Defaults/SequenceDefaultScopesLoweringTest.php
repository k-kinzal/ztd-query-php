<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Defaults;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults\SequenceDefaultScopesLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SequenceDefaultScopesLowering::class)]
final class SequenceDefaultScopesLoweringTest extends TestCase
{
    public function testLowerRequiresTheSelectedSchemaDeclaration(): void
    {
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('Default sequence privileges require a declared schema.');
        (new Semantics())->analyze('ALTER DEFAULT PRIVILEGES IN SCHEMA unbound_space GRANT USAGE ON SEQUENCES TO PUBLIC');
    }
}
