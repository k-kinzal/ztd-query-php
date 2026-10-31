<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Expression;

use MySqlMemory\Instance;
use MySqlMemory\Program\Expression\DeleteUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(DeleteUse::class)]
#[Medium]
final class DeleteUseTest extends TestCase
{
    /**
     * @return iterable<string, array{M\Routine\Program\Write\DeferredMultipleDelete}>
     */
    public static function providerWrites(): iterable
    {
        $query = new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('source'))));
        yield 'derived-target' => [new M\Routine\Program\Write\DeferredMultipleDelete(null, [], new M\Dml\Target\DeletionInput([new M\Relation\DerivedTable($query, new Name('q'))]), M\Dml\MultipleDeleteForm::BeforeFrom)];
    }

    #[DataProvider('providerWrites')]
    public function testErrorPreparesDependenciesBeforeTheTargetRole(M\Routine\Program\Write\DeferredMultipleDelete $write): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        self::assertSame(1146, (new DeleteUse())->error($write, $session)->getCode());
        $session->query('CREATE TABLE source(a INT)');
        $session->text = 'unrelated invalid input';
        self::assertSame(1288, (new DeleteUse())->error($write, $session)->getCode());
        self::assertSame('unrelated invalid input', $session->text);
    }

    #[DataProvider('providerWrites')]
    public function testRaiseRequiresTheDestinationNamespace(M\Routine\Program\Write\DeferredMultipleDelete $write): void
    {
        $session = (new Instance())->connect();
        $this->expectExceptionMessage('No database selected');
        (new DeleteUse())->raise($write, $session);
    }

    public function testInputsPreparesTheRetainedDocumentWithoutReadingRequestText(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $target = new QualifiedName(new Name('t'));
        $document = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        $table = new M\Relation\JsonTable($document, new M\Literal\StringLiteral(['$']), [new M\Call\Json\OrdinalityColumn(new Name('n'))], new Name('j'));
        $input = new M\Dml\Target\DeletionInput([new M\Relation\TableReference($target), $table], [$target]);
        $write = new M\Routine\Program\Write\DeferredMultipleDelete(null, [], $input, M\Dml\MultipleDeleteForm::BeforeFrom);
        $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, new \SqlSemantics\Contract\SearchPath('d'), $session->resolution());
        $derivation = new \SqlSemantics\Construction\Derivation($context);
        $write->deriveProgram($derivation, new \SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope($derivation->environment()));
        $session->text = 'opaque request label';
        $this->expectExceptionCode(1241);
        (new DeleteUse())->inputs($write, (new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation), $session);
    }

    public function testErrorBindsTheOriginalPredicateBeforeItsOrderingPosition(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $write = new M\Routine\Program\Write\DeferredDelete(null, [], new M\Dml\WriteTarget(new QualifiedName(new Name('t'))), new M\Name\ColumnUse(new Name('a')), [new M\Query\OrderItem(new M\Literal\NumberLiteral('2'))]);
        self::assertSame(1146, (new DeleteUse())->error($write, $session)->getCode());
        $session->query('CREATE TABLE t(b INT)');
        $session->text = 'opaque request label';
        self::assertSame("Unknown column 'a' in 'where clause'", (new DeleteUse())->error($write, $session)->getMessage());
        $session->query('ALTER TABLE t ADD COLUMN a INT');
        $session->text = 'opaque request label';
        self::assertSame("Unknown column '2' in 'order clause'", (new DeleteUse())->error($write, $session)->getMessage());
        self::assertSame('opaque request label', $session->text);
    }

    public function testInputsRegistersTheRetainedCommonTableDefinitions(): void
    {
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'), new Name('a'))]);
        $with = new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('c'), [], $query)]);
        $input = new M\Dml\Target\DeletionInput([new M\Relation\TableReference(new QualifiedName(new Name('c')))]);
        $write = new M\Routine\Program\Write\DeferredMultipleDelete($with, [], $input, M\Dml\MultipleDeleteForm::BeforeFrom);
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->text = 'opaque request label';
        self::assertSame(1288, (new DeleteUse())->error($write, $session)->getCode());
        self::assertSame('opaque request label', $session->text);
    }

    public function testInputsLeavesTheCommonTargetConditionAheadOfOrdering(): void
    {
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'), new Name('a'))]);
        $with = new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('c'), [], $query)]);
        $write = new M\Routine\Program\Write\DeferredDelete($with, [], new M\Dml\WriteTarget(new QualifiedName(new Name('c'))), new M\Name\ColumnUse(new Name('missing')), [new M\Query\OrderItem(new M\Literal\NumberLiteral('1'))]);
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->text = 'opaque request label';
        self::assertSame(1288, (new DeleteUse())->error($write, $session)->getCode());
        self::assertSame('opaque request label', $session->text);
    }

    public function testStatementUsesThePublishedReadOnlyTarget(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $statement = $session->semantics()->analyze('DELETE q FROM (SELECT 1) q');
        self::assertInstanceOf(M\Dml\MultipleDelete::class, $statement);
        $session->text = 'opaque request label';
        $this->expectExceptionCode(1288);
        (new DeleteUse())->statement($statement, $session);
    }

    public function testCheckUsesTheAlreadyBoundDeletionOperands(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $statement = $session->semantics()->analyze('DELETE q FROM (SELECT 1) q');
        self::assertInstanceOf(M\Dml\MultipleDelete::class, $statement);
        $session->text = 'opaque request label';
        $this->expectExceptionCode(1288);
        (new DeleteUse())->check($statement, $statement->statementInformation(), $session);
    }

    public function testExplainedIsNeverReachedForAnUnknownFormat(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $reply = $session->run('EXPLAIN FORMAT=XML DELETE x FROM t')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply);
        self::assertSame(1791, $reply->getCode());
    }

    public function testExplainedRestoresTheCallerDatabaseAfterTargetFailure(): void
    {
        $session = (new Instance('8.4.7', [], ['d', 'other']))->connect(database: 'other');
        $statement = $session->semantics()->analyze('EXPLAIN FOR DATABASE d DELETE x FROM t');
        self::assertInstanceOf(M\Utility\Explain\Explain::class, $statement);
        $session->text = 'opaque explanation label';
        $this->expectExceptionCode(1109);
        try {
            (new DeleteUse())->explained($statement, $session);
        } finally {
            self::assertSame('other', $session->variables->database);
            self::assertSame('opaque explanation label', $session->text);
        }
    }

}
