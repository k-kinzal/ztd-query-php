<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\NamedIdentity;
use SqlSemantics\Type\Modifier\TextParameter;

#[CoversClass(\SqlSemantics\Ast\Type\ArrayBounds::class)]
#[Medium]
final class ArrayBoundsTest extends TestCase
{
    #[TestWith(['app.measure(\'a[3]\') ARRAY[4]'])]
    #[TestWith(['app.measure(\'a[3]\')[4]'])]
    public function testReadTakesDimensionsOnlyFromTheArrayDeclaration(string $declaration): void
    {
        $builder = new SchemaBuilder(Dialect::PostgreSql);
        $type = $builder->build('CREATE TABLE t(x ' . $declaration . ')')->tables[0]->columns[0]->type;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\ArrayStorage::class, $type->identity);
        self::assertCount(1, $type->identity->dimensions);
        self::assertNotNull($type->identity->dimensions[0]->length);
        self::assertSame('4', $type->identity->dimensions[0]->length->spelling);
        self::assertInstanceOf(NamedIdentity::class, $type->identity->element->identity);
        self::assertInstanceOf(TextParameter::class, $type->identity->element->identity->arguments[0]);
        self::assertSame("'a[3]'", $type->identity->element->identity->arguments[0]->value->text);
        $sql = \SqlSemantics\Serialization\TypeDeclaration::write($type)->toString();
        $rebound = $builder->build('CREATE TABLE t(x ' . $sql . ')')->tables[0]->columns[0]->type;
        self::assertSame($sql, \SqlSemantics\Serialization\TypeDeclaration::write($rebound)->toString());
    }

}
