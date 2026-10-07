<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Variable\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Reach;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Writability;

#[CoversClass(SystemVariables::class)]
#[Small]
final class SystemVariablesTest extends TestCase
{
    public function testOfReadsTheCatalogOfEachRelease(): void
    {
        self::assertSame(SystemVariables::of(GrammarRelease::MySql847), SystemVariables::of(GrammarRelease::MySql847));
        self::assertNull(SystemVariables::of(GrammarRelease::MySql5651)->find('activate_all_roles_on_login'));
        self::assertNotNull(SystemVariables::of(GrammarRelease::MySql847)->find('activate_all_roles_on_login'));
    }

    public function testWritabilityReadsTheReadOnlyScopes(): void
    {
        self::assertSame([Writability::Writable, Writability::GlobalOnly, Writability::ReadOnly], [SystemVariables::writability('No'), SystemVariables::writability('Session'), SystemVariables::writability('Yes')]);
    }

    public function testDomainBuildsTheTypeOfARead(): void
    {
        self::assertSame([Kind::Integer, 21, true], [SystemVariables::domain(8, 21, 0, true, 'binary')->kind, SystemVariables::domain(8, 21, 0, true, 'binary')->length, SystemVariables::domain(8, 21, 0, true, 'binary')->unsigned]);
        self::assertSame([Kind::Double, 6], [SystemVariables::domain(5, 23, 6, false, 'binary')->kind, SystemVariables::domain(5, 23, 6, false, 'binary')->decimals]);
        self::assertSame([Field::VarString, 'utf8mb3_general_ci'], [SystemVariables::domain(253, 21845, 31, false, 'utf8mb3_general_ci')->field, SystemVariables::domain(253, 21845, 31, false, 'utf8mb3_general_ci')->collation->name]);
    }

    public function testFindIgnoresCase(): void
    {
        self::assertSame(Reach::Session, SystemVariables::of(GrammarRelease::MySql847)->find('TIMESTAMP')?->reach);
        self::assertSame(Writability::GlobalOnly, SystemVariables::of(GrammarRelease::MySql847)->find('max_allowed_packet')?->writability);
    }
}
