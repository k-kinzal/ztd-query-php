<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Analyzer;
use SqlSemantics\SemanticsIR;

#[CoversClass(Analyzer::class)]
#[Small]
final class AnalyzerTest extends TestCase
{
    /**
     * @throws AnalysisException
     */
    public function testAnalyzeAcceptsSqlWithoutAnExplicitContext(): void
    {
        $query = self::createStub(SemanticsIR::class);
        $analyzer = self::createStub(Analyzer::class);
        $analyzer->method('analyze')->willReturn($query);

        self::assertSame($query, $analyzer->analyze('SELECT 1'));
    }
}
