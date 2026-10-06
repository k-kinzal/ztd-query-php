<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule::class)]
#[Small]
final class QueryMisuseRuleTest extends TestCase
{
    public function testEveryRuleHasAMessage(): void
    {
        self::assertSame('multiple ORDER BY clauses not allowed', \SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule::MultipleOrderBy->value);
        self::assertGreaterThan(30, count(\SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule::cases()));
    }
}
