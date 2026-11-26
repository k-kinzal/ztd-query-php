<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Key\CreatePrimaryKeyLowering;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;

#[CoversClass(CreatePrimaryKeyLowering::class)]
#[Small]
final class CreatePrimaryKeyLoweringTest extends TestCase
{
    public function testLowerResolvesOrderAndLegacyDirection(): void
    {
        $parsed = new IndexDefinition(IndexKind::Primary, [
            new ColumnPart(new Name('SECOND'), direction: Direction::Descending),
            new ColumnPart(new Name('first')),
        ]);
        $lowering = new CreatePrimaryKeyLowering();
        $positions = ['first' => 0, 'second' => 1];

        $legacy = $lowering->lower($parsed, null, $positions, GrammarRelease::MySql5744);
        $current = $lowering->lower($parsed, null, $positions, GrammarRelease::MySql910);

        self::assertNotNull($legacy);
        self::assertNotNull($current);
        self::assertSame(1, $current->terms[0]->slot);
        self::assertSame(0, $current->terms[1]->slot);
        self::assertTrue($current->terms[0]->descending);
        self::assertFalse($legacy->terms[0]->descending);
    }
}
