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
 * The rows of INFORMATION_SCHEMA.CHARACTER_SETS: the character sets of the release, in the order of the ids of their default collations.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-character-sets-table.html.
 *
 * @visibility MySqlMemory
 */
final class CharacterSets implements SystemRows
{
    /**
     * Answers a row for each character set.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $catalog = Catalog::shared();
        $descriptions = ServerCatalog::shared()->charsets;
        $rows = [];
        foreach ($catalog->defaults[$reading->release->value] ?? [] as $name => $default) {
            $collation = Collation::named($default);
            $rows[$collation->id ?? 0] = [
                'CHARACTER_SET_NAME' => $collation?->charset->nameIn($reading->release) ?? $name,
                'DEFAULT_COLLATE_NAME' => $collation?->nameIn($reading->release) ?? $default,
                'DESCRIPTION' => $descriptions[$name] ?? '',
                'MAXLEN' => $catalog->charsets[$name]->maxLength ?? 1,
            ];
        }
        ksort($rows);

        return array_values($rows);
    }
}
