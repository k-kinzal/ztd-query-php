<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Literal;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;

/**
 * Direct TIMESTAMP structures preserve independently generated calendar values without SQL input.
 */
#[CoversNothing]
#[Medium]
final class TimestampDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerMoments(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 40; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * The same instant has delimited, compact or ISO-separated representation.
     * The expected calendar fields are computed before choosing their representation.
     */
    #[DataProvider('providerMoments')]
    public function testTimestampStructuresExecuteWithoutSourceText(string $release, int $seed): void
    {
        $choices = new Choices('timestamp-domain:' . $seed);
        $year = $choices->integer(2000, 2069);
        $month = $choices->integer(1, 12);
        $day = $choices->integer(1, 28);
        $hour = $choices->integer(0, 23);
        $minute = $choices->integer(0, 59);
        $second = $choices->integer(0, 59);
        $fraction = $choices->one(['', '.5', '.123456']);
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $clock = sprintf('%02d:%02d:%02d', $hour, $minute, $second);
        $text = $choices->one([$date . ' ' . $clock, $date . 'T' . $clock, sprintf('%02d%02d%02d%02d%02d%02d', $year - 2000, $month, $day, $hour, $minute, $second), sprintf('%04d%02d%02d%02d%02d%02d', $year, $month, $day, $hour, $minute, $second)]) . $fraction;
        $literal = new TemporalLiteral(TemporalForm::Timestamp, $text);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $session->variables->session['sql_mode'] = $choices->one(['', 'ALLOW_INVALID_DATES', 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE']);
        $reply = (new Execution())->runIn($session, $literal);

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[$date . ' ' . $clock . $fraction]], $reply->rows);
    }
}
