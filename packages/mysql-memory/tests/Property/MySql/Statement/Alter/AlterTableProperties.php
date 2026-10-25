<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Alter;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable as Model;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\DefaultSetting;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\AddConstraint;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Bindings;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Embedding;
use Tests\Generator\Metadata;
use Tests\Generator\Profiles;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class AlterTableProperties extends TestCase
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
        $mode = (new Profiles())->mode($profile);
        $session = (new Instance(substr($profile->grammar->value, 6), ['sql_mode' => $mode], [$database]))->connect(database: $database);
        $parameters = array_fill(0, (new Bindings())->count($operation->statement), [null, Domain::null()]);
        try {
            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement), $parameters);
            self::assertGreaterThanOrEqual(0, $reply->warnings());
        } catch (SqlError $error) {
            self::assertTrue($error->signalled !== null || !in_array($error->error, [StatementError::UnknownError, StatementError::NotSupportedYet], true), self::MODEL . ': ' . $error->getMessage());
        }
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @return iterable<string, array{string, AlgorithmOption|LockOption}>
     */
    public static function providerAlterChoicesOptions(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach ([false, true] as $lock) {
                $names = $lock ? ['NONE', 'SHARED', 'EXCLUSIVE'] : ['COPY', 'INPLACE', ...str_starts_with($release, '5.') ? [] : ['INSTANT']];
                foreach ([null, ...$names, ...str_starts_with($release, '5.') ? ['DEFAULT'] : []] as $name) {
                    for ($seed = 0; $seed < 5; $seed++) {
                        $choices = new Choices('alter-choice:' . $release . ':' . $seed);
                        $spelling = $name === null ? null : new Name(implode('', array_map(static fn (string $letter): string => $choices->one([$letter, strtolower($letter)]), str_split($name))));
                        yield $release . ':' . ($lock ? 'lock' : 'algorithm') . ':' . ($name ?? 'keyword') . ':' . $seed => [$release, $lock ? new LockOption($spelling) : new AlgorithmOption($spelling)];
                    }
                }
            }
        }
    }

    /**
     * All known algorithms permit an unchanged table; all known locks permit adding its secondary index.
     * The fixture supplies the table and integer column independently of the constructed statement.
     */
    #[DataProvider('providerAlterChoicesOptions')]
    #[Test]
    public function satisfiesGeneratedOptionUsesItsConstructedDomain(string $release, AlgorithmOption|LockOption $option): void
    {
        $session = (new Instance($release))->connect();
        $session->execute('CREATE DATABASE d');
        $session->execute('CREATE TABLE d.t(a INT)');
        $commands = [$option];
        if ($option instanceof LockOption) {
            $commands[] = new AddConstraint(new IndexDefinition(IndexKind::Index, [new ColumnPart(new Name('a'))], new ColumnName(new Name('i'))));
        }
        $statement = new Model(new QualifiedName(new Name('t'), new Name('d')), $commands);
        $session->text = 'unrelated invalid SQL';
        $statement1 = (new Embedding())->statement($statement);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        self::assertInstanceOf(Completion::class, (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []));
    }

    /**
     * @throws JsonException When a semantic inventory is invalid
     */
    #[Test]
    public function satisfiesPreservesTheGeneratedAlterAfterRejectingAColumnTimestampClause(): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices('semantic-types-1611:23');
        $choices->one($catalogue->roots());
        $construction = new Construction($choices, $catalogue, new Metadata());
        $node = $construction->object(Model::class, 6);
        self::assertInstanceOf(Model::class, $node);
        self::assertSame('mysql', (new Profiles())->select($node)->grammar->database());
        self::assertArrayHasKey(DefaultSetting::class . ': ALTER COLUMN SET DEFAULT accepts a literal or a parenthesized expression; a bare timestamp clause belongs to a column definition.', $construction->rejections);
        $subject = $node;
        $profile = (new Profiles())->select($subject);
        $session = (new Instance(substr($profile->grammar->value, 6)))->connect();
        $session->variables->session['sql_mode'] = (new Profiles())->mode($profile);
        $statement = (new Embedding())->statement($subject);
        $parameters = array_fill(0, (new Bindings())->count($statement), [null, Domain::null()]);
        try {
            $operation = (new Binding())->operation($session, $statement, $parameters, $profile);
            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement), $parameters);
            self::assertGreaterThanOrEqual(0, $reply->warnings());
        } catch (SqlError $failure) {
            self::assertTrue($failure->signalled !== null || !in_array($failure->error, [StatementError::UnknownError, StatementError::NotSupportedYet], true), $failure->getMessage());
        }
    }
}
