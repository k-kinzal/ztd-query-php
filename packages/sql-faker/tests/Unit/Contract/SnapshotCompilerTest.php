<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\Production;
use SqlFaker\Contract\ProductionRule;
use SqlFaker\Contract\SnapshotCompiler;
use SqlFaker\Contract\Symbol;

#[CoversNothing]
final class SnapshotCompilerTest extends TestCase
{
    public function testSnapshotCompilerCanBuildContractGrammarsFromSourceText(): void
    {
        $compiler = new class () implements SnapshotCompiler {
            public function compile(string $source): Grammar
            {
                return new Grammar($source, [
                    $source => new ProductionRule($source, [
                        new Production([new Symbol('SELECT', false)]),
                    ]),
                ]);
            }
        };

        self::assertSame('stmt', $compiler->compile('stmt')->startSymbol);
    }
}
