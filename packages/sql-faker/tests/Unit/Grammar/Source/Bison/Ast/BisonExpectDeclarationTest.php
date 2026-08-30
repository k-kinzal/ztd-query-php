<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Source\Bison\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\Bison\Ast\BisonExpectDeclaration;

#[CoversClass(BisonExpectDeclaration::class)]
final class BisonExpectDeclarationTest extends TestCase
{
    public function testCount(): void
    {
        $decl = new BisonExpectDeclaration(37);

        self::assertSame(37, $decl->count);
    }
}
