<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Transaction\Xa\RecoveryColumn;
use SqlSemantics\Model\Transaction\Xa\RecoveryEncoding;
use SqlSemantics\Model\Transaction\Xa\RecoveryField;

#[CoversClass(RecoveryColumn::class)]
#[Medium]
final class RecoveryColumnTest extends TestCase
{
    public function testInputsDoNotInventAScalarInputForServerProducedData(): void
    {
        $source = (new DialectParser(Dialect::MySql))->parse('XA RECOVER');
        $column = new RecoveryColumn($source, 's0', RecoveryField::Data, RecoveryEncoding::Bytes);
        self::assertSame([], $column->inputs());
        self::assertSame([], $column->lineage());
        self::assertSame(\SqlSemantics\Model\ExpressionKind::XaRecovery, $column->kind);
    }

    public function testSpellingIdentifiesTheProducedField(): void
    {
        $source = (new DialectParser(Dialect::MySql))->parse('XA RECOVER');
        $column = new RecoveryColumn($source, 's0', RecoveryField::Format, RecoveryEncoding::Bytes);
        self::assertSame('formatID', $column->spelling());
    }

    public function testWithFactsRetainsDerivedMetadata(): void
    {
        $source = (new DialectParser(Dialect::MySql))->parse('XA RECOVER');
        $column = new RecoveryColumn($source, 's0', RecoveryField::Format, RecoveryEncoding::Bytes);
        $copy = $column->withFacts($column->facts);
        self::assertSame($column->field, $copy->field);
        self::assertNotSame($column, $copy);
    }

    public function testWithFactsRejectsContradictoryMetadata(): void
    {
        $source = (new DialectParser(Dialect::MySql))->parse('XA RECOVER');
        $column = new RecoveryColumn($source, 's0', RecoveryField::Format, RecoveryEncoding::Bytes);
        $facts = new \SqlSemantics\Model\Scalar\ExpressionFacts($column->type, \SqlSemantics\Type\Nullability::MaybeNull, []);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $column->withFacts($facts);
    }

    public function testCannotBeSerializedAsAnIndependentScalarExpression(): void
    {
        $source = (new DialectParser(Dialect::MySql))->parse('XA RECOVER');
        $column = new RecoveryColumn($source, 's0', RecoveryField::Format, RecoveryEncoding::Bytes);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        \SqlSemantics\Serialization\Expressions::write($column);
    }
}
