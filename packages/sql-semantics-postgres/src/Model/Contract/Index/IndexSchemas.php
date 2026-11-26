<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Contract\Index;

/**
 * Stable and session-specific namespaces used by the PostgreSQL index binding.
 *
 * @visibility SqlSemantics
 */
final class IndexSchemas
{
    /**
     * Tells whether the namespace exists in each pinned stock database.
     */
    public static function stock(string $schema): bool
    {
        return $schema === 'public' || self::system($schema);
    }

    /**
     * Tells whether the namespace is owned by the stock database.
     */
    public static function system(string $schema): bool
    {
        return in_array($schema, ['pg_catalog', 'information_schema', 'pg_toast'], true);
    }

    /**
     * Tells whether the name depends on a session-specific namespace number.
     */
    public static function sessionInternal(string $schema): bool
    {
        return preg_match('/\Apg_(?:toast_)?temp_[0-9]+\z/D', $schema) === 1;
    }
}
