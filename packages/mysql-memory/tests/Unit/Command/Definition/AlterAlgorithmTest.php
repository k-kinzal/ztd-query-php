<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\AlterAlgorithm;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(AlterAlgorithm::class)]
#[Small]
final class AlterAlgorithmTest extends TestCase
{
    #[DataProvider('providerChanges')]
    public function testCopiesRowsAccordingToEngineAndEffectiveChange(string $version, string $engine, bool $temporary, string $action, int $affected): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE ' . ($temporary ? 'TEMPORARY ' : '') . "TABLE t(a INT, b INT, KEY i(a)) ENGINE=$engine");
        $session->query('INSERT INTO t VALUES (1,2),(2,3)');
        $reply = $session->query('ALTER TABLE t ' . $action)[0];
        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame($affected, $reply->affectedRows);
        self::assertSame("Records: $affected  Duplicates: 0  Warnings: 0", $reply->info);
        self::assertSame(0, $reply->warnings);
        $rows = $session->query('SELECT a, b FROM t ORDER BY a')[0];
        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['1', '2'], ['2', '3']], $rows->rows);
    }

    /**
     * @return iterable<string, array{string, string, bool, string, int}>
     */
    public static function providerChanges(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            $version = substr($release->value, 6);
            foreach (['MyISAM', 'MEMORY', 'InnoDB'] as $engine) {
                foreach ([false, true] as $temporary) {
                    $actions = ['ADD INDEX j(b)' => true, 'DROP INDEX i' => true, 'DROP INDEX i, ADD INDEX j(a)' => true, 'DROP INDEX i, ADD INDEX i(b)' => true, 'DROP INDEX i, ADD INDEX i(a)' => false, 'MODIFY a INT' => false, 'ALTER a SET DEFAULT 3' => false];
                    if ($release !== GrammarRelease::MySql5651) {
                        $actions['RENAME INDEX i TO j'] = false;
                        $actions['RENAME KEY i TO j, ALGORITHM=INPLACE'] = false;
                    }
                    foreach ($actions as $action => $changed) {
                        if ($temporary && str_contains($action, 'ALGORITHM=INPLACE')) {
                            continue;
                        }
                        $affected = $temporary || ($engine !== 'InnoDB' && $changed) ? 2 : 0;
                        yield "$version/$engine/" . (int) $temporary . "/$action" => [$version, $engine, $temporary, $action, $affected];
                    }
                    if ($temporary) {
                        yield "$version/$engine/temporary/lock" => [$version, $engine, true, 'ADD INDEX j(b), LOCK=NONE', 2];
                        yield "$version/$engine/temporary/type" => [$version, $engine, true, 'MODIFY a BIGINT, LOCK=NONE', 2];
                    }
                    if (!in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) && !$temporary) {
                        yield "$version/$engine/instant/noop" => [$version, $engine, false, 'DROP INDEX i, ADD INDEX i(a), ALGORITHM=INSTANT', 0];
                        if ($engine !== 'InnoDB') {
                            yield "$version/$engine/instant/rename" => [$version, $engine, false, 'RENAME INDEX i TO j, ALGORITHM=INSTANT', 0];
                        }
                    }
                }
            }
        }
    }

    #[DataProvider('providerRestrictions')]
    public function testRefusesUnsupportedAlgorithmsBeforePublishingChanges(string $engine, bool $temporary, string $action, int $code, string $message): void
    {
        $session = (new Instance('8.4.7', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE ' . ($temporary ? 'TEMPORARY ' : '') . "TABLE t(a INT, b INT, KEY i(a)) ENGINE=$engine");
        $session->query('INSERT INTO t VALUES (1,2),(2,3)');
        try {
            $session->query('ALTER TABLE t ' . $action);
            self::fail('The requested algorithm must fail.');
        } catch (SqlError $error) {
            self::assertSame($code, $error->getCode());
            self::assertSame($message, $error->getMessage());
        }
        $rows = $session->query('SELECT a, b FROM t ORDER BY a')[0];
        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['1', '2'], ['2', '3']], $rows->rows);
    }

    /**
     * @return iterable<string, array{string, bool, string, int, string}>
     */
    public static function providerRestrictions(): iterable
    {
        foreach (['MyISAM', 'MEMORY', 'InnoDB'] as $engine) {
            foreach ([false, true] as $temporary) {
                $prefix = $engine . '/' . (int) $temporary;
                yield "$prefix/instant" => [$engine, $temporary, 'ADD INDEX j(b), ALGORITHM=INSTANT', 1845, 'ALGORITHM=INSTANT is not supported for this operation. Try ALGORITHM=' . ($temporary ? 'COPY' : 'COPY/INPLACE') . '.'];
                if ($temporary || $engine !== 'InnoDB') {
                    yield "$prefix/inplace" => [$engine, $temporary, 'DROP INDEX i, ALGORITHM=INPLACE', 1845, 'ALGORITHM=INPLACE is not supported for this operation. Try ALGORITHM=COPY.'];
                }
            }
            yield "$engine/copy-lock" => [$engine, false, 'ADD INDEX j(b), ALGORITHM=COPY, LOCK=NONE', 1846, 'LOCK=NONE is not supported. Reason: COPY algorithm requires a lock. Try LOCK=SHARED.'];
            if ($engine !== 'InnoDB') {
                yield "$engine/lock" => [$engine, false, 'ADD INDEX j(b), LOCK=NONE', 1845, 'LOCK=NONE is not supported for this operation. Try LOCK=SHARED.'];
                yield "$engine/type" => [$engine, false, 'MODIFY a BIGINT, ALGORITHM=INPLACE', 1845, 'ALGORITHM=INPLACE is not supported for this operation. Try ALGORITHM=COPY.'];
            }
        }
        yield 'InnoDB/rename-instant' => ['InnoDB', false, 'RENAME INDEX i TO j, ALGORITHM=INSTANT', 1845, 'ALGORITHM=INSTANT is not supported for this operation. Try ALGORITHM=COPY/INPLACE.'];
    }
    public function testColumnsRecognizesTypeChanges(): void
    {
        $session = (new Instance('8.4.7', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE TABLE u(a BIGINT)');
        $old = $session->instance->dictionary->table('d', 't');
        $new = $session->instance->dictionary->table('d', 'u');
        self::assertNotNull($old);
        self::assertNotNull($new);
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, microtime(true));
        self::assertSame([true, false], (new AlterAlgorithm())->columns($old->definition, $new->definition, [0], $context));
    }

    public function testTypeAlgorithmRefusesInplaceTypeChanges(): void
    {
        $session = (new Instance('8.4.7', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1846);
        $this->expectExceptionMessage('ALGORITHM=INPLACE is not supported. Reason: Cannot change column type INPLACE. Try ALGORITHM=COPY.');
        $session->query('ALTER TABLE t MODIFY a BIGINT, ALGORITHM=INPLACE');
    }

    public function testTemporaryRefusesInstantEvenWithoutIndexStorageChanges(): void
    {
        $session = (new Instance('8.4.7', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TEMPORARY TABLE t(a INT, KEY i(a))');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1845);
        $this->expectExceptionMessage('ALGORITHM=INSTANT is not supported for this operation. Try ALGORITHM=COPY.');
        $session->query('ALTER TABLE t DROP INDEX i, ADD INDEX i(a), ALGORITHM=INSTANT');
    }

    public function testEngineAlgorithmRefusesLockNoneForMyIsamTypeChanges(): void
    {
        $session = (new Instance('8.4.7', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT) ENGINE=MyISAM');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1845);
        $this->expectExceptionMessage('LOCK=NONE is not supported for this operation. Try LOCK=SHARED.');
        $session->query('ALTER TABLE t MODIFY a BIGINT, LOCK=NONE');
    }

    public function testChangedKeysFollowsColumnOriginsAndIndexRenames(): void
    {
        $session = (new Instance('8.4.7', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT, b INT, KEY i(a)); CREATE TABLE u(b INT, a INT, KEY j(a))');
        $old = $session->instance->dictionary->table('d', 't');
        $new = $session->instance->dictionary->table('d', 'u');
        self::assertNotNull($old);
        self::assertNotNull($new);
        $layout = \MySqlMemory\Command\Definition\TableLayout::of($old->definition);
        $change = new \MySqlMemory\Command\Definition\TableChange($layout, 't', 'd', GrammarRelease::MySql847);
        $rename = $session->analyze('ALTER TABLE t RENAME INDEX i TO j');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Alter\AlterTable::class, $rename);
        $change->apply($rename->commands);
        self::assertFalse((new AlterAlgorithm())->changedKeys($old->definition, $new->definition, [1, 0], $layout));
        self::assertTrue((new AlterAlgorithm())->changedKeys($old->definition, $new->definition, [0, 1], $layout));
    }

    public function testChangedKeysRetainsFunctionalSlotsThroughDefaultAndColumnAddition(): void
    {
        $session = (new Instance('8.4.7', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT, KEY i((a + 1))); INSERT INTO t VALUES (1), (2)');
        $replies = $session->query('ALTER TABLE t ALTER a SET DEFAULT 3, ALGORITHM=INSTANT; ALTER TABLE t ADD b INT, ALGORITHM=INSTANT');
        self::assertInstanceOf(Completion::class, $replies[0]);
        self::assertInstanceOf(Completion::class, $replies[1]);
        self::assertSame([0, 0], [$replies[0]->affectedRows, $replies[1]->affectedRows]);
        $rows = $session->query('SELECT a, b FROM t ORDER BY a')[0];
        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['1', null], ['2', null]], $rows->rows);
    }

    #[DataProvider('providerRenames')]
    public function testRenamedKeysDistinguishesMetadataChangesFromIdenticalReplacements(string $sql, bool $renamed): void
    {
        $session = (new Instance('8.4.7', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT, KEY i(a))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $layout = \MySqlMemory\Command\Definition\TableLayout::of($table->definition);
        $change = new \MySqlMemory\Command\Definition\TableChange($layout, 't', 'd', GrammarRelease::MySql847);
        $alter = $session->analyze('ALTER TABLE t ' . $sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Alter\AlterTable::class, $alter);
        $change->apply($alter->commands);
        self::assertSame($renamed, (new AlterAlgorithm())->renamedKeys($layout));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerRenames(): iterable
    {
        yield 'renamed' => ['RENAME INDEX i TO j', true];
        yield 'replaced' => ['DROP INDEX i, ADD INDEX i(a)', false];
    }

}
