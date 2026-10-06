<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege\Level;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(ObjectLevel::class)]
#[Medium]
final class ObjectLevelTest extends TestCase
{
    public function testDescribeNamesTheObject(): void
    {
        self::assertSame('object shop.t', (new ObjectLevel(new QualifiedName(new Name('t'), new Name('shop'))))->describe());
    }

    public function testRenderWritesTheName(): void
    {
        self::assertSame('GRANT SELECT ON shop.t TO u', (new Semantics(Dialect::MySql))->analyze('grant select on shop . t to u')->toString());
    }
}
