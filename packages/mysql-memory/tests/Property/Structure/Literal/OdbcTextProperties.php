<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Literal;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\OdbcEscape;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Fake\MemoryClient as Execution;
use Tests\Generator\Choices;

/**
 * ODBC temporal escapes retain strings outside the temporal value domain.
 */
#[CoversNothing]
#[Medium]
final class OdbcTextProperties extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerInputs(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 40; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Directly constructed escapes execute arbitrary non-temporal decoded text.
     * The caller's unrelated SQL cannot supply or repair the generated operand.
     */
    #[DataProvider('providerInputs')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesNonTemporalStringsExecuteWithoutConstructingInvalidDates(string $release, int $seed): void
    {
        $choices = new Choices('odbc-text:' . $seed);
        $text = 'x';
        $length = $choices->integer(0, 80);
        for ($index = 0; $index < $length; $index++) {
            $text .= chr($choices->integer(32, 126));
        }
        $escape = new OdbcEscape(new Name($choices->one(['d', 't', 'ts', 'D', 'T', 'TS'])), new StringLiteral([$text]));
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $reply = (new Execution())->runIn($session, $escape);

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[$text]], $reply->rows);
        self::assertSame($text, $reply->columns[0]->name);
    }
    /**
     * One semantic escape changes its resolved type and column name with calendar settings.
     * The same pre-Operation structure is reused, with no source SQL available to execution.
     */
    #[DataProvider('providerInputs')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesCalendarModesDetermineTheEscapeTypeAndName(string $release, int $seed): void
    {
        $choices = new Choices('odbc-calendar:' . $seed);
        $year = $choices->integer(1000, 9999);
        $text = $choices->one([sprintf('%04d-00-01', $year), sprintf('%04d-02-31', $year), '0000-00-00']);
        $escape = new OdbcEscape(new Name('d'), new StringLiteral([$text]));
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $session->variables->session['sql_mode'] = 'NO_ZERO_DATE,NO_ZERO_IN_DATE';
        $plain = (new Execution())->runIn($session, $escape);

        self::assertInstanceOf(ResultSet::class, $plain);
        self::assertSame([[$text]], $plain->rows);
        self::assertSame($text, $plain->columns[0]->name);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::VarString, $plain->columns[0]->type);
        $session->variables->session['sql_mode'] = 'ALLOW_INVALID_DATES';
        $dated = (new Execution())->runIn($session, $escape);

        self::assertInstanceOf(ResultSet::class, $dated);
        self::assertSame([[$text]], $dated->rows);
        self::assertSame("{ d '" . $text . "' }", $dated->columns[0]->name);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Date, $dated->columns[0]->type);
    }

}
