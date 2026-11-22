<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\CreateSchema;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(CreateSchema::class)]
#[Small]
final class CreateSchemaTest extends TestCase
{
    public function testResolveUsesTheCatalogSchemaForAnExplicitCaseVariant(): void
    {
        $operation = (new Inspection())->inspect('CREATE TABLE MAIN.data (value)');
        self::assertInstanceOf(CreateTable::class, $operation->statement);

        $schema = (new CreateSchema())->resolve($operation->binding(), $operation->statement->name, $operation->statement->temporary);

        self::assertSame('main', $schema->value);
    }
}
