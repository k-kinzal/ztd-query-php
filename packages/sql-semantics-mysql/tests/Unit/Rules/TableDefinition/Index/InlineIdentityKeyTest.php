<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\InlineIdentityKey;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(InlineIdentityKey::class)]
#[Medium]
final class InlineIdentityKeyTest extends TestCase
{
    public function testQualifiesAnOrdinaryInlineUniqueField(): void
    {
        $result = (new Inspection())->inspect('CREATE TABLE app.t (id INT NOT NULL UNIQUE, ordinary INT)');
        self::assertInstanceOf(CreateTable::class, $result->statement);
        $unique = $result->statement->elements[0];
        $ordinary = $result->statement->elements[1];
        self::assertInstanceOf(ColumnDefinition::class, $unique);
        self::assertInstanceOf(ColumnDefinition::class, $ordinary);

        $key = new InlineIdentityKey();
        self::assertTrue($key->qualifies($unique));
        self::assertFalse($key->qualifies($ordinary));
    }

    public function testKindsKeepsBothPhysicalKeysFromOneColumn(): void
    {
        $result = (new Inspection())->inspect('CREATE TABLE app.t (id INT PRIMARY KEY UNIQUE)');
        self::assertInstanceOf(CreateTable::class, $result->statement);
        $column = $result->statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $column);

        self::assertSame([IndexKind::Primary, IndexKind::Unique], (new InlineIdentityKey())->kinds($column));
    }
}
