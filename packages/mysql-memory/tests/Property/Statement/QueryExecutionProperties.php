<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\TestTrait;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field as FieldType;

/**
 * Executes generated expression, projection and statement meanings directly.
 * Even semantic derivation is absent: execution receives the generated bindings.
 */
#[CoversNothing]
#[Large]
final class QueryExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Quantified comparisons consume matching row shapes, NULLs and empty subqueries directly.
     */
    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('providerKeywordReleases')]
    public function satisfiesQuantifiedRowMembership(\SqlSemantics\Contract\GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Expression\Subquery\QuantifiedComparisonGenerator::rowQueries($release))->then(static function (array $input) use ($release): void {
            [$statement, $expected] = $input;
            self::assertInstanceOf(QueryRoot::class, $statement);
            $session = (new Instance(substr($release->value, 6), [], ['pbt']))->connect(database: 'pbt');
            $session->text = 'not SQL: generated quantified row comparison';
            $reply = (new Execution($session))->statement($statement);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertSame([[$expected]], $reply->rows);
            self::assertCount(1, $reply->columns);
            self::assertSame('chosen', $reply->columns[0]->name);
            self::assertSame(0, $reply->warnings());
        });
    }

    /**
     * Typed cast synonyms consume the same generated operand and preserve its result metadata.
     */
    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('providerKeywordReleases')]
    public function satisfiesCastSynonymsPreserveGeneratedValues(\SqlSemantics\Contract\GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Type\CastTargetGenerator::queries($release))->then(static function (array $sample) use ($release): void {
            [$query, $expected] = $sample;
            self::assertInstanceOf(QueryRoot::class, $query);
            $session = (new Instance(substr($release->value, 6)))->connect();
            $session->text = 'generated cast targets without SQL';
            $reply = (new Execution($session))->statement($query);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertSame([[$expected, $expected]], $reply->rows);
            self::assertSame($reply->columns[0]->type, $reply->columns[1]->type);
            self::assertSame($reply->columns[0]->length, $reply->columns[1]->length);
            self::assertSame($reply->columns[0]->decimals, $reply->columns[1]->decimals);
            self::assertSame($reply->columns[0]->flags, $reply->columns[1]->flags);
            self::assertSame('generated cast targets without SQL', $session->text);
            self::assertSame([], $session->transaction->statements->running);
        });
    }

    /**
     * Every clock synonym reads the same statement instant and exposes the same temporal domain.
     */
    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('providerKeywordReleases')]
    public function satisfiesClockSynonymsReadTheSameStatementInstant(\SqlSemantics\Contract\GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Call\ClockCallGenerator::queries($release))->then(static function (\SqlSemantics\Statement\AnalyzedStatement $statement) use ($release): void {
            $session = (new Instance(substr($release->value, 6)))->connect();
            $session->text = 'Generated clock alternatives without SQL';
            $reply = (new Execution($session))->statement($statement);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertCount(1, $reply->rows);
            self::assertSame($reply->rows[0][1], $reply->rows[0][0]);
            self::assertSame($reply->columns[1]->type, $reply->columns[0]->type);
            self::assertSame($reply->columns[1]->length, $reply->columns[0]->length);
            self::assertSame($reply->columns[1]->decimals, $reply->columns[0]->decimals);
            self::assertSame($reply->columns[1]->flags, $reply->columns[0]->flags);
            self::assertSame($reply->columns[1]->charset, $reply->columns[0]->charset);
            self::assertSame('Generated clock alternatives without SQL', $session->text);
        });
    }

    /**
     * Population synonyms consume their generated rows and compute the expected statistic.
     */
    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('providerKeywordReleases')]
    public function satisfiesAggregateSynonymsComputeTheirGeneratedPopulation(\SqlSemantics\Contract\GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Call\Aggregate\AggregateGenerator::populations($release))->then(static function (array $sample) use ($release): void {
            [$statement, $expected] = $sample;
            self::assertInstanceOf(\SqlSemantics\Statement\AnalyzedStatement::class, $statement);
            $session = (new Instance(substr($release->value, 6)))->connect();
            $session->text = 'Generated aggregate alternatives without SQL';
            $reply = (new Execution($session))->statement($statement);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertCount(1, $reply->rows);
            self::assertNotNull($reply->rows[0][0]);
            self::assertEqualsWithDelta($expected, (float) $reply->rows[0][0], 0.000001);
            self::assertSame($reply->rows[0][1], $reply->rows[0][0]);
            self::assertSame($reply->columns[1]->type, $reply->columns[0]->type);
            self::assertSame($reply->columns[1]->length, $reply->columns[0]->length);
            self::assertSame($reply->columns[1]->decimals, $reply->columns[0]->decimals);
            self::assertSame($reply->columns[1]->flags, $reply->columns[0]->flags);
            self::assertSame($reply->columns[1]->charset, $reply->columns[0]->charset);
            self::assertSame('Generated aggregate alternatives without SQL', $session->text);
        });
    }

    /**
     * Typed keyword alternatives preserve the function value and protocol metadata.
     */
    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('providerKeywordReleases')]
    public function satisfiesKeywordAlternativesExecuteTheirCanonicalFunction(\SqlSemantics\Contract\GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Call\KeywordCallGenerator::queries($release))->then(static function (\SqlSemantics\Statement\AnalyzedStatement $statement) use ($release): void {
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Generated keyword alternatives without SQL';
            $reply = (new Execution($session))->statement($statement);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertCount(1, $reply->rows);
            self::assertCount(2, $reply->columns);
            self::assertSame($reply->rows[0][1], $reply->rows[0][0]);
            self::assertSame('selected', $reply->columns[0]->name);
            self::assertSame('canonical', $reply->columns[1]->name);
            self::assertSame($reply->columns[1]->type, $reply->columns[0]->type);
            self::assertSame($reply->columns[1]->length, $reply->columns[0]->length);
            self::assertSame($reply->columns[1]->decimals, $reply->columns[0]->decimals);
            self::assertSame($reply->columns[1]->flags, $reply->columns[0]->flags);
            self::assertSame($reply->columns[1]->charset, $reply->columns[0]->charset);
            self::assertSame('Generated keyword alternatives without SQL', $session->text);
        });
    }

    /**
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease}>
     */
    public static function providerKeywordReleases(): iterable
    {
        foreach (\SqlSemantics\Contract\GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value => [$release];
            }
        }
    }

    /**
     * Executes the generated expression and preserves its result metadata.
     */
    #[Test]
    public function satisfiesGeneratedProjectionOwnsItsValueTypeNameAndSource(): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\QueryRootGenerator::projection())->then(static function (QueryRoot $statement): void {
            $field = $statement->field(0);
            self::assertInstanceOf(StringLiteral::class, $field->expression);
            $expression = $field->expression;
            self::assertEquals($field->type, $expression->dataType());
            self::assertNotNull($expression->source());
            $session = (new Instance('8.4.7'))->connect();
            $session->text = 'Generated values without SQL text or semantic derivation';
            $reply = (new Execution($session))->statement($statement);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertSame([[$expression->value()]], $reply->rows);
            self::assertSame($field->name?->value, $reply->columns[0]->name);
            self::assertSame(FieldType::VarString, $reply->columns[0]->type);
        });
    }

    /**
     * Execution uses the generated warning without recovering an input spelling.
     */
    #[Test]
    public function satisfiesGeneratedNoticesReachTheExecutionDiagnostics(): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\QueryRootGenerator::warnedProjection())->then(static function (QueryRoot $statement): void {
            $notice = $statement->notices()[0];
            self::assertSame($statement->field(0)->name, $notice->subject);
            self::assertNotNull($notice->inputSource);
            $session = (new Instance('8.4.7'))->connect();
            $session->text = 'No SQL text is available to reconstruct this warning';
            $reply = (new Execution($session))->statement($statement);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertSame(1, $reply->warnings());
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\CodedWarning::class, $notice->warning);
            self::assertSame([['Warning', $notice->warning->code(), $notice->warning->message()]], $session->diagnostics->conditions);
            self::assertSame($statement->field(0)->name?->value, $reply->columns[0]->name);
        });
    }

    /**
     * The generated diagnostic reaches the SQL reply without reanalyzing its expression.
     */
    #[Test]
    public function satisfiesGeneratedMissingColumnRetainsItsResolutionAndSqlError(): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\QueryRootGenerator::missingColumn())->then(static function (QueryRoot $statement): void {
            $field = $statement->field(0);
            self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\MissingColumn::class, $field->resolution);
            self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $field->type);
            self::assertSame($field->resolution, $field->type->cause);
            self::assertSame([$field->resolution], $statement->diagnostics());
            $session = (new Instance('8.4.7'))->connect();
            $session->text = 'A generated missing reference without source SQL';
            try {
                (new Execution($session))->statement($statement);
                self::fail('The generated missing column cannot produce a result row.');
            } catch (\MySqlMemory\Error\SqlError $error) {
                self::assertSame(1054, $error->getCode());
                self::assertStringContainsString($field->resolution->name->value, $error->getMessage());
            }
            self::assertSame('A generated missing reference without source SQL', $session->text);
        });
    }

    /**
     * Generated JSON-path operands select their array cell during ordinary query execution.
     */
    #[Test]
    public function satisfiesGeneratedJsonPathSelectsTheSpecifiedArrayCell(): void
    {
        $statements = new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator(\SqlSemantics\Contract\GrammarRelease::MySql847, \Tests\Generator\MySql\Statement\QueryRootGenerator::jsonPath());
        $this->forAll($statements)->then(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): void {
            $expression = $statement->field(0)->expression;
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall::class, $expression);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Call\Json\Path::class, $expression->pathValue);
            $index = $expression->pathValue->legs[0]->from;
            self::assertNotNull($index);
            $position = $index->last ? 3 - $index->offset : $index->offset;
            $session = (new Instance('8.4.7'))->connect();
            $session->text = 'JSON path semantic input without a SQL source';
            $reply = (new Execution($session))->statement($statement);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertSame([[(string) (($position + 1) * 10)]], $reply->rows);
            self::assertSame('selected', $reply->columns[0]->name);
        });
    }

    /**
     * The generated decoded-path boundary survives construction and error reporting.
     */
    #[Test]
    public function satisfiesGeneratedInvalidJsonPathReportsItsBytePosition(): void
    {
        $statements = new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator(\SqlSemantics\Contract\GrammarRelease::MySql847, \Tests\Generator\MySql\Statement\QueryRootGenerator::invalidJsonPath());
        $this->forAll($statements)->then(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): void {
            $expression = $statement->field(0)->expression;
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall::class, $expression);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Invalid::class, $expression->pathValue);
            $session = (new Instance('8.4.7'))->connect();
            $session->text = 'Generated decoded-path error without a SQL source';
            try {
                (new Execution($session))->statement($statement);
                self::fail('The generated invalid JSON path cannot produce a result row.');
            } catch (\MySqlMemory\Error\SqlError $error) {
                self::assertSame(3143, $error->getCode());
                self::assertSame(\MySqlMemory\Error\Family\DataError::InvalidJsonPath->message($expression->pathValue->position), $error->getMessage());
            }
        });
    }


    /**
     * Executes a generated declaration, its write and its column read as one dependent input.
     */
    #[Test]
    public function satisfiesGeneratedDeclarationsAndReferencesExecuteTogether(): void
    {
        $this->forAll(\Tests\Generator\Semantics\Statement\ScriptGenerator::declaredTable())->then(static function (\SqlSemantics\Statement\Script $script): void {
            $sequence = $script->statements;
            [$create, $insert, $query] = $sequence;
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable::class, $create);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows::class, $insert);
            self::assertInstanceOf(QueryRoot::class, $query);
            $declaration = $create->declarations()[0];
            $resolution = $query->field(0)->resolution;
            self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\ResolvedColumn::class, $resolution);
            $target = $insert->into->table->relationInformation()->table;
            self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\DeclaredTable::class, $target);
            self::assertSame($declaration, $target->table);
            $input = $resolution->relation->relationInformation()->table;
            self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\DeclaredTable::class, $input);
            self::assertSame($declaration, $input->table);
            $position = array_search($resolution->declaration(), [...$declaration->columns, ...array_map(static fn ($hidden) => $hidden->column, $declaration->implicit)], true);
            self::assertIsInt($position);
            $values = array_map(static function ($value): string {
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral::class, $value);
                return $value->text;
            }, $insert->rows[0]->values);
            $values[] = null;
            $session = (new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Generated declaration and local references without a SQL source';
            $execution = new Execution($session);
            self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $execution->statement($create));
            $written = $execution->statement($insert);
            self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $written);
            self::assertSame(1, $written->affectedRows);
            $result = $execution->statement($query);
            self::assertInstanceOf(ResultSet::class, $result);
            self::assertSame([[$values[$position]]], $result->rows);
            self::assertSame($resolution->slot->name?->value, $result->columns[0]->name);
            self::assertSame(FieldType::Long, $result->columns[0]->type);
            self::assertSame([], $session->transaction->statements->running);
        });
    }

    /**
     * Input spelling notices survive binding without retaining or rereading their source.
     */
    #[Test]
    public function satisfiesGeneratedSpellingNoticesReachTheirPublishedAlias(): void
    {
        $this->forAll(\Tests\Generator\Operands::oneOf([\Tests\Generator\MySql\Statement\QueryRootGenerator::spellingNotice(), \Tests\Generator\MySql\Statement\QueryRootGenerator::trimmedAlias()]))->then(static function (QueryRoot $statement): void {
            $notice = $statement->notices()[0];
            self::assertSame($statement->field(0)->name, $notice->subject);
            self::assertNotNull($notice->inputSource);
            $session = (new Instance('8.4.7'))->connect();
            $session->text = 'A generated alias spelling without SQL input';
            $statement = (new \MySqlMemory\Session\Statement\Binding())->operation($session, $statement);
            self::assertCount(1, $statement->notices());
            self::assertTrue($statement->notices()[0]->fromInput);
            $reply = (new Execution($session))->statement($statement);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertSame([['value']], $reply->rows);
            self::assertSame($statement->field(0)->name?->value, $reply->columns[0]->name);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\CodedWarning::class, $notice->warning);
            self::assertSame([['Warning', $notice->warning->code(), $notice->warning->message()]], $session->diagnostics->conditions);
        });
    }

    /**
     * NULL-only meaning reaches both the row and result-column metadata.
     */
    #[Test]
    public function satisfiesGeneratedNullFactsProduceNullValues(): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\QueryRootGenerator::nullProjection())->then(static function (QueryRoot $statement): void {
            $reply = (new Execution((new Instance('8.4.7'))->connect()))->statement($statement);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertSame([[null]], $reply->rows);
            self::assertSame(FieldType::Null, $reply->columns[0]->type);
            self::assertSame($statement->field(0)->name?->value, $reply->columns[0]->name);
        });
    }

    /**
     * A generated marker reads the supplied runtime slot without analyzing SQL again.
     */
    #[Test]
    public function satisfiesGeneratedParameterBindingsReadTheirRuntimeInput(): void
    {
        $this->forAll(\Tests\Generator\Operands::oneOf([\Tests\Generator\MySql\Statement\QueryRootGenerator::parameterProjection(), \Tests\Generator\Semantics\Statement\Reference\Missing\UndeclaredDomainGenerator::parameterQueries()]), \Eris\Generators::choose(0, 1000))->then(static function (QueryRoot $statement, int $value): void {
            $session = (new Instance('8.4.7'))->connect();
            $session->text = 'Generated marker without SQL input';
            $reply = (new Execution($session))->statement($statement, [[$value, \MySqlMemory\Typing\Domain::integer()]]);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertSame([[(string) $value]], $reply->rows);
            self::assertSame($statement->field(0)->name?->value, $reply->columns[0]->name);
            self::assertSame('Generated marker without SQL input', $session->text);
        });
    }

    /**
     * Generated lookup outcomes describe the same fields that execution exposes.
     */
    #[Test]
    public function satisfiesGeneratedFieldLookupsBelongToTheExecutedProjection(): void
    {
        $this->forAll(\Tests\Generator\Semantics\Statement\Shape\FieldLookupGenerator::queries())->then(static function (array $input): void {
            [$statement, $expected, $name] = $input;
            self::assertInstanceOf(QueryRoot::class, $statement);
            self::assertInstanceOf(\SqlSemantics\Statement\Shape\FieldLookup::class, $expected);
            self::assertIsString($name);
            self::assertEquals($expected, $statement->lookupField($name));
            $session = (new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt');
            try {
                $reply = (new Execution($session))->statement($statement);
                self::assertNotInstanceOf(\SqlSemantics\Statement\Shape\DependentField::class, $expected);
                self::assertInstanceOf(ResultSet::class, $reply);
                $values = [];
                $names = [];
                $fields = $statement->fields();
                self::assertNotNull($fields);
                foreach ($fields->items as $field) {
                    self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral::class, $field->expression);
                    $values[] = $field->expression->text;
                    $names[] = $field->name?->value;
                }
                self::assertSame([$values], $reply->rows);
                self::assertSame($names, array_map(static fn ($column): string => $column->name, $reply->columns));
            } catch (\MySqlMemory\Error\SqlError $error) {
                self::assertInstanceOf(\SqlSemantics\Statement\Shape\DependentField::class, $expected);
                self::assertSame(1146, $error->getCode());
            }
            self::assertSame([], $session->transaction->statements->running);
        });
    }

    /**
     * Row width metadata and the generated operands agree throughout execution.
     */
    #[Test]
    public function satisfiesGeneratedTupleWidthsExecuteTheirRows(): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Expression\TupleGenerator::comparisons())->then(static function (QueryRoot $statement): void {
            $session = (new Instance('8.4.7'))->connect();
            $reply = (new Execution($session))->statement($statement);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertSame([['1']], $reply->rows);
            self::assertSame($statement->field(0)->name?->value, $reply->columns[0]->name);
            self::assertSame([], $session->diagnostics->conditions);
        });
    }

    /**
     * A common-table resolution executes the rows owned by its generated definition.
     */
    #[Test]
    public function satisfiesCommonTableBindingsReadTheirDefinition(): void
    {
        $this->forAll(\Tests\Generator\Semantics\Statement\Reference\Table\CommonTableGenerator::queries())->then(static function (QueryRoot $statement): void {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\QueryExpression::class, $statement->query);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\With\With::class, $statement->query->with);
            $field = $statement->query->with->tables[0]->query->queryInformation()->projection[0];
            self::assertInstanceOf(\SqlSemantics\Statement\Shape\Field::class, $field);
            self::assertInstanceOf(StringLiteral::class, $field->expression);
            $reply = (new Execution((new Instance('8.4.7'))->connect()))->statement($statement);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertSame([[$field->expression->value()]], $reply->rows);
            self::assertSame($field->name?->value, $reply->columns[0]->name);
        });
    }

    /**
     * Session preparation resolves a generated type choice before numeric execution.
     */
    #[Test]
    public function satisfiesGeneratedRealChoicesUseTheExecutionMode(): void
    {
        $this->forAll(\Tests\Generator\Semantics\Statement\Type\ChoiceGenerator::realCasts(), \Eris\Generators::bool())->then(static function (QueryRoot $input, bool $single): void {
            self::assertInstanceOf(\SqlSemantics\Statement\Type\Choice::class, $input->field(0)->type);
            $expression = $input->field(0)->expression;
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast::class, $expression);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral::class, $expression->operand);
            $session = (new Instance('8.4.7', globals: ['sql_mode' => $single ? 'REAL_AS_FLOAT' : '']))->connect();
            $session->text = 'Generated REAL choice without source SQL';
            $bound = (new \MySqlMemory\Session\Statement\Binding())->operation($session, $input);
            $reply = (new Execution($session))->statement($bound);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertSame([[$expression->operand->text]], $reply->rows);
            self::assertSame($single ? FieldType::Float : FieldType::Double, $reply->columns[0]->type);
            self::assertSame('Generated REAL choice without source SQL', $session->text);
        });
    }

    /**
     * Preparation resolves incomplete declarations from current catalog values alone.
     */
    #[Test]
    public function satisfiesIncompleteColumnBindingsResolveBeforeExecution(): void
    {
        $this->forAll(\Tests\Generator\Semantics\Statement\Reference\Column\ConditionalColumnGenerator::incompleteReads())->then(static function (array $sequence): void {
            [$create, $insert, $query, $expected] = $sequence;
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable::class, $create);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows::class, $insert);
            self::assertInstanceOf(QueryRoot::class, $query);
            self::assertInstanceOf(QueryRoot::class, $expected);
            self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\ConditionalColumn::class, $query->field(0)->resolution);
            $session = (new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Incomplete generated declarations without SQL';
            $execution = new Execution($session);
            $execution->statement($create);
            $execution->statement($insert);
            $bound = (new \MySqlMemory\Session\Statement\Binding())->operation($session, $query);
            self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\ResolvedColumn::class, $bound->field(0)->resolution);
            $actual = $execution->statement($bound);
            $complete = $execution->statement($expected);
            self::assertInstanceOf(ResultSet::class, $actual);
            self::assertInstanceOf(ResultSet::class, $complete);
            self::assertSame($complete->rows, $actual->rows);
            self::assertSame($complete->columns[0]->name, $actual->columns[0]->name);
            self::assertSame($complete->columns[0]->type, $actual->columns[0]->type);
            self::assertSame('Incomplete generated declarations without SQL', $session->text);
        });
    }

    /**
     * A catalog refresh replaces conditional and conflicting candidates before scanning rows.
     */
    #[Test]
    public function satisfiesGeneratedTableCandidatesResolveToInstalledTables(): void
    {
        $this->forAll(\Tests\Generator\Semantics\Statement\Reference\Table\TableResolutionGenerator::reboundReads())->then(static function (array $sequence): void {
            [$create, $insert, $input, $expected] = $sequence;
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable::class, $create);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows::class, $insert);
            self::assertInstanceOf(QueryRoot::class, $input);
            self::assertInstanceOf(QueryRoot::class, $expected);
            $session = (new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Generated declaration candidates without SQL';
            $execution = new Execution($session);
            $execution->statement($create);
            $execution->statement($insert);
            $bound = (new \MySqlMemory\Session\Statement\Binding())->operation($session, $input);
            self::assertSame([], $bound->diagnostics());
            $actual = $execution->statement($bound);
            $complete = $execution->statement($expected);
            self::assertInstanceOf(ResultSet::class, $actual);
            self::assertInstanceOf(ResultSet::class, $complete);
            self::assertSame($complete->rows, $actual->rows);
            self::assertSame($complete->columns[0]->name, $actual->columns[0]->name);
            self::assertSame($complete->columns[0]->type, $actual->columns[0]->type);
            self::assertSame('Generated declaration candidates without SQL', $session->text);
        });
    }

    /**
     * An introduced string's value and column name use its character set conversion.
     */
    #[Test]
    public function satisfiesGeneratedNameConversionsReachResultMetadata(): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Query\Problem\NameConversionGenerator::queries())->then(static function (QueryRoot $input): void {
            $literal = $input->field(0)->expression;
            self::assertInstanceOf(StringLiteral::class, $literal);
            $session = (new Instance('8.4.7'))->connect();
            $reply = (new Execution($session))->statement($input);
            self::assertInstanceOf(ResultSet::class, $reply);
            $converted = 'é' . substr($literal->value(), 1);
            self::assertSame([[$converted]], $reply->rows);
            self::assertSame($converted, $reply->columns[0]->name);
        });
    }


    /**
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease}>
     */
    public static function providerRadixReleases(): iterable
    {
        foreach (\SqlSemantics\Contract\GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value => [$release];
            }
        }
    }

    /**
     * Binary values preserve leading zero bytes through every generated literal notation.
     */
    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('providerRadixReleases')]
    public function satisfiesGeneratedRadixNotationPreservesItsBinaryValue(\SqlSemantics\Contract\GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Literal\RadixLiteralGenerator::queries($release))->then(static function (\SqlSemantics\Statement\AnalyzedStatement $statement) use ($release): void {
            $literal = $statement->field(0)->expression;
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral::class, $literal);
            $session = (new Instance(substr($release->value, 6)))->connect();
            $session->text = 'Generated binary literal without SQL input';
            $reply = (new Execution($session))->statement($statement);
            self::assertInstanceOf(ResultSet::class, $reply);
            $radix = $literal->radix === \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal ? 16 : 2;
            $bytes = (int) ceil(strlen($literal->digits) * ($radix === 16 ? 4 : 1) / 8);
            $expected = $literal->digits === '' ? '' : str_pad(base_convert($literal->digits, $radix, 16), $bytes * 2, '0', STR_PAD_LEFT);
            self::assertCount(1, $reply->rows);
            self::assertIsString($reply->rows[0][0]);
            self::assertSame($expected, bin2hex($reply->rows[0][0]));
            self::assertSame($statement->field(0)->name?->value, $reply->columns[0]->name);
            self::assertSame('Generated binary literal without SQL input', $session->text);
        });
    }
}
