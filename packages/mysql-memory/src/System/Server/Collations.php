<?php

declare(strict_types=1);

namespace MySqlMemory\System\Server;

use MySqlMemory\Command\Show\Server\ServerCatalog;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Catalog;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * The rows of INFORMATION_SCHEMA.COLLATIONS: the collations of the release.
 *
 * MySQL 8.0 and later list them by character set name, 5.6 and 5.7 in the order of their
 * character sets in INFORMATION_SCHEMA.CHARACTER_SETS; the collations of a character set by id
 * (verified on live 5.7.44 and 8.4.7 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-collations-table.html.
 *
 * @visibility MySqlMemory
 */
final class Collations implements SystemRows
{
    /**
     * Answers a row for each collation.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $catalog = Catalog::shared();
        $defaults = $catalog->defaults[$reading->release->value] ?? [];
        $sortLengths = ServerCatalog::shared()->collations;
        $rows = [];
        foreach (self::ordered($reading) as $collation) {
            $charset = $collation->charset->name;
            $rows[] = [
                'COLLATION_NAME' => $collation->nameIn($reading->release),
                'CHARACTER_SET_NAME' => $collation->charset->nameIn($reading->release),
                'ID' => $collation->id,
                'IS_DEFAULT' => ($defaults[$charset] ?? null) === $collation->name ? 'Yes' : '',
                'IS_COMPILED' => 'Yes',
                'SORTLEN' => $sortLengths[$collation->name] ?? 1,
                'PAD_ATTRIBUTE' => $collation->padSpace ? 'PAD SPACE' : 'NO PAD',
            ];
        }

        return $rows;
    }

    /**
     * Answers the collations of the release in the order INFORMATION_SCHEMA lists them.
     *
     * @return list<Collation>
     */
    public static function ordered(Reading $reading): array
    {
        $catalog = Catalog::shared();
        $defaults = $catalog->defaults[$reading->release->value] ?? [];
        $collations = [];
        foreach ($catalog->collations as $name => $collation) {
            if (in_array($reading->release->value, $catalog->releases[$name] ?? [], true)) {
                $collations[] = $collation;
            }
        }
        $rank = static fn (Collation $collation): array => [$reading->dictionary() ? $collation->charset->nameIn($reading->release) : (Collation::named($defaults[$collation->charset->name] ?? '')->id ?? 0), $collation->id];
        usort($collations, static fn (Collation $left, Collation $right): int => $rank($left) <=> $rank($right));

        return $collations;
    }
}
