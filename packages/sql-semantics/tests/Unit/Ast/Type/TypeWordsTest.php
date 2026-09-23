<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Dialect;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;
use SqlSemantics\Type\Modifier\IdentifierParameter;
use SqlSemantics\Type\Modifier\TextParameter;

#[CoversClass(\SqlSemantics\Ast\Type\TypeWords::class)]
#[Medium]
final class TypeWordsTest extends TestCase
{
    public function testReadKeepsMySqlEncodingSeparateFromItsLength(): void
    {
        $source = (new DialectParser(Dialect::MySql))->parse('CREATE TABLE t(x VARCHAR(12) CHARACTER SET utf8mb4 BINARY)');
        $type = Tree::outer($source, ['type'])[0];
        $words = \SqlSemantics\Ast\Type\TypeWords::read($type, Dialect::MySql);
        self::assertSame(['VARCHAR'], $words->words);
        self::assertInstanceOf(NumericParameter::class, $words->parameters[0]);
        self::assertSame('12', $words->parameters[0]->spelling);
        self::assertSame('utf8mb4', $words->characterSet);
        self::assertTrue($words->binary);
    }

    public function testReadRetainsTheOperandCategoriesOfParameterizedTypes(): void
    {
        $source = (new DialectParser(Dialect::PostgreSql))->parse('CREATE TABLE t(x NUMERIC(\'12\', "2"))');
        $words = \SqlSemantics\Ast\Type\TypeWords::read(Tree::outer($source, ['Typename'])[0], Dialect::PostgreSql);
        self::assertSame(['NUMERIC'], $words->words);
        self::assertInstanceOf(TextParameter::class, $words->parameters[0]);
        self::assertInstanceOf(IdentifierParameter::class, $words->parameters[1]);
    }

}
