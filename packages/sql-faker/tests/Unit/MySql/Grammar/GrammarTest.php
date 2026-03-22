<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Grammar;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\Production;
use SqlFaker\MySql\Grammar\ProductionRule;
use SqlFaker\MySql\Grammar\Terminal;

#[CoversNothing]
final class GrammarTest extends TestCase
{
    public function testConstructor(): void
    {
        $rule = new ProductionRule('start', [new Production([new Terminal('SELECT_SYM')])]);
        $grammar = new Grammar('start', ['start' => $rule]);

        self::assertSame('start', $grammar->startSymbol);
        self::assertSame(['start' => $rule], $grammar->ruleMap);
    }

    public function testConstructorKeyLhsMismatchThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Grammar('start', ['wrong_key' => new ProductionRule('start', [])]);
    }
}
