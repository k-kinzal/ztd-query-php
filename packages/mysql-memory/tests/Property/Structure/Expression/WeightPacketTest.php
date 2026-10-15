<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Expression;

use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Choices;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightCast;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightString;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightStringParameters;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Scalar;

/**
 * Large constructor lengths must remain bounded by session state during execution.
 * These inputs are not filtered or retried after construction or execution.
 */
#[CoversNothing]
#[Medium]
final class WeightPacketTest extends TestCase
{
    /**
     * @return iterable<string, array{string, Scalar}>
     */
    public static function providerLengths(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($index = 0; $index < 100; $index++) {
                $choices = new Choices('weight-packet:' . $index);
                $value = $choices->one(['1', '65536', '1073741824', '2147483648', '4294967295', '4294967296', '18446744073709551615', (string) $choices->integer(1, 4294967295)]);
                $subject = new StringLiteral(['abc']);
                $length = new Numeral($value);
                $node = match ($choices->integer(0, 3)) {
                    0 => new WeightString($subject, WeightCast::Char, $length),
                    1 => new WeightString($subject, WeightCast::Binary, $length),
                    2 => new WeightStringParameters($subject, $length, new Numeral('1'), new Numeral('128')),
                    default => new WeightStringParameters($subject, new Numeral('1'), $length, new Numeral('128')),
                };
                yield $release . ':' . $index => [$release, $node];
            }
        }
    }

    /**
     * Large values are legal structures; execution returns bounded SQL values and conditions.
     */
    #[DataProvider('providerLengths')]
    public function testConstructionCannotForceAnUnboundedWeightAllocation(string $release, Scalar $node): void
    {
        $session = (new Instance($release, globals: ['max_allowed_packet' => 4096, 'character_set_connection' => 'utf8mb4', 'collation_connection' => 'utf8mb4_bin']))->connect();
        $session->text = 'unrelated invalid input';
        $outcome = (new Memory())->observe($session, $node);

        self::assertArrayNotHasKey('error', $outcome);
        $rows = $outcome['results'][0]['rows'];
        self::assertIsArray($rows);
        self::assertCount(1, $rows);
        self::assertIsArray($rows[0]);
        $value = $rows[0][0];
        self::assertTrue($value === null || is_string($value));
        self::assertLessThanOrEqual(4096, strlen((string) $value));
        self::assertSame('unrelated invalid input', $session->text);
    }
}
