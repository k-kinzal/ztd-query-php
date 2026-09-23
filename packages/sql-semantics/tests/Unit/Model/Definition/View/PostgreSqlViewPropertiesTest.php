<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\View\PostgreSqlViewProperties;
use SqlSemantics\Model\Statement\Definition\CreateViewStatement;
use SqlSemantics\Schema\Storage\Parameter;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PostgreSqlViewProperties::class)]
#[Medium]
final class PostgreSqlViewPropertiesTest extends TestCase
{
    public function testCarriesRecursionAndOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE RECURSIVE VIEW v (n) WITH (security_barrier, check_option = local) AS SELECT 1');
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        self::assertInstanceOf(PostgreSqlViewProperties::class, $statement->properties);
        self::assertTrue($statement->properties->recursive);
        self::assertCount(2, $statement->properties->parameters);
        self::assertContainsOnlyInstancesOf(Parameter::class, $statement->properties->parameters);
        self::assertSame(['security_barrier'], $statement->properties->parameters[0]->name->parts);
    }

    public function testDefaultsDescribeAPlainView(): void
    {
        $properties = new PostgreSqlViewProperties();
        self::assertFalse($properties->recursive);
        self::assertSame([], $properties->parameters);
    }

}
