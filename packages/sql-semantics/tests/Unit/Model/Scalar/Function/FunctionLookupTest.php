<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Function;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Scalar\Function\FunctionLookup;

#[CoversClass(FunctionLookup::class)]
final class FunctionLookupTest extends TestCase
{
    public function testCasesDistinguishGrammarFormsFromNameLookup(): void
    {
        self::assertSame(['grammar', 'name'], array_column(FunctionLookup::cases(), 'value'));
    }
}
