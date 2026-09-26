<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\AnalysisException::class)]
#[UsesClass(\SqlSemantics\Facade\Semantics::class)]
#[UsesClass(\SqlSemantics\Statement\Element::class)]
#[UsesClass(\SqlSemantics\Statement\Writer::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[Medium]
final class StatementTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testWithCommandConstructsAndUpdatesWithoutLoadingTheSqlParser(): void
    {
        self::assertFalse(class_exists(\SqlParser\Parser\LrParser::class, false));
        $command = new \SqlSemantics\Statement\Model\Sqlite\Value\CmdWithCommitEndTransOpt_ccca6149(
            'COMMIT',
            new \SqlSemantics\Statement\Model\Sqlite\Value\TransOptWith_6ac05548(),
        );
        $statement = new \SqlSemantics\Statement\Statement($command);
        $updated = $statement->withCommand($command->withTransOpt(new \SqlSemantics\Statement\Model\Sqlite\Value\TransOptWithTransaction_ea573324()));
        self::assertSame('COMMIT TRANSACTION', $updated->toString());
        self::assertSame('COMMIT', $statement->toString());
        self::assertFalse(class_exists(\SqlParser\Parser\LrParser::class, false));
    }

    public function testWithCommandSharesUnchangedValuesAndPreservesTheOriginal(): void
    {
        $transaction = new \SqlSemantics\Statement\Model\Sqlite\Value\TransOptWithTransaction_ea573324();
        $command = new \SqlSemantics\Statement\Model\Sqlite\Value\CmdWithCommitEndTransOpt_ccca6149('COMMIT', $transaction);
        $original = new \SqlSemantics\Statement\Statement($command);
        $updated = $original->withCommand($command->withCommitEnd('END'));
        self::assertSame('COMMIT TRANSACTION', $original->toString());
        self::assertSame('END TRANSACTION', $updated->toString());
        self::assertNotSame($original, $updated);
        self::assertSame($transaction, $command->withCommitEnd('END')->transOpt);
    }

    public function testWithCommandReplacesWhereUsingStructuredValues(): void
    {
        $original = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT foo FROM items');
        $command = $original->command;
        self::assertInstanceOf(\SqlSemantics\Statement\Model\Sqlite\Value\EcmdWithCmdxSemi_b7577a8f::class, $command);
        $select = $command->cmdx;
        self::assertInstanceOf(\SqlSemantics\Statement\Model\Sqlite\Value\OneselectWithSelectDistinctSelcollistFromWhereOptGroupbyOptHavingOptOrderbyOptLimitOpt_218e0475::class, $select);
        $where = new \SqlSemantics\Statement\Model\Sqlite\Value\WhereOptWithWhereExpr_93445e09(
            new \SqlSemantics\Statement\Model\Sqlite\Value\ExprWithExprEqNeExpr_49d16f16(
                new \SqlSemantics\Statement\Model\Sqlite\Value\ExprWithIdj_e1794d68('foo'),
                '=',
                new \SqlSemantics\Statement\Model\Sqlite\Value\TermWithInteger_298801b2('1'),
            ),
        );
        $changedSelect = $select->withWhere($where);
        $updated = $original->withCommand($command->withCmdx($changedSelect));
        self::assertSame('SELECT foo FROM items WHERE foo = 1', $updated->toString());
        self::assertSame('SELECT foo FROM items', $original->toString());
        self::assertSame($select->from, $changedSelect->from);
        self::assertSame($select->projections, $changedSelect->projections);
        self::assertSame('SELECT foo FROM items', $original->withCommand($command->withCmdx($changedSelect->withWhere($select->where)))->toString());
    }

    public function testToStringUsesIndependentlyConstructedData(): void
    {
        $transaction = new \SqlSemantics\Statement\Model\Sqlite\Value\TransOptWithTransaction_ea573324();
        $commit = new \SqlSemantics\Statement\Statement(new \SqlSemantics\Statement\Model\Sqlite\Value\CmdWithCommitEndTransOpt_ccca6149('COMMIT', $transaction));
        $end = new \SqlSemantics\Statement\Statement(new \SqlSemantics\Statement\Model\Sqlite\Value\CmdWithCommitEndTransOpt_ccca6149('END', $transaction));
        self::assertSame('COMMIT TRANSACTION', $commit->toString());
        self::assertSame('END TRANSACTION', $end->toString());
        self::assertSame('COMMIT TRANSACTION', $commit->toString());
    }

    public function testWithCommandKeepsTheCommentsAroundTheCommand(): void
    {
        $original = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("/*+ SeqScan(items) */ SELECT foo FROM items /* traceparent='00-abc' */");
        $replacement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT bar FROM items')->command;
        $updated = $original->withCommand($replacement);
        self::assertSame(['/*+ SeqScan(items) */'], $original->comments->before(\SqlSemantics\Statement\Statement::BEFORE));
        self::assertSame(["/* traceparent='00-abc' */"], $original->comments->before(\SqlSemantics\Statement\Statement::AFTER));
        self::assertSame("/*+ SeqScan(items) */ SELECT bar FROM items /* traceparent='00-abc' */", $updated->toString());
        self::assertSame("/*+ SeqScan(items) */ SELECT foo FROM items /* traceparent='00-abc' */", $original->toString());
    }

    public function testWithCommentsReplacesTheCommentsAroundTheSameCommand(): void
    {
        $original = new \SqlSemantics\Statement\Statement(new \SqlSemantics\Statement\Model\Sqlite\Value\CmdWithCommitEndTransOpt_ccca6149('COMMIT', new \SqlSemantics\Statement\Model\Sqlite\Value\TransOptWith_6ac05548()));
        $updated = $original->withComments(new \SqlSemantics\Statement\Comments([\SqlSemantics\Statement\Statement::BEFORE => ['-- before'], \SqlSemantics\Statement\Statement::AFTER => ['/* after */']]));
        self::assertSame("-- before\nCOMMIT /* after */", $updated->toString());
        self::assertSame('COMMIT', $original->toString());
        self::assertSame($original->command, $updated->command);
    }

    public function testToStringKeepsAnOptimizerHintAfterTheStatementKeyword(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT /*+ SET_VAR(sort_buffer_size=16M) */ id FROM users');
        self::assertSame('SELECT /*+ SET_VAR(sort_buffer_size=16M) */ id FROM users', $statement->toString());
        self::assertSame([], $statement->comments->positions());
    }

    public function testToStringKeepsExecutableCommentDelimitersAroundTheSqlTheyEnclose(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-8.4.7'))->analyze('SELECT /*!50700 1, */ 2, /*!99999 3, */ 4 # done');
        self::assertSame('SELECT /*!50700 1 , */ 2 , /*!99999 3, */ 4 # done', $statement->toString());
    }

    public function testToStringSeparatesAPrefixOperatorFromANegatedOperand(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT @ -1, @ x');
        self::assertSame('SELECT @ - 1 , @ x', $statement->toString());
    }

    public function testToStringAttachesVariableMarkersToTheirNames(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT @a, @@global.max_connections, @`b`, @'c'");
        self::assertSame("SELECT @a , @@GLOBAL .max_connections , @`b` , @'c'", $statement->toString());
    }
}
