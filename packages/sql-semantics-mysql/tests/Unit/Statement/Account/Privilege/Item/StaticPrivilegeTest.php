<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege\Item;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;

#[CoversClass(StaticPrivilege::class)]
#[Medium]
final class StaticPrivilegeTest extends TestCase
{
    #[DataProvider('providerSpellings')]
    public function testRenderPreservesTheShowSchemasPrivilege(string $version, string $sql): void
    {
        self::assertSame($sql, (new Semantics(Dialect::MySql, 'mysql-' . $version))->analyze($sql)->toString());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerSpellings(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach (['SCHEMAS', 'DATABASES'] as $word) {
                foreach (["GRANT SHOW $word ON *.* TO CURRENT_USER", "REVOKE SHOW $word ON *.* FROM CURRENT_USER"] as $sql) {
                    yield "$version $sql" => [$version, $sql];
                }
            }
        }
    }

    public function testRenderWritesTheColumns(): void
    {
        self::assertSame('GRANT INSERT (a, `b c`), REFERENCES ON t TO u', (new Semantics(Dialect::MySql))->analyze('grant insert (a, `b c`), references on t to u')->toString());
    }
}
