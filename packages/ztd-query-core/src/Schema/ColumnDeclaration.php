<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

/**
 * How a column was declared: the family it belongs to, and the platform's own words for it.
 *
 * The family is what every platform agrees on, so the shadow can reason about
 * a column without knowing which database it came from. The native text is
 * what that database actually wrote, kept verbatim because only it can say
 * what precision, width or signedness was asked for.
 *
 * @visibility public
 *
 * @example Describe a platform-neutral column type
 *     $type = new \ZtdQuery\Schema\ColumnType(\ZtdQuery\Schema\ColumnTypeFamily::INTEGER, 'BIGINT');
 *     $type->family // => \ZtdQuery\Schema\ColumnTypeFamily::INTEGER
 *     $type->nativeType // => 'BIGINT'
 */
final class ColumnDeclaration
{
    /**
     * @param ColumnTypeFamily $family The family every platform agrees this belongs to
     * @param string $nativeType The platform's own words for the type, verbatim
     */
    public function __construct(
        public readonly ColumnTypeFamily $family,
        public readonly string $nativeType,
    ) {
    }
}
