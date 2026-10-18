<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Lowerer\Declaration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DiagnosticTest extends TestCase
{
    public function testCapturedContract(): void
    {
        $source = new \Deriver\Deriver\Sources\SourceRef('s', 'a.php', 0, 1);
        $diagnostic = new \Deriver\Deriver\Lowerer\Declaration\Diagnostic('INCOMPLETE_SOURCE', $source, 'missing-parent');
        self::assertSame($source, $diagnostic->at);
        self::assertSame('missing-parent', $diagnostic->operation);
    }
}
