<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Prepared\PrepareTextStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PrepareTextStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class PrepareTextStatementTest extends TestCase
{
    public function testWithOriginPreservesRequiredOperandsAndSerialization(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('PREPARE s FROM @sql', strict: false);
        self::assertInstanceOf(PrepareTextStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedVariableReference::class, $statement->sql);
        self::assertSame('sql', $statement->sql->name);
        $changed = $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('new-scope', $statement->source, Dialect::MySql));
        self::assertSame('new-scope', $changed->scopeId);
        self::assertNotSame($statement, $changed);
        self::assertSame('PREPARE `s` FROM @`sql`', $changed->toString());
        self::assertSame($changed->toString(), $binder->bind($changed->toString(), strict: false)->toString());
    }

    public function testRetainsDefinedVariableWithoutAValue(): void
    {
        $type = new \SqlSemantics\Type\TypeDescriptor(Dialect::MySql, \SqlSemantics\Type\Identity\BuiltinIdentity::Text);
        $variable = new \SqlSemantics\Schema\VariableDefinition('sql', \SqlSemantics\Schema\VariableScope::User, $type);
        $schema = (new SchemaBuilder(Dialect::MySql))->build()->withVariables($variable);
        $statement = (new Binder($schema))->bind('PREPARE s FROM @sql');
        self::assertInstanceOf(PrepareTextStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\VariableReference::class, $statement->sql);
        self::assertSame($variable, $statement->sql->definition);
        self::assertFalse(property_exists($statement->sql, 'value'));
    }

    public function testDoesNotParseTheValueOfItsSqlOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("PREPARE s FROM 'runtime input'");
        self::assertInstanceOf(PrepareTextStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $statement->sql);
        self::assertSame("'runtime input'", $statement->sql->text);
    }
}
