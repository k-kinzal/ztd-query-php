<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\StatementGenerator;

#[CoversNothing]
final class StatementGeneratorTest extends TestCase
{
    public function testStatementGeneratorCanRenderFromGenerationRequest(): void
    {
        $generator = new class () implements StatementGenerator {
            public function generate(GenerationRequest $request): string
            {
                return sprintf('%s:%d', $request->startRule ?? 'stmt', $request->seed ?? 0);
            }
        };

        self::assertSame('stmt:9', $generator->generate(new GenerationRequest(seed: 9)));
    }
}
