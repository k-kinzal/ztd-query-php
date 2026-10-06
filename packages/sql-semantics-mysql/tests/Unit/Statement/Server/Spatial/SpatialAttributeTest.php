<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Spatial;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute;

#[CoversClass(SpatialAttribute::class)]
#[Medium]
final class SpatialAttributeTest extends TestCase
{
    public function testRenderWritesTheOrganizationIdentifier(): void
    {
        self::assertSame("CREATE SPATIAL REFERENCE SYSTEM 9 NAME 'n' DEFINITION 'd' ORGANIZATION 'o' IDENTIFIED BY 9 DESCRIPTION 'x'", (new Semantics(Dialect::MySql))->analyze("create spatial reference system 9 name 'n' definition 'd' organization 'o' identified by 9 description 'x'")->toString());
    }
}
