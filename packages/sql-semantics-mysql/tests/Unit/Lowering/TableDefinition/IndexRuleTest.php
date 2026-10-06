<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\IndexRule;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key\KeyOptionRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;

#[CoversClass(IndexRule::class)]
#[Medium]
final class IndexRuleTest extends TestCase
{
    public function testIndexLowersTheLegacyForm(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE SPATIAL INDEX i ON t (g)')->find('create')[0];
        $index = (new IndexRule($lowering))->index($lowering->form($node));

        self::assertSame(IndexKind::Spatial, $index->kind);
    }

    public function testStructureLowersTheClauseBeforeOn(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE INDEX i USING HASH ON t (a)')->find('key_alg')[0];

        self::assertSame(IndexAlgorithm::Hash, (new IndexRule($lowering))->structure($node, new KeyOptionRule($lowering)));
    }

    public function testStatementLowersCreateIndexStmt(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE UNIQUE INDEX i ON t (a)')->find('create_index_stmt')[0];

        self::assertSame(IndexKind::Unique, (new IndexRule($lowering))->statement($node)->kind);
    }
}
