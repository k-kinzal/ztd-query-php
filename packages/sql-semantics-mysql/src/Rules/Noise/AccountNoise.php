<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Noise;

/**
 * The noise and synonym token positions of the productions of accounts and privileges.
 *
 * Only a token with no influence on meaning in its production may be listed
 * as noise, and only terminals the manual defines as synonyms may share a
 * key. Every entry is listed in the method documentation with its reason and
 * the manual page that states it. Nothing else is skipped or merged by the
 * token correspondence check.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class AccountNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * - `opt_privileges: PRIVILEGES`: `ALL` and `ALL PRIVILEGES` are the same
     *   request (https://dev.mysql.com/doc/refman/8.4/en/privileges-provided.html#priv_all:
     *   "ALL [PRIVILEGES]").
     * - `opt_and: AND_SYM`: the AND between REQUIRE conditions is optional and
     *   the conditions are a set (https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-tls:
     *   "tls_option [[AND] tls_option] ...").
     * - `opt_acl_type: TABLE_SYM`: TABLE is the default object type of the ON
     *   clause; the grammar action gives both Acl_type::TABLE
     *   (https://dev.mysql.com/doc/refman/8.4/en/grant.html: "object_type: { TABLE | FUNCTION | PROCEDURE }",
     *   "ON TABLE" is optional).
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            'opt_privileges: PRIVILEGES' => [0],
            'opt_and: AND_SYM' => [0],
            'opt_acl_type: TABLE_SYM' => [0],
        ];
    }

    /**
     * Answers the key of each synonym position by production signature.
     *
     * @return array<string, array<int, string>>
     */
    public static function synonyms(): array
    {
        return [];
    }
}
