<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary\Program;

use MySqlMemory\Dictionary\Program\Installed;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Installed::class)]
#[Small]
final class InstalledTest extends TestCase
{
    public function testRoutineRetainsSignatureAndIndependentInstallationDates(): void
    {
        $instance = new Instance(routineTimestamps: ['FUNCTION:version_major' => [946684800, 946771200]]);
        $routine = $instance->dictionary->schemas['sys']->functions['version_major'];

        self::assertSame('2000-01-01 00:00:00', $routine->created);
        self::assertSame('2000-01-02 00:00:00', $routine->modified);
        self::assertSame('FUNCTION', $routine->kind());
        self::assertSame(['mysql.sys', 'localhost'], $routine->definer);
        self::assertSame('INVOKER', $routine->security);
        self::assertSame(0, $routine->parameterCount());
        self::assertNull($routine->statement);
        self::assertNotNull($routine->installed);
        self::assertArrayNotHasKey('ROUTINE_DEFINITION', $routine->installed->metadata);
    }

    /**
     * @return iterable<string, array{string, Field, int, list<string>}>
     */
    public static function providerDomains(): iterable
    {
        yield 'version' => ['version_minor', Field::Tiny, 3, []];
        yield 'thread identity' => ['ps_thread_id', Field::LongLong, 20, []];
        yield 'varchar' => ['sys_get_config', Field::VarString, 128, []];
        yield 'text' => ['quote_identifier', Field::Blob, 65535, []];
        yield 'longtext' => ['format_statement', Field::LongBlob, 4294967295, []];
        yield 'enum' => ['ps_is_thread_instrumented', Field::Enum, 7, ['YES', 'NO', 'UNKNOWN']];
    }

    /**
     * @param list<string> $members
     */
    #[DataProvider('providerDomains')]
    public function testReturnedResolvesPublicSignatures(string $name, Field $field, int $length, array $members): void
    {
        $routine = (new Instance())->dictionary->schemas['sys']->functions[$name];
        $domain = $routine->returned();

        self::assertSame([$field, $length, $members, true], [$domain->field, $domain->length, $domain->members, $domain->nullable]);
        self::assertSame($domain, $routine->returned());
    }

    public function testReturnedHasNoValueForAProcedure(): void
    {
        $routine = (new Instance())->dictionary->schemas['sys']->procedures['table_exists'];

        self::assertSame(Field::Null, $routine->returned()->field);
        self::assertSame(3, $routine->parameterCount());
    }
}
