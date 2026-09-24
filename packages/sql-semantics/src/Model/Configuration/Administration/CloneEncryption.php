<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Administration;

/**
 * The explicit encryption requirement of a remote clone connection.
 * @visibility public
 * @example Reading the clause
 *     \SqlSemantics\Model\Configuration\Administration\CloneEncryption::Refused->value // => 'REQUIRE NO SSL'
 */
enum CloneEncryption: string
{
    case Required = 'REQUIRE SSL';
    case Refused = 'REQUIRE NO SSL';
}
