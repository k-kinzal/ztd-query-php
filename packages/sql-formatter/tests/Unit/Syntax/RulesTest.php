<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Syntax\Rules;

#[CoversClass(Rules::class)]
final class RulesTest extends TestCase
{
    #[TestWith(['joined_table', true])]
    #[TestWith(['joinop', true])]
    #[TestWith(['columnref', false])]
    public function testIsJoinRecognizesGrammarOwners(string $name, bool $expected): void
    {
        self::assertSame($expected, Rules::isJoin($name));
    }
}
