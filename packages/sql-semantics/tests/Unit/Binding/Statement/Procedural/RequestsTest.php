<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Procedural\Requests;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Loading\ImportTableStatement;
use SqlSemantics\Model\Statement\Locking\LockInstanceStatement;
use SqlSemantics\Model\Statement\Locking\UnlockInstanceStatement;
use SqlSemantics\Model\Statement\Procedural\HelpStatement;
use SqlSemantics\Model\Statement\Server\UnlockTablesStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;

#[CoversClass(Requests::class)]
#[Medium]
final class RequestsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testHelpDecodesIdentifierAndStringTopicsAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $identifier = $binder->bind('HELP `a``b`');
        $text = $binder->bind("HELP 'it''s'");
        self::assertInstanceOf(HelpStatement::class, $identifier);
        self::assertInstanceOf(HelpStatement::class, $text);
        self::assertSame('a`b', $identifier->topic);
        self::assertSame("it's", $text->topic);
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($identifier));
        self::assertInstanceOf(HelpStatement::class, $rebound);
        self::assertSame('a`b', $rebound->topic);
    }

    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testImportKeepsEveryFilePatternSpelling(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind("IMPORT TABLE FROM 'a''.sdi', \"b.sdi\"");
        self::assertInstanceOf(ImportTableStatement::class, $statement);
        self::assertSame(["'a''.sdi'", '"b.sdi"'], array_map(static fn ($file): string => $file->text, $statement->files));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testInstanceSeparatesBackupLocksFromTableLocks(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        self::assertInstanceOf(LockInstanceStatement::class, $binder->bind('LOCK INSTANCE FOR BACKUP'));
        self::assertInstanceOf(UnlockInstanceStatement::class, $binder->bind('UNLOCK INSTANCE'));
        self::assertInstanceOf(UnlockTablesStatement::class, $binder->bind('UNLOCK TABLES'));
    }

    public function testTextRejectsANonLiteralOperand(): void
    {
        $this->expectException(SemanticException::class);
        Requests::text(new Node('TEXT_STRING_sys', 0, []));
    }
}
