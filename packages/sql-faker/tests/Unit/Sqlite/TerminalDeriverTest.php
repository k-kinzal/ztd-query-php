<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use Faker\Factory;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\Production;
use SqlFaker\Contract\ProductionRule;
use SqlFaker\Contract\Symbol;
use SqlFaker\Contract\TerminationLengths;
use SqlFaker\Generation\FakerRandomSource;
use SqlFaker\Sqlite\TerminalDeriver;

#[CoversNothing]
final class TerminalDeriverTest extends TestCase
{
    public function testDeriveUsesSqliteDefaultStartRule(): void
    {
        $grammar = new Grammar('cmd', [
            'cmd' => new ProductionRule('cmd', [
                new Production([new Symbol('SELECT', false)]),
            ]),
        ]);

        $deriver = new TerminalDeriver(new FakerRandomSource(Factory::create()));

        self::assertSame(
            ['SELECT'],
            $deriver->derive($grammar, new TerminationLengths(['cmd' => 1]), new GenerationRequest())->terminals,
        );
    }

    public function testDeriveIncludesCurrentRuleNameWhenAnEmptyProductionRuleIsReached(): void
    {
        $grammar = new Grammar('cmd', [
            'cmd' => new ProductionRule('cmd', []),
        ]);

        $deriver = new TerminalDeriver(new FakerRandomSource(Factory::create()));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Production rule 'cmd' has no alternatives.");

        $deriver->derive($grammar, new TerminationLengths(['cmd' => 1]), new GenerationRequest());
    }
}
