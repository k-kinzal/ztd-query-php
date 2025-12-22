<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Ast;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Ast\BisonDeclaration;

#[CoversNothing]
final class BisonDeclarationTest extends TestCase
{
    public function testIsInterface(): void
    {
        $ref = new \ReflectionClass(BisonDeclaration::class);
        self::assertTrue($ref->isInterface());
    }
}
