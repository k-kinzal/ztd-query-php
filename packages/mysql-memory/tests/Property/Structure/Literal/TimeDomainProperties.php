<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Literal;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use Tests\Fake\MemoryClient as Execution;
use Tests\Generator\Choices;

/**
 * Direct TIME structures preserve signed durations independently of SQL input and session calendar modes.
 */
#[CoversNothing]
#[Medium]
final class TimeDomainProperties extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerDurations(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 40; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * The same duration can have clock, compact or day-and-clock representation.
     * The expected value is computed from independently generated seconds, not from semantic normalization.
     */
    #[DataProvider('providerDurations')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesTimeStructuresExecuteWithoutSourceText(string $release, int $seed): void
    {
        $choices = new Choices('time-domain:' . $seed);
        $seconds = $choices->integer(0, 3020398);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remaining = $seconds % 60;
        $sign = $choices->one(['', '-']);
        $fraction = $choices->one(['', '.5', '.123456']);
        $clock = sprintf('%02d:%02d:%02d', $hours, $minutes, $remaining);
        $text = $sign . $choices->one([$clock, sprintf('%02d%02d%02d', $hours, $minutes, $remaining), intdiv($hours, 24) . $choices->one([' ', "\t", "\n", "\r", "\v", "\f"]) . sprintf('%02d:%02d:%02d', $hours % 24, $minutes, $remaining)]) . $fraction;
        $literal = new TemporalLiteral(TemporalForm::Time, $text);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $session->variables->session['sql_mode'] = $choices->one(['', 'ALLOW_INVALID_DATES', 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE']);
        $reply = (new Execution())->runIn($session, $literal);

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[$sign . $clock . $fraction]], $reply->rows);
    }
}
