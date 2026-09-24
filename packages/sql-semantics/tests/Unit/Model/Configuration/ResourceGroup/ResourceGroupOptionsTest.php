<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\ResourceGroup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\ResourceGroup\CpuRange;
use SqlSemantics\Model\Configuration\ResourceGroup\ResourceGroupOptions;
use SqlSemantics\Model\Configuration\ResourceGroup\ThreadCategory;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ResourceGroupOptions::class)]
#[Medium]
final class ResourceGroupOptionsTest extends TestCase
{
    public function testValidateAcceptsAPriorityInsideTheTypeRange(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET RESOURCE GROUP g')->origin;
        ResourceGroupOptions::validate($origin, 'g', [new CpuRange(0, 1)], -20, ThreadCategory::System);
        $this->expectException(InvalidStructure::class);
        ResourceGroupOptions::validate($origin, 'g', [], -1, ThreadCategory::User);
    }

    public function testValidateRejectsANameLongerThan64Characters(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET RESOURCE GROUP g')->origin;
        $this->expectException(InvalidStructure::class);
        ResourceGroupOptions::validate($origin, str_repeat('a', 65));
    }

    public function testValidateRejectsAnotherDialect(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET RESOURCE GROUP g')->origin;
        $this->expectException(InvalidStructure::class);
        ResourceGroupOptions::validate(new Origin('s0', $origin->source, Dialect::PostgreSql), 'g');
    }
}
