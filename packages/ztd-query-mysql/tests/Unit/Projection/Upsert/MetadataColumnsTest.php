<?php

declare(strict_types=1);

namespace Tests\Unit\Projection\Upsert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Projection\Upsert\MetadataColumns;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlIdentifierQuoter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Projection\Upsert\ExpressionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Projection\Upsert\QualifiedColumn::class)]
#[CoversClass(MetadataColumns::class)]
final class MetadataColumnsTest extends TestCase
{
    public function testRender(): void
    {
        $quoter = new \ZtdQuery\Platform\MySql\MySqlIdentifierQuoter();
        $binder = new \ZtdQuery\Platform\MySql\Projection\Upsert\ExpressionBinder($quoter, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create());
        $columns = new MetadataColumns($quoter, $binder);
        $codec = new \ZtdQuery\Shadow\Mutation\UpsertMutationRow();
        self::assertSame([
            '(SELECT `__ztd_incoming`.`score` FROM `t` AS `__ztd_existing` WHERE TRUE LIMIT 1) AS `' . $codec->valueColumn(0) . '`',
            '(SELECT `__ztd_existing`.`score` > 0 FROM `t` AS `__ztd_existing` WHERE TRUE LIMIT 1) AS `' . $codec->predicateColumn() . '`',
        ], $columns->render('t', ['score'], ['score' => 'VALUES(score)'], 'TRUE', 'score > 0', null));
        self::assertSame([], $columns->render('t', ['score'], [], 'TRUE', null, null));
    }

}
