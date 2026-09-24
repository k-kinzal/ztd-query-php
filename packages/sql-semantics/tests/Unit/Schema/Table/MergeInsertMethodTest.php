<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Table\MergeInsertMethod;
use SqlSemantics\Schema\Table\MySqlProperties;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MergeInsertMethod::class)]
#[Medium]
final class MergeInsertMethodTest extends TestCase
{
    public function testIsReadFromTheDeclaration(): void
    {
        $properties = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT) INSERT_METHOD = NO')->tables[0]->properties;
        self::assertInstanceOf(MySqlProperties::class, $properties);
        self::assertSame(MergeInsertMethod::No, $properties->insertMethod);
    }
}
