<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlNativeUpsertProjector;

#[CoversClass(MySqlNativeUpsertProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlIdentifierQuoter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
final class MySqlNativeUpsertProjectorTest extends TestCase
{
    public function testProjectsMySqlFunctionsThroughExistingAndIncomingRows(): void
    {
        $projector = new MySqlNativeUpsertProjector();

        $result = $projector->project(
            'SELECT 1 AS `id`, 15 AS `price`, 2 AS `version`, \'{}\' AS `meta`',
            'products',
            ['id', 'price', 'version', 'meta'],
            ['empty_key' => [], 'PRIMARY' => ['id'], 'version_key' => ['price', 'version']],
            [
                'price' => 'IF(VALUES(version) > version, VALUES(price), price)',
                'meta' => "JSON_SET(meta, '$.price', VALUES(price))",
            ],
            'VALUES(version) > version',
        );

        self::assertSame(
            'SELECT `__ztd_incoming`.`id` AS `id`, `__ztd_incoming`.`price` AS `price`, '
            . '`__ztd_incoming`.`version` AS `version`, `__ztd_incoming`.`meta` AS `meta`, '
            . '(SELECT IF(`__ztd_incoming`.`version` > `__ztd_existing`.`version`, '
            . '`__ztd_incoming`.`price`, `__ztd_existing`.`price`) FROM `products` AS `__ztd_existing` '
            . 'WHERE ((`__ztd_existing`.`id` = `__ztd_incoming`.`id`) OR '
            . '(`__ztd_existing`.`price` = `__ztd_incoming`.`price` AND '
            . '`__ztd_existing`.`version` = `__ztd_incoming`.`version`)) LIMIT 1) AS `__ztd_upsert_value_0`, '
            . "(SELECT JSON_SET(`__ztd_existing`.`meta`, '$.price', `__ztd_incoming`.`price`) "
            . 'FROM `products` AS `__ztd_existing` WHERE ((`__ztd_existing`.`id` = `__ztd_incoming`.`id`) OR '
            . '(`__ztd_existing`.`price` = `__ztd_incoming`.`price` AND '
            . '`__ztd_existing`.`version` = `__ztd_incoming`.`version`)) LIMIT 1) AS `__ztd_upsert_value_1`, '
            . '(SELECT `__ztd_incoming`.`version` > `__ztd_existing`.`version` '
            . 'FROM `products` AS `__ztd_existing` WHERE ((`__ztd_existing`.`id` = `__ztd_incoming`.`id`) OR '
            . '(`__ztd_existing`.`price` = `__ztd_incoming`.`price` AND '
            . '`__ztd_existing`.`version` = `__ztd_incoming`.`version`)) LIMIT 1) AS `__ztd_upsert_predicate` '
            . "FROM (SELECT 1 AS `id`, 15 AS `price`, 2 AS `version`, '{}' AS `meta`) AS `__ztd_incoming`",
            $result,
        );
    }

    public function testLeavesIncompleteIncomingFunctionSyntaxUnchanged(): void
    {
        $projector = new MySqlNativeUpsertProjector();

        $missingColumn = $projector->project(
            'SELECT 1 AS `id`, 2 AS `price`',
            'items',
            ['id', 'price'],
            ['PRIMARY' => ['id']],
            ['price' => 'VALUES('],
        );
        $missingClose = $projector->project(
            'SELECT 1 AS `id`, 2 AS `price`',
            'items',
            ['id', 'price'],
            ['PRIMARY' => ['id']],
            ['price' => 'VALUES(price'],
        );

        self::assertStringContainsString('(SELECT VALUES( ', $missingColumn);
        self::assertStringContainsString('(SELECT VALUES(`__ztd_existing`.`price` ', $missingClose);
    }

    public function testReturnsIncomingProjectionWhenConflictMetadataIsUnavailable(): void
    {
        $projector = new MySqlNativeUpsertProjector();

        self::assertSame(
            'SELECT 1 AS "id"',
            $projector->project('SELECT 1 AS "id"', 'items', ['id'], [], ['id' => 'EXCLUDED.id']),
        );
        self::assertSame(
            'SELECT 1 AS "id"',
            $projector->project('SELECT 1 AS "id"', 'items', ['id'], ['PRIMARY' => ['id']], []),
        );
    }

    public function testKeepsIdentifiersInsideScalarSubqueryInTheirNativeScope(): void
    {
        $result = (new MySqlNativeUpsertProjector())->project(
            'SELECT 1 AS `id`, 0 AS `price`',
            'prices',
            ['id', 'price'],
            ['PRIMARY' => ['id']],
            ['price' => 'price + (SELECT price FROM price_list WHERE id = prices.id) + COALESCE(VALUES(price), 0)'],
        );

        self::assertSame(
            'SELECT `__ztd_incoming`.`id` AS `id`, `__ztd_incoming`.`price` AS `price`, '
            . '(SELECT `__ztd_existing`.`price` + (SELECT price FROM price_list WHERE id = prices.id) '
            . '+ COALESCE(`__ztd_incoming`.`price`, 0) FROM `prices` AS `__ztd_existing` '
            . 'WHERE ((`__ztd_existing`.`id` = `__ztd_incoming`.`id`)) LIMIT 1) AS `__ztd_upsert_value_0` '
            . 'FROM (SELECT 1 AS `id`, 0 AS `price`) AS `__ztd_incoming`',
            $result,
        );
    }

