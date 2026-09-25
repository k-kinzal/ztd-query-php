<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\Schema\Storage\ImpliedSetting;
use SqlSemantics\Schema\Storage\Parameter;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Parameter::class)]
#[Medium]
final class ParameterTest extends TestCase
{
    public function testCarriesTheDeclaredNameAndLiteralValue(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t (id integer) WITH (fillfactor = 70)');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertInstanceOf(PostgreSqlProperties::class, $statement->definition->table->properties);
        $parameter = $statement->definition->table->properties->storageParameters[0];
        self::assertSame(['fillfactor'], $parameter->name->parts);
        self::assertInstanceOf(Literal::class, $parameter->value);
    }

    public function testAnOptionNamedWithoutAValueIsEnabled(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t (id integer) WITH (autovacuum_enabled)');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertInstanceOf(PostgreSqlProperties::class, $statement->definition->table->properties);
        $parameter = $statement->definition->table->properties->storageParameters[0];
        self::assertSame(ImpliedSetting::Enabled, $parameter->value);
        self::assertSame('CREATE TABLE "public"."t"("id" integer) WITH ("autovacuum_enabled")', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

}
