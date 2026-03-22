<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\Production;
use SqlFaker\Contract\ProductionRule;
use SqlFaker\Contract\Symbol;
use SqlFaker\Contract\TerminationLengths;
use SqlFaker\Generation\FakerRandomSource;
use SqlFaker\PostgreSql\TerminalDeriver;

#[CoversNothing]
final class TerminalDeriverTest extends TestCase
{
    public function testDeriveUsesPostgreSqlDefaultStartRule(): void
    {
        $grammar = new Grammar('stmtmulti', [
            'stmtmulti' => new ProductionRule('stmtmulti', [
                new Production([new Symbol('IDENT', false)]),
            ]),
        ]);

        $deriver = new TerminalDeriver(new FakerRandomSource(Factory::create()));

        self::assertSame(
            ['IDENT'],
            $deriver->derive($grammar, new TerminationLengths(['stmtmulti' => 1]), new GenerationRequest())->terminals,
        );
    }
}
