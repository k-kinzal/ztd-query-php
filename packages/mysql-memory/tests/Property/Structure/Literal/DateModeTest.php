<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Literal;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;

/**
 * Calendar-invalid dates have a concrete execution-state witness rather than a blanket error exemption.
 */
#[CoversNothing]
#[Medium]
final class DateModeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerModes(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 30; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * The same directly constructed date fails without ALLOW_INVALID_DATES and succeeds with it.
     * No SQL or AST is supplied to either execution, and the rejected observation is checked explicitly.
     */
    #[DataProvider('providerModes')]
    public function testCalendarValidityDependsOnTheSessionMode(string $release, int $seed): void
    {
        $choices = new Choices('date-modes:' . $seed);
        $text = sprintf('%04d-%02d-31', $choices->integer(1000, 9999), $choices->one([2, 4, 6, 9, 11]));
        $form = $choices->one([TemporalForm::Date, TemporalForm::Timestamp]);
        $text .= $form === TemporalForm::Timestamp ? sprintf(' %02d:%02d:%02d', $choices->integer(0, 23), $choices->integer(0, 59), $choices->integer(0, 59)) : '';
        $literal = new TemporalLiteral($form, $text);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $session->variables->session['sql_mode'] = '';
        $execution = new Execution();
        $failure = null;
        try {
            $execution->runIn($session, $literal);
        } catch (SqlError $error) {
            $failure = $error;
        }

        self::assertInstanceOf(SqlError::class, $failure);
        self::assertSame(1525, $failure->getCode());
        $session->variables->session['sql_mode'] = 'ALLOW_INVALID_DATES';
        $reply = $execution->runIn($session, $literal);

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[$text]], $reply->rows);
    }
}
