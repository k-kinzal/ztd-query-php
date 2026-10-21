<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Structure\Audit\Comparison;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversNothing]
#[Large]
final class TableCatalogHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Statement}>
     */
    public static function providerChanges(): iterable
    {
        foreach (['5.6.51', '8.4.7', '9.1.0'] as $release) {
            $first = new QualifiedName(new Name('2'));
            $second = new QualifiedName(new Name('1.5'));
            yield $release . ':index' => [$release, new M\Alter\DropIndex(new Name('t'), new QualifiedName(new Name('A1B2C')))];
            yield $release . ':primary' => [$release, new M\Alter\DropIndex(new Name('PRIMARY'), new QualifiedName(new Name('A1B2C')))];
            yield $release . ':temporary' => [$release, new M\Alter\DropTable(true, false, [new M\Alter\TargetTable(new QualifiedName(new Name('utf8mb4'))), new M\Alter\TargetTable(new QualifiedName(new Name('t')))])];
            yield $release . ':rename' => [$release, new M\Alter\RenameTable([new M\Alter\TableRenaming($first, new QualifiedName(new Name('2024-01-01'))), new M\Alter\TableRenaming($second, $first)])];
        }
    }

    #[DataProvider('providerChanges')]
    public function testTheUnchangedRequestSucceedsAfterItsCatalogObjectsExist(string $release, M\Statement $node): void
    {
        $observation = (new Comparison())->compare($node, new LanguageProfile(GrammarRelease::from('mysql-' . $release)));
        self::assertTrue($observation['matches'], serialize($observation));
        self::assertNotEmpty($observation['transitions']);
        $last = $observation['transitions'][count($observation['transitions']) - 1];
        self::assertSame($observation['sql'], $last['sql']);
        self::assertArrayNotHasKey('error', $last['native']);
        self::assertNotEmpty($last['native']['results']);
    }
}
