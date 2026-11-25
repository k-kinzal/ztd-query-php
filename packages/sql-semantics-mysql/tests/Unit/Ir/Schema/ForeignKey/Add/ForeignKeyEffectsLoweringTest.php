<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyEffectsLowering;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceEvent;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceMatch;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceOption;
use SqlSemantics\Platform\MySql\Statement\Table\Key\References;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferentialAction;

#[CoversClass(ForeignKeyEffectsLowering::class)]
#[Medium]
final class ForeignKeyEffectsLoweringTest extends TestCase
{
    public function testLowerDistinguishesModernAndLegacyMatch(): void
    {
        $reference = new References(
            new QualifiedName(new Name('parent')),
            [new Name('id')],
            ReferenceMatch::Full,
            [new ReferentialAction(ReferenceEvent::Delete, ReferenceOption::Cascade)]
        );
        $lowering = new ForeignKeyEffectsLowering();
        $modern = $lowering->lower($reference, GrammarRelease::MySql910);
        $legacy = $lowering->lower($reference, GrammarRelease::MySql5744);

        self::assertSame(' MATCH FULL ON UPDATE NO ACTION ON DELETE CASCADE', $modern->sql());
        self::assertSame(' ON UPDATE RESTRICT ON DELETE RESTRICT', $legacy->sql());
    }
}
