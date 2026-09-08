<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator;
use SqlFaker\Grammar\Generation\Version\VersionCase;
use SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator;

/**
 * Explicit keyword dispatch for the checked-in releases, based on each tag's sql/lex.h.
 * These declarations select handlers; spellings remain in the upstream registration table.
 */
final class KeywordDefinitions
{
    private const FROM_80 = [
                'ACTIVE_SYM', 'ADMIN_SYM', 'ARRAY_SYM', 'ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS_SYM', 'ATTRIBUTE_SYM',
                'AUTHENTICATION_SYM', 'BIGINT_SYM', 'BINARY_SYM', 'BIT_AND_SYM', 'BIT_OR_SYM',
                'BIT_XOR_SYM', 'BUCKETS_SYM', 'BULK_SYM', 'CHALLENGE_RESPONSE_SYM', 'CLONE_SYM',
                'COMPONENT_SYM', 'CUME_DIST_SYM', 'DATETIME_SYM', 'DEFAULT_SYM', 'DEFINITION_SYM',
                'DENSE_RANK_SYM', 'DERIVED_CONDITION_PUSHDOWN_HINT', 'DERIVED_MERGE_HINT', 'DESCRIPTION_SYM', 'DISCARD_SYM',
                'EMPTY_SYM', 'ENFORCED_SYM', 'ENGINE_ATTRIBUTE_SYM', 'ENUM_SYM', 'EXCEPT_SYM',
                'EXCLUDE_SYM', 'FACTOR_SYM', 'FAILED_LOGIN_ATTEMPTS_SYM', 'FINISH_SYM', 'FIRST_VALUE_SYM',
                'FOLLOWING_SYM', 'GENERATE_SYM', 'GEOMETRYCOLLECTION_SYM', 'GET_SOURCE_PUBLIC_KEY_SYM', 'GROUPING_SYM',
                'GROUPS_SYM', 'GROUP_INDEX_HINT', 'GTID_ONLY_SYM', 'HASH_JOIN_HINT', 'HISTOGRAM_SYM',
                'HISTORY_SYM', 'INACTIVE_SYM', 'INDEX_HINT', 'INDEX_MERGE_HINT', 'INFILE_SYM',
                'INITIAL_SYM', 'INITIATE_SYM', 'INSERT_SYM', 'INTERSECT_SYM', 'INVISIBLE_SYM',
                'JOIN_FIXED_ORDER_HINT', 'JOIN_INDEX_HINT', 'JOIN_ORDER_HINT', 'JOIN_PREFIX_HINT', 'JOIN_SUFFIX_HINT',
                'JSON_TABLE_SYM', 'JSON_VALUE_SYM', 'KEYRING_SYM', 'LAG_SYM', 'LAST_VALUE_SYM',
                'LATERAL_SYM', 'LEAD_SYM', 'LINESTRING_SYM', 'LOCKED_SYM', 'LONGBLOB_SYM',
                'LONGTEXT_SYM', 'MEDIUMBLOB_SYM', 'MEDIUMINT_SYM', 'MEDIUMTEXT_SYM', 'MEMBER_SYM',
                'MULTILINESTRING_SYM', 'MULTIPOINT_SYM', 'MULTIPOLYGON_SYM', 'NESTED_SYM', 'NETWORK_NAMESPACE_SYM',
                'NOWAIT_SYM', 'NO_DERIVED_CONDITION_PUSHDOWN_HINT', 'NO_DERIVED_MERGE_HINT', 'NO_GROUP_INDEX_HINT', 'NO_HASH_JOIN_HINT',
                'NO_INDEX_HINT', 'NO_INDEX_MERGE_HINT', 'NO_JOIN_INDEX_HINT', 'NO_ORDER_INDEX_HINT', 'NO_SKIP_SCAN_HINT',
                'NTH_VALUE_SYM', 'NTILE_SYM', 'NULLS_SYM', 'OFF_SYM', 'OF_SYM',
                'OJ_SYM', 'OLD_SYM', 'ON_SYM', 'OPTIONAL_SYM', 'ORDER_INDEX_HINT',
                'ORDINALITY_SYM', 'ORGANIZATION_SYM', 'OTHERS_SYM', 'OUTER_SYM', 'OVER_SYM',
                'PASSWORD_LOCK_TIME_SYM', 'PATH_SYM', 'PERCENT_RANK_SYM', 'PERSIST_ONLY_SYM', 'PERSIST_SYM',
                'POLYGON_SYM', 'PRECEDING_SYM', 'PRIVILEGE_CHECKS_USER_SYM', 'RANDOM_SYM', 'RANK_SYM',
                'REAL_SYM', 'RECURSIVE_SYM', 'REFERENCE_SYM', 'REGISTRATION_SYM', 'REPLACE_SYM',
                'REPLICAS_SYM', 'REPLICA_SYM', 'REQUIRE_ROW_FORMAT_SYM', 'REQUIRE_TABLE_PRIMARY_KEY_CHECK_SYM', 'RESOURCE_GROUP_HINT',
                'RESOURCE_SYM', 'RESPECT_SYM', 'RESTART_SYM', 'RETAIN_SYM', 'RETURNING_SYM',
                'REUSE_SYM', 'ROLE_SYM', 'ROW_NUMBER_SYM', 'SECONDARY_ENGINE_ATTRIBUTE_SYM', 'SECONDARY_ENGINE_SYM',
                'SECONDARY_LOAD_SYM', 'SECONDARY_SYM', 'SECONDARY_UNLOAD_SYM', 'SET_SYM', 'SET_VAR_HINT',
                'SKIP_SCAN_HINT', 'SKIP_SYM', 'SMALLINT_SYM', 'SOURCE_AUTO_POSITION_SYM', 'SOURCE_BIND_SYM',
                'SOURCE_COMPRESSION_ALGORITHM_SYM', 'SOURCE_CONNECTION_AUTO_FAILOVER_SYM', 'SOURCE_CONNECT_RETRY_SYM', 'SOURCE_DELAY_SYM', 'SOURCE_HEARTBEAT_PERIOD_SYM',
                'SOURCE_HOST_SYM', 'SOURCE_LOG_FILE_SYM', 'SOURCE_LOG_POS_SYM', 'SOURCE_PASSWORD_SYM', 'SOURCE_PORT_SYM',
                'SOURCE_PUBLIC_KEY_PATH_SYM', 'SOURCE_RETRY_COUNT_SYM', 'SOURCE_SSL_CAPATH_SYM', 'SOURCE_SSL_CA_SYM', 'SOURCE_SSL_CERT_SYM',
                'SOURCE_SSL_CIPHER_SYM', 'SOURCE_SSL_CRLPATH_SYM', 'SOURCE_SSL_CRL_SYM', 'SOURCE_SSL_KEY_SYM', 'SOURCE_SSL_SYM',
                'SOURCE_SSL_VERIFY_SERVER_CERT_SYM', 'SOURCE_TLS_CIPHERSUITES_SYM', 'SOURCE_TLS_VERSION_SYM', 'SOURCE_USER_SYM', 'SOURCE_ZSTD_COMPRESSION_LEVEL_SYM',
                'SRID_SYM', 'STREAM_SYM', 'ST_COLLECT_SYM', 'SYSTEM_SYM', 'THREAD_PRIORITY_SYM',
                'TIES_SYM', 'TIMESTAMP_SYM', 'TINYBLOB_SYM', 'TINYINT_SYM', 'TINYTEXT_SYN',
                'TLS_SYM', 'UNBOUNDED_SYM', 'UNREGISTER_SYM', 'UNSIGNED_SYM', 'URL_SYM',
                'VARBINARY_SYM', 'VARCHAR_SYM', 'VCPU_SYM', 'VISIBLE_SYM', 'WINDOW_SYM',
                'ZEROFILL_SYM', 'ZONE_SYM',
            ];

