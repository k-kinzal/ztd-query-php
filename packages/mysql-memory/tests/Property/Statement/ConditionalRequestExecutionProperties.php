<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\Generators;
use LogicException;
use MySqlMemory\Command\Prepared\PreparedStatement;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Eris\TestTrait;
use Tests\Generator\MySql\Statement\Account\Privilege\RevokePrivilegesGenerator;
use Tests\Generator\MySql\Statement\Dml\UpdateGenerator;
use Tests\Generator\MySql\Statement\Routine\CreateTriggerGenerator;
use Tests\Generator\MySql\Statement\Table\CreateIndexGenerator;
use Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator;

/**
 * Executes generated complete requests before and after their related table definitions.
 */
#[CoversNothing]
#[Large]
final class ConditionalRequestExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * DO prepares generated dependencies before its retained wildcard or row-width condition.
     */
    #[Test]
    #[DataProvider('providerEvaluationReleases')]
    public function satisfiesEvaluationRequestsReachTheirConditionAfterTheGeneratedTable(GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Dml\EvaluationGenerator::dependent($release))->then(static function (array $sample) use ($release): void {
            [$absent, $definition, $present, $code] = $sample;
            self::assertInstanceOf(M\Dml\Evaluation::class, $absent);
            self::assertInstanceOf(M\Table\CreateTable::class, $definition);
            self::assertInstanceOf(M\Dml\Evaluation::class, $present);
            $database = $definition->name->schema->value ?? 'pbt';
            $session = (new Instance(substr($release->value, 6), databases: [$database]))->connect(database: $database);
            $session->text = 'Generated DO requests without SQL';
            $execution = new Execution($session);
            try {
                $execution->statement($absent);
                self::fail('The generated subquery requires its absent table.');
            } catch (SqlError $error) {
                self::assertSame(1146, $error->getCode());
            }
            self::assertInstanceOf(Completion::class, $execution->statement($definition));
            try {
                $execution->statement($present);
                self::fail('The complete DO request retains its preparation condition.');
            } catch (SqlError $error) {
                self::assertSame($code, $error->getCode());
            }
            self::assertSame('Generated DO requests without SQL', $session->text);
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerEvaluationReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value => [$release];
            }
        }
    }

    /**
     * Missing catalog state is an ordinary SQL refusal; implementation failures escape.
     *
     * @param 'index'|'update'|'trigger'|'revoke' $family The generated statement family
     */
    #[Test]
    #[DataProvider('providerRequests')]
    public function satisfiesRequestsWithoutPrerequisitesUseTheCommonBoundary(GrammarRelease $release, string $family): void
    {
        $requests = match ($family) {
            'index' => new CreateIndexGenerator($release),
            'update' => new UpdateGenerator($release),
            'trigger' => new CreateTriggerGenerator(),
            'revoke' => new RevokePrivilegesGenerator(),
        };
        $this->forAll(new AnalyzedStatementGenerator($release, $requests))->then(static function (\SqlSemantics\Statement\AnalyzedStatement $operation) use ($release, $family): void {
            $session = (new Instance(substr($release->value, 6), [], ['pbt', 'other']))->connect(database: 'pbt');
            $session->text = 'generated request without SQL';
            try {
                (new Execution($session))->statement($operation);
                self::fail('The generated request requires absent state or retains a routine wildcard condition.');
            } catch (SqlError $error) {
                self::assertSame($family === 'revoke' ? 1144 : 1146, $error->getCode());
            }
            self::assertSame([], $session->instance->dictionary->schema('pbt')?->tables);
            self::assertSame('generated request without SQL', $session->text);
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
        });
    }

    /**
     * Eris constructs and shrinks each dependency together with its consuming request.
     * Conditional indexes can prepare before the table exists; UPDATE prepares after creation.
     *
     * @param 'index'|'update'|'trigger' $family The generated statement family
     */
    #[Test]
    #[DataProvider('providerDependentRequests')]
    public function satisfiesRelatedDefinitionsExposeTheReachedCondition(GrammarRelease $release, string $family): void
    {
        $requests = match ($family) {
            'index' => new CreateIndexGenerator($release),
            'update' => new UpdateGenerator($release),
            'trigger' => new CreateTriggerGenerator(),
        };
        $sequences = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $operation): array {
            $request = $operation;
            if (!$request instanceof M\Table\CreateIndex && !$request instanceof M\Dml\Update && !$request instanceof M\Routine\CreateTrigger) {
                throw new LogicException('The generator supplies an index, update or trigger request.');
            }
            if ($request instanceof M\Table\CreateIndex && !$request->parts[0] instanceof M\Table\Key\ColumnPart) {
                throw new LogicException('The index generator supplies a column key.');
            }
            $column = match (true) {
                $request instanceof M\Table\CreateIndex => $request->parts[0]->column,
                $request instanceof M\Dml\Update => $request->assignments[0]->column->name,
                default => new Name('a'),
            };
            $table = new M\Table\CreateTable(new QualifiedName(new Name('target')), [new M\Table\Column\ColumnDefinition(new M\Name\ColumnName($column), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)))]);
            return [(new \SqlSemantics\Platform\MySql\Construction\Publication())->publish(new \SqlSemantics\Contract\AnalysisContext($operation->profile(), [new Name('pbt')], session: M\Type\Resolved\Settings::defaults($operation->profile()->grammar)), $table, new \SqlSemantics\Statement\Source\SourceMap()), $operation];
        }, new AnalyzedStatementGenerator($release, $requests));
        $this->forAll($sequences)->then(static function (array $sequence) use ($release): void {
            [$definition, $operation] = $sequence;
            self::assertInstanceOf(MySqlStatement::class, $definition);
            self::assertInstanceOf(MySqlStatement::class, $operation);
            $session = (new Instance(substr($release->value, 6), [], ['pbt', 'other']))->connect(database: 'pbt');
            $request = $operation;
            self::assertTrue($request instanceof M\Table\CreateIndex || $request instanceof M\Dml\Update || $request instanceof M\Routine\CreateTrigger);
            if ($request instanceof M\Table\CreateIndex && $request->hasExecutionCondition()) {
                (new \MySqlMemory\Session\Access\Preparation())->check($operation, $session);
            }
            $prepared = PreparedStatement::capture($operation, $session);
            self::assertInstanceOf(Completion::class, (new Execution($session))->statement($definition));
            $table = $session->instance->dictionary->table('pbt', 'target');
            self::assertNotNull($table);
            $conditional = $request->hasExecutionCondition();
            $reply = $prepared->run($session, 'generated dependent request', [])[0];
            if ($conditional) {
                self::assertInstanceOf(SqlError::class, $reply);
                $code = match (true) {
                    $request instanceof M\Table\CreateIndex => 1280,
                    $request instanceof M\Dml\Update => 1241,
                    $request instanceof M\Routine\CreateTrigger => 1435,
                };
                self::assertSame($code, $reply->getCode());
                self::assertSame($table, $session->instance->dictionary->table('pbt', 'target'));
            } else {
                self::assertInstanceOf(Completion::class, $reply);
                if ($request instanceof M\Table\CreateIndex) {
                    self::assertCount(1, $session->instance->dictionary->table('pbt', 'target')?->definition->keys ?? []);
                } elseif ($request instanceof M\Routine\CreateTrigger) {
                    $schema = $session->instance->dictionary->schema('pbt');
                    self::assertNotNull($schema);
                    self::assertCount(1, $schema->triggers);
                }
            }
            self::assertSame($request, $prepared->statement);
            self::assertSame('generated dependent request', $session->text);
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease, string}>
     */
    public static function providerRequests(): iterable
    {
        foreach (\Container\MySqlRelease::versions() as $version) {
            foreach (['index', 'update', 'revoke', 'trigger'] as $family) {
                yield $version . ' ' . $family => [GrammarRelease::from('mysql-' . $version), $family];
            }
        }
    }

    /**
     * @return iterable<string, array{GrammarRelease, string}>
     */
    public static function providerDependentRequests(): iterable
    {
        foreach (\Container\MySqlRelease::versions() as $version) {
            foreach (['index', 'update', 'trigger'] as $family) {
                yield $version . ' ' . $family => [GrammarRelease::from('mysql-' . $version), $family];
            }
        }
    }
}
