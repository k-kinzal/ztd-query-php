<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * One native normalized label in an ordered ENUM or SET declaration.
 * @visibility public
 * @example Reading a label with significant leading space
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationMember(' red'))->value // => ' red'
 */
final class ForeignKeyEnumerationMember
{
    use Snapshot;

    /**
     * Checks the decoded utf8mb4 label before release-specific placement.
     */
    public function __construct(public readonly string $value)
    {
        Check::input(preg_match('//u', $value) === 1, 'A native ENUM or SET member requires a settled utf8mb4 label.');
    }
}