    /**
     * Shares keyword interpretation while limiting each terminal to its reviewed releases.
     */
    public function create(string $version, KeywordLexemeGenerator $keywords): LexemeGenerator
    {
        return new ChoiceLexemeGenerator(
            (new CommonKeywordDefinitions())->create($version, $keywords),
            $this->legacy($version, $keywords),
            $this->beforeLts($version, $keywords),
            $this->mysql56($version, $keywords),
            $this->from57($version, $keywords),
            $this->beforeLts57($version, $keywords),
            $this->mysql57($version, $keywords),
            $this->from80($version, $keywords),
            $this->beforeLts80($version, $keywords),
            $this->from81($version, $keywords),
            $this->from82($version, $keywords),
            $this->from83($version, $keywords),
            $this->from84($version, $keywords),
            $this->from90($version, $keywords),
        );
    }

    /**
     * Declares the exact releases in which this group of registered terminals is handled.
     */
    public function legacy(string $version, KeywordLexemeGenerator $keywords): LexemeGenerator
    {
        return new MatchingLexemeGenerator(
            static fn (LexemeInput $input): bool => in_array($input->terminal()->name, [
                'ANALYSE_SYM', 'BIGINT', 'BINARY', 'BIT_AND', 'BIT_OR',
                'BIT_XOR', 'DATETIME', 'DEFAULT', 'DES_KEY_FILE', 'DISCARD',
                'ENUM', 'GEOMETRYCOLLECTION', 'INFILE', 'INSERT', 'LINESTRING',
                'LONGBLOB', 'LONGTEXT', 'MASTER_SERVER_ID_SYM', 'MEDIUMBLOB', 'MEDIUMINT',
                'MEDIUMTEXT', 'MULTILINESTRING', 'MULTIPOINT', 'MULTIPOLYGON', 'ON',
                'OUTER', 'POLYGON', 'REAL', 'REDOFILE_SYM', 'REPLACE',
                'SET', 'SMALLINT', 'SQL_CACHE_SYM', 'TIMESTAMP', 'TINYBLOB',
                'TINYINT', 'TINYTEXT', 'UNSIGNED', 'VARBINARY', 'VARCHAR',
                'ZEROFILL',
            ], true),
            new VersionedLexemeGenerator($version, new VersionCase(
                ['mysql-5.6.51', 'mysql-5.7.44'],
                $keywords,
                'mysql-keywords-2',
            )),
        );
    }

