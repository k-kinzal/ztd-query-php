<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\Production;
use SqlFaker\Contract\ProductionRule;
use SqlFaker\Contract\Runtime;
use SqlFaker\Contract\Symbol;

#[CoversClass(GenerationRequest::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Production::class)]
#[UsesClass(Symbol::class)]
final class RuntimeTest extends TestCase
{
    public function testRuntimeImplementationsCanExposeAlgorithmContract(): void
    {
        $runtime = new class () implements Runtime {
            public function snapshot(): Grammar
            {
                return new Grammar('stmt', [
                    'stmt' => new ProductionRule('stmt', [
                        new Production([new Symbol('SELECT', false)]),
                    ]),
                ]);
            }

            public function supportedGrammar(): Grammar
            {
                return $this->snapshot();
            }

            public function generate(GenerationRequest $request): string
            {
                return sprintf('%s:%d', $request->startRule ?? 'stmt', $request->seed ?? 0);
            }
        };

        self::assertSame('stmt', $runtime->snapshot()->startSymbol);
        self::assertSame('stmt:9', $runtime->generate(new GenerationRequest(seed: 9)));
    }
}
