<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain;

/**
 * A resolved native utf8mb4 collation for foreign-key string fields.
 * @visibility public
 * @example Identifying the exact string comparison mode
 *     \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyTextCollation::Unicode->value // => 'utf8mb4_unicode_ci'
 */
enum ForeignKeyTextCollation: string
{
    case Binary = 'utf8mb4_bin';
    case General = 'utf8mb4_general_ci';
    case Unicode = 'utf8mb4_unicode_ci';
}
