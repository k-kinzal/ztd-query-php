<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Ast\Type\LabelReader::class)]
#[Medium]
final class LabelReaderTest extends TestCase
{
    /**
     * @param class-string<\SqlSemantics\Type\Identity\Enumeration|\SqlSemantics\Type\Identity\LabelSet> $class
     */
    #[TestWith(['ENUM', \SqlSemantics\Type\Identity\Enumeration::class])]
    #[TestWith(['SET', \SqlSemantics\Type\Identity\LabelSet::class])]
    public function testReadRetainsAllByteLiteralFormsAndEncoding(string $kind, string $class): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(x ' . $kind . '(0x0f, 0b01, X\'FF\', B\'101\', \'a\') UNICODE BINARY)');
        $identity = $schema->tables[0]->columns[0]->type->identity;
        self::assertInstanceOf($class, $identity);
        self::assertSame('ucs2', $identity->characterSet);
        self::assertTrue($identity->binary);
        self::assertSame(['binary', 'bit-string', 'binary', 'bit-string', 'text'], array_map(static fn (Literal $literal): string => $literal->literalKind->value, $identity->labels));
    }

}
