<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\CommandPrefix;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommandPrefix::class)]
#[Small]
final class CommandPrefixTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerTableCommands(): iterable
    {
        yield 'create' => ['CREATE TABLE t', true];
        yield 'load' => ['LOAD DATA', true];
        yield 'alter' => ['ALTER TABLE t', true];
        yield 'alter ignore' => ['ALTER IGNORE TABLE t', true];
        yield 'database' => ['ALTER DATABASE d', false];
        yield 'selection' => ['SELECT 1', false];
    }

    #[DataProvider('providerTableCommands')]
    public function testTableCommandDistinguishesTablePrefixesFromDatabaseAlterations(string $sql, bool $expected): void
    {
        $parser = (new Instance('5.7.44'))->connect()->semantics()->parser();
        $prefix = new CommandPrefix();
        self::assertSame($expected, $prefix->tableCommand($parser->tokenize($sql)));
    }

    /**
     * @return iterable<string, array{string, string, int|null}>
     */
    public static function providerPrefixes(): iterable
    {
        foreach (['5.6.51', '5.7.44'] as $release) {
            foreach ([
                'CREATE TEMPORARY TEMPORARY TABLE t (a INT) SECONDARY_ENGINE NULL' => 1046,
                'CREATE TEMPORARY TEMPORARY TABLE d.t (a INT) SECONDARY_ENGINE NULL' => null,
                "LOAD DATA INFILE 'f' INTO TABLE t IGNORE 1.5 LINES" => 1046,
                "LOAD DATA INFILE 'f' INTO TABLE t (" => 1046,
                "LOAD DATA INFILE 'f' INTO TABLE t PARTITION (p) (" => 1046,
                "LOAD DATA INFILE 'f' INTO TABLE d.t IGNORE 1.5 LINES" => null,
                "LOAD DATA INFILE 'f' INTO TABLE t. IGNORE 1.5 LINES" => null,
                "LOAD DATA INFILE 'f' INTO TABLE t.*" => null,
                "LOAD DATA INFILE 'f' INTO TABLE t PARTITION (" => null,
                "LOAD DATA INFILE 'f' COUNT 1.5 INTO TABLE t" => null,
            ] as $sql => $code) {
                yield $release . ' ' . $sql => [$release, $sql, $code];
            }
        }
        yield 'modern load' => ['8.4.7', "LOAD DATA INFILE 'f' INTO TABLE t (", null];
        yield 'modern database option' => ['8.4.7', 'ALTER DATABASE d READ ONLY 2 +', 1064];
        yield 'legacy password comma' => ['5.7.44', "SET PASSWORD = 'pw', @@time_zone = SYSTEM", 1064];
        yield 'unrelated command' => ['5.6.51', 'SELECT 1 FROM t (', null];
    }

    public function testFormatRetainsLegacyUnknownNamesBeforeLaterInput(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $error = (new CommandPrefix())->format("EXPLAIN FORMAT = 'yaml' SELECT ?", 30, $session);

        self::assertNotNull($error);
        self::assertSame([1791, "Unknown EXPLAIN format name: 'yaml'"], [$error->getCode(), $error->getMessage()]);
        self::assertNull((new CommandPrefix())->format('EXPLAIN FORMAT = JSON SELECT ?', 29, $session));
    }

    public function testApplicableSeparatesDatabaseOptionsFromOtherAlterCommands(): void
    {
        $session = (new Instance())->connect();
        $parser = $session->semantics()->parser();
        $prefix = new CommandPrefix();

        self::assertTrue($prefix->applicable($parser->tokenize('ALTER SCHEMA d READ ONLY 2'), $session));
        self::assertFalse($prefix->applicable($parser->tokenize('ALTER TABLE t ADD a INT'), $session));
        self::assertFalse($prefix->applicable($parser->tokenize('SELECT 1'), $session));
    }

    #[DataProvider('providerPrefixes')]
    public function testBeforeResolvesOnlyCompletedLegacyLoadTargets(string $release, string $sql, ?int $code): void
    {
        $session = (new Instance($release))->connect();
        $parser = $session->semantics()->parser();
        try {
            $parser->parse($sql);
            self::fail('This regression must contain a syntax error.');
        } catch (\SqlParser\Parser\SyntaxException $failure) {
            $error = (new CommandPrefix())->before($parser->tokenize($sql), $sql, $failure->token->offset, $session);
            self::assertSame($code, $error?->getCode());
            self::assertSame([], $session->diagnostics->conditions);
        }
    }

    public function testBeforeKeepsAuthenticationErrorsAheadOfLaterRevokeSyntax(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $sql = "REVOKE ALL, GRANT OPTION FROM u IDENTIFIED BY 'pw' IGNORE UNKNOWN USER";
        $error = (new CommandPrefix())->before($session->semantics()->parser()->tokenize($sql), $sql, (int) strpos($sql, 'IGNORE'), $session);

        self::assertSame(1064, $error?->getCode());
        self::assertStringContainsString("near ''pw' IGNORE UNKNOWN USER'", $error->getMessage());
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testBeforeKeepsSyntaxErrorsWhenTheDatabaseIsSelected(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->use('mysql');
        $sql = "LOAD DATA INFILE 'f' INTO TABLE t (";

        self::assertNull((new CommandPrefix())->before($session->semantics()->parser()->tokenize($sql), $sql, strlen($sql), $session));
    }

    public function testBeforeLeavesRejectedOperandsToTheirCompletedProductionRules(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $sql = 'SET NAMES=1 TRAILING';

        self::assertNull((new CommandPrefix())->before($session->semantics()->parser()->tokenize($sql), $sql, strlen($sql), $session));
    }

    public function testCompletedRequiresADatabaseForUnqualifiedCreation(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $model = new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')));

        self::assertSame(1046, (new CommandPrefix())->completed($model, null, '', $session)?->getCode());
        $session->use('mysql');
        self::assertNull((new CommandPrefix())->completed($model, null, '', $session));
    }
}
