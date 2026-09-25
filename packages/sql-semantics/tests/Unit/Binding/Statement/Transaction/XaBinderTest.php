<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Transaction\XaBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Transaction\Xa\XaEndStatement;
use SqlSemantics\Model\Statement\Transaction\Xa\XaStartStatement;
use SqlSemantics\Model\Transaction\Xa\EndMode;
use SqlSemantics\Model\Transaction\Xa\StartMode;
use SqlSemantics\SchemaBuilder;

#[CoversClass(XaBinder::class)]
#[Medium]
final class XaBinderTest extends TestCase
{
    #[DataProvider('providerVersions')]
    public function testBindPreservesTheSameTransactionOperandsAcrossGrammarReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind("XA /* request */ BEGIN X'00ff', B'0101', 0x2a JOIN");
        self::assertInstanceOf(XaStartStatement::class, $statement);
        self::assertSame(StartMode::Join, $statement->mode);
        self::assertNotNull($statement->transactionId->branch);
        self::assertNotNull($statement->transactionId->branch->format);
        self::assertSame("X'00ff'", $statement->transactionId->global->text);
        self::assertSame("B'0101'", $statement->transactionId->branch->qualifier->text);
        self::assertSame('0x2a', $statement->transactionId->branch->format->spelling);
        self::assertSame("XA START X'00ff', B'0101', 0x2a JOIN", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerVersions(): iterable
    {
        yield '5.6' => ['mysql-5.6.51'];
        yield '5.7' => ['mysql-5.7.44'];
        yield '8.0' => ['mysql-8.0.44'];
        yield '8.1' => ['mysql-8.1.0'];
        yield '8.2' => ['mysql-8.2.0'];
        yield '8.3' => ['mysql-8.3.0'];
        yield '8.4' => ['mysql-8.4.7'];
        yield '9.0' => ['mysql-9.0.1'];
        yield '9.1' => ['mysql-9.1.0'];
    }

    public function testBindSeparatesPolicyKeywordsFromIdentifierContents(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("XA END 'SUSPEND FOR MIGRATE' SUSPEND");
        self::assertInstanceOf(XaEndStatement::class, $statement);
        self::assertSame(EndMode::Suspend, $statement->mode);
        self::assertSame("'SUSPEND FOR MIGRATE'", $statement->transactionId->global->text);
    }

    public function testIdentifierDiagnosesOversizedComponentsEvenInNonstrictBinding(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('bounded byte-string components');
        $binder->bind("XA START '" . str_repeat('a', 65) . "'", strict: false);
    }

    public function testIdentifierRetainsNumericFormatSpellingWithoutEvaluation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("XA START 'g', 'b', 1.5e2");
        self::assertInstanceOf(XaStartStatement::class, $statement);
        self::assertNotNull($statement->transactionId->branch);
        self::assertNotNull($statement->transactionId->branch->format);
        self::assertSame('1.5e2', $statement->transactionId->branch->format->spelling);
        self::assertSame("XA START 'g', 'b', 1.5e2", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[DataProvider('providerVersions')]
    public function testBindDoesNotApplyTransactionStateAcrossStatements(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statements = $binder->bindAll("XA START 'g'; XA COMMIT 'different' ONE PHASE");
        self::assertInstanceOf(XaStartStatement::class, $statements[0]);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\Xa\XaCommitStatement::class, $statements[1]);
        self::assertSame("'different'", $statements[1]->transactionId->global->text);
    }

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerBindClassifiesEachXaOperation(): array
    {
        return [
            [Dialect::MySql, null, 'xa start \'a\' join', [XaStartStatement::class, 'XA START \'a\' JOIN']],
            [Dialect::MySql, null, 'XA BEGIN \'a\' RESUME', [XaStartStatement::class, 'XA START \'a\' RESUME']],
            [Dialect::MySql, null, 'XA END \'a\' SUSPEND', [XaEndStatement::class, 'XA END \'a\' SUSPEND']],
            [Dialect::MySql, null, 'XA END \'a\', \'b\', 3', [XaEndStatement::class, 'XA END \'a\', \'b\', 3']],
            [Dialect::MySql, null, 'XA PREPARE \'a\'', [\SqlSemantics\Model\Statement\Transaction\Xa\XaPrepareStatement::class, 'XA PREPARE \'a\'']],
            [Dialect::MySql, null, 'xa commit \'a\' one phase', [\SqlSemantics\Model\Statement\Transaction\Xa\XaCommitStatement::class, 'XA COMMIT \'a\' ONE PHASE']],
            [Dialect::MySql, null, 'XA COMMIT \'a\'', [\SqlSemantics\Model\Statement\Transaction\Xa\XaCommitStatement::class, 'XA COMMIT \'a\'']],
            [Dialect::MySql, null, 'XA ROLLBACK \'a\'', [\SqlSemantics\Model\Statement\Transaction\Xa\XaRollbackStatement::class, 'XA ROLLBACK \'a\'']],
            [Dialect::MySql, null, 'XA RECOVER', [\SqlSemantics\Model\Statement\Transaction\Xa\XaRecoverStatement::class, 'XA RECOVER']],
            [Dialect::MySql, null, 'XA RECOVER CONVERT XID', [\SqlSemantics\Model\Statement\Transaction\Xa\XaRecoverStatement::class, 'XA RECOVER CONVERT XID']],
            [Dialect::MySql, null, 'xa recover convert xid', [\SqlSemantics\Model\Statement\Transaction\Xa\XaRecoverStatement::class, 'XA RECOVER CONVERT XID']],
        ];
    }

    #[DataProvider('providerBindClassifiesEachXaOperation')]
    public function testBindClassifiesEachXaOperation(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build()))->bind($sql, strict: false);
        self::assertSame($expected, [$statement::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }

    public function testIdentifierReadsTheGlobalAndBranchParts(): void
    {
        $root = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse("XA END 'a', 'b', 3");
        $id = XaBinder::identifier(\SqlSemantics\Ast\Tree::outer($root, ['xid'])[0]);
        self::assertSame("'a'", $id->global->spelling());
        self::assertSame("'b'", $id->branch?->qualifier->spelling());
    }
}
