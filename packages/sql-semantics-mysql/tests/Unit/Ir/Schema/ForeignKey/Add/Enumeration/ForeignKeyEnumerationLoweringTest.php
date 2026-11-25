<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add\Enumeration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyTextCollation;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationLowering;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ForeignKeyEnumerationLowering::class)]
#[Medium]
final class ForeignKeyEnumerationLoweringTest extends TestCase
{
    public function testStorageSettlesExactOrderAndInheritedCollation(): void
    {
        $sql = "CREATE TABLE app.labels (x ENUM('closed ', ' open', '')) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin";
        $table = (new Inspection())->inspect($sql)->declarations()[0];
        $storage = (new ForeignKeyEnumerationLowering())->storage($table->columns[0], GrammarRelease::MySql910);

        self::assertNotNull($storage);
        self::assertSame(ForeignKeyTextCollation::Binary, $storage->collation);
        self::assertSame(['closed', ' open', ''], array_column($storage->members, 'value'));
    }

    public function testNormalizedKeepsSpaceBeforeLegacyZeroTermination(): void
    {
        $lowering = new ForeignKeyEnumerationLowering();

        self::assertSame('z ', $lowering->normalized("z \0x  ", true));
        self::assertSame("z \0x", $lowering->normalized("z \0x  ", false));
    }
}
