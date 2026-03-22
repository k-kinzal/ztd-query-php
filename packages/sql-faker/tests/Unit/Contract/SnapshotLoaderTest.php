<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\Production;
use SqlFaker\Contract\ProductionRule;
use SqlFaker\Contract\SnapshotLoader;
use SqlFaker\Contract\Symbol;

#[CoversNothing]
final class SnapshotLoaderTest extends TestCase
{
    public function testSnapshotLoaderCanExposeConfiguredGrammarSnapshot(): void
    {
        $loader = new class () implements SnapshotLoader {
            public function version(): string
            {
                return 'fixture-1.0';
            }

            public function load(): Grammar
            {
                return new Grammar('stmt', [
                    'stmt' => new ProductionRule('stmt', [
                        new Production([new Symbol('SELECT', false)]),
                    ]),
                ]);
            }
        };

        self::assertSame('fixture-1.0', $loader->version());
        self::assertSame('stmt', $loader->load()->startSymbol);
    }
}
