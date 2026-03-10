<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\Production;
use SqlFaker\Contract\ProductionRule;
use SqlFaker\Contract\SupportedGrammarBuilder;
use SqlFaker\Contract\Symbol;

#[CoversNothing]
final class SupportedGrammarBuilderTest extends TestCase
{
    public function testSupportedGrammarBuilderCanTransformSnapshotIntoSupportedGrammar(): void
    {
        $snapshot = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Symbol('SELECT', false)]),
            ]),
        ]);

        $builder = new class () implements SupportedGrammarBuilder {
            public function build(Grammar $snapshot): Grammar
            {
                return $snapshot;
            }
        };

        self::assertSame('stmt', $builder->build($snapshot)->startSymbol);
    }
}
