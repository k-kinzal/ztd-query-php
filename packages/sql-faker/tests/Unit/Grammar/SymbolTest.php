<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Symbol;

#[CoversNothing]
final class SymbolTest extends TestCase
{
    public function testDefinesValueMethod(): void
    {
        $ref = new \ReflectionClass(Symbol::class);
        self::assertTrue($ref->isInterface());
        self::assertTrue($ref->hasMethod('value'));
    }
}
