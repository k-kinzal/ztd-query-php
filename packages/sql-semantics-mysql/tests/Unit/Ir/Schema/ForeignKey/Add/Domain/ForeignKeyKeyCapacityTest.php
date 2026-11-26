<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyKeyCapacity;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringKind;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyPair;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Contract\InnoDbKeyLimit;

#[CoversClass(ForeignKeyKeyCapacity::class)]
#[Small]
final class ForeignKeyKeyCapacityTest extends TestCase
{
    public function testEffectiveUsesTheStockReleaseLayoutAndExplicitCompact(): void
    {
        $capacity = new ForeignKeyKeyCapacity();

        self::assertSame(InnoDbKeyLimit::Compact, $capacity->effective(GrammarRelease::MySql5651, null));
        self::assertSame(InnoDbKeyLimit::LargePrefix, $capacity->effective(GrammarRelease::MySql5744, null));
        self::assertSame(InnoDbKeyLimit::Compact, $capacity->effective(GrammarRelease::MySql910, InnoDbKeyLimit::Compact));
    }

    public function testCheckAllowsFourMaximumCompactTerms(): void
    {
        $type = new ForeignKeyStringStorage(ForeignKeyStringKind::Varbinary, 767);
        $domain = new ForeignKeyStringDomain($type, $type);
        $column = new ForeignKeyPair(new Identifier('x'), new Identifier('y'), $domain, true);
        $capacity = new ForeignKeyKeyCapacity();
        $capacity->check([$column, $column, $column, $column], InnoDbKeyLimit::Compact);

        self::assertSame(3068, 4 * $column->domain->childKeyBytes());
    }
}
