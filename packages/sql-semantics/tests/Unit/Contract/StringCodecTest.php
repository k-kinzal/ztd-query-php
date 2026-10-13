<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Contract\StringCodec;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

#[CoversClass(StringCodec::class)]
#[Medium]
final class StringCodecTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerModes(): iterable
    {
        yield 'backslash escapes' => [''];
        yield 'literal backslashes' => ['NO_BACKSLASH_ESCAPES'];
    }

    #[DataProvider('providerModes')]
    public function testQuoteDecodesToTheSameBytesUnderTheSelectedProfile(string $mode): void
    {
        $bytes = "a\\b'c\0\n日本語";
        $semantics = new Semantics(Dialect::MySql, null, Mode::fromString($mode));
        $codec = Platforms::of('mysql')->codec($semantics->profile());
        self::assertInstanceOf(StringCodec::class, $codec);
        $statement = $semantics->analyze('SELECT ' . $codec->quote($bytes))->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $statement);
        $statement = $statement->query;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(SelectExpression::class, $statement->items[0]);
        self::assertInstanceOf(StringLiteral::class, $statement->items[0]->expression);
        self::assertSame($bytes, $statement->items[0]->expression->value());
    }
}
