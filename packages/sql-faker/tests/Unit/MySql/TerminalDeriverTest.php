<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\Production;
use SqlFaker\Contract\ProductionRule;
use SqlFaker\Contract\Symbol;
use SqlFaker\Contract\TerminationLengths;
use SqlFaker\MySql\TerminalDeriver;

#[CoversNothing]
final class TerminalDeriverTest extends TestCase
{
    public function testDeriveUsesMySqlDefaultStartRule(): void
    {
        $grammar = new Grammar('simple_statement_or_begin', [
            'simple_statement_or_begin' => new ProductionRule('simple_statement_or_begin', [
                new Production([new Symbol('IDENT', false)]),
            ]),
        ]);

        $deriver = new TerminalDeriver(Factory::create());

        self::assertSame(
            ['IDENT'],
            $deriver->derive($grammar, new TerminationLengths(['simple_statement_or_begin' => 1]), new GenerationRequest())->terminals,
        );
    }
}
