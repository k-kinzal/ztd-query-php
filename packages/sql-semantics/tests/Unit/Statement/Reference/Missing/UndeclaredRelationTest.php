<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference\Missing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;

#[CoversClass(UndeclaredRelation::class)]
#[Small]
final class UndeclaredRelationTest extends TestCase
{
    public function testDescribeNamesAnUnqualifiedRelation(): void
    {
        self::assertSame('the declaration of relation t', (new UndeclaredRelation(new QualifiedName(new Name('t'))))->describe());
    }

    public function testDescribeWritesEveryQualifierTheStatementWrote(): void
    {
        $name = new QualifiedName(new Name('t'), new Name('s'), new Name('c'));

        $missing = new UndeclaredRelation($name);

        self::assertSame('the declaration of relation c.s.t', $missing->describe());
        self::assertSame($name, $missing->name);
    }
}
