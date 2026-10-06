<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnostic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(InvalidConstruction::class)]
#[Small]
final class InvalidConstructionTest extends TestCase
{
    public function testAConstructorReportsAnInputOutsideItsDomain(): void
    {
        $this->expectExceptionMessage('A catalog qualifier requires a schema qualifier.');

        new QualifiedName(new Name('t'), null, new Name('c'));
    }

    public function testTheMessageIsKept(): void
    {
        self::assertSame('A node occurs twice.', (new InvalidConstruction('A node occurs twice.'))->getMessage());
    }
}