    /**
     * Declares the exact releases in which this group of registered terminals is handled.
     */
    public function beforeLts(string $version, KeywordLexemeGenerator $keywords): LexemeGenerator
    {
        return new MatchingLexemeGenerator(
            static fn (LexemeInput $input): bool => in_array($input->terminal()->name, [
                'MASTER_AUTO_POSITION_SYM', 'MASTER_BIND_SYM', 'MASTER_CONNECT_RETRY_SYM', 'MASTER_DELAY_SYM', 'MASTER_HEARTBEAT_PERIOD_SYM',
                'MASTER_HOST_SYM', 'MASTER_LOG_FILE_SYM', 'MASTER_LOG_POS_SYM', 'MASTER_PASSWORD_SYM', 'MASTER_PORT_SYM',
                'MASTER_RETRY_COUNT_SYM', 'MASTER_SSL_CAPATH_SYM', 'MASTER_SSL_CA_SYM', 'MASTER_SSL_CERT_SYM', 'MASTER_SSL_CIPHER_SYM',
                'MASTER_SSL_CRLPATH_SYM', 'MASTER_SSL_CRL_SYM', 'MASTER_SSL_KEY_SYM', 'MASTER_SSL_SYM', 'MASTER_SSL_VERIFY_SERVER_CERT_SYM',
                'MASTER_USER_SYM',
            ], true),
            new VersionedLexemeGenerator($version, new VersionCase(
                ['mysql-5.6.51', 'mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0'],
                $keywords,
                'mysql-keywords-3',
            )),
        );
    }

