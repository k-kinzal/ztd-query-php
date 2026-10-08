<?php

declare(strict_types=1);

namespace MySqlMemory\System\Server;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of INFORMATION_SCHEMA.COLLATION_CHARACTER_SET_APPLICABILITY: the character set of each collation, in the order of INFORMATION_SCHEMA.COLLATIONS.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-collation-character-set-applicability-table.html.
 *
 * @visibility MySqlMemory
 */
final class CollationApplicability implements SystemRows
{
    /**
     * Answers a row for each collation.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        return array_map(static fn ($collation): array => ['COLLATION_NAME' => $collation->nameIn($reading->release), 'CHARACTER_SET_NAME' => $collation->charset->nameIn($reading->release)], Collations::ordered($reading));
    }
}
