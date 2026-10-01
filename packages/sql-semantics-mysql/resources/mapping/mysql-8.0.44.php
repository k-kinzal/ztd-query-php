<?php

declare(strict_types=1);

/** Generated construction recipes; never retained by a Statement. */
return new \SqlSemantics\Core\Analysis\Vocabulary(array (
  'start_entry' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sql_statement',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StartEntryWithGrammarSelectorExprBitExprEndOfInput_a19910d8',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'GRAMMAR_SELECTOR_EXPR',
        1 => 'bit_expr',
        2 => 'END_OF_INPUT',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StartEntryWithGrammarSelectorPartPartitionClauseEndOfInput_e5b5ad55',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'GRAMMAR_SELECTOR_PART',
        1 => 'partition_clause',
        2 => 'END_OF_INPUT',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StartEntryWithGrammarSelectorGcolIdentSysExprEndOfInput_5a05b17d',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'GRAMMAR_SELECTOR_GCOL',
        1 => 'IDENT_sys',
        2 => '(',
        3 => 'expr',
        4 => ')',
        5 => 'END_OF_INPUT',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StartEntryWithGrammarSelectorCteTableSubqueryEndOfInput_051e7d03',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'GRAMMAR_SELECTOR_CTE',
        1 => 'table_subquery',
        2 => 'END_OF_INPUT',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StartEntryWithGrammarSelectorDerivedExprExprEndOfInput_52e234ff',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'GRAMMAR_SELECTOR_DERIVED_EXPR',
        1 => 'expr',
        2 => 'END_OF_INPUT',
      ),
    ),
  ),
  'sql_statement' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SqlStatementWithEndOfInput_6b0d544a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'END_OF_INPUT',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SqlStatementWithSimpleStatementOrBeginOptEndOfInput_e8dd5b0b',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'simple_statement_or_begin',
        1 => ';',
        2 => 'opt_end_of_input',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SqlStatementWithSimpleStatementOrBeginEndOfInput_cc601372',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'simple_statement_or_begin',
        1 => 'END_OF_INPUT',
      ),
    ),
  ),
  'opt_end_of_input' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptEndOfInputChoice_439083f3::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptEndOfInputChoice_439083f3::Use_e3b0c442',
      'symbols' =>
      array (
        0 => 'END_OF_INPUT',
      ),
    ),
  ),
  'simple_statement_or_begin' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'simple_statement',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'begin_stmt',
      ),
    ),
  ),
  'simple_statement' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_database_stmt',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_event_stmt',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_function_stmt',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_instance_stmt',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_logfile_stmt',
      ),
    ),
    5 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_procedure_stmt',
      ),
    ),
    6 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_resource_group_stmt',
      ),
    ),
    7 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_server_stmt',
      ),
    ),
    8 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_tablespace_stmt',
      ),
    ),
    9 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_undo_tablespace_stmt',
      ),
    ),
    10 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_table_stmt',
      ),
    ),
    11 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_user_stmt',
      ),
    ),
    12 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_view_stmt',
      ),
    ),
    13 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'analyze_table_stmt',
      ),
    ),
    14 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'binlog_base64_event',
      ),
    ),
    15 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'call_stmt',
      ),
    ),
    16 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'change',
      ),
    ),
    17 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'check_table_stmt',
      ),
    ),
    18 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'checksum',
      ),
    ),
    19 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'clone_stmt',
      ),
    ),
    20 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'commit',
      ),
    ),
    21 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'create',
      ),
    ),
    22 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'create_index_stmt',
      ),
    ),
    23 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'create_resource_group_stmt',
      ),
    ),
    24 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'create_role_stmt',
      ),
    ),
    25 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'create_srs_stmt',
      ),
    ),
    26 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'create_table_stmt',
      ),
    ),
    27 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'deallocate',
      ),
    ),
    28 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'delete_stmt',
      ),
    ),
    29 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'describe_stmt',
      ),
    ),
    30 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'do_stmt',
      ),
    ),
    31 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'drop_database_stmt',
      ),
    ),
    32 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'drop_event_stmt',
      ),
    ),
    33 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'drop_function_stmt',
      ),
    ),
    34 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'drop_index_stmt',
      ),
    ),
    35 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'drop_logfile_stmt',
      ),
    ),
    36 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'drop_procedure_stmt',
      ),
    ),
    37 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'drop_resource_group_stmt',
      ),
    ),
    38 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'drop_role_stmt',
      ),
    ),
    39 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'drop_server_stmt',
      ),
    ),
    40 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'drop_srs_stmt',
      ),
    ),
    41 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'drop_tablespace_stmt',
      ),
    ),
    42 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'drop_undo_tablespace_stmt',
      ),
    ),
    43 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'drop_table_stmt',
      ),
    ),
    44 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'drop_trigger_stmt',
      ),
    ),
    45 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'drop_user_stmt',
      ),
    ),
    46 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'drop_view_stmt',
      ),
    ),
    47 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'execute',
      ),
    ),
    48 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'explain_stmt',
      ),
    ),
    49 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'flush',
      ),
    ),
    50 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'get_diagnostics',
      ),
    ),
    51 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'group_replication',
      ),
    ),
    52 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'grant',
      ),
    ),
    53 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'handler_stmt',
      ),
    ),
    54 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'help',
      ),
    ),
    55 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'import_stmt',
      ),
    ),
    56 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'insert_stmt',
      ),
    ),
    57 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'install_stmt',
      ),
    ),
    58 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'kill',
      ),
    ),
    59 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'load_stmt',
      ),
    ),
    60 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'lock',
      ),
    ),
    61 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'optimize_table_stmt',
      ),
    ),
    62 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'keycache_stmt',
      ),
    ),
    63 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'preload_stmt',
      ),
    ),
    64 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'prepare',
      ),
    ),
    65 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'purge',
      ),
    ),
    66 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'release',
      ),
    ),
    67 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'rename',
      ),
    ),
    68 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'repair_table_stmt',
      ),
    ),
    69 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'replace_stmt',
      ),
    ),
    70 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'reset',
      ),
    ),
    71 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'resignal_stmt',
      ),
    ),
    72 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'restart_server_stmt',
      ),
    ),
    73 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'revoke',
      ),
    ),
    74 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'rollback',
      ),
    ),
    75 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'savepoint',
      ),
    ),
    76 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'select_stmt',
      ),
    ),
    77 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'set',
      ),
    ),
    78 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'set_resource_group_stmt',
      ),
    ),
    79 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'set_role_stmt',
      ),
    ),
    80 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_binary_logs_stmt',
      ),
    ),
    81 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_binlog_events_stmt',
      ),
    ),
    82 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_character_set_stmt',
      ),
    ),
    83 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_collation_stmt',
      ),
    ),
    84 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_columns_stmt',
      ),
    ),
    85 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_count_errors_stmt',
      ),
    ),
    86 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_count_warnings_stmt',
      ),
    ),
    87 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_create_database_stmt',
      ),
    ),
    88 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_create_event_stmt',
      ),
    ),
    89 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_create_function_stmt',
      ),
    ),
    90 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_create_procedure_stmt',
      ),
    ),
    91 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_create_table_stmt',
      ),
    ),
    92 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_create_trigger_stmt',
      ),
    ),
    93 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_create_user_stmt',
      ),
    ),
    94 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_create_view_stmt',
      ),
    ),
    95 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_databases_stmt',
      ),
    ),
    96 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_engine_logs_stmt',
      ),
    ),
    97 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_engine_mutex_stmt',
      ),
    ),
    98 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_engine_status_stmt',
      ),
    ),
    99 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_engines_stmt',
      ),
    ),
    100 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_errors_stmt',
      ),
    ),
    101 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_events_stmt',
      ),
    ),
    102 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_function_code_stmt',
      ),
    ),
    103 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_function_status_stmt',
      ),
    ),
    104 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_grants_stmt',
      ),
    ),
    105 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_keys_stmt',
      ),
    ),
    106 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_master_status_stmt',
      ),
    ),
    107 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_open_tables_stmt',
      ),
    ),
    108 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_plugins_stmt',
      ),
    ),
    109 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_privileges_stmt',
      ),
    ),
    110 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_procedure_code_stmt',
      ),
    ),
    111 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_procedure_status_stmt',
      ),
    ),
    112 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_processlist_stmt',
      ),
    ),
    113 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_profile_stmt',
      ),
    ),
    114 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_profiles_stmt',
      ),
    ),
    115 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_relaylog_events_stmt',
      ),
    ),
    116 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_replica_status_stmt',
      ),
    ),
    117 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_replicas_stmt',
      ),
    ),
    118 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_status_stmt',
      ),
    ),
    119 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_table_status_stmt',
      ),
    ),
    120 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_tables_stmt',
      ),
    ),
    121 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_triggers_stmt',
      ),
    ),
    122 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_variables_stmt',
      ),
    ),
    123 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show_warnings_stmt',
      ),
    ),
    124 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'shutdown_stmt',
      ),
    ),
    125 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'signal_stmt',
      ),
    ),
    126 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'start',
      ),
    ),
    127 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'start_replica_stmt',
      ),
    ),
    128 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'stop_replica_stmt',
      ),
    ),
    129 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'truncate_stmt',
      ),
    ),
    130 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'uninstall',
      ),
    ),
    131 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'unlock',
      ),
    ),
    132 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'update_stmt',
      ),
    ),
    133 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'use',
      ),
    ),
    134 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'xa',
      ),
    ),
  ),
  'deallocate' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DeallocateWithDeallocateOrDropPrepareSymIdent_32a2ecc2',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'deallocate_or_drop',
        1 => 'PREPARE_SYM',
        2 => 'ident',
      ),
    ),
  ),
  'deallocate_or_drop' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\DeallocateOrDropChoice_c47ac3ab::UseDeallocate_e349f57b',
      'symbols' =>
      array (
        0 => 'DEALLOCATE_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\DeallocateOrDropChoice_c47ac3ab::UseDrop_f3062ed5',
      'symbols' =>
      array (
        0 => 'DROP',
      ),
    ),
  ),
  'prepare' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PrepareWithPrepareSymIdentFromPrepareSrc_d5872283',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'PREPARE_SYM',
        1 => 'ident',
        2 => 'FROM',
        3 => 'prepare_src',
      ),
    ),
  ),
  'prepare_src' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'TEXT_STRING_sys',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PrepareSrcWithIdentOrText_9e3b898e',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '@',
        1 => 'ident_or_text',
      ),
    ),
  ),
  'execute' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExecuteWithExecuteSymIdentExecuteUsing_0904a9cf',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'EXECUTE_SYM',
        1 => 'ident',
        2 => 'execute_using',
      ),
    ),
  ),
  'execute_using' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExecuteUsingWith_48b68878',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExecuteUsingWithUsingExecuteVarList_1ff27432',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'USING',
        1 => 'execute_var_list',
      ),
    ),
  ),
  'execute_var_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExecuteVarListWithExecuteVarListExecuteVarIdent_52936c33',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'execute_var_list',
        1 => ',',
        2 => 'execute_var_ident',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'execute_var_ident',
      ),
    ),
  ),
  'execute_var_ident' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExecuteVarIdentWithIdentOrText_a743527b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '@',
        1 => 'ident_or_text',
      ),
    ),
  ),
  'help' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\HelpWithHelpSymIdentOrText_59ed8d59',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'HELP_SYM',
        1 => 'ident_or_text',
      ),
    ),
  ),
  'change_replication_source' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceChoice_3c418e5d::UseMaster_30e77240',
      'symbols' =>
      array (
        0 => 'MASTER_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceChoice_3c418e5d::UseReplicationSource_ca44d9ee',
      'symbols' =>
      array (
        0 => 'REPLICATION',
        1 => 'SOURCE_SYM',
      ),
    ),
  ),
  'change' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ChangeWithChangeChangeReplicationSourceToSymSourceDefsOptChannel_04ebdc59',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'CHANGE',
        1 => 'change_replication_source',
        2 => 'TO_SYM',
        3 => 'source_defs',
        4 => 'opt_channel',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ChangeWithChangeReplicationFilterSymFilterDefsOptChannel_ccf0bd55',
      'fields' =>
      array (
        0 => 3,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'CHANGE',
        1 => 'REPLICATION',
        2 => 'FILTER_SYM',
        3 => 'filter_defs',
        4 => 'opt_channel',
      ),
    ),
  ),
  'filter_defs' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'filter_def',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FilterDefsWithFilterDefsFilterDef_f7c5468a',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'filter_defs',
        1 => ',',
        2 => 'filter_def',
      ),
    ),
  ),
  'filter_def' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FilterDefWithReplicateDoDbEqOptFilterDbList_24f1574f',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'REPLICATE_DO_DB',
        1 => 'EQ',
        2 => 'opt_filter_db_list',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FilterDefWithReplicateIgnoreDbEqOptFilterDbList_e68c7a5c',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'REPLICATE_IGNORE_DB',
        1 => 'EQ',
        2 => 'opt_filter_db_list',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FilterDefWithReplicateDoTableEqOptFilterTableList_96b8eea7',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'REPLICATE_DO_TABLE',
        1 => 'EQ',
        2 => 'opt_filter_table_list',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FilterDefWithReplicateIgnoreTableEqOptFilterTableList_213cb86a',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'REPLICATE_IGNORE_TABLE',
        1 => 'EQ',
        2 => 'opt_filter_table_list',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FilterDefWithReplicateWildDoTableEqOptFilterStringList_fcf50be5',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'REPLICATE_WILD_DO_TABLE',
        1 => 'EQ',
        2 => 'opt_filter_string_list',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FilterDefWithReplicateWildIgnoreTableEqOptFilterStringList_de391eb0',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'REPLICATE_WILD_IGNORE_TABLE',
        1 => 'EQ',
        2 => 'opt_filter_string_list',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FilterDefWithReplicateRewriteDbEqOptFilterDbPairList_18447a6d',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'REPLICATE_REWRITE_DB',
        1 => 'EQ',
        2 => 'opt_filter_db_pair_list',
      ),
    ),
  ),
  'opt_filter_db_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptFilterDbListWith_83f061a3',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptFilterDbListWithFilterDbList_21580376',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'filter_db_list',
        2 => ')',
      ),
    ),
  ),
  'filter_db_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'filter_db_ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FilterDbListWithFilterDbListFilterDbIdent_f0ae01d2',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'filter_db_list',
        1 => ',',
        2 => 'filter_db_ident',
      ),
    ),
  ),
  'filter_db_ident' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
  ),
  'opt_filter_db_pair_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptFilterDbPairListWith_7a6bccb2',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptFilterDbPairListWithFilterDbPairList_23fe7981',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'filter_db_pair_list',
        2 => ')',
      ),
    ),
  ),
  'filter_db_pair_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FilterDbPairListWithFilterDbIdentFilterDbIdent_74a6fe4e',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'filter_db_ident',
        2 => ',',
        3 => 'filter_db_ident',
        4 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FilterDbPairListWithFilterDbPairListFilterDbIdentFilterDbIdent_a1228328',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'filter_db_pair_list',
        1 => ',',
        2 => '(',
        3 => 'filter_db_ident',
        4 => ',',
        5 => 'filter_db_ident',
        6 => ')',
      ),
    ),
  ),
  'opt_filter_table_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptFilterTableListWith_1a65f7ea',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptFilterTableListWithFilterTableList_f45fe0e7',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'filter_table_list',
        2 => ')',
      ),
    ),
  ),
  'filter_table_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'filter_table_ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FilterTableListWithFilterTableListFilterTableIdent_b1477413',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'filter_table_list',
        1 => ',',
        2 => 'filter_table_ident',
      ),
    ),
  ),
  'filter_table_ident' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FilterTableIdentWithSchemaIdent_3f622d2c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'schema',
        1 => '.',
        2 => 'ident',
      ),
    ),
  ),
  'opt_filter_string_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptFilterStringListWith_1cd09dc6',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptFilterStringListWithFilterStringList_fc6faf46',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'filter_string_list',
        2 => ')',
      ),
    ),
  ),
  'filter_string_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'filter_string',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FilterStringListWithFilterStringListFilterString_c5dc6bf0',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'filter_string_list',
        1 => ',',
        2 => 'filter_string',
      ),
    ),
  ),
  'filter_string' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'filter_wild_db_table_string',
      ),
    ),
  ),
  'source_defs' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'source_def',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefsWithSourceDefsSourceDef_5e3868a5',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'source_defs',
        1 => ',',
        2 => 'source_def',
      ),
    ),
  ),
  'change_replication_source_auto_position' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceAutoPositionChoice_05740ac9::UseMasterAutoPosition_fb73bc1d',
      'symbols' =>
      array (
        0 => 'MASTER_AUTO_POSITION_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceAutoPositionChoice_05740ac9::UseSourceAutoPosition_8c283d8d',
      'symbols' =>
      array (
        0 => 'SOURCE_AUTO_POSITION_SYM',
      ),
    ),
  ),
  'change_replication_source_host' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceHostChoice_dd9b573d::UseMasterHost_6243f77d',
      'symbols' =>
      array (
        0 => 'MASTER_HOST_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceHostChoice_dd9b573d::UseSourceHost_6a686af5',
      'symbols' =>
      array (
        0 => 'SOURCE_HOST_SYM',
      ),
    ),
  ),
  'change_replication_source_bind' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceBindChoice_9a547d6e::UseMasterBind_9365e0c1',
      'symbols' =>
      array (
        0 => 'MASTER_BIND_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceBindChoice_9a547d6e::UseSourceBind_a5b81c2f',
      'symbols' =>
      array (
        0 => 'SOURCE_BIND_SYM',
      ),
    ),
  ),
  'change_replication_source_user' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceUserChoice_2b662003::UseMasterUser_69910c29',
      'symbols' =>
      array (
        0 => 'MASTER_USER_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceUserChoice_2b662003::UseSourceUser_0af213fc',
      'symbols' =>
      array (
        0 => 'SOURCE_USER_SYM',
      ),
    ),
  ),
  'change_replication_source_password' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourcePasswordChoice_5f715a45::UseMasterPassword_9ba29272',
      'symbols' =>
      array (
        0 => 'MASTER_PASSWORD_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourcePasswordChoice_5f715a45::UseSourcePassword_cb455082',
      'symbols' =>
      array (
        0 => 'SOURCE_PASSWORD_SYM',
      ),
    ),
  ),
  'change_replication_source_port' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourcePortChoice_317b5084::UseMasterPort_9bd4c62c',
      'symbols' =>
      array (
        0 => 'MASTER_PORT_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourcePortChoice_317b5084::UseSourcePort_68734bd9',
      'symbols' =>
      array (
        0 => 'SOURCE_PORT_SYM',
      ),
    ),
  ),
  'change_replication_source_connect_retry' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceConnectRetryChoice_bbf0e642::UseMasterConnectRetry_dd62b4b4',
      'symbols' =>
      array (
        0 => 'MASTER_CONNECT_RETRY_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceConnectRetryChoice_bbf0e642::UseSourceConnectRetry_1ed86dba',
      'symbols' =>
      array (
        0 => 'SOURCE_CONNECT_RETRY_SYM',
      ),
    ),
  ),
  'change_replication_source_retry_count' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceRetryCountChoice_5b957526::UseMasterRetryCount_c07ebb57',
      'symbols' =>
      array (
        0 => 'MASTER_RETRY_COUNT_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceRetryCountChoice_5b957526::UseSourceRetryCount_492586e1',
      'symbols' =>
      array (
        0 => 'SOURCE_RETRY_COUNT_SYM',
      ),
    ),
  ),
  'change_replication_source_delay' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceDelayChoice_23f94cd7::UseMasterDelay_46d8303f',
      'symbols' =>
      array (
        0 => 'MASTER_DELAY_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceDelayChoice_23f94cd7::UseSourceDelay_a4539d40',
      'symbols' =>
      array (
        0 => 'SOURCE_DELAY_SYM',
      ),
    ),
  ),
  'change_replication_source_ssl' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceSslChoice_f5524761::UseMasterSsl_d51eade6',
      'symbols' =>
      array (
        0 => 'MASTER_SSL_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceSslChoice_f5524761::UseSourceSsl_40e45bd2',
      'symbols' =>
      array (
        0 => 'SOURCE_SSL_SYM',
      ),
    ),
  ),
  'change_replication_source_ssl_ca' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceSslCaChoice_10ee1032::UseMasterSslCa_5752f531',
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CA_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceSslCaChoice_10ee1032::UseSourceSslCa_c64b1381',
      'symbols' =>
      array (
        0 => 'SOURCE_SSL_CA_SYM',
      ),
    ),
  ),
  'change_replication_source_ssl_capath' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceSslCapathChoice_d4dd319d::UseMasterSslCapath_6601f6af',
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CAPATH_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceSslCapathChoice_d4dd319d::UseSourceSslCapath_a1f8a98c',
      'symbols' =>
      array (
        0 => 'SOURCE_SSL_CAPATH_SYM',
      ),
    ),
  ),
  'change_replication_source_ssl_cipher' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceSslCipherChoice_b9ec872e::UseMasterSslCipher_6545252f',
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CIPHER_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceSslCipherChoice_b9ec872e::UseSourceSslCipher_895a09b2',
      'symbols' =>
      array (
        0 => 'SOURCE_SSL_CIPHER_SYM',
      ),
    ),
  ),
  'change_replication_source_ssl_crl' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceSslCrlChoice_ba7c3584::UseMasterSslCrl_0f884349',
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CRL_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceSslCrlChoice_ba7c3584::UseSourceSslCrl_884196ab',
      'symbols' =>
      array (
        0 => 'SOURCE_SSL_CRL_SYM',
      ),
    ),
  ),
  'change_replication_source_ssl_crlpath' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceSslCrlpathChoice_37df6a87::UseMasterSslCrlpath_5ff0d704',
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CRLPATH_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceSslCrlpathChoice_37df6a87::UseSourceSslCrlpath_6bd956ff',
      'symbols' =>
      array (
        0 => 'SOURCE_SSL_CRLPATH_SYM',
      ),
    ),
  ),
  'change_replication_source_ssl_key' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceSslKeyChoice_2e699592::UseMasterSslKey_15a7e69d',
      'symbols' =>
      array (
        0 => 'MASTER_SSL_KEY_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceSslKeyChoice_2e699592::UseSourceSslKey_73de7649',
      'symbols' =>
      array (
        0 => 'SOURCE_SSL_KEY_SYM',
      ),
    ),
  ),
  'change_replication_source_ssl_verify_server_cert' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceSslVerifyServerCertChoice_9caa0d1f::UseMasterSslVerifyServerCert_f0a3303f',
      'symbols' =>
      array (
        0 => 'MASTER_SSL_VERIFY_SERVER_CERT_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceSslVerifyServerCertChoice_9caa0d1f::UseSourceSslVerifyServerCert_3fb50364',
      'symbols' =>
      array (
        0 => 'SOURCE_SSL_VERIFY_SERVER_CERT_SYM',
      ),
    ),
  ),
  'change_replication_source_tls_version' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceTlsVersionChoice_2601071a::UseMasterTlsVersion_28fb5a3a',
      'symbols' =>
      array (
        0 => 'MASTER_TLS_VERSION_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceTlsVersionChoice_2601071a::UseSourceTlsVersion_32f3c227',
      'symbols' =>
      array (
        0 => 'SOURCE_TLS_VERSION_SYM',
      ),
    ),
  ),
  'change_replication_source_tls_ciphersuites' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceTlsCiphersuitesChoice_cb6ca2ef::UseMasterTlsCiphersuites_0780b74c',
      'symbols' =>
      array (
        0 => 'MASTER_TLS_CIPHERSUITES_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceTlsCiphersuitesChoice_cb6ca2ef::UseSourceTlsCiphersuites_bb87c833',
      'symbols' =>
      array (
        0 => 'SOURCE_TLS_CIPHERSUITES_SYM',
      ),
    ),
  ),
  'change_replication_source_ssl_cert' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceSslCertChoice_e523488f::UseMasterSslCert_d72fef78',
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CERT_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceSslCertChoice_e523488f::UseSourceSslCert_b7f592ab',
      'symbols' =>
      array (
        0 => 'SOURCE_SSL_CERT_SYM',
      ),
    ),
  ),
  'change_replication_source_public_key' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourcePublicKeyChoice_0d7ab5ea::UseMasterPublicKeyPath_38dfaf84',
      'symbols' =>
      array (
        0 => 'MASTER_PUBLIC_KEY_PATH_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourcePublicKeyChoice_0d7ab5ea::UseSourcePublicKeyPath_448715c0',
      'symbols' =>
      array (
        0 => 'SOURCE_PUBLIC_KEY_PATH_SYM',
      ),
    ),
  ),
  'change_replication_source_get_source_public_key' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceGetSourcePublicKeyChoice_e3e63dc2::UseGetMasterPublicKey_2a3c2138',
      'symbols' =>
      array (
        0 => 'GET_MASTER_PUBLIC_KEY_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceGetSourcePublicKeyChoice_e3e63dc2::UseGetSourcePublicKey_8c748a8e',
      'symbols' =>
      array (
        0 => 'GET_SOURCE_PUBLIC_KEY_SYM',
      ),
    ),
  ),
  'change_replication_source_heartbeat_period' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceHeartbeatPeriodChoice_e5a96520::UseMasterHeartbeatPeriod_1d182c62',
      'symbols' =>
      array (
        0 => 'MASTER_HEARTBEAT_PERIOD_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceHeartbeatPeriodChoice_e5a96520::UseSourceHeartbeatPeriod_f085c427',
      'symbols' =>
      array (
        0 => 'SOURCE_HEARTBEAT_PERIOD_SYM',
      ),
    ),
  ),
  'change_replication_source_compression_algorithm' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceCompressionAlgorithmChoice_e81e4221::UseMasterCompressionAlgorithms_b466a9dc',
      'symbols' =>
      array (
        0 => 'MASTER_COMPRESSION_ALGORITHM_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceCompressionAlgorithmChoice_e81e4221::UseSourceCompressionAlgorithms_fd2872c4',
      'symbols' =>
      array (
        0 => 'SOURCE_COMPRESSION_ALGORITHM_SYM',
      ),
    ),
  ),
  'change_replication_source_zstd_compression_level' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceZstdCompressionLevelChoice_690c94ad::UseMasterZstdCompressionLevel_db3005a2',
      'symbols' =>
      array (
        0 => 'MASTER_ZSTD_COMPRESSION_LEVEL_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ChangeReplicationSourceZstdCompressionLevelChoice_690c94ad::UseSourceZstdCompressionLevel_178ee386',
      'symbols' =>
      array (
        0 => 'SOURCE_ZSTD_COMPRESSION_LEVEL_SYM',
      ),
    ),
  ),
  'source_def' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceHostEqTextStringSysNonewline_f195e9a2',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_host',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithNetworkNamespaceSymEqTextStringSysNonewline_84a11718',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'NETWORK_NAMESPACE_SYM',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceBindEqTextStringSysNonewline_8106204f',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_bind',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceUserEqTextStringSysNonewline_587c80d2',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_user',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourcePasswordEqTextStringSysNonewline_1f036623',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_password',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourcePortEqUlongNum_acc7cd2f',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_port',
        1 => 'EQ',
        2 => 'ulong_num',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceConnectRetryEqUlongNum_daf4c6b3',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_connect_retry',
        1 => 'EQ',
        2 => 'ulong_num',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceRetryCountEqUlongNum_4b7458f0',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_retry_count',
        1 => 'EQ',
        2 => 'ulong_num',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceDelayEqUlongNum_ec89a3e3',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_delay',
        1 => 'EQ',
        2 => 'ulong_num',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceSslEqUlongNum_2d59d3e5',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_ssl',
        1 => 'EQ',
        2 => 'ulong_num',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceSslCaEqTextStringSysNonewline_e62fb5e6',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_ssl_ca',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceSslCapathEqTextStringSysNonewline_d843abda',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_ssl_capath',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceTlsVersionEqTextStringSysNonewline_efe67d2c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_tls_version',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceTlsCiphersuitesEqSourceTlsCiphersuitesDef_d9ad0d93',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_tls_ciphersuites',
        1 => 'EQ',
        2 => 'source_tls_ciphersuites_def',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceSslCertEqTextStringSysNonewline_6c425c9d',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_ssl_cert',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceSslCipherEqTextStringSysNonewline_a8201ff6',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_ssl_cipher',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceSslKeyEqTextStringSysNonewline_f1cb73a5',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_ssl_key',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceSslVerifyServerCertEqUlongNum_06984f12',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_ssl_verify_server_cert',
        1 => 'EQ',
        2 => 'ulong_num',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceSslCrlEqTextStringSysNonewline_4be1a30e',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_ssl_crl',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceSslCrlpathEqTextStringSysNonewline_69bbfa50',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_ssl_crlpath',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourcePublicKeyEqTextStringSysNonewline_75025a40',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_public_key',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    21 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceGetSourcePublicKeyEqUlongNum_4544eda1',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_get_source_public_key',
        1 => 'EQ',
        2 => 'ulong_num',
      ),
    ),
    22 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceHeartbeatPeriodEqNumLiteral_0f59b648',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_heartbeat_period',
        1 => 'EQ',
        2 => 'NUM_literal',
      ),
    ),
    23 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithIgnoreServerIdsSymEqIgnoreServerIdList_67fee0b7',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'IGNORE_SERVER_IDS_SYM',
        1 => 'EQ',
        2 => '(',
        3 => 'ignore_server_id_list',
        4 => ')',
      ),
    ),
    24 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceCompressionAlgorithmEqTextStringSys_f8f85925',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_compression_algorithm',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys',
      ),
    ),
    25 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceZstdCompressionLevelEqUlongNum_28360796',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_zstd_compression_level',
        1 => 'EQ',
        2 => 'ulong_num',
      ),
    ),
    26 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithChangeReplicationSourceAutoPositionEqUlongNum_c18d87f8',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_replication_source_auto_position',
        1 => 'EQ',
        2 => 'ulong_num',
      ),
    ),
    27 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithPrivilegeChecksUserSymEqPrivilegeCheckDef_8bc6ef74',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'PRIVILEGE_CHECKS_USER_SYM',
        1 => 'EQ',
        2 => 'privilege_check_def',
      ),
    ),
    28 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithRequireRowFormatSymEqUlongNum_8ce9dd57',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'REQUIRE_ROW_FORMAT_SYM',
        1 => 'EQ',
        2 => 'ulong_num',
      ),
    ),
    29 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithRequireTablePrimaryKeyCheckSymEqTablePrimaryKeyCheckDef_7cb39007',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'REQUIRE_TABLE_PRIMARY_KEY_CHECK_SYM',
        1 => 'EQ',
        2 => 'table_primary_key_check_def',
      ),
    ),
    30 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithSourceConnectionAutoFailoverSymEqRealUlongNum_02e2ade5',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_CONNECTION_AUTO_FAILOVER_SYM',
        1 => 'EQ',
        2 => 'real_ulong_num',
      ),
    ),
    31 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithAssignGtidsToAnonymousTransactionsSymEqAssignGtidsToAnonymousTransactionsDe_321c91eb',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS_SYM',
        1 => 'EQ',
        2 => 'assign_gtids_to_anonymous_transactions_def',
      ),
    ),
    32 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceDefWithGtidOnlySymEqRealUlongNum_04ea876a',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'GTID_ONLY_SYM',
        1 => 'EQ',
        2 => 'real_ulong_num',
      ),
    ),
    33 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'source_file_def',
      ),
    ),
  ),
  'ignore_server_id_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IgnoreServerIdListWith_844b1f8d',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ignore_server_id',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IgnoreServerIdListWithIgnoreServerIdListIgnoreServerId_f106ddf6',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ignore_server_id_list',
        1 => ',',
        2 => 'ignore_server_id',
      ),
    ),
  ),
  'ignore_server_id' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ulong_num',
      ),
    ),
  ),
  'privilege_check_def' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'user_ident_or_text',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PrivilegeCheckDefWithNullSym_a9884fa5',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NULL_SYM',
      ),
    ),
  ),
  'table_primary_key_check_def' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TablePrimaryKeyCheckDefChoice_5e389903::UseStream_df2ff3bb',
      'symbols' =>
      array (
        0 => 'STREAM_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TablePrimaryKeyCheckDefChoice_5e389903::UseOn_e8a01133',
      'symbols' =>
      array (
        0 => 'ON_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TablePrimaryKeyCheckDefChoice_5e389903::UseOff_38cca6be',
      'symbols' =>
      array (
        0 => 'OFF_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TablePrimaryKeyCheckDefChoice_5e389903::UseGenerate_1cd35aa5',
      'symbols' =>
      array (
        0 => 'GENERATE_SYM',
      ),
    ),
  ),
  'assign_gtids_to_anonymous_transactions_def' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AssignGtidsToAnonymousTransactionsDefWithOffSym_5ed95d9b',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'OFF_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AssignGtidsToAnonymousTransactionsDefWithLocalSym_ea6949be',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'LOCAL_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AssignGtidsToAnonymousTransactionsDefWithTextString_b67230e9',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TEXT_STRING',
      ),
    ),
  ),
  'source_tls_ciphersuites_def' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceTlsCiphersuitesDefWithNullSym_6464911a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NULL_SYM',
      ),
    ),
  ),
  'source_log_file' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SourceLogFileChoice_cac2112c::UseMasterLogFile_106ccab6',
      'symbols' =>
      array (
        0 => 'MASTER_LOG_FILE_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SourceLogFileChoice_cac2112c::UseSourceLogFile_d13af792',
      'symbols' =>
      array (
        0 => 'SOURCE_LOG_FILE_SYM',
      ),
    ),
  ),
  'source_log_pos' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SourceLogPosChoice_64b7706c::UseMasterLogPos_a017f1e2',
      'symbols' =>
      array (
        0 => 'MASTER_LOG_POS_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SourceLogPosChoice_64b7706c::UseSourceLogPos_cc3aa6d9',
      'symbols' =>
      array (
        0 => 'SOURCE_LOG_POS_SYM',
      ),
    ),
  ),
  'source_file_def' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceFileDefWithSourceLogFileEqTextStringSysNonewline_5cdeb6bb',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'source_log_file',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceFileDefWithSourceLogPosEqUlonglongNum_a6f679e7',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'source_log_pos',
        1 => 'EQ',
        2 => 'ulonglong_num',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceFileDefWithRelayLogFileSymEqTextStringSysNonewline_b2c2b365',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'RELAY_LOG_FILE_SYM',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceFileDefWithRelayLogPosSymEqUlongNum_048f1305',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'RELAY_LOG_POS_SYM',
        1 => 'EQ',
        2 => 'ulong_num',
      ),
    ),
  ),
  'opt_channel' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptChannelWith_93aa3f91',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptChannelWithForSymChannelSymTextStringSysNonewline_69b19953',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'FOR_SYM',
        1 => 'CHANNEL_SYM',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
  ),
  'create_table_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableStmtWithCreateOptTemporaryTableSymOptIfNotExistsTableIdentTableElementListOptCreate_42b9f2b6',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 4,
        3 => 6,
        4 => 8,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'opt_temporary',
        2 => 'TABLE_SYM',
        3 => 'opt_if_not_exists',
        4 => 'table_ident',
        5 => '(',
        6 => 'table_element_list',
        7 => ')',
        8 => 'opt_create_table_options_etc',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableStmtWithCreateOptTemporaryTableSymOptIfNotExistsTableIdentOptCreateTableOptionsEtc_6d2161ee',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 4,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'opt_temporary',
        2 => 'TABLE_SYM',
        3 => 'opt_if_not_exists',
        4 => 'table_ident',
        5 => 'opt_create_table_options_etc',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableStmtWithCreateOptTemporaryTableSymOptIfNotExistsTableIdentLikeTableIdent_a1a5b49d',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 4,
        3 => 6,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'opt_temporary',
        2 => 'TABLE_SYM',
        3 => 'opt_if_not_exists',
        4 => 'table_ident',
        5 => 'LIKE',
        6 => 'table_ident',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableStmtWithCreateOptTemporaryTableSymOptIfNotExistsTableIdentLikeTableIdent_6eac2bcb',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 4,
        3 => 7,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'opt_temporary',
        2 => 'TABLE_SYM',
        3 => 'opt_if_not_exists',
        4 => 'table_ident',
        5 => '(',
        6 => 'LIKE',
        7 => 'table_ident',
        8 => ')',
      ),
    ),
  ),
  'create_role_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateRoleStmtWithCreateRoleSymOptIfNotExistsRoleList_7ad7bbd3',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'ROLE_SYM',
        2 => 'opt_if_not_exists',
        3 => 'role_list',
      ),
    ),
  ),
  'create_resource_group_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateResourceGroupStmtWithCreateResourceSymGroupSymIdentTypeSymOptEqualResourceGroupTypesOptResourceG_b3fe291b',
      'fields' =>
      array (
        0 => 3,
        1 => 5,
        2 => 6,
        3 => 7,
        4 => 8,
        5 => 9,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'RESOURCE_SYM',
        2 => 'GROUP_SYM',
        3 => 'ident',
        4 => 'TYPE_SYM',
        5 => 'opt_equal',
        6 => 'resource_group_types',
        7 => 'opt_resource_group_vcpu_list',
        8 => 'opt_resource_group_priority',
        9 => 'opt_resource_group_enable_disable',
      ),
    ),
  ),
  'create' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateWithCreateDatabaseOptIfNotExistsIdentOptCreateDatabaseOptions_01299d72',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'DATABASE',
        2 => 'opt_if_not_exists',
        3 => 'ident',
        4 => 'opt_create_database_options',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateWithCreateViewOrTriggerOrSpOrEvent_54aaed13',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'view_or_trigger_or_sp_or_event',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateWithCreateUserOptIfNotExistsCreateUserListDefaultRoleClauseRequireClauseConnect_8ff04e8d',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 6,
        6 => 7,
        7 => 8,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'USER',
        2 => 'opt_if_not_exists',
        3 => 'create_user_list',
        4 => 'default_role_clause',
        5 => 'require_clause',
        6 => 'connect_options',
        7 => 'opt_account_lock_password_expire_options',
        8 => 'opt_user_attribute',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateWithCreateLogfileSymGroupSymIdentAddLgUndofileOptLogfileGroupOptions_c870954e',
      'fields' =>
      array (
        0 => 3,
        1 => 5,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'LOGFILE_SYM',
        2 => 'GROUP_SYM',
        3 => 'ident',
        4 => 'ADD',
        5 => 'lg_undofile',
        6 => 'opt_logfile_group_options',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateWithCreateTablespaceSymIdentOptTsDatafileNameOptLogfileGroupNameOptTablespaceOp_7a90bcdf',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'TABLESPACE_SYM',
        2 => 'ident',
        3 => 'opt_ts_datafile_name',
        4 => 'opt_logfile_group_name',
        5 => 'opt_tablespace_options',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateWithCreateUndoSymTablespaceSymIdentAddTsDatafileOptUndoTablespaceOptions_cfd82de7',
      'fields' =>
      array (
        0 => 3,
        1 => 5,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'UNDO_SYM',
        2 => 'TABLESPACE_SYM',
        3 => 'ident',
        4 => 'ADD',
        5 => 'ts_datafile',
        6 => 'opt_undo_tablespace_options',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateWithCreateServerSymIdentOrTextForeignDataSymWrapperSymIdentOrTextOptionsSymServ_70badfce',
      'fields' =>
      array (
        0 => 2,
        1 => 6,
        2 => 9,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'SERVER_SYM',
        2 => 'ident_or_text',
        3 => 'FOREIGN',
        4 => 'DATA_SYM',
        5 => 'WRAPPER_SYM',
        6 => 'ident_or_text',
        7 => 'OPTIONS_SYM',
        8 => '(',
        9 => 'server_options_list',
        10 => ')',
      ),
    ),
  ),
  'create_srs_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateSrsStmtWithCreateOrSymReplaceSymSpatialSymReferenceSymSystemSymRealUlonglongNumSrsAttr_566de541',
      'fields' =>
      array (
        0 => 6,
        1 => 7,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'OR_SYM',
        2 => 'REPLACE_SYM',
        3 => 'SPATIAL_SYM',
        4 => 'REFERENCE_SYM',
        5 => 'SYSTEM_SYM',
        6 => 'real_ulonglong_num',
        7 => 'srs_attributes',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateSrsStmtWithCreateSpatialSymReferenceSymSystemSymOptIfNotExistsRealUlonglongNumSrsAttri_ffea6152',
      'fields' =>
      array (
        0 => 4,
        1 => 5,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'SPATIAL_SYM',
        2 => 'REFERENCE_SYM',
        3 => 'SYSTEM_SYM',
        4 => 'opt_if_not_exists',
        5 => 'real_ulonglong_num',
        6 => 'srs_attributes',
      ),
    ),
  ),
  'srs_attributes' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SrsAttributesWith_67d2b7e9',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SrsAttributesWithSrsAttributesNameSymTextStringSysNonewline_1a687ed9',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'srs_attributes',
        1 => 'NAME_SYM',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SrsAttributesWithSrsAttributesDefinitionSymTextStringSysNonewline_70c0facc',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'srs_attributes',
        1 => 'DEFINITION_SYM',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SrsAttributesWithSrsAttributesOrganizationSymTextStringSysNonewlineIdentifiedSymByRealUlongl_b2332281',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'srs_attributes',
        1 => 'ORGANIZATION_SYM',
        2 => 'TEXT_STRING_sys_nonewline',
        3 => 'IDENTIFIED_SYM',
        4 => 'BY',
        5 => 'real_ulonglong_num',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SrsAttributesWithSrsAttributesDescriptionSymTextStringSysNonewline_065f130b',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'srs_attributes',
        1 => 'DESCRIPTION_SYM',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
  ),
  'default_role_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DefaultRoleClauseWith_59cb6592',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DefaultRoleClauseWithDefaultSymRoleSymRoleList_b2abf4bd',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT_SYM',
        1 => 'ROLE_SYM',
        2 => 'role_list',
      ),
    ),
  ),
  'create_index_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateIndexStmtWithCreateOptUniqueIndexSymIdentOptIndexTypeClauseOnSymTableIdentKeyListWithExp_55ecda89',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 4,
        3 => 6,
        4 => 8,
        5 => 10,
        6 => 11,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'opt_unique',
        2 => 'INDEX_SYM',
        3 => 'ident',
        4 => 'opt_index_type_clause',
        5 => 'ON_SYM',
        6 => 'table_ident',
        7 => '(',
        8 => 'key_list_with_expression',
        9 => ')',
        10 => 'opt_index_options',
        11 => 'opt_index_lock_and_algorithm',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateIndexStmtWithCreateFulltextSymIndexSymIdentOnSymTableIdentKeyListWithExpressionOptFullte_2d5d6d89',
      'fields' =>
      array (
        0 => 3,
        1 => 5,
        2 => 7,
        3 => 9,
        4 => 10,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'FULLTEXT_SYM',
        2 => 'INDEX_SYM',
        3 => 'ident',
        4 => 'ON_SYM',
        5 => 'table_ident',
        6 => '(',
        7 => 'key_list_with_expression',
        8 => ')',
        9 => 'opt_fulltext_index_options',
        10 => 'opt_index_lock_and_algorithm',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateIndexStmtWithCreateSpatialSymIndexSymIdentOnSymTableIdentKeyListWithExpressionOptSpatial_e11a9a1f',
      'fields' =>
      array (
        0 => 3,
        1 => 5,
        2 => 7,
        3 => 9,
        4 => 10,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'SPATIAL_SYM',
        2 => 'INDEX_SYM',
        3 => 'ident',
        4 => 'ON_SYM',
        5 => 'table_ident',
        6 => '(',
        7 => 'key_list_with_expression',
        8 => ')',
        9 => 'opt_spatial_index_options',
        10 => 'opt_index_lock_and_algorithm',
      ),
    ),
  ),
  'server_options_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'server_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ServerOptionsListWithServerOptionsListServerOption_f2ff17cf',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'server_options_list',
        1 => ',',
        2 => 'server_option',
      ),
    ),
  ),
  'server_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ServerOptionWithUserTextStringSys_0385ab07',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'USER',
        1 => 'TEXT_STRING_sys',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ServerOptionWithHostSymTextStringSys_adb8a619',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'HOST_SYM',
        1 => 'TEXT_STRING_sys',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ServerOptionWithDatabaseTextStringSys_7d5ccf48',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DATABASE',
        1 => 'TEXT_STRING_sys',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ServerOptionWithOwnerSymTextStringSys_5e05a943',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'OWNER_SYM',
        1 => 'TEXT_STRING_sys',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ServerOptionWithPasswordTextStringSys_f80122c0',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'TEXT_STRING_sys',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ServerOptionWithSocketSymTextStringSys_00173a64',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SOCKET_SYM',
        1 => 'TEXT_STRING_sys',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ServerOptionWithPortSymUlongNum_fd07b46c',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'PORT_SYM',
        1 => 'ulong_num',
      ),
    ),
  ),
  'event_tail' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\EventTailWithEventSymOptIfNotExistsSpNameOnSymScheduleSymEvScheduleTimeOptEvOnCompletion_90fb9d80',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 5,
        3 => 6,
        4 => 7,
        5 => 8,
        6 => 10,
      ),
      'symbols' =>
      array (
        0 => 'EVENT_SYM',
        1 => 'opt_if_not_exists',
        2 => 'sp_name',
        3 => 'ON_SYM',
        4 => 'SCHEDULE_SYM',
        5 => 'ev_schedule_time',
        6 => 'opt_ev_on_completion',
        7 => 'opt_ev_status',
        8 => 'opt_ev_comment',
        9 => 'DO_SYM',
        10 => 'ev_sql_stmt',
      ),
    ),
  ),
  'ev_schedule_time' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\EvScheduleTimeWithEverySymExprIntervalEvStartsEvEnds_b360a351',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'EVERY_SYM',
        1 => 'expr',
        2 => 'interval',
        3 => 'ev_starts',
        4 => 'ev_ends',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\EvScheduleTimeWithAtSymExpr_78d0c685',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'AT_SYM',
        1 => 'expr',
      ),
    ),
  ),
  'opt_ev_status' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptEvStatusChoice_577d70da::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptEvStatusChoice_577d70da::UseEnable_18912667',
      'symbols' =>
      array (
        0 => 'ENABLE_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptEvStatusChoice_577d70da::UseDisableOnSlave_170434e8',
      'symbols' =>
      array (
        0 => 'DISABLE_SYM',
        1 => 'ON_SYM',
        2 => 'SLAVE',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptEvStatusChoice_577d70da::UseDisable_0fb87bd2',
      'symbols' =>
      array (
        0 => 'DISABLE_SYM',
      ),
    ),
  ),
  'ev_starts' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\EvStartsWith_f86d8aee',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\EvStartsWithStartsSymExpr_58ad94a7',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'STARTS_SYM',
        1 => 'expr',
      ),
    ),
  ),
  'ev_ends' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\EvEndsWith_4b91bf0e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\EvEndsWithEndsSymExpr_cfdd496b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ENDS_SYM',
        1 => 'expr',
      ),
    ),
  ),
  'opt_ev_on_completion' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptEvOnCompletionWith_d03a4b88',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ev_on_completion',
      ),
    ),
  ),
  'ev_on_completion' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\EvOnCompletionChoice_87a9a3d9::UseOnCompletionPreserve_ef5091c8',
      'symbols' =>
      array (
        0 => 'ON_SYM',
        1 => 'COMPLETION_SYM',
        2 => 'PRESERVE_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\EvOnCompletionChoice_87a9a3d9::UseOnCompletionNotPreserve_deea4e64',
      'symbols' =>
      array (
        0 => 'ON_SYM',
        1 => 'COMPLETION_SYM',
        2 => 'NOT_SYM',
        3 => 'PRESERVE_SYM',
      ),
    ),
  ),
  'opt_ev_comment' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptEvCommentWith_5786f122',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptEvCommentWithCommentSymTextStringSys_ba4aec74',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'COMMENT_SYM',
        1 => 'TEXT_STRING_sys',
      ),
    ),
  ),
  'ev_sql_stmt' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ev_sql_stmt_inner',
      ),
    ),
  ),
  'ev_sql_stmt_inner' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_proc_stmt_statement',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_proc_stmt_return',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_proc_stmt_if',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'case_stmt_specification',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_labeled_block',
      ),
    ),
    5 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_unlabeled_block',
      ),
    ),
    6 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_labeled_control',
      ),
    ),
    7 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_proc_stmt_unlabeled',
      ),
    ),
    8 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_proc_stmt_leave',
      ),
    ),
    9 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_proc_stmt_iterate',
      ),
    ),
    10 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_proc_stmt_open',
      ),
    ),
    11 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_proc_stmt_fetch',
      ),
    ),
    12 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_proc_stmt_close',
      ),
    ),
  ),
  'sp_name' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpNameWithIdentIdent_4d3ae231',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => '.',
        2 => 'ident',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
  ),
  'sp_a_chistics' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpAChisticsWith_d477a987',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpAChisticsWithSpAChisticsSpChistic_8a3f438b',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'sp_a_chistics',
        1 => 'sp_chistic',
      ),
    ),
  ),
  'sp_c_chistics' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpCChisticsWith_b9f79ec8',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpCChisticsWithSpCChisticsSpCChistic_c3cfb408',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'sp_c_chistics',
        1 => 'sp_c_chistic',
      ),
    ),
  ),
  'sp_chistic' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpChisticWithCommentSymTextStringSys_697c7857',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'COMMENT_SYM',
        1 => 'TEXT_STRING_sys',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpChisticWithLanguageSymSqlSym_1066f659',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'LANGUAGE_SYM',
        1 => 'SQL_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpChisticWithNoSymSqlSym_6882f56e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NO_SYM',
        1 => 'SQL_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpChisticWithContainsSymSqlSym_da021d72',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'CONTAINS_SYM',
        1 => 'SQL_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpChisticWithReadsSymSqlSymDataSym_c9f0d3ea',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'READS_SYM',
        1 => 'SQL_SYM',
        2 => 'DATA_SYM',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpChisticWithModifiesSymSqlSymDataSym_dce6af98',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'MODIFIES_SYM',
        1 => 'SQL_SYM',
        2 => 'DATA_SYM',
      ),
    ),
    6 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_suid',
      ),
    ),
  ),
  'sp_c_chistic' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_chistic',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpCChisticWithDeterministicSym_8b60106e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DETERMINISTIC_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpCChisticWithNotDeterministicSym_a7baa879',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'not',
        1 => 'DETERMINISTIC_SYM',
      ),
    ),
  ),
  'sp_suid' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpSuidChoice_6ca6a5d9::UseSqlSecurityDefiner_57d87183',
      'symbols' =>
      array (
        0 => 'SQL_SYM',
        1 => 'SECURITY_SYM',
        2 => 'DEFINER_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpSuidChoice_6ca6a5d9::UseSqlSecurityInvoker_e5a5d4a3',
      'symbols' =>
      array (
        0 => 'SQL_SYM',
        1 => 'SECURITY_SYM',
        2 => 'INVOKER_SYM',
      ),
    ),
  ),
  'call_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CallStmtWithCallSymSpNameOptParenExprList_b6edf390',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'CALL_SYM',
        1 => 'sp_name',
        2 => 'opt_paren_expr_list',
      ),
    ),
  ),
  'opt_paren_expr_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptParenExprListWith_308ce0be',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptParenExprListWithOptExprList_ee708da1',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'opt_expr_list',
        2 => ')',
      ),
    ),
  ),
  'sp_fdparam_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpFdparamListWith_4ac194c0',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_fdparams',
      ),
    ),
  ),
  'sp_fdparams' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpFdparamsWithSpFdparamsSpFdparam_20e01a48',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'sp_fdparams',
        1 => ',',
        2 => 'sp_fdparam',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_fdparam',
      ),
    ),
  ),
  'sp_fdparam' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpFdparamWithIdentTypeOptCollate_13ec3bc9',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => 'type',
        2 => 'opt_collate',
      ),
    ),
  ),
  'sp_pdparam_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpPdparamListWith_b339418d',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_pdparams',
      ),
    ),
  ),
  'sp_pdparams' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpPdparamsWithSpPdparamsSpPdparam_48069f45',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'sp_pdparams',
        1 => ',',
        2 => 'sp_pdparam',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_pdparam',
      ),
    ),
  ),
  'sp_pdparam' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpPdparamWithSpOptInoutIdentTypeOptCollate_193121f1',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
      ),
      'symbols' =>
      array (
        0 => 'sp_opt_inout',
        1 => 'ident',
        2 => 'type',
        3 => 'opt_collate',
      ),
    ),
  ),
  'sp_opt_inout' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpOptInoutChoice_d88be5b9::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpOptInoutChoice_d88be5b9::UseIn_fed1d872',
      'symbols' =>
      array (
        0 => 'IN_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpOptInoutChoice_d88be5b9::UseOut_c57929ed',
      'symbols' =>
      array (
        0 => 'OUT_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpOptInoutChoice_d88be5b9::UseInout_abf58a7f',
      'symbols' =>
      array (
        0 => 'INOUT_SYM',
      ),
    ),
  ),
  'sp_proc_stmts' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpProcStmtsWith_0927265b',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpProcStmtsWithSpProcStmtsSpProcStmt_60bac25d',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'sp_proc_stmts',
        1 => 'sp_proc_stmt',
        2 => ';',
      ),
    ),
  ),
  'sp_proc_stmts1' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpProcStmts1WithSpProcStmt_71dd5f4a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'sp_proc_stmt',
        1 => ';',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpProcStmts1WithSpProcStmts1SpProcStmt_2a2b59fc',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'sp_proc_stmts1',
        1 => 'sp_proc_stmt',
        2 => ';',
      ),
    ),
  ),
  'sp_decls' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpDeclsWith_cfc000bf',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpDeclsWithSpDeclsSpDecl_640896a1',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'sp_decls',
        1 => 'sp_decl',
        2 => ';',
      ),
    ),
  ),
  'sp_decl' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpDeclWithDeclareSymSpDeclIdentsTypeOptCollateSpOptDefault_cbb5608b',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'DECLARE_SYM',
        1 => 'sp_decl_idents',
        2 => 'type',
        3 => 'opt_collate',
        4 => 'sp_opt_default',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpDeclWithDeclareSymIdentConditionSymForSymSpCond_fcab607e',
      'fields' =>
      array (
        0 => 1,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'DECLARE_SYM',
        1 => 'ident',
        2 => 'CONDITION_SYM',
        3 => 'FOR_SYM',
        4 => 'sp_cond',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpDeclWithDeclareSymSpHandlerTypeHandlerSymForSymSpHcondListSpProcStmt_16f124c2',
      'fields' =>
      array (
        0 => 1,
        1 => 4,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'DECLARE_SYM',
        1 => 'sp_handler_type',
        2 => 'HANDLER_SYM',
        3 => 'FOR_SYM',
        4 => 'sp_hcond_list',
        5 => 'sp_proc_stmt',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpDeclWithDeclareSymIdentCursorSymForSymSelectStmt_e09d6e1a',
      'fields' =>
      array (
        0 => 1,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'DECLARE_SYM',
        1 => 'ident',
        2 => 'CURSOR_SYM',
        3 => 'FOR_SYM',
        4 => 'select_stmt',
      ),
    ),
  ),
  'sp_handler_type' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpHandlerTypeChoice_5cd1c82d::UseExit_3a093158',
      'symbols' =>
      array (
        0 => 'EXIT_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpHandlerTypeChoice_5cd1c82d::UseContinue_628db0c7',
      'symbols' =>
      array (
        0 => 'CONTINUE_SYM',
      ),
    ),
  ),
  'sp_hcond_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_hcond_element',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpHcondListWithSpHcondListSpHcondElement_e7ce5eea',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'sp_hcond_list',
        1 => ',',
        2 => 'sp_hcond_element',
      ),
    ),
  ),
  'sp_hcond_element' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_hcond',
      ),
    ),
  ),
  'sp_cond' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ulong_num',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sqlstate',
      ),
    ),
  ),
  'sqlstate' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SqlstateWithSqlstateSymOptValueTextStringLiteral_c5b1b0d5',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SQLSTATE_SYM',
        1 => 'opt_value',
        2 => 'TEXT_STRING_literal',
      ),
    ),
  ),
  'opt_value' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptValueChoice_07895cda::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptValueChoice_07895cda::UseValue_8ec121c9',
      'symbols' =>
      array (
        0 => 'VALUE_SYM',
      ),
    ),
  ),
  'sp_hcond' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_cond',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpHcondWithSqlwarningSym_b9115250',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SQLWARNING_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpHcondWithNotFoundSym_4287b49a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'not',
        1 => 'FOUND_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpHcondWithSqlexceptionSym_176dcc8b',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SQLEXCEPTION_SYM',
      ),
    ),
  ),
  'signal_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SignalStmtWithSignalSymSignalValueOptSetSignalInformation_bfdd02d4',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SIGNAL_SYM',
        1 => 'signal_value',
        2 => 'opt_set_signal_information',
      ),
    ),
  ),
  'signal_value' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sqlstate',
      ),
    ),
  ),
  'opt_signal_value' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSignalValueWith_5ddbe8aa',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'signal_value',
      ),
    ),
  ),
  'opt_set_signal_information' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSetSignalInformationWith_86a388ab',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSetSignalInformationWithSetSymSignalInformationItemList_62c925e3',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SET_SYM',
        1 => 'signal_information_item_list',
      ),
    ),
  ),
  'signal_information_item_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SignalInformationItemListWithSignalConditionInformationItemNameEqSignalAllowedExpr_972fceaa',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'signal_condition_information_item_name',
        1 => 'EQ',
        2 => 'signal_allowed_expr',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SignalInformationItemListWithSignalInformationItemListSignalConditionInformationItemNameEqSignalAllowedE_f5337e17',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'signal_information_item_list',
        1 => ',',
        2 => 'signal_condition_information_item_name',
        3 => 'EQ',
        4 => 'signal_allowed_expr',
      ),
    ),
  ),
  'signal_allowed_expr' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'literal_or_null',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'rvalue_system_or_user_variable',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'simple_ident',
      ),
    ),
  ),
  'signal_condition_information_item_name' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SignalConditionInformationItemNameChoice_bb755e37::UseClassOrigin_9ff1521b',
      'symbols' =>
      array (
        0 => 'CLASS_ORIGIN_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SignalConditionInformationItemNameChoice_bb755e37::UseSubclassOrigin_286b534f',
      'symbols' =>
      array (
        0 => 'SUBCLASS_ORIGIN_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SignalConditionInformationItemNameChoice_bb755e37::UseConstraintCatalog_28495488',
      'symbols' =>
      array (
        0 => 'CONSTRAINT_CATALOG_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SignalConditionInformationItemNameChoice_bb755e37::UseConstraintSchema_bd00f60e',
      'symbols' =>
      array (
        0 => 'CONSTRAINT_SCHEMA_SYM',
      ),
    ),
    4 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SignalConditionInformationItemNameChoice_bb755e37::UseConstraintName_733be03d',
      'symbols' =>
      array (
        0 => 'CONSTRAINT_NAME_SYM',
      ),
    ),
    5 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SignalConditionInformationItemNameChoice_bb755e37::UseCatalogName_1838e585',
      'symbols' =>
      array (
        0 => 'CATALOG_NAME_SYM',
      ),
    ),
    6 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SignalConditionInformationItemNameChoice_bb755e37::UseSchemaName_05f2bc8a',
      'symbols' =>
      array (
        0 => 'SCHEMA_NAME_SYM',
      ),
    ),
    7 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SignalConditionInformationItemNameChoice_bb755e37::UseTableName_17c5467f',
      'symbols' =>
      array (
        0 => 'TABLE_NAME_SYM',
      ),
    ),
    8 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SignalConditionInformationItemNameChoice_bb755e37::UseColumnName_5e16ba8b',
      'symbols' =>
      array (
        0 => 'COLUMN_NAME_SYM',
      ),
    ),
    9 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SignalConditionInformationItemNameChoice_bb755e37::UseCursorName_d936cc3b',
      'symbols' =>
      array (
        0 => 'CURSOR_NAME_SYM',
      ),
    ),
    10 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SignalConditionInformationItemNameChoice_bb755e37::UseMessageText_3340fdcc',
      'symbols' =>
      array (
        0 => 'MESSAGE_TEXT_SYM',
      ),
    ),
    11 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SignalConditionInformationItemNameChoice_bb755e37::UseMysqlErrno_1d2786ba',
      'symbols' =>
      array (
        0 => 'MYSQL_ERRNO_SYM',
      ),
    ),
  ),
  'resignal_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ResignalStmtWithResignalSymOptSignalValueOptSetSignalInformation_e7b36e0f',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'RESIGNAL_SYM',
        1 => 'opt_signal_value',
        2 => 'opt_set_signal_information',
      ),
    ),
  ),
  'get_diagnostics' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GetDiagnosticsWithGetSymWhichAreaDiagnosticsSymDiagnosticsInformation_146255d5',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'GET_SYM',
        1 => 'which_area',
        2 => 'DIAGNOSTICS_SYM',
        3 => 'diagnostics_information',
      ),
    ),
  ),
  'which_area' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\WhichAreaChoice_331f077d::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\WhichAreaChoice_331f077d::UseCurrent_e3cc57e1',
      'symbols' =>
      array (
        0 => 'CURRENT_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\WhichAreaChoice_331f077d::UseStacked_5e050eb3',
      'symbols' =>
      array (
        0 => 'STACKED_SYM',
      ),
    ),
  ),
  'diagnostics_information' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'statement_information',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DiagnosticsInformationWithConditionSymConditionNumberConditionInformation_7f76f4ff',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'CONDITION_SYM',
        1 => 'condition_number',
        2 => 'condition_information',
      ),
    ),
  ),
  'statement_information' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'statement_information_item',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StatementInformationWithStatementInformationStatementInformationItem_4bbc08fc',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'statement_information',
        1 => ',',
        2 => 'statement_information_item',
      ),
    ),
  ),
  'statement_information_item' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StatementInformationItemWithSimpleTargetSpecificationEqStatementInformationItemName_31c2f05e',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'simple_target_specification',
        1 => 'EQ',
        2 => 'statement_information_item_name',
      ),
    ),
  ),
  'simple_target_specification' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleTargetSpecificationWithIdentOrText_e128c9fa',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '@',
        1 => 'ident_or_text',
      ),
    ),
  ),
  'statement_information_item_name' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\StatementInformationItemNameChoice_7f0cdc04::UseNumber_a081e07b',
      'symbols' =>
      array (
        0 => 'NUMBER_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\StatementInformationItemNameChoice_7f0cdc04::UseRowCount_afb476bf',
      'symbols' =>
      array (
        0 => 'ROW_COUNT_SYM',
      ),
    ),
  ),
  'condition_number' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'signal_allowed_expr',
      ),
    ),
  ),
  'condition_information' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'condition_information_item',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ConditionInformationWithConditionInformationConditionInformationItem_e65e0b2c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'condition_information',
        1 => ',',
        2 => 'condition_information_item',
      ),
    ),
  ),
  'condition_information_item' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ConditionInformationItemWithSimpleTargetSpecificationEqConditionInformationItemName_eaab7442',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'simple_target_specification',
        1 => 'EQ',
        2 => 'condition_information_item_name',
      ),
    ),
  ),
  'condition_information_item_name' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ConditionInformationItemNameChoice_8096dfc6::UseClassOrigin_9ff1521b',
      'symbols' =>
      array (
        0 => 'CLASS_ORIGIN_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ConditionInformationItemNameChoice_8096dfc6::UseSubclassOrigin_286b534f',
      'symbols' =>
      array (
        0 => 'SUBCLASS_ORIGIN_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ConditionInformationItemNameChoice_8096dfc6::UseConstraintCatalog_28495488',
      'symbols' =>
      array (
        0 => 'CONSTRAINT_CATALOG_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ConditionInformationItemNameChoice_8096dfc6::UseConstraintSchema_bd00f60e',
      'symbols' =>
      array (
        0 => 'CONSTRAINT_SCHEMA_SYM',
      ),
    ),
    4 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ConditionInformationItemNameChoice_8096dfc6::UseConstraintName_733be03d',
      'symbols' =>
      array (
        0 => 'CONSTRAINT_NAME_SYM',
      ),
    ),
    5 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ConditionInformationItemNameChoice_8096dfc6::UseCatalogName_1838e585',
      'symbols' =>
      array (
        0 => 'CATALOG_NAME_SYM',
      ),
    ),
    6 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ConditionInformationItemNameChoice_8096dfc6::UseSchemaName_05f2bc8a',
      'symbols' =>
      array (
        0 => 'SCHEMA_NAME_SYM',
      ),
    ),
    7 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ConditionInformationItemNameChoice_8096dfc6::UseTableName_17c5467f',
      'symbols' =>
      array (
        0 => 'TABLE_NAME_SYM',
      ),
    ),
    8 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ConditionInformationItemNameChoice_8096dfc6::UseColumnName_5e16ba8b',
      'symbols' =>
      array (
        0 => 'COLUMN_NAME_SYM',
      ),
    ),
    9 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ConditionInformationItemNameChoice_8096dfc6::UseCursorName_d936cc3b',
      'symbols' =>
      array (
        0 => 'CURSOR_NAME_SYM',
      ),
    ),
    10 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ConditionInformationItemNameChoice_8096dfc6::UseMessageText_3340fdcc',
      'symbols' =>
      array (
        0 => 'MESSAGE_TEXT_SYM',
      ),
    ),
    11 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ConditionInformationItemNameChoice_8096dfc6::UseMysqlErrno_1d2786ba',
      'symbols' =>
      array (
        0 => 'MYSQL_ERRNO_SYM',
      ),
    ),
    12 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ConditionInformationItemNameChoice_8096dfc6::UseReturnedSqlstate_e7982ed7',
      'symbols' =>
      array (
        0 => 'RETURNED_SQLSTATE_SYM',
      ),
    ),
  ),
  'sp_decl_idents' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpDeclIdentsWithSpDeclIdentsIdent_fe0aba6a',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'sp_decl_idents',
        1 => ',',
        2 => 'ident',
      ),
    ),
  ),
  'sp_opt_default' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpOptDefaultWith_a71875b4',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpOptDefaultWithDefaultSymExpr_bfc2817b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT_SYM',
        1 => 'expr',
      ),
    ),
  ),
  'sp_proc_stmt' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_proc_stmt_statement',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_proc_stmt_return',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_proc_stmt_if',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'case_stmt_specification',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_labeled_block',
      ),
    ),
    5 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_unlabeled_block',
      ),
    ),
    6 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_labeled_control',
      ),
    ),
    7 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_proc_stmt_unlabeled',
      ),
    ),
    8 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_proc_stmt_leave',
      ),
    ),
    9 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_proc_stmt_iterate',
      ),
    ),
    10 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_proc_stmt_open',
      ),
    ),
    11 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_proc_stmt_fetch',
      ),
    ),
    12 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_proc_stmt_close',
      ),
    ),
  ),
  'sp_proc_stmt_if' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpProcStmtIfWithIfSpIfEndIf_50db2a3c',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'IF',
        1 => 'sp_if',
        2 => 'END',
        3 => 'IF',
      ),
    ),
  ),
  'sp_proc_stmt_statement' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'simple_statement',
      ),
    ),
  ),
  'sp_proc_stmt_return' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpProcStmtReturnWithReturnSymExpr_aee705d6',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'RETURN_SYM',
        1 => 'expr',
      ),
    ),
  ),
  'sp_proc_stmt_unlabeled' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_unlabeled_control',
      ),
    ),
  ),
  'sp_proc_stmt_leave' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpProcStmtLeaveWithLeaveSymLabelIdent_1f97b4d7',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LEAVE_SYM',
        1 => 'label_ident',
      ),
    ),
  ),
  'sp_proc_stmt_iterate' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpProcStmtIterateWithIterateSymLabelIdent_c913861f',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ITERATE_SYM',
        1 => 'label_ident',
      ),
    ),
  ),
  'sp_proc_stmt_open' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpProcStmtOpenWithOpenSymIdent_44a25d71',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'OPEN_SYM',
        1 => 'ident',
      ),
    ),
  ),
  'sp_proc_stmt_fetch' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpProcStmtFetchWithFetchSymSpOptFetchNoiseIdentIntoSpFetchList_4d8ae5bb',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'FETCH_SYM',
        1 => 'sp_opt_fetch_noise',
        2 => 'ident',
        3 => 'INTO',
        4 => 'sp_fetch_list',
      ),
    ),
  ),
  'sp_proc_stmt_close' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpProcStmtCloseWithCloseSymIdent_19846cc5',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'CLOSE_SYM',
        1 => 'ident',
      ),
    ),
  ),
  'sp_opt_fetch_noise' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpOptFetchNoiseChoice_b9e4d57d::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpOptFetchNoiseChoice_b9e4d57d::UseNextFrom_42eb4e0c',
      'symbols' =>
      array (
        0 => 'NEXT_SYM',
        1 => 'FROM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpOptFetchNoiseChoice_b9e4d57d::UseFrom_f4383c66',
      'symbols' =>
      array (
        0 => 'FROM',
      ),
    ),
  ),
  'sp_fetch_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpFetchListWithSpFetchListIdent_616638e3',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'sp_fetch_list',
        1 => ',',
        2 => 'ident',
      ),
    ),
  ),
  'sp_if' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpIfWithExprThenSymSpProcStmts1SpElseifs_106dd387',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'THEN_SYM',
        2 => 'sp_proc_stmts1',
        3 => 'sp_elseifs',
      ),
    ),
  ),
  'sp_elseifs' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpElseifsWith_ec7b3465',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpElseifsWithElseifSymSpIf_c8735ebb',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ELSEIF_SYM',
        1 => 'sp_if',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpElseifsWithElseSpProcStmts1_6ba73156',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ELSE',
        1 => 'sp_proc_stmts1',
      ),
    ),
  ),
  'case_stmt_specification' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'simple_case_stmt',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'searched_case_stmt',
      ),
    ),
  ),
  'simple_case_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleCaseStmtWithCaseSymExprSimpleWhenClauseListElseClauseOptEndCaseSym_2e7aa147',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'CASE_SYM',
        1 => 'expr',
        2 => 'simple_when_clause_list',
        3 => 'else_clause_opt',
        4 => 'END',
        5 => 'CASE_SYM',
      ),
    ),
  ),
  'searched_case_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SearchedCaseStmtWithCaseSymSearchedWhenClauseListElseClauseOptEndCaseSym_735630f5',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'CASE_SYM',
        1 => 'searched_when_clause_list',
        2 => 'else_clause_opt',
        3 => 'END',
        4 => 'CASE_SYM',
      ),
    ),
  ),
  'simple_when_clause_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'simple_when_clause',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleWhenClauseListWithSimpleWhenClauseListSimpleWhenClause_b19513d9',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'simple_when_clause_list',
        1 => 'simple_when_clause',
      ),
    ),
  ),
  'searched_when_clause_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'searched_when_clause',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SearchedWhenClauseListWithSearchedWhenClauseListSearchedWhenClause_71e2c353',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'searched_when_clause_list',
        1 => 'searched_when_clause',
      ),
    ),
  ),
  'simple_when_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleWhenClauseWithWhenSymExprThenSymSpProcStmts1_9265715a',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'WHEN_SYM',
        1 => 'expr',
        2 => 'THEN_SYM',
        3 => 'sp_proc_stmts1',
      ),
    ),
  ),
  'searched_when_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SearchedWhenClauseWithWhenSymExprThenSymSpProcStmts1_2c2671d1',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'WHEN_SYM',
        1 => 'expr',
        2 => 'THEN_SYM',
        3 => 'sp_proc_stmts1',
      ),
    ),
  ),
  'else_clause_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ElseClauseOptWith_a616b0f6',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ElseClauseOptWithElseSpProcStmts1_2d355716',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ELSE',
        1 => 'sp_proc_stmts1',
      ),
    ),
  ),
  'sp_labeled_control' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpLabeledControlWithLabelIdentSpUnlabeledControlSpOptLabel_fd2eb6ec',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'label_ident',
        1 => ':',
        2 => 'sp_unlabeled_control',
        3 => 'sp_opt_label',
      ),
    ),
  ),
  'sp_opt_label' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpOptLabelWith_f45d6e1c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'label_ident',
      ),
    ),
  ),
  'sp_labeled_block' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpLabeledBlockWithLabelIdentSpBlockContentSpOptLabel_20e5e072',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'label_ident',
        1 => ':',
        2 => 'sp_block_content',
        3 => 'sp_opt_label',
      ),
    ),
  ),
  'sp_unlabeled_block' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_block_content',
      ),
    ),
  ),
  'sp_block_content' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpBlockContentWithBeginSymSpDeclsSpProcStmtsEnd_f10d790b',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'BEGIN_SYM',
        1 => 'sp_decls',
        2 => 'sp_proc_stmts',
        3 => 'END',
      ),
    ),
  ),
  'sp_unlabeled_control' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpUnlabeledControlWithLoopSymSpProcStmts1EndLoopSym_06472eeb',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LOOP_SYM',
        1 => 'sp_proc_stmts1',
        2 => 'END',
        3 => 'LOOP_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpUnlabeledControlWithWhileSymExprDoSymSpProcStmts1EndWhileSym_1acfcead',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'WHILE_SYM',
        1 => 'expr',
        2 => 'DO_SYM',
        3 => 'sp_proc_stmts1',
        4 => 'END',
        5 => 'WHILE_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpUnlabeledControlWithRepeatSymSpProcStmts1UntilSymExprEndRepeatSym_a1565ad2',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'REPEAT_SYM',
        1 => 'sp_proc_stmts1',
        2 => 'UNTIL_SYM',
        3 => 'expr',
        4 => 'END',
        5 => 'REPEAT_SYM',
      ),
    ),
  ),
  'trg_action_time' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TrgActionTimeChoice_b4681e82::UseBefore_c341c7fe',
      'symbols' =>
      array (
        0 => 'BEFORE_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TrgActionTimeChoice_b4681e82::UseAfter_94c3f141',
      'symbols' =>
      array (
        0 => 'AFTER_SYM',
      ),
    ),
  ),
  'trg_event' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TrgEventChoice_c6bd7e6b::UseInsert_014413c2',
      'symbols' =>
      array (
        0 => 'INSERT_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TrgEventChoice_c6bd7e6b::UseUpdate_6cb78ab1',
      'symbols' =>
      array (
        0 => 'UPDATE_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TrgEventChoice_c6bd7e6b::UseDelete_65daeb37',
      'symbols' =>
      array (
        0 => 'DELETE_SYM',
      ),
    ),
  ),
  'opt_ts_datafile_name' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptTsDatafileNameWith_f07dd0b0',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptTsDatafileNameWithAddTsDatafile_1fa8c8fc',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ADD',
        1 => 'ts_datafile',
      ),
    ),
  ),
  'opt_logfile_group_name' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptLogfileGroupNameWith_6e9fdf5a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptLogfileGroupNameWithUseSymLogfileSymGroupSymIdent_74764b48',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'USE_SYM',
        1 => 'LOGFILE_SYM',
        2 => 'GROUP_SYM',
        3 => 'ident',
      ),
    ),
  ),
  'opt_tablespace_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptTablespaceOptionsWith_848a6e2c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'tablespace_option_list',
      ),
    ),
  ),
  'tablespace_option_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'tablespace_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TablespaceOptionListWithTablespaceOptionListOptCommaTablespaceOption_2bcb9697',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'tablespace_option_list',
        1 => 'opt_comma',
        2 => 'tablespace_option',
      ),
    ),
  ),
  'tablespace_option' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_initial_size',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_autoextend_size',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_max_size',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_extent_size',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_nodegroup',
      ),
    ),
    5 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_engine',
      ),
    ),
    6 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_wait',
      ),
    ),
    7 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_comment',
      ),
    ),
    8 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_file_block_size',
      ),
    ),
    9 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_encryption',
      ),
    ),
    10 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_engine_attribute',
      ),
    ),
  ),
  'opt_alter_tablespace_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAlterTablespaceOptionsWith_c8c39871',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_tablespace_option_list',
      ),
    ),
  ),
  'alter_tablespace_option_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_tablespace_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterTablespaceOptionListWithAlterTablespaceOptionListOptCommaAlterTablespaceOption_d88a4091',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'alter_tablespace_option_list',
        1 => 'opt_comma',
        2 => 'alter_tablespace_option',
      ),
    ),
  ),
  'alter_tablespace_option' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_initial_size',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_autoextend_size',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_max_size',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_engine',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_wait',
      ),
    ),
    5 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_encryption',
      ),
    ),
    6 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_engine_attribute',
      ),
    ),
  ),
  'opt_undo_tablespace_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptUndoTablespaceOptionsWith_ec266e3f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'undo_tablespace_option_list',
      ),
    ),
  ),
  'undo_tablespace_option_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'undo_tablespace_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UndoTablespaceOptionListWithUndoTablespaceOptionListOptCommaUndoTablespaceOption_2c6d0b06',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'undo_tablespace_option_list',
        1 => 'opt_comma',
        2 => 'undo_tablespace_option',
      ),
    ),
  ),
  'undo_tablespace_option' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_engine',
      ),
    ),
  ),
  'opt_logfile_group_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptLogfileGroupOptionsWith_abe82683',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'logfile_group_option_list',
      ),
    ),
  ),
  'logfile_group_option_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'logfile_group_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LogfileGroupOptionListWithLogfileGroupOptionListOptCommaLogfileGroupOption_3e331a47',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'logfile_group_option_list',
        1 => 'opt_comma',
        2 => 'logfile_group_option',
      ),
    ),
  ),
  'logfile_group_option' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_initial_size',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_undo_buffer_size',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_redo_buffer_size',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_nodegroup',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_engine',
      ),
    ),
    5 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_wait',
      ),
    ),
    6 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_comment',
      ),
    ),
  ),
  'opt_alter_logfile_group_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAlterLogfileGroupOptionsWith_6d0adb8e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_logfile_group_option_list',
      ),
    ),
  ),
  'alter_logfile_group_option_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_logfile_group_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterLogfileGroupOptionListWithAlterLogfileGroupOptionListOptCommaAlterLogfileGroupOption_bb081ac1',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'alter_logfile_group_option_list',
        1 => 'opt_comma',
        2 => 'alter_logfile_group_option',
      ),
    ),
  ),
  'alter_logfile_group_option' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_initial_size',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_engine',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_wait',
      ),
    ),
  ),
  'ts_datafile' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TsDatafileWithDatafileSymTextStringSys_d9eaa72e',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DATAFILE_SYM',
        1 => 'TEXT_STRING_sys',
      ),
    ),
  ),
  'undo_tablespace_state' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\UndoTablespaceStateChoice_23d85132::UseActive_630c2f1c',
      'symbols' =>
      array (
        0 => 'ACTIVE_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\UndoTablespaceStateChoice_23d85132::UseInactive_df343bd4',
      'symbols' =>
      array (
        0 => 'INACTIVE_SYM',
      ),
    ),
  ),
  'lg_undofile' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LgUndofileWithUndofileSymTextStringSys_54c963ef',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'UNDOFILE_SYM',
        1 => 'TEXT_STRING_sys',
      ),
    ),
  ),
  'ts_option_initial_size' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TsOptionInitialSizeWithInitialSizeSymOptEqualSizeNumber_94fdbcbe',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'INITIAL_SIZE_SYM',
        1 => 'opt_equal',
        2 => 'size_number',
      ),
    ),
  ),
  'ts_option_autoextend_size' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'option_autoextend_size',
      ),
    ),
  ),
  'option_autoextend_size' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptionAutoextendSizeWithAutoextendSizeSymOptEqualSizeNumber_9421c387',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'AUTOEXTEND_SIZE_SYM',
        1 => 'opt_equal',
        2 => 'size_number',
      ),
    ),
  ),
  'ts_option_max_size' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TsOptionMaxSizeWithMaxSizeSymOptEqualSizeNumber_f8a7c43c',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MAX_SIZE_SYM',
        1 => 'opt_equal',
        2 => 'size_number',
      ),
    ),
  ),
  'ts_option_extent_size' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TsOptionExtentSizeWithExtentSizeSymOptEqualSizeNumber_f729c56a',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'EXTENT_SIZE_SYM',
        1 => 'opt_equal',
        2 => 'size_number',
      ),
    ),
  ),
  'ts_option_undo_buffer_size' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TsOptionUndoBufferSizeWithUndoBufferSizeSymOptEqualSizeNumber_46162f45',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'UNDO_BUFFER_SIZE_SYM',
        1 => 'opt_equal',
        2 => 'size_number',
      ),
    ),
  ),
  'ts_option_redo_buffer_size' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TsOptionRedoBufferSizeWithRedoBufferSizeSymOptEqualSizeNumber_d7384860',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'REDO_BUFFER_SIZE_SYM',
        1 => 'opt_equal',
        2 => 'size_number',
      ),
    ),
  ),
  'ts_option_nodegroup' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TsOptionNodegroupWithNodegroupSymOptEqualRealUlongNum_971e2caa',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'NODEGROUP_SYM',
        1 => 'opt_equal',
        2 => 'real_ulong_num',
      ),
    ),
  ),
  'ts_option_comment' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TsOptionCommentWithCommentSymOptEqualTextStringSys_24887817',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'COMMENT_SYM',
        1 => 'opt_equal',
        2 => 'TEXT_STRING_sys',
      ),
    ),
  ),
  'ts_option_engine' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TsOptionEngineWithOptStorageEngineSymOptEqualIdentOrText_84d1f87d',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'opt_storage',
        1 => 'ENGINE_SYM',
        2 => 'opt_equal',
        3 => 'ident_or_text',
      ),
    ),
  ),
  'ts_option_file_block_size' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TsOptionFileBlockSizeWithFileBlockSizeSymOptEqualSizeNumber_611fc5e3',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'FILE_BLOCK_SIZE_SYM',
        1 => 'opt_equal',
        2 => 'size_number',
      ),
    ),
  ),
  'ts_option_wait' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TsOptionWaitChoice_06a79d39::UseWait_4f618185',
      'symbols' =>
      array (
        0 => 'WAIT_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TsOptionWaitChoice_06a79d39::UseNoWait_c19ba2da',
      'symbols' =>
      array (
        0 => 'NO_WAIT_SYM',
      ),
    ),
  ),
  'ts_option_encryption' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TsOptionEncryptionWithEncryptionSymOptEqualTextStringSys_d45e7acd',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ENCRYPTION_SYM',
        1 => 'opt_equal',
        2 => 'TEXT_STRING_sys',
      ),
    ),
  ),
  'ts_option_engine_attribute' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TsOptionEngineAttributeWithEngineAttributeSymOptEqualJsonAttribute_4d7b7e63',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ENGINE_ATTRIBUTE_SYM',
        1 => 'opt_equal',
        2 => 'json_attribute',
      ),
    ),
  ),
  'size_number' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'real_ulonglong_num',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'IDENT_sys',
      ),
    ),
  ),
  'opt_create_table_options_etc' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptCreateTableOptionsEtcWithCreateTableOptionsOptCreatePartitioningEtc_3c795411',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'create_table_options',
        1 => 'opt_create_partitioning_etc',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_create_partitioning_etc',
      ),
    ),
  ),
  'opt_create_partitioning_etc' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptCreatePartitioningEtcWithPartitionClauseOptDuplicateAsQe_58c41092',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'partition_clause',
        1 => 'opt_duplicate_as_qe',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_duplicate_as_qe',
      ),
    ),
  ),
  'opt_duplicate_as_qe' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptDuplicateAsQeWith_8befdf4f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptDuplicateAsQeWithDuplicateAsCreateQueryExpression_396a67d9',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'duplicate',
        1 => 'as_create_query_expression',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'as_create_query_expression',
      ),
    ),
  ),
  'as_create_query_expression' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AsCreateQueryExpressionWithAsQueryExpressionWithOptLockingClauses_d1733b77',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'AS',
        1 => 'query_expression_with_opt_locking_clauses',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'query_expression_with_opt_locking_clauses',
      ),
    ),
  ),
  'partition_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartitionClauseWithPartitionSymByPartTypeDefOptNumPartsOptSubPartOptPartDefs_0d423316',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'PARTITION_SYM',
        1 => 'BY',
        2 => 'part_type_def',
        3 => 'opt_num_parts',
        4 => 'opt_sub_part',
        5 => 'opt_part_defs',
      ),
    ),
  ),
  'part_type_def' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartTypeDefWithOptLinearKeySymOptKeyAlgoOptNameList_4d485aec',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'opt_linear',
        1 => 'KEY_SYM',
        2 => 'opt_key_algo',
        3 => '(',
        4 => 'opt_name_list',
        5 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartTypeDefWithOptLinearHashSymBitExpr_aa5a4096',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'opt_linear',
        1 => 'HASH_SYM',
        2 => '(',
        3 => 'bit_expr',
        4 => ')',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartTypeDefWithRangeSymBitExpr_a4c0abba',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'RANGE_SYM',
        1 => '(',
        2 => 'bit_expr',
        3 => ')',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartTypeDefWithRangeSymColumnsNameList_15280a45',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'RANGE_SYM',
        1 => 'COLUMNS',
        2 => '(',
        3 => 'name_list',
        4 => ')',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartTypeDefWithListSymBitExpr_7213b1a7',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'LIST_SYM',
        1 => '(',
        2 => 'bit_expr',
        3 => ')',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartTypeDefWithListSymColumnsNameList_3a719ffb',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'LIST_SYM',
        1 => 'COLUMNS',
        2 => '(',
        3 => 'name_list',
        4 => ')',
      ),
    ),
  ),
  'opt_linear' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptLinearChoice_7401897f::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptLinearChoice_7401897f::UseLinear_36ad86d8',
      'symbols' =>
      array (
        0 => 'LINEAR_SYM',
      ),
    ),
  ),
  'opt_key_algo' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptKeyAlgoWith_415c0172',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptKeyAlgoWithAlgorithmSymEqRealUlongNum_faa1bb0f',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ALGORITHM_SYM',
        1 => 'EQ',
        2 => 'real_ulong_num',
      ),
    ),
  ),
  'opt_num_parts' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptNumPartsWith_573f93e7',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptNumPartsWithPartitionsSymRealUlongNum_c08cd852',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'PARTITIONS_SYM',
        1 => 'real_ulong_num',
      ),
    ),
  ),
  'opt_sub_part' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSubPartWith_8001a404',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSubPartWithSubpartitionSymByOptLinearHashSymBitExprOptNumSubparts_bc80bd06',
      'fields' =>
      array (
        0 => 2,
        1 => 5,
        2 => 7,
      ),
      'symbols' =>
      array (
        0 => 'SUBPARTITION_SYM',
        1 => 'BY',
        2 => 'opt_linear',
        3 => 'HASH_SYM',
        4 => '(',
        5 => 'bit_expr',
        6 => ')',
        7 => 'opt_num_subparts',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSubPartWithSubpartitionSymByOptLinearKeySymOptKeyAlgoNameListOptNumSubparts_c9746b5c',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 6,
        3 => 8,
      ),
      'symbols' =>
      array (
        0 => 'SUBPARTITION_SYM',
        1 => 'BY',
        2 => 'opt_linear',
        3 => 'KEY_SYM',
        4 => 'opt_key_algo',
        5 => '(',
        6 => 'name_list',
        7 => ')',
        8 => 'opt_num_subparts',
      ),
    ),
  ),
  'opt_name_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptNameListWith_c932aafd',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'name_list',
      ),
    ),
  ),
  'name_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NameListWithNameListIdent_32e74685',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'name_list',
        1 => ',',
        2 => 'ident',
      ),
    ),
  ),
  'opt_num_subparts' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptNumSubpartsWith_875a51f8',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptNumSubpartsWithSubpartitionsSymRealUlongNum_c1854729',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SUBPARTITIONS_SYM',
        1 => 'real_ulong_num',
      ),
    ),
  ),
  'opt_part_defs' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPartDefsWith_015b49ac',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPartDefsWithPartDefList_1876d384',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'part_def_list',
        2 => ')',
      ),
    ),
  ),
  'part_def_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'part_definition',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartDefListWithPartDefListPartDefinition_8715b6fe',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'part_def_list',
        1 => ',',
        2 => 'part_definition',
      ),
    ),
  ),
  'part_definition' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartDefinitionWithPartitionSymIdentOptPartValuesOptPartOptionsOptSubPartition_3163ca6c',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'PARTITION_SYM',
        1 => 'ident',
        2 => 'opt_part_values',
        3 => 'opt_part_options',
        4 => 'opt_sub_partition',
      ),
    ),
  ),
  'opt_part_values' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPartValuesWith_7b1f8246',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPartValuesWithValuesLessSymThanSymPartFuncMax_dbb3fea7',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'VALUES',
        1 => 'LESS_SYM',
        2 => 'THAN_SYM',
        3 => 'part_func_max',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPartValuesWithValuesInSymPartValuesIn_b68c1edb',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'VALUES',
        1 => 'IN_SYM',
        2 => 'part_values_in',
      ),
    ),
  ),
  'part_func_max' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartFuncMaxWithMaxValueSym_df2108bd',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'MAX_VALUE_SYM',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'part_value_item_list_paren',
      ),
    ),
  ),
  'part_values_in' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'part_value_item_list_paren',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartValuesInWithPartValueList_27328ce6',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'part_value_list',
        2 => ')',
      ),
    ),
  ),
  'part_value_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'part_value_item_list_paren',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartValueListWithPartValueListPartValueItemListParen_f423d110',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'part_value_list',
        1 => ',',
        2 => 'part_value_item_list_paren',
      ),
    ),
  ),
  'part_value_item_list_paren' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartValueItemListParenWithPartValueItemList_ac40b6eb',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'part_value_item_list',
        2 => ')',
      ),
    ),
  ),
  'part_value_item_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'part_value_item',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartValueItemListWithPartValueItemListPartValueItem_e8b99965',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'part_value_item_list',
        1 => ',',
        2 => 'part_value_item',
      ),
    ),
  ),
  'part_value_item' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartValueItemWithMaxValueSym_bbc2a98e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'MAX_VALUE_SYM',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'bit_expr',
      ),
    ),
  ),
  'opt_sub_partition' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSubPartitionWith_b07814c4',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSubPartitionWithSubPartList_cabd577c',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'sub_part_list',
        2 => ')',
      ),
    ),
  ),
  'sub_part_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sub_part_definition',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SubPartListWithSubPartListSubPartDefinition_ade00b2c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'sub_part_list',
        1 => ',',
        2 => 'sub_part_definition',
      ),
    ),
  ),
  'sub_part_definition' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SubPartDefinitionWithSubpartitionSymIdentOrTextOptPartOptions_11168737',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SUBPARTITION_SYM',
        1 => 'ident_or_text',
        2 => 'opt_part_options',
      ),
    ),
  ),
  'opt_part_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPartOptionsWith_9b45b3d8',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'part_option_list',
      ),
    ),
  ),
  'part_option_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartOptionListWithPartOptionListPartOption_1a58106d',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'part_option_list',
        1 => 'part_option',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'part_option',
      ),
    ),
  ),
  'part_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartOptionWithTablespaceSymOptEqualIdent_33febdf7',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'TABLESPACE_SYM',
        1 => 'opt_equal',
        2 => 'ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartOptionWithOptStorageEngineSymOptEqualIdentOrText_6c2f88e4',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'opt_storage',
        1 => 'ENGINE_SYM',
        2 => 'opt_equal',
        3 => 'ident_or_text',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartOptionWithNodegroupSymOptEqualRealUlongNum_4efb9c1a',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'NODEGROUP_SYM',
        1 => 'opt_equal',
        2 => 'real_ulong_num',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartOptionWithMaxRowsOptEqualRealUlonglongNum_945ea6d8',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MAX_ROWS',
        1 => 'opt_equal',
        2 => 'real_ulonglong_num',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartOptionWithMinRowsOptEqualRealUlonglongNum_77d3abd8',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MIN_ROWS',
        1 => 'opt_equal',
        2 => 'real_ulonglong_num',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartOptionWithDataSymDirectorySymOptEqualTextStringSys_fbdd3b34',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DATA_SYM',
        1 => 'DIRECTORY_SYM',
        2 => 'opt_equal',
        3 => 'TEXT_STRING_sys',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartOptionWithIndexSymDirectorySymOptEqualTextStringSys_974f3166',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'INDEX_SYM',
        1 => 'DIRECTORY_SYM',
        2 => 'opt_equal',
        3 => 'TEXT_STRING_sys',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartOptionWithCommentSymOptEqualTextStringSys_f5ac470c',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'COMMENT_SYM',
        1 => 'opt_equal',
        2 => 'TEXT_STRING_sys',
      ),
    ),
  ),
  'alter_database_options' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_database_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterDatabaseOptionsWithAlterDatabaseOptionsAlterDatabaseOption_61042e83',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'alter_database_options',
        1 => 'alter_database_option',
      ),
    ),
  ),
  'alter_database_option' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'create_database_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterDatabaseOptionWithReadSymOnlySymOptEqualTernaryOption_73040304',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'READ_SYM',
        1 => 'ONLY_SYM',
        2 => 'opt_equal',
        3 => 'ternary_option',
      ),
    ),
  ),
  'opt_create_database_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptCreateDatabaseOptionsWith_e5a1b67a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'create_database_options',
      ),
    ),
  ),
  'create_database_options' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'create_database_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateDatabaseOptionsWithCreateDatabaseOptionsCreateDatabaseOption_d4673b72',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'create_database_options',
        1 => 'create_database_option',
      ),
    ),
  ),
  'create_database_option' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'default_collation',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'default_charset',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'default_encryption',
      ),
    ),
  ),
  'opt_if_not_exists' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIfNotExistsWith_a0a3a58a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIfNotExistsWithIfNotExists_4be9685c',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'IF',
        1 => 'not',
        2 => 'EXISTS',
      ),
    ),
  ),
  'create_table_options_space_separated' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'create_table_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionsSpaceSeparatedWithCreateTableOptionsSpaceSeparatedCreateTableOption_3d54e10a',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'create_table_options_space_separated',
        1 => 'create_table_option',
      ),
    ),
  ),
  'create_table_options' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'create_table_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionsWithCreateTableOptionsOptCommaCreateTableOption_1a46c03c',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'create_table_options',
        1 => 'opt_comma',
        2 => 'create_table_option',
      ),
    ),
  ),
  'opt_comma' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptCommaChoice_07863778::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptCommaChoice_07863778::Use_d03502c4',
      'symbols' =>
      array (
        0 => ',',
      ),
    ),
  ),
  'create_table_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithEngineSymOptEqualIdentOrText_2c48feae',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ENGINE_SYM',
        1 => 'opt_equal',
        2 => 'ident_or_text',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithSecondaryEngineSymOptEqualNullSym_a1258e05',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SECONDARY_ENGINE_SYM',
        1 => 'opt_equal',
        2 => 'NULL_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithSecondaryEngineSymOptEqualIdentOrText_aaf93a05',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SECONDARY_ENGINE_SYM',
        1 => 'opt_equal',
        2 => 'ident_or_text',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithMaxRowsOptEqualUlonglongNum_7abefb05',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MAX_ROWS',
        1 => 'opt_equal',
        2 => 'ulonglong_num',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithMinRowsOptEqualUlonglongNum_ff8335e9',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MIN_ROWS',
        1 => 'opt_equal',
        2 => 'ulonglong_num',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithAvgRowLengthOptEqualUlonglongNum_653679f2',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'AVG_ROW_LENGTH',
        1 => 'opt_equal',
        2 => 'ulonglong_num',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithPasswordOptEqualTextStringSys_cbb30b0a',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'opt_equal',
        2 => 'TEXT_STRING_sys',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithCommentSymOptEqualTextStringSys_dcdc882d',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'COMMENT_SYM',
        1 => 'opt_equal',
        2 => 'TEXT_STRING_sys',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithCompressionSymOptEqualTextStringSys_eded7540',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'COMPRESSION_SYM',
        1 => 'opt_equal',
        2 => 'TEXT_STRING_sys',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithEncryptionSymOptEqualTextStringSys_7a1cf93f',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ENCRYPTION_SYM',
        1 => 'opt_equal',
        2 => 'TEXT_STRING_sys',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithAutoIncOptEqualUlonglongNum_c6cb7212',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'AUTO_INC',
        1 => 'opt_equal',
        2 => 'ulonglong_num',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithPackKeysSymOptEqualTernaryOption_c1b5a94e',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'PACK_KEYS_SYM',
        1 => 'opt_equal',
        2 => 'ternary_option',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithStatsAutoRecalcSymOptEqualTernaryOption_9c921c88',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'STATS_AUTO_RECALC_SYM',
        1 => 'opt_equal',
        2 => 'ternary_option',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithStatsPersistentSymOptEqualTernaryOption_80a89a69',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'STATS_PERSISTENT_SYM',
        1 => 'opt_equal',
        2 => 'ternary_option',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithStatsSamplePagesSymOptEqualUlongNum_5441b4de',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'STATS_SAMPLE_PAGES_SYM',
        1 => 'opt_equal',
        2 => 'ulong_num',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithStatsSamplePagesSymOptEqualDefaultSym_2b631e87',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'STATS_SAMPLE_PAGES_SYM',
        1 => 'opt_equal',
        2 => 'DEFAULT_SYM',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithChecksumSymOptEqualUlongNum_0a98c695',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'CHECKSUM_SYM',
        1 => 'opt_equal',
        2 => 'ulong_num',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithTableChecksumSymOptEqualUlongNum_6c2824bd',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'TABLE_CHECKSUM_SYM',
        1 => 'opt_equal',
        2 => 'ulong_num',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithDelayKeyWriteSymOptEqualUlongNum_bdbc6076',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DELAY_KEY_WRITE_SYM',
        1 => 'opt_equal',
        2 => 'ulong_num',
      ),
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithRowFormatSymOptEqualRowTypes_b15361cf',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ROW_FORMAT_SYM',
        1 => 'opt_equal',
        2 => 'row_types',
      ),
    ),
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithUnionSymOptEqualOptTableList_0d047314',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'UNION_SYM',
        1 => 'opt_equal',
        2 => '(',
        3 => 'opt_table_list',
        4 => ')',
      ),
    ),
    21 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'default_charset',
      ),
    ),
    22 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'default_collation',
      ),
    ),
    23 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithInsertMethodOptEqualMergeInsertTypes_19515d94',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'INSERT_METHOD',
        1 => 'opt_equal',
        2 => 'merge_insert_types',
      ),
    ),
    24 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithDataSymDirectorySymOptEqualTextStringSys_e34cf103',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DATA_SYM',
        1 => 'DIRECTORY_SYM',
        2 => 'opt_equal',
        3 => 'TEXT_STRING_sys',
      ),
    ),
    25 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithIndexSymDirectorySymOptEqualTextStringSys_e0bcd389',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'INDEX_SYM',
        1 => 'DIRECTORY_SYM',
        2 => 'opt_equal',
        3 => 'TEXT_STRING_sys',
      ),
    ),
    26 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithTablespaceSymOptEqualIdent_21426f82',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'TABLESPACE_SYM',
        1 => 'opt_equal',
        2 => 'ident',
      ),
    ),
    27 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithStorageSymDiskSym_e8595655',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'STORAGE_SYM',
        1 => 'DISK_SYM',
      ),
    ),
    28 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithStorageSymMemorySym_8a1b3bca',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'STORAGE_SYM',
        1 => 'MEMORY_SYM',
      ),
    ),
    29 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithConnectionSymOptEqualTextStringSys_0ba6fe9d',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'CONNECTION_SYM',
        1 => 'opt_equal',
        2 => 'TEXT_STRING_sys',
      ),
    ),
    30 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithKeyBlockSizeOptEqualUlonglongNum_040d98d8',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'KEY_BLOCK_SIZE',
        1 => 'opt_equal',
        2 => 'ulonglong_num',
      ),
    ),
    31 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithStartSymTransactionSym_ebb5f5ec',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'START_SYM',
        1 => 'TRANSACTION_SYM',
      ),
    ),
    32 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithEngineAttributeSymOptEqualJsonAttribute_058118a6',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ENGINE_ATTRIBUTE_SYM',
        1 => 'opt_equal',
        2 => 'json_attribute',
      ),
    ),
    33 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithSecondaryEngineAttributeSymOptEqualJsonAttribute_c8e5604d',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SECONDARY_ENGINE_ATTRIBUTE_SYM',
        1 => 'opt_equal',
        2 => 'json_attribute',
      ),
    ),
    34 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'option_autoextend_size',
      ),
    ),
  ),
  'ternary_option' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ulong_num',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TernaryOptionWithDefaultSym_f2ec1f92',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT_SYM',
      ),
    ),
  ),
  'default_charset' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DefaultCharsetWithOptDefaultCharacterSetOptEqualCharsetName_3e7b5baf',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
      ),
      'symbols' =>
      array (
        0 => 'opt_default',
        1 => 'character_set',
        2 => 'opt_equal',
        3 => 'charset_name',
      ),
    ),
  ),
  'default_collation' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DefaultCollationWithOptDefaultCollateSymOptEqualCollationName_24e9f6a2',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'opt_default',
        1 => 'COLLATE_SYM',
        2 => 'opt_equal',
        3 => 'collation_name',
      ),
    ),
  ),
  'default_encryption' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DefaultEncryptionWithOptDefaultEncryptionSymOptEqualTextStringSys_09b0ed73',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'opt_default',
        1 => 'ENCRYPTION_SYM',
        2 => 'opt_equal',
        3 => 'TEXT_STRING_sys',
      ),
    ),
  ),
  'row_types' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\RowTypesChoice_e208fbd9::UseDefault_89dbf710',
      'symbols' =>
      array (
        0 => 'DEFAULT_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\RowTypesChoice_e208fbd9::UseFixed_f28b6901',
      'symbols' =>
      array (
        0 => 'FIXED_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\RowTypesChoice_e208fbd9::UseDynamic_ef1070bb',
      'symbols' =>
      array (
        0 => 'DYNAMIC_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\RowTypesChoice_e208fbd9::UseCompressed_dbc63f89',
      'symbols' =>
      array (
        0 => 'COMPRESSED_SYM',
      ),
    ),
    4 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\RowTypesChoice_e208fbd9::UseRedundant_a5f96bdf',
      'symbols' =>
      array (
        0 => 'REDUNDANT_SYM',
      ),
    ),
    5 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\RowTypesChoice_e208fbd9::UseCompact_0bff0c2b',
      'symbols' =>
      array (
        0 => 'COMPACT_SYM',
      ),
    ),
  ),
  'merge_insert_types' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\MergeInsertTypesChoice_2475624f::UseNo_23794d91',
      'symbols' =>
      array (
        0 => 'NO_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\MergeInsertTypesChoice_2475624f::UseFirst_267d3b81',
      'symbols' =>
      array (
        0 => 'FIRST_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\MergeInsertTypesChoice_2475624f::UseLast_7e86aeec',
      'symbols' =>
      array (
        0 => 'LAST_SYM',
      ),
    ),
  ),
  'udf_type' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UdfTypeWithStringSym_c17d0cf0',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'STRING_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UdfTypeWithRealSym_64930085',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'REAL_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UdfTypeWithDecimalSym_0a7cc2f2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DECIMAL_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UdfTypeWithIntSym_974aaa37',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'INT_SYM',
      ),
    ),
  ),
  'table_element_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_element',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableElementListWithTableElementListTableElement_229f8430',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_element_list',
        1 => ',',
        2 => 'table_element',
      ),
    ),
  ),
  'table_element' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'column_def',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_constraint_def',
      ),
    ),
  ),
  'column_def' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnDefWithIdentFieldDefOptReferences_fecb2498',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => 'field_def',
        2 => 'opt_references',
      ),
    ),
  ),
  'opt_references' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptReferencesWith_695e565f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'references',
      ),
    ),
  ),
  'table_constraint_def' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableConstraintDefWithKeyOrIndexOptIndexNameAndTypeKeyListWithExpressionOptIndexOptions_c844f9a7',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'key_or_index',
        1 => 'opt_index_name_and_type',
        2 => '(',
        3 => 'key_list_with_expression',
        4 => ')',
        5 => 'opt_index_options',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableConstraintDefWithFulltextSymOptKeyOrIndexOptIdentKeyListWithExpressionOptFulltextIndexOption_1f6b4201',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 4,
        3 => 6,
      ),
      'symbols' =>
      array (
        0 => 'FULLTEXT_SYM',
        1 => 'opt_key_or_index',
        2 => 'opt_ident',
        3 => '(',
        4 => 'key_list_with_expression',
        5 => ')',
        6 => 'opt_fulltext_index_options',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableConstraintDefWithSpatialSymOptKeyOrIndexOptIdentKeyListWithExpressionOptSpatialIndexOptions_39f3760e',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 4,
        3 => 6,
      ),
      'symbols' =>
      array (
        0 => 'SPATIAL_SYM',
        1 => 'opt_key_or_index',
        2 => 'opt_ident',
        3 => '(',
        4 => 'key_list_with_expression',
        5 => ')',
        6 => 'opt_spatial_index_options',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableConstraintDefWithOptConstraintNameConstraintKeyTypeOptIndexNameAndTypeKeyListWithExpressionO_1a05d953',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 4,
        4 => 6,
      ),
      'symbols' =>
      array (
        0 => 'opt_constraint_name',
        1 => 'constraint_key_type',
        2 => 'opt_index_name_and_type',
        3 => '(',
        4 => 'key_list_with_expression',
        5 => ')',
        6 => 'opt_index_options',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableConstraintDefWithOptConstraintNameForeignKeySymOptIdentKeyListReferences_3c498ccd',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
        2 => 5,
        3 => 7,
      ),
      'symbols' =>
      array (
        0 => 'opt_constraint_name',
        1 => 'FOREIGN',
        2 => 'KEY_SYM',
        3 => 'opt_ident',
        4 => '(',
        5 => 'key_list',
        6 => ')',
        7 => 'references',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableConstraintDefWithOptConstraintNameCheckConstraintOptConstraintEnforcement_21fd9e18',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'opt_constraint_name',
        1 => 'check_constraint',
        2 => 'opt_constraint_enforcement',
      ),
    ),
  ),
  'check_constraint' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CheckConstraintWithCheckSymExpr_2f6e0a86',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'CHECK_SYM',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
  ),
  'opt_constraint_name' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptConstraintNameWith_5eb772f7',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptConstraintNameWithConstraintOptIdent_7a9f6bf0',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'CONSTRAINT',
        1 => 'opt_ident',
      ),
    ),
  ),
  'opt_not' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptNotChoice_6a769467::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptNotChoice_6a769467::UseNot_8be3365c',
      'symbols' =>
      array (
        0 => 'NOT_SYM',
      ),
    ),
  ),
  'opt_constraint_enforcement' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptConstraintEnforcementWith_32156886',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'constraint_enforcement',
      ),
    ),
  ),
  'constraint_enforcement' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ConstraintEnforcementWithOptNotEnforcedSym_238b2fa2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'opt_not',
        1 => 'ENFORCED_SYM',
      ),
    ),
  ),
  'field_def' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldDefWithTypeOptColumnAttributeList_42b23b30',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'type',
        1 => 'opt_column_attribute_list',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldDefWithTypeOptCollateOptGeneratedAlwaysAsExprOptStoredAttributeOptColumnAttributeL_6d80fb0f',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 5,
        4 => 7,
        5 => 8,
      ),
      'symbols' =>
      array (
        0 => 'type',
        1 => 'opt_collate',
        2 => 'opt_generated_always',
        3 => 'AS',
        4 => '(',
        5 => 'expr',
        6 => ')',
        7 => 'opt_stored_attribute',
        8 => 'opt_column_attribute_list',
      ),
    ),
  ),
  'opt_generated_always' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptGeneratedAlwaysChoice_c41a9e3f::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptGeneratedAlwaysChoice_c41a9e3f::UseGeneratedAlways_75b9aa2e',
      'symbols' =>
      array (
        0 => 'GENERATED',
        1 => 'ALWAYS_SYM',
      ),
    ),
  ),
  'opt_stored_attribute' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptStoredAttributeChoice_5c2f8f16::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptStoredAttributeChoice_5c2f8f16::UseVirtual_bf02c8c1',
      'symbols' =>
      array (
        0 => 'VIRTUAL_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptStoredAttributeChoice_5c2f8f16::UseStored_1afc73e0',
      'symbols' =>
      array (
        0 => 'STORED_SYM',
      ),
    ),
  ),
  'type' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithIntTypeOptFieldLengthFieldOptions_94ef4a3a',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'int_type',
        1 => 'opt_field_length',
        2 => 'field_options',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithRealTypeOptPrecisionFieldOptions_53962f7c',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'real_type',
        1 => 'opt_precision',
        2 => 'field_options',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithNumericTypeFloatOptionsFieldOptions_504c7d9c',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'numeric_type',
        1 => 'float_options',
        2 => 'field_options',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithBitSym_33c3a5aa',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'BIT_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithBitSymFieldLength_a425befb',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'BIT_SYM',
        1 => 'field_length',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithBoolSym_915d16bf',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'BOOL_SYM',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithBooleanSym_2ec1cded',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'BOOLEAN_SYM',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithCharSymFieldLengthOptCharsetWithOptBinary_be92677f',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'CHAR_SYM',
        1 => 'field_length',
        2 => 'opt_charset_with_opt_binary',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithCharSymOptCharsetWithOptBinary_5b2e9d22',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'CHAR_SYM',
        1 => 'opt_charset_with_opt_binary',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithNcharFieldLengthOptBinMod_11d71e6c',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'nchar',
        1 => 'field_length',
        2 => 'opt_bin_mod',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithNcharOptBinMod_0f351aa4',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'nchar',
        1 => 'opt_bin_mod',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithBinarySymFieldLength_07a0e373',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'BINARY_SYM',
        1 => 'field_length',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithBinarySym_85f672e9',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'BINARY_SYM',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithVarcharFieldLengthOptCharsetWithOptBinary_e26c3c11',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'varchar',
        1 => 'field_length',
        2 => 'opt_charset_with_opt_binary',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithNvarcharFieldLengthOptBinMod_a89a2a2d',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'nvarchar',
        1 => 'field_length',
        2 => 'opt_bin_mod',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithVarbinarySymFieldLength_c5cb4b4a',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'VARBINARY_SYM',
        1 => 'field_length',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithYearSymOptFieldLengthFieldOptions_4adcd603',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'YEAR_SYM',
        1 => 'opt_field_length',
        2 => 'field_options',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithDateSym_66fcc849',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DATE_SYM',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithTimeSymTypeDatetimePrecision_c7576f0b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'TIME_SYM',
        1 => 'type_datetime_precision',
      ),
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithTimestampSymTypeDatetimePrecision_34966c1a',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'TIMESTAMP_SYM',
        1 => 'type_datetime_precision',
      ),
    ),
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithDatetimeSymTypeDatetimePrecision_071c5079',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DATETIME_SYM',
        1 => 'type_datetime_precision',
      ),
    ),
    21 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithTinyblobSym_1b37fc83',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'TINYBLOB_SYM',
      ),
    ),
    22 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithBlobSymOptFieldLength_f1fcf668',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'BLOB_SYM',
        1 => 'opt_field_length',
      ),
    ),
    23 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'spatial_type',
      ),
    ),
    24 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithMediumblobSym_9a3a320e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'MEDIUMBLOB_SYM',
      ),
    ),
    25 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithLongblobSym_724bc7a1',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'LONGBLOB_SYM',
      ),
    ),
    26 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithLongSymVarbinarySym_096d967a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'LONG_SYM',
        1 => 'VARBINARY_SYM',
      ),
    ),
    27 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithLongSymVarcharOptCharsetWithOptBinary_3761b0b9',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'LONG_SYM',
        1 => 'varchar',
        2 => 'opt_charset_with_opt_binary',
      ),
    ),
    28 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithTinytextSynOptCharsetWithOptBinary_3000dfa3',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'TINYTEXT_SYN',
        1 => 'opt_charset_with_opt_binary',
      ),
    ),
    29 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithTextSymOptFieldLengthOptCharsetWithOptBinary_f9d68c93',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'TEXT_SYM',
        1 => 'opt_field_length',
        2 => 'opt_charset_with_opt_binary',
      ),
    ),
    30 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithMediumtextSymOptCharsetWithOptBinary_3e93006b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'MEDIUMTEXT_SYM',
        1 => 'opt_charset_with_opt_binary',
      ),
    ),
    31 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithLongtextSymOptCharsetWithOptBinary_fa94ccc0',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LONGTEXT_SYM',
        1 => 'opt_charset_with_opt_binary',
      ),
    ),
    32 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithEnumSymStringListOptCharsetWithOptBinary_dcafe50e',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'ENUM_SYM',
        1 => '(',
        2 => 'string_list',
        3 => ')',
        4 => 'opt_charset_with_opt_binary',
      ),
    ),
    33 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithSetSymStringListOptCharsetWithOptBinary_5bf5c78d',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'SET_SYM',
        1 => '(',
        2 => 'string_list',
        3 => ')',
        4 => 'opt_charset_with_opt_binary',
      ),
    ),
    34 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithLongSymOptCharsetWithOptBinary_02982acc',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LONG_SYM',
        1 => 'opt_charset_with_opt_binary',
      ),
    ),
    35 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithSerialSym_9b29769b',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SERIAL_SYM',
      ),
    ),
    36 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithJsonSym_d784a430',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'JSON_SYM',
      ),
    ),
  ),
  'spatial_type' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpatialTypeWithGeometrySym_886a3377',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'GEOMETRY_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpatialTypeWithGeometrycollectionSym_a6121910',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'GEOMETRYCOLLECTION_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpatialTypeWithPointSym_e0841522',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'POINT_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpatialTypeWithMultipointSym_44c95c91',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'MULTIPOINT_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpatialTypeWithLinestringSym_65c18fc4',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'LINESTRING_SYM',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpatialTypeWithMultilinestringSym_a655db2a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'MULTILINESTRING_SYM',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpatialTypeWithPolygonSym_7c8fb4b4',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'POLYGON_SYM',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpatialTypeWithMultipolygonSym_700bb1f0',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'MULTIPOLYGON_SYM',
      ),
    ),
  ),
  'nchar' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NcharWithNcharSym_fdd57adb',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NCHAR_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NcharWithNationalSymCharSym_8d2d7822',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'NATIONAL_SYM',
        1 => 'CHAR_SYM',
      ),
    ),
  ),
  'varchar' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\VarcharWithCharSymVarying_a959635c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CHAR_SYM',
        1 => 'VARYING',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\VarcharWithVarcharSym_165e7bba',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'VARCHAR_SYM',
      ),
    ),
  ),
  'nvarchar' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NvarcharWithNationalSymVarcharSym_7b11dac8',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'NATIONAL_SYM',
        1 => 'VARCHAR_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NvarcharWithNvarcharSym_482a214c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NVARCHAR_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NvarcharWithNcharSymVarcharSym_88b407cf',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'NCHAR_SYM',
        1 => 'VARCHAR_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NvarcharWithNationalSymCharSymVarying_51fa105d',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'NATIONAL_SYM',
        1 => 'CHAR_SYM',
        2 => 'VARYING',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NvarcharWithNcharSymVarying_5beb3ae7',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NCHAR_SYM',
        1 => 'VARYING',
      ),
    ),
  ),
  'int_type' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntTypeWithIntSym_ae16590a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'INT_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntTypeWithTinyintSym_d556f0ba',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TINYINT_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntTypeWithSmallintSym_d0ca68a6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SMALLINT_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntTypeWithMediumintSym_59b5b1ec',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MEDIUMINT_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntTypeWithBigintSym_4fe77cff',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BIGINT_SYM',
      ),
    ),
  ),
  'real_type' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RealTypeWithRealSym_99bd5640',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'REAL_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RealTypeWithDoubleSymOptPrecision_871736d1',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DOUBLE_SYM',
        1 => 'opt_PRECISION',
      ),
    ),
  ),
  'opt_PRECISION' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptPrecisionChoice_1c718145::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptPrecisionChoice_1c718145::UsePrecision_783d8929',
      'symbols' =>
      array (
        0 => 'PRECISION',
      ),
    ),
  ),
  'numeric_type' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NumericTypeWithFloatSym_1955dc5a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FLOAT_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NumericTypeWithDecimalSym_fdf8cad6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DECIMAL_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NumericTypeWithNumericSym_4d613dfc',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NUMERIC_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NumericTypeWithFixedSym_d75c8291',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'FIXED_SYM',
      ),
    ),
  ),
  'standard_float_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandardFloatOptionsWith_97e44f01',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'field_length',
      ),
    ),
  ),
  'float_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FloatOptionsWith_59167973',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'field_length',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'precision',
      ),
    ),
  ),
  'precision' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PrecisionWithNumNum_b146042a',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'NUM',
        2 => ',',
        3 => 'NUM',
        4 => ')',
      ),
    ),
  ),
  'type_datetime_precision' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeDatetimePrecisionWith_994ff2df',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeDatetimePrecisionWithNum_838cd694',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'NUM',
        2 => ')',
      ),
    ),
  ),
  'func_datetime_precision' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FuncDatetimePrecisionWith_6152a3f9',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FuncDatetimePrecisionWith_7f68bd5e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => ')',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FuncDatetimePrecisionWithNum_d805444a',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'NUM',
        2 => ')',
      ),
    ),
  ),
  'field_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldOptionsWith_74c1c97c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'field_opt_list',
      ),
    ),
  ),
  'field_opt_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldOptListWithFieldOptListFieldOption_3c4e5aec',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'field_opt_list',
        1 => 'field_option',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'field_option',
      ),
    ),
  ),
  'field_option' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\FieldOptionChoice_01bed8e0::UseSigned_701723fe',
      'symbols' =>
      array (
        0 => 'SIGNED_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\FieldOptionChoice_01bed8e0::UseUnsigned_0839843f',
      'symbols' =>
      array (
        0 => 'UNSIGNED_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\FieldOptionChoice_01bed8e0::UseZerofill_34f1925c',
      'symbols' =>
      array (
        0 => 'ZEROFILL_SYM',
      ),
    ),
  ),
  'field_length' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldLengthWithLongNum_3e52d4e9',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'LONG_NUM',
        2 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldLengthWithUlonglongNum_d7d79f00',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'ULONGLONG_NUM',
        2 => ')',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldLengthWithDecimalNum_8703ddb4',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'DECIMAL_NUM',
        2 => ')',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldLengthWithNum_c0e43b60',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'NUM',
        2 => ')',
      ),
    ),
  ),
  'opt_field_length' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptFieldLengthWith_5946e675',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'field_length',
      ),
    ),
  ),
  'opt_precision' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPrecisionWith_3d94a07a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'precision',
      ),
    ),
  ),
  'opt_column_attribute_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptColumnAttributeListWith_365d7582',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'column_attribute_list',
      ),
    ),
  ),
  'column_attribute_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnAttributeListWithColumnAttributeListColumnAttribute_162e97dd',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'column_attribute_list',
        1 => 'column_attribute',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'column_attribute',
      ),
    ),
  ),
  'column_attribute' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnAttributeWithNullSym_75974420',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NULL_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnAttributeWithNotNullSym_fb89a163',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'not',
        1 => 'NULL_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnAttributeWithNotSecondarySym_ec177641',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'not',
        1 => 'SECONDARY_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnAttributeWithDefaultSymNowOrSignedLiteral_734821ff',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT_SYM',
        1 => 'now_or_signed_literal',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnAttributeWithDefaultSymExpr_ac7e2aac',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT_SYM',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnAttributeWithOnSymUpdateSymNow_728f533f',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ON_SYM',
        1 => 'UPDATE_SYM',
        2 => 'now',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnAttributeWithAutoInc_96f018b4',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'AUTO_INC',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnAttributeWithSerialSymDefaultSymValueSym_9e4cc32d',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SERIAL_SYM',
        1 => 'DEFAULT_SYM',
        2 => 'VALUE_SYM',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnAttributeWithOptPrimaryKeySym_5e18ec5c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'opt_primary',
        1 => 'KEY_SYM',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnAttributeWithUniqueSym_a2bb71da',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'UNIQUE_SYM',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnAttributeWithUniqueSymKeySym_7cff04f7',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'UNIQUE_SYM',
        1 => 'KEY_SYM',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnAttributeWithCommentSymTextStringSys_43651151',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'COMMENT_SYM',
        1 => 'TEXT_STRING_sys',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnAttributeWithCollateSymCollationName_e89489d1',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'COLLATE_SYM',
        1 => 'collation_name',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnAttributeWithColumnFormatSymColumnFormat_3c4879f2',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'COLUMN_FORMAT_SYM',
        1 => 'column_format',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnAttributeWithStorageSymStorageMedia_c5c6ae18',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'STORAGE_SYM',
        1 => 'storage_media',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnAttributeWithSridSymRealUlonglongNum_d36f7f92',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SRID_SYM',
        1 => 'real_ulonglong_num',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnAttributeWithOptConstraintNameCheckConstraint_74b7cb7a',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'opt_constraint_name',
        1 => 'check_constraint',
      ),
    ),
    17 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'constraint_enforcement',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnAttributeWithEngineAttributeSymOptEqualJsonAttribute_e3873b91',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ENGINE_ATTRIBUTE_SYM',
        1 => 'opt_equal',
        2 => 'json_attribute',
      ),
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnAttributeWithSecondaryEngineAttributeSymOptEqualJsonAttribute_3408de85',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SECONDARY_ENGINE_ATTRIBUTE_SYM',
        1 => 'opt_equal',
        2 => 'json_attribute',
      ),
    ),
    20 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'visibility',
      ),
    ),
  ),
  'column_format' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ColumnFormatChoice_3a30b484::UseDefault_89dbf710',
      'symbols' =>
      array (
        0 => 'DEFAULT_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ColumnFormatChoice_3a30b484::UseFixed_f28b6901',
      'symbols' =>
      array (
        0 => 'FIXED_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ColumnFormatChoice_3a30b484::UseDynamic_ef1070bb',
      'symbols' =>
      array (
        0 => 'DYNAMIC_SYM',
      ),
    ),
  ),
  'storage_media' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\StorageMediaChoice_544b467b::UseDefault_89dbf710',
      'symbols' =>
      array (
        0 => 'DEFAULT_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\StorageMediaChoice_544b467b::UseDisk_8267b044',
      'symbols' =>
      array (
        0 => 'DISK_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\StorageMediaChoice_544b467b::UseMemory_a266fe9c',
      'symbols' =>
      array (
        0 => 'MEMORY_SYM',
      ),
    ),
  ),
  'now' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NowWithNowSymFuncDatetimePrecision_8a7d9aba',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'NOW_SYM',
        1 => 'func_datetime_precision',
      ),
    ),
  ),
  'now_or_signed_literal' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'now',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'signed_literal_or_null',
      ),
    ),
  ),
  'character_set' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CharacterSetWithCharSymSetSym_10316b6c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CHAR_SYM',
        1 => 'SET_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CharacterSetWithCharset_451e4a9c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'CHARSET',
      ),
    ),
  ),
  'charset_name' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_or_text',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CharsetNameWithBinarySym_04b33183',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'BINARY_SYM',
      ),
    ),
  ),
  'opt_load_data_charset' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptLoadDataCharsetWith_ab23b997',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptLoadDataCharsetWithCharacterSetCharsetName_29ece866',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'character_set',
        1 => 'charset_name',
      ),
    ),
  ),
  'old_or_new_charset_name' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_or_text',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OldOrNewCharsetNameWithBinarySym_967ea453',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'BINARY_SYM',
      ),
    ),
  ),
  'old_or_new_charset_name_or_default' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'old_or_new_charset_name',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OldOrNewCharsetNameOrDefaultWithDefaultSym_bf7ef988',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT_SYM',
      ),
    ),
  ),
  'collation_name' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_or_text',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CollationNameWithBinarySym_36e1af5b',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'BINARY_SYM',
      ),
    ),
  ),
  'opt_collate' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptCollateWith_884f47b8',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptCollateWithCollateSymCollationName_7f12cdcb',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'COLLATE_SYM',
        1 => 'collation_name',
      ),
    ),
  ),
  'opt_default' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptDefaultChoice_5cef4826::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptDefaultChoice_5cef4826::UseDefault_89dbf710',
      'symbols' =>
      array (
        0 => 'DEFAULT_SYM',
      ),
    ),
  ),
  'ascii' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\AsciiChoice_bcce0f9e::UseAscii_481868aa',
      'symbols' =>
      array (
        0 => 'ASCII_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\AsciiChoice_bcce0f9e::UseBinaryAscii_8dcea185',
      'symbols' =>
      array (
        0 => 'BINARY_SYM',
        1 => 'ASCII_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\AsciiChoice_bcce0f9e::UseAsciiBinary_99b568cc',
      'symbols' =>
      array (
        0 => 'ASCII_SYM',
        1 => 'BINARY_SYM',
      ),
    ),
  ),
  'unicode' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\UnicodeChoice_12e6823c::UseUnicode_7d10420f',
      'symbols' =>
      array (
        0 => 'UNICODE_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\UnicodeChoice_12e6823c::UseUnicodeBinary_e90354c2',
      'symbols' =>
      array (
        0 => 'UNICODE_SYM',
        1 => 'BINARY_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\UnicodeChoice_12e6823c::UseBinaryUnicode_62131883',
      'symbols' =>
      array (
        0 => 'BINARY_SYM',
        1 => 'UNICODE_SYM',
      ),
    ),
  ),
  'opt_charset_with_opt_binary' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptCharsetWithOptBinaryWith_50c2d2d7',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ascii',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'unicode',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptCharsetWithOptBinaryWithByteSym_0f27da53',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'BYTE_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptCharsetWithOptBinaryWithCharacterSetCharsetNameOptBinMod_5634910c',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'character_set',
        1 => 'charset_name',
        2 => 'opt_bin_mod',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptCharsetWithOptBinaryWithBinarySym_0a9a0946',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'BINARY_SYM',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptCharsetWithOptBinaryWithBinarySymCharacterSetCharsetName_3c6faee9',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'BINARY_SYM',
        1 => 'character_set',
        2 => 'charset_name',
      ),
    ),
  ),
  'opt_bin_mod' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptBinModChoice_e8ffc0d2::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptBinModChoice_e8ffc0d2::UseBinary_4c77b56b',
      'symbols' =>
      array (
        0 => 'BINARY_SYM',
      ),
    ),
  ),
  'ws_num_codepoints' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WsNumCodepointsWithRealUlongNum_ffc61d5d',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'real_ulong_num',
        2 => ')',
      ),
    ),
  ),
  'opt_primary' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptPrimaryChoice_80145a26::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptPrimaryChoice_80145a26::UsePrimary_bc9a6688',
      'symbols' =>
      array (
        0 => 'PRIMARY_SYM',
      ),
    ),
  ),
  'references' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ReferencesWithReferencesTableIdentOptRefListOptMatchClauseOptOnUpdateDelete_1f679bc6',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'REFERENCES',
        1 => 'table_ident',
        2 => 'opt_ref_list',
        3 => 'opt_match_clause',
        4 => 'opt_on_update_delete',
      ),
    ),
  ),
  'opt_ref_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptRefListWith_f30e73a3',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptRefListWithReferenceList_93f45fe9',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'reference_list',
        2 => ')',
      ),
    ),
  ),
  'reference_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ReferenceListWithReferenceListIdent_53d13dba',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'reference_list',
        1 => ',',
        2 => 'ident',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
  ),
  'opt_match_clause' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptMatchClauseChoice_46f6cce1::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptMatchClauseChoice_46f6cce1::UseMatchFull_a1c2aa11',
      'symbols' =>
      array (
        0 => 'MATCH',
        1 => 'FULL',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptMatchClauseChoice_46f6cce1::UseMatchPartial_b0d05904',
      'symbols' =>
      array (
        0 => 'MATCH',
        1 => 'PARTIAL',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptMatchClauseChoice_46f6cce1::UseMatchSimple_cdbeceec',
      'symbols' =>
      array (
        0 => 'MATCH',
        1 => 'SIMPLE_SYM',
      ),
    ),
  ),
  'opt_on_update_delete' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptOnUpdateDeleteWith_eb58e09d',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptOnUpdateDeleteWithOnSymUpdateSymDeleteOption_085f45de',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ON_SYM',
        1 => 'UPDATE_SYM',
        2 => 'delete_option',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptOnUpdateDeleteWithOnSymDeleteSymDeleteOption_34b47242',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ON_SYM',
        1 => 'DELETE_SYM',
        2 => 'delete_option',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptOnUpdateDeleteWithOnSymUpdateSymDeleteOptionOnSymDeleteSymDeleteOption_774692bd',
      'fields' =>
      array (
        0 => 2,
        1 => 5,
      ),
      'symbols' =>
      array (
        0 => 'ON_SYM',
        1 => 'UPDATE_SYM',
        2 => 'delete_option',
        3 => 'ON_SYM',
        4 => 'DELETE_SYM',
        5 => 'delete_option',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptOnUpdateDeleteWithOnSymDeleteSymDeleteOptionOnSymUpdateSymDeleteOption_e957a5fd',
      'fields' =>
      array (
        0 => 2,
        1 => 5,
      ),
      'symbols' =>
      array (
        0 => 'ON_SYM',
        1 => 'DELETE_SYM',
        2 => 'delete_option',
        3 => 'ON_SYM',
        4 => 'UPDATE_SYM',
        5 => 'delete_option',
      ),
    ),
  ),
  'delete_option' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\DeleteOptionChoice_5edeaf0e::UseRestrict_bd7a04e6',
      'symbols' =>
      array (
        0 => 'RESTRICT',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\DeleteOptionChoice_5edeaf0e::UseCascade_86844e57',
      'symbols' =>
      array (
        0 => 'CASCADE',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\DeleteOptionChoice_5edeaf0e::UseSetNull_a5f7c4e6',
      'symbols' =>
      array (
        0 => 'SET_SYM',
        1 => 'NULL_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\DeleteOptionChoice_5edeaf0e::UseNoAction_25595c7c',
      'symbols' =>
      array (
        0 => 'NO_SYM',
        1 => 'ACTION',
      ),
    ),
    4 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\DeleteOptionChoice_5edeaf0e::UseSetDefault_639a6c2d',
      'symbols' =>
      array (
        0 => 'SET_SYM',
        1 => 'DEFAULT_SYM',
      ),
    ),
  ),
  'constraint_key_type' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ConstraintKeyTypeWithPrimarySymKeySym_78ff0f3b',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'PRIMARY_SYM',
        1 => 'KEY_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ConstraintKeyTypeWithUniqueSymOptKeyOrIndex_3b62040c',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'UNIQUE_SYM',
        1 => 'opt_key_or_index',
      ),
    ),
  ),
  'key_or_index' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\KeyOrIndexChoice_f6ff0fbf::UseKey_5ca24005',
      'symbols' =>
      array (
        0 => 'KEY_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\KeyOrIndexChoice_f6ff0fbf::UseIndex_ea69fe17',
      'symbols' =>
      array (
        0 => 'INDEX_SYM',
      ),
    ),
  ),
  'opt_key_or_index' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptKeyOrIndexWith_12f1c8b2',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'key_or_index',
      ),
    ),
  ),
  'keys_or_index' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\KeysOrIndexChoice_63f390bb::UseKeys_3fca5061',
      'symbols' =>
      array (
        0 => 'KEYS',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\KeysOrIndexChoice_63f390bb::UseIndex_ea69fe17',
      'symbols' =>
      array (
        0 => 'INDEX_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\KeysOrIndexChoice_63f390bb::UseIndexes_019e4e72',
      'symbols' =>
      array (
        0 => 'INDEXES',
      ),
    ),
  ),
  'opt_unique' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptUniqueChoice_78808712::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptUniqueChoice_78808712::UseUnique_64636a12',
      'symbols' =>
      array (
        0 => 'UNIQUE_SYM',
      ),
    ),
  ),
  'opt_fulltext_index_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptFulltextIndexOptionsWith_414e63c4',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'fulltext_index_options',
      ),
    ),
  ),
  'fulltext_index_options' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'fulltext_index_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FulltextIndexOptionsWithFulltextIndexOptionsFulltextIndexOption_109a666c',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'fulltext_index_options',
        1 => 'fulltext_index_option',
      ),
    ),
  ),
  'fulltext_index_option' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'common_index_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FulltextIndexOptionWithWithParserSymIdentSys_8cd50188',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'PARSER_SYM',
        2 => 'IDENT_sys',
      ),
    ),
  ),
  'opt_spatial_index_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSpatialIndexOptionsWith_c680d8bc',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'spatial_index_options',
      ),
    ),
  ),
  'spatial_index_options' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'spatial_index_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpatialIndexOptionsWithSpatialIndexOptionsSpatialIndexOption_8321a369',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'spatial_index_options',
        1 => 'spatial_index_option',
      ),
    ),
  ),
  'spatial_index_option' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'common_index_option',
      ),
    ),
  ),
  'opt_index_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIndexOptionsWith_4e486f6e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'index_options',
      ),
    ),
  ),
  'index_options' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'index_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IndexOptionsWithIndexOptionsIndexOption_d896d05b',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'index_options',
        1 => 'index_option',
      ),
    ),
  ),
  'index_option' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'common_index_option',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'index_type_clause',
      ),
    ),
  ),
  'common_index_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CommonIndexOptionWithKeyBlockSizeOptEqualUlongNum_f571ef5c',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'KEY_BLOCK_SIZE',
        1 => 'opt_equal',
        2 => 'ulong_num',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CommonIndexOptionWithCommentSymTextStringSys_d5b63102',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'COMMENT_SYM',
        1 => 'TEXT_STRING_sys',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'visibility',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CommonIndexOptionWithEngineAttributeSymOptEqualJsonAttribute_b825b6cc',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ENGINE_ATTRIBUTE_SYM',
        1 => 'opt_equal',
        2 => 'json_attribute',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CommonIndexOptionWithSecondaryEngineAttributeSymOptEqualJsonAttribute_091a2490',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SECONDARY_ENGINE_ATTRIBUTE_SYM',
        1 => 'opt_equal',
        2 => 'json_attribute',
      ),
    ),
  ),
  'opt_index_name_and_type' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIndexNameAndTypeWithOptIdentUsingIndexType_bb698abc',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'opt_ident',
        1 => 'USING',
        2 => 'index_type',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIndexNameAndTypeWithIdentTypeSymIndexType_465d7414',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => 'TYPE_SYM',
        2 => 'index_type',
      ),
    ),
  ),
  'opt_index_type_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIndexTypeClauseWith_0c059514',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'index_type_clause',
      ),
    ),
  ),
  'index_type_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IndexTypeClauseWithUsingIndexType_837cc56e',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'USING',
        1 => 'index_type',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IndexTypeClauseWithTypeSymIndexType_d6e76b16',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'TYPE_SYM',
        1 => 'index_type',
      ),
    ),
  ),
  'visibility' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\VisibilityChoice_d7208761::UseVisible_5cea256d',
      'symbols' =>
      array (
        0 => 'VISIBLE_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\VisibilityChoice_d7208761::UseInvisible_03f65a90',
      'symbols' =>
      array (
        0 => 'INVISIBLE_SYM',
      ),
    ),
  ),
  'index_type' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\IndexTypeChoice_5a9f82fb::UseBtree_3a08eb1d',
      'symbols' =>
      array (
        0 => 'BTREE_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\IndexTypeChoice_5a9f82fb::UseRtree_c3316be2',
      'symbols' =>
      array (
        0 => 'RTREE_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\IndexTypeChoice_5a9f82fb::UseHash_c1fb44c7',
      'symbols' =>
      array (
        0 => 'HASH_SYM',
      ),
    ),
  ),
  'key_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyListWithKeyListKeyPart_24a96f9f',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'key_list',
        1 => ',',
        2 => 'key_part',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'key_part',
      ),
    ),
  ),
  'key_part' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyPartWithIdentOptOrderingDirection_408b0960',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => 'opt_ordering_direction',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyPartWithIdentNumOptOrderingDirection_49f320b2',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => '(',
        2 => 'NUM',
        3 => ')',
        4 => 'opt_ordering_direction',
      ),
    ),
  ),
  'key_list_with_expression' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyListWithExpressionWithKeyListWithExpressionKeyPartWithExpression_2aee3d17',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'key_list_with_expression',
        1 => ',',
        2 => 'key_part_with_expression',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'key_part_with_expression',
      ),
    ),
  ),
  'key_part_with_expression' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'key_part',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyPartWithExpressionWithExprOptOrderingDirection_23caebd6',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'expr',
        2 => ')',
        3 => 'opt_ordering_direction',
      ),
    ),
  ),
  'opt_ident' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIdentWith_272e262b',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
  ),
  'string_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'text_string',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StringListWithStringListTextString_f62af2d6',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'string_list',
        1 => ',',
        2 => 'text_string',
      ),
    ),
  ),
  'alter_table_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterTableStmtWithAlterTableSymTableIdentOptAlterTableActions_9e5ca714',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'TABLE_SYM',
        2 => 'table_ident',
        3 => 'opt_alter_table_actions',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterTableStmtWithAlterTableSymTableIdentStandaloneAlterTableAction_cdacb9cb',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'TABLE_SYM',
        2 => 'table_ident',
        3 => 'standalone_alter_table_action',
      ),
    ),
  ),
  'alter_database_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterDatabaseStmtWithAlterDatabaseIdentOrEmptyAlterDatabaseOptions_27d4d7a8',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'DATABASE',
        2 => 'ident_or_empty',
        3 => 'alter_database_options',
      ),
    ),
  ),
  'alter_procedure_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterProcedureStmtWithAlterProcedureSymSpNameSpAChistics_423008b4',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'PROCEDURE_SYM',
        2 => 'sp_name',
        3 => 'sp_a_chistics',
      ),
    ),
  ),
  'alter_function_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterFunctionStmtWithAlterFunctionSymSpNameSpAChistics_e4395087',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'FUNCTION_SYM',
        2 => 'sp_name',
        3 => 'sp_a_chistics',
      ),
    ),
  ),
  'alter_view_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterViewStmtWithAlterViewAlgorithmDefinerOptViewTail_6fe44f1c',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'view_algorithm',
        2 => 'definer_opt',
        3 => 'view_tail',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterViewStmtWithAlterDefinerOptViewTail_5cc7287f',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'definer_opt',
        2 => 'view_tail',
      ),
    ),
  ),
  'alter_event_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterEventStmtWithAlterDefinerOptEventSymSpNameEvAlterOnScheduleCompletionOptEvRenameToOptEvS_4ceaf2e5',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 4,
        3 => 5,
        4 => 6,
        5 => 7,
        6 => 8,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'definer_opt',
        2 => 'EVENT_SYM',
        3 => 'sp_name',
        4 => 'ev_alter_on_schedule_completion',
        5 => 'opt_ev_rename_to',
        6 => 'opt_ev_status',
        7 => 'opt_ev_comment',
        8 => 'opt_ev_sql_stmt',
      ),
    ),
  ),
  'alter_logfile_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterLogfileStmtWithAlterLogfileSymGroupSymIdentAddLgUndofileOptAlterLogfileGroupOptions_ba5c6944',
      'fields' =>
      array (
        0 => 3,
        1 => 5,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'LOGFILE_SYM',
        2 => 'GROUP_SYM',
        3 => 'ident',
        4 => 'ADD',
        5 => 'lg_undofile',
        6 => 'opt_alter_logfile_group_options',
      ),
    ),
  ),
  'alter_tablespace_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterTablespaceStmtWithAlterTablespaceSymIdentAddTsDatafileOptAlterTablespaceOptions_7730373f',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'TABLESPACE_SYM',
        2 => 'ident',
        3 => 'ADD',
        4 => 'ts_datafile',
        5 => 'opt_alter_tablespace_options',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterTablespaceStmtWithAlterTablespaceSymIdentDropTsDatafileOptAlterTablespaceOptions_8e45b3ac',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'TABLESPACE_SYM',
        2 => 'ident',
        3 => 'DROP',
        4 => 'ts_datafile',
        5 => 'opt_alter_tablespace_options',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterTablespaceStmtWithAlterTablespaceSymIdentRenameToSymIdent_29694a86',
      'fields' =>
      array (
        0 => 2,
        1 => 5,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'TABLESPACE_SYM',
        2 => 'ident',
        3 => 'RENAME',
        4 => 'TO_SYM',
        5 => 'ident',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterTablespaceStmtWithAlterTablespaceSymIdentAlterTablespaceOptionList_568cd599',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'TABLESPACE_SYM',
        2 => 'ident',
        3 => 'alter_tablespace_option_list',
      ),
    ),
  ),
  'alter_undo_tablespace_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUndoTablespaceStmtWithAlterUndoSymTablespaceSymIdentSetSymUndoTablespaceStateOptUndoTablespaceOpt_be2cbbba',
      'fields' =>
      array (
        0 => 3,
        1 => 5,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'UNDO_SYM',
        2 => 'TABLESPACE_SYM',
        3 => 'ident',
        4 => 'SET_SYM',
        5 => 'undo_tablespace_state',
        6 => 'opt_undo_tablespace_options',
      ),
    ),
  ),
  'alter_server_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterServerStmtWithAlterServerSymIdentOrTextOptionsSymServerOptionsList_c7a07438',
      'fields' =>
      array (
        0 => 2,
        1 => 5,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'SERVER_SYM',
        2 => 'ident_or_text',
        3 => 'OPTIONS_SYM',
        4 => '(',
        5 => 'server_options_list',
        6 => ')',
      ),
    ),
  ),
  'alter_user_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserStmtWithAlterUserCommandAlterUserListRequireClauseConnectOptionsOptAccountLockPassw_93f5dc3c',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
        4 => 4,
        5 => 5,
      ),
      'symbols' =>
      array (
        0 => 'alter_user_command',
        1 => 'alter_user_list',
        2 => 'require_clause',
        3 => 'connect_options',
        4 => 'opt_account_lock_password_expire_options',
        5 => 'opt_user_attribute',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserStmtWithAlterUserCommandUserFuncIdentifiedByRandomPasswordOptReplacePasswordOptReta_5f6494af',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
        4 => 4,
      ),
      'symbols' =>
      array (
        0 => 'alter_user_command',
        1 => 'user_func',
        2 => 'identified_by_random_password',
        3 => 'opt_replace_password',
        4 => 'opt_retain_current_password',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserStmtWithAlterUserCommandUserFuncIdentifiedByPasswordOptReplacePasswordOptRetainCurr_4986121c',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
        4 => 4,
      ),
      'symbols' =>
      array (
        0 => 'alter_user_command',
        1 => 'user_func',
        2 => 'identified_by_password',
        3 => 'opt_replace_password',
        4 => 'opt_retain_current_password',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserStmtWithAlterUserCommandUserFuncDiscardSymOldSymPassword_7b69c0d5',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'alter_user_command',
        1 => 'user_func',
        2 => 'DISCARD_SYM',
        3 => 'OLD_SYM',
        4 => 'PASSWORD',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserStmtWithAlterUserCommandUserDefaultSymRoleSymAll_a8b0b960',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'alter_user_command',
        1 => 'user',
        2 => 'DEFAULT_SYM',
        3 => 'ROLE_SYM',
        4 => 'ALL',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserStmtWithAlterUserCommandUserDefaultSymRoleSymNoneSym_66879c7a',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'alter_user_command',
        1 => 'user',
        2 => 'DEFAULT_SYM',
        3 => 'ROLE_SYM',
        4 => 'NONE_SYM',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserStmtWithAlterUserCommandUserDefaultSymRoleSymRoleList_b3e35912',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'alter_user_command',
        1 => 'user',
        2 => 'DEFAULT_SYM',
        3 => 'ROLE_SYM',
        4 => 'role_list',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserStmtWithAlterUserCommandUserOptUserRegistration_6bae80ee',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'alter_user_command',
        1 => 'user',
        2 => 'opt_user_registration',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserStmtWithAlterUserCommandUserFuncOptUserRegistration_debaabcf',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'alter_user_command',
        1 => 'user_func',
        2 => 'opt_user_registration',
      ),
    ),
  ),
  'opt_replace_password' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptReplacePasswordWith_55807822',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptReplacePasswordWithReplaceSymTextStringPassword_baa2ba02',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'REPLACE_SYM',
        1 => 'TEXT_STRING_password',
      ),
    ),
  ),
  'alter_resource_group_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterResourceGroupStmtWithAlterResourceSymGroupSymIdentOptResourceGroupVcpuListOptResourceGroupPriori_2421b3ea',
      'fields' =>
      array (
        0 => 3,
        1 => 4,
        2 => 5,
        3 => 6,
        4 => 7,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'RESOURCE_SYM',
        2 => 'GROUP_SYM',
        3 => 'ident',
        4 => 'opt_resource_group_vcpu_list',
        5 => 'opt_resource_group_priority',
        6 => 'opt_resource_group_enable_disable',
        7 => 'opt_force',
      ),
    ),
  ),
  'alter_user_command' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserCommandWithAlterUserIfExists_3276589d',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'USER',
        2 => 'if_exists',
      ),
    ),
  ),
  'opt_user_attribute' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptUserAttributeWith_ee64e4b7',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptUserAttributeWithAttributeSymTextStringLiteral_6e7b3e29',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ATTRIBUTE_SYM',
        1 => 'TEXT_STRING_literal',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptUserAttributeWithCommentSymTextStringLiteral_054d4c9e',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'COMMENT_SYM',
        1 => 'TEXT_STRING_literal',
      ),
    ),
  ),
  'opt_account_lock_password_expire_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAccountLockPasswordExpireOptionsWith_85a2700a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_account_lock_password_expire_option_list',
      ),
    ),
  ),
  'opt_account_lock_password_expire_option_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_account_lock_password_expire_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAccountLockPasswordExpireOptionListWithOptAccountLockPasswordExpireOptionListOptAccountLockPasswordExpireOption_a4bd7338',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'opt_account_lock_password_expire_option_list',
        1 => 'opt_account_lock_password_expire_option',
      ),
    ),
  ),
  'opt_account_lock_password_expire_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAccountLockPasswordExpireOptionWithAccountSymUnlockSym_4fa132cd',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ACCOUNT_SYM',
        1 => 'UNLOCK_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAccountLockPasswordExpireOptionWithAccountSymLockSym_164528cb',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ACCOUNT_SYM',
        1 => 'LOCK_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAccountLockPasswordExpireOptionWithPasswordExpireSym_24ed3131',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'EXPIRE_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAccountLockPasswordExpireOptionWithPasswordExpireSymIntervalSymRealUlongNumDaySym_e63ff55a',
      'fields' =>
      array (
        0 => 3,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'EXPIRE_SYM',
        2 => 'INTERVAL_SYM',
        3 => 'real_ulong_num',
        4 => 'DAY_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAccountLockPasswordExpireOptionWithPasswordExpireSymNeverSym_2c3f4ee1',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'EXPIRE_SYM',
        2 => 'NEVER_SYM',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAccountLockPasswordExpireOptionWithPasswordExpireSymDefaultSym_3bde59ed',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'EXPIRE_SYM',
        2 => 'DEFAULT_SYM',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAccountLockPasswordExpireOptionWithPasswordHistorySymRealUlongNum_cbab3704',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'HISTORY_SYM',
        2 => 'real_ulong_num',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAccountLockPasswordExpireOptionWithPasswordHistorySymDefaultSym_41a58ea2',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'HISTORY_SYM',
        2 => 'DEFAULT_SYM',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAccountLockPasswordExpireOptionWithPasswordReuseSymIntervalSymRealUlongNumDaySym_4f5bd8bb',
      'fields' =>
      array (
        0 => 3,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'REUSE_SYM',
        2 => 'INTERVAL_SYM',
        3 => 'real_ulong_num',
        4 => 'DAY_SYM',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAccountLockPasswordExpireOptionWithPasswordReuseSymIntervalSymDefaultSym_c1c5ef93',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'REUSE_SYM',
        2 => 'INTERVAL_SYM',
        3 => 'DEFAULT_SYM',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAccountLockPasswordExpireOptionWithPasswordRequireSymCurrentSym_a0a2690f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'REQUIRE_SYM',
        2 => 'CURRENT_SYM',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAccountLockPasswordExpireOptionWithPasswordRequireSymCurrentSymDefaultSym_6303ba83',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'REQUIRE_SYM',
        2 => 'CURRENT_SYM',
        3 => 'DEFAULT_SYM',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAccountLockPasswordExpireOptionWithPasswordRequireSymCurrentSymOptionalSym_ddb073ad',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'REQUIRE_SYM',
        2 => 'CURRENT_SYM',
        3 => 'OPTIONAL_SYM',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAccountLockPasswordExpireOptionWithFailedLoginAttemptsSymRealUlongNum_501d247c',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'FAILED_LOGIN_ATTEMPTS_SYM',
        1 => 'real_ulong_num',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAccountLockPasswordExpireOptionWithPasswordLockTimeSymRealUlongNum_1d41c93a',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD_LOCK_TIME_SYM',
        1 => 'real_ulong_num',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAccountLockPasswordExpireOptionWithPasswordLockTimeSymUnboundedSym_384a90f7',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD_LOCK_TIME_SYM',
        1 => 'UNBOUNDED_SYM',
      ),
    ),
  ),
  'connect_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ConnectOptionsWith_11cd14bf',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ConnectOptionsWithWithConnectOptionList_c99c8af1',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'connect_option_list',
      ),
    ),
  ),
  'connect_option_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ConnectOptionListWithConnectOptionListConnectOption_8de97d56',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'connect_option_list',
        1 => 'connect_option',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'connect_option',
      ),
    ),
  ),
  'connect_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ConnectOptionWithMaxQueriesPerHourUlongNum_4907374b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'MAX_QUERIES_PER_HOUR',
        1 => 'ulong_num',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ConnectOptionWithMaxUpdatesPerHourUlongNum_c2b0ffdc',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'MAX_UPDATES_PER_HOUR',
        1 => 'ulong_num',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ConnectOptionWithMaxConnectionsPerHourUlongNum_11ac81a9',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'MAX_CONNECTIONS_PER_HOUR',
        1 => 'ulong_num',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ConnectOptionWithMaxUserConnectionsSymUlongNum_bef2c04b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'MAX_USER_CONNECTIONS_SYM',
        1 => 'ulong_num',
      ),
    ),
  ),
  'user_func' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UserFuncWithUser_7d483aef',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'USER',
        1 => '(',
        2 => ')',
      ),
    ),
  ),
  'ev_alter_on_schedule_completion' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\EvAlterOnScheduleCompletionWith_1f9ed2c9',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\EvAlterOnScheduleCompletionWithOnSymScheduleSymEvScheduleTime_8290f166',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ON_SYM',
        1 => 'SCHEDULE_SYM',
        2 => 'ev_schedule_time',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ev_on_completion',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\EvAlterOnScheduleCompletionWithOnSymScheduleSymEvScheduleTimeEvOnCompletion_758530e0',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ON_SYM',
        1 => 'SCHEDULE_SYM',
        2 => 'ev_schedule_time',
        3 => 'ev_on_completion',
      ),
    ),
  ),
  'opt_ev_rename_to' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptEvRenameToWith_2bede306',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptEvRenameToWithRenameToSymSpName_be93d49b',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'RENAME',
        1 => 'TO_SYM',
        2 => 'sp_name',
      ),
    ),
  ),
  'opt_ev_sql_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptEvSqlStmtWith_4da36ac1',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptEvSqlStmtWithDoSymEvSqlStmt_a4860ccd',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DO_SYM',
        1 => 'ev_sql_stmt',
      ),
    ),
  ),
  'ident_or_empty' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentOrEmptyWith_f1ec48b1',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
  ),
  'opt_alter_table_actions' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_alter_command_list',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAlterTableActionsWithOptAlterCommandListAlterTablePartitionOptions_6428b7bb',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'opt_alter_command_list',
        1 => 'alter_table_partition_options',
      ),
    ),
  ),
  'standalone_alter_table_action' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'standalone_alter_commands',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterTableActionWithAlterCommandsModifierListStandaloneAlterCommands_29945c1e',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'alter_commands_modifier_list',
        1 => ',',
        2 => 'standalone_alter_commands',
      ),
    ),
  ),
  'alter_table_partition_options' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'partition_clause',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterTablePartitionOptionsWithRemoveSymPartitioningSym_c96cd05c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'REMOVE_SYM',
        1 => 'PARTITIONING_SYM',
      ),
    ),
  ),
  'opt_alter_command_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAlterCommandListWith_27bb6e79',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_commands_modifier_list',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_list',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAlterCommandListWithAlterCommandsModifierListAlterList_b16bd83f',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'alter_commands_modifier_list',
        1 => ',',
        2 => 'alter_list',
      ),
    ),
  ),
  'standalone_alter_commands' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterCommandsWithDiscardSymTablespaceSym_54cac5f9',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DISCARD_SYM',
        1 => 'TABLESPACE_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterCommandsWithImportTablespaceSym_dcdc2e4b',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'IMPORT',
        1 => 'TABLESPACE_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterCommandsWithAddPartitionSymOptNoWriteToBinlog_085dd5d7',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ADD',
        1 => 'PARTITION_SYM',
        2 => 'opt_no_write_to_binlog',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterCommandsWithAddPartitionSymOptNoWriteToBinlogPartDefList_41ca164f',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'ADD',
        1 => 'PARTITION_SYM',
        2 => 'opt_no_write_to_binlog',
        3 => '(',
        4 => 'part_def_list',
        5 => ')',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterCommandsWithAddPartitionSymOptNoWriteToBinlogPartitionsSymRealUlongNum_3ccfd8b0',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'ADD',
        1 => 'PARTITION_SYM',
        2 => 'opt_no_write_to_binlog',
        3 => 'PARTITIONS_SYM',
        4 => 'real_ulong_num',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterCommandsWithDropPartitionSymIdentStringList_e41e5c1d',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'PARTITION_SYM',
        2 => 'ident_string_list',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterCommandsWithRebuildSymPartitionSymOptNoWriteToBinlogAllOrAltPartNameList_a531ae19',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'REBUILD_SYM',
        1 => 'PARTITION_SYM',
        2 => 'opt_no_write_to_binlog',
        3 => 'all_or_alt_part_name_list',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterCommandsWithOptimizePartitionSymOptNoWriteToBinlogAllOrAltPartNameList_492888d5',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'OPTIMIZE',
        1 => 'PARTITION_SYM',
        2 => 'opt_no_write_to_binlog',
        3 => 'all_or_alt_part_name_list',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterCommandsWithAnalyzeSymPartitionSymOptNoWriteToBinlogAllOrAltPartNameList_f5f199cd',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ANALYZE_SYM',
        1 => 'PARTITION_SYM',
        2 => 'opt_no_write_to_binlog',
        3 => 'all_or_alt_part_name_list',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterCommandsWithCheckSymPartitionSymAllOrAltPartNameListOptMiCheckTypes_42770747',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'CHECK_SYM',
        1 => 'PARTITION_SYM',
        2 => 'all_or_alt_part_name_list',
        3 => 'opt_mi_check_types',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterCommandsWithRepairPartitionSymOptNoWriteToBinlogAllOrAltPartNameListOptMiRepairTypes_d90f530d',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'REPAIR',
        1 => 'PARTITION_SYM',
        2 => 'opt_no_write_to_binlog',
        3 => 'all_or_alt_part_name_list',
        4 => 'opt_mi_repair_types',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterCommandsWithCoalescePartitionSymOptNoWriteToBinlogRealUlongNum_55baa0cd',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'COALESCE',
        1 => 'PARTITION_SYM',
        2 => 'opt_no_write_to_binlog',
        3 => 'real_ulong_num',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterCommandsWithTruncateSymPartitionSymAllOrAltPartNameList_64757ed5',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'TRUNCATE_SYM',
        1 => 'PARTITION_SYM',
        2 => 'all_or_alt_part_name_list',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterCommandsWithReorganizeSymPartitionSymOptNoWriteToBinlog_24a5790f',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'REORGANIZE_SYM',
        1 => 'PARTITION_SYM',
        2 => 'opt_no_write_to_binlog',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterCommandsWithReorganizeSymPartitionSymOptNoWriteToBinlogIdentStringListIntoPartDefList_09bde691',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'REORGANIZE_SYM',
        1 => 'PARTITION_SYM',
        2 => 'opt_no_write_to_binlog',
        3 => 'ident_string_list',
        4 => 'INTO',
        5 => '(',
        6 => 'part_def_list',
        7 => ')',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterCommandsWithExchangeSymPartitionSymIdentWithTableSymTableIdentOptWithValidation_d24efb2a',
      'fields' =>
      array (
        0 => 2,
        1 => 5,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'EXCHANGE_SYM',
        1 => 'PARTITION_SYM',
        2 => 'ident',
        3 => 'WITH',
        4 => 'TABLE_SYM',
        5 => 'table_ident',
        6 => 'opt_with_validation',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterCommandsWithDiscardSymPartitionSymAllOrAltPartNameListTablespaceSym_9f5f0f30',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DISCARD_SYM',
        1 => 'PARTITION_SYM',
        2 => 'all_or_alt_part_name_list',
        3 => 'TABLESPACE_SYM',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterCommandsWithImportPartitionSymAllOrAltPartNameListTablespaceSym_78a1c915',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'IMPORT',
        1 => 'PARTITION_SYM',
        2 => 'all_or_alt_part_name_list',
        3 => 'TABLESPACE_SYM',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterCommandsWithSecondaryLoadSym_9bf73baf',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SECONDARY_LOAD_SYM',
      ),
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StandaloneAlterCommandsWithSecondaryUnloadSym_023d7937',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SECONDARY_UNLOAD_SYM',
      ),
    ),
  ),
  'opt_with_validation' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptWithValidationWith_34097fc9',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'with_validation',
      ),
    ),
  ),
  'with_validation' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\WithValidationChoice_6a2a77c6::UseWithValidation_193f25f1',
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'VALIDATION_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\WithValidationChoice_6a2a77c6::UseWithoutValidation_c3ffb244',
      'symbols' =>
      array (
        0 => 'WITHOUT_SYM',
        1 => 'VALIDATION_SYM',
      ),
    ),
  ),
  'all_or_alt_part_name_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AllOrAltPartNameListWithAll_e565f304',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ALL',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_string_list',
      ),
    ),
  ),
  'alter_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_list_item',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListWithAlterListAlterListItem_b11d1276',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'alter_list',
        1 => ',',
        2 => 'alter_list_item',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListWithAlterListAlterCommandsModifier_86c99eeb',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'alter_list',
        1 => ',',
        2 => 'alter_commands_modifier',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'create_table_options_space_separated',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListWithAlterListCreateTableOptionsSpaceSeparated_031bcf52',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'alter_list',
        1 => ',',
        2 => 'create_table_options_space_separated',
      ),
    ),
  ),
  'alter_commands_modifier_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_commands_modifier',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterCommandsModifierListWithAlterCommandsModifierListAlterCommandsModifier_8d9186ab',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'alter_commands_modifier_list',
        1 => ',',
        2 => 'alter_commands_modifier',
      ),
    ),
  ),
  'alter_list_item' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithAddOptColumnIdentFieldDefOptReferencesOptPlace_b599af0d',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
      ),
      'symbols' =>
      array (
        0 => 'ADD',
        1 => 'opt_column',
        2 => 'ident',
        3 => 'field_def',
        4 => 'opt_references',
        5 => 'opt_place',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithAddOptColumnTableElementList_803278ea',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ADD',
        1 => 'opt_column',
        2 => '(',
        3 => 'table_element_list',
        4 => ')',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithAddTableConstraintDef_05547204',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ADD',
        1 => 'table_constraint_def',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithChangeOptColumnIdentIdentFieldDefOptPlace_f3ae7342',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
      ),
      'symbols' =>
      array (
        0 => 'CHANGE',
        1 => 'opt_column',
        2 => 'ident',
        3 => 'ident',
        4 => 'field_def',
        5 => 'opt_place',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithModifySymOptColumnIdentFieldDefOptPlace_06526116',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'MODIFY_SYM',
        1 => 'opt_column',
        2 => 'ident',
        3 => 'field_def',
        4 => 'opt_place',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithDropOptColumnIdentOptRestrict_0b5b1d41',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'opt_column',
        2 => 'ident',
        3 => 'opt_restrict',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithDropForeignKeySymIdent_c21bc800',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'FOREIGN',
        2 => 'KEY_SYM',
        3 => 'ident',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithDropPrimarySymKeySym_ae3f6e96',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'PRIMARY_SYM',
        2 => 'KEY_SYM',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithDropKeyOrIndexIdent_6bd7ba11',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'key_or_index',
        2 => 'ident',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithDropCheckSymIdent_6ba683fa',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'CHECK_SYM',
        2 => 'ident',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithDropConstraintIdent_ccf88c11',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'CONSTRAINT',
        2 => 'ident',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithDisableSymKeys_b431c2cf',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DISABLE_SYM',
        1 => 'KEYS',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithEnableSymKeys_bcc7f3d8',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ENABLE_SYM',
        1 => 'KEYS',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithAlterOptColumnIdentSetSymDefaultSymSignedLiteralOrNull_33ecdb89',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'opt_column',
        2 => 'ident',
        3 => 'SET_SYM',
        4 => 'DEFAULT_SYM',
        5 => 'signed_literal_or_null',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithAlterOptColumnIdentSetSymDefaultSymExpr_8fc735e2',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'opt_column',
        2 => 'ident',
        3 => 'SET_SYM',
        4 => 'DEFAULT_SYM',
        5 => '(',
        6 => 'expr',
        7 => ')',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithAlterOptColumnIdentDropDefaultSym_c44119d6',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'opt_column',
        2 => 'ident',
        3 => 'DROP',
        4 => 'DEFAULT_SYM',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithAlterOptColumnIdentSetSymVisibility_f36d509f',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'opt_column',
        2 => 'ident',
        3 => 'SET_SYM',
        4 => 'visibility',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithAlterIndexSymIdentVisibility_15733c27',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'INDEX_SYM',
        2 => 'ident',
        3 => 'visibility',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithAlterCheckSymIdentConstraintEnforcement_b69a8dad',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'CHECK_SYM',
        2 => 'ident',
        3 => 'constraint_enforcement',
      ),
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithAlterConstraintIdentConstraintEnforcement_d364439b',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'CONSTRAINT',
        2 => 'ident',
        3 => 'constraint_enforcement',
      ),
    ),
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithRenameOptToTableIdent_98cfa9c5',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'RENAME',
        1 => 'opt_to',
        2 => 'table_ident',
      ),
    ),
    21 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithRenameKeyOrIndexIdentToSymIdent_f1c1552b',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'RENAME',
        1 => 'key_or_index',
        2 => 'ident',
        3 => 'TO_SYM',
        4 => 'ident',
      ),
    ),
    22 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithRenameColumnSymIdentToSymIdent_5266d05e',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'RENAME',
        1 => 'COLUMN_SYM',
        2 => 'ident',
        3 => 'TO_SYM',
        4 => 'ident',
      ),
    ),
    23 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithConvertSymToSymCharacterSetCharsetNameOptCollate_1ce431b4',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'CONVERT_SYM',
        1 => 'TO_SYM',
        2 => 'character_set',
        3 => 'charset_name',
        4 => 'opt_collate',
      ),
    ),
    24 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithConvertSymToSymCharacterSetDefaultSymOptCollate_06ae9dec',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'CONVERT_SYM',
        1 => 'TO_SYM',
        2 => 'character_set',
        3 => 'DEFAULT_SYM',
        4 => 'opt_collate',
      ),
    ),
    25 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithForceSym_9ea5035d',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'FORCE_SYM',
      ),
    ),
    26 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithOrderSymByAlterOrderList_e8a51e39',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ORDER_SYM',
        1 => 'BY',
        2 => 'alter_order_list',
      ),
    ),
  ),
  'alter_commands_modifier' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_algorithm_option',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_lock_option',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'with_validation',
      ),
    ),
  ),
  'opt_index_lock_and_algorithm' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIndexLockAndAlgorithmWith_0d61006e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_lock_option',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_algorithm_option',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIndexLockAndAlgorithmWithAlterLockOptionAlterAlgorithmOption_acf18afc',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'alter_lock_option',
        1 => 'alter_algorithm_option',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIndexLockAndAlgorithmWithAlterAlgorithmOptionAlterLockOption_9ad3e7ab',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'alter_algorithm_option',
        1 => 'alter_lock_option',
      ),
    ),
  ),
  'alter_algorithm_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterAlgorithmOptionWithAlgorithmSymOptEqualAlterAlgorithmOptionValue_aa54780f',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ALGORITHM_SYM',
        1 => 'opt_equal',
        2 => 'alter_algorithm_option_value',
      ),
    ),
  ),
  'alter_algorithm_option_value' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterAlgorithmOptionValueWithDefaultSym_82f17609',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT_SYM',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
  ),
  'alter_lock_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterLockOptionWithLockSymOptEqualAlterLockOptionValue_a8dbe235',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'LOCK_SYM',
        1 => 'opt_equal',
        2 => 'alter_lock_option_value',
      ),
    ),
  ),
  'alter_lock_option_value' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterLockOptionValueWithDefaultSym_684e967f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT_SYM',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
  ),
  'opt_column' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptColumnChoice_eefdbfac::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptColumnChoice_eefdbfac::UseColumn_83a8e21d',
      'symbols' =>
      array (
        0 => 'COLUMN_SYM',
      ),
    ),
  ),
  'opt_ignore' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptIgnoreChoice_928aa92e::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptIgnoreChoice_928aa92e::UseIgnore_eff4f8c3',
      'symbols' =>
      array (
        0 => 'IGNORE_SYM',
      ),
    ),
  ),
  'opt_restrict' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptRestrictChoice_e1e7cf73::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptRestrictChoice_e1e7cf73::UseRestrict_bd7a04e6',
      'symbols' =>
      array (
        0 => 'RESTRICT',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptRestrictChoice_e1e7cf73::UseCascade_86844e57',
      'symbols' =>
      array (
        0 => 'CASCADE',
      ),
    ),
  ),
  'opt_place' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPlaceWith_2e02e1a1',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPlaceWithAfterSymIdent_afb909e0',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'AFTER_SYM',
        1 => 'ident',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPlaceWithFirstSym_f656c428',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'FIRST_SYM',
      ),
    ),
  ),
  'opt_to' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptToChoice_c11aeeda::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptToChoice_c11aeeda::UseTo_c3bd7d9e',
      'symbols' =>
      array (
        0 => 'TO_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptToChoice_c11aeeda::Use_380918b9',
      'symbols' =>
      array (
        0 => 'EQ',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptToChoice_c11aeeda::UseAs_de148153',
      'symbols' =>
      array (
        0 => 'AS',
      ),
    ),
  ),
  'group_replication' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GroupReplicationWithGroupReplicationStartOptGroupReplicationStartOptions_01ae7076',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'group_replication_start',
        1 => 'opt_group_replication_start_options',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GroupReplicationWithStopSymGroupReplication_7825fd32',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'STOP_SYM',
        1 => 'GROUP_REPLICATION',
      ),
    ),
  ),
  'group_replication_start' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\GroupReplicationStartChoice_9afef309::UseStartGroupReplication_dc944310',
      'symbols' =>
      array (
        0 => 'START_SYM',
        1 => 'GROUP_REPLICATION',
      ),
    ),
  ),
  'opt_group_replication_start_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptGroupReplicationStartOptionsWith_088a8271',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'group_replication_start_options',
      ),
    ),
  ),
  'group_replication_start_options' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'group_replication_start_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GroupReplicationStartOptionsWithGroupReplicationStartOptionsGroupReplicationStartOption_77c998f1',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'group_replication_start_options',
        1 => ',',
        2 => 'group_replication_start_option',
      ),
    ),
  ),
  'group_replication_start_option' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'group_replication_user',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'group_replication_password',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'group_replication_plugin_auth',
      ),
    ),
  ),
  'group_replication_user' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GroupReplicationUserWithUserEqTextStringSysNonewline_03ae1383',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'USER',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
  ),
  'group_replication_password' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GroupReplicationPasswordWithPasswordEqTextStringSysNonewline_078bd483',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
  ),
  'group_replication_plugin_auth' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GroupReplicationPluginAuthWithDefaultAuthSymEqTextStringSysNonewline_85be2a7e',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT_AUTH_SYM',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
  ),
  'replica' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ReplicaChoice_501eb360::UseSlave_32e9e993',
      'symbols' =>
      array (
        0 => 'SLAVE',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ReplicaChoice_501eb360::UseReplica_238146a8',
      'symbols' =>
      array (
        0 => 'REPLICA_SYM',
      ),
    ),
  ),
  'stop_replica_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StopReplicaStmtWithStopSymReplicaOptReplicaThreadOptionListOptChannel_e0cabbb7',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'STOP_SYM',
        1 => 'replica',
        2 => 'opt_replica_thread_option_list',
        3 => 'opt_channel',
      ),
    ),
  ),
  'start_replica_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StartReplicaStmtWithStartSymReplicaOptReplicaThreadOptionListOptReplicaUntilOptUserOptionOptPas_3425c67b',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 6,
        6 => 7,
        7 => 8,
      ),
      'symbols' =>
      array (
        0 => 'START_SYM',
        1 => 'replica',
        2 => 'opt_replica_thread_option_list',
        3 => 'opt_replica_until',
        4 => 'opt_user_option',
        5 => 'opt_password_option',
        6 => 'opt_default_auth_option',
        7 => 'opt_plugin_dir_option',
        8 => 'opt_channel',
      ),
    ),
  ),
  'start' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StartWithStartSymTransactionSymOptStartTransactionOptionList_b19284a3',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'START_SYM',
        1 => 'TRANSACTION_SYM',
        2 => 'opt_start_transaction_option_list',
      ),
    ),
  ),
  'opt_start_transaction_option_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptStartTransactionOptionListWith_b0cc86ff',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'start_transaction_option_list',
      ),
    ),
  ),
  'start_transaction_option_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'start_transaction_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StartTransactionOptionListWithStartTransactionOptionListStartTransactionOption_4e3f8832',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'start_transaction_option_list',
        1 => ',',
        2 => 'start_transaction_option',
      ),
    ),
  ),
  'start_transaction_option' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\StartTransactionOptionChoice_39ab0f54::UseWithConsistentSnapshot_4d167c75',
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'CONSISTENT_SYM',
        2 => 'SNAPSHOT_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\StartTransactionOptionChoice_39ab0f54::UseReadOnly_6628aa89',
      'symbols' =>
      array (
        0 => 'READ_SYM',
        1 => 'ONLY_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\StartTransactionOptionChoice_39ab0f54::UseReadWrite_4c96461f',
      'symbols' =>
      array (
        0 => 'READ_SYM',
        1 => 'WRITE_SYM',
      ),
    ),
  ),
  'opt_user_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptUserOptionWith_507d58b2',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptUserOptionWithUserEqTextStringSys_9b5ae451',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'USER',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys',
      ),
    ),
  ),
  'opt_password_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPasswordOptionWith_507bdcd6',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPasswordOptionWithPasswordEqTextStringSys_d7ce189b',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys',
      ),
    ),
  ),
  'opt_default_auth_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptDefaultAuthOptionWith_3187cd57',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptDefaultAuthOptionWithDefaultAuthSymEqTextStringSys_bcb6abf7',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT_AUTH_SYM',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys',
      ),
    ),
  ),
  'opt_plugin_dir_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPluginDirOptionWith_81c61449',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPluginDirOptionWithPluginDirSymEqTextStringSys_57ba0571',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'PLUGIN_DIR_SYM',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys',
      ),
    ),
  ),
  'opt_replica_thread_option_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptReplicaThreadOptionListWith_9077602d',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'replica_thread_option_list',
      ),
    ),
  ),
  'replica_thread_option_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'replica_thread_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ReplicaThreadOptionListWithReplicaThreadOptionListReplicaThreadOption_69ce4658',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'replica_thread_option_list',
        1 => ',',
        2 => 'replica_thread_option',
      ),
    ),
  ),
  'replica_thread_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ReplicaThreadOptionWithSqlThread_b8f1768a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SQL_THREAD',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ReplicaThreadOptionWithRelayThread_59b34e13',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RELAY_THREAD',
      ),
    ),
  ),
  'opt_replica_until' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptReplicaUntilWith_ba57b29a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptReplicaUntilWithUntilSymReplicaUntil_bc9391b0',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'UNTIL_SYM',
        1 => 'replica_until',
      ),
    ),
  ),
  'replica_until' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'source_file_def',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ReplicaUntilWithReplicaUntilSourceFileDef_f6bd55eb',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'replica_until',
        1 => ',',
        2 => 'source_file_def',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ReplicaUntilWithSqlBeforeGtidsEqTextStringSys_74ef071c',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SQL_BEFORE_GTIDS',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ReplicaUntilWithSqlAfterGtidsEqTextStringSys_8c49785f',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SQL_AFTER_GTIDS',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ReplicaUntilWithSqlAfterMtsGaps_fac916b3',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SQL_AFTER_MTS_GAPS',
      ),
    ),
  ),
  'checksum' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ChecksumWithChecksumSymTableOrTablesTableListOptChecksumType_a3067bb2',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'CHECKSUM_SYM',
        1 => 'table_or_tables',
        2 => 'table_list',
        3 => 'opt_checksum_type',
      ),
    ),
  ),
  'opt_checksum_type' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptChecksumTypeChoice_7ff41d7d::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptChecksumTypeChoice_7ff41d7d::UseQuick_e0273b60',
      'symbols' =>
      array (
        0 => 'QUICK',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptChecksumTypeChoice_7ff41d7d::UseExtended_9632d8ea',
      'symbols' =>
      array (
        0 => 'EXTENDED_SYM',
      ),
    ),
  ),
  'repair_table_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RepairTableStmtWithRepairOptNoWriteToBinlogTableOrTablesTableListOptMiRepairTypes_d04f9241',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'REPAIR',
        1 => 'opt_no_write_to_binlog',
        2 => 'table_or_tables',
        3 => 'table_list',
        4 => 'opt_mi_repair_types',
      ),
    ),
  ),
  'opt_mi_repair_types' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptMiRepairTypesWith_327e902e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'mi_repair_types',
      ),
    ),
  ),
  'mi_repair_types' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'mi_repair_type',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MiRepairTypesWithMiRepairTypesMiRepairType_ea14dd21',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'mi_repair_types',
        1 => 'mi_repair_type',
      ),
    ),
  ),
  'mi_repair_type' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\MiRepairTypeChoice_ddecba63::UseQuick_e0273b60',
      'symbols' =>
      array (
        0 => 'QUICK',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\MiRepairTypeChoice_ddecba63::UseExtended_9632d8ea',
      'symbols' =>
      array (
        0 => 'EXTENDED_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\MiRepairTypeChoice_ddecba63::UseUseFrm_2c59274b',
      'symbols' =>
      array (
        0 => 'USE_FRM',
      ),
    ),
  ),
  'analyze_table_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AnalyzeTableStmtWithAnalyzeSymOptNoWriteToBinlogTableOrTablesTableListOptHistogram_7ee2bab4',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'ANALYZE_SYM',
        1 => 'opt_no_write_to_binlog',
        2 => 'table_or_tables',
        3 => 'table_list',
        4 => 'opt_histogram',
      ),
    ),
  ),
  'opt_histogram_update_param' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptHistogramUpdateParamWith_ac8f5c85',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptHistogramUpdateParamWithWithNumBucketsSym_0a559c3e',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'NUM',
        2 => 'BUCKETS_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptHistogramUpdateParamWithUsingDataSymTextStringLiteral_da3bc236',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'USING',
        1 => 'DATA_SYM',
        2 => 'TEXT_STRING_literal',
      ),
    ),
  ),
  'opt_histogram' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptHistogramWith_ab787cfb',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptHistogramWithUpdateSymHistogramSymOnSymIdentStringListOptHistogramUpdateParam_1ccea86d',
      'fields' =>
      array (
        0 => 3,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'UPDATE_SYM',
        1 => 'HISTOGRAM_SYM',
        2 => 'ON_SYM',
        3 => 'ident_string_list',
        4 => 'opt_histogram_update_param',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptHistogramWithDropHistogramSymOnSymIdentStringList_ca8c7b8d',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'HISTOGRAM_SYM',
        2 => 'ON_SYM',
        3 => 'ident_string_list',
      ),
    ),
  ),
  'binlog_base64_event' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BinlogBase64EventWithBinlogSymTextStringSys_beff19b0',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'BINLOG_SYM',
        1 => 'TEXT_STRING_sys',
      ),
    ),
  ),
  'check_table_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CheckTableStmtWithCheckSymTableOrTablesTableListOptMiCheckTypes_0b43c975',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'CHECK_SYM',
        1 => 'table_or_tables',
        2 => 'table_list',
        3 => 'opt_mi_check_types',
      ),
    ),
  ),
  'opt_mi_check_types' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptMiCheckTypesWith_beb10558',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'mi_check_types',
      ),
    ),
  ),
  'mi_check_types' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'mi_check_type',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MiCheckTypesWithMiCheckTypeMiCheckTypes_322e3fb2',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'mi_check_type',
        1 => 'mi_check_types',
      ),
    ),
  ),
  'mi_check_type' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\MiCheckTypeChoice_147f9765::UseQuick_e0273b60',
      'symbols' =>
      array (
        0 => 'QUICK',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\MiCheckTypeChoice_147f9765::UseFast_8d5ebd1c',
      'symbols' =>
      array (
        0 => 'FAST_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\MiCheckTypeChoice_147f9765::UseMedium_7c48dd67',
      'symbols' =>
      array (
        0 => 'MEDIUM_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\MiCheckTypeChoice_147f9765::UseExtended_9632d8ea',
      'symbols' =>
      array (
        0 => 'EXTENDED_SYM',
      ),
    ),
    4 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\MiCheckTypeChoice_147f9765::UseChanged_b6f00f28',
      'symbols' =>
      array (
        0 => 'CHANGED',
      ),
    ),
    5 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\MiCheckTypeChoice_147f9765::UseForUpgrade_44aef177',
      'symbols' =>
      array (
        0 => 'FOR_SYM',
        1 => 'UPGRADE_SYM',
      ),
    ),
  ),
  'optimize_table_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptimizeTableStmtWithOptimizeOptNoWriteToBinlogTableOrTablesTableList_3ea338ec',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'OPTIMIZE',
        1 => 'opt_no_write_to_binlog',
        2 => 'table_or_tables',
        3 => 'table_list',
      ),
    ),
  ),
  'opt_no_write_to_binlog' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptNoWriteToBinlogChoice_834bd6a1::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptNoWriteToBinlogChoice_834bd6a1::UseNoWriteToBinlog_57640581',
      'symbols' =>
      array (
        0 => 'NO_WRITE_TO_BINLOG',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptNoWriteToBinlogChoice_834bd6a1::UseLocal_646c1937',
      'symbols' =>
      array (
        0 => 'LOCAL_SYM',
      ),
    ),
  ),
  'rename' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RenameWithRenameTableOrTablesTableToTableList_c1667a43',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'RENAME',
        1 => 'table_or_tables',
        2 => 'table_to_table_list',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RenameWithRenameUserRenameList_33d6cb0f',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'RENAME',
        1 => 'USER',
        2 => 'rename_list',
      ),
    ),
  ),
  'rename_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RenameListWithUserToSymUser_5c51183b',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'TO_SYM',
        2 => 'user',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RenameListWithRenameListUserToSymUser_a31d6295',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'rename_list',
        1 => ',',
        2 => 'user',
        3 => 'TO_SYM',
        4 => 'user',
      ),
    ),
  ),
  'table_to_table_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_to_table',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableToTableListWithTableToTableListTableToTable_b9e3a181',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_to_table_list',
        1 => ',',
        2 => 'table_to_table',
      ),
    ),
  ),
  'table_to_table' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableToTableWithTableIdentToSymTableIdent_3bc05998',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_ident',
        1 => 'TO_SYM',
        2 => 'table_ident',
      ),
    ),
  ),
  'keycache_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeycacheStmtWithCacheSymIndexSymKeycacheListInSymKeyCacheName_8396186c',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'CACHE_SYM',
        1 => 'INDEX_SYM',
        2 => 'keycache_list',
        3 => 'IN_SYM',
        4 => 'key_cache_name',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeycacheStmtWithCacheSymIndexSymTableIdentAdmPartitionOptCacheKeyListInSymKeyCacheName_2f4d3162',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
        3 => 6,
      ),
      'symbols' =>
      array (
        0 => 'CACHE_SYM',
        1 => 'INDEX_SYM',
        2 => 'table_ident',
        3 => 'adm_partition',
        4 => 'opt_cache_key_list',
        5 => 'IN_SYM',
        6 => 'key_cache_name',
      ),
    ),
  ),
  'keycache_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'assign_to_keycache',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeycacheListWithKeycacheListAssignToKeycache_05a8c82b',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'keycache_list',
        1 => ',',
        2 => 'assign_to_keycache',
      ),
    ),
  ),
  'assign_to_keycache' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AssignToKeycacheWithTableIdentOptCacheKeyList_49e4a0ee',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'table_ident',
        1 => 'opt_cache_key_list',
      ),
    ),
  ),
  'key_cache_name' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyCacheNameWithDefaultSym_04036be6',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT_SYM',
      ),
    ),
  ),
  'preload_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PreloadStmtWithLoadIndexSymIntoCacheSymTableIdentAdmPartitionOptCacheKeyListOptIgnoreLeave_86e78d1f',
      'fields' =>
      array (
        0 => 4,
        1 => 5,
        2 => 6,
        3 => 7,
      ),
      'symbols' =>
      array (
        0 => 'LOAD',
        1 => 'INDEX_SYM',
        2 => 'INTO',
        3 => 'CACHE_SYM',
        4 => 'table_ident',
        5 => 'adm_partition',
        6 => 'opt_cache_key_list',
        7 => 'opt_ignore_leaves',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PreloadStmtWithLoadIndexSymIntoCacheSymPreloadList_94de743a',
      'fields' =>
      array (
        0 => 4,
      ),
      'symbols' =>
      array (
        0 => 'LOAD',
        1 => 'INDEX_SYM',
        2 => 'INTO',
        3 => 'CACHE_SYM',
        4 => 'preload_list',
      ),
    ),
  ),
  'preload_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'preload_keys',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PreloadListWithPreloadListPreloadKeys_ed175b42',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'preload_list',
        1 => ',',
        2 => 'preload_keys',
      ),
    ),
  ),
  'preload_keys' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PreloadKeysWithTableIdentOptCacheKeyListOptIgnoreLeaves_e52fbc81',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_ident',
        1 => 'opt_cache_key_list',
        2 => 'opt_ignore_leaves',
      ),
    ),
  ),
  'adm_partition' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AdmPartitionWithPartitionSymAllOrAltPartNameList_9985e82c',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'PARTITION_SYM',
        1 => '(',
        2 => 'all_or_alt_part_name_list',
        3 => ')',
      ),
    ),
  ),
  'opt_cache_key_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptCacheKeyListWith_e0fbaebe',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptCacheKeyListWithKeyOrIndexOptKeyUsageList_6dbf58cb',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'key_or_index',
        1 => '(',
        2 => 'opt_key_usage_list',
        3 => ')',
      ),
    ),
  ),
  'opt_ignore_leaves' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptIgnoreLeavesChoice_fea420ae::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptIgnoreLeavesChoice_fea420ae::UseIgnoreLeaves_f61ccd79',
      'symbols' =>
      array (
        0 => 'IGNORE_SYM',
        1 => 'LEAVES',
      ),
    ),
  ),
  'select_stmt' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'query_expression',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectStmtWithQueryExpressionLockingClauseList_ae1da1e3',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'query_expression',
        1 => 'locking_clause_list',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'select_stmt_with_into',
      ),
    ),
  ),
  'select_stmt_with_into' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectStmtWithIntoWithSelectStmtWithInto_531e38fc',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'select_stmt_with_into',
        2 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectStmtWithIntoWithQueryExpressionIntoClause_a2e64bf6',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'query_expression',
        1 => 'into_clause',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectStmtWithIntoWithQueryExpressionIntoClauseLockingClauseList_0be943b9',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'query_expression',
        1 => 'into_clause',
        2 => 'locking_clause_list',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectStmtWithIntoWithQueryExpressionLockingClauseListIntoClause_693abe32',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'query_expression',
        1 => 'locking_clause_list',
        2 => 'into_clause',
      ),
    ),
  ),
  'query_expression' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryExpressionWithQueryExpressionBodyOptOrderClauseOptLimitClause_15bbdf22',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'query_expression_body',
        1 => 'opt_order_clause',
        2 => 'opt_limit_clause',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryExpressionWithWithClauseQueryExpressionBodyOptOrderClauseOptLimitClause_f3d9f9a0',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
      ),
      'symbols' =>
      array (
        0 => 'with_clause',
        1 => 'query_expression_body',
        2 => 'opt_order_clause',
        3 => 'opt_limit_clause',
      ),
    ),
  ),
  'query_expression_body' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'query_primary',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'query_expression_parens',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryExpressionBodyWithQueryExpressionBodyUnionSymUnionOptionQueryExpressionBody_24d25384',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'query_expression_body',
        1 => 'UNION_SYM',
        2 => 'union_option',
        3 => 'query_expression_body',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryExpressionBodyWithQueryExpressionBodyExceptSymUnionOptionQueryExpressionBody_3f0c837a',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'query_expression_body',
        1 => 'EXCEPT_SYM',
        2 => 'union_option',
        3 => 'query_expression_body',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryExpressionBodyWithQueryExpressionBodyIntersectSymUnionOptionQueryExpressionBody_50417a62',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'query_expression_body',
        1 => 'INTERSECT_SYM',
        2 => 'union_option',
        3 => 'query_expression_body',
      ),
    ),
  ),
  'query_expression_parens' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryExpressionParensWithQueryExpressionParens_830c0f99',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'query_expression_parens',
        2 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryExpressionParensWithQueryExpressionWithOptLockingClauses_23be58de',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'query_expression_with_opt_locking_clauses',
        2 => ')',
      ),
    ),
  ),
  'query_primary' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'query_specification',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_value_constructor',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'explicit_table',
      ),
    ),
  ),
  'query_specification' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QuerySpecificationWithSelectSymSelectOptionsSelectItemListIntoClauseOptFromClauseOptWhereClauseOp_2b8950f0',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 6,
        6 => 7,
        7 => 8,
      ),
      'symbols' =>
      array (
        0 => 'SELECT_SYM',
        1 => 'select_options',
        2 => 'select_item_list',
        3 => 'into_clause',
        4 => 'opt_from_clause',
        5 => 'opt_where_clause',
        6 => 'opt_group_clause',
        7 => 'opt_having_clause',
        8 => 'opt_window_clause',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QuerySpecificationWithSelectSymSelectOptionsSelectItemListOptFromClauseOptWhereClauseOptGroupClau_5368232c',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 6,
        6 => 7,
      ),
      'symbols' =>
      array (
        0 => 'SELECT_SYM',
        1 => 'select_options',
        2 => 'select_item_list',
        3 => 'opt_from_clause',
        4 => 'opt_where_clause',
        5 => 'opt_group_clause',
        6 => 'opt_having_clause',
        7 => 'opt_window_clause',
      ),
    ),
  ),
  'opt_from_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptFromClauseWith_8fd94b11',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'from_clause',
      ),
    ),
  ),
  'from_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FromClauseWithFromFromTables_b932a58c',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'FROM',
        1 => 'from_tables',
      ),
    ),
  ),
  'from_tables' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FromTablesWithDualSym_5154c0f5',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DUAL_SYM',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_reference_list',
      ),
    ),
  ),
  'table_reference_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_reference',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableReferenceListWithTableReferenceListTableReference_37c69dbc',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_reference_list',
        1 => ',',
        2 => 'table_reference',
      ),
    ),
  ),
  'table_value_constructor' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableValueConstructorWithValuesValuesRowList_51394bf1',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'VALUES',
        1 => 'values_row_list',
      ),
    ),
  ),
  'explicit_table' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExplicitTableWithTableSymTableIdent_2371bf4a',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'TABLE_SYM',
        1 => 'table_ident',
      ),
    ),
  ),
  'select_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectOptionsWith_9fcc1d40',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'select_option_list',
      ),
    ),
  ),
  'select_option_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectOptionListWithSelectOptionListSelectOption_f67334b1',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'select_option_list',
        1 => 'select_option',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'select_option',
      ),
    ),
  ),
  'select_option' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'query_spec_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectOptionWithSqlNoCacheSym_0dde9155',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SQL_NO_CACHE_SYM',
      ),
    ),
  ),
  'locking_clause_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LockingClauseListWithLockingClauseListLockingClause_f112f1b9',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'locking_clause_list',
        1 => 'locking_clause',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'locking_clause',
      ),
    ),
  ),
  'locking_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LockingClauseWithForSymLockStrengthOptLockedRowAction_46d3f361',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'FOR_SYM',
        1 => 'lock_strength',
        2 => 'opt_locked_row_action',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LockingClauseWithForSymLockStrengthTableLockingListOptLockedRowAction_1b573512',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'FOR_SYM',
        1 => 'lock_strength',
        2 => 'table_locking_list',
        3 => 'opt_locked_row_action',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LockingClauseWithLockSymInSymShareSymModeSym_02ee99f4',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'LOCK_SYM',
        1 => 'IN_SYM',
        2 => 'SHARE_SYM',
        3 => 'MODE_SYM',
      ),
    ),
  ),
  'lock_strength' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\LockStrengthChoice_3fc0a512::UseUpdate_6cb78ab1',
      'symbols' =>
      array (
        0 => 'UPDATE_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\LockStrengthChoice_3fc0a512::UseShare_0b060acf',
      'symbols' =>
      array (
        0 => 'SHARE_SYM',
      ),
    ),
  ),
  'table_locking_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableLockingListWithOfSymTableAliasRefList_f1c48c92',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'OF_SYM',
        1 => 'table_alias_ref_list',
      ),
    ),
  ),
  'opt_locked_row_action' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptLockedRowActionWith_bbf84edf',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'locked_row_action',
      ),
    ),
  ),
  'locked_row_action' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\LockedRowActionChoice_360323ad::UseSkipLocked_5ad391e2',
      'symbols' =>
      array (
        0 => 'SKIP_SYM',
        1 => 'LOCKED_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\LockedRowActionChoice_360323ad::UseNowait_3fa8f87a',
      'symbols' =>
      array (
        0 => 'NOWAIT_SYM',
      ),
    ),
  ),
  'select_item_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectItemListWithSelectItemListSelectItem_06beb76a',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'select_item_list',
        1 => ',',
        2 => 'select_item',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'select_item',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectItemListWith_2365e026',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => '*',
      ),
    ),
  ),
  'select_item' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_wild',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectItemWithExprSelectAlias_e90ac39c',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'select_alias',
      ),
    ),
  ),
  'select_alias' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectAliasWith_62eaaf4e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectAliasWithAsIdent_c8107983',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'AS',
        1 => 'ident',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectAliasWithAsTextStringValidated_0fcf0f1b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'AS',
        1 => 'TEXT_STRING_validated',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'TEXT_STRING_validated',
      ),
    ),
  ),
  'optional_braces' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptionalBracesChoice_f4fff975::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptionalBracesChoice_f4fff975::Use_e779214a',
      'symbols' =>
      array (
        0 => '(',
        1 => ')',
      ),
    ),
  ),
  'expr' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExprWithExprOrExpr_f24995de',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'or',
        2 => 'expr',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExprWithExprXorExpr_6b357729',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'XOR',
        2 => 'expr',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExprWithExprAndExpr_7c66b353',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'and',
        2 => 'expr',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExprWithNotSymExpr_61d2ec6a',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'NOT_SYM',
        1 => 'expr',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExprWithBoolPriIsTrueSym_ebfa55ca',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'bool_pri',
        1 => 'IS',
        2 => 'TRUE_SYM',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExprWithBoolPriIsNotTrueSym_71c5c304',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bool_pri',
        1 => 'IS',
        2 => 'not',
        3 => 'TRUE_SYM',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExprWithBoolPriIsFalseSym_eec27390',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'bool_pri',
        1 => 'IS',
        2 => 'FALSE_SYM',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExprWithBoolPriIsNotFalseSym_a88bcb10',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bool_pri',
        1 => 'IS',
        2 => 'not',
        3 => 'FALSE_SYM',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExprWithBoolPriIsUnknownSym_2deb6c81',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'bool_pri',
        1 => 'IS',
        2 => 'UNKNOWN_SYM',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExprWithBoolPriIsNotUnknownSym_f0b175c9',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bool_pri',
        1 => 'IS',
        2 => 'not',
        3 => 'UNKNOWN_SYM',
      ),
    ),
    10 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'bool_pri',
      ),
    ),
  ),
  'bool_pri' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BoolPriWithBoolPriIsNullSym_f56ead87',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'bool_pri',
        1 => 'IS',
        2 => 'NULL_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BoolPriWithBoolPriIsNotNullSym_232645ca',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bool_pri',
        1 => 'IS',
        2 => 'not',
        3 => 'NULL_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BoolPriWithBoolPriCompOpPredicate_1de7e569',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bool_pri',
        1 => 'comp_op',
        2 => 'predicate',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BoolPriWithBoolPriCompOpAllOrAnyTableSubquery_17ee7fe5',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
      ),
      'symbols' =>
      array (
        0 => 'bool_pri',
        1 => 'comp_op',
        2 => 'all_or_any',
        3 => 'table_subquery',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'predicate',
      ),
    ),
  ),
  'predicate' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PredicateWithBitExprInSymTableSubquery_7e45c094',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'IN_SYM',
        2 => 'table_subquery',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PredicateWithBitExprNotInSymTableSubquery_110a4ffb',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'not',
        2 => 'IN_SYM',
        3 => 'table_subquery',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PredicateWithBitExprInSymExpr_88828ec7',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'IN_SYM',
        2 => '(',
        3 => 'expr',
        4 => ')',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PredicateWithBitExprInSymExprExprList_1b9e18a4',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'IN_SYM',
        2 => '(',
        3 => 'expr',
        4 => ',',
        5 => 'expr_list',
        6 => ')',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PredicateWithBitExprNotInSymExpr_fb07a40f',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'not',
        2 => 'IN_SYM',
        3 => '(',
        4 => 'expr',
        5 => ')',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PredicateWithBitExprNotInSymExprExprList_2feb440c',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 4,
        3 => 6,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'not',
        2 => 'IN_SYM',
        3 => '(',
        4 => 'expr',
        5 => ',',
        6 => 'expr_list',
        7 => ')',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PredicateWithBitExprMemberSymOptOfSimpleExpr_03ad03f3',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'MEMBER_SYM',
        2 => 'opt_of',
        3 => '(',
        4 => 'simple_expr',
        5 => ')',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PredicateWithBitExprBetweenSymBitExprAndSymPredicate_263af825',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'BETWEEN_SYM',
        2 => 'bit_expr',
        3 => 'AND_SYM',
        4 => 'predicate',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PredicateWithBitExprNotBetweenSymBitExprAndSymPredicate_05866d1d',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'not',
        2 => 'BETWEEN_SYM',
        3 => 'bit_expr',
        4 => 'AND_SYM',
        5 => 'predicate',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PredicateWithBitExprSoundsSymLikeBitExpr_1aa4ab11',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'SOUNDS_SYM',
        2 => 'LIKE',
        3 => 'bit_expr',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PredicateWithBitExprLikeSimpleExpr_6d1607c9',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'LIKE',
        2 => 'simple_expr',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PredicateWithBitExprLikeSimpleExprEscapeSymSimpleExpr_95bd7cd4',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'LIKE',
        2 => 'simple_expr',
        3 => 'ESCAPE_SYM',
        4 => 'simple_expr',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PredicateWithBitExprNotLikeSimpleExpr_96dfb517',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'not',
        2 => 'LIKE',
        3 => 'simple_expr',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PredicateWithBitExprNotLikeSimpleExprEscapeSymSimpleExpr_3fac99d9',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'not',
        2 => 'LIKE',
        3 => 'simple_expr',
        4 => 'ESCAPE_SYM',
        5 => 'simple_expr',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PredicateWithBitExprRegexpBitExpr_8fdc7629',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'REGEXP',
        2 => 'bit_expr',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PredicateWithBitExprNotRegexpBitExpr_100c3f18',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'not',
        2 => 'REGEXP',
        3 => 'bit_expr',
      ),
    ),
    16 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'bit_expr',
      ),
    ),
  ),
  'opt_of' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptOfChoice_8c38c611::UseOf_270826d4',
      'symbols' =>
      array (
        0 => 'OF_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptOfChoice_8c38c611::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
  ),
  'bit_expr' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BitExprWithBitExprBitExpr_daf4ae21',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => '|',
        2 => 'bit_expr',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BitExprWithBitExprBitExpr_b1e18bb5',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => '&',
        2 => 'bit_expr',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BitExprWithBitExprShiftLeftBitExpr_defcdee1',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'SHIFT_LEFT',
        2 => 'bit_expr',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BitExprWithBitExprShiftRightBitExpr_4d58b578',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'SHIFT_RIGHT',
        2 => 'bit_expr',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BitExprWithBitExprBitExpr_18836827',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => '+',
        2 => 'bit_expr',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BitExprWithBitExprBitExpr_b739e7d8',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => '-',
        2 => 'bit_expr',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BitExprWithBitExprIntervalSymExprInterval_5c9e5b84',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => '+',
        2 => 'INTERVAL_SYM',
        3 => 'expr',
        4 => 'interval',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BitExprWithBitExprIntervalSymExprInterval_ecc4c379',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => '-',
        2 => 'INTERVAL_SYM',
        3 => 'expr',
        4 => 'interval',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BitExprWithBitExprBitExpr_b9da6182',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => '*',
        2 => 'bit_expr',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BitExprWithBitExprBitExpr_ee6728ce',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => '/',
        2 => 'bit_expr',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BitExprWithBitExprBitExpr_71e493b1',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => '%',
        2 => 'bit_expr',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BitExprWithBitExprDivSymBitExpr_a81ee65d',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'DIV_SYM',
        2 => 'bit_expr',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BitExprWithBitExprModSymBitExpr_d9ce4170',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'MOD_SYM',
        2 => 'bit_expr',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BitExprWithBitExprBitExpr_fa755e68',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => '^',
        2 => 'bit_expr',
      ),
    ),
    14 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'simple_expr',
      ),
    ),
  ),
  'or' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OrWithOrSym_ac6c8257',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'OR_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OrWithOr2Sym_919e11b0',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'OR2_SYM',
      ),
    ),
  ),
  'and' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\AndChoice_1c2990dc::UseAnd_bd972cc4',
      'symbols' =>
      array (
        0 => 'AND_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\AndChoice_1c2990dc::Use_73e7b6f8',
      'symbols' =>
      array (
        0 => 'AND_AND_SYM',
      ),
    ),
  ),
  'not' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NotWithNotSym_55a46618',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NOT_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NotWithNot2Sym_871a4e53',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NOT2_SYM',
      ),
    ),
  ),
  'not2' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\Not2With_2796fc28',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => '!',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\Not2WithNot2Sym_c635ff60',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NOT2_SYM',
      ),
    ),
  ),
  'comp_op' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CompOpWithEq_ecb3bae4',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'EQ',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CompOpWithEqualSym_66adce9b',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'EQUAL_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CompOpWithGe_4e73b570',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'GE',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CompOpWithGtSym_51ca05eb',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'GT_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CompOpWithLe_f82d4ddc',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'LE',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CompOpWithLt_f208e164',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'LT',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CompOpWithNe_8000993a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NE',
      ),
    ),
  ),
  'all_or_any' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AllOrAnyWithAll_9fca1259',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ALL',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AllOrAnyWithAnySym_dd47bed1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ANY_SYM',
      ),
    ),
  ),
  'simple_expr' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'simple_ident',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'function_call_keyword',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'function_call_nonkeyword',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'function_call_generic',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'function_call_conflict',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithSimpleExprCollateSymIdentOrText_e9fce836',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'simple_expr',
        1 => 'COLLATE_SYM',
        2 => 'ident_or_text',
      ),
    ),
    6 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'literal_or_null',
      ),
    ),
    7 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'param_marker',
      ),
    ),
    8 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'rvalue_system_or_user_variable',
      ),
    ),
    9 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'in_expression_user_variable_assignment',
      ),
    ),
    10 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'set_function_specification',
      ),
    ),
    11 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'window_func_call',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithSimpleExprOrOrSymSimpleExpr_5df05b72',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'simple_expr',
        1 => 'OR_OR_SYM',
        2 => 'simple_expr',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithSimpleExpr_9ba2b450',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '+',
        1 => 'simple_expr',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithSimpleExpr_deab3046',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '-',
        1 => 'simple_expr',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithSimpleExpr_038aa870',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '~',
        1 => 'simple_expr',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithNot2SimpleExpr_a2c52a89',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'not2',
        1 => 'simple_expr',
      ),
    ),
    17 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'row_subquery',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithExpr_1b0b055b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'expr',
        2 => ')',
      ),
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithExprExprList_00458b5a',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'expr',
        2 => ',',
        3 => 'expr_list',
        4 => ')',
      ),
    ),
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithRowSymExprExprList_9cbe36dd',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'ROW_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr_list',
        5 => ')',
      ),
    ),
    21 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithExistsTableSubquery_b60ca85e',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'EXISTS',
        1 => 'table_subquery',
      ),
    ),
    22 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithIdentExpr_d3ab69b2',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => '{',
        1 => 'ident',
        2 => 'expr',
        3 => '}',
      ),
    ),
    23 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithMatchIdentListArgAgainstBitExprFulltextOptions_15c55729',
      'fields' =>
      array (
        0 => 1,
        1 => 4,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'MATCH',
        1 => 'ident_list_arg',
        2 => 'AGAINST',
        3 => '(',
        4 => 'bit_expr',
        5 => 'fulltext_options',
        6 => ')',
      ),
    ),
    24 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithBinarySymSimpleExpr_5ee955b5',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'BINARY_SYM',
        1 => 'simple_expr',
      ),
    ),
    25 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithCastSymExprAsCastTypeOptArrayCast_c3c0a070',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'CAST_SYM',
        1 => '(',
        2 => 'expr',
        3 => 'AS',
        4 => 'cast_type',
        5 => 'opt_array_cast',
        6 => ')',
      ),
    ),
    26 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithCastSymExprAtSymLocalSymAsCastTypeOptArrayCast_61b8de7c',
      'fields' =>
      array (
        0 => 2,
        1 => 6,
        2 => 7,
      ),
      'symbols' =>
      array (
        0 => 'CAST_SYM',
        1 => '(',
        2 => 'expr',
        3 => 'AT_SYM',
        4 => 'LOCAL_SYM',
        5 => 'AS',
        6 => 'cast_type',
        7 => 'opt_array_cast',
        8 => ')',
      ),
    ),
    27 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithCastSymExprAtSymTimeSymZoneSymOptIntervalTextStringLiteralAsDatetimeSymType_ea5f6a60',
      'fields' =>
      array (
        0 => 2,
        1 => 6,
        2 => 7,
        3 => 10,
      ),
      'symbols' =>
      array (
        0 => 'CAST_SYM',
        1 => '(',
        2 => 'expr',
        3 => 'AT_SYM',
        4 => 'TIME_SYM',
        5 => 'ZONE_SYM',
        6 => 'opt_interval',
        7 => 'TEXT_STRING_literal',
        8 => 'AS',
        9 => 'DATETIME_SYM',
        10 => 'type_datetime_precision',
        11 => ')',
      ),
    ),
    28 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithCaseSymOptExprWhenListOptElseEnd_7ad29d8c',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'CASE_SYM',
        1 => 'opt_expr',
        2 => 'when_list',
        3 => 'opt_else',
        4 => 'END',
      ),
    ),
    29 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithConvertSymExprCastType_cc833c17',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'CONVERT_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'cast_type',
        5 => ')',
      ),
    ),
    30 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithConvertSymExprUsingCharsetName_d20847bf',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'CONVERT_SYM',
        1 => '(',
        2 => 'expr',
        3 => 'USING',
        4 => 'charset_name',
        5 => ')',
      ),
    ),
    31 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithDefaultSymSimpleIdent_8ef7b91d',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT_SYM',
        1 => '(',
        2 => 'simple_ident',
        3 => ')',
      ),
    ),
    32 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithValuesSimpleIdentNospvar_b477bc21',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'VALUES',
        1 => '(',
        2 => 'simple_ident_nospvar',
        3 => ')',
      ),
    ),
    33 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithIntervalSymExprIntervalExpr_b11621f6',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'INTERVAL_SYM',
        1 => 'expr',
        2 => 'interval',
        3 => '+',
        4 => 'expr',
      ),
    ),
    34 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithSimpleIdentJsonSeparatorSymTextStringLiteral_79d5e28c',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'simple_ident',
        1 => 'JSON_SEPARATOR_SYM',
        2 => 'TEXT_STRING_literal',
      ),
    ),
    35 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithSimpleIdentJsonUnquotedSeparatorSymTextStringLiteral_b297fedc',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'simple_ident',
        1 => 'JSON_UNQUOTED_SEPARATOR_SYM',
        2 => 'TEXT_STRING_literal',
      ),
    ),
  ),
  'opt_array_cast' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptArrayCastChoice_8fb6f935::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptArrayCastChoice_8fb6f935::UseArray_23a49b7b',
      'symbols' =>
      array (
        0 => 'ARRAY_SYM',
      ),
    ),
  ),
  'function_call_keyword' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithCharSymExprList_76f41ac4',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'CHAR_SYM',
        1 => '(',
        2 => 'expr_list',
        3 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithCharSymExprListUsingCharsetName_e72a0fee',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'CHAR_SYM',
        1 => '(',
        2 => 'expr_list',
        3 => 'USING',
        4 => 'charset_name',
        5 => ')',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithCurrentUserOptionalBraces_a1a8fb72',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'CURRENT_USER',
        1 => 'optional_braces',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithDateSymExpr_2fde8b16',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DATE_SYM',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithDaySymExpr_ae0f8900',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DAY_SYM',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithHourSymExpr_ae841a41',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'HOUR_SYM',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithInsertSymExprExprExprExpr_5e0eae99',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 6,
        3 => 8,
      ),
      'symbols' =>
      array (
        0 => 'INSERT_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ',',
        6 => 'expr',
        7 => ',',
        8 => 'expr',
        9 => ')',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithIntervalSymExprExpr_3410c0bf',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'INTERVAL_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ')',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithIntervalSymExprExprExprList_86ca9adf',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'INTERVAL_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ',',
        6 => 'expr_list',
        7 => ')',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithJsonValueSymSimpleExprTextLiteralOptReturningTypeOptOnEmptyOrError_5be209a3',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 5,
        3 => 6,
      ),
      'symbols' =>
      array (
        0 => 'JSON_VALUE_SYM',
        1 => '(',
        2 => 'simple_expr',
        3 => ',',
        4 => 'text_literal',
        5 => 'opt_returning_type',
        6 => 'opt_on_empty_or_error',
        7 => ')',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithLeftExprExpr_f6a83a05',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'LEFT',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ')',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithMinuteSymExpr_da407496',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MINUTE_SYM',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithMonthSymExpr_20aedfff',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MONTH_SYM',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithRightExprExpr_5bf69c9e',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'RIGHT',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ')',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithSecondSymExpr_3fee144c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SECOND_SYM',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithTimeSymExpr_ca5163ad',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'TIME_SYM',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithTimestampSymExpr_36207213',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'TIMESTAMP_SYM',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithTimestampSymExprExpr_fd197587',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'TIMESTAMP_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ')',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithTrimExpr_76e61e1f',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'TRIM',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithTrimLeadingExprFromExpr_e704b713',
      'fields' =>
      array (
        0 => 3,
        1 => 5,
      ),
      'symbols' =>
      array (
        0 => 'TRIM',
        1 => '(',
        2 => 'LEADING',
        3 => 'expr',
        4 => 'FROM',
        5 => 'expr',
        6 => ')',
      ),
    ),
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithTrimTrailingExprFromExpr_aceb3baa',
      'fields' =>
      array (
        0 => 3,
        1 => 5,
      ),
      'symbols' =>
      array (
        0 => 'TRIM',
        1 => '(',
        2 => 'TRAILING',
        3 => 'expr',
        4 => 'FROM',
        5 => 'expr',
        6 => ')',
      ),
    ),
    21 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithTrimBothExprFromExpr_6da20136',
      'fields' =>
      array (
        0 => 3,
        1 => 5,
      ),
      'symbols' =>
      array (
        0 => 'TRIM',
        1 => '(',
        2 => 'BOTH',
        3 => 'expr',
        4 => 'FROM',
        5 => 'expr',
        6 => ')',
      ),
    ),
    22 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithTrimLeadingFromExpr_18382d4e',
      'fields' =>
      array (
        0 => 4,
      ),
      'symbols' =>
      array (
        0 => 'TRIM',
        1 => '(',
        2 => 'LEADING',
        3 => 'FROM',
        4 => 'expr',
        5 => ')',
      ),
    ),
    23 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithTrimTrailingFromExpr_1515f78c',
      'fields' =>
      array (
        0 => 4,
      ),
      'symbols' =>
      array (
        0 => 'TRIM',
        1 => '(',
        2 => 'TRAILING',
        3 => 'FROM',
        4 => 'expr',
        5 => ')',
      ),
    ),
    24 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithTrimBothFromExpr_e1f66bfd',
      'fields' =>
      array (
        0 => 4,
      ),
      'symbols' =>
      array (
        0 => 'TRIM',
        1 => '(',
        2 => 'BOTH',
        3 => 'FROM',
        4 => 'expr',
        5 => ')',
      ),
    ),
    25 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithTrimExprFromExpr_05acd88a',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'TRIM',
        1 => '(',
        2 => 'expr',
        3 => 'FROM',
        4 => 'expr',
        5 => ')',
      ),
    ),
    26 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithUser_13390f6c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'USER',
        1 => '(',
        2 => ')',
      ),
    ),
    27 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithYearSymExpr_fba35336',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'YEAR_SYM',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
  ),
  'function_call_nonkeyword' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithAdddateSymExprExpr_9ef7492b',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'ADDDATE_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithAdddateSymExprIntervalSymExprInterval_0f0cc312',
      'fields' =>
      array (
        0 => 2,
        1 => 5,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'ADDDATE_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'INTERVAL_SYM',
        5 => 'expr',
        6 => 'interval',
        7 => ')',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithCurdateOptionalBraces_eea78213',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'CURDATE',
        1 => 'optional_braces',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithCurtimeFuncDatetimePrecision_98072d05',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'CURTIME',
        1 => 'func_datetime_precision',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithDateAddIntervalExprIntervalSymExprInterval_2f4c3258',
      'fields' =>
      array (
        0 => 2,
        1 => 5,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'DATE_ADD_INTERVAL',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'INTERVAL_SYM',
        5 => 'expr',
        6 => 'interval',
        7 => ')',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithDateSubIntervalExprIntervalSymExprInterval_6416a880',
      'fields' =>
      array (
        0 => 2,
        1 => 5,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'DATE_SUB_INTERVAL',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'INTERVAL_SYM',
        5 => 'expr',
        6 => 'interval',
        7 => ')',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithExtractSymIntervalFromExpr_612c8d23',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'EXTRACT_SYM',
        1 => '(',
        2 => 'interval',
        3 => 'FROM',
        4 => 'expr',
        5 => ')',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithGetFormatDateTimeTypeExpr_451c5d3d',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'GET_FORMAT',
        1 => '(',
        2 => 'date_time_type',
        3 => ',',
        4 => 'expr',
        5 => ')',
      ),
    ),
    8 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'now',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithPositionSymBitExprInSymExpr_ee8c0e87',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'POSITION_SYM',
        1 => '(',
        2 => 'bit_expr',
        3 => 'IN_SYM',
        4 => 'expr',
        5 => ')',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithSubdateSymExprExpr_425d9c14',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'SUBDATE_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ')',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithSubdateSymExprIntervalSymExprInterval_c6ba703f',
      'fields' =>
      array (
        0 => 2,
        1 => 5,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'SUBDATE_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'INTERVAL_SYM',
        5 => 'expr',
        6 => 'interval',
        7 => ')',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithSubstringExprExprExpr_bf7601f4',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
        3 => 6,
      ),
      'symbols' =>
      array (
        0 => 'SUBSTRING',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ',',
        6 => 'expr',
        7 => ')',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithSubstringExprExpr_4fe7f0cd',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'SUBSTRING',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ')',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithSubstringExprFromExprForSymExpr_b73b9cfe',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
        3 => 6,
      ),
      'symbols' =>
      array (
        0 => 'SUBSTRING',
        1 => '(',
        2 => 'expr',
        3 => 'FROM',
        4 => 'expr',
        5 => 'FOR_SYM',
        6 => 'expr',
        7 => ')',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithSubstringExprFromExpr_e978d291',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'SUBSTRING',
        1 => '(',
        2 => 'expr',
        3 => 'FROM',
        4 => 'expr',
        5 => ')',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithSysdateFuncDatetimePrecision_969fafa7',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SYSDATE',
        1 => 'func_datetime_precision',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithTimestampAddIntervalTimeStampExprExpr_5a16341a',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'TIMESTAMP_ADD',
        1 => '(',
        2 => 'interval_time_stamp',
        3 => ',',
        4 => 'expr',
        5 => ',',
        6 => 'expr',
        7 => ')',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithTimestampDiffIntervalTimeStampExprExpr_26209d6f',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'TIMESTAMP_DIFF',
        1 => '(',
        2 => 'interval_time_stamp',
        3 => ',',
        4 => 'expr',
        5 => ',',
        6 => 'expr',
        7 => ')',
      ),
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithUtcDateSymOptionalBraces_582e9a02',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'UTC_DATE_SYM',
        1 => 'optional_braces',
      ),
    ),
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithUtcTimeSymFuncDatetimePrecision_3a89dc82',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'UTC_TIME_SYM',
        1 => 'func_datetime_precision',
      ),
    ),
    21 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallNonkeywordWithUtcTimestampSymFuncDatetimePrecision_75be19ac',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'UTC_TIMESTAMP_SYM',
        1 => 'func_datetime_precision',
      ),
    ),
  ),
  'opt_returning_type' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptReturningTypeWith_bf7f0435',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptReturningTypeWithReturningSymCastType_1589f2bb',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'RETURNING_SYM',
        1 => 'cast_type',
      ),
    ),
  ),
  'function_call_conflict' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithAsciiSymExpr_d4ee26af',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ASCII_SYM',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithCharsetExpr_29e8fbfd',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'CHARSET',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithCoalesceExprList_9a48db3a',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'COALESCE',
        1 => '(',
        2 => 'expr_list',
        3 => ')',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithCollationSymExpr_fb2c9206',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'COLLATION_SYM',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithDatabase_d9f2215d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DATABASE',
        1 => '(',
        2 => ')',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithIfExprExprExpr_9f5b9486',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'IF',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ',',
        6 => 'expr',
        7 => ')',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithFormatSymExprExpr_d7f20a68',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'FORMAT_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ')',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithFormatSymExprExprExpr_8292331a',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'FORMAT_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ',',
        6 => 'expr',
        7 => ')',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithMicrosecondSymExpr_36090e54',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MICROSECOND_SYM',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithModSymExprExpr_0cdf01b7',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'MOD_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ')',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithQuarterSymExpr_27ac48ac',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'QUARTER_SYM',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithRepeatSymExprExpr_713e0dbf',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'REPEAT_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ')',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithReplaceSymExprExprExpr_9ad70056',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'REPLACE_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ',',
        6 => 'expr',
        7 => ')',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithReverseSymExpr_fa07f2d9',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'REVERSE_SYM',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithRowCountSym_b1ab3878',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ROW_COUNT_SYM',
        1 => '(',
        2 => ')',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithTruncateSymExprExpr_d6f4c285',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'TRUNCATE_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ')',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithWeekSymExpr_0bbda77e',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'WEEK_SYM',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithWeekSymExprExpr_460a93d8',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'WEEK_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ')',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithWeightStringSymExpr_30f40bfa',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'WEIGHT_STRING_SYM',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithWeightStringSymExprAsCharSymWsNumCodepoints_3ab7b46c',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'WEIGHT_STRING_SYM',
        1 => '(',
        2 => 'expr',
        3 => 'AS',
        4 => 'CHAR_SYM',
        5 => 'ws_num_codepoints',
        6 => ')',
      ),
    ),
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithWeightStringSymExprAsBinarySymWsNumCodepoints_53392b92',
      'fields' =>
      array (
        0 => 2,
        1 => 5,
      ),
      'symbols' =>
      array (
        0 => 'WEIGHT_STRING_SYM',
        1 => '(',
        2 => 'expr',
        3 => 'AS',
        4 => 'BINARY_SYM',
        5 => 'ws_num_codepoints',
        6 => ')',
      ),
    ),
    21 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithWeightStringSymExprUlongNumUlongNumUlongNum_4729d338',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 6,
        3 => 8,
      ),
      'symbols' =>
      array (
        0 => 'WEIGHT_STRING_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'ulong_num',
        5 => ',',
        6 => 'ulong_num',
        7 => ',',
        8 => 'ulong_num',
        9 => ')',
      ),
    ),
    22 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'geometry_function',
      ),
    ),
  ),
  'geometry_function' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GeometryFunctionWithGeometrycollectionSymOptExprList_9b37789e',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'GEOMETRYCOLLECTION_SYM',
        1 => '(',
        2 => 'opt_expr_list',
        3 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GeometryFunctionWithLinestringSymExprList_3dbb4e60',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'LINESTRING_SYM',
        1 => '(',
        2 => 'expr_list',
        3 => ')',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GeometryFunctionWithMultilinestringSymExprList_e364a46a',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MULTILINESTRING_SYM',
        1 => '(',
        2 => 'expr_list',
        3 => ')',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GeometryFunctionWithMultipointSymExprList_eed67769',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MULTIPOINT_SYM',
        1 => '(',
        2 => 'expr_list',
        3 => ')',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GeometryFunctionWithMultipolygonSymExprList_e6ca5f3a',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MULTIPOLYGON_SYM',
        1 => '(',
        2 => 'expr_list',
        3 => ')',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GeometryFunctionWithPointSymExprExpr_1914f353',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'POINT_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ')',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GeometryFunctionWithPolygonSymExprList_ef8cb975',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'POLYGON_SYM',
        1 => '(',
        2 => 'expr_list',
        3 => ')',
      ),
    ),
  ),
  'function_call_generic' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallGenericWithIdentSysOptUdfExprList_ec153c03',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'IDENT_sys',
        1 => '(',
        2 => 'opt_udf_expr_list',
        3 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallGenericWithIdentIdentOptExprList_9b04de61',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => '.',
        2 => 'ident',
        3 => '(',
        4 => 'opt_expr_list',
        5 => ')',
      ),
    ),
  ),
  'fulltext_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FulltextOptionsWithOptNaturalLanguageModeOptQueryExpansion_ad246ebc',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'opt_natural_language_mode',
        1 => 'opt_query_expansion',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FulltextOptionsWithInSymBooleanSymModeSym_7ade1ced',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'IN_SYM',
        1 => 'BOOLEAN_SYM',
        2 => 'MODE_SYM',
      ),
    ),
  ),
  'opt_natural_language_mode' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptNaturalLanguageModeChoice_6931c211::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptNaturalLanguageModeChoice_6931c211::UseInNaturalLanguageMode_cdff8eb0',
      'symbols' =>
      array (
        0 => 'IN_SYM',
        1 => 'NATURAL',
        2 => 'LANGUAGE_SYM',
        3 => 'MODE_SYM',
      ),
    ),
  ),
  'opt_query_expansion' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptQueryExpansionChoice_59a9bd59::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptQueryExpansionChoice_59a9bd59::UseWithQueryExpansion_3dd27063',
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'QUERY_SYM',
        2 => 'EXPANSION_SYM',
      ),
    ),
  ),
  'opt_udf_expr_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptUdfExprListWith_34c655f9',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'udf_expr_list',
      ),
    ),
  ),
  'udf_expr_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'udf_expr',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UdfExprListWithUdfExprListUdfExpr_948cbced',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'udf_expr_list',
        1 => ',',
        2 => 'udf_expr',
      ),
    ),
  ),
  'udf_expr' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UdfExprWithExprSelectAlias_56a2fa1f',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'select_alias',
      ),
    ),
  ),
  'set_function_specification' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sum_expr',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'grouping_operation',
      ),
    ),
  ),
  'sum_expr' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithAvgSymInSumExprOptWindowingClause_15a305a4',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'AVG_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
        4 => 'opt_windowing_clause',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithAvgSymDistinctInSumExprOptWindowingClause_087cedb8',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'AVG_SYM',
        1 => '(',
        2 => 'DISTINCT',
        3 => 'in_sum_expr',
        4 => ')',
        5 => 'opt_windowing_clause',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithBitAndSymInSumExprOptWindowingClause_62f1e6d2',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'BIT_AND_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
        4 => 'opt_windowing_clause',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithBitOrSymInSumExprOptWindowingClause_a36aa5db',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'BIT_OR_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
        4 => 'opt_windowing_clause',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithJsonArrayaggInSumExprOptWindowingClause_1d83d334',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'JSON_ARRAYAGG',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
        4 => 'opt_windowing_clause',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithJsonObjectaggInSumExprInSumExprOptWindowingClause_9e798b41',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'JSON_OBJECTAGG',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ',',
        4 => 'in_sum_expr',
        5 => ')',
        6 => 'opt_windowing_clause',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithStCollectSymInSumExprOptWindowingClause_f6ff78cb',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'ST_COLLECT_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
        4 => 'opt_windowing_clause',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithStCollectSymDistinctInSumExprOptWindowingClause_25227720',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'ST_COLLECT_SYM',
        1 => '(',
        2 => 'DISTINCT',
        3 => 'in_sum_expr',
        4 => ')',
        5 => 'opt_windowing_clause',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithBitXorSymInSumExprOptWindowingClause_27c652f9',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'BIT_XOR_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
        4 => 'opt_windowing_clause',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithCountSymOptAllOptWindowingClause_23a36c19',
      'fields' =>
      array (
        0 => 2,
        1 => 5,
      ),
      'symbols' =>
      array (
        0 => 'COUNT_SYM',
        1 => '(',
        2 => 'opt_all',
        3 => '*',
        4 => ')',
        5 => 'opt_windowing_clause',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithCountSymInSumExprOptWindowingClause_e88244f4',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'COUNT_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
        4 => 'opt_windowing_clause',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithCountSymDistinctExprListOptWindowingClause_ed59e55c',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'COUNT_SYM',
        1 => '(',
        2 => 'DISTINCT',
        3 => 'expr_list',
        4 => ')',
        5 => 'opt_windowing_clause',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithMinSymInSumExprOptWindowingClause_9d1ede7d',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'MIN_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
        4 => 'opt_windowing_clause',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithMinSymDistinctInSumExprOptWindowingClause_c8ecca43',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'MIN_SYM',
        1 => '(',
        2 => 'DISTINCT',
        3 => 'in_sum_expr',
        4 => ')',
        5 => 'opt_windowing_clause',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithMaxSymInSumExprOptWindowingClause_9ec94b1b',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'MAX_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
        4 => 'opt_windowing_clause',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithMaxSymDistinctInSumExprOptWindowingClause_6821fbec',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'MAX_SYM',
        1 => '(',
        2 => 'DISTINCT',
        3 => 'in_sum_expr',
        4 => ')',
        5 => 'opt_windowing_clause',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithStdSymInSumExprOptWindowingClause_a6257b6a',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'STD_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
        4 => 'opt_windowing_clause',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithVarianceSymInSumExprOptWindowingClause_d9296098',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'VARIANCE_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
        4 => 'opt_windowing_clause',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithStddevSampSymInSumExprOptWindowingClause_ef25735e',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'STDDEV_SAMP_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
        4 => 'opt_windowing_clause',
      ),
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithVarSampSymInSumExprOptWindowingClause_8d4dd41f',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'VAR_SAMP_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
        4 => 'opt_windowing_clause',
      ),
    ),
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithSumSymInSumExprOptWindowingClause_03ca4a3e',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'SUM_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
        4 => 'opt_windowing_clause',
      ),
    ),
    21 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithSumSymDistinctInSumExprOptWindowingClause_776d1237',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'SUM_SYM',
        1 => '(',
        2 => 'DISTINCT',
        3 => 'in_sum_expr',
        4 => ')',
        5 => 'opt_windowing_clause',
      ),
    ),
    22 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithGroupConcatSymOptDistinctExprListOptGorderClauseOptGconcatSeparatorOptWindo_d95d5089',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
        3 => 5,
        4 => 7,
      ),
      'symbols' =>
      array (
        0 => 'GROUP_CONCAT_SYM',
        1 => '(',
        2 => 'opt_distinct',
        3 => 'expr_list',
        4 => 'opt_gorder_clause',
        5 => 'opt_gconcat_separator',
        6 => ')',
        7 => 'opt_windowing_clause',
      ),
    ),
  ),
  'window_func_call' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFuncCallWithRowNumberSymWindowingClause_e98270d7',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ROW_NUMBER_SYM',
        1 => '(',
        2 => ')',
        3 => 'windowing_clause',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFuncCallWithRankSymWindowingClause_9a72e614',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'RANK_SYM',
        1 => '(',
        2 => ')',
        3 => 'windowing_clause',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFuncCallWithDenseRankSymWindowingClause_70d7a6d6',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DENSE_RANK_SYM',
        1 => '(',
        2 => ')',
        3 => 'windowing_clause',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFuncCallWithCumeDistSymWindowingClause_6e1e24b7',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'CUME_DIST_SYM',
        1 => '(',
        2 => ')',
        3 => 'windowing_clause',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFuncCallWithPercentRankSymWindowingClause_f265194a',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'PERCENT_RANK_SYM',
        1 => '(',
        2 => ')',
        3 => 'windowing_clause',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFuncCallWithNtileSymStableIntegerWindowingClause_ec55b4c2',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'NTILE_SYM',
        1 => '(',
        2 => 'stable_integer',
        3 => ')',
        4 => 'windowing_clause',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFuncCallWithLeadSymExprOptLeadLagInfoOptNullTreatmentWindowingClause_45806aca',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 5,
        3 => 6,
      ),
      'symbols' =>
      array (
        0 => 'LEAD_SYM',
        1 => '(',
        2 => 'expr',
        3 => 'opt_lead_lag_info',
        4 => ')',
        5 => 'opt_null_treatment',
        6 => 'windowing_clause',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFuncCallWithLagSymExprOptLeadLagInfoOptNullTreatmentWindowingClause_e6591106',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 5,
        3 => 6,
      ),
      'symbols' =>
      array (
        0 => 'LAG_SYM',
        1 => '(',
        2 => 'expr',
        3 => 'opt_lead_lag_info',
        4 => ')',
        5 => 'opt_null_treatment',
        6 => 'windowing_clause',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFuncCallWithFirstValueSymExprOptNullTreatmentWindowingClause_416b6d35',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'FIRST_VALUE_SYM',
        1 => '(',
        2 => 'expr',
        3 => ')',
        4 => 'opt_null_treatment',
        5 => 'windowing_clause',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFuncCallWithLastValueSymExprOptNullTreatmentWindowingClause_6136c771',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'LAST_VALUE_SYM',
        1 => '(',
        2 => 'expr',
        3 => ')',
        4 => 'opt_null_treatment',
        5 => 'windowing_clause',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFuncCallWithNthValueSymExprSimpleExprOptFromFirstLastOptNullTreatmentWindowingClause_5a83eb1e',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 6,
        3 => 7,
        4 => 8,
      ),
      'symbols' =>
      array (
        0 => 'NTH_VALUE_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'simple_expr',
        5 => ')',
        6 => 'opt_from_first_last',
        7 => 'opt_null_treatment',
        8 => 'windowing_clause',
      ),
    ),
  ),
  'opt_lead_lag_info' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptLeadLagInfoWith_a537d1fb',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptLeadLagInfoWithStableIntegerOptLlDefault_e83cba7b',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => ',',
        1 => 'stable_integer',
        2 => 'opt_ll_default',
      ),
    ),
  ),
  'stable_integer' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'int64_literal',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'param_or_var',
      ),
    ),
  ),
  'param_or_var' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'param_marker',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ParamOrVarWithIdentOrText_e54473af',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '@',
        1 => 'ident_or_text',
      ),
    ),
  ),
  'opt_ll_default' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptLlDefaultWith_05299b5f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptLlDefaultWithExpr_485243cd',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => ',',
        1 => 'expr',
      ),
    ),
  ),
  'opt_null_treatment' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptNullTreatmentChoice_a717e62c::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptNullTreatmentChoice_a717e62c::UseRespectNulls_a4010503',
      'symbols' =>
      array (
        0 => 'RESPECT_SYM',
        1 => 'NULLS_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptNullTreatmentChoice_a717e62c::UseIgnoreNulls_91b4e4c0',
      'symbols' =>
      array (
        0 => 'IGNORE_SYM',
        1 => 'NULLS_SYM',
      ),
    ),
  ),
  'opt_from_first_last' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptFromFirstLastChoice_3c171937::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptFromFirstLastChoice_3c171937::UseFromFirst_f44b294e',
      'symbols' =>
      array (
        0 => 'FROM',
        1 => 'FIRST_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptFromFirstLastChoice_3c171937::UseFromLast_0fb07b44',
      'symbols' =>
      array (
        0 => 'FROM',
        1 => 'LAST_SYM',
      ),
    ),
  ),
  'opt_windowing_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptWindowingClauseWith_34dc97c1',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'windowing_clause',
      ),
    ),
  ),
  'windowing_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowingClauseWithOverSymWindowNameOrSpec_3ecf488b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'OVER_SYM',
        1 => 'window_name_or_spec',
      ),
    ),
  ),
  'window_name_or_spec' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'window_name',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'window_spec',
      ),
    ),
  ),
  'window_name' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
  ),
  'window_spec' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowSpecWithWindowSpecDetails_f95bb1be',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'window_spec_details',
        2 => ')',
      ),
    ),
  ),
  'window_spec_details' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowSpecDetailsWithOptExistingWindowNameOptPartitionClauseOptWindowOrderByClauseOptWindowFrame_4a73fee5',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
      ),
      'symbols' =>
      array (
        0 => 'opt_existing_window_name',
        1 => 'opt_partition_clause',
        2 => 'opt_window_order_by_clause',
        3 => 'opt_window_frame_clause',
      ),
    ),
  ),
  'opt_existing_window_name' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptExistingWindowNameWith_57667184',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'window_name',
      ),
    ),
  ),
  'opt_partition_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPartitionClauseWith_0b054f34',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPartitionClauseWithPartitionSymByGroupList_b012b67f',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'PARTITION_SYM',
        1 => 'BY',
        2 => 'group_list',
      ),
    ),
  ),
  'opt_window_order_by_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptWindowOrderByClauseWith_6751f94c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptWindowOrderByClauseWithOrderSymByOrderList_2a457d1a',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ORDER_SYM',
        1 => 'BY',
        2 => 'order_list',
      ),
    ),
  ),
  'opt_window_frame_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptWindowFrameClauseWith_9202d50b',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptWindowFrameClauseWithWindowFrameUnitsWindowFrameExtentOptWindowFrameExclusion_293a5fc6',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'window_frame_units',
        1 => 'window_frame_extent',
        2 => 'opt_window_frame_exclusion',
      ),
    ),
  ),
  'window_frame_extent' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'window_frame_start',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'window_frame_between',
      ),
    ),
  ),
  'window_frame_start' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFrameStartWithUnboundedSymPrecedingSym_3ba59478',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'UNBOUNDED_SYM',
        1 => 'PRECEDING_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFrameStartWithNumLiteralPrecedingSym_a0630982',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NUM_literal',
        1 => 'PRECEDING_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFrameStartWithParamMarkerPrecedingSym_4773f42b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'param_marker',
        1 => 'PRECEDING_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFrameStartWithIntervalSymExprIntervalPrecedingSym_0809b4f8',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'INTERVAL_SYM',
        1 => 'expr',
        2 => 'interval',
        3 => 'PRECEDING_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFrameStartWithCurrentSymRowSym_6877dda5',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'CURRENT_SYM',
        1 => 'ROW_SYM',
      ),
    ),
  ),
  'window_frame_between' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFrameBetweenWithBetweenSymWindowFrameBoundAndSymWindowFrameBound_ae4f992f',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'BETWEEN_SYM',
        1 => 'window_frame_bound',
        2 => 'AND_SYM',
        3 => 'window_frame_bound',
      ),
    ),
  ),
  'window_frame_bound' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'window_frame_start',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFrameBoundWithUnboundedSymFollowingSym_3431dd62',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'UNBOUNDED_SYM',
        1 => 'FOLLOWING_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFrameBoundWithNumLiteralFollowingSym_59045146',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NUM_literal',
        1 => 'FOLLOWING_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFrameBoundWithParamMarkerFollowingSym_16b3a356',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'param_marker',
        1 => 'FOLLOWING_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowFrameBoundWithIntervalSymExprIntervalFollowingSym_3e3315a7',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'INTERVAL_SYM',
        1 => 'expr',
        2 => 'interval',
        3 => 'FOLLOWING_SYM',
      ),
    ),
  ),
  'opt_window_frame_exclusion' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptWindowFrameExclusionChoice_0dcc2b2b::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptWindowFrameExclusionChoice_0dcc2b2b::UseExcludeCurrentRow_b1f83261',
      'symbols' =>
      array (
        0 => 'EXCLUDE_SYM',
        1 => 'CURRENT_SYM',
        2 => 'ROW_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptWindowFrameExclusionChoice_0dcc2b2b::UseExcludeGroup_1e1e3216',
      'symbols' =>
      array (
        0 => 'EXCLUDE_SYM',
        1 => 'GROUP_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptWindowFrameExclusionChoice_0dcc2b2b::UseExcludeTies_a062fa84',
      'symbols' =>
      array (
        0 => 'EXCLUDE_SYM',
        1 => 'TIES_SYM',
      ),
    ),
    4 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptWindowFrameExclusionChoice_0dcc2b2b::UseExcludeNoOthers_b122de28',
      'symbols' =>
      array (
        0 => 'EXCLUDE_SYM',
        1 => 'NO_SYM',
        2 => 'OTHERS_SYM',
      ),
    ),
  ),
  'window_frame_units' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\WindowFrameUnitsChoice_95f00093::UseRows_6d2b98cb',
      'symbols' =>
      array (
        0 => 'ROWS_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\WindowFrameUnitsChoice_95f00093::UseRange_69da9691',
      'symbols' =>
      array (
        0 => 'RANGE_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\WindowFrameUnitsChoice_95f00093::UseGroups_1a84bba2',
      'symbols' =>
      array (
        0 => 'GROUPS_SYM',
      ),
    ),
  ),
  'grouping_operation' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GroupingOperationWithGroupingSymExprList_5f24fb42',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'GROUPING_SYM',
        1 => '(',
        2 => 'expr_list',
        3 => ')',
      ),
    ),
  ),
  'in_expression_user_variable_assignment' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InExpressionUserVariableAssignmentWithIdentOrTextSetVarExpr_35c1d0bd',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => '@',
        1 => 'ident_or_text',
        2 => 'SET_VAR',
        3 => 'expr',
      ),
    ),
  ),
  'rvalue_system_or_user_variable' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RvalueSystemOrUserVariableWithIdentOrText_a9759b52',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '@',
        1 => 'ident_or_text',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RvalueSystemOrUserVariableWithOptRvalueSystemVariableTypeRvalueSystemVariable_42195e71',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => '@',
        1 => '@',
        2 => 'opt_rvalue_system_variable_type',
        3 => 'rvalue_system_variable',
      ),
    ),
  ),
  'opt_distinct' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptDistinctWith_9445df40',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptDistinctWithDistinct_8c7a3067',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DISTINCT',
      ),
    ),
  ),
  'opt_gconcat_separator' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptGconcatSeparatorWith_31902c7e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptGconcatSeparatorWithSeparatorSymTextString_7e35b28a',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SEPARATOR_SYM',
        1 => 'text_string',
      ),
    ),
  ),
  'opt_gorder_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptGorderClauseWith_201ea8c0',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptGorderClauseWithOrderSymByGorderList_d22a02f9',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ORDER_SYM',
        1 => 'BY',
        2 => 'gorder_list',
      ),
    ),
  ),
  'gorder_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GorderListWithGorderListOrderExpr_dfb08b0b',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'gorder_list',
        1 => ',',
        2 => 'order_expr',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'order_expr',
      ),
    ),
  ),
  'in_sum_expr' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InSumExprWithOptAllExpr_da377783',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'opt_all',
        1 => 'expr',
      ),
    ),
  ),
  'cast_type' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithBinarySymOptFieldLength_f84c874f',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'BINARY_SYM',
        1 => 'opt_field_length',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithCharSymOptFieldLengthOptCharsetWithOptBinary_06423c31',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'CHAR_SYM',
        1 => 'opt_field_length',
        2 => 'opt_charset_with_opt_binary',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithNcharOptFieldLength_73d65b7f',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'nchar',
        1 => 'opt_field_length',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithSignedSym_7b8b0b9b',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SIGNED_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithSignedSymIntSym_23e335d7',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SIGNED_SYM',
        1 => 'INT_SYM',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithUnsignedSym_99c201a1',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'UNSIGNED_SYM',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithUnsignedSymIntSym_7863c6f9',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'UNSIGNED_SYM',
        1 => 'INT_SYM',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithDateSym_45e9babb',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DATE_SYM',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithYearSym_06ddeb54',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'YEAR_SYM',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithTimeSymTypeDatetimePrecision_d121e480',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'TIME_SYM',
        1 => 'type_datetime_precision',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithDatetimeSymTypeDatetimePrecision_50928055',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DATETIME_SYM',
        1 => 'type_datetime_precision',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithDecimalSymFloatOptions_7b45e7c9',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DECIMAL_SYM',
        1 => 'float_options',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithJsonSym_9816f4d7',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'JSON_SYM',
      ),
    ),
    13 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'real_type',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithFloatSymStandardFloatOptions_f35b5072',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'FLOAT_SYM',
        1 => 'standard_float_options',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithPointSym_442d07b6',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'POINT_SYM',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithLinestringSym_94226fec',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'LINESTRING_SYM',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithPolygonSym_b1472b50',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'POLYGON_SYM',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithMultipointSym_b8f117c5',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'MULTIPOINT_SYM',
      ),
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithMultilinestringSym_e7a35789',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'MULTILINESTRING_SYM',
      ),
    ),
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithMultipolygonSym_c3aafe9f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'MULTIPOLYGON_SYM',
      ),
    ),
    21 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithGeometrycollectionSym_9341b564',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'GEOMETRYCOLLECTION_SYM',
      ),
    ),
  ),
  'opt_expr_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptExprListWith_50cd0077',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'expr_list',
      ),
    ),
  ),
  'expr_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'expr',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExprListWithExprListExpr_5c9083b9',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'expr_list',
        1 => ',',
        2 => 'expr',
      ),
    ),
  ),
  'ident_list_arg' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_list',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentListArgWithIdentList_4ebbfd25',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'ident_list',
        2 => ')',
      ),
    ),
  ),
  'ident_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'simple_ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentListWithIdentListSimpleIdent_15b3083f',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ident_list',
        1 => ',',
        2 => 'simple_ident',
      ),
    ),
  ),
  'opt_expr' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptExprWith_501d59a0',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'expr',
      ),
    ),
  ),
  'opt_else' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptElseWith_e7c5cc88',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptElseWithElseExpr_6f2c8117',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ELSE',
        1 => 'expr',
      ),
    ),
  ),
  'when_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WhenListWithWhenSymExprThenSymExpr_dc2e3408',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'WHEN_SYM',
        1 => 'expr',
        2 => 'THEN_SYM',
        3 => 'expr',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WhenListWithWhenListWhenSymExprThenSymExpr_96d28342',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'when_list',
        1 => 'WHEN_SYM',
        2 => 'expr',
        3 => 'THEN_SYM',
        4 => 'expr',
      ),
    ),
  ),
  'table_reference' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_factor',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'joined_table',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableReferenceWithOjSymEscTableReference_4e68ad4f',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => '{',
        1 => 'OJ_SYM',
        2 => 'esc_table_reference',
        3 => '}',
      ),
    ),
  ),
  'esc_table_reference' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_factor',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'joined_table',
      ),
    ),
  ),
  'joined_table' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JoinedTableWithTableReferenceInnerJoinTypeTableReferenceOnSymExpr_0d75e6f0',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'table_reference',
        1 => 'inner_join_type',
        2 => 'table_reference',
        3 => 'ON_SYM',
        4 => 'expr',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JoinedTableWithTableReferenceInnerJoinTypeTableReferenceUsingUsingList_55e39a12',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'table_reference',
        1 => 'inner_join_type',
        2 => 'table_reference',
        3 => 'USING',
        4 => '(',
        5 => 'using_list',
        6 => ')',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JoinedTableWithTableReferenceOuterJoinTypeTableReferenceOnSymExpr_5b822d86',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'table_reference',
        1 => 'outer_join_type',
        2 => 'table_reference',
        3 => 'ON_SYM',
        4 => 'expr',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JoinedTableWithTableReferenceOuterJoinTypeTableReferenceUsingUsingList_1b92fe2e',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'table_reference',
        1 => 'outer_join_type',
        2 => 'table_reference',
        3 => 'USING',
        4 => '(',
        5 => 'using_list',
        6 => ')',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JoinedTableWithTableReferenceInnerJoinTypeTableReference_3b0aad3f',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_reference',
        1 => 'inner_join_type',
        2 => 'table_reference',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JoinedTableWithTableReferenceNaturalJoinTypeTableFactor_e7df9b6f',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_reference',
        1 => 'natural_join_type',
        2 => 'table_factor',
      ),
    ),
  ),
  'natural_join_type' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NaturalJoinTypeWithNaturalOptInnerJoinSym_f7f6245d',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'NATURAL',
        1 => 'opt_inner',
        2 => 'JOIN_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NaturalJoinTypeWithNaturalRightOptOuterJoinSym_424a027a',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'NATURAL',
        1 => 'RIGHT',
        2 => 'opt_outer',
        3 => 'JOIN_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NaturalJoinTypeWithNaturalLeftOptOuterJoinSym_90db9b14',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'NATURAL',
        1 => 'LEFT',
        2 => 'opt_outer',
        3 => 'JOIN_SYM',
      ),
    ),
  ),
  'inner_join_type' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\InnerJoinTypeChoice_32963d08::UseJoin_a9e153ee',
      'symbols' =>
      array (
        0 => 'JOIN_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\InnerJoinTypeChoice_32963d08::UseInnerJoin_98b2b2a0',
      'symbols' =>
      array (
        0 => 'INNER_SYM',
        1 => 'JOIN_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\InnerJoinTypeChoice_32963d08::UseCrossJoin_82443ed3',
      'symbols' =>
      array (
        0 => 'CROSS',
        1 => 'JOIN_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\InnerJoinTypeChoice_32963d08::UseStraightJoin_880c4b53',
      'symbols' =>
      array (
        0 => 'STRAIGHT_JOIN',
      ),
    ),
  ),
  'outer_join_type' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OuterJoinTypeWithLeftOptOuterJoinSym_21d24cd3',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LEFT',
        1 => 'opt_outer',
        2 => 'JOIN_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OuterJoinTypeWithRightOptOuterJoinSym_baeebe08',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'RIGHT',
        1 => 'opt_outer',
        2 => 'JOIN_SYM',
      ),
    ),
  ),
  'opt_inner' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptInnerChoice_bae3c2bb::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptInnerChoice_bae3c2bb::UseInner_67f54b7b',
      'symbols' =>
      array (
        0 => 'INNER_SYM',
      ),
    ),
  ),
  'opt_outer' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptOuterChoice_86cb63c7::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptOuterChoice_86cb63c7::UseOuter_2c635ca0',
      'symbols' =>
      array (
        0 => 'OUTER_SYM',
      ),
    ),
  ),
  'opt_use_partition' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptUsePartitionWith_ddfeed1f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'use_partition',
      ),
    ),
  ),
  'use_partition' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UsePartitionWithPartitionSymUsingList_9af060bb',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'PARTITION_SYM',
        1 => '(',
        2 => 'using_list',
        3 => ')',
      ),
    ),
  ),
  'table_factor' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'single_table',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'single_table_parens',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'derived_table',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'joined_table_parens',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_reference_list_parens',
      ),
    ),
    5 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_function',
      ),
    ),
  ),
  'table_reference_list_parens' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableReferenceListParensWithTableReferenceListParens_aadf1d5e',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'table_reference_list_parens',
        2 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableReferenceListParensWithTableReferenceListTableReference_534e0163',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'table_reference_list',
        2 => ',',
        3 => 'table_reference',
        4 => ')',
      ),
    ),
  ),
  'single_table_parens' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SingleTableParensWithSingleTableParens_e2406c1c',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'single_table_parens',
        2 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SingleTableParensWithSingleTable_8e667bbf',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'single_table',
        2 => ')',
      ),
    ),
  ),
  'single_table' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SingleTableWithTableIdentOptUsePartitionOptTableAliasOptKeyDefinition_e33dd17f',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
      ),
      'symbols' =>
      array (
        0 => 'table_ident',
        1 => 'opt_use_partition',
        2 => 'opt_table_alias',
        3 => 'opt_key_definition',
      ),
    ),
  ),
  'joined_table_parens' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JoinedTableParensWithJoinedTableParens_9988d5c7',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'joined_table_parens',
        2 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JoinedTableParensWithJoinedTable_3e5d167d',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'joined_table',
        2 => ')',
      ),
    ),
  ),
  'derived_table' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DerivedTableWithTableSubqueryOptTableAliasOptDerivedColumnList_52c62ad2',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_subquery',
        1 => 'opt_table_alias',
        2 => 'opt_derived_column_list',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DerivedTableWithLateralSymTableSubqueryOptTableAliasOptDerivedColumnList_01106007',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'LATERAL_SYM',
        1 => 'table_subquery',
        2 => 'opt_table_alias',
        3 => 'opt_derived_column_list',
      ),
    ),
  ),
  'table_function' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableFunctionWithJsonTableSymExprTextLiteralColumnsClauseOptTableAlias_3caa7f67',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 5,
        3 => 7,
      ),
      'symbols' =>
      array (
        0 => 'JSON_TABLE_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'text_literal',
        5 => 'columns_clause',
        6 => ')',
        7 => 'opt_table_alias',
      ),
    ),
  ),
  'columns_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnsClauseWithColumnsColumnsList_8226384e',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'COLUMNS',
        1 => '(',
        2 => 'columns_list',
        3 => ')',
      ),
    ),
  ),
  'columns_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'jt_column',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnsListWithColumnsListJtColumn_8107f4d4',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'columns_list',
        1 => ',',
        2 => 'jt_column',
      ),
    ),
  ),
  'jt_column' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JtColumnWithIdentForSymOrdinalitySym_323ae7fa',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => 'FOR_SYM',
        2 => 'ORDINALITY_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JtColumnWithIdentTypeOptCollateJtColumnTypePathSymTextLiteralOptOnEmptyOrErrorJsonTable_08c038c0',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
        4 => 5,
        5 => 6,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => 'type',
        2 => 'opt_collate',
        3 => 'jt_column_type',
        4 => 'PATH_SYM',
        5 => 'text_literal',
        6 => 'opt_on_empty_or_error_json_table',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JtColumnWithNestedSymPathSymTextLiteralColumnsClause_09e28380',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'NESTED_SYM',
        1 => 'PATH_SYM',
        2 => 'text_literal',
        3 => 'columns_clause',
      ),
    ),
  ),
  'jt_column_type' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\JtColumnTypeChoice_5bb8e3c9::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\JtColumnTypeChoice_5bb8e3c9::UseExists_b1ce3865',
      'symbols' =>
      array (
        0 => 'EXISTS',
      ),
    ),
  ),
  'opt_on_empty_or_error' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptOnEmptyOrErrorWith_2911654c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'on_empty',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'on_error',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptOnEmptyOrErrorWithOnEmptyOnError_5ebe1740',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'on_empty',
        1 => 'on_error',
      ),
    ),
  ),
  'opt_on_empty_or_error_json_table' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_on_empty_or_error',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptOnEmptyOrErrorJsonTableWithOnErrorOnEmpty_ac84fcb7',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'on_error',
        1 => 'on_empty',
      ),
    ),
  ),
  'on_empty' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OnEmptyWithJsonOnResponseOnSymEmptySym_c3c091ca',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'json_on_response',
        1 => 'ON_SYM',
        2 => 'EMPTY_SYM',
      ),
    ),
  ),
  'on_error' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OnErrorWithJsonOnResponseOnSymErrorSym_c6455f38',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'json_on_response',
        1 => 'ON_SYM',
        2 => 'ERROR_SYM',
      ),
    ),
  ),
  'json_on_response' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JsonOnResponseWithErrorSym_f1139502',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ERROR_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JsonOnResponseWithNullSym_a695ec00',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NULL_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JsonOnResponseWithDefaultSymSignedLiteral_30ad843d',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT_SYM',
        1 => 'signed_literal',
      ),
    ),
  ),
  'index_hint_clause' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\IndexHintClauseChoice_6b9790b3::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\IndexHintClauseChoice_6b9790b3::UseForJoin_eda154a2',
      'symbols' =>
      array (
        0 => 'FOR_SYM',
        1 => 'JOIN_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\IndexHintClauseChoice_6b9790b3::UseForOrderBy_1ac1df62',
      'symbols' =>
      array (
        0 => 'FOR_SYM',
        1 => 'ORDER_SYM',
        2 => 'BY',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\IndexHintClauseChoice_6b9790b3::UseForGroupBy_a6916070',
      'symbols' =>
      array (
        0 => 'FOR_SYM',
        1 => 'GROUP_SYM',
        2 => 'BY',
      ),
    ),
  ),
  'index_hint_type' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\IndexHintTypeChoice_3dcfc7c9::UseForce_bd16a503',
      'symbols' =>
      array (
        0 => 'FORCE_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\IndexHintTypeChoice_3dcfc7c9::UseIgnore_eff4f8c3',
      'symbols' =>
      array (
        0 => 'IGNORE_SYM',
      ),
    ),
  ),
  'index_hint_definition' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IndexHintDefinitionWithIndexHintTypeKeyOrIndexIndexHintClauseKeyUsageList_d0ca86e4',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'index_hint_type',
        1 => 'key_or_index',
        2 => 'index_hint_clause',
        3 => '(',
        4 => 'key_usage_list',
        5 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IndexHintDefinitionWithUseSymKeyOrIndexIndexHintClauseOptKeyUsageList_39427321',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'USE_SYM',
        1 => 'key_or_index',
        2 => 'index_hint_clause',
        3 => '(',
        4 => 'opt_key_usage_list',
        5 => ')',
      ),
    ),
  ),
  'index_hints_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'index_hint_definition',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IndexHintsListWithIndexHintsListIndexHintDefinition_fbca760e',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'index_hints_list',
        1 => 'index_hint_definition',
      ),
    ),
  ),
  'opt_index_hints_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIndexHintsListWith_7359f8d5',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'index_hints_list',
      ),
    ),
  ),
  'opt_key_definition' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_index_hints_list',
      ),
    ),
  ),
  'opt_key_usage_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptKeyUsageListWith_995e4d72',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'key_usage_list',
      ),
    ),
  ),
  'key_usage_element' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyUsageElementWithPrimarySym_a0d40477',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'PRIMARY_SYM',
      ),
    ),
  ),
  'key_usage_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'key_usage_element',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyUsageListWithKeyUsageListKeyUsageElement_076ee135',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'key_usage_list',
        1 => ',',
        2 => 'key_usage_element',
      ),
    ),
  ),
  'using_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_string_list',
      ),
    ),
  ),
  'ident_string_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentStringListWithIdentStringListIdent_3ba96746',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ident_string_list',
        1 => ',',
        2 => 'ident',
      ),
    ),
  ),
  'interval' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'interval_time_stamp',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntervalWithDayHourSym_7b2c3856',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DAY_HOUR_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntervalWithDayMicrosecondSym_582fa3d6',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DAY_MICROSECOND_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntervalWithDayMinuteSym_6e30bffe',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DAY_MINUTE_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntervalWithDaySecondSym_6854ca86',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DAY_SECOND_SYM',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntervalWithHourMicrosecondSym_0a008adc',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'HOUR_MICROSECOND_SYM',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntervalWithHourMinuteSym_b366c05f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'HOUR_MINUTE_SYM',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntervalWithHourSecondSym_e129e63b',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'HOUR_SECOND_SYM',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntervalWithMinuteMicrosecondSym_3dd1e0e1',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'MINUTE_MICROSECOND_SYM',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntervalWithMinuteSecondSym_1063a560',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'MINUTE_SECOND_SYM',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntervalWithSecondMicrosecondSym_f953ad23',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SECOND_MICROSECOND_SYM',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntervalWithYearMonthSym_af0d52fd',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'YEAR_MONTH_SYM',
      ),
    ),
  ),
  'interval_time_stamp' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntervalTimeStampWithDaySym_3f8add4e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DAY_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntervalTimeStampWithWeekSym_5886f3a9',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'WEEK_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntervalTimeStampWithHourSym_e7ecf372',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HOUR_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntervalTimeStampWithMinuteSym_387ad70b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MINUTE_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntervalTimeStampWithMonthSym_8ff04241',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MONTH_SYM',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntervalTimeStampWithQuarterSym_d1df0b3d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'QUARTER_SYM',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntervalTimeStampWithSecondSym_3d0b0f01',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SECOND_SYM',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntervalTimeStampWithMicrosecondSym_58b176ef',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'MICROSECOND_SYM',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntervalTimeStampWithYearSym_06e4d61b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'YEAR_SYM',
      ),
    ),
  ),
  'date_time_type' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\DateTimeTypeChoice_349d02a6::UseDate_17f9a0a5',
      'symbols' =>
      array (
        0 => 'DATE_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\DateTimeTypeChoice_349d02a6::UseTime_5888675d',
      'symbols' =>
      array (
        0 => 'TIME_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\DateTimeTypeChoice_349d02a6::UseTimestamp_80c1f032',
      'symbols' =>
      array (
        0 => 'TIMESTAMP_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\DateTimeTypeChoice_349d02a6::UseDatetime_107291fd',
      'symbols' =>
      array (
        0 => 'DATETIME_SYM',
      ),
    ),
  ),
  'opt_as' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptAsChoice_88c85c7d::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptAsChoice_88c85c7d::UseAs_de148153',
      'symbols' =>
      array (
        0 => 'AS',
      ),
    ),
  ),
  'opt_table_alias' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptTableAliasWith_aaa72a4b',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptTableAliasWithOptAsIdent_35f95dae',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'opt_as',
        1 => 'ident',
      ),
    ),
  ),
  'opt_all' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptAllChoice_66c44b99::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptAllChoice_66c44b99::UseAll_b5c7aed7',
      'symbols' =>
      array (
        0 => 'ALL',
      ),
    ),
  ),
  'opt_where_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptWhereClauseWith_3a245f59',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'where_clause',
      ),
    ),
  ),
  'where_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WhereClauseWithWhereExpr_6672a183',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'WHERE',
        1 => 'expr',
      ),
    ),
  ),
  'opt_having_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptHavingClauseWith_05f3ee11',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptHavingClauseWithHavingExpr_2930858d',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'HAVING',
        1 => 'expr',
      ),
    ),
  ),
  'with_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WithClauseWithWithWithList_12ddc17a',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'with_list',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WithClauseWithWithRecursiveSymWithList_167bb8db',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'RECURSIVE_SYM',
        2 => 'with_list',
      ),
    ),
  ),
  'with_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WithListWithWithListCommonTableExpr_018fb771',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'with_list',
        1 => ',',
        2 => 'common_table_expr',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'common_table_expr',
      ),
    ),
  ),
  'common_table_expr' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CommonTableExprWithIdentOptDerivedColumnListAsTableSubquery_42edd512',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => 'opt_derived_column_list',
        2 => 'AS',
        3 => 'table_subquery',
      ),
    ),
  ),
  'opt_derived_column_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptDerivedColumnListWith_8eb86b4d',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptDerivedColumnListWithSimpleIdentList_6998144f',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'simple_ident_list',
        2 => ')',
      ),
    ),
  ),
  'simple_ident_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleIdentListWithSimpleIdentListIdent_3d2555f0',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'simple_ident_list',
        1 => ',',
        2 => 'ident',
      ),
    ),
  ),
  'opt_window_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptWindowClauseWith_c2916614',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptWindowClauseWithWindowSymWindowDefinitionList_a250ef3d',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'WINDOW_SYM',
        1 => 'window_definition_list',
      ),
    ),
  ),
  'window_definition_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'window_definition',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowDefinitionListWithWindowDefinitionListWindowDefinition_e74c196c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'window_definition_list',
        1 => ',',
        2 => 'window_definition',
      ),
    ),
  ),
  'window_definition' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WindowDefinitionWithWindowNameAsWindowSpec_51fa306c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'window_name',
        1 => 'AS',
        2 => 'window_spec',
      ),
    ),
  ),
  'opt_group_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptGroupClauseWith_d436d84f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptGroupClauseWithGroupSymByGroupListOlapOpt_71c99020',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'GROUP_SYM',
        1 => 'BY',
        2 => 'group_list',
        3 => 'olap_opt',
      ),
    ),
  ),
  'group_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GroupListWithGroupListGroupingExpr_98395124',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'group_list',
        1 => ',',
        2 => 'grouping_expr',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'grouping_expr',
      ),
    ),
  ),
  'olap_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OlapOptWith_891ddc53',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OlapOptWithWithRollupSym_6aee56b6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'WITH_ROLLUP_SYM',
      ),
    ),
  ),
  'alter_order_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterOrderListWithAlterOrderListAlterOrderItem_3f858882',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'alter_order_list',
        1 => ',',
        2 => 'alter_order_item',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_order_item',
      ),
    ),
  ),
  'alter_order_item' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterOrderItemWithSimpleIdentNospvarOptOrderingDirection_6e2c5dd4',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'simple_ident_nospvar',
        1 => 'opt_ordering_direction',
      ),
    ),
  ),
  'opt_order_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptOrderClauseWith_be547984',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'order_clause',
      ),
    ),
  ),
  'order_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OrderClauseWithOrderSymByOrderList_494184af',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ORDER_SYM',
        1 => 'BY',
        2 => 'order_list',
      ),
    ),
  ),
  'order_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OrderListWithOrderListOrderExpr_57c6cce7',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'order_list',
        1 => ',',
        2 => 'order_expr',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'order_expr',
      ),
    ),
  ),
  'opt_ordering_direction' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptOrderingDirectionWith_523f1773',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ordering_direction',
      ),
    ),
  ),
  'ordering_direction' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OrderingDirectionChoice_63bee526::UseAsc_323b087e',
      'symbols' =>
      array (
        0 => 'ASC',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OrderingDirectionChoice_63bee526::UseDesc_984da4fe',
      'symbols' =>
      array (
        0 => 'DESC',
      ),
    ),
  ),
  'opt_limit_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptLimitClauseWith_65d11c7a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'limit_clause',
      ),
    ),
  ),
  'limit_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LimitClauseWithLimitLimitOptions_7cedcf7b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LIMIT',
        1 => 'limit_options',
      ),
    ),
  ),
  'limit_options' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'limit_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LimitOptionsWithLimitOptionLimitOption_b6fb42bf',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'limit_option',
        1 => ',',
        2 => 'limit_option',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LimitOptionsWithLimitOptionOffsetSymLimitOption_2254dcb3',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'limit_option',
        1 => 'OFFSET_SYM',
        2 => 'limit_option',
      ),
    ),
  ),
  'limit_option' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'param_marker',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LimitOptionWithUlonglongNum_fa7e6b0b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ULONGLONG_NUM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LimitOptionWithLongNum_19100e33',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LONG_NUM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LimitOptionWithNum_a6c80c64',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NUM',
      ),
    ),
  ),
  'opt_simple_limit' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSimpleLimitWith_280f9886',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSimpleLimitWithLimitLimitOption_6ec3b90b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LIMIT',
        1 => 'limit_option',
      ),
    ),
  ),
  'ulong_num' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UlongNumWithNum_b0045ec3',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NUM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UlongNumWithHexNum_e036cef7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HEX_NUM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UlongNumWithLongNum_21c49506',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LONG_NUM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UlongNumWithUlonglongNum_6eb47d62',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ULONGLONG_NUM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UlongNumWithDecimalNum_46190ac1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DECIMAL_NUM',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UlongNumWithFloatNum_4227aac1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FLOAT_NUM',
      ),
    ),
  ),
  'real_ulong_num' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RealUlongNumWithNum_783a9f56',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NUM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RealUlongNumWithHexNum_724c7b44',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HEX_NUM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RealUlongNumWithLongNum_c736136e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LONG_NUM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RealUlongNumWithUlonglongNum_2da8b3ef',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ULONGLONG_NUM',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'dec_num_error',
      ),
    ),
  ),
  'ulonglong_num' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UlonglongNumWithNum_79b48558',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NUM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UlonglongNumWithUlonglongNum_e743eda0',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ULONGLONG_NUM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UlonglongNumWithLongNum_ece42aa4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LONG_NUM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UlonglongNumWithDecimalNum_5d406503',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DECIMAL_NUM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UlonglongNumWithFloatNum_e512c6f2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FLOAT_NUM',
      ),
    ),
  ),
  'real_ulonglong_num' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RealUlonglongNumWithNum_6036e0a6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NUM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RealUlonglongNumWithHexNum_5d3de79c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HEX_NUM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RealUlonglongNumWithUlonglongNum_6376b2bd',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ULONGLONG_NUM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RealUlonglongNumWithLongNum_183e573e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LONG_NUM',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'dec_num_error',
      ),
    ),
  ),
  'dec_num_error' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'dec_num',
      ),
    ),
  ),
  'dec_num' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DecNumWithDecimalNum_d2e949d6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DECIMAL_NUM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DecNumWithFloatNum_9c0d1538',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FLOAT_NUM',
      ),
    ),
  ),
  'select_var_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectVarListWithSelectVarListSelectVarIdent_195416a4',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'select_var_list',
        1 => ',',
        2 => 'select_var_ident',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'select_var_ident',
      ),
    ),
  ),
  'select_var_ident' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectVarIdentWithIdentOrText_25cdc367',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '@',
        1 => 'ident_or_text',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_or_text',
      ),
    ),
  ),
  'into_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntoClauseWithIntoIntoDestination_8d199e61',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'INTO',
        1 => 'into_destination',
      ),
    ),
  ),
  'into_destination' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntoDestinationWithOutfileTextStringFilesystemOptLoadDataCharsetOptFieldTermOptLineTerm_b8db7bd6',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'OUTFILE',
        1 => 'TEXT_STRING_filesystem',
        2 => 'opt_load_data_charset',
        3 => 'opt_field_term',
        4 => 'opt_line_term',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntoDestinationWithDumpfileTextStringFilesystem_3f14f32a',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DUMPFILE',
        1 => 'TEXT_STRING_filesystem',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'select_var_list',
      ),
    ),
  ),
  'do_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DoStmtWithDoSymSelectItemList_de67280f',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DO_SYM',
        1 => 'select_item_list',
      ),
    ),
  ),
  'drop_table_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropTableStmtWithDropOptTemporaryTableOrTablesIfExistsTableListOptRestrict_61d8e19f',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'opt_temporary',
        2 => 'table_or_tables',
        3 => 'if_exists',
        4 => 'table_list',
        5 => 'opt_restrict',
      ),
    ),
  ),
  'drop_index_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropIndexStmtWithDropIndexSymIdentOnSymTableIdentOptIndexLockAndAlgorithm_e8c96b85',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'INDEX_SYM',
        2 => 'ident',
        3 => 'ON_SYM',
        4 => 'table_ident',
        5 => 'opt_index_lock_and_algorithm',
      ),
    ),
  ),
  'drop_database_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropDatabaseStmtWithDropDatabaseIfExistsIdent_2f3a0924',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'DATABASE',
        2 => 'if_exists',
        3 => 'ident',
      ),
    ),
  ),
  'drop_function_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropFunctionStmtWithDropFunctionSymIfExistsIdentIdent_17b90fde',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'FUNCTION_SYM',
        2 => 'if_exists',
        3 => 'ident',
        4 => '.',
        5 => 'ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropFunctionStmtWithDropFunctionSymIfExistsIdent_a3060cd7',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'FUNCTION_SYM',
        2 => 'if_exists',
        3 => 'ident',
      ),
    ),
  ),
  'drop_resource_group_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropResourceGroupStmtWithDropResourceSymGroupSymIdentOptForce_2680bd98',
      'fields' =>
      array (
        0 => 3,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'RESOURCE_SYM',
        2 => 'GROUP_SYM',
        3 => 'ident',
        4 => 'opt_force',
      ),
    ),
  ),
  'drop_procedure_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropProcedureStmtWithDropProcedureSymIfExistsSpName_b112eaee',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'PROCEDURE_SYM',
        2 => 'if_exists',
        3 => 'sp_name',
      ),
    ),
  ),
  'drop_user_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropUserStmtWithDropUserIfExistsUserList_46d75bb8',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'USER',
        2 => 'if_exists',
        3 => 'user_list',
      ),
    ),
  ),
  'drop_view_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropViewStmtWithDropViewSymIfExistsTableListOptRestrict_53489655',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'VIEW_SYM',
        2 => 'if_exists',
        3 => 'table_list',
        4 => 'opt_restrict',
      ),
    ),
  ),
  'drop_event_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropEventStmtWithDropEventSymIfExistsSpName_72c7c929',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'EVENT_SYM',
        2 => 'if_exists',
        3 => 'sp_name',
      ),
    ),
  ),
  'drop_trigger_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropTriggerStmtWithDropTriggerSymIfExistsSpName_8f6750a7',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'TRIGGER_SYM',
        2 => 'if_exists',
        3 => 'sp_name',
      ),
    ),
  ),
  'drop_tablespace_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropTablespaceStmtWithDropTablespaceSymIdentOptDropTsOptions_fa99150b',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'TABLESPACE_SYM',
        2 => 'ident',
        3 => 'opt_drop_ts_options',
      ),
    ),
  ),
  'drop_undo_tablespace_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropUndoTablespaceStmtWithDropUndoSymTablespaceSymIdentOptUndoTablespaceOptions_896c4e31',
      'fields' =>
      array (
        0 => 3,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'UNDO_SYM',
        2 => 'TABLESPACE_SYM',
        3 => 'ident',
        4 => 'opt_undo_tablespace_options',
      ),
    ),
  ),
  'drop_logfile_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropLogfileStmtWithDropLogfileSymGroupSymIdentOptDropTsOptions_7af84ca1',
      'fields' =>
      array (
        0 => 3,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'LOGFILE_SYM',
        2 => 'GROUP_SYM',
        3 => 'ident',
        4 => 'opt_drop_ts_options',
      ),
    ),
  ),
  'drop_server_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropServerStmtWithDropServerSymIfExistsIdentOrText_2d7a7ad6',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'SERVER_SYM',
        2 => 'if_exists',
        3 => 'ident_or_text',
      ),
    ),
  ),
  'drop_srs_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropSrsStmtWithDropSpatialSymReferenceSymSystemSymIfExistsRealUlonglongNum_75bc399b',
      'fields' =>
      array (
        0 => 4,
        1 => 5,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'SPATIAL_SYM',
        2 => 'REFERENCE_SYM',
        3 => 'SYSTEM_SYM',
        4 => 'if_exists',
        5 => 'real_ulonglong_num',
      ),
    ),
  ),
  'drop_role_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropRoleStmtWithDropRoleSymIfExistsRoleList_e247f4a3',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'ROLE_SYM',
        2 => 'if_exists',
        3 => 'role_list',
      ),
    ),
  ),
  'table_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableListWithTableListTableIdent_07896ede',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_list',
        1 => ',',
        2 => 'table_ident',
      ),
    ),
  ),
  'table_alias_ref_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_ident_opt_wild',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableAliasRefListWithTableAliasRefListTableIdentOptWild_364e8f7a',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_alias_ref_list',
        1 => ',',
        2 => 'table_ident_opt_wild',
      ),
    ),
  ),
  'if_exists' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\IfExistsChoice_d99928b2::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\IfExistsChoice_d99928b2::UseIfExists_82cdccc7',
      'symbols' =>
      array (
        0 => 'IF',
        1 => 'EXISTS',
      ),
    ),
  ),
  'opt_ignore_unknown_user' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIgnoreUnknownUserWith_f452fc5f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIgnoreUnknownUserWithIgnoreSymUnknownSymUser_7983907d',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'IGNORE_SYM',
        1 => 'UNKNOWN_SYM',
        2 => 'USER',
      ),
    ),
  ),
  'opt_temporary' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptTemporaryChoice_10bf1b85::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptTemporaryChoice_10bf1b85::UseTemporary_cb28e366',
      'symbols' =>
      array (
        0 => 'TEMPORARY',
      ),
    ),
  ),
  'opt_drop_ts_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptDropTsOptionsWith_70230481',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'drop_ts_option_list',
      ),
    ),
  ),
  'drop_ts_option_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'drop_ts_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropTsOptionListWithDropTsOptionListOptCommaDropTsOption_8bd467b1',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'drop_ts_option_list',
        1 => 'opt_comma',
        2 => 'drop_ts_option',
      ),
    ),
  ),
  'drop_ts_option' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_engine',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_option_wait',
      ),
    ),
  ),
  'insert_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InsertStmtWithInsertSymInsertLockOptionOptIgnoreOptIntoTableIdentOptUsePartitionInsertFro_5460f971',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 6,
        6 => 7,
        7 => 8,
      ),
      'symbols' =>
      array (
        0 => 'INSERT_SYM',
        1 => 'insert_lock_option',
        2 => 'opt_ignore',
        3 => 'opt_INTO',
        4 => 'table_ident',
        5 => 'opt_use_partition',
        6 => 'insert_from_constructor',
        7 => 'opt_values_reference',
        8 => 'opt_insert_update_list',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InsertStmtWithInsertSymInsertLockOptionOptIgnoreOptIntoTableIdentOptUsePartitionSetSymUpd_c3e85d5a',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 7,
        6 => 8,
        7 => 9,
      ),
      'symbols' =>
      array (
        0 => 'INSERT_SYM',
        1 => 'insert_lock_option',
        2 => 'opt_ignore',
        3 => 'opt_INTO',
        4 => 'table_ident',
        5 => 'opt_use_partition',
        6 => 'SET_SYM',
        7 => 'update_list',
        8 => 'opt_values_reference',
        9 => 'opt_insert_update_list',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InsertStmtWithInsertSymInsertLockOptionOptIgnoreOptIntoTableIdentOptUsePartitionInsertQue_de6115f9',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 6,
        6 => 7,
      ),
      'symbols' =>
      array (
        0 => 'INSERT_SYM',
        1 => 'insert_lock_option',
        2 => 'opt_ignore',
        3 => 'opt_INTO',
        4 => 'table_ident',
        5 => 'opt_use_partition',
        6 => 'insert_query_expression',
        7 => 'opt_insert_update_list',
      ),
    ),
  ),
  'replace_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ReplaceStmtWithReplaceSymReplaceLockOptionOptIntoTableIdentOptUsePartitionInsertFromConstr_faddcebe',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
      ),
      'symbols' =>
      array (
        0 => 'REPLACE_SYM',
        1 => 'replace_lock_option',
        2 => 'opt_INTO',
        3 => 'table_ident',
        4 => 'opt_use_partition',
        5 => 'insert_from_constructor',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ReplaceStmtWithReplaceSymReplaceLockOptionOptIntoTableIdentOptUsePartitionSetSymUpdateList_aef01fef',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 6,
      ),
      'symbols' =>
      array (
        0 => 'REPLACE_SYM',
        1 => 'replace_lock_option',
        2 => 'opt_INTO',
        3 => 'table_ident',
        4 => 'opt_use_partition',
        5 => 'SET_SYM',
        6 => 'update_list',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ReplaceStmtWithReplaceSymReplaceLockOptionOptIntoTableIdentOptUsePartitionInsertQueryExpre_e4d59508',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
      ),
      'symbols' =>
      array (
        0 => 'REPLACE_SYM',
        1 => 'replace_lock_option',
        2 => 'opt_INTO',
        3 => 'table_ident',
        4 => 'opt_use_partition',
        5 => 'insert_query_expression',
      ),
    ),
  ),
  'insert_lock_option' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\InsertLockOptionChoice_9353dd7b::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\InsertLockOptionChoice_9353dd7b::UseLowPriority_987c9984',
      'symbols' =>
      array (
        0 => 'LOW_PRIORITY',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\InsertLockOptionChoice_9353dd7b::UseDelayed_e28b0c35',
      'symbols' =>
      array (
        0 => 'DELAYED_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\InsertLockOptionChoice_9353dd7b::UseHighPriority_93dc3609',
      'symbols' =>
      array (
        0 => 'HIGH_PRIORITY',
      ),
    ),
  ),
  'replace_lock_option' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_low_priority',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ReplaceLockOptionWithDelayedSym_b216a5e8',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DELAYED_SYM',
      ),
    ),
  ),
  'opt_INTO' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptIntoChoice_7023492e::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptIntoChoice_7023492e::UseInto_e07e33d3',
      'symbols' =>
      array (
        0 => 'INTO',
      ),
    ),
  ),
  'insert_from_constructor' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'insert_values',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InsertFromConstructorWithInsertValues_25d27282',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => ')',
        2 => 'insert_values',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InsertFromConstructorWithInsertColumnsInsertValues_3986a30d',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'insert_columns',
        2 => ')',
        3 => 'insert_values',
      ),
    ),
  ),
  'insert_query_expression' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'query_expression_with_opt_locking_clauses',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InsertQueryExpressionWithQueryExpressionWithOptLockingClauses_fcc512d0',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => ')',
        2 => 'query_expression_with_opt_locking_clauses',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InsertQueryExpressionWithInsertColumnsQueryExpressionWithOptLockingClauses_89ec416f',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'insert_columns',
        2 => ')',
        3 => 'query_expression_with_opt_locking_clauses',
      ),
    ),
  ),
  'insert_columns' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InsertColumnsWithInsertColumnsInsertColumn_058540af',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'insert_columns',
        1 => ',',
        2 => 'insert_column',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'insert_column',
      ),
    ),
  ),
  'insert_values' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InsertValuesWithValueOrValuesValuesList_f4ab378b',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'value_or_values',
        1 => 'values_list',
      ),
    ),
  ),
  'query_expression_with_opt_locking_clauses' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'query_expression',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryExpressionWithOptLockingClausesWithQueryExpressionLockingClauseList_c7e0cf77',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'query_expression',
        1 => 'locking_clause_list',
      ),
    ),
  ),
  'value_or_values' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ValueOrValuesChoice_9296b1a0::UseValue_8ec121c9',
      'symbols' =>
      array (
        0 => 'VALUE_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ValueOrValuesChoice_9296b1a0::UseValues_e79a0720',
      'symbols' =>
      array (
        0 => 'VALUES',
      ),
    ),
  ),
  'values_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ValuesListWithValuesListRowValue_7dec9bff',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'values_list',
        1 => ',',
        2 => 'row_value',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'row_value',
      ),
    ),
  ),
  'values_row_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ValuesRowListWithValuesRowListRowValueExplicit_734510f7',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'values_row_list',
        1 => ',',
        2 => 'row_value_explicit',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'row_value_explicit',
      ),
    ),
  ),
  'equal' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\EqualWithEq_0deaf285',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'EQ',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\EqualWithSetVar_d233fceb',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SET_VAR',
      ),
    ),
  ),
  'opt_equal' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptEqualWith_230e2544',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'equal',
      ),
    ),
  ),
  'row_value' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RowValueWithOptValues_3fdc4705',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'opt_values',
        2 => ')',
      ),
    ),
  ),
  'row_value_explicit' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RowValueExplicitWithRowSymOptValues_7fb9c1e4',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ROW_SYM',
        1 => '(',
        2 => 'opt_values',
        3 => ')',
      ),
    ),
  ),
  'opt_values' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptValuesWith_b931ee77',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'values',
      ),
    ),
  ),
  'values' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ValuesWithValuesExprOrDefault_1c75d8ad',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'values',
        1 => ',',
        2 => 'expr_or_default',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'expr_or_default',
      ),
    ),
  ),
  'expr_or_default' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'expr',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExprOrDefaultWithDefaultSym_c43114ce',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT_SYM',
      ),
    ),
  ),
  'opt_values_reference' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptValuesReferenceWith_b56bc413',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptValuesReferenceWithAsIdentOptDerivedColumnList_89d5bd1c',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'AS',
        1 => 'ident',
        2 => 'opt_derived_column_list',
      ),
    ),
  ),
  'opt_insert_update_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptInsertUpdateListWith_170a6adf',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptInsertUpdateListWithOnSymDuplicateSymKeySymUpdateSymUpdateList_56fff8c8',
      'fields' =>
      array (
        0 => 4,
      ),
      'symbols' =>
      array (
        0 => 'ON_SYM',
        1 => 'DUPLICATE_SYM',
        2 => 'KEY_SYM',
        3 => 'UPDATE_SYM',
        4 => 'update_list',
      ),
    ),
  ),
  'update_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UpdateStmtWithOptWithClauseUpdateSymOptLowPriorityOptIgnoreTableReferenceListSetSymUpdate_550b8674',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 6,
        5 => 7,
        6 => 8,
        7 => 9,
      ),
      'symbols' =>
      array (
        0 => 'opt_with_clause',
        1 => 'UPDATE_SYM',
        2 => 'opt_low_priority',
        3 => 'opt_ignore',
        4 => 'table_reference_list',
        5 => 'SET_SYM',
        6 => 'update_list',
        7 => 'opt_where_clause',
        8 => 'opt_order_clause',
        9 => 'opt_simple_limit',
      ),
    ),
  ),
  'opt_with_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptWithClauseWith_af4f1023',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'with_clause',
      ),
    ),
  ),
  'update_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UpdateListWithUpdateListUpdateElem_f0ee3f6c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'update_list',
        1 => ',',
        2 => 'update_elem',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'update_elem',
      ),
    ),
  ),
  'update_elem' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UpdateElemWithSimpleIdentNospvarEqualExprOrDefault_fd44c70f',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'simple_ident_nospvar',
        1 => 'equal',
        2 => 'expr_or_default',
      ),
    ),
  ),
  'opt_low_priority' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptLowPriorityChoice_4fdab283::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptLowPriorityChoice_4fdab283::UseLowPriority_987c9984',
      'symbols' =>
      array (
        0 => 'LOW_PRIORITY',
      ),
    ),
  ),
  'delete_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DeleteStmtWithOptWithClauseDeleteSymOptDeleteOptionsFromTableIdentOptTableAliasOptUsePart_4c55cbf5',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
        3 => 5,
        4 => 6,
        5 => 7,
        6 => 8,
        7 => 9,
      ),
      'symbols' =>
      array (
        0 => 'opt_with_clause',
        1 => 'DELETE_SYM',
        2 => 'opt_delete_options',
        3 => 'FROM',
        4 => 'table_ident',
        5 => 'opt_table_alias',
        6 => 'opt_use_partition',
        7 => 'opt_where_clause',
        8 => 'opt_order_clause',
        9 => 'opt_simple_limit',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DeleteStmtWithOptWithClauseDeleteSymOptDeleteOptionsTableAliasRefListFromTableReferenceLi_fecc4570',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 5,
        4 => 6,
      ),
      'symbols' =>
      array (
        0 => 'opt_with_clause',
        1 => 'DELETE_SYM',
        2 => 'opt_delete_options',
        3 => 'table_alias_ref_list',
        4 => 'FROM',
        5 => 'table_reference_list',
        6 => 'opt_where_clause',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DeleteStmtWithOptWithClauseDeleteSymOptDeleteOptionsFromTableAliasRefListUsingTableRefere_ab819022',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
        3 => 6,
        4 => 7,
      ),
      'symbols' =>
      array (
        0 => 'opt_with_clause',
        1 => 'DELETE_SYM',
        2 => 'opt_delete_options',
        3 => 'FROM',
        4 => 'table_alias_ref_list',
        5 => 'USING',
        6 => 'table_reference_list',
        7 => 'opt_where_clause',
      ),
    ),
  ),
  'opt_wild' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptWildChoice_b5c0d7fc::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptWildChoice_b5c0d7fc::Use_01dd8e9f',
      'symbols' =>
      array (
        0 => '.',
        1 => '*',
      ),
    ),
  ),
  'opt_delete_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptDeleteOptionsWith_c1df58bb',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptDeleteOptionsWithOptDeleteOptionOptDeleteOptions_48cb0df7',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'opt_delete_option',
        1 => 'opt_delete_options',
      ),
    ),
  ),
  'opt_delete_option' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptDeleteOptionChoice_3dd5b335::UseQuick_e0273b60',
      'symbols' =>
      array (
        0 => 'QUICK',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptDeleteOptionChoice_3dd5b335::UseLowPriority_987c9984',
      'symbols' =>
      array (
        0 => 'LOW_PRIORITY',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptDeleteOptionChoice_3dd5b335::UseIgnore_eff4f8c3',
      'symbols' =>
      array (
        0 => 'IGNORE_SYM',
      ),
    ),
  ),
  'truncate_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TruncateStmtWithTruncateSymOptTableTableIdent_a56a216a',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'TRUNCATE_SYM',
        1 => 'opt_table',
        2 => 'table_ident',
      ),
    ),
  ),
  'opt_table' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptTableChoice_64f6cc2f::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptTableChoice_64f6cc2f::UseTable_52ca2fea',
      'symbols' =>
      array (
        0 => 'TABLE_SYM',
      ),
    ),
  ),
  'opt_profile_defs' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptProfileDefsWith_16b837ce',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'profile_defs',
      ),
    ),
  ),
  'profile_defs' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'profile_def',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ProfileDefsWithProfileDefsProfileDef_7aace7bf',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'profile_defs',
        1 => ',',
        2 => 'profile_def',
      ),
    ),
  ),
  'profile_def' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ProfileDefChoice_246a5626::UseCpu_db9a4c7d',
      'symbols' =>
      array (
        0 => 'CPU_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ProfileDefChoice_246a5626::UseMemory_a266fe9c',
      'symbols' =>
      array (
        0 => 'MEMORY_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ProfileDefChoice_246a5626::UseBlockIo_173f9e4d',
      'symbols' =>
      array (
        0 => 'BLOCK_SYM',
        1 => 'IO_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ProfileDefChoice_246a5626::UseContextSwitches_c2e5ed64',
      'symbols' =>
      array (
        0 => 'CONTEXT_SYM',
        1 => 'SWITCHES_SYM',
      ),
    ),
    4 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ProfileDefChoice_246a5626::UsePageFaults_d73b38d1',
      'symbols' =>
      array (
        0 => 'PAGE_SYM',
        1 => 'FAULTS_SYM',
      ),
    ),
    5 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ProfileDefChoice_246a5626::UseIpc_331b8ee4',
      'symbols' =>
      array (
        0 => 'IPC_SYM',
      ),
    ),
    6 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ProfileDefChoice_246a5626::UseSwaps_9d70aa8c',
      'symbols' =>
      array (
        0 => 'SWAPS_SYM',
      ),
    ),
    7 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ProfileDefChoice_246a5626::UseSource_56ccd012',
      'symbols' =>
      array (
        0 => 'SOURCE_SYM',
      ),
    ),
    8 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ProfileDefChoice_246a5626::UseAll_b5c7aed7',
      'symbols' =>
      array (
        0 => 'ALL',
      ),
    ),
  ),
  'opt_for_query' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptForQueryWith_ce33a8bc',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptForQueryWithForSymQuerySymNum_eda2665e',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'FOR_SYM',
        1 => 'QUERY_SYM',
        2 => 'NUM',
      ),
    ),
  ),
  'show_databases_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowDatabasesStmtWithShowDatabasesOptWildOrWhere_d28dc5dc',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'DATABASES',
        2 => 'opt_wild_or_where',
      ),
    ),
  ),
  'show_tables_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowTablesStmtWithShowOptShowCmdTypeTablesOptDbOptWildOrWhere_e80b4102',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'opt_show_cmd_type',
        2 => 'TABLES',
        3 => 'opt_db',
        4 => 'opt_wild_or_where',
      ),
    ),
  ),
  'show_triggers_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowTriggersStmtWithShowOptFullTriggersSymOptDbOptWildOrWhere_212a9954',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'opt_full',
        2 => 'TRIGGERS_SYM',
        3 => 'opt_db',
        4 => 'opt_wild_or_where',
      ),
    ),
  ),
  'show_events_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowEventsStmtWithShowEventsSymOptDbOptWildOrWhere_a1f45eba',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'EVENTS_SYM',
        2 => 'opt_db',
        3 => 'opt_wild_or_where',
      ),
    ),
  ),
  'show_table_status_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowTableStatusStmtWithShowTableSymStatusSymOptDbOptWildOrWhere_09642354',
      'fields' =>
      array (
        0 => 3,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'TABLE_SYM',
        2 => 'STATUS_SYM',
        3 => 'opt_db',
        4 => 'opt_wild_or_where',
      ),
    ),
  ),
  'show_open_tables_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowOpenTablesStmtWithShowOpenSymTablesOptDbOptWildOrWhere_844db97b',
      'fields' =>
      array (
        0 => 3,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'OPEN_SYM',
        2 => 'TABLES',
        3 => 'opt_db',
        4 => 'opt_wild_or_where',
      ),
    ),
  ),
  'show_plugins_stmt' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ShowPluginsStmtChoice_6ad724db::UseShowPlugins_d80d134d',
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'PLUGINS_SYM',
      ),
    ),
  ),
  'show_engine_logs_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowEngineLogsStmtWithShowEngineSymEngineOrAllLogsSym_50576f7f',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'ENGINE_SYM',
        2 => 'engine_or_all',
        3 => 'LOGS_SYM',
      ),
    ),
  ),
  'show_engine_mutex_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowEngineMutexStmtWithShowEngineSymEngineOrAllMutexSym_4e58f698',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'ENGINE_SYM',
        2 => 'engine_or_all',
        3 => 'MUTEX_SYM',
      ),
    ),
  ),
  'show_engine_status_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowEngineStatusStmtWithShowEngineSymEngineOrAllStatusSym_8666140d',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'ENGINE_SYM',
        2 => 'engine_or_all',
        3 => 'STATUS_SYM',
      ),
    ),
  ),
  'show_columns_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowColumnsStmtWithShowOptShowCmdTypeColumnsFromOrInTableIdentOptDbOptWildOrWhere_02885338',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 6,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'opt_show_cmd_type',
        2 => 'COLUMNS',
        3 => 'from_or_in',
        4 => 'table_ident',
        5 => 'opt_db',
        6 => 'opt_wild_or_where',
      ),
    ),
  ),
  'show_binary_logs_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowBinaryLogsStmtWithShowMasterOrBinaryLogsSym_bf9d5782',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'master_or_binary',
        2 => 'LOGS_SYM',
      ),
    ),
  ),
  'show_replicas_stmt' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ShowReplicasStmtChoice_4340aa31::UseShowSlaveHosts_b1d358e3',
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'SLAVE',
        2 => 'HOSTS_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ShowReplicasStmtChoice_4340aa31::UseShowReplicas_0ab1cee3',
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'REPLICAS_SYM',
      ),
    ),
  ),
  'show_binlog_events_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowBinlogEventsStmtWithShowBinlogSymEventsSymOptBinlogInBinlogFromOptLimitClause_749a6365',
      'fields' =>
      array (
        0 => 3,
        1 => 4,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'BINLOG_SYM',
        2 => 'EVENTS_SYM',
        3 => 'opt_binlog_in',
        4 => 'binlog_from',
        5 => 'opt_limit_clause',
      ),
    ),
  ),
  'show_relaylog_events_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowRelaylogEventsStmtWithShowRelaylogSymEventsSymOptBinlogInBinlogFromOptLimitClauseOptChannel_291a1c32',
      'fields' =>
      array (
        0 => 3,
        1 => 4,
        2 => 5,
        3 => 6,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'RELAYLOG_SYM',
        2 => 'EVENTS_SYM',
        3 => 'opt_binlog_in',
        4 => 'binlog_from',
        5 => 'opt_limit_clause',
        6 => 'opt_channel',
      ),
    ),
  ),
  'show_keys_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowKeysStmtWithShowOptExtendedKeysOrIndexFromOrInTableIdentOptDbOptWhereClause_d5ca9560',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 6,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'opt_extended',
        2 => 'keys_or_index',
        3 => 'from_or_in',
        4 => 'table_ident',
        5 => 'opt_db',
        6 => 'opt_where_clause',
      ),
    ),
  ),
  'show_engines_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowEnginesStmtWithShowOptStorageEnginesSym_da5d6655',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'opt_storage',
        2 => 'ENGINES_SYM',
      ),
    ),
  ),
  'show_count_warnings_stmt' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ShowCountWarningsStmtChoice_0ee0015f::UseShowCountWarnings_6ad3f36a',
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'COUNT_SYM',
        2 => '(',
        3 => '*',
        4 => ')',
        5 => 'WARNINGS',
      ),
    ),
  ),
  'show_count_errors_stmt' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ShowCountErrorsStmtChoice_45b84e63::UseShowCountErrors_c4da3959',
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'COUNT_SYM',
        2 => '(',
        3 => '*',
        4 => ')',
        5 => 'ERRORS',
      ),
    ),
  ),
  'show_warnings_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowWarningsStmtWithShowWarningsOptLimitClause_a0e88afc',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'WARNINGS',
        2 => 'opt_limit_clause',
      ),
    ),
  ),
  'show_errors_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowErrorsStmtWithShowErrorsOptLimitClause_ad6a5518',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'ERRORS',
        2 => 'opt_limit_clause',
      ),
    ),
  ),
  'show_profiles_stmt' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ShowProfilesStmtChoice_d306bf3a::UseShowProfiles_10e6f7fd',
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'PROFILES_SYM',
      ),
    ),
  ),
  'show_profile_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowProfileStmtWithShowProfileSymOptProfileDefsOptForQueryOptLimitClause_5bb7b5fb',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'PROFILE_SYM',
        2 => 'opt_profile_defs',
        3 => 'opt_for_query',
        4 => 'opt_limit_clause',
      ),
    ),
  ),
  'show_status_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowStatusStmtWithShowOptVarTypeStatusSymOptWildOrWhere_8247e559',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'opt_var_type',
        2 => 'STATUS_SYM',
        3 => 'opt_wild_or_where',
      ),
    ),
  ),
  'show_processlist_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowProcesslistStmtWithShowOptFullProcesslistSym_d789e163',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'opt_full',
        2 => 'PROCESSLIST_SYM',
      ),
    ),
  ),
  'show_variables_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowVariablesStmtWithShowOptVarTypeVariablesOptWildOrWhere_9e93e053',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'opt_var_type',
        2 => 'VARIABLES',
        3 => 'opt_wild_or_where',
      ),
    ),
  ),
  'show_character_set_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowCharacterSetStmtWithShowCharacterSetOptWildOrWhere_de91fc05',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'character_set',
        2 => 'opt_wild_or_where',
      ),
    ),
  ),
  'show_collation_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowCollationStmtWithShowCollationSymOptWildOrWhere_18ebbd86',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'COLLATION_SYM',
        2 => 'opt_wild_or_where',
      ),
    ),
  ),
  'show_privileges_stmt' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ShowPrivilegesStmtChoice_18bc00fb::UseShowPrivileges_3999f1e2',
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'PRIVILEGES',
      ),
    ),
  ),
  'show_grants_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowGrantsStmtWithShowGrants_7bfe1973',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'GRANTS',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowGrantsStmtWithShowGrantsForSymUser_7d98cf47',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'GRANTS',
        2 => 'FOR_SYM',
        3 => 'user',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowGrantsStmtWithShowGrantsForSymUserUsingUserList_c46cfaea',
      'fields' =>
      array (
        0 => 3,
        1 => 5,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'GRANTS',
        2 => 'FOR_SYM',
        3 => 'user',
        4 => 'USING',
        5 => 'user_list',
      ),
    ),
  ),
  'show_create_database_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowCreateDatabaseStmtWithShowCreateDatabaseOptIfNotExistsIdent_581c32d7',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'CREATE',
        2 => 'DATABASE',
        3 => 'opt_if_not_exists',
        4 => 'ident',
      ),
    ),
  ),
  'show_create_table_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowCreateTableStmtWithShowCreateTableSymTableIdent_ad6daa7b',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'CREATE',
        2 => 'TABLE_SYM',
        3 => 'table_ident',
      ),
    ),
  ),
  'show_create_view_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowCreateViewStmtWithShowCreateViewSymTableIdent_52e50cd8',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'CREATE',
        2 => 'VIEW_SYM',
        3 => 'table_ident',
      ),
    ),
  ),
  'show_master_status_stmt' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ShowMasterStatusStmtChoice_51f059d6::UseShowMasterStatus_16c97857',
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'MASTER_SYM',
        2 => 'STATUS_SYM',
      ),
    ),
  ),
  'show_replica_status_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowReplicaStatusStmtWithShowReplicaStatusSymOptChannel_84621002',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'replica',
        2 => 'STATUS_SYM',
        3 => 'opt_channel',
      ),
    ),
  ),
  'show_create_procedure_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowCreateProcedureStmtWithShowCreateProcedureSymSpName_2178bfe5',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'CREATE',
        2 => 'PROCEDURE_SYM',
        3 => 'sp_name',
      ),
    ),
  ),
  'show_create_function_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowCreateFunctionStmtWithShowCreateFunctionSymSpName_84ec0f6f',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'CREATE',
        2 => 'FUNCTION_SYM',
        3 => 'sp_name',
      ),
    ),
  ),
  'show_create_trigger_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowCreateTriggerStmtWithShowCreateTriggerSymSpName_28f97f3c',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'CREATE',
        2 => 'TRIGGER_SYM',
        3 => 'sp_name',
      ),
    ),
  ),
  'show_procedure_status_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowProcedureStatusStmtWithShowProcedureSymStatusSymOptWildOrWhere_e6a80f62',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'PROCEDURE_SYM',
        2 => 'STATUS_SYM',
        3 => 'opt_wild_or_where',
      ),
    ),
  ),
  'show_function_status_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowFunctionStatusStmtWithShowFunctionSymStatusSymOptWildOrWhere_11df7a12',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'FUNCTION_SYM',
        2 => 'STATUS_SYM',
        3 => 'opt_wild_or_where',
      ),
    ),
  ),
  'show_procedure_code_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowProcedureCodeStmtWithShowProcedureSymCodeSymSpName_524e221a',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'PROCEDURE_SYM',
        2 => 'CODE_SYM',
        3 => 'sp_name',
      ),
    ),
  ),
  'show_function_code_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowFunctionCodeStmtWithShowFunctionSymCodeSymSpName_7829fc3e',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'FUNCTION_SYM',
        2 => 'CODE_SYM',
        3 => 'sp_name',
      ),
    ),
  ),
  'show_create_event_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowCreateEventStmtWithShowCreateEventSymSpName_cf02a0b2',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'CREATE',
        2 => 'EVENT_SYM',
        3 => 'sp_name',
      ),
    ),
  ),
  'show_create_user_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowCreateUserStmtWithShowCreateUserUser_0212cc2f',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'CREATE',
        2 => 'USER',
        3 => 'user',
      ),
    ),
  ),
  'engine_or_all' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_or_text',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\EngineOrAllWithAll_771560d4',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ALL',
      ),
    ),
  ),
  'master_or_binary' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\MasterOrBinaryChoice_a8a79e4d::UseMaster_30e77240',
      'symbols' =>
      array (
        0 => 'MASTER_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\MasterOrBinaryChoice_a8a79e4d::UseBinary_4c77b56b',
      'symbols' =>
      array (
        0 => 'BINARY_SYM',
      ),
    ),
  ),
  'opt_storage' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptStorageChoice_8bafacff::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptStorageChoice_8bafacff::UseStorage_b2f6362f',
      'symbols' =>
      array (
        0 => 'STORAGE_SYM',
      ),
    ),
  ),
  'opt_db' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptDbWith_e8f14526',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptDbWithFromOrInIdent_99311b31',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'from_or_in',
        1 => 'ident',
      ),
    ),
  ),
  'opt_full' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptFullChoice_2a2428ff::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptFullChoice_2a2428ff::UseFull_cb6839ca',
      'symbols' =>
      array (
        0 => 'FULL',
      ),
    ),
  ),
  'opt_extended' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptExtendedChoice_6c2248eb::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptExtendedChoice_6c2248eb::UseExtended_9632d8ea',
      'symbols' =>
      array (
        0 => 'EXTENDED_SYM',
      ),
    ),
  ),
  'opt_show_cmd_type' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptShowCmdTypeChoice_2f00cb0f::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptShowCmdTypeChoice_2f00cb0f::UseFull_cb6839ca',
      'symbols' =>
      array (
        0 => 'FULL',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptShowCmdTypeChoice_2f00cb0f::UseExtended_9632d8ea',
      'symbols' =>
      array (
        0 => 'EXTENDED_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptShowCmdTypeChoice_2f00cb0f::UseExtendedFull_d290d1e0',
      'symbols' =>
      array (
        0 => 'EXTENDED_SYM',
        1 => 'FULL',
      ),
    ),
  ),
  'from_or_in' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\FromOrInChoice_6f91678a::UseFrom_f4383c66',
      'symbols' =>
      array (
        0 => 'FROM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\FromOrInChoice_6f91678a::UseIn_fed1d872',
      'symbols' =>
      array (
        0 => 'IN_SYM',
      ),
    ),
  ),
  'opt_binlog_in' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptBinlogInWith_34d0ea3a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptBinlogInWithInSymTextStringSys_103d0610',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'IN_SYM',
        1 => 'TEXT_STRING_sys',
      ),
    ),
  ),
  'binlog_from' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BinlogFromWith_c846d9e6',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BinlogFromWithFromUlonglongNum_f69d62e6',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'FROM',
        1 => 'ulonglong_num',
      ),
    ),
  ),
  'opt_wild_or_where' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptWildOrWhereWith_e6b93859',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptWildOrWhereWithLikeTextStringLiteral_9ebded98',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LIKE',
        1 => 'TEXT_STRING_literal',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'where_clause',
      ),
    ),
  ),
  'describe_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DescribeStmtWithDescribeCommandTableIdentOptDescribeColumn_f90b93b9',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'describe_command',
        1 => 'table_ident',
        2 => 'opt_describe_column',
      ),
    ),
  ),
  'explain_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExplainStmtWithDescribeCommandOptExplainOptionsExplainableStmt_c1c9381c',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'describe_command',
        1 => 'opt_explain_options',
        2 => 'explainable_stmt',
      ),
    ),
  ),
  'explainable_stmt' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'select_stmt',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'insert_stmt',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'replace_stmt',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'update_stmt',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'delete_stmt',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExplainableStmtWithForSymConnectionSymRealUlongNum_9037949f',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'FOR_SYM',
        1 => 'CONNECTION_SYM',
        2 => 'real_ulong_num',
      ),
    ),
  ),
  'describe_command' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DescribeCommandWithDesc_f894e0ac',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DESC',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DescribeCommandWithDescribe_a3cb6a25',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DESCRIBE',
      ),
    ),
  ),
  'opt_explain_format' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptExplainFormatWith_1dec0485',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptExplainFormatWithFormatSymEqIdentOrText_4e9292da',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'FORMAT_SYM',
        1 => 'EQ',
        2 => 'ident_or_text',
      ),
    ),
  ),
  'opt_explain_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptExplainOptionsWithAnalyzeSymOptExplainFormat_62eae5e8',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ANALYZE_SYM',
        1 => 'opt_explain_format',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_explain_format',
      ),
    ),
  ),
  'opt_describe_column' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptDescribeColumnWith_296cec4d',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'text_string',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
  ),
  'flush' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FlushWithFlushSymOptNoWriteToBinlogFlushOptions_991be9df',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'FLUSH_SYM',
        1 => 'opt_no_write_to_binlog',
        2 => 'flush_options',
      ),
    ),
  ),
  'flush_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FlushOptionsWithTableOrTablesOptTableListOptFlushLock_ade860b1',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_or_tables',
        1 => 'opt_table_list',
        2 => 'opt_flush_lock',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'flush_options_list',
      ),
    ),
  ),
  'opt_flush_lock' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptFlushLockChoice_d12692ad::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptFlushLockChoice_d12692ad::UseWithReadLock_cf240c10',
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'READ_SYM',
        2 => 'LOCK_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptFlushLockChoice_d12692ad::UseForExport_964dac9c',
      'symbols' =>
      array (
        0 => 'FOR_SYM',
        1 => 'EXPORT_SYM',
      ),
    ),
  ),
  'flush_options_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FlushOptionsListWithFlushOptionsListFlushOption_c5b6f838',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'flush_options_list',
        1 => ',',
        2 => 'flush_option',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'flush_option',
      ),
    ),
  ),
  'flush_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FlushOptionWithErrorSymLogsSym_5572acaf',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ERROR_SYM',
        1 => 'LOGS_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FlushOptionWithEngineSymLogsSym_bbf4fe5e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ENGINE_SYM',
        1 => 'LOGS_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FlushOptionWithGeneralLogsSym_f8aefa1a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'GENERAL',
        1 => 'LOGS_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FlushOptionWithSlowLogsSym_a8bcaccd',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SLOW',
        1 => 'LOGS_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FlushOptionWithBinarySymLogsSym_41159344',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'BINARY_SYM',
        1 => 'LOGS_SYM',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FlushOptionWithRelayLogsSymOptChannel_840844eb',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'RELAY',
        1 => 'LOGS_SYM',
        2 => 'opt_channel',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FlushOptionWithHostsSym_11ae5d16',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'HOSTS_SYM',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FlushOptionWithPrivileges_6dea5816',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'PRIVILEGES',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FlushOptionWithLogsSym_0da54bee',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'LOGS_SYM',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FlushOptionWithStatusSym_79a32137',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'STATUS_SYM',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FlushOptionWithResources_b990d0a6',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'RESOURCES',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FlushOptionWithOptimizerCostsSym_f6c9b69e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'OPTIMIZER_COSTS_SYM',
      ),
    ),
  ),
  'opt_table_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptTableListWith_9b679588',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_list',
      ),
    ),
  ),
  'reset' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ResetWithResetSymResetOptions_497bfe21',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'RESET_SYM',
        1 => 'reset_options',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ResetWithResetSymPersistSymOptIfExistsIdent_61cec66c',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'RESET_SYM',
        1 => 'PERSIST_SYM',
        2 => 'opt_if_exists_ident',
      ),
    ),
  ),
  'reset_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ResetOptionsWithResetOptionsResetOption_7e7b7df8',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'reset_options',
        1 => ',',
        2 => 'reset_option',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'reset_option',
      ),
    ),
  ),
  'opt_if_exists_ident' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIfExistsIdentWith_3ac79d47',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIfExistsIdentWithIfExistsPersistedVariableIdent_385dc152',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'if_exists',
        1 => 'persisted_variable_ident',
      ),
    ),
  ),
  'persisted_variable_ident' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PersistedVariableIdentWithIdentIdent_53c32861',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => '.',
        2 => 'ident',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PersistedVariableIdentWithDefaultSymIdent_1a2997de',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT_SYM',
        1 => '.',
        2 => 'ident',
      ),
    ),
  ),
  'reset_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ResetOptionWithSlaveOptReplicaResetOptionsOptChannel_508c709a',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SLAVE',
        1 => 'opt_replica_reset_options',
        2 => 'opt_channel',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ResetOptionWithReplicaSymOptReplicaResetOptionsOptChannel_e0ce5717',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'REPLICA_SYM',
        1 => 'opt_replica_reset_options',
        2 => 'opt_channel',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ResetOptionWithMasterSymSourceResetOptions_3ad49d73',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SYM',
        1 => 'source_reset_options',
      ),
    ),
  ),
  'opt_replica_reset_options' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptReplicaResetOptionsChoice_66c44b99::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptReplicaResetOptionsChoice_66c44b99::UseAll_b5c7aed7',
      'symbols' =>
      array (
        0 => 'ALL',
      ),
    ),
  ),
  'source_reset_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceResetOptionsWith_b0cfacea',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SourceResetOptionsWithToSymRealUlonglongNum_ba7a7ef0',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'TO_SYM',
        1 => 'real_ulonglong_num',
      ),
    ),
  ),
  'purge' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PurgeWithPurgePurgeOptions_d63a2795',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'PURGE',
        1 => 'purge_options',
      ),
    ),
  ),
  'purge_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PurgeOptionsWithMasterOrBinaryLogsSymPurgeOption_274a9a9c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'master_or_binary',
        1 => 'LOGS_SYM',
        2 => 'purge_option',
      ),
    ),
  ),
  'purge_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PurgeOptionWithToSymTextStringSys_39901f47',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'TO_SYM',
        1 => 'TEXT_STRING_sys',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PurgeOptionWithBeforeSymExpr_3447e8c2',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'BEFORE_SYM',
        1 => 'expr',
      ),
    ),
  ),
  'kill' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KillWithKillSymKillOptionExpr_677f0c68',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'KILL_SYM',
        1 => 'kill_option',
        2 => 'expr',
      ),
    ),
  ),
  'kill_option' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\KillOptionChoice_cb5c94e4::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\KillOptionChoice_cb5c94e4::UseConnection_453e643a',
      'symbols' =>
      array (
        0 => 'CONNECTION_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\KillOptionChoice_cb5c94e4::UseQuery_d80eef57',
      'symbols' =>
      array (
        0 => 'QUERY_SYM',
      ),
    ),
  ),
  'use' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UseWithUseSymIdent_fc7525ae',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'USE_SYM',
        1 => 'ident',
      ),
    ),
  ),
  'load_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LoadStmtWithLoadDataOrXmlLoadDataLockOptFromKeywordOptLocalLoadSourceTypeTextStringFile_53602d45',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 6,
        6 => 7,
        7 => 8,
        8 => 9,
        9 => 12,
        10 => 13,
        11 => 14,
        12 => 15,
        13 => 16,
        14 => 17,
        15 => 18,
        16 => 19,
        17 => 20,
        18 => 21,
      ),
      'symbols' =>
      array (
        0 => 'LOAD',
        1 => 'data_or_xml',
        2 => 'load_data_lock',
        3 => 'opt_from_keyword',
        4 => 'opt_local',
        5 => 'load_source_type',
        6 => 'TEXT_STRING_filesystem',
        7 => 'opt_source_count',
        8 => 'opt_source_order',
        9 => 'opt_duplicate',
        10 => 'INTO',
        11 => 'TABLE_SYM',
        12 => 'table_ident',
        13 => 'opt_use_partition',
        14 => 'opt_load_data_charset',
        15 => 'opt_xml_rows_identified_by',
        16 => 'opt_field_term',
        17 => 'opt_line_term',
        18 => 'opt_ignore_lines',
        19 => 'opt_field_or_var_spec',
        20 => 'opt_load_data_set_spec',
        21 => 'opt_load_algorithm',
      ),
    ),
  ),
  'data_or_xml' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\DataOrXmlChoice_71428860::UseData_c97c29c7',
      'symbols' =>
      array (
        0 => 'DATA_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\DataOrXmlChoice_71428860::UseXml_40658e9a',
      'symbols' =>
      array (
        0 => 'XML_SYM',
      ),
    ),
  ),
  'opt_local' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptLocalChoice_a187b50c::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptLocalChoice_a187b50c::UseLocal_646c1937',
      'symbols' =>
      array (
        0 => 'LOCAL_SYM',
      ),
    ),
  ),
  'opt_from_keyword' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptFromKeywordChoice_8c3f0536::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptFromKeywordChoice_8c3f0536::UseFrom_f4383c66',
      'symbols' =>
      array (
        0 => 'FROM',
      ),
    ),
  ),
  'load_data_lock' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\LoadDataLockChoice_d4b9972a::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\LoadDataLockChoice_d4b9972a::UseConcurrent_852eda03',
      'symbols' =>
      array (
        0 => 'CONCURRENT',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\LoadDataLockChoice_d4b9972a::UseLowPriority_987c9984',
      'symbols' =>
      array (
        0 => 'LOW_PRIORITY',
      ),
    ),
  ),
  'load_source_type' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\LoadSourceTypeChoice_ff94dae9::UseInfile_dd9367e7',
      'symbols' =>
      array (
        0 => 'INFILE_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\LoadSourceTypeChoice_ff94dae9::UseUrl_e7a241de',
      'symbols' =>
      array (
        0 => 'URL_SYM',
      ),
    ),
  ),
  'opt_source_count' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSourceCountWith_289996eb',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSourceCountWithCountSymNum_a0b6ee12',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'COUNT_SYM',
        1 => 'NUM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSourceCountWithIdentSysNum_720bcd2a',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'IDENT_sys',
        1 => 'NUM',
      ),
    ),
  ),
  'opt_source_order' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptSourceOrderChoice_3bb841a7::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptSourceOrderChoice_3bb841a7::UseInPrimaryKeyOrder_cfbaebb1',
      'symbols' =>
      array (
        0 => 'IN_SYM',
        1 => 'PRIMARY_SYM',
        2 => 'KEY_SYM',
        3 => 'ORDER_SYM',
      ),
    ),
  ),
  'opt_duplicate' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptDuplicateWith_208a1f27',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'duplicate',
      ),
    ),
  ),
  'duplicate' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\DuplicateChoice_ed63d717::UseReplace_9b66c971',
      'symbols' =>
      array (
        0 => 'REPLACE_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\DuplicateChoice_ed63d717::UseIgnore_eff4f8c3',
      'symbols' =>
      array (
        0 => 'IGNORE_SYM',
      ),
    ),
  ),
  'opt_field_term' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptFieldTermWith_6f457457',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptFieldTermWithColumnsFieldTermList_b9999c72',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'COLUMNS',
        1 => 'field_term_list',
      ),
    ),
  ),
  'field_term_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldTermListWithFieldTermListFieldTerm_00595581',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'field_term_list',
        1 => 'field_term',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'field_term',
      ),
    ),
  ),
  'field_term' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldTermWithTerminatedByTextString_89d84299',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'TERMINATED',
        1 => 'BY',
        2 => 'text_string',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldTermWithOptionallyEnclosedByTextString_94cd4b91',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'OPTIONALLY',
        1 => 'ENCLOSED',
        2 => 'BY',
        3 => 'text_string',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldTermWithEnclosedByTextString_e1cc57fe',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ENCLOSED',
        1 => 'BY',
        2 => 'text_string',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldTermWithEscapedByTextString_e321c03d',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ESCAPED',
        1 => 'BY',
        2 => 'text_string',
      ),
    ),
  ),
  'opt_line_term' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptLineTermWith_a03055e3',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptLineTermWithLinesLineTermList_97c5193f',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LINES',
        1 => 'line_term_list',
      ),
    ),
  ),
  'line_term_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LineTermListWithLineTermListLineTerm_3c14dca0',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'line_term_list',
        1 => 'line_term',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'line_term',
      ),
    ),
  ),
  'line_term' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LineTermWithTerminatedByTextString_d51a5d08',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'TERMINATED',
        1 => 'BY',
        2 => 'text_string',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LineTermWithStartingByTextString_e18a2985',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'STARTING',
        1 => 'BY',
        2 => 'text_string',
      ),
    ),
  ),
  'opt_xml_rows_identified_by' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptXmlRowsIdentifiedByWith_b8e436c9',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptXmlRowsIdentifiedByWithRowsSymIdentifiedSymByTextString_cf6e50e1',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ROWS_SYM',
        1 => 'IDENTIFIED_SYM',
        2 => 'BY',
        3 => 'text_string',
      ),
    ),
  ),
  'opt_ignore_lines' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIgnoreLinesWith_14aecce0',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIgnoreLinesWithIgnoreSymNumLinesOrRows_518c1061',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'IGNORE_SYM',
        1 => 'NUM',
        2 => 'lines_or_rows',
      ),
    ),
  ),
  'lines_or_rows' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\LinesOrRowsChoice_0e1d3876::UseLines_72df37d4',
      'symbols' =>
      array (
        0 => 'LINES',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\LinesOrRowsChoice_0e1d3876::UseRows_6d2b98cb',
      'symbols' =>
      array (
        0 => 'ROWS_SYM',
      ),
    ),
  ),
  'opt_field_or_var_spec' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptFieldOrVarSpecWith_007fcd43',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptFieldOrVarSpecWithFieldsOrVars_0b685183',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'fields_or_vars',
        2 => ')',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptFieldOrVarSpecWith_c8edae68',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => ')',
      ),
    ),
  ),
  'fields_or_vars' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldsOrVarsWithFieldsOrVarsFieldOrVar_61b4443d',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'fields_or_vars',
        1 => ',',
        2 => 'field_or_var',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'field_or_var',
      ),
    ),
  ),
  'field_or_var' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'simple_ident_nospvar',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldOrVarWithIdentOrText_6fd3d2e3',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '@',
        1 => 'ident_or_text',
      ),
    ),
  ),
  'opt_load_data_set_spec' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptLoadDataSetSpecWith_37237abe',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptLoadDataSetSpecWithSetSymLoadDataSetList_d6fb3216',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SET_SYM',
        1 => 'load_data_set_list',
      ),
    ),
  ),
  'load_data_set_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LoadDataSetListWithLoadDataSetListLoadDataSetElem_d637b29d',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'load_data_set_list',
        1 => ',',
        2 => 'load_data_set_elem',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'load_data_set_elem',
      ),
    ),
  ),
  'load_data_set_elem' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LoadDataSetElemWithSimpleIdentNospvarEqualExprOrDefault_2d3157e3',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'simple_ident_nospvar',
        1 => 'equal',
        2 => 'expr_or_default',
      ),
    ),
  ),
  'opt_load_algorithm' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptLoadAlgorithmChoice_e6484b72::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptLoadAlgorithmChoice_e6484b72::UseAlgorithmBulk_09c89115',
      'symbols' =>
      array (
        0 => 'ALGORITHM_SYM',
        1 => 'EQ',
        2 => 'BULK_SYM',
      ),
    ),
  ),
  'text_literal' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TextLiteralWithTextString_e4795b6b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TEXT_STRING',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TextLiteralWithNcharString_cea3e07e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NCHAR_STRING',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TextLiteralWithUnderscoreCharsetTextString_d74c5e2d',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'UNDERSCORE_CHARSET',
        1 => 'TEXT_STRING',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TextLiteralWithTextLiteralTextStringLiteral_95576efb',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'text_literal',
        1 => 'TEXT_STRING_literal',
      ),
    ),
  ),
  'text_string' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'TEXT_STRING_literal',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TextStringWithHexNum_e10148ad',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HEX_NUM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TextStringWithBinNum_a8d4b26c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BIN_NUM',
      ),
    ),
  ),
  'param_marker' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ParamMarkerWithParamMarker_ee6e75a3',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PARAM_MARKER',
      ),
    ),
  ),
  'signed_literal' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'literal',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SignedLiteralWithNumLiteral_5d32f4fd',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '+',
        1 => 'NUM_literal',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SignedLiteralWithNumLiteral_039dd223',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '-',
        1 => 'NUM_literal',
      ),
    ),
  ),
  'signed_literal_or_null' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'signed_literal',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'null_as_literal',
      ),
    ),
  ),
  'null_as_literal' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\NullAsLiteralChoice_83b9fcc2::UseNull_fb329000',
      'symbols' =>
      array (
        0 => 'NULL_SYM',
      ),
    ),
  ),
  'literal' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'text_literal',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'NUM_literal',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'temporal_literal',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LiteralWithFalseSym_8cbf8171',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'FALSE_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LiteralWithTrueSym_31dca60d',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'TRUE_SYM',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LiteralWithHexNum_d4728a68',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HEX_NUM',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LiteralWithBinNum_cdfa2555',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BIN_NUM',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LiteralWithUnderscoreCharsetHexNum_b544dd41',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'UNDERSCORE_CHARSET',
        1 => 'HEX_NUM',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LiteralWithUnderscoreCharsetBinNum_326cde49',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'UNDERSCORE_CHARSET',
        1 => 'BIN_NUM',
      ),
    ),
  ),
  'literal_or_null' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'literal',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'null_as_literal',
      ),
    ),
  ),
  'NUM_literal' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'int64_literal',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NumLiteralWithDecimalNum_1a6d0c95',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DECIMAL_NUM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NumLiteralWithFloatNum_9e659a12',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FLOAT_NUM',
      ),
    ),
  ),
  'int64_literal' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\Int64LiteralWithNum_442fd3cf',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NUM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\Int64LiteralWithLongNum_a2a6764c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LONG_NUM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\Int64LiteralWithUlonglongNum_c1b71d8b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ULONGLONG_NUM',
      ),
    ),
  ),
  'temporal_literal' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TemporalLiteralWithDateSymTextString_873dc452',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DATE_SYM',
        1 => 'TEXT_STRING',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TemporalLiteralWithTimeSymTextString_a7fefc87',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'TIME_SYM',
        1 => 'TEXT_STRING',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TemporalLiteralWithTimestampSymTextString_a476257b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'TIMESTAMP_SYM',
        1 => 'TEXT_STRING',
      ),
    ),
  ),
  'opt_interval' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptIntervalChoice_7b326ecb::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptIntervalChoice_7b326ecb::UseInterval_8ed24e3a',
      'symbols' =>
      array (
        0 => 'INTERVAL_SYM',
      ),
    ),
  ),
  'insert_column' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'simple_ident_nospvar',
      ),
    ),
  ),
  'table_wild' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableWildWithIdent_ce4934bd',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => '.',
        2 => '*',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableWildWithIdentIdent_e2b883f2',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => '.',
        2 => 'ident',
        3 => '.',
        4 => '*',
      ),
    ),
  ),
  'order_expr' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OrderExprWithExprOptOrderingDirection_87e58a40',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'opt_ordering_direction',
      ),
    ),
  ),
  'grouping_expr' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'expr',
      ),
    ),
  ),
  'simple_ident' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'simple_ident_q',
      ),
    ),
  ),
  'simple_ident_nospvar' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'simple_ident_q',
      ),
    ),
  ),
  'simple_ident_q' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleIdentQWithIdentIdent_591fa226',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => '.',
        2 => 'ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleIdentQWithIdentIdentIdent_134a470f',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => '.',
        2 => 'ident',
        3 => '.',
        4 => 'ident',
      ),
    ),
  ),
  'table_ident' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableIdentWithIdentIdent_040003e0',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => '.',
        2 => 'ident',
      ),
    ),
  ),
  'table_ident_opt_wild' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableIdentOptWildWithIdentOptWild_85c7920f',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => 'opt_wild',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableIdentOptWildWithIdentIdentOptWild_c3d48a0f',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => '.',
        2 => 'ident',
        3 => 'opt_wild',
      ),
    ),
  ),
  'IDENT_sys' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentSysWithIdent_cb71cb24',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'IDENT',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentSysWithIdentQuoted_a05e4f7c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'IDENT_QUOTED',
      ),
    ),
  ),
  'TEXT_STRING_sys_nonewline' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'TEXT_STRING_sys',
      ),
    ),
  ),
  'filter_wild_db_table_string' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
  ),
  'TEXT_STRING_sys' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TextStringSysWithTextString_c6524d97',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TEXT_STRING',
      ),
    ),
  ),
  'TEXT_STRING_literal' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TextStringLiteralWithTextString_88b96700',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TEXT_STRING',
      ),
    ),
  ),
  'TEXT_STRING_filesystem' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TextStringFilesystemWithTextString_3388c2cf',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TEXT_STRING',
      ),
    ),
  ),
  'TEXT_STRING_password' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TextStringPasswordWithTextString_f63b4edf',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TEXT_STRING',
      ),
    ),
  ),
  'TEXT_STRING_hash' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'TEXT_STRING_sys',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TextStringHashWithHexNum_01c13004',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HEX_NUM',
      ),
    ),
  ),
  'TEXT_STRING_validated' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TextStringValidatedWithTextString_4b1e745a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TEXT_STRING',
      ),
    ),
  ),
  'ident' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'IDENT_sys',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_keyword',
      ),
    ),
  ),
  'role_ident' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'IDENT_sys',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'role_keyword',
      ),
    ),
  ),
  'label_ident' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'IDENT_sys',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'label_keyword',
      ),
    ),
  ),
  'lvalue_ident' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'IDENT_sys',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'lvalue_keyword',
      ),
    ),
  ),
  'ident_or_text' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'TEXT_STRING_sys',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentOrTextWithLexHostname_e79c1abe',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LEX_HOSTNAME',
      ),
    ),
  ),
  'role_ident_or_text' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'role_ident',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'TEXT_STRING_sys',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleIdentOrTextWithLexHostname_6e6fdaca',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LEX_HOSTNAME',
      ),
    ),
  ),
  'user_ident_or_text' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_or_text',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UserIdentOrTextWithIdentOrTextIdentOrText_db146b3c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ident_or_text',
        1 => '@',
        2 => 'ident_or_text',
      ),
    ),
  ),
  'user' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'user_ident_or_text',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UserWithCurrentUserOptionalBraces_c8a8d435',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'CURRENT_USER',
        1 => 'optional_braces',
      ),
    ),
  ),
  'role' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'role_ident_or_text',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleWithRoleIdentOrTextIdentOrText_c39e10ea',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'role_ident_or_text',
        1 => '@',
        2 => 'ident_or_text',
      ),
    ),
  ),
  'schema' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
  ),
  'ident_keyword' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_keywords_unambiguous',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_keywords_ambiguous_1_roles_and_labels',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_keywords_ambiguous_2_labels',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_keywords_ambiguous_3_roles',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_keywords_ambiguous_4_system_variables',
      ),
    ),
  ),
  'ident_keywords_ambiguous_1_roles_and_labels' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous1RolesAndLabelsWithExecuteSym_1db5e261',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EXECUTE_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous1RolesAndLabelsWithRestartSym_d353f294',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RESTART_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous1RolesAndLabelsWithShutdown_e9e2ce8b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SHUTDOWN',
      ),
    ),
  ),
  'ident_keywords_ambiguous_2_labels' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithAsciiSym_1fe4e6cf',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ASCII_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithBeginSym_42919a41',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BEGIN_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithByteSym_4d7761b4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BYTE_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithCacheSym_11d463b6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CACHE_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithCharset_14e56807',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CHARSET',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithChecksumSym_504d96b1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CHECKSUM_SYM',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithCloneSym_a77a1a17',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CLONE_SYM',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithCommentSym_e121fdc3',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COMMENT_SYM',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithCommitSym_23bf0130',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COMMIT_SYM',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithContainsSym_25f2b4ca',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CONTAINS_SYM',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithDeallocateSym_98d4f7fd',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DEALLOCATE_SYM',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithDoSym_ebe3b579',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DO_SYM',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithEnd_0041afd1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'END',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithFlushSym_f4c33a91',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FLUSH_SYM',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithFollowsSym_442c2786',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FOLLOWS_SYM',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithHandlerSym_812f7559',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HANDLER_SYM',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithHelpSym_7281164b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HELP_SYM',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithImport_5dc1dcd8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'IMPORT',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithInstallSym_4ddc69ae',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'INSTALL_SYM',
      ),
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithLanguageSym_db52aca5',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LANGUAGE_SYM',
      ),
    ),
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithNoSym_26c2122f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NO_SYM',
      ),
    ),
    21 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithPrecedesSym_67b58607',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PRECEDES_SYM',
      ),
    ),
    22 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithPrepareSym_3bcb1862',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PREPARE_SYM',
      ),
    ),
    23 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithRepair_9ce7dcd1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REPAIR',
      ),
    ),
    24 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithResetSym_905e9471',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RESET_SYM',
      ),
    ),
    25 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithRollbackSym_af042b59',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ROLLBACK_SYM',
      ),
    ),
    26 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithSavepointSym_094f670a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SAVEPOINT_SYM',
      ),
    ),
    27 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithSignedSym_0a3447ce',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SIGNED_SYM',
      ),
    ),
    28 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithSlave_38d2d218',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SLAVE',
      ),
    ),
    29 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithStartSym_7e35da06',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'START_SYM',
      ),
    ),
    30 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithStopSym_89e19462',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'STOP_SYM',
      ),
    ),
    31 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithTruncateSym_5df241aa',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TRUNCATE_SYM',
      ),
    ),
    32 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithUnicodeSym_0beff1b0',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UNICODE_SYM',
      ),
    ),
    33 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithUninstallSym_de956fa7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UNINSTALL_SYM',
      ),
    ),
    34 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous2LabelsWithXaSym_69660c67',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'XA_SYM',
      ),
    ),
  ),
  'label_keyword' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_keywords_unambiguous',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_keywords_ambiguous_3_roles',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_keywords_ambiguous_4_system_variables',
      ),
    ),
  ),
  'ident_keywords_ambiguous_3_roles' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous3RolesWithEventSym_78d4295b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EVENT_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous3RolesWithFileSym_8e2760fa',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FILE_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous3RolesWithNoneSym_d97793db',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NONE_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous3RolesWithProcess_a256d9ac',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PROCESS',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous3RolesWithProxySym_436d1d93',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PROXY_SYM',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous3RolesWithReload_610dc0ad',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RELOAD',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous3RolesWithReplication_283b5654',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REPLICATION',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous3RolesWithResourceSym_cfb93acb',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RESOURCE_SYM',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous3RolesWithSuperSym_eb90a858',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SUPER_SYM',
      ),
    ),
  ),
  'ident_keywords_unambiguous' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithAction_211bdc72',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ACTION',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithAccountSym_5b1f3a3e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ACCOUNT_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithActiveSym_5abbc086',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ACTIVE_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithAdddateSym_4bcef8ed',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ADDDATE_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithAdminSym_6d9e45af',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ADMIN_SYM',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithAfterSym_dfcbaf4f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'AFTER_SYM',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithAgainst_d431e1ee',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'AGAINST',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithAggregateSym_952d62c4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'AGGREGATE_SYM',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithAlgorithmSym_7e9eb492',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ALGORITHM_SYM',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithAlwaysSym_bddb8912',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ALWAYS_SYM',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithAnySym_cf46d6f7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ANY_SYM',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithArraySym_4ad32b25',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ARRAY_SYM',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithAtSym_d000be70',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'AT_SYM',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithAttributeSym_fb68a3cd',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ATTRIBUTE_SYM',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithAuthenticationSym_7d663dd1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'AUTHENTICATION_SYM',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithAutoextendSizeSym_076245f9',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'AUTOEXTEND_SIZE_SYM',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithAutoInc_20295995',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'AUTO_INC',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithAvgRowLength_c8ad48d9',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'AVG_ROW_LENGTH',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithAvgSym_3f4727c9',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'AVG_SYM',
      ),
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithBackupSym_e75935e3',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BACKUP_SYM',
      ),
    ),
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithBinlogSym_34fe5e13',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BINLOG_SYM',
      ),
    ),
    21 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithBitSym_c2a23d82',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BIT_SYM',
      ),
    ),
    22 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithBlockSym_e7a452d0',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BLOCK_SYM',
      ),
    ),
    23 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithBooleanSym_479cea5a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BOOLEAN_SYM',
      ),
    ),
    24 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithBoolSym_26605692',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BOOL_SYM',
      ),
    ),
    25 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithBtreeSym_3c108446',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BTREE_SYM',
      ),
    ),
    26 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithBucketsSym_c57a376e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BUCKETS_SYM',
      ),
    ),
    27 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithBulkSym_76bf9164',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BULK_SYM',
      ),
    ),
    28 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithCascaded_8611b092',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CASCADED',
      ),
    ),
    29 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithCatalogNameSym_fe5e3379',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CATALOG_NAME_SYM',
      ),
    ),
    30 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithChainSym_f8dadc6b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CHAIN_SYM',
      ),
    ),
    31 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithChallengeResponseSym_4ecb97b5',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CHALLENGE_RESPONSE_SYM',
      ),
    ),
    32 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithChanged_1d100621',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CHANGED',
      ),
    ),
    33 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithChannelSym_02e3ba57',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CHANNEL_SYM',
      ),
    ),
    34 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithCipherSym_1ff8f95c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CIPHER_SYM',
      ),
    ),
    35 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithClassOriginSym_37d49054',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CLASS_ORIGIN_SYM',
      ),
    ),
    36 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithClientSym_5f03e667',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CLIENT_SYM',
      ),
    ),
    37 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithCloseSym_8aaecbf6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CLOSE_SYM',
      ),
    ),
    38 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithCoalesce_983da7f9',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COALESCE',
      ),
    ),
    39 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithCodeSym_309f244d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CODE_SYM',
      ),
    ),
    40 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithCollationSym_b58da967',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COLLATION_SYM',
      ),
    ),
    41 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithColumns_1c295193',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COLUMNS',
      ),
    ),
    42 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithColumnFormatSym_79d6f323',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COLUMN_FORMAT_SYM',
      ),
    ),
    43 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithColumnNameSym_bfc4c518',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COLUMN_NAME_SYM',
      ),
    ),
    44 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithCommittedSym_025cbd9a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COMMITTED_SYM',
      ),
    ),
    45 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithCompactSym_ae150d56',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COMPACT_SYM',
      ),
    ),
    46 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithCompletionSym_6a7aa9c8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COMPLETION_SYM',
      ),
    ),
    47 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithComponentSym_eae23358',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COMPONENT_SYM',
      ),
    ),
    48 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithCompressedSym_731ae570',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COMPRESSED_SYM',
      ),
    ),
    49 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithCompressionSym_df44860f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COMPRESSION_SYM',
      ),
    ),
    50 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithConcurrent_9dee6a9e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CONCURRENT',
      ),
    ),
    51 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithConnectionSym_c59cd7d8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CONNECTION_SYM',
      ),
    ),
    52 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithConsistentSym_fd539ba9',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CONSISTENT_SYM',
      ),
    ),
    53 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithConstraintCatalogSym_680ddfb5',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CONSTRAINT_CATALOG_SYM',
      ),
    ),
    54 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithConstraintNameSym_4c4754cf',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CONSTRAINT_NAME_SYM',
      ),
    ),
    55 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithConstraintSchemaSym_086ee8ae',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CONSTRAINT_SCHEMA_SYM',
      ),
    ),
    56 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithContextSym_0d6c2726',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CONTEXT_SYM',
      ),
    ),
    57 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithCpuSym_16c777a4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CPU_SYM',
      ),
    ),
    58 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithCurrentSym_76bb84c2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CURRENT_SYM',
      ),
    ),
    59 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithCursorNameSym_dc604d4f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CURSOR_NAME_SYM',
      ),
    ),
    60 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithDatafileSym_fcf30099',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DATAFILE_SYM',
      ),
    ),
    61 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithDataSym_dc7fbfb6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DATA_SYM',
      ),
    ),
    62 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithDatetimeSym_a1ce6c31',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DATETIME_SYM',
      ),
    ),
    63 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithDateSym_3a73febe',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DATE_SYM',
      ),
    ),
    64 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithDaySym_ed3c5290',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DAY_SYM',
      ),
    ),
    65 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithDefaultAuthSym_421a993e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT_AUTH_SYM',
      ),
    ),
    66 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithDefinerSym_6f39182e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DEFINER_SYM',
      ),
    ),
    67 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithDefinitionSym_e7c90425',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DEFINITION_SYM',
      ),
    ),
    68 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithDelayKeyWriteSym_5c7e1f4b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DELAY_KEY_WRITE_SYM',
      ),
    ),
    69 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithDescriptionSym_49210044',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DESCRIPTION_SYM',
      ),
    ),
    70 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithDiagnosticsSym_6590672f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DIAGNOSTICS_SYM',
      ),
    ),
    71 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithDirectorySym_b030c57e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DIRECTORY_SYM',
      ),
    ),
    72 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithDisableSym_f9c03f05',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DISABLE_SYM',
      ),
    ),
    73 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithDiscardSym_6ec7a59e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DISCARD_SYM',
      ),
    ),
    74 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithDiskSym_61918584',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DISK_SYM',
      ),
    ),
    75 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithDumpfile_5408911f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DUMPFILE',
      ),
    ),
    76 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithDuplicateSym_f3dd499b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DUPLICATE_SYM',
      ),
    ),
    77 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithDynamicSym_69584ea1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DYNAMIC_SYM',
      ),
    ),
    78 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithEnableSym_ec8bb7b7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ENABLE_SYM',
      ),
    ),
    79 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithEncryptionSym_28b0683a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ENCRYPTION_SYM',
      ),
    ),
    80 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithEndsSym_9a89f905',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ENDS_SYM',
      ),
    ),
    81 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithEnforcedSym_57a08518',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ENFORCED_SYM',
      ),
    ),
    82 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithEnginesSym_ddf3742a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ENGINES_SYM',
      ),
    ),
    83 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithEngineSym_ce083f86',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ENGINE_SYM',
      ),
    ),
    84 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithEngineAttributeSym_2b954a27',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ENGINE_ATTRIBUTE_SYM',
      ),
    ),
    85 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithEnumSym_b28f408e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ENUM_SYM',
      ),
    ),
    86 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithErrors_6b8037ea',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ERRORS',
      ),
    ),
    87 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithErrorSym_8993d04b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ERROR_SYM',
      ),
    ),
    88 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithEscapeSym_cb1d4618',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ESCAPE_SYM',
      ),
    ),
    89 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithEventsSym_8543ac1d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EVENTS_SYM',
      ),
    ),
    90 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithEverySym_991be1c7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EVERY_SYM',
      ),
    ),
    91 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithExchangeSym_2026d2e7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EXCHANGE_SYM',
      ),
    ),
    92 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithExcludeSym_6ab051d4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EXCLUDE_SYM',
      ),
    ),
    93 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithExpansionSym_196f8972',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EXPANSION_SYM',
      ),
    ),
    94 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithExpireSym_e30f508a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EXPIRE_SYM',
      ),
    ),
    95 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithExportSym_0d10dc6c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EXPORT_SYM',
      ),
    ),
    96 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithExtendedSym_4835db79',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EXTENDED_SYM',
      ),
    ),
    97 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithExtentSizeSym_02e6543a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EXTENT_SIZE_SYM',
      ),
    ),
    98 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithFactorSym_716f35c2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FACTOR_SYM',
      ),
    ),
    99 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithFailedLoginAttemptsSym_23d2384e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FAILED_LOGIN_ATTEMPTS_SYM',
      ),
    ),
    100 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithFastSym_42d2f123',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FAST_SYM',
      ),
    ),
    101 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithFaultsSym_f848bb44',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FAULTS_SYM',
      ),
    ),
    102 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithFileBlockSizeSym_d8671449',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FILE_BLOCK_SIZE_SYM',
      ),
    ),
    103 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithFilterSym_746a3637',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FILTER_SYM',
      ),
    ),
    104 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithFinishSym_7b60575f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FINISH_SYM',
      ),
    ),
    105 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithFirstSym_660b8259',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FIRST_SYM',
      ),
    ),
    106 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithFixedSym_d00c5fd8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FIXED_SYM',
      ),
    ),
    107 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithFollowingSym_561326d7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FOLLOWING_SYM',
      ),
    ),
    108 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithFormatSym_6074a774',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FORMAT_SYM',
      ),
    ),
    109 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithFoundSym_dde84b2a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FOUND_SYM',
      ),
    ),
    110 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithFull_515def18',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FULL',
      ),
    ),
    111 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithGeneral_2d0205bc',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'GENERAL',
      ),
    ),
    112 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithGenerateSym_b6576d56',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'GENERATE_SYM',
      ),
    ),
    113 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithGeometrycollectionSym_a8096007',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'GEOMETRYCOLLECTION_SYM',
      ),
    ),
    114 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithGeometrySym_15d83ba8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'GEOMETRY_SYM',
      ),
    ),
    115 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithGetFormat_639b5453',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'GET_FORMAT',
      ),
    ),
    116 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithGetMasterPublicKeySym_8b930de4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'GET_MASTER_PUBLIC_KEY_SYM',
      ),
    ),
    117 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithGetSourcePublicKeySym_1bb0e5fe',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'GET_SOURCE_PUBLIC_KEY_SYM',
      ),
    ),
    118 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithGrants_1c41c976',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'GRANTS',
      ),
    ),
    119 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithGroupReplication_e7aede58',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'GROUP_REPLICATION',
      ),
    ),
    120 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithGtidOnlySym_a2c0688b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'GTID_ONLY_SYM',
      ),
    ),
    121 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithHashSym_1f0d25d4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HASH_SYM',
      ),
    ),
    122 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithHistogramSym_67754d5f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HISTOGRAM_SYM',
      ),
    ),
    123 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithHistorySym_7f79daa4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HISTORY_SYM',
      ),
    ),
    124 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithHostsSym_9fcdbd9e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HOSTS_SYM',
      ),
    ),
    125 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithHostSym_a5da9e21',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HOST_SYM',
      ),
    ),
    126 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithHourSym_5d5c7e13',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HOUR_SYM',
      ),
    ),
    127 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithIdentifiedSym_6132b006',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'IDENTIFIED_SYM',
      ),
    ),
    128 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithIgnoreServerIdsSym_017ac4cc',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'IGNORE_SERVER_IDS_SYM',
      ),
    ),
    129 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithInactiveSym_ecdf8b8f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'INACTIVE_SYM',
      ),
    ),
    130 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithIndexes_c8cb855c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'INDEXES',
      ),
    ),
    131 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithInitialSizeSym_a30c1733',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'INITIAL_SIZE_SYM',
      ),
    ),
    132 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithInitialSym_874f209c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'INITIAL_SYM',
      ),
    ),
    133 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithInitiateSym_59816210',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'INITIATE_SYM',
      ),
    ),
    134 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithInsertMethod_c7429ffd',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'INSERT_METHOD',
      ),
    ),
    135 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithInstanceSym_baeaf1a2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'INSTANCE_SYM',
      ),
    ),
    136 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithInvisibleSym_66897bac',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'INVISIBLE_SYM',
      ),
    ),
    137 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithInvokerSym_c31dbe08',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'INVOKER_SYM',
      ),
    ),
    138 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithIoSym_534d0553',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'IO_SYM',
      ),
    ),
    139 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithIpcSym_4f3212b6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'IPC_SYM',
      ),
    ),
    140 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithIsolation_c76675db',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ISOLATION',
      ),
    ),
    141 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithIssuerSym_5db45c7c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ISSUER_SYM',
      ),
    ),
    142 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithJsonSym_8b1bfcd1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'JSON_SYM',
      ),
    ),
    143 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithJsonValueSym_9bdf7ee0',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'JSON_VALUE_SYM',
      ),
    ),
    144 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithKeyBlockSize_e5cde7fa',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'KEY_BLOCK_SIZE',
      ),
    ),
    145 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithKeyringSym_61453348',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'KEYRING_SYM',
      ),
    ),
    146 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithLastSym_56b0d9b3',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LAST_SYM',
      ),
    ),
    147 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithLeaves_06493562',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LEAVES',
      ),
    ),
    148 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithLessSym_7e9ae2b7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LESS_SYM',
      ),
    ),
    149 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithLevelSym_89de8ae4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LEVEL_SYM',
      ),
    ),
    150 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithLinestringSym_a5f124de',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LINESTRING_SYM',
      ),
    ),
    151 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithListSym_7b19adad',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LIST_SYM',
      ),
    ),
    152 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithLockedSym_2a818e7d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LOCKED_SYM',
      ),
    ),
    153 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithLocksSym_f0f72d29',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LOCKS_SYM',
      ),
    ),
    154 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithLogfileSym_bbe4a61b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LOGFILE_SYM',
      ),
    ),
    155 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithLogsSym_a9537aff',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LOGS_SYM',
      ),
    ),
    156 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterAutoPositionSym_b232f7fe',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_AUTO_POSITION_SYM',
      ),
    ),
    157 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterCompressionAlgorithmSym_43be012a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_COMPRESSION_ALGORITHM_SYM',
      ),
    ),
    158 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterConnectRetrySym_308f0f5a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_CONNECT_RETRY_SYM',
      ),
    ),
    159 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterDelaySym_bcc95c61',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_DELAY_SYM',
      ),
    ),
    160 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterHeartbeatPeriodSym_13a793ec',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_HEARTBEAT_PERIOD_SYM',
      ),
    ),
    161 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterHostSym_54eebf14',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_HOST_SYM',
      ),
    ),
    162 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithNetworkNamespaceSym_80bec4dc',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NETWORK_NAMESPACE_SYM',
      ),
    ),
    163 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterLogFileSym_da8dfafa',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_LOG_FILE_SYM',
      ),
    ),
    164 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterLogPosSym_04f25939',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_LOG_POS_SYM',
      ),
    ),
    165 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterPasswordSym_57db6010',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_PASSWORD_SYM',
      ),
    ),
    166 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterPortSym_e194c377',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_PORT_SYM',
      ),
    ),
    167 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterPublicKeyPathSym_e0c5d3dd',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_PUBLIC_KEY_PATH_SYM',
      ),
    ),
    168 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterRetryCountSym_6ae527b5',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_RETRY_COUNT_SYM',
      ),
    ),
    169 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterSslCapathSym_286a1397',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CAPATH_SYM',
      ),
    ),
    170 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterSslCaSym_88518da6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CA_SYM',
      ),
    ),
    171 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterSslCertSym_cd770dba',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CERT_SYM',
      ),
    ),
    172 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterSslCipherSym_13abf197',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CIPHER_SYM',
      ),
    ),
    173 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterSslCrlpathSym_232c5be2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CRLPATH_SYM',
      ),
    ),
    174 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterSslCrlSym_dd2a6c26',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CRL_SYM',
      ),
    ),
    175 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterSslKeySym_02ad3534',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_KEY_SYM',
      ),
    ),
    176 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterSslSym_b805463d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_SYM',
      ),
    ),
    177 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterSym_e0799be4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SYM',
      ),
    ),
    178 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterTlsCiphersuitesSym_9e745bdd',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_TLS_CIPHERSUITES_SYM',
      ),
    ),
    179 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterTlsVersionSym_efce9b7a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_TLS_VERSION_SYM',
      ),
    ),
    180 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterUserSym_029b6617',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_USER_SYM',
      ),
    ),
    181 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMasterZstdCompressionLevelSym_1dda4664',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_ZSTD_COMPRESSION_LEVEL_SYM',
      ),
    ),
    182 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMaxConnectionsPerHour_1e5ca27f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MAX_CONNECTIONS_PER_HOUR',
      ),
    ),
    183 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMaxQueriesPerHour_c405850a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MAX_QUERIES_PER_HOUR',
      ),
    ),
    184 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMaxRows_9d58e083',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MAX_ROWS',
      ),
    ),
    185 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMaxSizeSym_88779a69',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MAX_SIZE_SYM',
      ),
    ),
    186 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMaxUpdatesPerHour_3232dcfe',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MAX_UPDATES_PER_HOUR',
      ),
    ),
    187 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMaxUserConnectionsSym_81fb266f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MAX_USER_CONNECTIONS_SYM',
      ),
    ),
    188 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMediumSym_02c1c214',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MEDIUM_SYM',
      ),
    ),
    189 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMemberSym_12b83b3d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MEMBER_SYM',
      ),
    ),
    190 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMemorySym_5c9428d6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MEMORY_SYM',
      ),
    ),
    191 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMergeSym_42cd69af',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MERGE_SYM',
      ),
    ),
    192 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMessageTextSym_2adea713',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MESSAGE_TEXT_SYM',
      ),
    ),
    193 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMicrosecondSym_d50abecb',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MICROSECOND_SYM',
      ),
    ),
    194 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMigrateSym_90b43df1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MIGRATE_SYM',
      ),
    ),
    195 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMinuteSym_e984cc3d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MINUTE_SYM',
      ),
    ),
    196 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMinRows_7eb9dd5c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MIN_ROWS',
      ),
    ),
    197 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithModeSym_239df34e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MODE_SYM',
      ),
    ),
    198 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithModifySym_80bbc3bd',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MODIFY_SYM',
      ),
    ),
    199 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMonthSym_625cee75',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MONTH_SYM',
      ),
    ),
    200 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMultilinestringSym_d205939a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MULTILINESTRING_SYM',
      ),
    ),
    201 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMultipointSym_75a1c975',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MULTIPOINT_SYM',
      ),
    ),
    202 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMultipolygonSym_bfc36f94',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MULTIPOLYGON_SYM',
      ),
    ),
    203 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMutexSym_a048786a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MUTEX_SYM',
      ),
    ),
    204 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithMysqlErrnoSym_2d516f63',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MYSQL_ERRNO_SYM',
      ),
    ),
    205 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithNamesSym_0fc79bc2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NAMES_SYM',
      ),
    ),
    206 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithNameSym_56f06beb',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NAME_SYM',
      ),
    ),
    207 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithNationalSym_97e20e4d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NATIONAL_SYM',
      ),
    ),
    208 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithNcharSym_af02179a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NCHAR_SYM',
      ),
    ),
    209 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithNdbclusterSym_d2169b87',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NDBCLUSTER_SYM',
      ),
    ),
    210 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithNestedSym_ca468bbf',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NESTED_SYM',
      ),
    ),
    211 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithNeverSym_79b71683',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NEVER_SYM',
      ),
    ),
    212 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithNewSym_62360230',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NEW_SYM',
      ),
    ),
    213 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithNextSym_0e408e21',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NEXT_SYM',
      ),
    ),
    214 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithNodegroupSym_f46a9409',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NODEGROUP_SYM',
      ),
    ),
    215 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithNowaitSym_d7617930',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NOWAIT_SYM',
      ),
    ),
    216 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithNoWaitSym_e9985cf2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NO_WAIT_SYM',
      ),
    ),
    217 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithNullsSym_a4dd977c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NULLS_SYM',
      ),
    ),
    218 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithNumberSym_589ecab1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NUMBER_SYM',
      ),
    ),
    219 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithNvarcharSym_d9687cae',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NVARCHAR_SYM',
      ),
    ),
    220 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithOffSym_9e42c205',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'OFF_SYM',
      ),
    ),
    221 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithOffsetSym_f1189a99',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'OFFSET_SYM',
      ),
    ),
    222 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithOjSym_d1cc60dc',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'OJ_SYM',
      ),
    ),
    223 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithOldSym_88e03077',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'OLD_SYM',
      ),
    ),
    224 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithOneSym_66c5e93b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ONE_SYM',
      ),
    ),
    225 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithOnlySym_0d23e668',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ONLY_SYM',
      ),
    ),
    226 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithOpenSym_d5a0a50b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'OPEN_SYM',
      ),
    ),
    227 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithOptionalSym_91035976',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'OPTIONAL_SYM',
      ),
    ),
    228 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithOptionsSym_8bbbc641',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'OPTIONS_SYM',
      ),
    ),
    229 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithOrdinalitySym_a977142e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ORDINALITY_SYM',
      ),
    ),
    230 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithOrganizationSym_f8da0e44',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ORGANIZATION_SYM',
      ),
    ),
    231 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithOthersSym_bd55481b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'OTHERS_SYM',
      ),
    ),
    232 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithOwnerSym_f794a3d9',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'OWNER_SYM',
      ),
    ),
    233 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithPackKeysSym_518870e8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PACK_KEYS_SYM',
      ),
    ),
    234 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithPageSym_652e70a5',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PAGE_SYM',
      ),
    ),
    235 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithParserSym_caccc49e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PARSER_SYM',
      ),
    ),
    236 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithPartial_3a342f69',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PARTIAL',
      ),
    ),
    237 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithPartitioningSym_bc09112f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PARTITIONING_SYM',
      ),
    ),
    238 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithPartitionsSym_1a25df5c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PARTITIONS_SYM',
      ),
    ),
    239 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithPassword_592707d1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
      ),
    ),
    240 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithPasswordLockTimeSym_07c14205',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD_LOCK_TIME_SYM',
      ),
    ),
    241 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithPathSym_3b083e02',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PATH_SYM',
      ),
    ),
    242 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithPhaseSym_22071fb4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PHASE_SYM',
      ),
    ),
    243 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithPluginsSym_7524a184',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PLUGINS_SYM',
      ),
    ),
    244 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithPluginDirSym_07633295',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PLUGIN_DIR_SYM',
      ),
    ),
    245 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithPluginSym_df541d30',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PLUGIN_SYM',
      ),
    ),
    246 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithPointSym_36b2fcaa',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'POINT_SYM',
      ),
    ),
    247 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithPolygonSym_4a6afc31',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'POLYGON_SYM',
      ),
    ),
    248 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithPortSym_6f7c1d36',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PORT_SYM',
      ),
    ),
    249 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithPrecedingSym_7dcb4d46',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PRECEDING_SYM',
      ),
    ),
    250 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithPreserveSym_0240021d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PRESERVE_SYM',
      ),
    ),
    251 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithPrevSym_70d99d06',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PREV_SYM',
      ),
    ),
    252 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithPrivileges_0396f291',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PRIVILEGES',
      ),
    ),
    253 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithPrivilegeChecksUserSym_a89050be',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PRIVILEGE_CHECKS_USER_SYM',
      ),
    ),
    254 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithProcesslistSym_251b902c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PROCESSLIST_SYM',
      ),
    ),
    255 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithProfilesSym_f337f425',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PROFILES_SYM',
      ),
    ),
    256 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithProfileSym_46750053',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PROFILE_SYM',
      ),
    ),
    257 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithQuarterSym_49159564',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'QUARTER_SYM',
      ),
    ),
    258 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithQuerySym_5c85ce0e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'QUERY_SYM',
      ),
    ),
    259 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithQuick_fee6b1c4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'QUICK',
      ),
    ),
    260 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRandomSym_cc8c8511',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RANDOM_SYM',
      ),
    ),
    261 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithReadOnlySym_5f573bfd',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'READ_ONLY_SYM',
      ),
    ),
    262 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRebuildSym_3c5e54ff',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REBUILD_SYM',
      ),
    ),
    263 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRecoverSym_df67ad8a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RECOVER_SYM',
      ),
    ),
    264 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRedoBufferSizeSym_67a42b75',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REDO_BUFFER_SIZE_SYM',
      ),
    ),
    265 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRedundantSym_4f47a09d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REDUNDANT_SYM',
      ),
    ),
    266 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithReferenceSym_3e9126fd',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REFERENCE_SYM',
      ),
    ),
    267 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRegistrationSym_530d36a8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REGISTRATION_SYM',
      ),
    ),
    268 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRelay_501688a0',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RELAY',
      ),
    ),
    269 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRelaylogSym_e1279dd3',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RELAYLOG_SYM',
      ),
    ),
    270 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRelayLogFileSym_627fb1aa',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RELAY_LOG_FILE_SYM',
      ),
    ),
    271 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRelayLogPosSym_a4d0170d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RELAY_LOG_POS_SYM',
      ),
    ),
    272 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRelayThread_a628563a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RELAY_THREAD',
      ),
    ),
    273 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRemoveSym_c7ddcbfb',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REMOVE_SYM',
      ),
    ),
    274 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithAssignGtidsToAnonymousTransactionsSym_4c6335c3',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS_SYM',
      ),
    ),
    275 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithReorganizeSym_c4239788',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REORGANIZE_SYM',
      ),
    ),
    276 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRepeatableSym_30b903f8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REPEATABLE_SYM',
      ),
    ),
    277 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithReplicasSym_525163a7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REPLICAS_SYM',
      ),
    ),
    278 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithReplicateDoDb_874572ec',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REPLICATE_DO_DB',
      ),
    ),
    279 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithReplicateDoTable_bd6916d5',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REPLICATE_DO_TABLE',
      ),
    ),
    280 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithReplicateIgnoreDb_103fb4d6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REPLICATE_IGNORE_DB',
      ),
    ),
    281 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithReplicateIgnoreTable_18c6066a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REPLICATE_IGNORE_TABLE',
      ),
    ),
    282 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithReplicateRewriteDb_7a081f51',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REPLICATE_REWRITE_DB',
      ),
    ),
    283 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithReplicateWildDoTable_cf93f470',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REPLICATE_WILD_DO_TABLE',
      ),
    ),
    284 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithReplicateWildIgnoreTable_2f616fcf',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REPLICATE_WILD_IGNORE_TABLE',
      ),
    ),
    285 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithReplicaSym_f3950fa7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REPLICA_SYM',
      ),
    ),
    286 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRequireRowFormatSym_0b0d7b42',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REQUIRE_ROW_FORMAT_SYM',
      ),
    ),
    287 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRequireTablePrimaryKeyCheckSym_3f1ee239',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REQUIRE_TABLE_PRIMARY_KEY_CHECK_SYM',
      ),
    ),
    288 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithResources_8ade25f8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RESOURCES',
      ),
    ),
    289 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRespectSym_ae4a6d13',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RESPECT_SYM',
      ),
    ),
    290 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRestoreSym_f65b3dea',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RESTORE_SYM',
      ),
    ),
    291 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithResumeSym_68384e60',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RESUME_SYM',
      ),
    ),
    292 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRetainSym_f71ee1a1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RETAIN_SYM',
      ),
    ),
    293 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithReturnedSqlstateSym_7f19402a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RETURNED_SQLSTATE_SYM',
      ),
    ),
    294 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithReturningSym_d8cdf73e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RETURNING_SYM',
      ),
    ),
    295 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithReturnsSym_dbe5d80e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RETURNS_SYM',
      ),
    ),
    296 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithReuseSym_645db05c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REUSE_SYM',
      ),
    ),
    297 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithReverseSym_1e7736ae',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REVERSE_SYM',
      ),
    ),
    298 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRoleSym_8b70b485',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ROLE_SYM',
      ),
    ),
    299 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRollupSym_a0e227ed',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ROLLUP_SYM',
      ),
    ),
    300 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRotateSym_b9708f59',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ROTATE_SYM',
      ),
    ),
    301 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRoutineSym_f37bb3a1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ROUTINE_SYM',
      ),
    ),
    302 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRowCountSym_38f9393f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ROW_COUNT_SYM',
      ),
    ),
    303 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRowFormatSym_12a7382f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ROW_FORMAT_SYM',
      ),
    ),
    304 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithRtreeSym_bb0e2d0b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RTREE_SYM',
      ),
    ),
    305 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithScheduleSym_fc56fe1f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SCHEDULE_SYM',
      ),
    ),
    306 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSchemaNameSym_7da228c3',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SCHEMA_NAME_SYM',
      ),
    ),
    307 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSecondaryEngineSym_50c8da6a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SECONDARY_ENGINE_SYM',
      ),
    ),
    308 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSecondaryEngineAttributeSym_3af9d230',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SECONDARY_ENGINE_ATTRIBUTE_SYM',
      ),
    ),
    309 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSecondaryLoadSym_c051477d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SECONDARY_LOAD_SYM',
      ),
    ),
    310 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSecondarySym_37d16147',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SECONDARY_SYM',
      ),
    ),
    311 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSecondaryUnloadSym_2d435c43',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SECONDARY_UNLOAD_SYM',
      ),
    ),
    312 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSecondSym_2fd62c64',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SECOND_SYM',
      ),
    ),
    313 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSecuritySym_cce36522',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SECURITY_SYM',
      ),
    ),
    314 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSerializableSym_76da3e35',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SERIALIZABLE_SYM',
      ),
    ),
    315 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSerialSym_2964dd4b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SERIAL_SYM',
      ),
    ),
    316 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithServerSym_1ca4d312',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SERVER_SYM',
      ),
    ),
    317 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithShareSym_ae0b67e2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SHARE_SYM',
      ),
    ),
    318 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSimpleSym_be572f46',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SIMPLE_SYM',
      ),
    ),
    319 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSkipSym_e3f2d98b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SKIP_SYM',
      ),
    ),
    320 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSlow_97e13030',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SLOW',
      ),
    ),
    321 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSnapshotSym_1aa37bc2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SNAPSHOT_SYM',
      ),
    ),
    322 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSocketSym_3a5dafd4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOCKET_SYM',
      ),
    ),
    323 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSonameSym_a82b83ff',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SONAME_SYM',
      ),
    ),
    324 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSoundsSym_c291443e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOUNDS_SYM',
      ),
    ),
    325 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceAutoPositionSym_e58e83fa',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_AUTO_POSITION_SYM',
      ),
    ),
    326 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceBindSym_30bcbacf',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_BIND_SYM',
      ),
    ),
    327 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceCompressionAlgorithmSym_0f7029b6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_COMPRESSION_ALGORITHM_SYM',
      ),
    ),
    328 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceConnectionAutoFailoverSym_67e59f0b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_CONNECTION_AUTO_FAILOVER_SYM',
      ),
    ),
    329 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceConnectRetrySym_a8cbcae9',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_CONNECT_RETRY_SYM',
      ),
    ),
    330 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceDelaySym_50fda366',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_DELAY_SYM',
      ),
    ),
    331 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceHeartbeatPeriodSym_5c30ca93',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_HEARTBEAT_PERIOD_SYM',
      ),
    ),
    332 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceHostSym_74ce0c4c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_HOST_SYM',
      ),
    ),
    333 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceLogFileSym_352aec29',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_LOG_FILE_SYM',
      ),
    ),
    334 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceLogPosSym_0435b81c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_LOG_POS_SYM',
      ),
    ),
    335 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourcePasswordSym_9f8b73be',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_PASSWORD_SYM',
      ),
    ),
    336 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourcePortSym_1d88cc85',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_PORT_SYM',
      ),
    ),
    337 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourcePublicKeyPathSym_5ddc6ce5',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_PUBLIC_KEY_PATH_SYM',
      ),
    ),
    338 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceRetryCountSym_fe819a80',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_RETRY_COUNT_SYM',
      ),
    ),
    339 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceSslCapathSym_ada306a9',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_SSL_CAPATH_SYM',
      ),
    ),
    340 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceSslCaSym_0705603e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_SSL_CA_SYM',
      ),
    ),
    341 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceSslCertSym_32fb7fe9',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_SSL_CERT_SYM',
      ),
    ),
    342 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceSslCipherSym_ff4c06e9',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_SSL_CIPHER_SYM',
      ),
    ),
    343 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceSslCrlpathSym_fa18ac20',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_SSL_CRLPATH_SYM',
      ),
    ),
    344 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceSslCrlSym_f5ac5d06',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_SSL_CRL_SYM',
      ),
    ),
    345 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceSslKeySym_f7611ba3',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_SSL_KEY_SYM',
      ),
    ),
    346 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceSslSym_5146d075',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_SSL_SYM',
      ),
    ),
    347 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceSslVerifyServerCertSym_26f98882',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_SSL_VERIFY_SERVER_CERT_SYM',
      ),
    ),
    348 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceSym_426a8016',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_SYM',
      ),
    ),
    349 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceTlsCiphersuitesSym_6b161fb1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_TLS_CIPHERSUITES_SYM',
      ),
    ),
    350 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceTlsVersionSym_aa7eca00',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_TLS_VERSION_SYM',
      ),
    ),
    351 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceUserSym_b0f99559',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_USER_SYM',
      ),
    ),
    352 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSourceZstdCompressionLevelSym_c7fb6bb2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_ZSTD_COMPRESSION_LEVEL_SYM',
      ),
    ),
    353 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSqlAfterGtids_a3f6d1b2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SQL_AFTER_GTIDS',
      ),
    ),
    354 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSqlAfterMtsGaps_fd48fddc',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SQL_AFTER_MTS_GAPS',
      ),
    ),
    355 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSqlBeforeGtids_8e91e4ed',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SQL_BEFORE_GTIDS',
      ),
    ),
    356 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSqlBufferResult_c3483c4a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SQL_BUFFER_RESULT',
      ),
    ),
    357 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSqlNoCacheSym_78290222',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SQL_NO_CACHE_SYM',
      ),
    ),
    358 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSqlThread_c0000c4a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SQL_THREAD',
      ),
    ),
    359 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSridSym_d26fddc4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SRID_SYM',
      ),
    ),
    360 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithStackedSym_d2613562',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'STACKED_SYM',
      ),
    ),
    361 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithStartsSym_86342541',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'STARTS_SYM',
      ),
    ),
    362 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithStatsAutoRecalcSym_5ffb9cde',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'STATS_AUTO_RECALC_SYM',
      ),
    ),
    363 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithStatsPersistentSym_5d1ce717',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'STATS_PERSISTENT_SYM',
      ),
    ),
    364 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithStatsSamplePagesSym_152be68a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'STATS_SAMPLE_PAGES_SYM',
      ),
    ),
    365 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithStatusSym_6e4cb76a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'STATUS_SYM',
      ),
    ),
    366 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithStorageSym_f5380f89',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'STORAGE_SYM',
      ),
    ),
    367 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithStreamSym_2190bdc2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'STREAM_SYM',
      ),
    ),
    368 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithStringSym_3133e737',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'STRING_SYM',
      ),
    ),
    369 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithStCollectSym_0a1abb1d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ST_COLLECT_SYM',
      ),
    ),
    370 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSubclassOriginSym_63b90bdd',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SUBCLASS_ORIGIN_SYM',
      ),
    ),
    371 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSubdateSym_3470e305',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SUBDATE_SYM',
      ),
    ),
    372 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSubjectSym_8de4a97e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SUBJECT_SYM',
      ),
    ),
    373 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSubpartitionsSym_d2475dd7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SUBPARTITIONS_SYM',
      ),
    ),
    374 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSubpartitionSym_f50c2c9c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SUBPARTITION_SYM',
      ),
    ),
    375 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSuspendSym_7af1b1b5',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SUSPEND_SYM',
      ),
    ),
    376 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSwapsSym_a81970a6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SWAPS_SYM',
      ),
    ),
    377 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithSwitchesSym_d5b2c3ed',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SWITCHES_SYM',
      ),
    ),
    378 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithTables_62736c0b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TABLES',
      ),
    ),
    379 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithTablespaceSym_76c58991',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TABLESPACE_SYM',
      ),
    ),
    380 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithTableChecksumSym_d2e9a32a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TABLE_CHECKSUM_SYM',
      ),
    ),
    381 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithTableNameSym_13824d27',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TABLE_NAME_SYM',
      ),
    ),
    382 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithTemporary_15009c95',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TEMPORARY',
      ),
    ),
    383 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithTemptableSym_2e70b398',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TEMPTABLE_SYM',
      ),
    ),
    384 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithTextSym_8722e189',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TEXT_SYM',
      ),
    ),
    385 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithThanSym_b921cf76',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'THAN_SYM',
      ),
    ),
    386 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithThreadPrioritySym_214320b6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'THREAD_PRIORITY_SYM',
      ),
    ),
    387 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithTiesSym_d63c12c8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TIES_SYM',
      ),
    ),
    388 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithTimestampAdd_bd6fa56e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TIMESTAMP_ADD',
      ),
    ),
    389 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithTimestampDiff_bc0b71bc',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TIMESTAMP_DIFF',
      ),
    ),
    390 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithTimestampSym_efc1c042',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TIMESTAMP_SYM',
      ),
    ),
    391 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithTimeSym_a7821f14',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TIME_SYM',
      ),
    ),
    392 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithTlsSym_d51b57c2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TLS_SYM',
      ),
    ),
    393 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithTransactionSym_93413939',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TRANSACTION_SYM',
      ),
    ),
    394 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithTriggersSym_d22dd48e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TRIGGERS_SYM',
      ),
    ),
    395 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithTypesSym_fa47c0ed',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TYPES_SYM',
      ),
    ),
    396 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithTypeSym_12571a32',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TYPE_SYM',
      ),
    ),
    397 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithUnboundedSym_c6e99dbc',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UNBOUNDED_SYM',
      ),
    ),
    398 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithUncommittedSym_a4cc30b1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UNCOMMITTED_SYM',
      ),
    ),
    399 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithUndefinedSym_1ae410bb',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UNDEFINED_SYM',
      ),
    ),
    400 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithUndofileSym_5c5957d9',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UNDOFILE_SYM',
      ),
    ),
    401 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithUndoBufferSizeSym_98550a95',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UNDO_BUFFER_SIZE_SYM',
      ),
    ),
    402 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithUnknownSym_a9256704',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UNKNOWN_SYM',
      ),
    ),
    403 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithUnregisterSym_4015e3fe',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UNREGISTER_SYM',
      ),
    ),
    404 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithUntilSym_8414ebfc',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UNTIL_SYM',
      ),
    ),
    405 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithUpgradeSym_bdd9663b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UPGRADE_SYM',
      ),
    ),
    406 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithUrlSym_f997aeb8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'URL_SYM',
      ),
    ),
    407 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithUser_6d81b477',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'USER',
      ),
    ),
    408 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithUseFrm_533711f1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'USE_FRM',
      ),
    ),
    409 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithValidationSym_10f5f3fb',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'VALIDATION_SYM',
      ),
    ),
    410 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithValueSym_dd0e07df',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'VALUE_SYM',
      ),
    ),
    411 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithVariables_377dd881',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'VARIABLES',
      ),
    ),
    412 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithVcpuSym_550abe51',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'VCPU_SYM',
      ),
    ),
    413 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithViewSym_387fe9c8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'VIEW_SYM',
      ),
    ),
    414 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithVisibleSym_176cc3cb',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'VISIBLE_SYM',
      ),
    ),
    415 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithWaitSym_fb71dd60',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'WAIT_SYM',
      ),
    ),
    416 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithWarnings_20ce517a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'WARNINGS',
      ),
    ),
    417 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithWeekSym_4d79b99b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'WEEK_SYM',
      ),
    ),
    418 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithWeightStringSym_64c75be5',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'WEIGHT_STRING_SYM',
      ),
    ),
    419 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithWithoutSym_c8cca437',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'WITHOUT_SYM',
      ),
    ),
    420 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithWorkSym_30476849',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'WORK_SYM',
      ),
    ),
    421 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithWrapperSym_6c9da9e7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'WRAPPER_SYM',
      ),
    ),
    422 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithX509Sym_20252505',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'X509_SYM',
      ),
    ),
    423 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithXidSym_0519de50',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'XID_SYM',
      ),
    ),
    424 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithXmlSym_4bf574b5',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'XML_SYM',
      ),
    ),
    425 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithYearSym_edbe4f16',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'YEAR_SYM',
      ),
    ),
    426 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsUnambiguousWithZoneSym_49d4d2e9',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ZONE_SYM',
      ),
    ),
  ),
  'role_keyword' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_keywords_unambiguous',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_keywords_ambiguous_2_labels',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_keywords_ambiguous_4_system_variables',
      ),
    ),
  ),
  'lvalue_keyword' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_keywords_unambiguous',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_keywords_ambiguous_1_roles_and_labels',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_keywords_ambiguous_2_labels',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_keywords_ambiguous_3_roles',
      ),
    ),
  ),
  'ident_keywords_ambiguous_4_system_variables' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous4SystemVariablesWithGlobalSym_ab45ac07',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'GLOBAL_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous4SystemVariablesWithLocalSym_9bd51047',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LOCAL_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous4SystemVariablesWithPersistSym_4fae4d31',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PERSIST_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous4SystemVariablesWithPersistOnlySym_0a899fad',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PERSIST_ONLY_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentKeywordsAmbiguous4SystemVariablesWithSessionSym_21907d7e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SESSION_SYM',
      ),
    ),
  ),
  'set' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SetWithSetSymStartOptionValueList_b040c859',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SET_SYM',
        1 => 'start_option_value_list',
      ),
    ),
  ),
  'start_option_value_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StartOptionValueListWithOptionValueNoOptionTypeOptionValueListContinued_0afb5a23',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'option_value_no_option_type',
        1 => 'option_value_list_continued',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StartOptionValueListWithTransactionSymTransactionCharacteristics_a2181e35',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'TRANSACTION_SYM',
        1 => 'transaction_characteristics',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StartOptionValueListWithOptionTypeStartOptionValueListFollowingOptionType_0ef6b4c8',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'option_type',
        1 => 'start_option_value_list_following_option_type',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StartOptionValueListWithPasswordEqualTextStringPasswordOptReplacePasswordOptRetainCurrentPassword_58cdfd80',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'equal',
        2 => 'TEXT_STRING_password',
        3 => 'opt_replace_password',
        4 => 'opt_retain_current_password',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StartOptionValueListWithPasswordToSymRandomSymOptReplacePasswordOptRetainCurrentPassword_dea853df',
      'fields' =>
      array (
        0 => 3,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'TO_SYM',
        2 => 'RANDOM_SYM',
        3 => 'opt_replace_password',
        4 => 'opt_retain_current_password',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StartOptionValueListWithPasswordForSymUserEqualTextStringPasswordOptReplacePasswordOptRetainCurrent_079dc170',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
        3 => 5,
        4 => 6,
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'FOR_SYM',
        2 => 'user',
        3 => 'equal',
        4 => 'TEXT_STRING_password',
        5 => 'opt_replace_password',
        6 => 'opt_retain_current_password',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StartOptionValueListWithPasswordForSymUserToSymRandomSymOptReplacePasswordOptRetainCurrentPassword_512cc013',
      'fields' =>
      array (
        0 => 2,
        1 => 5,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'FOR_SYM',
        2 => 'user',
        3 => 'TO_SYM',
        4 => 'RANDOM_SYM',
        5 => 'opt_replace_password',
        6 => 'opt_retain_current_password',
      ),
    ),
  ),
  'set_role_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SetRoleStmtWithSetSymRoleSymRoleList_d8677534',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SET_SYM',
        1 => 'ROLE_SYM',
        2 => 'role_list',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SetRoleStmtWithSetSymRoleSymNoneSym_995d0491',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SET_SYM',
        1 => 'ROLE_SYM',
        2 => 'NONE_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SetRoleStmtWithSetSymRoleSymDefaultSym_f0db8568',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SET_SYM',
        1 => 'ROLE_SYM',
        2 => 'DEFAULT_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SetRoleStmtWithSetSymDefaultSymRoleSymRoleListToSymRoleList_6df06ab1',
      'fields' =>
      array (
        0 => 3,
        1 => 5,
      ),
      'symbols' =>
      array (
        0 => 'SET_SYM',
        1 => 'DEFAULT_SYM',
        2 => 'ROLE_SYM',
        3 => 'role_list',
        4 => 'TO_SYM',
        5 => 'role_list',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SetRoleStmtWithSetSymDefaultSymRoleSymNoneSymToSymRoleList_f7b23178',
      'fields' =>
      array (
        0 => 5,
      ),
      'symbols' =>
      array (
        0 => 'SET_SYM',
        1 => 'DEFAULT_SYM',
        2 => 'ROLE_SYM',
        3 => 'NONE_SYM',
        4 => 'TO_SYM',
        5 => 'role_list',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SetRoleStmtWithSetSymDefaultSymRoleSymAllToSymRoleList_b77fd143',
      'fields' =>
      array (
        0 => 5,
      ),
      'symbols' =>
      array (
        0 => 'SET_SYM',
        1 => 'DEFAULT_SYM',
        2 => 'ROLE_SYM',
        3 => 'ALL',
        4 => 'TO_SYM',
        5 => 'role_list',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SetRoleStmtWithSetSymRoleSymAllOptExceptRoleList_ed0ec9aa',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'SET_SYM',
        1 => 'ROLE_SYM',
        2 => 'ALL',
        3 => 'opt_except_role_list',
      ),
    ),
  ),
  'opt_except_role_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptExceptRoleListWith_c2bb4362',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptExceptRoleListWithExceptSymRoleList_da437f3b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'EXCEPT_SYM',
        1 => 'role_list',
      ),
    ),
  ),
  'set_resource_group_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SetResourceGroupStmtWithSetSymResourceSymGroupSymIdent_89e61059',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'SET_SYM',
        1 => 'RESOURCE_SYM',
        2 => 'GROUP_SYM',
        3 => 'ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SetResourceGroupStmtWithSetSymResourceSymGroupSymIdentForSymThreadIdListOptions_4d174e9e',
      'fields' =>
      array (
        0 => 3,
        1 => 5,
      ),
      'symbols' =>
      array (
        0 => 'SET_SYM',
        1 => 'RESOURCE_SYM',
        2 => 'GROUP_SYM',
        3 => 'ident',
        4 => 'FOR_SYM',
        5 => 'thread_id_list_options',
      ),
    ),
  ),
  'thread_id_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'real_ulong_num',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ThreadIdListWithThreadIdListOptCommaRealUlongNum_d6a4389c',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'thread_id_list',
        1 => 'opt_comma',
        2 => 'real_ulong_num',
      ),
    ),
  ),
  'thread_id_list_options' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'thread_id_list',
      ),
    ),
  ),
  'start_option_value_list_following_option_type' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StartOptionValueListFollowingOptionTypeWithOptionValueFollowingOptionTypeOptionValueListContinued_9ede999a',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'option_value_following_option_type',
        1 => 'option_value_list_continued',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\StartOptionValueListFollowingOptionTypeWithTransactionSymTransactionCharacteristics_0cc88f35',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'TRANSACTION_SYM',
        1 => 'transaction_characteristics',
      ),
    ),
  ),
  'option_value_list_continued' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptionValueListContinuedWith_cd1f7195',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptionValueListContinuedWithOptionValueList_475fa04c',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => ',',
        1 => 'option_value_list',
      ),
    ),
  ),
  'option_value_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'option_value',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptionValueListWithOptionValueListOptionValue_0cde6392',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'option_value_list',
        1 => ',',
        2 => 'option_value',
      ),
    ),
  ),
  'option_value' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptionValueWithOptionTypeOptionValueFollowingOptionType_55e267e6',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'option_type',
        1 => 'option_value_following_option_type',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'option_value_no_option_type',
      ),
    ),
  ),
  'option_type' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptionTypeChoice_04c440c6::UseGlobal_e7440dd3',
      'symbols' =>
      array (
        0 => 'GLOBAL_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptionTypeChoice_04c440c6::UsePersist_f2539f68',
      'symbols' =>
      array (
        0 => 'PERSIST_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptionTypeChoice_04c440c6::UsePersistOnly_78133024',
      'symbols' =>
      array (
        0 => 'PERSIST_ONLY_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptionTypeChoice_04c440c6::UseLocal_646c1937',
      'symbols' =>
      array (
        0 => 'LOCAL_SYM',
      ),
    ),
    4 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptionTypeChoice_04c440c6::UseSession_cb66ec75',
      'symbols' =>
      array (
        0 => 'SESSION_SYM',
      ),
    ),
  ),
  'opt_var_type' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptVarTypeChoice_d22273d3::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptVarTypeChoice_d22273d3::UseGlobal_e7440dd3',
      'symbols' =>
      array (
        0 => 'GLOBAL_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptVarTypeChoice_d22273d3::UseLocal_646c1937',
      'symbols' =>
      array (
        0 => 'LOCAL_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptVarTypeChoice_d22273d3::UseSession_cb66ec75',
      'symbols' =>
      array (
        0 => 'SESSION_SYM',
      ),
    ),
  ),
  'opt_rvalue_system_variable_type' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptRvalueSystemVariableTypeChoice_3c76b304::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptRvalueSystemVariableTypeChoice_3c76b304::UseGlobal_d19f0c78',
      'symbols' =>
      array (
        0 => 'GLOBAL_SYM',
        1 => '.',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptRvalueSystemVariableTypeChoice_3c76b304::UseLocal_04ef36f9',
      'symbols' =>
      array (
        0 => 'LOCAL_SYM',
        1 => '.',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptRvalueSystemVariableTypeChoice_3c76b304::UseSession_ffe3f182',
      'symbols' =>
      array (
        0 => 'SESSION_SYM',
        1 => '.',
      ),
    ),
  ),
  'opt_set_var_ident_type' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptSetVarIdentTypeChoice_ff16f3c6::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptSetVarIdentTypeChoice_ff16f3c6::UsePersist_db55aeb0',
      'symbols' =>
      array (
        0 => 'PERSIST_SYM',
        1 => '.',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptSetVarIdentTypeChoice_ff16f3c6::UsePersistOnly_f85921fb',
      'symbols' =>
      array (
        0 => 'PERSIST_ONLY_SYM',
        1 => '.',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptSetVarIdentTypeChoice_ff16f3c6::UseGlobal_d19f0c78',
      'symbols' =>
      array (
        0 => 'GLOBAL_SYM',
        1 => '.',
      ),
    ),
    4 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptSetVarIdentTypeChoice_ff16f3c6::UseLocal_04ef36f9',
      'symbols' =>
      array (
        0 => 'LOCAL_SYM',
        1 => '.',
      ),
    ),
    5 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptSetVarIdentTypeChoice_ff16f3c6::UseSession_ffe3f182',
      'symbols' =>
      array (
        0 => 'SESSION_SYM',
        1 => '.',
      ),
    ),
  ),
  'option_value_following_option_type' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptionValueFollowingOptionTypeWithLvalueVariableEqualSetExprOrDefault_2d294c21',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'lvalue_variable',
        1 => 'equal',
        2 => 'set_expr_or_default',
      ),
    ),
  ),
  'option_value_no_option_type' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptionValueNoOptionTypeWithLvalueVariableEqualSetExprOrDefault_cf6aea74',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'lvalue_variable',
        1 => 'equal',
        2 => 'set_expr_or_default',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptionValueNoOptionTypeWithIdentOrTextEqualExpr_245dc00a',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => '@',
        1 => 'ident_or_text',
        2 => 'equal',
        3 => 'expr',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptionValueNoOptionTypeWithOptSetVarIdentTypeLvalueVariableEqualSetExprOrDefault_e39e1716',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => '@',
        1 => '@',
        2 => 'opt_set_var_ident_type',
        3 => 'lvalue_variable',
        4 => 'equal',
        5 => 'set_expr_or_default',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptionValueNoOptionTypeWithCharacterSetOldOrNewCharsetNameOrDefault_6dd2403f',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'character_set',
        1 => 'old_or_new_charset_name_or_default',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptionValueNoOptionTypeWithNamesSymEqualExpr_5c942a03',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'NAMES_SYM',
        1 => 'equal',
        2 => 'expr',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptionValueNoOptionTypeWithNamesSymCharsetNameOptCollate_c0fc4666',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'NAMES_SYM',
        1 => 'charset_name',
        2 => 'opt_collate',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptionValueNoOptionTypeWithNamesSymDefaultSym_e9c83fda',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NAMES_SYM',
        1 => 'DEFAULT_SYM',
      ),
    ),
  ),
  'lvalue_variable' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'lvalue_ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LvalueVariableWithLvalueIdentIdent_340b4df0',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'lvalue_ident',
        1 => '.',
        2 => 'ident',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LvalueVariableWithDefaultSymIdent_8608d7bd',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT_SYM',
        1 => '.',
        2 => 'ident',
      ),
    ),
  ),
  'rvalue_system_variable' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_or_text',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RvalueSystemVariableWithIdentOrTextIdent_aabbce5c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ident_or_text',
        1 => '.',
        2 => 'ident',
      ),
    ),
  ),
  'transaction_characteristics' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TransactionCharacteristicsWithTransactionAccessModeOptIsolationLevel_fe2860ad',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'transaction_access_mode',
        1 => 'opt_isolation_level',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TransactionCharacteristicsWithIsolationLevelOptTransactionAccessMode_4dd185b1',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'isolation_level',
        1 => 'opt_transaction_access_mode',
      ),
    ),
  ),
  'transaction_access_mode' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'transaction_access_mode_types',
      ),
    ),
  ),
  'opt_transaction_access_mode' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptTransactionAccessModeWith_262b2fc7',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptTransactionAccessModeWithTransactionAccessMode_459fca8a',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => ',',
        1 => 'transaction_access_mode',
      ),
    ),
  ),
  'isolation_level' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IsolationLevelWithIsolationLevelSymIsolationTypes_8770c38e',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ISOLATION',
        1 => 'LEVEL_SYM',
        2 => 'isolation_types',
      ),
    ),
  ),
  'opt_isolation_level' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIsolationLevelWith_af20fb7c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIsolationLevelWithIsolationLevel_ce203f13',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => ',',
        1 => 'isolation_level',
      ),
    ),
  ),
  'transaction_access_mode_types' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TransactionAccessModeTypesChoice_c78eb422::UseReadOnly_6628aa89',
      'symbols' =>
      array (
        0 => 'READ_SYM',
        1 => 'ONLY_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TransactionAccessModeTypesChoice_c78eb422::UseReadWrite_4c96461f',
      'symbols' =>
      array (
        0 => 'READ_SYM',
        1 => 'WRITE_SYM',
      ),
    ),
  ),
  'isolation_types' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\IsolationTypesChoice_5c144ac4::UseReadUncommitted_4875265c',
      'symbols' =>
      array (
        0 => 'READ_SYM',
        1 => 'UNCOMMITTED_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\IsolationTypesChoice_5c144ac4::UseReadCommitted_c09d6186',
      'symbols' =>
      array (
        0 => 'READ_SYM',
        1 => 'COMMITTED_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\IsolationTypesChoice_5c144ac4::UseRepeatableRead_f5f5acc3',
      'symbols' =>
      array (
        0 => 'REPEATABLE_SYM',
        1 => 'READ_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\IsolationTypesChoice_5c144ac4::UseSerializable_7dc421f3',
      'symbols' =>
      array (
        0 => 'SERIALIZABLE_SYM',
      ),
    ),
  ),
  'set_expr_or_default' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'expr',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SetExprOrDefaultWithDefaultSym_309d0e16',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SetExprOrDefaultWithOnSym_1fc78d8c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ON_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SetExprOrDefaultWithAll_271cc1b9',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ALL',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SetExprOrDefaultWithBinarySym_9125bd09',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'BINARY_SYM',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SetExprOrDefaultWithRowSym_1e371c0c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ROW_SYM',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SetExprOrDefaultWithSystemSym_9377d518',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SYSTEM_SYM',
      ),
    ),
  ),
  'lock' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LockWithLockSymTableOrTablesTableLockList_672e9c46',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'LOCK_SYM',
        1 => 'table_or_tables',
        2 => 'table_lock_list',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LockWithLockSymInstanceSymForSymBackupSym_27ba6008',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'LOCK_SYM',
        1 => 'INSTANCE_SYM',
        2 => 'FOR_SYM',
        3 => 'BACKUP_SYM',
      ),
    ),
  ),
  'table_or_tables' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TableOrTablesChoice_d5ef97e3::UseTable_52ca2fea',
      'symbols' =>
      array (
        0 => 'TABLE_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TableOrTablesChoice_d5ef97e3::UseTables_51f6ddbd',
      'symbols' =>
      array (
        0 => 'TABLES',
      ),
    ),
  ),
  'table_lock_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_lock',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableLockListWithTableLockListTableLock_991e923d',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_lock_list',
        1 => ',',
        2 => 'table_lock',
      ),
    ),
  ),
  'table_lock' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableLockWithTableIdentOptTableAliasLockOption_e396abc1',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_ident',
        1 => 'opt_table_alias',
        2 => 'lock_option',
      ),
    ),
  ),
  'lock_option' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\LockOptionChoice_475d9195::UseRead_3f563741',
      'symbols' =>
      array (
        0 => 'READ_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\LockOptionChoice_475d9195::UseWrite_a970ec59',
      'symbols' =>
      array (
        0 => 'WRITE_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\LockOptionChoice_475d9195::UseLowPriorityWrite_38a02701',
      'symbols' =>
      array (
        0 => 'LOW_PRIORITY',
        1 => 'WRITE_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\LockOptionChoice_475d9195::UseReadLocal_50ae732f',
      'symbols' =>
      array (
        0 => 'READ_SYM',
        1 => 'LOCAL_SYM',
      ),
    ),
  ),
  'unlock' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UnlockWithUnlockSymTableOrTables_557ef2da',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'UNLOCK_SYM',
        1 => 'table_or_tables',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UnlockWithUnlockSymInstanceSym_adc7c72d',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'UNLOCK_SYM',
        1 => 'INSTANCE_SYM',
      ),
    ),
  ),
  'shutdown_stmt' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ShutdownStmtChoice_eae96b68::UseShutdown_256ef5da',
      'symbols' =>
      array (
        0 => 'SHUTDOWN',
      ),
    ),
  ),
  'restart_server_stmt' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\RestartServerStmtChoice_d5b3be98::UseRestart_0ce77031',
      'symbols' =>
      array (
        0 => 'RESTART_SYM',
      ),
    ),
  ),
  'alter_instance_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterInstanceStmtWithAlterInstanceSymAlterInstanceAction_75204d40',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'INSTANCE_SYM',
        2 => 'alter_instance_action',
      ),
    ),
  ),
  'alter_instance_action' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterInstanceActionWithRotateSymIdentOrTextMasterSymKeySym_b558a985',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ROTATE_SYM',
        1 => 'ident_or_text',
        2 => 'MASTER_SYM',
        3 => 'KEY_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterInstanceActionWithReloadTlsSym_1a2f9ee2',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'RELOAD',
        1 => 'TLS_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterInstanceActionWithReloadTlsSymNoSymRollbackSymOnSymErrorSym_237208a3',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'RELOAD',
        1 => 'TLS_SYM',
        2 => 'NO_SYM',
        3 => 'ROLLBACK_SYM',
        4 => 'ON_SYM',
        5 => 'ERROR_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterInstanceActionWithReloadTlsSymForSymChannelSymIdent_05a01d5c',
      'fields' =>
      array (
        0 => 4,
      ),
      'symbols' =>
      array (
        0 => 'RELOAD',
        1 => 'TLS_SYM',
        2 => 'FOR_SYM',
        3 => 'CHANNEL_SYM',
        4 => 'ident',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterInstanceActionWithReloadTlsSymForSymChannelSymIdentNoSymRollbackSymOnSymErrorSym_198c53d6',
      'fields' =>
      array (
        0 => 4,
      ),
      'symbols' =>
      array (
        0 => 'RELOAD',
        1 => 'TLS_SYM',
        2 => 'FOR_SYM',
        3 => 'CHANNEL_SYM',
        4 => 'ident',
        5 => 'NO_SYM',
        6 => 'ROLLBACK_SYM',
        7 => 'ON_SYM',
        8 => 'ERROR_SYM',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterInstanceActionWithEnableSymIdentIdent_4ce356a8',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ENABLE_SYM',
        1 => 'ident',
        2 => 'ident',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterInstanceActionWithDisableSymIdentIdent_4822a805',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DISABLE_SYM',
        1 => 'ident',
        2 => 'ident',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterInstanceActionWithReloadKeyringSym_705686c5',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'RELOAD',
        1 => 'KEYRING_SYM',
      ),
    ),
  ),
  'handler_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\HandlerStmtWithHandlerSymTableIdentOpenSymOptTableAlias_2266b243',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'HANDLER_SYM',
        1 => 'table_ident',
        2 => 'OPEN_SYM',
        3 => 'opt_table_alias',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\HandlerStmtWithHandlerSymIdentCloseSym_b2167ee8',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'HANDLER_SYM',
        1 => 'ident',
        2 => 'CLOSE_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\HandlerStmtWithHandlerSymIdentReadSymHandlerScanFunctionOptWhereClauseOptLimitClause_f9f50968',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 4,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'HANDLER_SYM',
        1 => 'ident',
        2 => 'READ_SYM',
        3 => 'handler_scan_function',
        4 => 'opt_where_clause',
        5 => 'opt_limit_clause',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\HandlerStmtWithHandlerSymIdentReadSymIdentHandlerRkeyFunctionOptWhereClauseOptLimitClause_80e518c1',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 4,
        3 => 5,
        4 => 6,
      ),
      'symbols' =>
      array (
        0 => 'HANDLER_SYM',
        1 => 'ident',
        2 => 'READ_SYM',
        3 => 'ident',
        4 => 'handler_rkey_function',
        5 => 'opt_where_clause',
        6 => 'opt_limit_clause',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\HandlerStmtWithHandlerSymIdentReadSymIdentHandlerRkeyModeValuesOptWhereClauseOptLimitClaus_0dfa400d',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 4,
        3 => 6,
        4 => 8,
        5 => 9,
      ),
      'symbols' =>
      array (
        0 => 'HANDLER_SYM',
        1 => 'ident',
        2 => 'READ_SYM',
        3 => 'ident',
        4 => 'handler_rkey_mode',
        5 => '(',
        6 => 'values',
        7 => ')',
        8 => 'opt_where_clause',
        9 => 'opt_limit_clause',
      ),
    ),
  ),
  'handler_scan_function' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\HandlerScanFunctionChoice_95f48b47::UseFirst_267d3b81',
      'symbols' =>
      array (
        0 => 'FIRST_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\HandlerScanFunctionChoice_95f48b47::UseNext_7a66eabf',
      'symbols' =>
      array (
        0 => 'NEXT_SYM',
      ),
    ),
  ),
  'handler_rkey_function' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\HandlerRkeyFunctionChoice_792b25a8::UseFirst_267d3b81',
      'symbols' =>
      array (
        0 => 'FIRST_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\HandlerRkeyFunctionChoice_792b25a8::UseNext_7a66eabf',
      'symbols' =>
      array (
        0 => 'NEXT_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\HandlerRkeyFunctionChoice_792b25a8::UsePrev_39a40026',
      'symbols' =>
      array (
        0 => 'PREV_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\HandlerRkeyFunctionChoice_792b25a8::UseLast_7e86aeec',
      'symbols' =>
      array (
        0 => 'LAST_SYM',
      ),
    ),
  ),
  'handler_rkey_mode' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\HandlerRkeyModeChoice_bd7740f2::Use_380918b9',
      'symbols' =>
      array (
        0 => 'EQ',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\HandlerRkeyModeChoice_bd7740f2::Use_92a00d7d',
      'symbols' =>
      array (
        0 => 'GE',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\HandlerRkeyModeChoice_bd7740f2::Use_b60080dc',
      'symbols' =>
      array (
        0 => 'LE',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\HandlerRkeyModeChoice_bd7740f2::Use_62b67e1f',
      'symbols' =>
      array (
        0 => 'GT_SYM',
      ),
    ),
    4 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\HandlerRkeyModeChoice_bd7740f2::Use_dabd3aff',
      'symbols' =>
      array (
        0 => 'LT',
      ),
    ),
  ),
  'revoke' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RevokeWithRevokeIfExistsRoleOrPrivilegeListFromUserListOptIgnoreUnknownUser_c2541d92',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 4,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'REVOKE',
        1 => 'if_exists',
        2 => 'role_or_privilege_list',
        3 => 'FROM',
        4 => 'user_list',
        5 => 'opt_ignore_unknown_user',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RevokeWithRevokeIfExistsRoleOrPrivilegeListOnSymOptAclTypeGrantIdentFromUserListOptIg_4ab2eedb',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 4,
        3 => 5,
        4 => 7,
        5 => 8,
      ),
      'symbols' =>
      array (
        0 => 'REVOKE',
        1 => 'if_exists',
        2 => 'role_or_privilege_list',
        3 => 'ON_SYM',
        4 => 'opt_acl_type',
        5 => 'grant_ident',
        6 => 'FROM',
        7 => 'user_list',
        8 => 'opt_ignore_unknown_user',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RevokeWithRevokeIfExistsAllOptPrivilegesOnSymOptAclTypeGrantIdentFromUserListOptIgnor_81623cb0',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 5,
        3 => 6,
        4 => 8,
        5 => 9,
      ),
      'symbols' =>
      array (
        0 => 'REVOKE',
        1 => 'if_exists',
        2 => 'ALL',
        3 => 'opt_privileges',
        4 => 'ON_SYM',
        5 => 'opt_acl_type',
        6 => 'grant_ident',
        7 => 'FROM',
        8 => 'user_list',
        9 => 'opt_ignore_unknown_user',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RevokeWithRevokeIfExistsAllOptPrivilegesGrantOptionFromUserListOptIgnoreUnknownUser_bb55e984',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 8,
        3 => 9,
      ),
      'symbols' =>
      array (
        0 => 'REVOKE',
        1 => 'if_exists',
        2 => 'ALL',
        3 => 'opt_privileges',
        4 => ',',
        5 => 'GRANT',
        6 => 'OPTION',
        7 => 'FROM',
        8 => 'user_list',
        9 => 'opt_ignore_unknown_user',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RevokeWithRevokeIfExistsProxySymOnSymUserFromUserListOptIgnoreUnknownUser_19de934f',
      'fields' =>
      array (
        0 => 1,
        1 => 4,
        2 => 6,
        3 => 7,
      ),
      'symbols' =>
      array (
        0 => 'REVOKE',
        1 => 'if_exists',
        2 => 'PROXY_SYM',
        3 => 'ON_SYM',
        4 => 'user',
        5 => 'FROM',
        6 => 'user_list',
        7 => 'opt_ignore_unknown_user',
      ),
    ),
  ),
  'grant' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantWithGrantRoleOrPrivilegeListToSymUserListOptWithAdminOption_2491e436',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'GRANT',
        1 => 'role_or_privilege_list',
        2 => 'TO_SYM',
        3 => 'user_list',
        4 => 'opt_with_admin_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantWithGrantRoleOrPrivilegeListOnSymOptAclTypeGrantIdentToSymUserListGrantOptionsO_c652ce74',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 4,
        3 => 6,
        4 => 7,
        5 => 8,
      ),
      'symbols' =>
      array (
        0 => 'GRANT',
        1 => 'role_or_privilege_list',
        2 => 'ON_SYM',
        3 => 'opt_acl_type',
        4 => 'grant_ident',
        5 => 'TO_SYM',
        6 => 'user_list',
        7 => 'grant_options',
        8 => 'opt_grant_as',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantWithGrantAllOptPrivilegesOnSymOptAclTypeGrantIdentToSymUserListGrantOptionsOptG_5a90256e',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 5,
        3 => 7,
        4 => 8,
        5 => 9,
      ),
      'symbols' =>
      array (
        0 => 'GRANT',
        1 => 'ALL',
        2 => 'opt_privileges',
        3 => 'ON_SYM',
        4 => 'opt_acl_type',
        5 => 'grant_ident',
        6 => 'TO_SYM',
        7 => 'user_list',
        8 => 'grant_options',
        9 => 'opt_grant_as',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantWithGrantProxySymOnSymUserToSymUserListOptGrantOption_e6976216',
      'fields' =>
      array (
        0 => 3,
        1 => 5,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'GRANT',
        1 => 'PROXY_SYM',
        2 => 'ON_SYM',
        3 => 'user',
        4 => 'TO_SYM',
        5 => 'user_list',
        6 => 'opt_grant_option',
      ),
    ),
  ),
  'opt_acl_type' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptAclTypeChoice_64a9f425::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptAclTypeChoice_64a9f425::UseTable_52ca2fea',
      'symbols' =>
      array (
        0 => 'TABLE_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptAclTypeChoice_64a9f425::UseFunction_9d1aae1b',
      'symbols' =>
      array (
        0 => 'FUNCTION_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptAclTypeChoice_64a9f425::UseProcedure_26996b4a',
      'symbols' =>
      array (
        0 => 'PROCEDURE_SYM',
      ),
    ),
  ),
  'opt_privileges' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptPrivilegesChoice_d5b75b69::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptPrivilegesChoice_d5b75b69::UsePrivileges_1bcab5f9',
      'symbols' =>
      array (
        0 => 'PRIVILEGES',
      ),
    ),
  ),
  'role_or_privilege_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'role_or_privilege',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeListWithRoleOrPrivilegeListRoleOrPrivilege_c60e40d5',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'role_or_privilege_list',
        1 => ',',
        2 => 'role_or_privilege',
      ),
    ),
  ),
  'role_or_privilege' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithRoleIdentOrTextOptColumnList_3a296193',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'role_ident_or_text',
        1 => 'opt_column_list',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithRoleIdentOrTextIdentOrText_93b50921',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'role_ident_or_text',
        1 => '@',
        2 => 'ident_or_text',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithSelectSymOptColumnList_0074a75d',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SELECT_SYM',
        1 => 'opt_column_list',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithInsertSymOptColumnList_3bc08f5f',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'INSERT_SYM',
        1 => 'opt_column_list',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithUpdateSymOptColumnList_ac4fa9d0',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'UPDATE_SYM',
        1 => 'opt_column_list',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithReferencesOptColumnList_6953bf15',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'REFERENCES',
        1 => 'opt_column_list',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithDeleteSym_38c3a117',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DELETE_SYM',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithUsage_386a733b',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'USAGE',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithIndexSym_d1c6aa3a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'INDEX_SYM',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithAlter_b05da287',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithCreate_897bc5cd',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithDrop_5dbf4bbd',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DROP',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithExecuteSym_5b617b1d',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'EXECUTE_SYM',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithReload_f0f894ed',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'RELOAD',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithShutdown_952c014c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SHUTDOWN',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithProcess_f92b2b8e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'PROCESS',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithFileSym_e6c0b5bb',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'FILE_SYM',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithGrantOption_33594553',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'GRANT',
        1 => 'OPTION',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithShowDatabases_36c56519',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'DATABASES',
      ),
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithSuperSym_074e0efa',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SUPER_SYM',
      ),
    ),
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithCreateTemporaryTables_ba243d34',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'TEMPORARY',
        2 => 'TABLES',
      ),
    ),
    21 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithLockSymTables_4db5a564',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'LOCK_SYM',
        1 => 'TABLES',
      ),
    ),
    22 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithReplicationSlave_bbee8b5b',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'REPLICATION',
        1 => 'SLAVE',
      ),
    ),
    23 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithReplicationClientSym_26a0f7b1',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'REPLICATION',
        1 => 'CLIENT_SYM',
      ),
    ),
    24 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithCreateViewSym_c8af6314',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'VIEW_SYM',
      ),
    ),
    25 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithShowViewSym_da7e77bb',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'VIEW_SYM',
      ),
    ),
    26 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithCreateRoutineSym_0b80e748',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'ROUTINE_SYM',
      ),
    ),
    27 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithAlterRoutineSym_9485b5d4',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'ROUTINE_SYM',
      ),
    ),
    28 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithCreateUser_9fff189b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'USER',
      ),
    ),
    29 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithEventSym_3af57d37',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'EVENT_SYM',
      ),
    ),
    30 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithTriggerSym_6b38a9ce',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'TRIGGER_SYM',
      ),
    ),
    31 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithCreateTablespaceSym_9889930a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'TABLESPACE_SYM',
      ),
    ),
    32 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithCreateRoleSym_80a2d3ee',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'ROLE_SYM',
      ),
    ),
    33 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleOrPrivilegeWithDropRoleSym_89b0ed73',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'ROLE_SYM',
      ),
    ),
  ),
  'opt_with_admin_option' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptWithAdminOptionChoice_e74a1a4b::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptWithAdminOptionChoice_e74a1a4b::UseWithAdminOption_ab19e181',
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'ADMIN_SYM',
        2 => 'OPTION',
      ),
    ),
  ),
  'opt_and' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptAndChoice_e271f3ee::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptAndChoice_e271f3ee::UseAnd_bd972cc4',
      'symbols' =>
      array (
        0 => 'AND_SYM',
      ),
    ),
  ),
  'require_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RequireListWithRequireListElementOptAndRequireList_e30929c9',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'require_list_element',
        1 => 'opt_and',
        2 => 'require_list',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'require_list_element',
      ),
    ),
  ),
  'require_list_element' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RequireListElementWithSubjectSymTextString_d3843b66',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SUBJECT_SYM',
        1 => 'TEXT_STRING',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RequireListElementWithIssuerSymTextString_d75797bf',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ISSUER_SYM',
        1 => 'TEXT_STRING',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RequireListElementWithCipherSymTextString_c99a7d99',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'CIPHER_SYM',
        1 => 'TEXT_STRING',
      ),
    ),
  ),
  'grant_ident' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantIdentWith_c381063a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => '*',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantIdentWithSchema_2ee250a8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'schema',
        1 => '.',
        2 => '*',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantIdentWith_5378a188',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => '*',
        1 => '.',
        2 => '*',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantIdentWithSchemaIdent_e7f41800',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'schema',
        1 => '.',
        2 => 'ident',
      ),
    ),
  ),
  'user_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'user',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UserListWithUserListUser_e5bc299d',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'user_list',
        1 => ',',
        2 => 'user',
      ),
    ),
  ),
  'role_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'role',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RoleListWithRoleListRole_5636cce4',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'role_list',
        1 => ',',
        2 => 'role',
      ),
    ),
  ),
  'opt_retain_current_password' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptRetainCurrentPasswordChoice_86de168e::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptRetainCurrentPasswordChoice_86de168e::UseRetainCurrentPassword_07c5d02b',
      'symbols' =>
      array (
        0 => 'RETAIN_SYM',
        1 => 'CURRENT_SYM',
        2 => 'PASSWORD',
      ),
    ),
  ),
  'opt_discard_old_password' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptDiscardOldPasswordChoice_8e95507a::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptDiscardOldPasswordChoice_8e95507a::UseDiscardOldPassword_3d763f2b',
      'symbols' =>
      array (
        0 => 'DISCARD_SYM',
        1 => 'OLD_SYM',
        2 => 'PASSWORD',
      ),
    ),
  ),
  'opt_user_registration' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptUserRegistrationWithFactorInitiateSymRegistrationSym_72cdf86e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'factor',
        1 => 'INITIATE_SYM',
        2 => 'REGISTRATION_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptUserRegistrationWithFactorUnregisterSym_0707d171',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'factor',
        1 => 'UNREGISTER_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptUserRegistrationWithFactorFinishSymRegistrationSymSetSymChallengeResponseSymAsTextStringHash_8312d242',
      'fields' =>
      array (
        0 => 0,
        1 => 6,
      ),
      'symbols' =>
      array (
        0 => 'factor',
        1 => 'FINISH_SYM',
        2 => 'REGISTRATION_SYM',
        3 => 'SET_SYM',
        4 => 'CHALLENGE_RESPONSE_SYM',
        5 => 'AS',
        6 => 'TEXT_STRING_hash',
      ),
    ),
  ),
  'create_user' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateUserWithUserIdentificationOptCreateUserWithMfa_ae6dc213',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'identification',
        2 => 'opt_create_user_with_mfa',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateUserWithUserIdentifiedWithPluginOptInitialAuth_8f027d30',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'identified_with_plugin',
        2 => 'opt_initial_auth',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateUserWithUserOptCreateUserWithMfa_d0a5b61a',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'opt_create_user_with_mfa',
      ),
    ),
  ),
  'opt_create_user_with_mfa' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptCreateUserWithMfaWith_9979b8f2',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptCreateUserWithMfaWithAndSymIdentification_06115c9b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'AND_SYM',
        1 => 'identification',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptCreateUserWithMfaWithAndSymIdentificationAndSymIdentification_5ca3da17',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'AND_SYM',
        1 => 'identification',
        2 => 'AND_SYM',
        3 => 'identification',
      ),
    ),
  ),
  'identification' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'identified_by_password',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'identified_by_random_password',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'identified_with_plugin',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'identified_with_plugin_as_auth',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'identified_with_plugin_by_password',
      ),
    ),
    5 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'identified_with_plugin_by_random_password',
      ),
    ),
  ),
  'identified_by_password' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentifiedByPasswordWithIdentifiedSymByTextStringPassword_9bf26ffe',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'IDENTIFIED_SYM',
        1 => 'BY',
        2 => 'TEXT_STRING_password',
      ),
    ),
  ),
  'identified_by_random_password' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\IdentifiedByRandomPasswordChoice_694f299c::UseIdentifiedByRandomPassword_5799a57c',
      'symbols' =>
      array (
        0 => 'IDENTIFIED_SYM',
        1 => 'BY',
        2 => 'RANDOM_SYM',
        3 => 'PASSWORD',
      ),
    ),
  ),
  'identified_with_plugin' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentifiedWithPluginWithIdentifiedSymWithIdentOrText_bd19b4e6',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'IDENTIFIED_SYM',
        1 => 'WITH',
        2 => 'ident_or_text',
      ),
    ),
  ),
  'identified_with_plugin_as_auth' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentifiedWithPluginAsAuthWithIdentifiedSymWithIdentOrTextAsTextStringHash_8cc3642a',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'IDENTIFIED_SYM',
        1 => 'WITH',
        2 => 'ident_or_text',
        3 => 'AS',
        4 => 'TEXT_STRING_hash',
      ),
    ),
  ),
  'identified_with_plugin_by_password' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentifiedWithPluginByPasswordWithIdentifiedSymWithIdentOrTextByTextStringPassword_42647618',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'IDENTIFIED_SYM',
        1 => 'WITH',
        2 => 'ident_or_text',
        3 => 'BY',
        4 => 'TEXT_STRING_password',
      ),
    ),
  ),
  'identified_with_plugin_by_random_password' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentifiedWithPluginByRandomPasswordWithIdentifiedSymWithIdentOrTextByRandomSymPassword_caa7494e',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'IDENTIFIED_SYM',
        1 => 'WITH',
        2 => 'ident_or_text',
        3 => 'BY',
        4 => 'RANDOM_SYM',
        5 => 'PASSWORD',
      ),
    ),
  ),
  'opt_initial_auth' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptInitialAuthWithInitialSymAuthenticationSymIdentifiedByRandomPassword_6e35d393',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'INITIAL_SYM',
        1 => 'AUTHENTICATION_SYM',
        2 => 'identified_by_random_password',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptInitialAuthWithInitialSymAuthenticationSymIdentifiedWithPluginAsAuth_0ceed756',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'INITIAL_SYM',
        1 => 'AUTHENTICATION_SYM',
        2 => 'identified_with_plugin_as_auth',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptInitialAuthWithInitialSymAuthenticationSymIdentifiedByPassword_58c1e3e2',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'INITIAL_SYM',
        1 => 'AUTHENTICATION_SYM',
        2 => 'identified_by_password',
      ),
    ),
  ),
  'alter_user' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserWithUserIdentifiedByPasswordReplaceSymTextStringPasswordOptRetainCurrentPasswor_0cca8aac',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'identified_by_password',
        2 => 'REPLACE_SYM',
        3 => 'TEXT_STRING_password',
        4 => 'opt_retain_current_password',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserWithUserIdentifiedWithPluginByPasswordReplaceSymTextStringPasswordOptRetainCurr_8bca43fb',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'identified_with_plugin_by_password',
        2 => 'REPLACE_SYM',
        3 => 'TEXT_STRING_password',
        4 => 'opt_retain_current_password',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserWithUserIdentifiedByPasswordOptRetainCurrentPassword_9e91318b',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'identified_by_password',
        2 => 'opt_retain_current_password',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserWithUserIdentifiedByRandomPasswordOptRetainCurrentPassword_a707714a',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'identified_by_random_password',
        2 => 'opt_retain_current_password',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserWithUserIdentifiedByRandomPasswordReplaceSymTextStringPasswordOptRetainCurrentP_e51a5595',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'identified_by_random_password',
        2 => 'REPLACE_SYM',
        3 => 'TEXT_STRING_password',
        4 => 'opt_retain_current_password',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserWithUserIdentifiedWithPlugin_3798b3a6',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'identified_with_plugin',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserWithUserIdentifiedWithPluginAsAuthOptRetainCurrentPassword_16558756',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'identified_with_plugin_as_auth',
        2 => 'opt_retain_current_password',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserWithUserIdentifiedWithPluginByPasswordOptRetainCurrentPassword_265c315e',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'identified_with_plugin_by_password',
        2 => 'opt_retain_current_password',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserWithUserIdentifiedWithPluginByRandomPasswordOptRetainCurrentPassword_f4dfac91',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'identified_with_plugin_by_random_password',
        2 => 'opt_retain_current_password',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserWithUserOptDiscardOldPassword_7f045226',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'opt_discard_old_password',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserWithUserAddFactorIdentification_19730ba0',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'ADD',
        2 => 'factor',
        3 => 'identification',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserWithUserAddFactorIdentificationAddFactorIdentification_c0f223cc',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 5,
        4 => 6,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'ADD',
        2 => 'factor',
        3 => 'identification',
        4 => 'ADD',
        5 => 'factor',
        6 => 'identification',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserWithUserModifySymFactorIdentification_b516f75f',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'MODIFY_SYM',
        2 => 'factor',
        3 => 'identification',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserWithUserModifySymFactorIdentificationModifySymFactorIdentification_b7c61ea1',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 5,
        4 => 6,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'MODIFY_SYM',
        2 => 'factor',
        3 => 'identification',
        4 => 'MODIFY_SYM',
        5 => 'factor',
        6 => 'identification',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserWithUserDropFactor_9b51120f',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'DROP',
        2 => 'factor',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserWithUserDropFactorDropFactor_be850b18',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'DROP',
        2 => 'factor',
        3 => 'DROP',
        4 => 'factor',
      ),
    ),
  ),
  'factor' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FactorWithNumFactorSym_a638fac6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NUM',
        1 => 'FACTOR_SYM',
      ),
    ),
  ),
  'create_user_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'create_user',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateUserListWithCreateUserListCreateUser_9d8f4b00',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'create_user_list',
        1 => ',',
        2 => 'create_user',
      ),
    ),
  ),
  'alter_user_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_user',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserListWithAlterUserListAlterUser_d5e73d55',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'alter_user_list',
        1 => ',',
        2 => 'alter_user',
      ),
    ),
  ),
  'opt_column_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptColumnListWith_26e1cd95',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptColumnListWithColumnList_d1564309',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'column_list',
        2 => ')',
      ),
    ),
  ),
  'column_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnListWithColumnListIdent_a6859808',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'column_list',
        1 => ',',
        2 => 'ident',
      ),
    ),
  ),
  'require_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RequireClauseWith_6cc389b6',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RequireClauseWithRequireSymRequireList_7f57c21b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'REQUIRE_SYM',
        1 => 'require_list',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RequireClauseWithRequireSymSslSym_6e604454',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'REQUIRE_SYM',
        1 => 'SSL_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RequireClauseWithRequireSymX509Sym_cfb95b6c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'REQUIRE_SYM',
        1 => 'X509_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RequireClauseWithRequireSymNoneSym_74f76803',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'REQUIRE_SYM',
        1 => 'NONE_SYM',
      ),
    ),
  ),
  'grant_options' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\GrantOptionsChoice_d601384a::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\GrantOptionsChoice_d601384a::UseWithGrantOption_79cfb8cf',
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'GRANT',
        2 => 'OPTION',
      ),
    ),
  ),
  'opt_grant_option' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptGrantOptionChoice_d601384a::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptGrantOptionChoice_d601384a::UseWithGrantOption_79cfb8cf',
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'GRANT',
        2 => 'OPTION',
      ),
    ),
  ),
  'opt_with_roles' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptWithRolesWith_679edd34',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptWithRolesWithWithRoleSymRoleList_02cff40c',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'ROLE_SYM',
        2 => 'role_list',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptWithRolesWithWithRoleSymAllOptExceptRoleList_a988fee9',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'ROLE_SYM',
        2 => 'ALL',
        3 => 'opt_except_role_list',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptWithRolesWithWithRoleSymNoneSym_5d8b616f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'ROLE_SYM',
        2 => 'NONE_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptWithRolesWithWithRoleSymDefaultSym_9a4f4d87',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'ROLE_SYM',
        2 => 'DEFAULT_SYM',
      ),
    ),
  ),
  'opt_grant_as' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptGrantAsWith_8587d748',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptGrantAsWithAsUserOptWithRoles_f4059a23',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'AS',
        1 => 'user',
        2 => 'opt_with_roles',
      ),
    ),
  ),
  'begin_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BeginStmtWithBeginSymOptWork_b7d4ff9e',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'BEGIN_SYM',
        1 => 'opt_work',
      ),
    ),
  ),
  'opt_work' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptWorkChoice_822cb2b8::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptWorkChoice_822cb2b8::UseWork_00b82880',
      'symbols' =>
      array (
        0 => 'WORK_SYM',
      ),
    ),
  ),
  'opt_chain' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptChainChoice_bd59608e::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptChainChoice_bd59608e::UseAndNoChain_0b393eac',
      'symbols' =>
      array (
        0 => 'AND_SYM',
        1 => 'NO_SYM',
        2 => 'CHAIN_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptChainChoice_bd59608e::UseAndChain_ca6c40ba',
      'symbols' =>
      array (
        0 => 'AND_SYM',
        1 => 'CHAIN_SYM',
      ),
    ),
  ),
  'opt_release' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptReleaseChoice_2fd595ea::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptReleaseChoice_2fd595ea::UseRelease_cdb88be9',
      'symbols' =>
      array (
        0 => 'RELEASE_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptReleaseChoice_2fd595ea::UseNoRelease_0d0165df',
      'symbols' =>
      array (
        0 => 'NO_SYM',
        1 => 'RELEASE_SYM',
      ),
    ),
  ),
  'opt_savepoint' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptSavepointChoice_f1b77095::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptSavepointChoice_f1b77095::UseSavepoint_7e4dde5b',
      'symbols' =>
      array (
        0 => 'SAVEPOINT_SYM',
      ),
    ),
  ),
  'commit' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CommitWithCommitSymOptWorkOptChainOptRelease_b895fd3e',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'COMMIT_SYM',
        1 => 'opt_work',
        2 => 'opt_chain',
        3 => 'opt_release',
      ),
    ),
  ),
  'rollback' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RollbackWithRollbackSymOptWorkOptChainOptRelease_6c2ebc58',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ROLLBACK_SYM',
        1 => 'opt_work',
        2 => 'opt_chain',
        3 => 'opt_release',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RollbackWithRollbackSymOptWorkToSymOptSavepointIdent_9126be6a',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'ROLLBACK_SYM',
        1 => 'opt_work',
        2 => 'TO_SYM',
        3 => 'opt_savepoint',
        4 => 'ident',
      ),
    ),
  ),
  'savepoint' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SavepointWithSavepointSymIdent_99f173d5',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SAVEPOINT_SYM',
        1 => 'ident',
      ),
    ),
  ),
  'release' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ReleaseWithReleaseSymSavepointSymIdent_a23908b5',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'RELEASE_SYM',
        1 => 'SAVEPOINT_SYM',
        2 => 'ident',
      ),
    ),
  ),
  'union_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UnionOptionWith_97883012',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UnionOptionWithDistinct_a19a26df',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DISTINCT',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UnionOptionWithAll_98d89d34',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ALL',
      ),
    ),
  ),
  'row_subquery' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'subquery',
      ),
    ),
  ),
  'table_subquery' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'subquery',
      ),
    ),
  ),
  'subquery' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'query_expression_parens',
      ),
    ),
  ),
  'query_spec_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QuerySpecOptionWithStraightJoin_d90b9541',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'STRAIGHT_JOIN',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QuerySpecOptionWithHighPriority_b2c577c7',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'HIGH_PRIORITY',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QuerySpecOptionWithDistinct_81d2980d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DISTINCT',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QuerySpecOptionWithSqlSmallResult_8536083c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SQL_SMALL_RESULT',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QuerySpecOptionWithSqlBigResult_d24cc9f5',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SQL_BIG_RESULT',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QuerySpecOptionWithSqlBufferResult_264d7c2b',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SQL_BUFFER_RESULT',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QuerySpecOptionWithSqlCalcFoundRows_71f8f33f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SQL_CALC_FOUND_ROWS',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QuerySpecOptionWithAll_94793b3d',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ALL',
      ),
    ),
  ),
  'init_lex_create_info' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\InitLexCreateInfoChoice_055539df::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
  ),
  'view_or_trigger_or_sp_or_event' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ViewOrTriggerOrSpOrEventWithDefinerInitLexCreateInfoDefinerTail_a6c43105',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'definer',
        1 => 'init_lex_create_info',
        2 => 'definer_tail',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ViewOrTriggerOrSpOrEventWithNoDefinerInitLexCreateInfoNoDefinerTail_fbd4fea0',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'no_definer',
        1 => 'init_lex_create_info',
        2 => 'no_definer_tail',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ViewOrTriggerOrSpOrEventWithViewReplaceOrAlgorithmDefinerOptInitLexCreateInfoViewTail_e44cb8a0',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
      ),
      'symbols' =>
      array (
        0 => 'view_replace_or_algorithm',
        1 => 'definer_opt',
        2 => 'init_lex_create_info',
        3 => 'view_tail',
      ),
    ),
  ),
  'definer_tail' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'view_tail',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'trigger_tail',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_tail',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sf_tail',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'event_tail',
      ),
    ),
  ),
  'no_definer_tail' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'view_tail',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'trigger_tail',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sp_tail',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sf_tail',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'udf_tail',
      ),
    ),
    5 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'event_tail',
      ),
    ),
  ),
  'definer_opt' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'no_definer',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'definer',
      ),
    ),
  ),
  'no_definer' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\NoDefinerChoice_055539df::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
  ),
  'definer' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DefinerWithDefinerSymEqUser_14a5b907',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DEFINER_SYM',
        1 => 'EQ',
        2 => 'user',
      ),
    ),
  ),
  'view_replace_or_algorithm' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'view_replace',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ViewReplaceOrAlgorithmWithViewReplaceViewAlgorithm_62a87f11',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'view_replace',
        1 => 'view_algorithm',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'view_algorithm',
      ),
    ),
  ),
  'view_replace' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ViewReplaceChoice_a21ca266::UseOrReplace_85db5453',
      'symbols' =>
      array (
        0 => 'OR_SYM',
        1 => 'REPLACE_SYM',
      ),
    ),
  ),
  'view_algorithm' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ViewAlgorithmChoice_7c294a36::UseAlgorithmUndefined_647e35e1',
      'symbols' =>
      array (
        0 => 'ALGORITHM_SYM',
        1 => 'EQ',
        2 => 'UNDEFINED_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ViewAlgorithmChoice_7c294a36::UseAlgorithmMerge_5911336c',
      'symbols' =>
      array (
        0 => 'ALGORITHM_SYM',
        1 => 'EQ',
        2 => 'MERGE_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ViewAlgorithmChoice_7c294a36::UseAlgorithmTemptable_c36ebe23',
      'symbols' =>
      array (
        0 => 'ALGORITHM_SYM',
        1 => 'EQ',
        2 => 'TEMPTABLE_SYM',
      ),
    ),
  ),
  'view_suid' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ViewSuidChoice_0ddb4b67::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ViewSuidChoice_0ddb4b67::UseSqlSecurityDefiner_57d87183',
      'symbols' =>
      array (
        0 => 'SQL_SYM',
        1 => 'SECURITY_SYM',
        2 => 'DEFINER_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ViewSuidChoice_0ddb4b67::UseSqlSecurityInvoker_e5a5d4a3',
      'symbols' =>
      array (
        0 => 'SQL_SYM',
        1 => 'SECURITY_SYM',
        2 => 'INVOKER_SYM',
      ),
    ),
  ),
  'view_tail' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ViewTailWithViewSuidViewSymTableIdentOptDerivedColumnListAsViewQueryBlock_f953b024',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'view_suid',
        1 => 'VIEW_SYM',
        2 => 'table_ident',
        3 => 'opt_derived_column_list',
        4 => 'AS',
        5 => 'view_query_block',
      ),
    ),
  ),
  'view_query_block' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ViewQueryBlockWithQueryExpressionWithOptLockingClausesViewCheckOption_9ccd525e',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'query_expression_with_opt_locking_clauses',
        1 => 'view_check_option',
      ),
    ),
  ),
  'view_check_option' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ViewCheckOptionChoice_909a630e::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ViewCheckOptionChoice_909a630e::UseWithCheckOption_e6c3e346',
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'CHECK_SYM',
        2 => 'OPTION',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ViewCheckOptionChoice_909a630e::UseWithCascadedCheckOption_715d83ad',
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'CASCADED',
        2 => 'CHECK_SYM',
        3 => 'OPTION',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ViewCheckOptionChoice_909a630e::UseWithLocalCheckOption_f2637ad1',
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'LOCAL_SYM',
        2 => 'CHECK_SYM',
        3 => 'OPTION',
      ),
    ),
  ),
  'trigger_action_order' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TriggerActionOrderChoice_b52f2f95::UseFollows_f734af59',
      'symbols' =>
      array (
        0 => 'FOLLOWS_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TriggerActionOrderChoice_b52f2f95::UsePrecedes_bba1c15a',
      'symbols' =>
      array (
        0 => 'PRECEDES_SYM',
      ),
    ),
  ),
  'trigger_follows_precedes_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TriggerFollowsPrecedesClauseWith_bfb07267',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TriggerFollowsPrecedesClauseWithTriggerActionOrderIdentOrText_03d98e96',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'trigger_action_order',
        1 => 'ident_or_text',
      ),
    ),
  ),
  'trigger_tail' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TriggerTailWithTriggerSymOptIfNotExistsSpNameTrgActionTimeTrgEventOnSymTableIdentForSymEac_c374f3ee',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 6,
        5 => 10,
        6 => 11,
      ),
      'symbols' =>
      array (
        0 => 'TRIGGER_SYM',
        1 => 'opt_if_not_exists',
        2 => 'sp_name',
        3 => 'trg_action_time',
        4 => 'trg_event',
        5 => 'ON_SYM',
        6 => 'table_ident',
        7 => 'FOR_SYM',
        8 => 'EACH_SYM',
        9 => 'ROW_SYM',
        10 => 'trigger_follows_precedes_clause',
        11 => 'sp_proc_stmt',
      ),
    ),
  ),
  'udf_tail' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UdfTailWithAggregateSymFunctionSymOptIfNotExistsIdentReturnsSymUdfTypeSonameSymTextStr_05a7f90c',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 5,
        3 => 7,
      ),
      'symbols' =>
      array (
        0 => 'AGGREGATE_SYM',
        1 => 'FUNCTION_SYM',
        2 => 'opt_if_not_exists',
        3 => 'ident',
        4 => 'RETURNS_SYM',
        5 => 'udf_type',
        6 => 'SONAME_SYM',
        7 => 'TEXT_STRING_sys',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UdfTailWithFunctionSymOptIfNotExistsIdentReturnsSymUdfTypeSonameSymTextStringSys_fcb7ad54',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 4,
        3 => 6,
      ),
      'symbols' =>
      array (
        0 => 'FUNCTION_SYM',
        1 => 'opt_if_not_exists',
        2 => 'ident',
        3 => 'RETURNS_SYM',
        4 => 'udf_type',
        5 => 'SONAME_SYM',
        6 => 'TEXT_STRING_sys',
      ),
    ),
  ),
  'sf_tail' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SfTailWithFunctionSymOptIfNotExistsSpNameSpFdparamListReturnsSymTypeOptCollateSpCChis_07342a0a',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 4,
        3 => 7,
        4 => 8,
        5 => 9,
        6 => 10,
      ),
      'symbols' =>
      array (
        0 => 'FUNCTION_SYM',
        1 => 'opt_if_not_exists',
        2 => 'sp_name',
        3 => '(',
        4 => 'sp_fdparam_list',
        5 => ')',
        6 => 'RETURNS_SYM',
        7 => 'type',
        8 => 'opt_collate',
        9 => 'sp_c_chistics',
        10 => 'sp_proc_stmt',
      ),
    ),
  ),
  'sp_tail' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpTailWithProcedureSymOptIfNotExistsSpNameSpPdparamListSpCChisticsSpProcStmt_e4ef21a0',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 4,
        3 => 6,
        4 => 7,
      ),
      'symbols' =>
      array (
        0 => 'PROCEDURE_SYM',
        1 => 'opt_if_not_exists',
        2 => 'sp_name',
        3 => '(',
        4 => 'sp_pdparam_list',
        5 => ')',
        6 => 'sp_c_chistics',
        7 => 'sp_proc_stmt',
      ),
    ),
  ),
  'xa' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\XaWithXaSymBeginOrStartXidOptJoinOrResume_d0ed05c5',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'XA_SYM',
        1 => 'begin_or_start',
        2 => 'xid',
        3 => 'opt_join_or_resume',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\XaWithXaSymEndXidOptSuspend_7cb035e1',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'XA_SYM',
        1 => 'END',
        2 => 'xid',
        3 => 'opt_suspend',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\XaWithXaSymPrepareSymXid_f2824330',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'XA_SYM',
        1 => 'PREPARE_SYM',
        2 => 'xid',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\XaWithXaSymCommitSymXidOptOnePhase_40a38876',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'XA_SYM',
        1 => 'COMMIT_SYM',
        2 => 'xid',
        3 => 'opt_one_phase',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\XaWithXaSymRollbackSymXid_2e26c474',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'XA_SYM',
        1 => 'ROLLBACK_SYM',
        2 => 'xid',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\XaWithXaSymRecoverSymOptConvertXid_d3d65f9e',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'XA_SYM',
        1 => 'RECOVER_SYM',
        2 => 'opt_convert_xid',
      ),
    ),
  ),
  'opt_convert_xid' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptConvertXidChoice_b32c22a0::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptConvertXidChoice_b32c22a0::UseConvertXid_79b02145',
      'symbols' =>
      array (
        0 => 'CONVERT_SYM',
        1 => 'XID_SYM',
      ),
    ),
  ),
  'xid' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'text_string',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\XidWithTextStringTextString_966ae52f',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'text_string',
        1 => ',',
        2 => 'text_string',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\XidWithTextStringTextStringUlongNum_75cb1bab',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'text_string',
        1 => ',',
        2 => 'text_string',
        3 => ',',
        4 => 'ulong_num',
      ),
    ),
  ),
  'begin_or_start' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\BeginOrStartChoice_af1cfb24::UseBegin_a8402858',
      'symbols' =>
      array (
        0 => 'BEGIN_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\BeginOrStartChoice_af1cfb24::UseStart_39f17ec6',
      'symbols' =>
      array (
        0 => 'START_SYM',
      ),
    ),
  ),
  'opt_join_or_resume' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptJoinOrResumeChoice_f1195da4::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptJoinOrResumeChoice_f1195da4::UseJoin_a9e153ee',
      'symbols' =>
      array (
        0 => 'JOIN_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptJoinOrResumeChoice_f1195da4::UseResume_0747c96a',
      'symbols' =>
      array (
        0 => 'RESUME_SYM',
      ),
    ),
  ),
  'opt_one_phase' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptOnePhaseChoice_8cb47ec9::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptOnePhaseChoice_8cb47ec9::UseOnePhase_07d795e3',
      'symbols' =>
      array (
        0 => 'ONE_SYM',
        1 => 'PHASE_SYM',
      ),
    ),
  ),
  'opt_suspend' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptSuspendChoice_d418cef2::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptSuspendChoice_d418cef2::UseSuspend_3de4e07a',
      'symbols' =>
      array (
        0 => 'SUSPEND_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptSuspendChoice_d418cef2::UseSuspendForMigrate_b99d6a9b',
      'symbols' =>
      array (
        0 => 'SUSPEND_SYM',
        1 => 'FOR_SYM',
        2 => 'MIGRATE_SYM',
      ),
    ),
  ),
  'install_option_type' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\InstallOptionTypeChoice_da753f0b::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\InstallOptionTypeChoice_da753f0b::UseGlobal_e7440dd3',
      'symbols' =>
      array (
        0 => 'GLOBAL_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\InstallOptionTypeChoice_da753f0b::UsePersist_f2539f68',
      'symbols' =>
      array (
        0 => 'PERSIST_SYM',
      ),
    ),
  ),
  'install_set_rvalue' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'expr',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InstallSetRvalueWithOnSym_cbc4467a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ON_SYM',
      ),
    ),
  ),
  'install_set_value' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InstallSetValueWithInstallOptionTypeLvalueVariableEqualInstallSetRvalue_0926f8be',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
      ),
      'symbols' =>
      array (
        0 => 'install_option_type',
        1 => 'lvalue_variable',
        2 => 'equal',
        3 => 'install_set_rvalue',
      ),
    ),
  ),
  'install_set_value_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'install_set_value',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InstallSetValueListWithInstallSetValueListInstallSetValue_25dd7d7a',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'install_set_value_list',
        1 => ',',
        2 => 'install_set_value',
      ),
    ),
  ),
  'opt_install_set_value_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptInstallSetValueListWith_31e95d10',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptInstallSetValueListWithSetSymInstallSetValueList_cef00b1a',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SET_SYM',
        1 => 'install_set_value_list',
      ),
    ),
  ),
  'install_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InstallStmtWithInstallSymPluginSymIdentSonameSymTextStringSys_0b23adc8',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'INSTALL_SYM',
        1 => 'PLUGIN_SYM',
        2 => 'ident',
        3 => 'SONAME_SYM',
        4 => 'TEXT_STRING_sys',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InstallStmtWithInstallSymComponentSymTextStringSysListOptInstallSetValueList_74eb12bf',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'INSTALL_SYM',
        1 => 'COMPONENT_SYM',
        2 => 'TEXT_STRING_sys_list',
        3 => 'opt_install_set_value_list',
      ),
    ),
  ),
  'uninstall' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UninstallWithUninstallSymPluginSymIdent_c1c76092',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'UNINSTALL_SYM',
        1 => 'PLUGIN_SYM',
        2 => 'ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UninstallWithUninstallSymComponentSymTextStringSysList_10c0a3cb',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'UNINSTALL_SYM',
        1 => 'COMPONENT_SYM',
        2 => 'TEXT_STRING_sys_list',
      ),
    ),
  ),
  'TEXT_STRING_sys_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'TEXT_STRING_sys',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TextStringSysListWithTextStringSysListTextStringSys_c07b8dd1',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'TEXT_STRING_sys_list',
        1 => ',',
        2 => 'TEXT_STRING_sys',
      ),
    ),
  ),
  'import_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ImportStmtWithImportTableSymFromTextStringSysList_665ea253',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'IMPORT',
        1 => 'TABLE_SYM',
        2 => 'FROM',
        3 => 'TEXT_STRING_sys_list',
      ),
    ),
  ),
  'clone_stmt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CloneStmtWithCloneSymLocalSymDataSymDirectorySymOptEqualTextStringFilesystem_10e2a977',
      'fields' =>
      array (
        0 => 4,
        1 => 5,
      ),
      'symbols' =>
      array (
        0 => 'CLONE_SYM',
        1 => 'LOCAL_SYM',
        2 => 'DATA_SYM',
        3 => 'DIRECTORY_SYM',
        4 => 'opt_equal',
        5 => 'TEXT_STRING_filesystem',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CloneStmtWithCloneSymInstanceSymFromUserUlongNumIdentifiedSymByTextStringSysOptDatadirSs_e138e9c4',
      'fields' =>
      array (
        0 => 3,
        1 => 5,
        2 => 8,
        3 => 9,
      ),
      'symbols' =>
      array (
        0 => 'CLONE_SYM',
        1 => 'INSTANCE_SYM',
        2 => 'FROM',
        3 => 'user',
        4 => ':',
        5 => 'ulong_num',
        6 => 'IDENTIFIED_SYM',
        7 => 'BY',
        8 => 'TEXT_STRING_sys',
        9 => 'opt_datadir_ssl',
      ),
    ),
  ),
  'opt_datadir_ssl' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_ssl',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptDatadirSslWithDataSymDirectorySymOptEqualTextStringFilesystemOptSsl_2cffbf11',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'DATA_SYM',
        1 => 'DIRECTORY_SYM',
        2 => 'opt_equal',
        3 => 'TEXT_STRING_filesystem',
        4 => 'opt_ssl',
      ),
    ),
  ),
  'opt_ssl' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptSslChoice_3cebd19c::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptSslChoice_3cebd19c::UseRequireSsl_44b1b2ba',
      'symbols' =>
      array (
        0 => 'REQUIRE_SYM',
        1 => 'SSL_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptSslChoice_3cebd19c::UseRequireNoSsl_0ea862f8',
      'symbols' =>
      array (
        0 => 'REQUIRE_SYM',
        1 => 'NO_SYM',
        2 => 'SSL_SYM',
      ),
    ),
  ),
  'resource_group_types' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ResourceGroupTypesWithUser_085b7313',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'USER',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ResourceGroupTypesWithSystemSym_bdbef898',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SYSTEM_SYM',
      ),
    ),
  ),
  'opt_resource_group_vcpu_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptResourceGroupVcpuListWith_4571ffff',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptResourceGroupVcpuListWithVcpuSymOptEqualVcpuRangeSpecList_2441036d',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'VCPU_SYM',
        1 => 'opt_equal',
        2 => 'vcpu_range_spec_list',
      ),
    ),
  ),
  'vcpu_range_spec_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'vcpu_num_or_range',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\VcpuRangeSpecListWithVcpuRangeSpecListOptCommaVcpuNumOrRange_56b3cc9b',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'vcpu_range_spec_list',
        1 => 'opt_comma',
        2 => 'vcpu_num_or_range',
      ),
    ),
  ),
  'vcpu_num_or_range' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\VcpuNumOrRangeWithNum_abe91d2a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NUM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\VcpuNumOrRangeWithNumNum_87d45322',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'NUM',
        1 => '-',
        2 => 'NUM',
      ),
    ),
  ),
  'signed_num' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SignedNumWithNum_a38aa23f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NUM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SignedNumWithNum_b0aad25d',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '-',
        1 => 'NUM',
      ),
    ),
  ),
  'opt_resource_group_priority' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptResourceGroupPriorityWith_674362ea',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptResourceGroupPriorityWithThreadPrioritySymOptEqualSignedNum_b9f4ee77',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'THREAD_PRIORITY_SYM',
        1 => 'opt_equal',
        2 => 'signed_num',
      ),
    ),
  ),
  'opt_resource_group_enable_disable' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptResourceGroupEnableDisableChoice_b5e6950f::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptResourceGroupEnableDisableChoice_b5e6950f::UseEnable_18912667',
      'symbols' =>
      array (
        0 => 'ENABLE_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptResourceGroupEnableDisableChoice_b5e6950f::UseDisable_0fb87bd2',
      'symbols' =>
      array (
        0 => 'DISABLE_SYM',
      ),
    ),
  ),
  'opt_force' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptForceChoice_5e6890ca::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptForceChoice_5e6890ca::UseForce_bd16a503',
      'symbols' =>
      array (
        0 => 'FORCE_SYM',
      ),
    ),
  ),
  'json_attribute' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'TEXT_STRING_sys',
      ),
    ),
  ),
), array (
), array (
));
