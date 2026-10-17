<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Expression;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightCast;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightString;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;

/**
 * Positive constructor lengths remain usable with both casts and retained leading zeros.
 */
#[CoversNothing]
#[Medium]
final class WeightLengthTest extends TestCase
{
    /**
     * @return iterable<string, array{string, WeightCast, Numeral}>
     */
    public static function providerLengths(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($index = 0; $index < 20; $index++) {
                $choices = new Choices('weight-length:' . $index);
                yield $release . ':' . $index => [$release, $choices->one(WeightCast::cases()), $choices->one([new Numeral('1'), new Numeral('00001'), new Numeral('2'), new Numeral('A', true), new Numeral('20', true), new Numeral('4294967296'), new Numeral('18446744073709551615')])];
            }
        }
    }

    /**
     * The constructed value executes directly, without a SQL source or an eligibility check.
     */
    #[DataProvider('providerLengths')]
    public function testPositiveLengthsExecuteInTheirDeclaredDomain(string $release, WeightCast $cast, Numeral $length): void
    {
        $node = new WeightString(new NullLiteral(), $cast, $length);
        $result = (new Execution())->run($node, $release);

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
    }

    /**
     * @return iterable<string, array{string, Numeral, int}>
     */
    public static function providerSpellings(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($index = 0; $index < 20; $index++) {
                $choices = new Choices('weight-spelling:' . $index);
                [$spelling, $length] = $choices->one([[new Numeral('00010'), 10], [new Numeral('A', true), 10], [new Numeral('20', true), 32], [new Numeral('0001'), 1], [new Numeral('F', true), 15], [new Numeral('4294967297'), 1]]);
                yield $release . ':' . $index => [$release, $spelling, $length];
            }
        }
    }

    /**
     * The requested binary length governs padding and truncation, independently of token spelling.
     */
    #[DataProvider('providerSpellings')]
    public function testNumericSpellingDoesNotChangeTheResolvedCast(string $release, Numeral $spelling, int $length): void
    {
        $subject = new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['abcdefghij']);
        $node = new WeightString($subject, WeightCast::Binary, $spelling);
        $session = (new \MySqlMemory\Instance($release))->connect();
        $session->text = 'unrelated invalid input';
        $observer = new \Fuzz\Structure\Audit\Memory();
        $result = $observer->observe($session, $node);

        self::assertSame([[str_pad(substr('abcdefghij', 0, $length), $length, "\0")]], $result['results'][0]['rows']);
        self::assertArrayNotHasKey('error', $result);
        self::assertSame('unrelated invalid input', $session->text);
    }
}
