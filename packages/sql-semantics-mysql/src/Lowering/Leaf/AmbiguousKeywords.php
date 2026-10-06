<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Leaf;

/**
 * The keywords MySQL 8.0 and later accept as identifiers except as a label, a role name or a system variable name: the rules `ident_keywords_ambiguous_*`.
 *
 * Each signature is a production whose only symbol is a keyword terminal; at
 * such a production the keyword is the identifier it spells. The list is the
 * union over every shipped release that has the rule.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/keywords.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class AmbiguousKeywords
{
    /**
     * The keyword-as-identifier productions.
     */
    public const SIGNATURES = [
        'ident_keywords_ambiguous_1_roles_and_labels: EXECUTE_SYM', 'ident_keywords_ambiguous_1_roles_and_labels: RESTART_SYM', 'ident_keywords_ambiguous_1_roles_and_labels: SHUTDOWN',
        'ident_keywords_ambiguous_2_labels: ASCII_SYM', 'ident_keywords_ambiguous_2_labels: BEGIN_SYM', 'ident_keywords_ambiguous_2_labels: BYTE_SYM',
        'ident_keywords_ambiguous_2_labels: CACHE_SYM', 'ident_keywords_ambiguous_2_labels: CHARSET', 'ident_keywords_ambiguous_2_labels: CHECKSUM_SYM',
        'ident_keywords_ambiguous_2_labels: CLONE_SYM', 'ident_keywords_ambiguous_2_labels: COMMENT_SYM', 'ident_keywords_ambiguous_2_labels: COMMIT_SYM',
        'ident_keywords_ambiguous_2_labels: CONTAINS_SYM', 'ident_keywords_ambiguous_2_labels: DEALLOCATE_SYM', 'ident_keywords_ambiguous_2_labels: DO_SYM',
        'ident_keywords_ambiguous_2_labels: END', 'ident_keywords_ambiguous_2_labels: FLUSH_SYM', 'ident_keywords_ambiguous_2_labels: FOLLOWS_SYM',
        'ident_keywords_ambiguous_2_labels: HANDLER_SYM', 'ident_keywords_ambiguous_2_labels: HELP_SYM', 'ident_keywords_ambiguous_2_labels: IMPORT',
        'ident_keywords_ambiguous_2_labels: INSTALL_SYM', 'ident_keywords_ambiguous_2_labels: LANGUAGE_SYM', 'ident_keywords_ambiguous_2_labels: NO_SYM',
        'ident_keywords_ambiguous_2_labels: PRECEDES_SYM', 'ident_keywords_ambiguous_2_labels: PREPARE_SYM', 'ident_keywords_ambiguous_2_labels: REPAIR',
        'ident_keywords_ambiguous_2_labels: RESET_SYM', 'ident_keywords_ambiguous_2_labels: ROLLBACK_SYM', 'ident_keywords_ambiguous_2_labels: SAVEPOINT_SYM',
        'ident_keywords_ambiguous_2_labels: SIGNED_SYM', 'ident_keywords_ambiguous_2_labels: SLAVE', 'ident_keywords_ambiguous_2_labels: START_SYM',
        'ident_keywords_ambiguous_2_labels: STOP_SYM', 'ident_keywords_ambiguous_2_labels: TRUNCATE_SYM', 'ident_keywords_ambiguous_2_labels: UNICODE_SYM',
        'ident_keywords_ambiguous_2_labels: UNINSTALL_SYM', 'ident_keywords_ambiguous_2_labels: XA_SYM', 'ident_keywords_ambiguous_3_roles: EVENT_SYM',
        'ident_keywords_ambiguous_3_roles: FILE_SYM', 'ident_keywords_ambiguous_3_roles: NONE_SYM', 'ident_keywords_ambiguous_3_roles: PROCESS',
        'ident_keywords_ambiguous_3_roles: PROXY_SYM', 'ident_keywords_ambiguous_3_roles: RELOAD', 'ident_keywords_ambiguous_3_roles: REPLICATION',
        'ident_keywords_ambiguous_3_roles: RESOURCE_SYM', 'ident_keywords_ambiguous_3_roles: SUPER_SYM', 'ident_keywords_ambiguous_4_system_variables: GLOBAL_SYM',
        'ident_keywords_ambiguous_4_system_variables: LOCAL_SYM', 'ident_keywords_ambiguous_4_system_variables: PERSIST_SYM', 'ident_keywords_ambiguous_4_system_variables: PERSIST_ONLY_SYM',
        'ident_keywords_ambiguous_4_system_variables: SESSION_SYM',
    ];
}
