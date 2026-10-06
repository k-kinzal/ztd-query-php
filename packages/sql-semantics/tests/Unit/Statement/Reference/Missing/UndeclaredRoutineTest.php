<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference\Missing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;

#[CoversClass(UndeclaredRoutine::class)]
#[Small]
final class UndeclaredRoutineTest extends TestCase
{
    public function testDescribeNamesTheRoutine(): void
    {
        $name = new QualifiedName(new Name('score'));

        $missing = new UndeclaredRoutine($name);

        self::assertSame('the signature of routine score', $missing->describe());
        self::assertSame($name, $missing->name);
    }
}
