<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesKeyword;

#[CoversClass(ValuesKeyword::class)]
#[Medium]
final class ValuesKeywordTest extends TestCase
{
    public function testCasesRetainBothGrammarAlternatives(): void
    {
        self::assertSame(['VALUE', 'VALUES'], array_map(static fn (ValuesKeyword $keyword): string => $keyword->value, ValuesKeyword::cases()));
    }
}
