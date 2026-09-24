<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Extensibility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Extensibility\Extensions;

#[CoversClass(Extensions::class)]
#[Medium]
final class ExtensionsTest extends TestCase
{
    #[TestWith(['CREATE EXTENSION e VERSION "1"', 'CREATE EXTENSION "e" VERSION \'1\''])]
    #[TestWith(['ALTER EXTENSION e UPDATE TO \'2\'', 'ALTER EXTENSION "e" UPDATE TO \'2\''])]
    #[TestWith(['ALTER EXTENSION e DROP FUNCTION f()', 'ALTER EXTENSION "e" DROP FUNCTION "f"()'])]
    #[TestWith(['CREATE LANGUAGE l HANDLER h VALIDATOR v', 'CREATE LANGUAGE "l" HANDLER "h" VALIDATOR "v"'])]
    #[TestWith(['CREATE ACCESS METHOD m TYPE TABLE HANDLER h', 'CREATE ACCESS METHOD "m" TYPE TABLE HANDLER "h"'])]
    public function testWriteSpellsEachFormFromItsOperands(string $sql, string $expected): void
    {
        $tree = Extensions::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql));
        self::assertNotNull($tree);
        self::assertSame($expected, $tree->toString());
    }

    public function testWriteIgnoresOtherStatements(): void
    {
        self::assertNull(Extensions::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }

    public function testNameQuotesEachComponent(): void
    {
        self::assertSame('"a"."b""c"', Extensions::name(new QualifiedName(['a', 'b"c']))->toString());
    }

    public function testTextEncodesAStringConstant(): void
    {
        self::assertSame("'it''s'", Extensions::text("it's")->toString());
    }
}
