<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\AutoIncrementColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(AutoIncrementColumn::class)]
#[Medium]
final class AutoIncrementColumnTest extends TestCase
{
    public function testMatchesRecognizesSerialAndAnExplicitAttribute(): void
    {
        $statement = (new Inspection())->inspect('CREATE TABLE app.t (id SERIAL, n INT, a INT AUTO_INCREMENT)')->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        [$serial, $ordinary, $attribute] = $statement->elements;
        self::assertInstanceOf(ColumnDefinition::class, $serial);
        self::assertInstanceOf(ColumnDefinition::class, $ordinary);
        self::assertInstanceOf(ColumnDefinition::class, $attribute);
        $flags = new AutoIncrementColumn();

        self::assertTrue($flags->matches($serial->specification));
        self::assertFalse($flags->matches($ordinary->specification));
        self::assertTrue($flags->matches($attribute->specification));
    }
}
