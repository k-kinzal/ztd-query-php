<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Variable\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Definition;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Reach;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\ValueShape;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Writability;

#[CoversClass(Definition::class)]
#[Small]
final class DefinitionTest extends TestCase
{
    public function testDefinitionHoldsWhatItIsGiven(): void
    {
        $definition = new Definition('autocommit', Reach::Both, ValueShape::Boolean, 'ON', Writability::Writable, null, null, Domain::integer());

        self::assertSame(['autocommit', Reach::Both, ValueShape::Boolean, 'ON', Writability::Writable], [$definition->name, $definition->reach, $definition->shape, $definition->default, $definition->writability]);
    }
}
