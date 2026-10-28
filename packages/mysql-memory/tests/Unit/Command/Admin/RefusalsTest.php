<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin;

use MySqlMemory\Command\Admin\Refusals;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\PriorityOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageRule;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\MissingTable;

#[CoversClass(Refusals::class)]
#[Small]
final class RefusalsTest extends TestCase
{
    public function testHandlesTheProblemsOfAdministrativeStatements(): void
    {
        self::assertSame([true, false], [Refusals::handles(new StorageProblem(StorageRule::WrongSize, 'FILE_BLOCK_SIZE')), Refusals::handles(new MissingTable(new QualifiedName(new Name('t'))))]);
    }

    public function testErrorNamesARepeatedEngineAsStorageEngine(): void
    {
        $error = (new Refusals())->error(new StorageProblem(StorageRule::RepeatedOption, 'ENGINE'), null, '');

        self::assertSame([1527, 'It is not allowed to specify STORAGE ENGINE more than once'], [$error->getCode(), $error->getMessage()]);
    }

    public function testErrorRefusesASizeOfMoreThanTwoBillion(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1532);
        $this->expectExceptionMessage("The size number was correct but we don't allow the digit part to be more than 2 billion");

        $session->query('CREATE TABLESPACE t FILE_BLOCK_SIZE = 3000000000G');
    }

    /**
     * @return iterable<string, array{\SqlSemantics\Statement\Fact\Diagnostic, int, string}>
     */
    public static function providerFactorAndAccountRefusals(): iterable
    {
        yield 'duplicate' => [new \SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidFactorPair(true), 4063, 'Authentication factor should be different.'];
        yield 'order' => [new \SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidFactorPair(false), 4062, '2 factor authentication method should be added before 3 factor authentication method.'];
        yield 'host' => [new \SqlSemantics\Platform\MySql\Statement\Account\Problem\AccountNameFailure('bad@host', true), 1105, "Malformed hostname (illegal symbol: '@')"];
        yield 'user length' => [new \SqlSemantics\Platform\MySql\Statement\Account\Problem\AccountNameFailure(str_repeat('あ', 33), false, 32), 1470, "String '" . str_repeat('あ', 23) . "?' is too long for user name (should be no longer than 32)"];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerFactorAndAccountRefusals')]
    public function testErrorMapsTypedFactorAndAccountRefusals(\SqlSemantics\Statement\Fact\Diagnostic $problem, int $code, string $message): void
    {
        self::assertTrue(Refusals::handles($problem));
        $error = (new Refusals())->error($problem, null, '');
        self::assertSame([$code, $message], [$error->getCode(), $error->getMessage()]);
    }

    public function testSettingFormatsARefusedUuidWithoutAStatement(): void
    {
        $text = new \SqlSemantics\Platform\MySql\Statement\Literal\Text(str_repeat('x', 127) . '猫');
        $error = (new Refusals())->setting(new RefusedSetting(ReplicationError::InvalidUuid, text: $text), null, 'unrelated input');

        self::assertSame([1525, "Incorrect UUID value: '" . str_repeat('x', 127) . "?'"], [$error->getCode(), $error->getMessage()]);
    }

    public function testSettingRetainsAnEarlierDelayWithoutAStatement(): void
    {
        $error = (new Refusals())->setting(new RefusedSetting(ReplicationError::DelayOutOfRange, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('2147483648')), null, 'unrelated input');

        self::assertSame([1729, 'The requested value 2147483648 for the source delay exceeds the maximum 2147483647'], [$error->getCode(), $error->getMessage()]);
    }

    public function testSpatialNamesTheStatementOfAnSridOutOfRange(): void
    {
        $reply = (new Instance())->connect()->run('DROP SPATIAL REFERENCE SYSTEM 4294967296')[0];
        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame(1690, $reply->getCode());
        $error = (new Refusals())->spatial(new SpatialProblem(SpatialRule::IdentifierOutOfRange), null, \SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialCommand::Drop);

        self::assertSame([1690, "SRID value is out of range in 'DROP SPATIAL REFERENCE SYSTEM'", '22003'], [$error->getCode(), $error->getMessage(), $error->sqlState()]);
    }

    public function testSpatialNamesTheLimitOfALongAttribute(): void
    {
        $error = (new Refusals())->spatial(new SpatialProblem(SpatialRule::TooLong, SpatialAttributeKind::Name), null);

        self::assertSame([3718, 'Attribute NAME is too long. The maximum length is 80 characters.', 'SR006'], [$error->getCode(), $error->getMessage(), $error->sqlState()]);
    }

    public function testSpatialReportsARepeatedAttributeFirst(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3709);
        $this->expectExceptionMessage('Multiple definitions of attribute ORGANIZATION.');

        $session->query("CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 2147483648 ORGANIZATION 'text' IDENTIFIED BY 0x0f DESCRIPTION 'text' ORGANIZATION 'text' IDENTIFIED BY 1 NAME 'text' DESCRIPTION 'text' DEFINITION 'x' NAME 'F*N' ORGANIZATION 'a''b' IDENTIFIED BY 0");
    }

    public function testPriorityNamesTheGroupAndTheValue(): void
    {
        $problem = new PriorityOutOfRange(new \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupName(new Name('FLUSH')), \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupKind::System, new \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority(false, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('000000000040')));
        $error = (new Refusals())->priority($problem);

        self::assertSame([3654, 'Invalid thread priority value 40 for System resource group FLUSH. Allowed range is [-20, 0].'], [$error->getCode(), $error->getMessage()]);
    }

    public function testReplicationQuotesTheNextBinaryLogIndex(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3567);
        $this->expectExceptionMessage("The requested value '2000000001' for the next binary log index is out of range. Please use a value between '1' and '2000000000'.");

        $session->query('RESET BINARY LOGS AND GTIDS TO 2000000001');
    }

    public function testReplicationRefusesAPasswordOfMoreThan32Characters(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3972);

        $session->query("START GROUP_REPLICATION PASSWORD = '" . str_repeat('p', 33) . "'");
    }

    public function testReplicationRefusesCredentialsForTheApplierAlone(): void
    {
        $error = (new Refusals())->replication(ReplicationError::ApplierWithCredentials, null, '');

        self::assertSame([1763, 'Setting authentication options is not possible when only the Replica SQL Thread is being started.'], [$error->getCode(), $error->getMessage()]);
    }

    public function testSwitchQuotesTheStatementFromTheValue(): void
    {
        $text = "CHANGE REPLICATION SOURCE TO GTID_ONLY = 2 FOR CHANNEL 'c'";
        $error = (new Refusals())->switch(null, $text);

        self::assertSame([1064, "You have an error in your CHANGE REPLICATION SOURCE syntax; GTID_ONLY only accepts values 0 or 1 near '2 FOR CHANNEL 'c'' at line 1"], [$error->getCode(), $error->getMessage()]);
    }

    public function testNearCutsTheTextTo80Bytes(): void
    {
        self::assertSame([str_repeat('a', 80), ''], [(new Refusals())->near('x' . str_repeat('a', 90), '/a/'), (new Refusals())->near('x', '/y/')]);
    }

    public function testLineFeedFindsTheTextWithALineFeed(): void
    {
        $statement = new \SqlSemantics\Platform\MySql\Statement\Literal\Text("a\nb");

        self::assertSame("a\nb", (new Refusals())->lineFeed($statement));
    }

    public function testOptionAnswersTheValueOfASourceOption(): void
    {
        $statement = (new Instance())->connect()->analyze("CHANGE REPLICATION SOURCE TO SOURCE_DELAY = 2147483648, ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS = '3e11fa47-71ca-11e1-9e33-c80aa9429562'");
        $refusals = new Refusals();

        self::assertSame(['2147483648', '3e11fa47-71ca-11e1-9e33-c80aa9429562', ''], [$refusals->option($statement, SourceOptionKind::Delay), $refusals->option($statement, SourceOptionKind::AssignGtidsToAnonymousTransactions), $refusals->option($statement, SourceOptionKind::Host)]);
    }

    public function testFirstAnswersTheFileNumberOfReset(): void
    {
        $statement = (new Instance())->connect()->analyze('RESET BINARY LOGS AND GTIDS TO 06344632');

        self::assertSame(['6344632', '0'], [(new Refusals())->first($statement), (new Refusals())->first(null)]);
    }

    public function testErrorAnswersTheReplicationErrorOfARefusedSetting(): void
    {
        $error = (new Refusals())->error(new RefusedSetting(ReplicationError::WildPattern), null, '');

        self::assertSame([3067, "Supplied filter list contains a value which is not in the required format 'db_pattern.table_pattern'"], [$error->getCode(), $error->getMessage()]);
    }
    public function testErrorReportsTheSourcePositionOfAFractionalSrid(): void
    {
        $session = (new Instance())->connect();
        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage("Only integers allowed as number here near '1e0)' at line 1");

        $session->query('CREATE TABLE t(a POINT SRID 1e0)');
    }

    public function testSpatialUsesTheLineFeedDiagnosticOperand(): void
    {
        $error = (new Refusals())->spatial(new SpatialProblem(SpatialRule::LineFeed, SpatialAttributeKind::Name, "a\nb"), null);

        self::assertSame([1525, 'HY000', "Incorrect argument contains not-allowed LF value: 'a\nb'"], [$error->getCode(), $error->sqlState(), $error->getMessage()]);
    }
    public function testSettingFormatsRetainedOperandsWithoutSqlOrAStatement(): void
    {
        $line = new RefusedSetting(ReplicationError::LineFeed, text: new \SqlSemantics\Platform\MySql\Statement\Literal\Text("a\nb"));
        $index = new RefusedSetting(ReplicationError::FileNumberOutOfRange, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('2000000001'));
        $refusals = new Refusals();

        self::assertSame("Incorrect argument contains not-allowed LF value: 'a\nb'", $refusals->setting($line, null, 'unrelated invalid input')->getMessage());
        self::assertSame("The requested value '2000000001' for the next binary log index is out of range. Please use a value between '1' and '2000000000'.", $refusals->setting($index, null, 'unrelated invalid input')->getMessage());
    }

    public function testDelayUsesTheRequestedReplicationVocabulary(): void
    {
        $rule = new Refusals();
        self::assertSame('The requested value 2147483648 for the master delay exceeds the maximum 2147483647', $rule->delay('2147483648', true)->getMessage());
        self::assertSame('The requested value 2147483648 for the source delay exceeds the maximum 2147483647', $rule->delay('2147483648', false)->getMessage());
    }

}
