<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Configuration\Account\GeneratedPasswordColumn;
use SqlSemantics\Model\Configuration\Account\GeneratedPasswordField;

#[CoversClass(GeneratedPasswordColumn::class)]
#[Medium]
final class GeneratedPasswordColumnTest extends TestCase
{
    public function testInputsDoNotInventAScalarInputForServerProducedData(): void
    {
        $source = (new DialectParser(Dialect::MySql))->parse('SET PASSWORD TO RANDOM');
        $column = new GeneratedPasswordColumn($source, 's0', CurrentAccount::Authenticated, GeneratedPasswordField::Password);
        self::assertSame([], $column->inputs());
        self::assertSame([], $column->lineage());
        self::assertSame(\SqlSemantics\Model\ExpressionKind::GeneratedPassword, $column->kind);
    }

    public function testSpellingIdentifiesTheProducedField(): void
    {
        $source = (new DialectParser(Dialect::MySql))->parse('SET PASSWORD TO RANDOM');
        $column = new GeneratedPasswordColumn($source, 's0', CurrentAccount::Authenticated, GeneratedPasswordField::AuthenticationFactor);
        self::assertSame('auth_factor', $column->spelling());
    }

    public function testWithFactsRetainsDerivedMetadata(): void
    {
        $source = (new DialectParser(Dialect::MySql))->parse('SET PASSWORD TO RANDOM');
        $column = new GeneratedPasswordColumn($source, 's0', CurrentAccount::Authenticated, GeneratedPasswordField::AuthenticationFactor);
        $copy = $column->withFacts($column->facts);
        self::assertSame($column->field, $copy->field);
        self::assertNotSame($column, $copy);
    }

    public function testWithFactsRejectsContradictoryMetadata(): void
    {
        $source = (new DialectParser(Dialect::MySql))->parse('SET PASSWORD TO RANDOM');
        $column = new GeneratedPasswordColumn($source, 's0', CurrentAccount::Authenticated, GeneratedPasswordField::AuthenticationFactor);
        $facts = new \SqlSemantics\Model\Scalar\ExpressionFacts($column->type, \SqlSemantics\Type\Nullability::MaybeNull, []);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $column->withFacts($facts);
    }

    public function testCannotBeSerializedAsAnIndependentScalarExpression(): void
    {
        $source = (new DialectParser(Dialect::MySql))->parse('SET PASSWORD TO RANDOM');
        $column = new GeneratedPasswordColumn($source, 's0', CurrentAccount::Authenticated, GeneratedPasswordField::AuthenticationFactor);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        \SqlSemantics\Serialization\Expressions::write($column);
    }
}
