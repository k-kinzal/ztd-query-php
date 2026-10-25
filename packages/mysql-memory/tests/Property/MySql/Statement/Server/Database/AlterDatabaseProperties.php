<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Server\Database;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase as Model;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseReadOnly;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class AlterDatabaseProperties extends TestCase
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
    public static function providerDatabaseConflictOptions(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Generated repeated spellings retain a conflict's order against a second invalid option.
     */
    #[DataProvider('providerDatabaseConflictOptions')]
    #[Test]
    public function satisfiesDirectOptionsRetainTheirFirstFailureAndPrecedingWarning(string $release, int $seed): void
    {
        $choices = new Choices('database-conflicts:' . $seed);
        $first = $choices->one([false, true]);
        $options = array_map(static fn (int $index): DatabaseReadOnly => new DatabaseReadOnly(new Numeral(str_repeat('0', $index) . ($first ? '1' : '0'))), range(0, $choices->integer(1, 5)));
        $options[] = new DatabaseReadOnly($first ? null : new Numeral('1e0'));
        $invalid = new DatabaseCharset(new CharsetName(new Name('missing_charset')));
        $earlier = $seed % 2 === 0;
        $options = $earlier ? [$invalid, ...$options] : [...$options, $invalid];
        $options = [new DatabaseCharset(new CharsetName(new Name('utf8'))), ...$options];
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $error = null;
        try {
            $statement1 = (new Embedding())->statement(new Model(new Name('missing_database'), $options));
            $session->activity->begin();
            StatementCounters::received($session);
            $session->hinted = [];
            $session->dots = [];
            $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
            (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        } catch (SqlError $caught) {
            $error = $caught;
        }
        self::assertSame($earlier ? 1115 : 1302, $error?->getCode());
        self::assertSame($earlier ? "Unknown character set: 'missing_charset'" : "Conflicting declarations: 'READ ONLY=0' and 'READ ONLY=1'", $error->getMessage());
        self::assertSame([3719, $earlier ? 1115 : 1302], array_column($session->diagnostics->conditions, 1));
    }
}
