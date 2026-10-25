<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Literal;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral as Model;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class TemporalLiteralProperties extends TestCase
{
    /**
     * @return iterable<string, array{non-negative-int}>
     */
    public static function providerSamples(): iterable
    {
        for ($sample = 0; $sample < (new \Tests\Generator\Samples())->count(); $sample++) {
            yield 'sample ' . $sample => [$sample];
        }
    }
    /**
     * Runs the generated analysis result through the ordinary dispatch and execution boundary.
     * @param non-negative-int $sample
     * @throws JsonException When the semantic type inventory cannot be read
     */
    #[Test]
    #[DataProvider('providerSamples')]
    public function satisfiesPublicValueInvariants(int $sample): void
    {
        $value = (new \Tests\Generator\Samples())->sample(self::MODEL, $sample);
        $statement = (new Embedding())->statement($value);
        $operation = (new \Tests\Generator\Semantics\Statement\OperationGenerator())->from($statement, new Choices('context:' . $sample));
        $profile = $operation->profile();
        $database = $operation->context->searchPath[0]->value;
        $mode = (new \Tests\Generator\Profiles())->mode($profile);
        $session = (new Instance(substr($profile->grammar->value, 6), ['sql_mode' => $mode], [$database]))->connect(database: $database);
        $parameters = array_fill(0, (new Bindings())->count($operation->statement), [null, \MySqlMemory\Typing\Domain::null()]);
        try {
            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement), $parameters);
            self::assertGreaterThanOrEqual(0, $reply->warnings());
        } catch (SqlError $error) {
            self::assertTrue($error->signalled !== null || !in_array($error->error, [\MySqlMemory\Error\Family\StatementError::UnknownError, \MySqlMemory\Error\Family\StatementError::NotSupportedYet], true), self::MODEL . ': ' . $error->getMessage());
        }
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerTimeDomainDurations(): iterable
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
    #[DataProvider('providerTimeDomainDurations')]
    #[Test]
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
        $literal = new Model(TemporalForm::Time, $text);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $session->variables->session['sql_mode'] = $choices->one(['', 'ALLOW_INVALID_DATES', 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE']);
        $statement1 = (new Embedding())->statement($literal);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $reply = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[$sign . $clock . $fraction]], $reply->rows);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerTimestampDomainMoments(): iterable
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
    #[DataProvider('providerTimestampDomainMoments')]
    #[Test]
    public function satisfiesTimestampStructuresExecuteWithoutSourceText(string $release, int $seed): void
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
        $literal = new Model(TemporalForm::Timestamp, $text);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $session->variables->session['sql_mode'] = $choices->one(['', 'ALLOW_INVALID_DATES', 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE']);
        $statement1 = (new Embedding())->statement($literal);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $reply = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[$date . ' ' . $clock . $fraction]], $reply->rows);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerDateModeModes(): iterable
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
    #[DataProvider('providerDateModeModes')]
    #[Test]
    public function satisfiesCalendarValidityDependsOnTheSessionMode(string $release, int $seed): void
    {
        $choices = new Choices('date-modes:' . $seed);
        $text = sprintf('%04d-%02d-31', $choices->integer(1000, 9999), $choices->one([2, 4, 6, 9, 11]));
        $form = $choices->one([TemporalForm::Date, TemporalForm::Timestamp]);
        $text .= $form === TemporalForm::Timestamp ? sprintf(' %02d:%02d:%02d', $choices->integer(0, 23), $choices->integer(0, 59), $choices->integer(0, 59)) : '';
        $literal = new Model($form, $text);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $session->variables->session['sql_mode'] = '';
        $failure = null;
        try {
            $statement1 = (new Embedding())->statement($literal);
            $session->activity->begin();
            StatementCounters::received($session);
            $session->hinted = [];
            $session->dots = [];
            $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
            (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        } catch (SqlError $error) {
            $failure = $error;
        }
        self::assertInstanceOf(SqlError::class, $failure);
        self::assertSame(1525, $failure->getCode());
        $session->variables->session['sql_mode'] = 'ALLOW_INVALID_DATES';
        $statement2 = (new Embedding())->statement($literal);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        $reply = (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[$text]], $reply->rows);
    }
}
