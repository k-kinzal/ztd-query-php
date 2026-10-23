<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use MySqlMemory\Error\SqlError;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CharsetConversion;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Fake\MemoryClient as Execution;
use Tests\Generator\Choices;

/**
 * Unknown names produce the native diagnostic even when the operand is unresolved.
 */
#[CoversNothing]
#[Medium]
final class CharacterSetProperties extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerSeeds(): iterable
    {
        for ($seed = 0; $seed < 50; $seed++) {
            yield (string) $seed => [(string) $seed];
        }
    }

    /**
     * Deriving a directly constructed conversion reports its unknown character set.
     *
     * @param string $seed The reproducible construction choices
     */
    #[DataProvider('providerSeeds')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesDirectConstructionReportsUnknownCharsetWithoutAnInternalFailure(string $seed): void
    {
        $choices = new Choices('charset-structure:' . $seed);
        $name = $choices->one(['', 'absent_' . $choices->integer(1, 10000)]);
        $operand = $choices->one([new NumberLiteral((string) $choices->integer(0, 100)), new ColumnUse(new Name('missing'))]);
        $expression = new CharsetConversion($operand, new CharsetName(new Name($name)));
        $release = $choices->one(['8.0.44', '8.4.7', '9.1.0']);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1115);
        $this->expectExceptionMessage("Unknown character set: '" . $name . "'");

        (new Execution())->run($expression, $release);
    }

    /**
     * Unsized casts remain expression types while their operands determine the result size.
     */
    #[DataProvider('providerSeeds')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesUnsizedStringCastsExecuteFromConstructedSemanticCatalogue(string $seed): void
    {
        $choices = new Choices('unsized-string:' . $seed);
        $value = 'value_' . $choices->integer(0, 100000);
        $kind = $choices->one([\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Binary, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Char, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::NationalChar]);
        $cast = new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast(new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral([$value]), new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget($kind));
        $expression = new CharsetConversion($cast, new CharsetName(new Name($choices->one(['latin1', 'utf8mb4', 'binary']))));
        $release = $choices->one(['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0']);
        $reply = (new Execution())->run($expression, $release);

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $reply);
        self::assertSame([[$value]], $reply->rows);
    }

}