    public function testBindsQualifiedAndQuotedIdentifiersWithoutChangingForeignNamespaces(): void
    {
        $result = (new MySqlNativeUpsertProjector())->project(
            'SELECT 1 AS `id`, 2 AS `odd``name`, 3 AS `price`',
            'prices',
            ['id', 'odd`name', 'price'],
            ['PRIMARY' => ['id']],
            ['price' => 'VALUES(`odd``name`) + prices.price + foreign.price + price(price) + CONSTANT'],
        );

        self::assertSame(
            'SELECT `__ztd_incoming`.`id` AS `id`, `__ztd_incoming`.`odd``name` AS `odd``name`, '
            . '`__ztd_incoming`.`price` AS `price`, '
            . '(SELECT `__ztd_incoming`.`odd``name` + `__ztd_existing`.`price` + foreign.price '
            . '+ price(`__ztd_existing`.`price`) + CONSTANT FROM `prices` AS `__ztd_existing` '
            . 'WHERE ((`__ztd_existing`.`id` = `__ztd_incoming`.`id`)) LIMIT 1) AS `__ztd_upsert_value_0` '
            . 'FROM (SELECT 1 AS `id`, 2 AS `odd``name`, 3 AS `price`) AS `__ztd_incoming`',
            $result,
        );
    }

    public function testMatchesColumnsAndIncomingNamespaceCaseInsensitively(): void
    {
        $result = (new MySqlNativeUpsertProjector())->project(
            'SELECT 1 AS `ID`, 2 AS `PRICE`',
            'ITEMS',
            ['ID', 'PRICE'],
            ['PRIMARY' => ['ID']],
            ['PRICE' => 'values(PRICE) + PRICE + ITEMS.PRICE'],
        );

        self::assertSame(
            'SELECT `__ztd_incoming`.`ID` AS `ID`, `__ztd_incoming`.`PRICE` AS `PRICE`, '
            . '(SELECT `__ztd_incoming`.`PRICE` + `__ztd_existing`.`PRICE` + `__ztd_existing`.`PRICE` '
            . 'FROM `ITEMS` AS `__ztd_existing` WHERE ((`__ztd_existing`.`ID` = `__ztd_incoming`.`ID`)) '
            . 'LIMIT 1) AS `__ztd_upsert_value_0` '
            . 'FROM (SELECT 1 AS `ID`, 2 AS `PRICE`) AS `__ztd_incoming`',
            $result,
        );
    }

    public function testBindsExplicitIncomingRowAlias(): void
    {
        $result = (new MySqlNativeUpsertProjector())->project(
            'SELECT 1 AS `id`, \'new\' AS `odd``name`',
            'users',
            ['id', 'odd`name'],
            ['PRIMARY' => ['id']],
            ['odd`name' => '`InCoMiNg`.`odd``name`'],
            incomingNamespace: 'incoming',
        );

        self::assertStringContainsString(
            '(SELECT `__ztd_incoming`.`odd``name` FROM `users` AS `__ztd_existing`',
            $result,
        );
        self::assertStringNotContainsString('`InCoMiNg`.', $result);
    }

    public function testBindsUnqualifiedQuotedIdentifier(): void
    {
        $result = (new MySqlNativeUpsertProjector())->project(
            'SELECT 1 AS `id`, 2 AS `odd``name`',
            'items',
            ['id', 'odd`name'],
            ['PRIMARY' => ['id']],
            ['odd`name' => '`odd``name` + 1'],
        );

        self::assertStringContainsString(
            '(SELECT `__ztd_existing`.`odd``name` + 1 FROM `items` AS `__ztd_existing`',
            $result,
        );
    }

    public function testBindsPartialConflictPredicateToExistingAndIncomingRows(): void
    {
        $result = (new MySqlNativeUpsertProjector())->project(
            'SELECT \'alice@example.com\' AS `email`, \'active\' AS `status`, 1 AS `login_count`',
            'users',
            ['email', 'status', 'login_count'],
            ['users_active_email' => ['email']],
            ['login_count' => 'GREATEST(login_count, VALUES(login_count))'],
            conflictPredicate: "status = 'active'",
        );

        self::assertStringContainsString(
            '`__ztd_existing`.`email` = `__ztd_incoming`.`email`',
            $result,
        );
        self::assertStringContainsString(
            '(`__ztd_existing`.`status` = \'active\') AND (`__ztd_incoming`.`status` = \'active\')',
            $result,
        );
    }
}
