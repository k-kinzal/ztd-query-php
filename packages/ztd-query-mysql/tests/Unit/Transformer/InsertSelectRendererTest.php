<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\Transformer\InsertSelectRenderer;
use ZtdQuery\Platform\MySql\Transformer\MySqlSelectListAliaser;

#[CoversClass(InsertSelectRenderer::class)]
#[UsesClass(MySqlIdentifierQuoter::class)]
#[UsesClass(MySqlSelectListAliaser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
final class InsertSelectRendererTest extends TestCase
{
    public function testRendersMySqlProjectionFromDialectNeutralPlan(): void
    {
        $sql = (new InsertSelectRenderer())->render(
            'SELECT DISTINCT name, COUNT(*) FROM users GROUP BY name',
            ['id', 'name', 'count', 'status'],
            ['name', 'count'],
            ['status' => "'active'"],
            ['id' => 8],
        );

        self::assertSame(
            'WITH `__ztd_insert_source` (`__ztd_insert_0`, `__ztd_insert_1`) AS ('
            . 'SELECT DISTINCT name AS `__ztd_insert_0`, COUNT(*) AS `__ztd_insert_1` FROM users GROUP BY name) '
            . 'SELECT 8 + ROW_NUMBER() OVER () - 1 AS `id`, `__ztd_insert_0` AS `name`, '
            . '`__ztd_insert_1` AS `count`, \'active\' AS `status` FROM `__ztd_insert_source`',
            $sql,
        );
    }

    public function testRejectsKnownMySqlProjectionCountMismatch(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('INSERT column count does not match SELECT column count.');

        (new InsertSelectRenderer())->render('SELECT id, name FROM users', ['id'], ['id'], []);
    }

    public function testAllowsMySqlWildcardWithSchemaDependentProjectionCount(): void
    {
        $result = (new InsertSelectRenderer())->render(
            'SELECT source.* FROM source',
            ['id', 'name'],
            ['id', 'name'],
            [],
        );

        self::assertStringContainsString('SELECT source.* FROM source', $result);
    }

    public function testRendersGeneratedIdentityWithMySqlDialectSyntax(): void
    {
        self::assertSame(
            '8 + ROW_NUMBER() OVER () - 1',
            (new InsertSelectRenderer())->renderGeneratedIdentity(8),
        );
    }

    public function testAcceptsMinimumGeneratedIdentityStart(): void
    {
        self::assertSame(
            '1 + ROW_NUMBER() OVER () - 1',
            (new InsertSelectRenderer())->renderGeneratedIdentity(1),
        );
    }

    public function testRejectsNonPositiveGeneratedIdentityStart(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Generated identity start must be positive.');

        (new InsertSelectRenderer())->renderGeneratedIdentity(0);
    }
}
