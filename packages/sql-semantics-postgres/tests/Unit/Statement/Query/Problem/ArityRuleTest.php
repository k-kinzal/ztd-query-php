<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\ArityRule::class)]
#[Small]
final class ArityRuleTest extends TestCase
{
    public function testRulesArePositionalFormats(): void
    {
        self::assertSame('VALUES lists must all be the same length', \SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\ArityRule::ValuesRows->value);
    }
}