    /**
     * Declares the exact releases in which this group of registered terminals is handled.
     */
    public function mysql56(string $version, KeywordLexemeGenerator $keywords): LexemeGenerator
    {
        return new MatchingLexemeGenerator(
            static fn (LexemeInput $input): bool => in_array($input->terminal()->name, [
                'OLD_PASSWORD', 'TABLESPACE',
            ], true),
            new VersionedLexemeGenerator($version, new VersionCase(
                ['mysql-5.6.51'],
                $keywords,
                'mysql-keywords-4',
            )),
        );
    }

    /**
     * Declares the exact releases in which this group of registered terminals is handled.
     */
    public function from57(string $version, KeywordLexemeGenerator $keywords): LexemeGenerator
    {
        return new MatchingLexemeGenerator(
            static fn (LexemeInput $input): bool => in_array($input->terminal()->name, [
                'ACCOUNT_SYM', 'ALWAYS_SYM', 'BKA_HINT', 'BNL_HINT', 'CHANNEL_SYM',
                'COMPRESSION_SYM', 'DUPSWEEDOUT_HINT', 'ENCRYPTION_SYM', 'FILE_BLOCK_SIZE_SYM', 'FILTER_SYM',
                'FIRSTMATCH_HINT', 'FOLLOWS_SYM', 'GENERATED', 'GROUP_REPLICATION', 'INSTANCE_SYM',
                'INTOEXISTS_HINT', 'JSON_ARRAYAGG', 'JSON_OBJECTAGG', 'JSON_SYM', 'LOOSESCAN_HINT',
                'MATERIALIZATION_HINT', 'MAX_EXECUTION_TIME_HINT', 'MRR_HINT', 'NEVER_SYM', 'NO_BKA_HINT',
                'NO_BNL_HINT', 'NO_ICP_HINT', 'NO_MRR_HINT', 'NO_RANGE_OPTIMIZATION_HINT', 'NO_SEMIJOIN_HINT',
                'OPTIMIZER_COSTS_SYM', 'PRECEDES_SYM', 'QB_NAME_HINT', 'REPLICATE_DO_DB', 'REPLICATE_DO_TABLE',
                'REPLICATE_IGNORE_DB', 'REPLICATE_IGNORE_TABLE', 'REPLICATE_REWRITE_DB', 'REPLICATE_WILD_DO_TABLE', 'REPLICATE_WILD_IGNORE_TABLE',
                'ROTATE_SYM', 'SEMIJOIN_HINT', 'STACKED_SYM', 'STORED_SYM', 'SUBQUERY_HINT',
                'TABLESPACE_SYM', 'VALIDATION_SYM', 'VIRTUAL_SYM', 'WITHOUT_SYM', 'XID_SYM',
            ], true),
            new VersionedLexemeGenerator($version, new VersionCase(
                ['mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'],
                $keywords,
                'mysql-keywords-5',
            )),
        );
    }

    /**
     * Declares the exact releases in which this group of registered terminals is handled.
     */
    public function beforeLts57(string $version, KeywordLexemeGenerator $keywords): LexemeGenerator
    {
        return new MatchingLexemeGenerator(
            static fn (LexemeInput $input): bool => in_array($input->terminal()->name, [
                'MASTER_TLS_VERSION_SYM',
            ], true),
            new VersionedLexemeGenerator($version, new VersionCase(
                ['mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0'],
                $keywords,
                'mysql-keywords-6',
            )),
        );
    }

    /**
     * Declares the exact releases in which this group of registered terminals is handled.
     */
    public function mysql57(string $version, KeywordLexemeGenerator $keywords): LexemeGenerator
    {
        return new MatchingLexemeGenerator(
            static fn (LexemeInput $input): bool => in_array($input->terminal()->name, [
                'PARSE_GCOL_EXPR_SYM',
            ], true),
            new VersionedLexemeGenerator($version, new VersionCase(
                ['mysql-5.7.44'],
                $keywords,
                'mysql-keywords-7',
            )),
        );
    }

