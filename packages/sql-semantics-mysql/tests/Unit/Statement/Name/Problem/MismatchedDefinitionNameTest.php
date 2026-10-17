<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Name\Problem\MismatchedDefinitionName;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(MismatchedDefinitionName::class)]
#[Small]
final class MismatchedDefinitionNameTest extends TestCase
{
    public function testMessageRetainsBothNameRoles(): void
    {
        $name = new ColumnName(new Name('a'), new QualifiedName(new Name('other')));
        $target = new QualifiedName(new Name('t'));
        $problem = new MismatchedDefinitionName($name, $target);
        self::assertSame($name, $problem->name);
        self::assertSame($target, $problem->target);
        self::assertSame('The definition name qualifier does not identify table t.', $problem->message());
    }
}
