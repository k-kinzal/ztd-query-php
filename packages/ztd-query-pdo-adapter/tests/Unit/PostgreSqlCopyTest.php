<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\PdoConnection;
use ZtdQuery\Adapter\Pdo\PostgreSqlCopy;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Platform\CopySupport;
use ZtdQuery\Platform\CopyTarget;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Session;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(PostgreSqlCopy::class)]
#[UsesClass(PdoConnection::class)]
#[UsesClass(ZtdPdoException::class)]
final class PostgreSqlCopyTest extends TestCase
{
    public function testGuardRawLetsAStatementThatIsNotACopyThrough(): void
    {
        [$copy] = $this->providerCopyOverSqlite();

        $this->expectNotToPerformAssertions();

        $copy->guardRaw('SELECT 1');
    }

    public function testGuardRawRefusesACopyWrittenAsRawSql(): void
    {
        [$copy] = $this->providerCopyOverSqlite();

        $this->expectException(ZtdPdoException::class);
        $this->expectExceptionMessage('ZTD Write Protection: Raw PostgreSQL COPY');

        $copy->guardRaw('COPY copy_target FROM STDIN');
    }

    public function testGuardRawLetsEverythingThroughWhereTheDialectHasNoCopy(): void
    {
        $copy = new PostgreSqlCopy(new PDO('sqlite::memory:'), $this->providerSession(null, new TableDefinitionRegistry()));

        $this->expectNotToPerformAssertions();

        $copy->guardRaw('COPY copy_target FROM STDIN');
    }

    public function testToArrayAnswersOneEncodedLinePerRow(): void
    {
        [$copy] = $this->providerCopyOverSqlite();

        self::assertSame(["1\tada\n", "2\tgrace\n"], $copy->toArray('copy_target'));
    }

    public function testToArrayRefusesATableNameThatIsNotAString(): void
    {
        [$copy] = $this->providerCopyOverSqlite();

        $this->expectExceptionMessage('PostgreSQL COPY argument $tableName must be a string, int given.');

        $copy->toArray(1);
    }

    public function testFromArrayWritesEveryLineItIsGiven(): void
    {
        [$copy, $pdo] = $this->providerCopyOverSqlite();

        $written = $copy->fromArray('copy_target', ["3\tlinus\n"]);

        self::assertSame([true, 3], [$written, $this->providerRowCount($pdo)]);
    }

    public function testFromArrayWritesNothingWhereItIsGivenNothing(): void
    {
        [$copy, $pdo] = $this->providerCopyOverSqlite();

        $written = $copy->fromArray('copy_target', []);

        self::assertSame([true, 2], [$written, $this->providerRowCount($pdo)]);
    }

    public function testFromArrayRefusesALineThatIsNotAString(): void
    {
        [$copy] = $this->providerCopyOverSqlite();

        $this->expectExceptionMessage('PostgreSQL COPY rows must be strings, int given.');

        $copy->fromArray('copy_target', [1]);
    }

    public function testFromArrayRefusesALineThatDoesNotFitTheTable(): void
    {
        [$copy] = $this->providerCopyOverSqlite();

        $this->expectExceptionMessage('PostgreSQL COPY row has 1 fields, but 2 fields are required.');

        $copy->fromArray('copy_target', ["3\n"]);
    }

    public function testToFileWritesEveryEncodedLineToTheFile(): void
    {
        [$copy] = $this->providerCopyOverSqlite();
        $path = tempnam(sys_get_temp_dir(), 'ztd');

        $written = $copy->toFile('copy_target', $path === false ? '' : $path);

        self::assertSame([true, "1\tada\n2\tgrace\n"], [$written, file_get_contents($path === false ? '' : $path)]);
    }

    public function testFromFileWritesEveryLineTheFileHolds(): void
    {
        [$copy, $pdo] = $this->providerCopyOverSqlite();
        $path = tempnam(sys_get_temp_dir(), 'ztd');
        file_put_contents($path === false ? '' : $path, "3\tlinus\n4\tdennis\n");

        $written = $copy->fromFile('copy_target', $path === false ? '' : $path);

        self::assertSame([true, 4], [$written, $this->providerRowCount($pdo)]);
    }

    public function testFromFileWritesNothingWhereThereIsNoFileToRead(): void
    {
        [$copy] = $this->providerCopyOverSqlite();

        self::assertFalse($copy->fromFile('copy_target', sys_get_temp_dir() . '/ztd-no-such-file'));
    }

    public function testTargetAnswersWhatTheCopyIsWrittenAgainst(): void
    {
        [$copy] = $this->providerCopyOverSqlite();

        [, $target] = $copy->target('copy_target', null);

        self::assertSame(['id', 'value'], $target->columns);
    }

