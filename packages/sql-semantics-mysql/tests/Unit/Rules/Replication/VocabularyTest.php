<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Replication\Vocabulary;

#[CoversClass(Vocabulary::class)]
#[Medium]
final class VocabularyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        yield 'start with a log coordinate' => ['8.0.44', "START SLAVE UNTIL MASTER_LOG_POS=1, MASTER_LOG_FILE='f.1'", ['START SLAVE', 'MASTER_LOG_POS', 'MASTER_LOG_FILE']];
        yield 'stop' => ['8.0.44', 'STOP SLAVE', ['STOP SLAVE']];
        yield 'status' => ['8.0.44', 'SHOW SLAVE STATUS', ['SHOW SLAVE STATUS']];
        yield 'hosts' => ['8.0.44', 'SHOW SLAVE HOSTS', ['SHOW SLAVE HOSTS']];
        yield 'reset occurrences' => ['8.0.44', 'RESET SLAVE, SLAVE', ['RESET SLAVE', 'RESET SLAVE']];
        yield 'source' => ['8.0.44', "CHANGE MASTER TO MASTER_HOST='h'", ['CHANGE MASTER', 'MASTER_HOST']];
        yield 'option under current header' => ['8.0.44', 'CHANGE REPLICATION SOURCE TO MASTER_COMPRESSION_ALGORITHMS=\'zlib\'', ['MASTER_COMPRESSION_ALGORITHM']];
        yield 'legacy release' => ['5.7.44', 'STOP SLAVE', []];
        yield 'current vocabulary' => ['8.0.44', 'STOP REPLICA', []];
    }

    /**
     * @param list<string> $names The deprecated occurrences in derivation order
     */
    #[DataProvider('providerStatements')]
    public function testDerivePublishesVocabularyBeforeExecution(string $release, string $sql, array $names): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-' . $release))->analyze($sql);
        $messages = array_map(static fn ($warning): string => $warning->message(), $operation->warnings());

        self::assertSame($names, array_map(static fn (string $message): string => explode("'", $message)[1], $messages));
    }

    public function testDeriveRetainsVocabularyNoticesBeforeARefusedUntil(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-8.0.44');
        $sql = 'START SLAVE UNTIL MASTER_LOG_POS=1';
        $notices = (new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed(new \SqlParser\MySql\MySqlParser('mysql-8.0.44'), $sql, strlen($sql), $semantics->profile());
        $warnings = array_values(array_filter(array_column($notices, 1), static fn ($notice): bool => $notice instanceof \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation));
        self::assertSame(['START SLAVE', 'MASTER_LOG_POS'], array_map(static fn ($notice): string => explode("'", $notice->message())[1], $warnings));
    }
}
