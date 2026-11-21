<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisContext;
use SqlSemantics\AnalysisException;
use SqlSemantics\IrAnalyzer;
use SqlSemantics\SemanticsIR;

#[CoversClass(IrAnalyzer::class)]
#[Small]
final class IrAnalyzerTest extends TestCase
{
    /**
     * @throws AnalysisException
     */
    public function testAnalyzeExposesTheCompleteContractToCallers(): void
    {
        $analyzer = new class () implements IrAnalyzer {
            public function analyze(string $sql, ?AnalysisContext $context = null): SemanticsIR
            {
                return new class ($sql) implements SemanticsIR {
                    public function __construct(private readonly string $sql)
                    {
                    }

                    public function profileId(): string
                    {
                        return 'test-profile';
                    }

                    public function toString(): string
                    {
                        return $this->sql;
                    }
                };
            }
        };
        $profile = static fn (IrAnalyzer $analyzer): string => $analyzer->analyze('SELECT 1')->profileId();

        self::assertSame('test-profile', $profile($analyzer));
    }
}
