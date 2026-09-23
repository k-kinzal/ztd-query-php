<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Modifier\IdentifierParameter;

#[CoversClass(\SqlSemantics\Serialization\Type\ModifierSyntax::class)]
#[Medium]
final class ModifierSyntaxTest extends TestCase
{
    #[TestWith(['numeric(\'12\', "2")'])]
    #[TestWith(['numeric(-(-12), 2)'])]
    #[TestWith(['app.money(currency, \'USD\', 12)'])]
    #[TestWith(['bit("3")'])]
    #[TestWith(['bit varying(\'3\')'])]
    #[TestWith(['timetz(\'3\')'])]
    #[TestWith(['timestamptz(3e0)'])]
    #[TestWith(['timetz(3.0)'])]
    #[TestWith(['timestamptz(-3)'])]
    public function testWriteRetainsTypeOperandsAcrossSerialization(string $declaration): void
    {
        $builder = new SchemaBuilder(Dialect::PostgreSql);
        $schema = $builder->build('CREATE TABLE t(x ' . $declaration . ')');
        $type = $schema->tables[0]->columns[0]->type;
        $sql = \SqlSemantics\Serialization\TypeDeclaration::write($type)->toString();
        $rebound = $builder->build('CREATE TABLE t(x ' . $sql . ')')->tables[0]->columns[0]->type;
        self::assertSame($type->identity::class, $rebound->identity::class);
        self::assertSame($type->name, $rebound->name);
        self::assertSame($sql, \SqlSemantics\Serialization\TypeDeclaration::write($rebound)->toString());
    }

    public function testWriteQuotesModifierIdentifiersWithoutTreatingThemAsSql(): void
    {
        $tree = \SqlSemantics\Serialization\Type\ModifierSyntax::write(new IdentifierParameter('x"y'));
        self::assertSame('"x""y"', $tree->toString());
    }

}