    public function testTargetRefusesADialectThatHasNoCopy(): void
    {
        $copy = new PostgreSqlCopy(new PDO('sqlite::memory:'), $this->providerSession(null, new TableDefinitionRegistry()));

        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $copy->target('copy_target', null);
    }

    public function testTargetRefusesATableNothingHasDescribed(): void
    {
        [$copy] = $this->providerCopyOverSqlite();

        $this->expectExceptionMessage('PostgreSQL COPY cannot resolve the schema for table "absent".');

        $copy->target('absent', null);
    }

    public function testStringArgumentAnswersAStringAsItStands(): void
    {
        [$copy] = $this->providerCopyOverSqlite();

        self::assertSame('value', $copy->stringArgument('value', 'separator'));
    }

    public function testStringArgumentRefusesWhatIsNotAString(): void
    {
        [$copy] = $this->providerCopyOverSqlite();

        $this->expectExceptionMessage('PostgreSQL COPY argument $separator must be a string, null given.');

        $copy->stringArgument(null, 'separator');
    }

    public function testOptionalStringArgumentAnswersNothingWhereNoneWasGiven(): void
    {
        [$copy] = $this->providerCopyOverSqlite();

        self::assertNull($copy->optionalStringArgument(null, 'fields'));
    }

    public function testOptionalStringArgumentRefusesWhatIsNeitherStringNorNull(): void
    {
        [$copy] = $this->providerCopyOverSqlite();

        $this->expectExceptionMessage('PostgreSQL COPY argument $fields must be a string, float given.');

        $copy->optionalStringArgument(1.5, 'fields');
    }

    /**
     * @return array{PostgreSqlCopy, PDO} The COPY, and the connection it runs on
     */
    public function providerCopyOverSqlite(): array
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE copy_target (id INTEGER, value TEXT)');
        $pdo->exec("INSERT INTO copy_target (id, value) VALUES (1, 'ada'), (2, 'grace')");

        $registry = new TableDefinitionRegistry();
        $registry->register('copy_target', new TableDefinition(
            ['id', 'value'],
            ['id' => 'INTEGER', 'value' => 'TEXT'],
            ['id'],
            [],
            [],
        ));

        return [new PostgreSqlCopy($pdo, $this->providerSession($this->providerCopySupport(), $registry)), $pdo];
    }

    /**
     * @return CopySupport What writes the SELECT and INSERT a COPY stands for
     */
    public function providerCopySupport(): CopySupport
    {
        $copySupport = static::createStub(CopySupport::class);
        $copySupport->method('tableName')->willReturnCallback(static fn (string $relation): string => $relation);
        $copySupport->method('target')->willReturnCallback(
            static fn (string $relation, ?string $fields, TableDefinition $definition): CopyTarget => new CopyTarget([$relation], ['id', 'value']),
        );
        $copySupport->method('selectSql')->willReturn('SELECT id, value FROM copy_target');
        $copySupport->method('insertSql')->willReturnCallback(
            static fn (CopyTarget $target, int $rowCount): string => 'INSERT INTO copy_target (id, value) VALUES '
                . implode(', ', array_fill(0, $rowCount, '(?, ?)')),
        );
        $copySupport->method('encodeRow')->willReturnCallback(
            static function (array $values, string $separator): string {
                $texts = [];
                foreach ($values as $value) {
                    $texts[] = is_scalar($value) ? (string) $value : '';
                }

                return implode($separator, $texts) . "\n";
            },
        );
        $copySupport->method('decodeRow')->willReturnCallback(
            static fn (string $row, string $separator): array => $separator === ''
                ? [rtrim($row, "\n")]
                : explode($separator, rtrim($row, "\n")),
        );
        $copySupport->method('isCopyStatement')->willReturnCallback(
            static fn (string $sql): bool => str_starts_with($sql, 'COPY '),
        );

        return $copySupport;
    }

    /**
     * @param CopySupport|null $copySupport What writes COPY, or null for a dialect that has none
     * @param TableDefinitionRegistry $registry What has described the tables
     *
     * @return Session The session
     */
    public function providerSession(?CopySupport $copySupport, TableDefinitionRegistry $registry): Session
    {
        return new Session(
            static::createStub(SqlRewriter::class),
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            new PdoConnection(new PDO('sqlite::memory:')),
            null,
            $registry,
            $copySupport,
        );
    }

    /**
     * @param PDO $pdo Connection to count on
     *
     * @return int The number of rows
     */
    public function providerRowCount(PDO $pdo): int
    {
        $statement = $pdo->query('SELECT COUNT(*) FROM copy_target');

        return $statement === false ? -1 : (int) $statement->fetchColumn();
    }
}