    /**
     * Declares the exact releases in which this group of registered terminals is handled.
     */
    public function from80(string $version, KeywordLexemeGenerator $keywords): LexemeGenerator
    {
        return new MatchingLexemeGenerator(
            static fn (LexemeInput $input): bool => in_array($input->terminal()->name, self::FROM_80, true),
            new VersionedLexemeGenerator($version, new VersionCase(
                ['mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'],
                $keywords,
                'mysql-keywords-8',
            )),
        );
    }

    /**
     * Declares the exact releases in which this group of registered terminals is handled.
     */
    public function beforeLts80(string $version, KeywordLexemeGenerator $keywords): LexemeGenerator
    {
        return new MatchingLexemeGenerator(
            static fn (LexemeInput $input): bool => in_array($input->terminal()->name, [
                'GET_MASTER_PUBLIC_KEY_SYM', 'MASTER_COMPRESSION_ALGORITHM_SYM', 'MASTER_PUBLIC_KEY_PATH_SYM', 'MASTER_TLS_CIPHERSUITES_SYM', 'MASTER_ZSTD_COMPRESSION_LEVEL_SYM',
            ], true),
            new VersionedLexemeGenerator($version, new VersionCase(
                ['mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0'],
                $keywords,
                'mysql-keywords-9',
            )),
        );
    }

    /**
     * Declares the exact releases in which this group of registered terminals is handled.
     */
    public function from81(string $version, KeywordLexemeGenerator $keywords): LexemeGenerator
    {
        return new MatchingLexemeGenerator(
            static fn (LexemeInput $input): bool => in_array($input->terminal()->name, [
                'PARSE_TREE_SYM',
            ], true),
            new VersionedLexemeGenerator($version, new VersionCase(
                ['mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'],
                $keywords,
                'mysql-keywords-10',
            )),
        );
    }

    /**
     * Declares the exact releases in which this group of registered terminals is handled.
     */
    public function from82(string $version, KeywordLexemeGenerator $keywords): LexemeGenerator
    {
        return new MatchingLexemeGenerator(
            static fn (LexemeInput $input): bool => in_array($input->terminal()->name, [
                'GTIDS_SYM', 'LOG_SYM', 'PARALLEL_SYM', 'S3_SYM',
            ], true),
            new VersionedLexemeGenerator($version, new VersionCase(
                ['mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'],
                $keywords,
                'mysql-keywords-11',
            )),
        );
    }

    /**
     * Declares the exact releases in which this group of registered terminals is handled.
     */
    public function from83(string $version, KeywordLexemeGenerator $keywords): LexemeGenerator
    {
        return new MatchingLexemeGenerator(
            static fn (LexemeInput $input): bool => in_array($input->terminal()->name, [
                'QUALIFY_SYM',
            ], true),
            new VersionedLexemeGenerator($version, new VersionCase(
                ['mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'],
                $keywords,
                'mysql-keywords-12',
            )),
        );
    }

    /**
     * Declares the exact releases in which this group of registered terminals is handled.
     */
    public function from84(string $version, KeywordLexemeGenerator $keywords): LexemeGenerator
    {
        return new MatchingLexemeGenerator(
            static fn (LexemeInput $input): bool => in_array($input->terminal()->name, [
                'AUTO_SYM', 'BERNOULLI_SYM', 'MANUAL_SYM', 'TABLESAMPLE_SYM',
            ], true),
            new VersionedLexemeGenerator($version, new VersionCase(
                ['mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'],
                $keywords,
                'mysql-keywords-13',
            )),
        );
    }

    /**
     * Declares the exact releases in which this group of registered terminals is handled.
     */
    public function from90(string $version, KeywordLexemeGenerator $keywords): LexemeGenerator
    {
        return new MatchingLexemeGenerator(
            static fn (LexemeInput $input): bool => in_array($input->terminal()->name, [
                'VECTOR_SYM',
            ], true),
            new VersionedLexemeGenerator($version, new VersionCase(
                ['mysql-9.0.1', 'mysql-9.1.0'],
                $keywords,
                'mysql-keywords-14',
            )),
        );
    }
}
