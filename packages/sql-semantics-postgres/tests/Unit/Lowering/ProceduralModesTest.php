<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\ProceduralModes;

#[CoversClass(ProceduralModes::class)]
#[Small]
final class ProceduralModesTest extends TestCase
{
    public function testRejectReportsAParserModeAsOutsideSqlText(): void
    {
        $this->expectException(AnalysisException::class);
        (new ProceduralModes())->reject(new Form(new \SqlParser\Parser\Node('parse_toplevel', 1, []), 'parse_toplevel: MODE_TYPE_NAME Typename'));
    }

    public function testRejectReportsAnyOtherProductionAsAGap(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: parse_toplevel: other');
        (new ProceduralModes())->reject(new Form(new \SqlParser\Parser\Node('parse_toplevel', 9, []), 'parse_toplevel: other'));
    }
}
