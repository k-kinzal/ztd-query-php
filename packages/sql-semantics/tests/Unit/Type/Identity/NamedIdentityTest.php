<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\NamedIdentity;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;
use SqlSemantics\Type\Modifier\IdentifierParameter;
use SqlSemantics\Type\Modifier\NegatedParameter;
use SqlSemantics\Type\Modifier\TextParameter;

#[CoversClass(NamedIdentity::class)]
#[Medium]
final class NamedIdentityTest extends TestCase
{
    public function testNameKeepsTheQualifiedTypeIdentityAndEveryInput(): void
    {
        $type = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(x app.measure(currency, \'USD\', 12, -3))')->tables[0]->columns[0]->type;
        self::assertInstanceOf(NamedIdentity::class, $type->identity);
        self::assertSame('app.measure', $type->identity->name());
        self::assertSame(['app', 'measure'], $type->identity->reference->parts);
        self::assertCount(4, $type->identity->arguments);
        self::assertInstanceOf(IdentifierParameter::class, $type->identity->arguments[0]);
        self::assertInstanceOf(TextParameter::class, $type->identity->arguments[1]);
        self::assertInstanceOf(NumericParameter::class, $type->identity->arguments[2]);
        self::assertInstanceOf(NegatedParameter::class, $type->identity->arguments[3]);
    }

}
