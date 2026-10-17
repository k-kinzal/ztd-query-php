<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\RepeatedDeleteTarget;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RepeatedDeleteTarget::class)]
#[Small]
final class RepeatedDeleteTargetTest extends TestCase
{
    public function testMessageRetainsThePrefixAndDeclarationIdentity(): void
    {
        $t = new QualifiedName(new Name('t'), new Name('d'));
        $program = new QualifiedName(new Name('p'), new Name('d'));
        $problem = new RepeatedDeleteTarget([$t, $t], $program);
        self::assertSame([$t, $t], $problem->targets);
        self::assertSame($program, $problem->program);
        self::assertSame("Not unique table/alias: 't'", $problem->message());
    }
}
