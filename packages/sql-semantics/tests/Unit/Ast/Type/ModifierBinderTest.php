<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\NamedIdentity;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;
use SqlSemantics\Type\Identity\Numeric\NumericStorage;
use SqlSemantics\Type\Modifier\IdentifierParameter;
use SqlSemantics\Type\Modifier\NegatedParameter;
use SqlSemantics\Type\Modifier\TextParameter;

#[CoversClass(\SqlSemantics\Ast\Type\ModifierBinder::class)]
#[Medium]
final class ModifierBinderTest extends TestCase
{
    public function testReadKeepsTextAndIdentifierModifiersSeparate(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n NUMERIC(\'12\', "2"))');
        $type = $schema->tables[0]->columns[0]->type->identity;
        self::assertInstanceOf(NumericStorage::class, $type);
        self::assertInstanceOf(TextParameter::class, $type->precision);
        self::assertSame("'12'", $type->precision->value->text);
        self::assertInstanceOf(IdentifierParameter::class, $type->scale);
        self::assertSame('2', $type->scale->name);
    }

    public function testReadKeepsNestedNumericNegationWithoutComputingAValue(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n NUMERIC(-(-12)))');
        $type = $schema->tables[0]->columns[0]->type->identity;
        self::assertInstanceOf(NumericStorage::class, $type);
        self::assertInstanceOf(NegatedParameter::class, $type->precision);
        self::assertInstanceOf(NegatedParameter::class, $type->precision->operand);
        self::assertInstanceOf(NumericParameter::class, $type->precision->operand->operand);
        self::assertSame('12', $type->precision->operand->operand->spelling);
    }

    public function testParametersDoNotResolveModifierIdentifiersAsColumns(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(currency integer)'));
        $statement = $binder->bind('SELECT app.measure(currency, \'USD\', 12) \'x\' FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $expression = $statement->outputs[0]->expression;
        self::assertSame([], $expression->lineage());
        self::assertInstanceOf(NamedIdentity::class, $expression->type->identity);
        self::assertCount(3, $expression->type->identity->arguments);
        self::assertInstanceOf(IdentifierParameter::class, $expression->type->identity->arguments[0]);
        self::assertSame('currency', $expression->type->identity->arguments[0]->name);
        self::assertInstanceOf(TextParameter::class, $expression->type->identity->arguments[1]);
        self::assertInstanceOf(NumericParameter::class, $expression->type->identity->arguments[2]);
        self::assertSame([], $statement->diagnostics);
    }

    #[TestWith(['SELECT app.measure(x => 12) \'a\''])]
    #[TestWith(['SELECT app.measure(1 + 2) \'a\''])]
    #[TestWith(['SELECT app.measure(t.width) \'a\''])]
    #[TestWith(['SELECT app.measure(a ORDER BY x) \'a\''])]
    #[TestWith(['SELECT app.measure(TRUE) \'a\''])]
    public function testParametersRejectFunctionArgumentsAndGeneralExpressions(string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('type modifiers');
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

}
