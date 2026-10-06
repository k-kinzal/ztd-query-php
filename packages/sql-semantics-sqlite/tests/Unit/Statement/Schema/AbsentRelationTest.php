<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AbsentRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(AbsentRelation::class)]
#[Small]
final class AbsentRelationTest extends TestCase
{
    public function testNameIsTheNameTheStatementWrote(): void
    {
        $name = new QualifiedName(new Name('t'), new Name('main'));

        self::assertSame($name, (new AbsentRelation($name))->name);
    }
}
