<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Core\Syntax\Rules;

#[CoversClass(Rules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Formatter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Settings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\MySql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\PostgreSql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\Sqlite\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Facade\DialectFactory::class)]
final class RulesTest extends TestCase
{
    #[TestWith(['joined_table', true])]
    #[TestWith(['joinop', true])]
    #[TestWith(['columnref', false])]
    public function testHasRecognizesConfiguredGrammarOwners(string $name, bool $expected): void
    {
        self::assertSame($expected, (new Rules(['joins' => ['joined_table', 'joinop']], []))->has('joins', $name));
    }
}
