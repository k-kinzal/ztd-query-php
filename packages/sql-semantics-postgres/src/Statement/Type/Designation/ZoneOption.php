<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Designation;

/**
 * The time zone clause written after TIMESTAMP or TIME.
 *
 * WITHOUT TIME ZONE is also what the absence of a clause means; the clause
 * written is kept.
 * Source: https://www.postgresql.org/docs/17/datatype-datetime.html.
 *
 * @visibility public
 * @example Spelling the clause that selects the zone-aware type
 *     \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\ZoneOption::WithTimeZone->value // => 'WITH TIME ZONE'
 */
enum ZoneOption: string
{
    case WithTimeZone = 'WITH TIME ZONE';
    case WithoutTimeZone = 'WITHOUT TIME ZONE';
}
