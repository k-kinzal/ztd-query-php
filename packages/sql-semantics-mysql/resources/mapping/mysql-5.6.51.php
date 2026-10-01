<?php

declare(strict_types=1);

/** Generated construction recipes; never retained by a Statement. */
return new \SqlSemantics\Core\Analysis\Vocabulary(array (
  'query' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryWithEndOfInput_ba35e94a',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryWithVerbClauseOptEndOfInput_d35b69bf',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'verb_clause',
        1 => ';',
        2 => 'opt_end_of_input',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryWithVerbClauseEndOfInput_ecd8a92b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'verb_clause',
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
  'verb_clause' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'statement',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'begin',
      ),
    ),
  ),
  'statement' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'analyze',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'binlog_base64_event',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'call',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'change',
      ),
    ),
    5 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'check',
      ),
    ),
    6 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'checksum',
      ),
    ),
    7 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'commit',
      ),
    ),
    8 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'create',
      ),
    ),
    9 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'deallocate',
      ),
    ),
    10 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'delete',
      ),
    ),
    11 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'describe',
      ),
    ),
    12 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'do',
      ),
    ),
    13 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'drop',
      ),
    ),
    14 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'execute',
      ),
    ),
    15 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'flush',
      ),
    ),
    16 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'get_diagnostics',
      ),
    ),
    17 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'grant',
      ),
    ),
    18 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'handler',
      ),
    ),
    19 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'help',
      ),
    ),
    20 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'insert',
      ),
    ),
    21 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'install',
      ),
    ),
    22 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'kill',
      ),
    ),
    23 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'load',
      ),
    ),
    24 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'lock',
      ),
    ),
    25 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'optimize',
      ),
    ),
    26 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'keycache',
      ),
    ),
    27 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'partition_entry',
      ),
    ),
    28 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'preload',
      ),
    ),
    29 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'prepare',
      ),
    ),
    30 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'purge',
      ),
    ),
    31 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'release',
      ),
    ),
    32 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'rename',
      ),
    ),
    33 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'repair',
      ),
    ),
    34 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'replace',
      ),
    ),
    35 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'reset',
      ),
    ),
    36 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'resignal_stmt',
      ),
    ),
    37 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'revoke',
      ),
    ),
    38 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'rollback',
      ),
    ),
    39 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'savepoint',
      ),
    ),
    40 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'select',
      ),
    ),
    41 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'set',
      ),
    ),
    42 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'signal_stmt',
      ),
    ),
    43 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'show',
      ),
    ),
    44 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'slave',
      ),
    ),
    45 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'start',
      ),
    ),
    46 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'truncate',
      ),
    ),
    47 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'uninstall',
      ),
    ),
    48 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'unlock',
      ),
    ),
    49 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'update',
      ),
    ),
    50 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'use',
      ),
    ),
    51 =>
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
  'change' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ChangeWithChangeMasterSymToSymMasterDefs_6738ea9a',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'CHANGE',
        1 => 'MASTER_SYM',
        2 => 'TO_SYM',
        3 => 'master_defs',
      ),
    ),
  ),
  'master_defs' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'master_def',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefsWithMasterDefsMasterDef_98a7a6bb',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'master_defs',
        1 => ',',
        2 => 'master_def',
      ),
    ),
  ),
  'master_def' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefWithMasterHostSymEqTextStringSysNonewline_e23956bc',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_HOST_SYM',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefWithMasterBindSymEqTextStringSysNonewline_bbf9a813',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_BIND_SYM',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefWithMasterUserSymEqTextStringSysNonewline_cf902fd5',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_USER_SYM',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefWithMasterPasswordSymEqTextStringSysNonewline_b18e8e2a',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_PASSWORD_SYM',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefWithMasterPortSymEqUlongNum_fde7117e',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_PORT_SYM',
        1 => 'EQ',
        2 => 'ulong_num',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefWithMasterConnectRetrySymEqUlongNum_6d059a68',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_CONNECT_RETRY_SYM',
        1 => 'EQ',
        2 => 'ulong_num',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefWithMasterRetryCountSymEqUlongNum_d756eabd',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_RETRY_COUNT_SYM',
        1 => 'EQ',
        2 => 'ulong_num',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefWithMasterDelaySymEqUlongNum_69df80cf',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_DELAY_SYM',
        1 => 'EQ',
        2 => 'ulong_num',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefWithMasterSslSymEqUlongNum_8d19a23a',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_SYM',
        1 => 'EQ',
        2 => 'ulong_num',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefWithMasterSslCaSymEqTextStringSysNonewline_2448486c',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CA_SYM',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefWithMasterSslCapathSymEqTextStringSysNonewline_8912df23',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CAPATH_SYM',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefWithMasterSslCertSymEqTextStringSysNonewline_7bd36e61',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CERT_SYM',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefWithMasterSslCipherSymEqTextStringSysNonewline_a3dd3f76',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CIPHER_SYM',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefWithMasterSslKeySymEqTextStringSysNonewline_a51f0e4e',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_KEY_SYM',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefWithMasterSslVerifyServerCertSymEqUlongNum_a3500b35',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_VERIFY_SERVER_CERT_SYM',
        1 => 'EQ',
        2 => 'ulong_num',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefWithMasterSslCrlSymEqTextStringSysNonewline_27fc09c1',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CRL_SYM',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefWithMasterSslCrlpathSymEqTextStringSysNonewline_8fdee7b3',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CRLPATH_SYM',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefWithMasterHeartbeatPeriodSymEqNumLiteral_4f261576',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_HEARTBEAT_PERIOD_SYM',
        1 => 'EQ',
        2 => 'NUM_literal',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefWithIgnoreServerIdsSymEqIgnoreServerIdList_47b81a15',
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
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterDefWithMasterAutoPositionSymEqUlongNum_128f01b6',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_AUTO_POSITION_SYM',
        1 => 'EQ',
        2 => 'ulong_num',
      ),
    ),
    20 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'master_file_def',
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
  'master_file_def' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterFileDefWithMasterLogFileSymEqTextStringSysNonewline_fd9fae0d',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_LOG_FILE_SYM',
        1 => 'EQ',
        2 => 'TEXT_STRING_sys_nonewline',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterFileDefWithMasterLogPosSymEqUlonglongNum_b806f0bd',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_LOG_POS_SYM',
        1 => 'EQ',
        2 => 'ulonglong_num',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterFileDefWithRelayLogFileSymEqTextStringSysNonewline_2a92ead8',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MasterFileDefWithRelayLogPosSymEqUlongNum_0f3baec5',
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
  'create' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateWithCreateOptTableOptionsTableSymOptIfNotExistsTableIdentCreate2_b0ee61da',
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
        1 => 'opt_table_options',
        2 => 'TABLE_SYM',
        3 => 'opt_if_not_exists',
        4 => 'table_ident',
        5 => 'create2',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateWithCreateOptUniqueIndexSymIdentKeyAlgOnTableIdentKeyListNormalKeyOptionsOptInd_63f8ab84',
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
        4 => 'key_alg',
        5 => 'ON',
        6 => 'table_ident',
        7 => '(',
        8 => 'key_list',
        9 => ')',
        10 => 'normal_key_options',
        11 => 'opt_index_lock_algorithm',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateWithCreateFulltextIndexSymIdentInitKeyOptionsOnTableIdentKeyListFulltextKeyOpti_33172aa6',
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
        1 => 'fulltext',
        2 => 'INDEX_SYM',
        3 => 'ident',
        4 => 'init_key_options',
        5 => 'ON',
        6 => 'table_ident',
        7 => '(',
        8 => 'key_list',
        9 => ')',
        10 => 'fulltext_key_options',
        11 => 'opt_index_lock_algorithm',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateWithCreateSpatialIndexSymIdentInitKeyOptionsOnTableIdentKeyListSpatialKeyOption_fccf64fb',
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
        1 => 'spatial',
        2 => 'INDEX_SYM',
        3 => 'ident',
        4 => 'init_key_options',
        5 => 'ON',
        6 => 'table_ident',
        7 => '(',
        8 => 'key_list',
        9 => ')',
        10 => 'spatial_key_options',
        11 => 'opt_index_lock_algorithm',
      ),
    ),
    4 =>
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
    5 =>
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
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateWithCreateUserClearPrivilegesGrantList_59580399',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'USER',
        2 => 'clear_privileges',
        3 => 'grant_list',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateWithCreateLogfileSymGroupSymLogfileGroupInfo_f44c8450',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'LOGFILE_SYM',
        2 => 'GROUP_SYM',
        3 => 'logfile_group_info',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateWithCreateTablespaceTablespaceInfo_af9ac0ac',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'TABLESPACE',
        2 => 'tablespace_info',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateWithCreateServerDef_54cd5a88',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'server_def',
      ),
    ),
  ),
  'server_def' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ServerDefWithServerSymIdentOrTextForeignDataSymWrapperSymIdentOrTextOptionsSymServerOpti_b62dd54d',
      'fields' =>
      array (
        0 => 1,
        1 => 5,
        2 => 8,
      ),
      'symbols' =>
      array (
        0 => 'SERVER_SYM',
        1 => 'ident_or_text',
        2 => 'FOREIGN',
        3 => 'DATA_SYM',
        4 => 'WRAPPER_SYM',
        5 => 'ident_or_text',
        6 => 'OPTIONS_SYM',
        7 => '(',
        8 => 'server_options_list',
        9 => ')',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\EventTailWithRememberNameEventSymOptIfNotExistsSpNameOnScheduleSymEvScheduleTimeOptEvOnC_6c29f016',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 6,
        4 => 7,
        5 => 8,
        6 => 9,
        7 => 11,
      ),
      'symbols' =>
      array (
        0 => 'remember_name',
        1 => 'EVENT_SYM',
        2 => 'opt_if_not_exists',
        3 => 'sp_name',
        4 => 'ON',
        5 => 'SCHEDULE_SYM',
        6 => 'ev_schedule_time',
        7 => 'opt_ev_on_completion',
        8 => 'opt_ev_status',
        9 => 'opt_ev_comment',
        10 => 'DO_SYM',
        11 => 'ev_sql_stmt',
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
        1 => 'ON',
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
        0 => 'ON',
        1 => 'COMPLETION_SYM',
        2 => 'PRESERVE_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\EvOnCompletionChoice_87a9a3d9::UseOnCompletionNotPreserve_deea4e64',
      'symbols' =>
      array (
        0 => 'ON',
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
  'clear_privileges' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ClearPrivilegesChoice_055539df::Use_e3b0c442',
      'symbols' =>
      array (
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
  'call' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CallWithCallSymSpNameOptSpCparamList_50cac9e7',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'CALL_SYM',
        1 => 'sp_name',
        2 => 'opt_sp_cparam_list',
      ),
    ),
  ),
  'opt_sp_cparam_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSpCparamListWith_ca508351',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSpCparamListWithOptSpCparams_8a9a729f',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'opt_sp_cparams',
        2 => ')',
      ),
    ),
  ),
  'opt_sp_cparams' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSpCparamsWith_7edff6b8',
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
        0 => 'sp_cparams',
      ),
    ),
  ),
  'sp_cparams' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpCparamsWithSpCparamsExpr_279ab4b5',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'sp_cparams',
        1 => ',',
        2 => 'expr',
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
  'sp_init_param' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpInitParamChoice_055539df::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
  ),
  'sp_fdparam' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpFdparamWithIdentSpInitParamTypeWithOptCollate_e3ec649b',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => 'sp_init_param',
        2 => 'type_with_opt_collate',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpPdparamWithSpOptInoutSpInitParamIdentTypeWithOptCollate_065a814e',
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
        1 => 'sp_init_param',
        2 => 'ident',
        3 => 'type_with_opt_collate',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpDeclWithDeclareSymSpDeclIdentsTypeWithOptCollateSpOptDefault_0ec33897',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DECLARE_SYM',
        1 => 'sp_decl_idents',
        2 => 'type_with_opt_collate',
        3 => 'sp_opt_default',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpDeclWithDeclareSymIdentCursorSymForSymSelect_8e2448b4',
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
        4 => 'select',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSetSignalInformationWithSetSignalInformationItemList_398bb131',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SET',
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
        0 => 'literal',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'variable',
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
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\WhichAreaChoice_806f1a48::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\WhichAreaChoice_806f1a48::UseCurrent_e3cc57e1',
      'symbols' =>
      array (
        0 => 'CURRENT_SYM',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpOptDefaultWithDefaultExpr_0622c16f',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT',
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
        0 => 'statement',
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
        0 => 'INSERT',
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
  'change_tablespace_access' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ChangeTablespaceAccessWithTablespaceNameTsAccessMode_eb4b17e4',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'tablespace_name',
        1 => 'ts_access_mode',
      ),
    ),
  ),
  'change_tablespace_info' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ChangeTablespaceInfoWithTablespaceNameChangeTsDatafileChangeTsOptionList_1bc88ea1',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'tablespace_name',
        1 => 'CHANGE',
        2 => 'ts_datafile',
        3 => 'change_ts_option_list',
      ),
    ),
  ),
  'tablespace_info' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TablespaceInfoWithTablespaceNameAddTsDatafileOptLogfileGroupNameTablespaceOptionList_700b6ebb',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'tablespace_name',
        1 => 'ADD',
        2 => 'ts_datafile',
        3 => 'opt_logfile_group_name',
        4 => 'tablespace_option_list',
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
  'alter_tablespace_info' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterTablespaceInfoWithTablespaceNameAddTsDatafileAlterTablespaceOptionList_af59466d',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'tablespace_name',
        1 => 'ADD',
        2 => 'ts_datafile',
        3 => 'alter_tablespace_option_list',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterTablespaceInfoWithTablespaceNameDropTsDatafileAlterTablespaceOptionList_6a7e7393',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'tablespace_name',
        1 => 'DROP',
        2 => 'ts_datafile',
        3 => 'alter_tablespace_option_list',
      ),
    ),
  ),
  'logfile_group_info' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LogfileGroupInfoWithLogfileGroupNameAddLogFileLogfileGroupOptionList_7f46fa64',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'logfile_group_name',
        1 => 'add_log_file',
        2 => 'logfile_group_option_list',
      ),
    ),
  ),
  'alter_logfile_group_info' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterLogfileGroupInfoWithLogfileGroupNameAddLogFileAlterLogfileGroupOptionList_bf6dee3e',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'logfile_group_name',
        1 => 'add_log_file',
        2 => 'alter_logfile_group_option_list',
      ),
    ),
  ),
  'add_log_file' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AddLogFileWithAddLgUndofile_21cfd9f1',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ADD',
        1 => 'lg_undofile',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AddLogFileWithAddLgRedofile_5c41ff98',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ADD',
        1 => 'lg_redofile',
      ),
    ),
  ),
  'change_ts_option_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'change_ts_options',
      ),
    ),
  ),
  'change_ts_options' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'change_ts_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ChangeTsOptionsWithChangeTsOptionsChangeTsOption_49e2d65e',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'change_ts_options',
        1 => 'change_ts_option',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ChangeTsOptionsWithChangeTsOptionsChangeTsOption_4dcd5cd1',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'change_ts_options',
        1 => ',',
        2 => 'change_ts_option',
      ),
    ),
  ),
  'change_ts_option' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_ts_initial_size',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_ts_autoextend_size',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_ts_max_size',
      ),
    ),
  ),
  'tablespace_option_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TablespaceOptionListWith_5f11fc66',
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
        0 => 'tablespace_options',
      ),
    ),
  ),
  'tablespace_options' =>
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TablespaceOptionsWithTablespaceOptionsTablespaceOption_bf240f18',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'tablespace_options',
        1 => 'tablespace_option',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TablespaceOptionsWithTablespaceOptionsTablespaceOption_f33adb44',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'tablespace_options',
        1 => ',',
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
        0 => 'opt_ts_initial_size',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_ts_autoextend_size',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_ts_max_size',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_ts_extent_size',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_ts_nodegroup',
      ),
    ),
    5 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_ts_engine',
      ),
    ),
    6 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_wait',
      ),
    ),
    7 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_ts_comment',
      ),
    ),
  ),
  'alter_tablespace_option_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterTablespaceOptionListWith_6e006e95',
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
        0 => 'alter_tablespace_options',
      ),
    ),
  ),
  'alter_tablespace_options' =>
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterTablespaceOptionsWithAlterTablespaceOptionsAlterTablespaceOption_7e8110f1',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'alter_tablespace_options',
        1 => 'alter_tablespace_option',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterTablespaceOptionsWithAlterTablespaceOptionsAlterTablespaceOption_514a8fd4',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'alter_tablespace_options',
        1 => ',',
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
        0 => 'opt_ts_initial_size',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_ts_autoextend_size',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_ts_max_size',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_ts_engine',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_wait',
      ),
    ),
  ),
  'logfile_group_option_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LogfileGroupOptionListWith_299182eb',
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
        0 => 'logfile_group_options',
      ),
    ),
  ),
  'logfile_group_options' =>
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LogfileGroupOptionsWithLogfileGroupOptionsLogfileGroupOption_a224f064',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'logfile_group_options',
        1 => 'logfile_group_option',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LogfileGroupOptionsWithLogfileGroupOptionsLogfileGroupOption_f4d1d9a3',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'logfile_group_options',
        1 => ',',
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
        0 => 'opt_ts_initial_size',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_ts_undo_buffer_size',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_ts_redo_buffer_size',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_ts_nodegroup',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_ts_engine',
      ),
    ),
    5 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_wait',
      ),
    ),
    6 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_ts_comment',
      ),
    ),
  ),
  'alter_logfile_group_option_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterLogfileGroupOptionListWith_73c5b21f',
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
        0 => 'alter_logfile_group_options',
      ),
    ),
  ),
  'alter_logfile_group_options' =>
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterLogfileGroupOptionsWithAlterLogfileGroupOptionsAlterLogfileGroupOption_5a6bd6dc',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'alter_logfile_group_options',
        1 => 'alter_logfile_group_option',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterLogfileGroupOptionsWithAlterLogfileGroupOptionsAlterLogfileGroupOption_accac659',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'alter_logfile_group_options',
        1 => ',',
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
        0 => 'opt_ts_initial_size',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_ts_engine',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_wait',
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
  'lg_redofile' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LgRedofileWithRedofileSymTextStringSys_b11130f3',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'REDOFILE_SYM',
        1 => 'TEXT_STRING_sys',
      ),
    ),
  ),
  'tablespace_name' =>
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
  'logfile_group_name' =>
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
  'ts_access_mode' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TsAccessModeChoice_cbe239e2::UseReadOnly_9f9aad46',
      'symbols' =>
      array (
        0 => 'READ_ONLY_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TsAccessModeChoice_cbe239e2::UseReadWrite_9aaea82b',
      'symbols' =>
      array (
        0 => 'READ_WRITE_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TsAccessModeChoice_cbe239e2::UseNotAccessible_3c09698a',
      'symbols' =>
      array (
        0 => 'NOT_SYM',
        1 => 'ACCESSIBLE_SYM',
      ),
    ),
  ),
  'opt_ts_initial_size' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptTsInitialSizeWithInitialSizeSymOptEqualSizeNumber_8698bf6b',
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
  'opt_ts_autoextend_size' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptTsAutoextendSizeWithAutoextendSizeSymOptEqualSizeNumber_09f42647',
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
  'opt_ts_max_size' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptTsMaxSizeWithMaxSizeSymOptEqualSizeNumber_95e6c530',
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
  'opt_ts_extent_size' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptTsExtentSizeWithExtentSizeSymOptEqualSizeNumber_fcc60b68',
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
  'opt_ts_undo_buffer_size' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptTsUndoBufferSizeWithUndoBufferSizeSymOptEqualSizeNumber_c3a58cf7',
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
  'opt_ts_redo_buffer_size' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptTsRedoBufferSizeWithRedoBufferSizeSymOptEqualSizeNumber_76d17a96',
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
  'opt_ts_nodegroup' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptTsNodegroupWithNodegroupSymOptEqualRealUlongNum_44528873',
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
  'opt_ts_comment' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptTsCommentWithCommentSymOptEqualTextStringSys_2de8dc17',
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
  'opt_ts_engine' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptTsEngineWithOptStorageEngineSymOptEqualStorageEngines_cbf76920',
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
        3 => 'storage_engines',
      ),
    ),
  ),
  'ts_wait' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TsWaitChoice_06a79d39::UseWait_4f618185',
      'symbols' =>
      array (
        0 => 'WAIT_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TsWaitChoice_06a79d39::UseNoWait_c19ba2da',
      'symbols' =>
      array (
        0 => 'NO_WAIT_SYM',
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
  'create2' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\Create2WithCreate2a_3cd166e4',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'create2a',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\Create2WithOptCreateTableOptionsOptCreatePartitioningCreate3_933517e1',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'opt_create_table_options',
        1 => 'opt_create_partitioning',
        2 => 'create3',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\Create2WithLikeTableIdent_508cf527',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LIKE',
        1 => 'table_ident',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\Create2WithLikeTableIdent_4a8a45c8',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'LIKE',
        2 => 'table_ident',
        3 => ')',
      ),
    ),
  ),
  'create2a' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\Create2aWithCreateFieldListOptCreateTableOptionsOptCreatePartitioningCreate3_27f326e1',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'create_field_list',
        1 => ')',
        2 => 'opt_create_table_options',
        3 => 'opt_create_partitioning',
        4 => 'create3',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\Create2aWithOptCreatePartitioningCreateSelectUnionOpt_706ed2a4',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'opt_create_partitioning',
        1 => 'create_select',
        2 => ')',
        3 => 'union_opt',
      ),
    ),
  ),
  'create3' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\Create3With_495dd383',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\Create3WithOptDuplicateOptAsCreateSelectUnionClause_16b88082',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
      ),
      'symbols' =>
      array (
        0 => 'opt_duplicate',
        1 => 'opt_as',
        2 => 'create_select',
        3 => 'union_clause',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\Create3WithOptDuplicateOptAsCreateSelectUnionOpt_5e75a149',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'opt_duplicate',
        1 => 'opt_as',
        2 => '(',
        3 => 'create_select',
        4 => ')',
        5 => 'union_opt',
      ),
    ),
  ),
  'opt_create_partitioning' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_partitioning',
      ),
    ),
  ),
  'opt_partitioning' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPartitioningWith_5320be23',
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
        0 => 'partitioning',
      ),
    ),
  ),
  'partitioning' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartitioningWithPartitionSymHavePartitioningPartition_67c48bad',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'PARTITION_SYM',
        1 => 'have_partitioning',
        2 => 'partition',
      ),
    ),
  ),
  'have_partitioning' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\HavePartitioningChoice_055539df::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
  ),
  'partition_entry' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartitionEntryWithPartitionSymPartition_f83ddf1b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'PARTITION_SYM',
        1 => 'partition',
      ),
    ),
  ),
  'partition' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartitionWithByPartTypeDefOptNumPartsOptSubPartPartDefs_6f67ccc0',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'BY',
        1 => 'part_type_def',
        2 => 'opt_num_parts',
        3 => 'opt_sub_part',
        4 => 'part_defs',
      ),
    ),
  ),
  'part_type_def' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartTypeDefWithOptLinearKeySymOptKeyAlgoPartFieldList_f9de8824',
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
        4 => 'part_field_list',
        5 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartTypeDefWithOptLinearHashSymPartFunc_07a4ddda',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'opt_linear',
        1 => 'HASH_SYM',
        2 => 'part_func',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartTypeDefWithRangeSymPartFunc_8fe0da20',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'RANGE_SYM',
        1 => 'part_func',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartTypeDefWithRangeSymPartColumnList_cf3e79b9',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'RANGE_SYM',
        1 => 'part_column_list',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartTypeDefWithListSymPartFunc_683480a6',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LIST_SYM',
        1 => 'part_func',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartTypeDefWithListSymPartColumnList_ce7b7613',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LIST_SYM',
        1 => 'part_column_list',
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
  'part_field_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartFieldListWith_4a430917',
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
        0 => 'part_field_item_list',
      ),
    ),
  ),
  'part_field_item_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'part_field_item',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartFieldItemListWithPartFieldItemListPartFieldItem_fa9e734f',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'part_field_item_list',
        1 => ',',
        2 => 'part_field_item',
      ),
    ),
  ),
  'part_field_item' =>
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
  'part_column_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartColumnListWithColumnsPartFieldList_c3c31c1e',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'COLUMNS',
        1 => '(',
        2 => 'part_field_list',
        3 => ')',
      ),
    ),
  ),
  'part_func' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartFuncWithRememberNamePartFuncExprRememberEnd_bbccc98f',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'remember_name',
        2 => 'part_func_expr',
        3 => 'remember_end',
        4 => ')',
      ),
    ),
  ),
  'sub_part_func' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SubPartFuncWithRememberNamePartFuncExprRememberEnd_f9f43feb',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'remember_name',
        2 => 'part_func_expr',
        3 => 'remember_end',
        4 => ')',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSubPartWithSubpartitionSymByOptLinearHashSymSubPartFuncOptNumSubparts_59f39922',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'SUBPARTITION_SYM',
        1 => 'BY',
        2 => 'opt_linear',
        3 => 'HASH_SYM',
        4 => 'sub_part_func',
        5 => 'opt_num_subparts',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSubPartWithSubpartitionSymByOptLinearKeySymOptKeyAlgoSubPartFieldListOptNumSubparts_650cac4d',
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
        6 => 'sub_part_field_list',
        7 => ')',
        8 => 'opt_num_subparts',
      ),
    ),
  ),
  'sub_part_field_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sub_part_field_item',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SubPartFieldListWithSubPartFieldListSubPartFieldItem_f02497fe',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'sub_part_field_list',
        1 => ',',
        2 => 'sub_part_field_item',
      ),
    ),
  ),
  'sub_part_field_item' =>
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
  'part_func_expr' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'bit_expr',
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
  'part_defs' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartDefsWith_34d4679f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartDefsWithPartDefList_896be96d',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartDefinitionWithPartitionSymPartNameOptPartValuesOptPartOptionsOptSubPartition_d06fb090',
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
        1 => 'part_name',
        2 => 'opt_part_values',
        3 => 'opt_part_options',
        4 => 'opt_sub_partition',
      ),
    ),
  ),
  'part_name' =>
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
        0 => 'part_value_item',
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
        0 => 'part_value_item',
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
        0 => 'part_value_item',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartValueListWithPartValueListPartValueItem_16a27a24',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'part_value_list',
        1 => ',',
        2 => 'part_value_item',
      ),
    ),
  ),
  'part_value_item' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartValueItemWithPartValueItemList_1ca55d3b',
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
        0 => 'part_value_expr_item',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartValueItemListWithPartValueItemListPartValueExprItem_64fb07a9',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'part_value_item_list',
        1 => ',',
        2 => 'part_value_expr_item',
      ),
    ),
  ),
  'part_value_expr_item' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PartValueExprItemWithMaxValueSym_ce00a32e',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SubPartDefinitionWithSubpartitionSymSubNameOptPartOptions_ff97ee22',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SUBPARTITION_SYM',
        1 => 'sub_name',
        2 => 'opt_part_options',
      ),
    ),
  ),
  'sub_name' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_or_text',
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
        0 => 'opt_part_option_list',
      ),
    ),
  ),
  'opt_part_option_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPartOptionListWithOptPartOptionListOptPartOption_d72adc1d',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'opt_part_option_list',
        1 => 'opt_part_option',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_part_option',
      ),
    ),
  ),
  'opt_part_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPartOptionWithTablespaceOptEqualIdentOrText_d9d91fb3',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'TABLESPACE',
        1 => 'opt_equal',
        2 => 'ident_or_text',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPartOptionWithOptStorageEngineSymOptEqualStorageEngines_2e1cc07c',
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
        3 => 'storage_engines',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPartOptionWithNodegroupSymOptEqualRealUlongNum_2349bcf6',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPartOptionWithMaxRowsOptEqualRealUlonglongNum_1610a981',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPartOptionWithMinRowsOptEqualRealUlonglongNum_581f067f',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPartOptionWithDataSymDirectorySymOptEqualTextStringSys_ef9efa31',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPartOptionWithIndexSymDirectorySymOptEqualTextStringSys_f1b139b3',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptPartOptionWithCommentSymOptEqualTextStringSys_d8508aa4',
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
  'create_select' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateSelectWithSelectSymSelectOptionsSelectItemListOptSelectFrom_57fef56e',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'SELECT_SYM',
        1 => 'select_options',
        2 => 'select_item_list',
        3 => 'opt_select_from',
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
  ),
  'opt_table_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptTableOptionsWith_210b1118',
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
        0 => 'table_options',
      ),
    ),
  ),
  'table_options' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableOptionsWithTableOptionTableOptions_87262d47',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'table_option',
        1 => 'table_options',
      ),
    ),
  ),
  'table_option' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TableOptionChoice_9d12e5a1::UseTemporary_cb28e366',
      'symbols' =>
      array (
        0 => 'TEMPORARY',
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
  'opt_create_table_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptCreateTableOptionsWith_0a9c1ccc',
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
        0 => 'create_table_options',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionsSpaceSeparatedWithCreateTableOptionCreateTableOptionsSpaceSeparated_432133f8',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'create_table_option',
        1 => 'create_table_options_space_separated',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionsWithCreateTableOptionCreateTableOptions_7fba0361',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'create_table_option',
        1 => 'create_table_options',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionsWithCreateTableOptionCreateTableOptions_cbabf0e5',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'create_table_option',
        1 => ',',
        2 => 'create_table_options',
      ),
    ),
  ),
  'create_table_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithEngineSymOptEqualStorageEngines_100e79a7',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ENGINE_SYM',
        1 => 'opt_equal',
        2 => 'storage_engines',
      ),
    ),
    1 =>
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
    2 =>
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
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithAvgRowLengthOptEqualUlongNum_386b678f',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'AVG_ROW_LENGTH',
        1 => 'opt_equal',
        2 => 'ulong_num',
      ),
    ),
    4 =>
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
    5 =>
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
    6 =>
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
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithPackKeysSymOptEqualUlongNum_b4d940f9',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'PACK_KEYS_SYM',
        1 => 'opt_equal',
        2 => 'ulong_num',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithPackKeysSymOptEqualDefault_c55dbe28',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'PACK_KEYS_SYM',
        1 => 'opt_equal',
        2 => 'DEFAULT',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithStatsAutoRecalcSymOptEqualUlongNum_ad529de2',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'STATS_AUTO_RECALC_SYM',
        1 => 'opt_equal',
        2 => 'ulong_num',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithStatsAutoRecalcSymOptEqualDefault_1afa0bdf',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'STATS_AUTO_RECALC_SYM',
        1 => 'opt_equal',
        2 => 'DEFAULT',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithStatsPersistentSymOptEqualUlongNum_e45118b9',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'STATS_PERSISTENT_SYM',
        1 => 'opt_equal',
        2 => 'ulong_num',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithStatsPersistentSymOptEqualDefault_91217193',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'STATS_PERSISTENT_SYM',
        1 => 'opt_equal',
        2 => 'DEFAULT',
      ),
    ),
    13 =>
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
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithStatsSamplePagesSymOptEqualDefault_11a247b2',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'STATS_SAMPLE_PAGES_SYM',
        1 => 'opt_equal',
        2 => 'DEFAULT',
      ),
    ),
    15 =>
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
    16 =>
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
    17 =>
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
    18 =>
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
    19 =>
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
    20 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'default_charset',
      ),
    ),
    21 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'default_collation',
      ),
    ),
    22 =>
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
    23 =>
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
    24 =>
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
    25 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithTablespaceIdent_2b481a6a',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'TABLESPACE',
        1 => 'ident',
      ),
    ),
    26 =>
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
    27 =>
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
    28 =>
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
    29 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateTableOptionWithKeyBlockSizeOptEqualUlongNum_c6c2909f',
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
  ),
  'default_charset' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DefaultCharsetWithOptDefaultCharsetOptEqualCharsetNameOrDefault_2aa5fc3d',
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
        1 => 'charset',
        2 => 'opt_equal',
        3 => 'charset_name_or_default',
      ),
    ),
  ),
  'default_collation' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DefaultCollationWithOptDefaultCollateSymOptEqualCollationNameOrDefault_871d17c5',
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
        3 => 'collation_name_or_default',
      ),
    ),
  ),
  'storage_engines' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_or_text',
      ),
    ),
  ),
  'known_storage_engines' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_or_text',
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
        0 => 'DEFAULT',
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
  'opt_select_from' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'opt_limit_clause',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSelectFromWithSelectFromSelectLockType_ba6af1d9',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'select_from',
        1 => 'select_lock_type',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UdfTypeWithReal_8b240f94',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'REAL',
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
  'create_field_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'field_list',
      ),
    ),
  ),
  'field_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'field_list_item',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldListWithFieldListFieldListItem_ff2bc09a',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'field_list',
        1 => ',',
        2 => 'field_list_item',
      ),
    ),
  ),
  'field_list_item' =>
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
        0 => 'key_def',
      ),
    ),
  ),
  'column_def' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnDefWithFieldSpecOptCheckConstraint_d35fdf1e',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'field_spec',
        1 => 'opt_check_constraint',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnDefWithFieldSpecReferences_ac30ccce',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'field_spec',
        1 => 'references',
      ),
    ),
  ),
  'key_def' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyDefWithNormalKeyTypeOptIdentKeyAlgKeyListNormalKeyOptions_ba727e03',
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
        0 => 'normal_key_type',
        1 => 'opt_ident',
        2 => 'key_alg',
        3 => '(',
        4 => 'key_list',
        5 => ')',
        6 => 'normal_key_options',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyDefWithFulltextOptKeyOrIndexOptIdentInitKeyOptionsKeyListFulltextKeyOptions_cc58d1be',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
        4 => 5,
        5 => 7,
      ),
      'symbols' =>
      array (
        0 => 'fulltext',
        1 => 'opt_key_or_index',
        2 => 'opt_ident',
        3 => 'init_key_options',
        4 => '(',
        5 => 'key_list',
        6 => ')',
        7 => 'fulltext_key_options',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyDefWithSpatialOptKeyOrIndexOptIdentInitKeyOptionsKeyListSpatialKeyOptions_1d3d0c25',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
        4 => 5,
        5 => 7,
      ),
      'symbols' =>
      array (
        0 => 'spatial',
        1 => 'opt_key_or_index',
        2 => 'opt_ident',
        3 => 'init_key_options',
        4 => '(',
        5 => 'key_list',
        6 => ')',
        7 => 'spatial_key_options',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyDefWithOptConstraintConstraintKeyTypeOptIdentKeyAlgKeyListNormalKeyOptions_e87a95bd',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
        4 => 5,
        5 => 7,
      ),
      'symbols' =>
      array (
        0 => 'opt_constraint',
        1 => 'constraint_key_type',
        2 => 'opt_ident',
        3 => 'key_alg',
        4 => '(',
        5 => 'key_list',
        6 => ')',
        7 => 'normal_key_options',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyDefWithOptConstraintForeignKeySymOptIdentKeyListReferences_79d4ec54',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
        2 => 5,
        3 => 7,
      ),
      'symbols' =>
      array (
        0 => 'opt_constraint',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyDefWithOptConstraintCheckConstraint_cc3cfb49',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'opt_constraint',
        1 => 'check_constraint',
      ),
    ),
  ),
  'opt_check_constraint' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptCheckConstraintWith_d8bbe143',
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
        0 => 'check_constraint',
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
  'opt_constraint' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptConstraintWith_33664b87',
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
        0 => 'constraint',
      ),
    ),
  ),
  'constraint' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ConstraintWithConstraintOptIdent_e5b3b545',
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
  'field_spec' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldSpecWithFieldIdentTypeOptAttribute_85f031c2',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'field_ident',
        1 => 'type',
        2 => 'opt_attribute',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithFloatSymFloatOptionsFieldOptions_5933fc02',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'FLOAT_SYM',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithCharFieldLengthOptBinary_41d21526',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'char',
        1 => 'field_length',
        2 => 'opt_binary',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithCharOptBinary_6079e418',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'char',
        1 => 'opt_binary',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithBinaryFieldLength_9757c11d',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'BINARY',
        1 => 'field_length',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithBinary_6ff83ca7',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'BINARY',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithVarcharFieldLengthOptBinary_84aec1f0',
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
        2 => 'opt_binary',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithVarbinaryFieldLength_ae7cf96a',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'VARBINARY',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithTimestampTypeDatetimePrecision_e50bc9e5',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'TIMESTAMP',
        1 => 'type_datetime_precision',
      ),
    ),
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithDatetimeTypeDatetimePrecision_3404186e',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DATETIME',
        1 => 'type_datetime_precision',
      ),
    ),
    21 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithTinyblob_f1084533',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'TINYBLOB',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithMediumblob_6be9e9e2',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'MEDIUMBLOB',
      ),
    ),
    25 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithLongblob_d34fa034',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'LONGBLOB',
      ),
    ),
    26 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithLongSymVarbinary_8a8260f8',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'LONG_SYM',
        1 => 'VARBINARY',
      ),
    ),
    27 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithLongSymVarcharOptBinary_d9de9331',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'LONG_SYM',
        1 => 'varchar',
        2 => 'opt_binary',
      ),
    ),
    28 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithTinytextOptBinary_88dd6400',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'TINYTEXT',
        1 => 'opt_binary',
      ),
    ),
    29 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithTextSymOptFieldLengthOptBinary_27797852',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'TEXT_SYM',
        1 => 'opt_field_length',
        2 => 'opt_binary',
      ),
    ),
    30 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithMediumtextOptBinary_e192d635',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'MEDIUMTEXT',
        1 => 'opt_binary',
      ),
    ),
    31 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithLongtextOptBinary_c673272f',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LONGTEXT',
        1 => 'opt_binary',
      ),
    ),
    32 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithDecimalSymFloatOptionsFieldOptions_e596a6f5',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DECIMAL_SYM',
        1 => 'float_options',
        2 => 'field_options',
      ),
    ),
    33 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithNumericSymFloatOptionsFieldOptions_5970e0f8',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'NUMERIC_SYM',
        1 => 'float_options',
        2 => 'field_options',
      ),
    ),
    34 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithFixedSymFloatOptionsFieldOptions_d061231f',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'FIXED_SYM',
        1 => 'float_options',
        2 => 'field_options',
      ),
    ),
    35 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithEnumStringListOptBinary_a6e96516',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'ENUM',
        1 => '(',
        2 => 'string_list',
        3 => ')',
        4 => 'opt_binary',
      ),
    ),
    36 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithSetStringListOptBinary_6e800127',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'SET',
        1 => '(',
        2 => 'string_list',
        3 => ')',
        4 => 'opt_binary',
      ),
    ),
    37 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithLongSymOptBinary_375da842',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LONG_SYM',
        1 => 'opt_binary',
      ),
    ),
    38 =>
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
  ),
  'spatial_type' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpatialTypeChoice_f4a716ad::UseGeometry_79bf0dba',
      'symbols' =>
      array (
        0 => 'GEOMETRY_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpatialTypeChoice_f4a716ad::UseGeometrycollection_b4a9ed47',
      'symbols' =>
      array (
        0 => 'GEOMETRYCOLLECTION',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpatialTypeChoice_f4a716ad::UsePoint_ab6eeb6a',
      'symbols' =>
      array (
        0 => 'POINT_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpatialTypeChoice_f4a716ad::UseMultipoint_e397135f',
      'symbols' =>
      array (
        0 => 'MULTIPOINT',
      ),
    ),
    4 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpatialTypeChoice_f4a716ad::UseLinestring_65a47196',
      'symbols' =>
      array (
        0 => 'LINESTRING',
      ),
    ),
    5 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpatialTypeChoice_f4a716ad::UseMultilinestring_d8179c08',
      'symbols' =>
      array (
        0 => 'MULTILINESTRING',
      ),
    ),
    6 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpatialTypeChoice_f4a716ad::UsePolygon_3c0d0a78',
      'symbols' =>
      array (
        0 => 'POLYGON',
      ),
    ),
    7 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpatialTypeChoice_f4a716ad::UseMultipolygon_28e54070',
      'symbols' =>
      array (
        0 => 'MULTIPOLYGON',
      ),
    ),
  ),
  'char' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CharWithCharSym_92bfbc3c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CHAR_SYM',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\VarcharWithCharVarying_35d3e7d5',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'char',
        1 => 'VARYING',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\VarcharWithVarchar_572cfb70',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'VARCHAR',
      ),
    ),
  ),
  'nvarchar' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NvarcharWithNationalSymVarchar_b8cbcfb0',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'NATIONAL_SYM',
        1 => 'VARCHAR',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NvarcharWithNcharSymVarchar_30b2f5f7',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'NCHAR_SYM',
        1 => 'VARCHAR',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntTypeWithTinyint_f0bb1d92',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TINYINT',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntTypeWithSmallint_0a5fba5a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SMALLINT',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntTypeWithMediumint_63441b8c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MEDIUMINT',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntTypeWithBigint_5ee812ef',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BIGINT',
      ),
    ),
  ),
  'real_type' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RealTypeWithReal_6cf56914',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'REAL',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RealTypeWithDoubleSym_61a9d8ab',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DOUBLE_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RealTypeWithDoubleSymPrecision_b38c42c6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DOUBLE_SYM',
        1 => 'PRECISION',
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
        0 => 'UNSIGNED',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\FieldOptionChoice_01bed8e0::UseZerofill_34f1925c',
      'symbols' =>
      array (
        0 => 'ZEROFILL',
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
  'opt_attribute' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAttributeWith_ea7e6195',
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
        0 => 'opt_attribute_list',
      ),
    ),
  ),
  'opt_attribute_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptAttributeListWithOptAttributeListAttribute_1ec347af',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'opt_attribute_list',
        1 => 'attribute',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'attribute',
      ),
    ),
  ),
  'attribute' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AttributeWithNullSym_5fb6742b',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AttributeWithNotNullSym_44d088c5',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AttributeWithDefaultNowOrSignedLiteral_60702836',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT',
        1 => 'now_or_signed_literal',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AttributeWithOnUpdateSymNow_41ec9c95',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ON',
        1 => 'UPDATE_SYM',
        2 => 'now',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AttributeWithAutoInc_d2d248c4',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'AUTO_INC',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AttributeWithSerialSymDefaultValueSym_d8793195',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SERIAL_SYM',
        1 => 'DEFAULT',
        2 => 'VALUE_SYM',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AttributeWithOptPrimaryKeySym_5da35135',
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
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AttributeWithUniqueSym_84d13898',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'UNIQUE_SYM',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AttributeWithUniqueSymKeySym_f22f1694',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'UNIQUE_SYM',
        1 => 'KEY_SYM',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AttributeWithCommentSymTextStringSys_ffdde2d0',
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
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AttributeWithCollateSymCollationName_45a06530',
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
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AttributeWithColumnFormatSymDefault_3d34a72f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'COLUMN_FORMAT_SYM',
        1 => 'DEFAULT',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AttributeWithColumnFormatSymFixedSym_1d1651ea',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'COLUMN_FORMAT_SYM',
        1 => 'FIXED_SYM',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AttributeWithColumnFormatSymDynamicSym_34e472fd',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'COLUMN_FORMAT_SYM',
        1 => 'DYNAMIC_SYM',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AttributeWithStorageSymDefault_649f70af',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'STORAGE_SYM',
        1 => 'DEFAULT',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AttributeWithStorageSymDiskSym_b31dd352',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'STORAGE_SYM',
        1 => 'DISK_SYM',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AttributeWithStorageSymMemorySym_0bf46439',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'STORAGE_SYM',
        1 => 'MEMORY_SYM',
      ),
    ),
  ),
  'type_with_opt_collate' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TypeWithOptCollateWithTypeOptCollate_1bdf9bd9',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'type',
        1 => 'opt_collate',
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
        0 => 'signed_literal',
      ),
    ),
  ),
  'charset' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CharsetWithCharSymSet_86c13974',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CHAR_SYM',
        1 => 'SET',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CharsetWithCharset_97138eab',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CharsetNameWithBinary_3c9315e9',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'BINARY',
      ),
    ),
  ),
  'charset_name_or_default' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'charset_name',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CharsetNameOrDefaultWithDefault_c5f38169',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptLoadDataCharsetWithCharsetCharsetNameOrDefault_8cd3b1f1',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'charset',
        1 => 'charset_name_or_default',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OldOrNewCharsetNameWithBinary_40ef3127',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'BINARY',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OldOrNewCharsetNameOrDefaultWithDefault_3ece158e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptCollateWithCollateSymCollationNameOrDefault_db9f6f26',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'COLLATE_SYM',
        1 => 'collation_name_or_default',
      ),
    ),
  ),
  'collation_name_or_default' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'collation_name',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CollationNameOrDefaultWithDefault_8754324f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT',
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
        0 => 'DEFAULT',
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
        0 => 'BINARY',
        1 => 'ASCII_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\AsciiChoice_bcce0f9e::UseAsciiBinary_99b568cc',
      'symbols' =>
      array (
        0 => 'ASCII_SYM',
        1 => 'BINARY',
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
        1 => 'BINARY',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\UnicodeChoice_12e6823c::UseBinaryUnicode_62131883',
      'symbols' =>
      array (
        0 => 'BINARY',
        1 => 'UNICODE_SYM',
      ),
    ),
  ),
  'opt_binary' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptBinaryWith_c6088ec2',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptBinaryWithByteSym_14fb48fd',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptBinaryWithCharsetCharsetNameOptBinMod_c837abed',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'charset',
        1 => 'charset_name',
        2 => 'opt_bin_mod',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptBinaryWithBinary_53236b40',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'BINARY',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptBinaryWithBinaryCharsetCharsetName_56ff1e73',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'BINARY',
        1 => 'charset',
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
        0 => 'BINARY',
      ),
    ),
  ),
  'ws_nweights' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WsNweightsWithRealUlongNum_aee34d31',
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
  'ws_level_flag_desc' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\WsLevelFlagDescChoice_63bee526::UseAsc_323b087e',
      'symbols' =>
      array (
        0 => 'ASC',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\WsLevelFlagDescChoice_63bee526::UseDesc_984da4fe',
      'symbols' =>
      array (
        0 => 'DESC',
      ),
    ),
  ),
  'ws_level_flag_reverse' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\WsLevelFlagReverseChoice_436e3cc3::UseReverse_27bed169',
      'symbols' =>
      array (
        0 => 'REVERSE_SYM',
      ),
    ),
  ),
  'ws_level_flags' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WsLevelFlagsWith_2b8139eb',
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
        0 => 'ws_level_flag_desc',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WsLevelFlagsWithWsLevelFlagDescWsLevelFlagReverse_c49be2d5',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ws_level_flag_desc',
        1 => 'ws_level_flag_reverse',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ws_level_flag_reverse',
      ),
    ),
  ),
  'ws_level_number' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'real_ulong_num',
      ),
    ),
  ),
  'ws_level_list_item' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WsLevelListItemWithWsLevelNumberWsLevelFlags_816157c2',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ws_level_number',
        1 => 'ws_level_flags',
      ),
    ),
  ),
  'ws_level_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ws_level_list_item',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WsLevelListWithWsLevelListWsLevelListItem_637627eb',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ws_level_list',
        1 => ',',
        2 => 'ws_level_list_item',
      ),
    ),
  ),
  'ws_level_range' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WsLevelRangeWithWsLevelNumberWsLevelNumber_ba71e81c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ws_level_number',
        1 => '-',
        2 => 'ws_level_number',
      ),
    ),
  ),
  'ws_level_list_or_range' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ws_level_list',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ws_level_range',
      ),
    ),
  ),
  'opt_ws_levels' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptWsLevelsWith_7ea0f3d1',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptWsLevelsWithLevelSymWsLevelListOrRange_b779afa4',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LEVEL_SYM',
        1 => 'ws_level_list_or_range',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptRefListWithRefList_c5bdbf80',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'ref_list',
        2 => ')',
      ),
    ),
  ),
  'ref_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RefListWithRefListIdent_3eea7af9',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ref_list',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptOnUpdateDeleteWithOnUpdateSymDeleteOption_05373e66',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ON',
        1 => 'UPDATE_SYM',
        2 => 'delete_option',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptOnUpdateDeleteWithOnDeleteSymDeleteOption_584e2d38',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ON',
        1 => 'DELETE_SYM',
        2 => 'delete_option',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptOnUpdateDeleteWithOnUpdateSymDeleteOptionOnDeleteSymDeleteOption_7ba62641',
      'fields' =>
      array (
        0 => 2,
        1 => 5,
      ),
      'symbols' =>
      array (
        0 => 'ON',
        1 => 'UPDATE_SYM',
        2 => 'delete_option',
        3 => 'ON',
        4 => 'DELETE_SYM',
        5 => 'delete_option',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptOnUpdateDeleteWithOnDeleteSymDeleteOptionOnUpdateSymDeleteOption_ace6040d',
      'fields' =>
      array (
        0 => 2,
        1 => 5,
      ),
      'symbols' =>
      array (
        0 => 'ON',
        1 => 'DELETE_SYM',
        2 => 'delete_option',
        3 => 'ON',
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
        0 => 'SET',
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
        0 => 'SET',
        1 => 'DEFAULT',
      ),
    ),
  ),
  'normal_key_type' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'key_or_index',
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
  'fulltext' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\FulltextChoice_9d97f14a::UseFulltext_f35ac2cc',
      'symbols' =>
      array (
        0 => 'FULLTEXT_SYM',
      ),
    ),
  ),
  'spatial' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SpatialChoice_c7fac8fe::UseSpatial_be06937b',
      'symbols' =>
      array (
        0 => 'SPATIAL_SYM',
      ),
    ),
  ),
  'init_key_options' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\InitKeyOptionsChoice_055539df::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
  ),
  'key_alg' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'init_key_options',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyAlgWithInitKeyOptionsKeyUsingAlg_ca5c73ab',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'init_key_options',
        1 => 'key_using_alg',
      ),
    ),
  ),
  'normal_key_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NormalKeyOptionsWith_b222f856',
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
        0 => 'normal_key_opts',
      ),
    ),
  ),
  'fulltext_key_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FulltextKeyOptionsWith_86fcd6d3',
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
        0 => 'fulltext_key_opts',
      ),
    ),
  ),
  'spatial_key_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpatialKeyOptionsWith_1fe722ae',
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
        0 => 'spatial_key_opts',
      ),
    ),
  ),
  'normal_key_opts' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'normal_key_opt',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NormalKeyOptsWithNormalKeyOptsNormalKeyOpt_11094c2c',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'normal_key_opts',
        1 => 'normal_key_opt',
      ),
    ),
  ),
  'spatial_key_opts' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'spatial_key_opt',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpatialKeyOptsWithSpatialKeyOptsSpatialKeyOpt_7e8d204d',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'spatial_key_opts',
        1 => 'spatial_key_opt',
      ),
    ),
  ),
  'fulltext_key_opts' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'fulltext_key_opt',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FulltextKeyOptsWithFulltextKeyOptsFulltextKeyOpt_06840da3',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'fulltext_key_opts',
        1 => 'fulltext_key_opt',
      ),
    ),
  ),
  'key_using_alg' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyUsingAlgWithUsingBtreeOrRtree_1999c2ef',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'USING',
        1 => 'btree_or_rtree',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyUsingAlgWithTypeSymBtreeOrRtree_16fe46ac',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'TYPE_SYM',
        1 => 'btree_or_rtree',
      ),
    ),
  ),
  'all_key_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AllKeyOptWithKeyBlockSizeOptEqualUlongNum_54eccb15',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AllKeyOptWithCommentSymTextStringSys_0a571b94',
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
  'normal_key_opt' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'all_key_opt',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'key_using_alg',
      ),
    ),
  ),
  'spatial_key_opt' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'all_key_opt',
      ),
    ),
  ),
  'fulltext_key_opt' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'all_key_opt',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FulltextKeyOptWithWithParserSymIdentSys_64745a70',
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
  'btree_or_rtree' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\BtreeOrRtreeChoice_5a9f82fb::UseBtree_3a08eb1d',
      'symbols' =>
      array (
        0 => 'BTREE_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\BtreeOrRtreeChoice_5a9f82fb::UseRtree_c3316be2',
      'symbols' =>
      array (
        0 => 'RTREE_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\BtreeOrRtreeChoice_5a9f82fb::UseHash_c1fb44c7',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyListWithKeyListKeyPartOrderDir_c89aca51',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'key_list',
        1 => ',',
        2 => 'key_part',
        3 => 'order_dir',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyListWithKeyPartOrderDir_f8e23cbd',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'key_part',
        1 => 'order_dir',
      ),
    ),
  ),
  'key_part' =>
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyPartWithIdentNum_0b2966cb',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => '(',
        2 => 'NUM',
        3 => ')',
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
        0 => 'field_ident',
      ),
    ),
  ),
  'opt_component' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptComponentWith_2f335423',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptComponentWithIdent_0d3c9b0d',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '.',
        1 => 'ident',
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
  'alter' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterWithAlterOptIgnoreTableSymTableIdentAlterCommands_9344e73b',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'opt_ignore',
        2 => 'TABLE_SYM',
        3 => 'table_ident',
        4 => 'alter_commands',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterWithAlterDatabaseIdentOrEmptyCreateDatabaseOptions_9c9dd213',
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
        3 => 'create_database_options',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterWithAlterDatabaseIdentUpgradeSymDataSymDirectorySymNameSym_5dada7b7',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'DATABASE',
        2 => 'ident',
        3 => 'UPGRADE_SYM',
        4 => 'DATA_SYM',
        5 => 'DIRECTORY_SYM',
        6 => 'NAME_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterWithAlterProcedureSymSpNameSpAChistics_3a624b5d',
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
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterWithAlterFunctionSymSpNameSpAChistics_5e89a1f4',
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
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterWithAlterViewAlgorithmDefinerOptViewTail_e313800b',
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
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterWithAlterDefinerOptViewTail_e4d21881',
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
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterWithAlterDefinerOptEventSymSpNameEvAlterOnScheduleCompletionOptEvRenameToOptEvS_dfabb8a0',
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
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterWithAlterTablespaceAlterTablespaceInfo_aa6d84fe',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'TABLESPACE',
        2 => 'alter_tablespace_info',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterWithAlterLogfileSymGroupSymAlterLogfileGroupInfo_1f98b9a0',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'LOGFILE_SYM',
        2 => 'GROUP_SYM',
        3 => 'alter_logfile_group_info',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterWithAlterTablespaceChangeTablespaceInfo_2b498139',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'TABLESPACE',
        2 => 'change_tablespace_info',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterWithAlterTablespaceChangeTablespaceAccess_90a1d16d',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'TABLESPACE',
        2 => 'change_tablespace_access',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterWithAlterServerSymIdentOrTextOptionsSymServerOptionsList_7b1270ac',
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
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterWithAlterUserClearPrivilegesAlterUserList_16cb6290',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'USER',
        2 => 'clear_privileges',
        3 => 'alter_user_list',
      ),
    ),
  ),
  'alter_user_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserListWithUserPasswordExpireSym_bdcc896b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'PASSWORD',
        2 => 'EXPIRE_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterUserListWithAlterUserListUserPasswordExpireSym_582e0e0e',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'alter_user_list',
        1 => ',',
        2 => 'user',
        3 => 'PASSWORD',
        4 => 'EXPIRE_SYM',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\EvAlterOnScheduleCompletionWithOnScheduleSymEvScheduleTime_14c40bfc',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ON',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\EvAlterOnScheduleCompletionWithOnScheduleSymEvScheduleTimeEvOnCompletion_51a2823d',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ON',
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
  'alter_commands' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterCommandsWith_f5a816f7',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterCommandsWithDiscardTablespace_c2375969',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DISCARD',
        1 => 'TABLESPACE',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterCommandsWithImportTablespace_2c3d258d',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'IMPORT',
        1 => 'TABLESPACE',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterCommandsWithAlterListOptPartitioning_8baf454b',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'alter_list',
        1 => 'opt_partitioning',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterCommandsWithAlterListRemovePartitioning_c584e497',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'alter_list',
        1 => 'remove_partitioning',
      ),
    ),
    5 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'remove_partitioning',
      ),
    ),
    6 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'partitioning',
      ),
    ),
    7 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'add_partition_rule',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterCommandsWithDropPartitionSymAltPartNameList_12228104',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'PARTITION_SYM',
        2 => 'alt_part_name_list',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterCommandsWithRebuildSymPartitionSymOptNoWriteToBinlogAllOrAltPartNameList_c6a608e3',
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
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterCommandsWithOptimizePartitionSymOptNoWriteToBinlogAllOrAltPartNameListOptNoWriteToBinlo_153743e4',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'OPTIMIZE',
        1 => 'PARTITION_SYM',
        2 => 'opt_no_write_to_binlog',
        3 => 'all_or_alt_part_name_list',
        4 => 'opt_no_write_to_binlog',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterCommandsWithAnalyzeSymPartitionSymOptNoWriteToBinlogAllOrAltPartNameList_0c0c1498',
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
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterCommandsWithCheckSymPartitionSymAllOrAltPartNameListOptMiCheckType_4540bdd5',
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
        3 => 'opt_mi_check_type',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterCommandsWithRepairPartitionSymOptNoWriteToBinlogAllOrAltPartNameListOptMiRepairType_82532b8d',
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
        4 => 'opt_mi_repair_type',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterCommandsWithCoalescePartitionSymOptNoWriteToBinlogRealUlongNum_fd358d22',
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
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterCommandsWithTruncateSymPartitionSymAllOrAltPartNameList_c1ec7082',
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
    16 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'reorg_partition_rule',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterCommandsWithExchangeSymPartitionSymAltPartNameItemWithTableSymTableIdentHavePartitionin_5cd7aea3',
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
        2 => 'alt_part_name_item',
        3 => 'WITH',
        4 => 'TABLE_SYM',
        5 => 'table_ident',
        6 => 'have_partitioning',
      ),
    ),
  ),
  'remove_partitioning' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RemovePartitioningWithRemoveSymPartitioningSymHavePartitioning_97c654ea',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'REMOVE_SYM',
        1 => 'PARTITIONING_SYM',
        2 => 'have_partitioning',
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
        0 => 'alt_part_name_list',
      ),
    ),
  ),
  'add_partition_rule' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AddPartitionRuleWithAddPartitionSymOptNoWriteToBinlogAddPartExtra_204fd6f6',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ADD',
        1 => 'PARTITION_SYM',
        2 => 'opt_no_write_to_binlog',
        3 => 'add_part_extra',
      ),
    ),
  ),
  'add_part_extra' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AddPartExtraWith_2941267f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AddPartExtraWithPartDefList_ddeba580',
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
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AddPartExtraWithPartitionsSymRealUlongNum_d67a0f5e',
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
  'reorg_partition_rule' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ReorgPartitionRuleWithReorganizeSymPartitionSymOptNoWriteToBinlogReorgPartsRule_69f90609',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'REORGANIZE_SYM',
        1 => 'PARTITION_SYM',
        2 => 'opt_no_write_to_binlog',
        3 => 'reorg_parts_rule',
      ),
    ),
  ),
  'reorg_parts_rule' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ReorgPartsRuleWith_eca50d67',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ReorgPartsRuleWithAltPartNameListIntoPartDefList_219322d3',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'alt_part_name_list',
        1 => 'INTO',
        2 => '(',
        3 => 'part_def_list',
        4 => ')',
      ),
    ),
  ),
  'alt_part_name_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alt_part_name_item',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AltPartNameListWithAltPartNameListAltPartNameItem_6feb845d',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'alt_part_name_list',
        1 => ',',
        2 => 'alt_part_name_item',
      ),
    ),
  ),
  'alt_part_name_item' =>
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
  ),
  'add_column' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AddColumnWithAddOptColumn_abe3ef9c',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ADD',
        1 => 'opt_column',
      ),
    ),
  ),
  'alter_list_item' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithAddColumnColumnDefOptPlace_4b1261e2',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'add_column',
        1 => 'column_def',
        2 => 'opt_place',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithAddKeyDef_ee3f9089',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ADD',
        1 => 'key_def',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithAddColumnCreateFieldList_2d7e8e00',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'add_column',
        1 => '(',
        2 => 'create_field_list',
        3 => ')',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithChangeOptColumnFieldIdentFieldSpecOptPlace_957cd093',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'CHANGE',
        1 => 'opt_column',
        2 => 'field_ident',
        3 => 'field_spec',
        4 => 'opt_place',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithModifySymOptColumnFieldIdentTypeOptAttributeOptPlace_a6c21ab0',
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
        0 => 'MODIFY_SYM',
        1 => 'opt_column',
        2 => 'field_ident',
        3 => 'type',
        4 => 'opt_attribute',
        5 => 'opt_place',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithDropOptColumnFieldIdentOptRestrict_6cc72288',
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
        2 => 'field_ident',
        3 => 'opt_restrict',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithDropForeignKeySymFieldIdent_6694a084',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'FOREIGN',
        2 => 'KEY_SYM',
        3 => 'field_ident',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithDropKeyOrIndexFieldIdent_6f9395c9',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'key_or_index',
        2 => 'field_ident',
      ),
    ),
    9 =>
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
    10 =>
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
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithAlterOptColumnFieldIdentSetDefaultSignedLiteral_ce605117',
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
        2 => 'field_ident',
        3 => 'SET',
        4 => 'DEFAULT',
        5 => 'signed_literal',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithAlterOptColumnFieldIdentDropDefault_cdab2235',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'opt_column',
        2 => 'field_ident',
        3 => 'DROP',
        4 => 'DEFAULT',
      ),
    ),
    13 =>
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
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterListItemWithConvertSymToSymCharsetCharsetNameOrDefaultOptCollate_d025d9c2',
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
        2 => 'charset',
        3 => 'charset_name_or_default',
        4 => 'opt_collate',
      ),
    ),
    15 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'create_table_options_space_separated',
      ),
    ),
    16 =>
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
    17 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_order_clause',
      ),
    ),
    18 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_algorithm_option',
      ),
    ),
    19 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'alter_lock_option',
      ),
    ),
  ),
  'opt_index_lock_algorithm' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIndexLockAlgorithmWith_44ed9747',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIndexLockAlgorithmWithAlterLockOptionAlterAlgorithmOption_dd48263f',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptIndexLockAlgorithmWithAlterAlgorithmOptionAlterLockOption_139ee30a',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterAlgorithmOptionWithAlgorithmSymOptEqualDefault_3e01841b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ALGORITHM_SYM',
        1 => 'opt_equal',
        2 => 'DEFAULT',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterAlgorithmOptionWithAlgorithmSymOptEqualIdent_695efa8d',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ALGORITHM_SYM',
        1 => 'opt_equal',
        2 => 'ident',
      ),
    ),
  ),
  'alter_lock_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterLockOptionWithLockSymOptEqualDefault_7b4156be',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LOCK_SYM',
        1 => 'opt_equal',
        2 => 'DEFAULT',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterLockOptionWithLockSymOptEqualIdent_5efb9d93',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'LOCK_SYM',
        1 => 'opt_equal',
        2 => 'ident',
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
  'slave' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SlaveWithStartSymSlaveOptSlaveThreadOptionListSlaveUntilSlaveConnectionOpts_826501f1',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'START_SYM',
        1 => 'SLAVE',
        2 => 'opt_slave_thread_option_list',
        3 => 'slave_until',
        4 => 'slave_connection_opts',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SlaveWithStopSymSlaveOptSlaveThreadOptionList_4fefa9b1',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'STOP_SYM',
        1 => 'SLAVE',
        2 => 'opt_slave_thread_option_list',
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
  'slave_connection_opts' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SlaveConnectionOptsWithSlaveUserNameOptSlaveUserPassOptSlavePluginAuthOptSlavePluginDirOpt_2560c9e9',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
      ),
      'symbols' =>
      array (
        0 => 'slave_user_name_opt',
        1 => 'slave_user_pass_opt',
        2 => 'slave_plugin_auth_opt',
        3 => 'slave_plugin_dir_opt',
      ),
    ),
  ),
  'slave_user_name_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SlaveUserNameOptWith_81b7f120',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SlaveUserNameOptWithUserEqTextStringSys_7bc538eb',
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
  'slave_user_pass_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SlaveUserPassOptWith_bf4ff07d',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SlaveUserPassOptWithPasswordEqTextStringSys_b9f2a004',
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
  'slave_plugin_auth_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SlavePluginAuthOptWith_d1b5c510',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SlavePluginAuthOptWithDefaultAuthSymEqTextStringSys_f74724d1',
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
  'slave_plugin_dir_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SlavePluginDirOptWith_54eed33f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SlavePluginDirOptWithPluginDirSymEqTextStringSys_44437ac4',
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
  'opt_slave_thread_option_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSlaveThreadOptionListWith_411a0954',
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
        0 => 'slave_thread_option_list',
      ),
    ),
  ),
  'slave_thread_option_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'slave_thread_option',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SlaveThreadOptionListWithSlaveThreadOptionListSlaveThreadOption_c9ea6dee',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'slave_thread_option_list',
        1 => ',',
        2 => 'slave_thread_option',
      ),
    ),
  ),
  'slave_thread_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SlaveThreadOptionWithSqlThread_857ca9f9',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SlaveThreadOptionWithRelayThread_ff2f8382',
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
  'slave_until' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SlaveUntilWith_625b84f5',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SlaveUntilWithUntilSymSlaveUntilOpts_a1964c46',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'UNTIL_SYM',
        1 => 'slave_until_opts',
      ),
    ),
  ),
  'slave_until_opts' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'master_file_def',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SlaveUntilOptsWithSlaveUntilOptsMasterFileDef_6712dd73',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'slave_until_opts',
        1 => ',',
        2 => 'master_file_def',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SlaveUntilOptsWithSqlBeforeGtidsEqTextStringSys_a8cbef17',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SlaveUntilOptsWithSqlAfterGtidsEqTextStringSys_47c5d09b',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SlaveUntilOptsWithSqlAfterMtsGaps_ea6037e8',
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
  'repair' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RepairWithRepairOptNoWriteToBinlogTableOrTablesTableListOptMiRepairType_5e1fc14b',
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
        4 => 'opt_mi_repair_type',
      ),
    ),
  ),
  'opt_mi_repair_type' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptMiRepairTypeWith_705fd2c8',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\MiRepairTypesWithMiRepairTypeMiRepairTypes_514ecff9',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'mi_repair_type',
        1 => 'mi_repair_types',
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
  'analyze' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AnalyzeWithAnalyzeSymOptNoWriteToBinlogTableOrTablesTableList_890f4849',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ANALYZE_SYM',
        1 => 'opt_no_write_to_binlog',
        2 => 'table_or_tables',
        3 => 'table_list',
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
  'check' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CheckWithCheckSymTableOrTablesTableListOptMiCheckType_fb509b33',
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
        3 => 'opt_mi_check_type',
      ),
    ),
  ),
  'opt_mi_check_type' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptMiCheckTypeWith_1ba00e62',
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
  'optimize' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptimizeWithOptimizeOptNoWriteToBinlogTableOrTablesTableList_60eb41f7',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RenameWithRenameUserClearPrivilegesRenameList_c01b3d90',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'RENAME',
        1 => 'USER',
        2 => 'clear_privileges',
        3 => 'rename_list',
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
  'keycache' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeycacheWithCacheSymIndexSymKeycacheListOrPartsInSymKeyCacheName_6d2e6db7',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'CACHE_SYM',
        1 => 'INDEX_SYM',
        2 => 'keycache_list_or_parts',
        3 => 'IN_SYM',
        4 => 'key_cache_name',
      ),
    ),
  ),
  'keycache_list_or_parts' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'keycache_list',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'assign_to_keycache_parts',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AssignToKeycacheWithTableIdentCacheKeysSpec_c5871e0f',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'table_ident',
        1 => 'cache_keys_spec',
      ),
    ),
  ),
  'assign_to_keycache_parts' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AssignToKeycachePartsWithTableIdentAdmPartitionCacheKeysSpec_c6d9ff7d',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_ident',
        1 => 'adm_partition',
        2 => 'cache_keys_spec',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeyCacheNameWithDefault_c18331d1',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT',
      ),
    ),
  ),
  'preload' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PreloadWithLoadIndexSymIntoCacheSymPreloadListOrParts_91e7ebb4',
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
        4 => 'preload_list_or_parts',
      ),
    ),
  ),
  'preload_list_or_parts' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'preload_keys_parts',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'preload_list',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PreloadKeysWithTableIdentCacheKeysSpecOptIgnoreLeaves_163952ec',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_ident',
        1 => 'cache_keys_spec',
        2 => 'opt_ignore_leaves',
      ),
    ),
  ),
  'preload_keys_parts' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PreloadKeysPartsWithTableIdentAdmPartitionCacheKeysSpecOptIgnoreLeaves_0f5ae70c',
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
        1 => 'adm_partition',
        2 => 'cache_keys_spec',
        3 => 'opt_ignore_leaves',
      ),
    ),
  ),
  'adm_partition' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AdmPartitionWithPartitionSymHavePartitioningAllOrAltPartNameList_3852b767',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'PARTITION_SYM',
        1 => 'have_partitioning',
        2 => '(',
        3 => 'all_or_alt_part_name_list',
        4 => ')',
      ),
    ),
  ),
  'cache_keys_spec' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'cache_key_list_or_empty',
      ),
    ),
  ),
  'cache_key_list_or_empty' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CacheKeyListOrEmptyWith_2afdcc8e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CacheKeyListOrEmptyWithKeyOrIndexOptKeyUsageList_1dbb2cae',
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
  'select' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'select_init',
      ),
    ),
  ),
  'select_init' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectInitWithSelectSymSelectInit2_efd5dbce',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SELECT_SYM',
        1 => 'select_init2',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectInitWithSelectParenUnionOpt_e7c38e3b',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'select_paren',
        2 => ')',
        3 => 'union_opt',
      ),
    ),
  ),
  'select_paren' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectParenWithSelectSymSelectPart2_40ac1445',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SELECT_SYM',
        1 => 'select_part2',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectParenWithSelectParen_981afd89',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'select_paren',
        2 => ')',
      ),
    ),
  ),
  'select_paren_derived' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectParenDerivedWithSelectSymSelectPart2Derived_d92d75b1',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SELECT_SYM',
        1 => 'select_part2_derived',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectParenDerivedWithSelectParenDerived_68230fc8',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'select_paren_derived',
        2 => ')',
      ),
    ),
  ),
  'select_init2' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectInit2WithSelectPart2UnionClause_dd0d3cd3',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'select_part2',
        1 => 'union_clause',
      ),
    ),
  ),
  'select_part2' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectPart2WithSelectOptionsSelectItemListSelectIntoSelectLockType_58be9461',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
      ),
      'symbols' =>
      array (
        0 => 'select_options',
        1 => 'select_item_list',
        2 => 'select_into',
        3 => 'select_lock_type',
      ),
    ),
  ),
  'select_into' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectIntoWithOptOrderClauseOptLimitClause_16aea2aa',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'opt_order_clause',
        1 => 'opt_limit_clause',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'into',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'select_from',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectIntoWithIntoSelectFrom_0c06ec01',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'into',
        1 => 'select_from',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectIntoWithSelectFromInto_5ee9b5e7',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'select_from',
        1 => 'into',
      ),
    ),
  ),
  'select_from' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectFromWithFromJoinTableListWhereClauseGroupClauseHavingClauseOptOrderClauseOptLimitCl_11395c2a',
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
        0 => 'FROM',
        1 => 'join_table_list',
        2 => 'where_clause',
        3 => 'group_clause',
        4 => 'having_clause',
        5 => 'opt_order_clause',
        6 => 'opt_limit_clause',
        7 => 'procedure_analyse_clause',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectFromWithFromDualSymWhereClauseOptLimitClause_8ad47375',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'FROM',
        1 => 'DUAL_SYM',
        2 => 'where_clause',
        3 => 'opt_limit_clause',
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
        0 => 'query_expression_option',
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
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectOptionWithSqlCacheSym_0babeeb5',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SQL_CACHE_SYM',
      ),
    ),
  ),
  'select_lock_type' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SelectLockTypeChoice_2789156b::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SelectLockTypeChoice_2789156b::UseForUpdate_fc448151',
      'symbols' =>
      array (
        0 => 'FOR_SYM',
        1 => 'UPDATE_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SelectLockTypeChoice_2789156b::UseLockInShareMode_7d703e8e',
      'symbols' =>
      array (
        0 => 'LOCK_SYM',
        1 => 'IN_SYM',
        2 => 'SHARE_SYM',
        3 => 'MODE_SYM',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectItemWithRememberNameTableWildRememberEnd_453e61c2',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'remember_name',
        1 => 'table_wild',
        2 => 'remember_end',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectItemWithRememberNameExprRememberEndSelectAlias_243a56d3',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
      ),
      'symbols' =>
      array (
        0 => 'remember_name',
        1 => 'expr',
        2 => 'remember_end',
        3 => 'select_alias',
      ),
    ),
  ),
  'remember_name' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\RememberNameChoice_055539df::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
  ),
  'remember_end' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\RememberEndChoice_055539df::Use_e3b0c442',
      'symbols' =>
      array (
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectAliasWithAsTextStringSys_6eee34db',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'AS',
        1 => 'TEXT_STRING_sys',
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
        0 => 'TEXT_STRING_sys',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BoolPriWithBoolPriEqualSymPredicate_6b5f755a',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'bool_pri',
        1 => 'EQUAL_SYM',
        2 => 'predicate',
      ),
    ),
    3 =>
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
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BoolPriWithBoolPriCompOpAllOrAnySubselect_ff3558ec',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'bool_pri',
        1 => 'comp_op',
        2 => 'all_or_any',
        3 => '(',
        4 => 'subselect',
        5 => ')',
      ),
    ),
    5 =>
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PredicateWithBitExprInSymSubselect_c4775214',
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
        3 => 'subselect',
        4 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PredicateWithBitExprNotInSymSubselect_58c1fc3e',
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
        4 => 'subselect',
        5 => ')',
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
    7 =>
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
    8 =>
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
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PredicateWithBitExprLikeSimpleExprOptEscape_2697d22c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'LIKE',
        2 => 'simple_expr',
        3 => 'opt_escape',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\PredicateWithBitExprNotLikeSimpleExprOptEscape_ae900c15',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'bit_expr',
        1 => 'not',
        2 => 'LIKE',
        3 => 'simple_expr',
        4 => 'opt_escape',
      ),
    ),
    11 =>
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
    12 =>
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
    13 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'bit_expr',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CompOpWithGe_4e73b570',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'GE',
      ),
    ),
    2 =>
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
    3 =>
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
    4 =>
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
    5 =>
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
        0 => 'literal',
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
        0 => 'variable',
      ),
    ),
    9 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'sum_expr',
      ),
    ),
    10 =>
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
    11 =>
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
    12 =>
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
    13 =>
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
    14 =>
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
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithSubselect_decff698',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'subselect',
        2 => ')',
      ),
    ),
    16 =>
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
    17 =>
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
    18 =>
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
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithExistsSubselect_5df3bf4b',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'EXISTS',
        1 => '(',
        2 => 'subselect',
        3 => ')',
      ),
    ),
    20 =>
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
    21 =>
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
    22 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithBinarySimpleExpr_a8645d50',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'BINARY',
        1 => 'simple_expr',
      ),
    ),
    23 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithCastSymExprAsCastType_6bf267b4',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'CAST_SYM',
        1 => '(',
        2 => 'expr',
        3 => 'AS',
        4 => 'cast_type',
        5 => ')',
      ),
    ),
    24 =>
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
    25 =>
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
    26 =>
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
    27 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleExprWithDefaultSimpleIdent_8a9ff1c6',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT',
        1 => '(',
        2 => 'simple_ident',
        3 => ')',
      ),
    ),
    28 =>
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
    29 =>
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithInsertExprExprExprExpr_6ddebf5b',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 6,
        3 => 8,
      ),
      'symbols' =>
      array (
        0 => 'INSERT',
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
    10 =>
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
    11 =>
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
    12 =>
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
    13 =>
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
    14 =>
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
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithTimestampExpr_506322b2',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'TIMESTAMP',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallKeywordWithTimestampExprExpr_f5572806',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'TIMESTAMP',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ')',
      ),
    ),
    17 =>
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
    18 =>
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
    19 =>
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
    20 =>
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
    21 =>
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
    22 =>
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
    23 =>
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
    24 =>
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
    25 =>
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
    26 =>
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithOldPasswordExpr_51140363',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'OLD_PASSWORD',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithPasswordExpr_e9306730',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => '(',
        2 => 'expr',
        3 => ')',
      ),
    ),
    12 =>
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
    13 =>
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
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithReplaceExprExprExpr_4162c6c5',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'REPLACE',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ',',
        6 => 'expr',
        7 => ')',
      ),
    ),
    15 =>
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
    16 =>
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
    17 =>
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
    18 =>
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
    19 =>
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
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithWeightStringSymExprOptWsLevels_2c9907fe',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'WEIGHT_STRING_SYM',
        1 => '(',
        2 => 'expr',
        3 => 'opt_ws_levels',
        4 => ')',
      ),
    ),
    21 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithWeightStringSymExprAsCharSymWsNweightsOptWsLevels_7df3f811',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 5,
        3 => 6,
      ),
      'symbols' =>
      array (
        0 => 'WEIGHT_STRING_SYM',
        1 => '(',
        2 => 'expr',
        3 => 'AS',
        4 => 'CHAR_SYM',
        5 => 'ws_nweights',
        6 => 'opt_ws_levels',
        7 => ')',
      ),
    ),
    22 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FunctionCallConflictWithWeightStringSymExprAsBinaryWsNweights_9dd76684',
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
        4 => 'BINARY',
        5 => 'ws_nweights',
        6 => ')',
      ),
    ),
    23 =>
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
    24 =>
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GeometryFunctionWithContainsSymExprExpr_de839dc6',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'CONTAINS_SYM',
        1 => '(',
        2 => 'expr',
        3 => ',',
        4 => 'expr',
        5 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GeometryFunctionWithGeometrycollectionExprList_be849e2f',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'GEOMETRYCOLLECTION',
        1 => '(',
        2 => 'expr_list',
        3 => ')',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GeometryFunctionWithLinestringExprList_048067b8',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'LINESTRING',
        1 => '(',
        2 => 'expr_list',
        3 => ')',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GeometryFunctionWithMultilinestringExprList_d6b7217b',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MULTILINESTRING',
        1 => '(',
        2 => 'expr_list',
        3 => ')',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GeometryFunctionWithMultipointExprList_51fefef4',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MULTIPOINT',
        1 => '(',
        2 => 'expr_list',
        3 => ')',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GeometryFunctionWithMultipolygonExprList_d29a6dc3',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MULTIPOLYGON',
        1 => '(',
        2 => 'expr_list',
        3 => ')',
      ),
    ),
    6 =>
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
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GeometryFunctionWithPolygonExprList_2138de9b',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'POLYGON',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UdfExprWithRememberNameExprRememberEndSelectAlias_bf198dad',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
      ),
      'symbols' =>
      array (
        0 => 'remember_name',
        1 => 'expr',
        2 => 'remember_end',
        3 => 'select_alias',
      ),
    ),
  ),
  'sum_expr' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithAvgSymInSumExpr_da7da98e',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'AVG_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithAvgSymDistinctInSumExpr_82a5cea2',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'AVG_SYM',
        1 => '(',
        2 => 'DISTINCT',
        3 => 'in_sum_expr',
        4 => ')',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithBitAndInSumExpr_2f8570c3',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'BIT_AND',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithBitOrInSumExpr_26c6dcaa',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'BIT_OR',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithBitXorInSumExpr_2fae1ced',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'BIT_XOR',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithCountSymOptAll_8d0d37b4',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'COUNT_SYM',
        1 => '(',
        2 => 'opt_all',
        3 => '*',
        4 => ')',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithCountSymInSumExpr_cc334516',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'COUNT_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithCountSymDistinctExprList_85ab2bd5',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'COUNT_SYM',
        1 => '(',
        2 => 'DISTINCT',
        3 => 'expr_list',
        4 => ')',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithMinSymInSumExpr_a79798ae',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MIN_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithMinSymDistinctInSumExpr_80e77e28',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'MIN_SYM',
        1 => '(',
        2 => 'DISTINCT',
        3 => 'in_sum_expr',
        4 => ')',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithMaxSymInSumExpr_d3f7489f',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'MAX_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithMaxSymDistinctInSumExpr_89a29a1a',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'MAX_SYM',
        1 => '(',
        2 => 'DISTINCT',
        3 => 'in_sum_expr',
        4 => ')',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithStdSymInSumExpr_2499d833',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'STD_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithVarianceSymInSumExpr_b348a60c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'VARIANCE_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithStddevSampSymInSumExpr_179bc405',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'STDDEV_SAMP_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithVarSampSymInSumExpr_df5f140a',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'VAR_SAMP_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithSumSymInSumExpr_c8c17b09',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'SUM_SYM',
        1 => '(',
        2 => 'in_sum_expr',
        3 => ')',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithSumSymDistinctInSumExpr_ce3d35be',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'SUM_SYM',
        1 => '(',
        2 => 'DISTINCT',
        3 => 'in_sum_expr',
        4 => ')',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SumExprWithGroupConcatSymOptDistinctExprListOptGorderClauseOptGconcatSeparator_32873b45',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
        3 => 5,
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
      ),
    ),
  ),
  'variable' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\VariableWithVariableAux_9dda7caf',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '@',
        1 => 'variable_aux',
      ),
    ),
  ),
  'variable_aux' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\VariableAuxWithIdentOrTextSetVarExpr_9dc71558',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ident_or_text',
        1 => 'SET_VAR',
        2 => 'expr',
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
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\VariableAuxWithOptVarIdentTypeIdentOrTextOptComponent_5607a0b9',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => '@',
        1 => 'opt_var_ident_type',
        2 => 'ident_or_text',
        3 => 'opt_component',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GorderListWithGorderListOrderIdentOrderDir_ed3b9915',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'gorder_list',
        1 => ',',
        2 => 'order_ident',
        3 => 'order_dir',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GorderListWithOrderIdentOrderDir_2dbd6439',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'order_ident',
        1 => 'order_dir',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithBinaryOptFieldLength_b2ef7014',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'BINARY',
        1 => 'opt_field_length',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithCharSymOptFieldLengthOptBinary_89a7039c',
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
        2 => 'opt_binary',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithNcharSymOptFieldLength_c86a6da4',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'NCHAR_SYM',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithUnsigned_68ed5a10',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'UNSIGNED',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithUnsignedIntSym_6741dfef',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'UNSIGNED',
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
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CastTypeWithDatetimeTypeDatetimePrecision_660d6d70',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DATETIME',
        1 => 'type_datetime_precision',
      ),
    ),
    10 =>
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
  'table_ref' =>
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
        0 => 'join_table',
      ),
    ),
  ),
  'join_table_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'derived_table_list',
      ),
    ),
  ),
  'esc_table_ref' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_ref',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\EscTableRefWithIdentTableRef_5f78619c',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => '{',
        1 => 'ident',
        2 => 'table_ref',
        3 => '}',
      ),
    ),
  ),
  'derived_table_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'esc_table_ref',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DerivedTableListWithDerivedTableListEscTableRef_34aabb4e',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'derived_table_list',
        1 => ',',
        2 => 'esc_table_ref',
      ),
    ),
  ),
  'join_table' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JoinTableWithTableRefNormalJoinTableRef_c502a929',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_ref',
        1 => 'normal_join',
        2 => 'table_ref',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JoinTableWithTableRefStraightJoinTableFactor_f82227aa',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_ref',
        1 => 'STRAIGHT_JOIN',
        2 => 'table_factor',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JoinTableWithTableRefNormalJoinTableRefOnExpr_39067174',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'table_ref',
        1 => 'normal_join',
        2 => 'table_ref',
        3 => 'ON',
        4 => 'expr',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JoinTableWithTableRefStraightJoinTableFactorOnExpr_59f498cc',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'table_ref',
        1 => 'STRAIGHT_JOIN',
        2 => 'table_factor',
        3 => 'ON',
        4 => 'expr',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JoinTableWithTableRefNormalJoinTableRefUsingUsingList_72b1700c',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'table_ref',
        1 => 'normal_join',
        2 => 'table_ref',
        3 => 'USING',
        4 => '(',
        5 => 'using_list',
        6 => ')',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JoinTableWithTableRefNaturalJoinSymTableFactor_33ff82dc',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'table_ref',
        1 => 'NATURAL',
        2 => 'JOIN_SYM',
        3 => 'table_factor',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JoinTableWithTableRefLeftOptOuterJoinSymTableRefOnExpr_07dea170',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
        3 => 6,
      ),
      'symbols' =>
      array (
        0 => 'table_ref',
        1 => 'LEFT',
        2 => 'opt_outer',
        3 => 'JOIN_SYM',
        4 => 'table_ref',
        5 => 'ON',
        6 => 'expr',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JoinTableWithTableRefLeftOptOuterJoinSymTableFactorUsingUsingList_e05831d5',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
        3 => 7,
      ),
      'symbols' =>
      array (
        0 => 'table_ref',
        1 => 'LEFT',
        2 => 'opt_outer',
        3 => 'JOIN_SYM',
        4 => 'table_factor',
        5 => 'USING',
        6 => '(',
        7 => 'using_list',
        8 => ')',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JoinTableWithTableRefNaturalLeftOptOuterJoinSymTableFactor_d0e52df0',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'table_ref',
        1 => 'NATURAL',
        2 => 'LEFT',
        3 => 'opt_outer',
        4 => 'JOIN_SYM',
        5 => 'table_factor',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JoinTableWithTableRefRightOptOuterJoinSymTableRefOnExpr_46e91d58',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
        3 => 6,
      ),
      'symbols' =>
      array (
        0 => 'table_ref',
        1 => 'RIGHT',
        2 => 'opt_outer',
        3 => 'JOIN_SYM',
        4 => 'table_ref',
        5 => 'ON',
        6 => 'expr',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JoinTableWithTableRefRightOptOuterJoinSymTableFactorUsingUsingList_b4efac80',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
        3 => 7,
      ),
      'symbols' =>
      array (
        0 => 'table_ref',
        1 => 'RIGHT',
        2 => 'opt_outer',
        3 => 'JOIN_SYM',
        4 => 'table_factor',
        5 => 'USING',
        6 => '(',
        7 => 'using_list',
        8 => ')',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\JoinTableWithTableRefNaturalRightOptOuterJoinSymTableFactor_45e501b5',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'table_ref',
        1 => 'NATURAL',
        2 => 'RIGHT',
        3 => 'opt_outer',
        4 => 'JOIN_SYM',
        5 => 'table_factor',
      ),
    ),
  ),
  'normal_join' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\NormalJoinChoice_7974dc69::UseJoin_a9e153ee',
      'symbols' =>
      array (
        0 => 'JOIN_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\NormalJoinChoice_7974dc69::UseInnerJoin_98b2b2a0',
      'symbols' =>
      array (
        0 => 'INNER_SYM',
        1 => 'JOIN_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\NormalJoinChoice_7974dc69::UseCrossJoin_82443ed3',
      'symbols' =>
      array (
        0 => 'CROSS',
        1 => 'JOIN_SYM',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UsePartitionWithPartitionSymUsingListHavePartitioning_d44c3152',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'PARTITION_SYM',
        1 => '(',
        2 => 'using_list',
        3 => ')',
        4 => 'have_partitioning',
      ),
    ),
  ),
  'table_factor' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableFactorWithTableIdentOptUsePartitionOptTableAliasOptKeyDefinition_878cf1f5',
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
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableFactorWithSelectDerivedInitGetSelectLexSelectDerived2_01d3d8c0',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'select_derived_init',
        1 => 'get_select_lex',
        2 => 'select_derived2',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableFactorWithGetSelectLexSelectDerivedUnionOptTableAlias_5f406aa3',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'get_select_lex',
        2 => 'select_derived_union',
        3 => ')',
        4 => 'opt_table_alias',
      ),
    ),
  ),
  'select_derived_union' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectDerivedUnionWithSelectDerivedOptUnionOrderOrLimit_4467bcad',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'select_derived',
        1 => 'opt_union_order_or_limit',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectDerivedUnionWithSelectDerivedUnionUnionSymUnionOptionQuerySpecificationOptUnionOrderOrLimit_73250fa6',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'select_derived_union',
        1 => 'UNION_SYM',
        2 => 'union_option',
        3 => 'query_specification',
        4 => 'opt_union_order_or_limit',
      ),
    ),
  ),
  'select_init2_derived' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'select_part2_derived',
      ),
    ),
  ),
  'select_part2_derived' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectPart2DerivedWithOptQueryExpressionOptionsSelectItemListOptSelectFromSelectLockType_e27e711a',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
      ),
      'symbols' =>
      array (
        0 => 'opt_query_expression_options',
        1 => 'select_item_list',
        2 => 'opt_select_from',
        3 => 'select_lock_type',
      ),
    ),
  ),
  'select_derived' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectDerivedWithGetSelectLexDerivedTableList_ab5023c5',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'get_select_lex',
        1 => 'derived_table_list',
      ),
    ),
  ),
  'select_derived2' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SelectDerived2WithSelectOptionsSelectItemListOptSelectFrom_92aa25b9',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'select_options',
        1 => 'select_item_list',
        2 => 'opt_select_from',
      ),
    ),
  ),
  'get_select_lex' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\GetSelectLexChoice_055539df::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
  ),
  'select_derived_init' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SelectDerivedInitChoice_ef56702b::UseSelect_6e426169',
      'symbols' =>
      array (
        0 => 'SELECT_SYM',
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
        0 => 'OUTER',
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
        0 => 'ident',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UsingListWithUsingListIdent_00da134c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'using_list',
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
        0 => 'TIMESTAMP',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\DateTimeTypeChoice_349d02a6::UseDatetime_107291fd',
      'symbols' =>
      array (
        0 => 'DATETIME',
      ),
    ),
  ),
  'table_alias' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TableAliasChoice_b9da65c1::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TableAliasChoice_b9da65c1::UseAs_de148153',
      'symbols' =>
      array (
        0 => 'AS',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\TableAliasChoice_b9da65c1::Use_380918b9',
      'symbols' =>
      array (
        0 => 'EQ',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptTableAliasWithTableAliasIdent_d2c42fe0',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'table_alias',
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
  'where_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WhereClauseWith_6f332f84',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
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
  'having_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\HavingClauseWith_5cf9b656',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\HavingClauseWithHavingExpr_43357082',
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
  'opt_escape' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptEscapeWithEscapeSymSimpleExpr_caf47b30',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ESCAPE_SYM',
        1 => 'simple_expr',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptEscapeWith_657affd0',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
  ),
  'group_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GroupClauseWith_ddf43617',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GroupClauseWithGroupSymByGroupListOlapOpt_ec4e111c',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GroupListWithGroupListOrderIdentOrderDir_ebc79b06',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'group_list',
        1 => ',',
        2 => 'order_ident',
        3 => 'order_dir',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GroupListWithOrderIdentOrderDir_d2167d69',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'order_ident',
        1 => 'order_dir',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OlapOptWithWithCubeSym_07a941a9',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'WITH_CUBE_SYM',
      ),
    ),
    2 =>
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
  'alter_order_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterOrderClauseWithOrderSymByAlterOrderList_54b4ed00',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\AlterOrderItemWithSimpleIdentNospvarOrderDir_75a7153c',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'simple_ident_nospvar',
        1 => 'order_dir',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OrderListWithOrderListOrderIdentOrderDir_4356e4aa',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'order_list',
        1 => ',',
        2 => 'order_ident',
        3 => 'order_dir',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OrderListWithOrderIdentOrderDir_8aeba2a7',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'order_ident',
        1 => 'order_dir',
      ),
    ),
  ),
  'order_dir' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OrderDirChoice_f474d63c::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OrderDirChoice_f474d63c::UseAsc_323b087e',
      'symbols' =>
      array (
        0 => 'ASC',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OrderDirChoice_f474d63c::UseDesc_984da4fe',
      'symbols' =>
      array (
        0 => 'DESC',
      ),
    ),
  ),
  'opt_limit_clause_init' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptLimitClauseInitWith_87e3739a',
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
  'delete_limit_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DeleteLimitClauseWith_93c7e5ff',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DeleteLimitClauseWithLimitLimitOption_a48cf82d',
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
    2 =>
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
    3 =>
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
  'procedure_analyse_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ProcedureAnalyseClauseWith_9a010a40',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ProcedureAnalyseClauseWithProcedureSymAnalyseSymOptProcedureAnalyseParams_8d1a1baf',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'PROCEDURE_SYM',
        1 => 'ANALYSE_SYM',
        2 => '(',
        3 => 'opt_procedure_analyse_params',
        4 => ')',
      ),
    ),
  ),
  'opt_procedure_analyse_params' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptProcedureAnalyseParamsWith_b3630d0f',
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
        0 => 'procedure_analyse_param',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptProcedureAnalyseParamsWithProcedureAnalyseParamProcedureAnalyseParam_6ec630d8',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'procedure_analyse_param',
        1 => ',',
        2 => 'procedure_analyse_param',
      ),
    ),
  ),
  'procedure_analyse_param' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ProcedureAnalyseParamWithNum_5b3927f0',
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
  'select_var_list_init' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'select_var_list',
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
  'into' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IntoWithIntoIntoDestination_dfe74e15',
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
        0 => 'select_var_list_init',
      ),
    ),
  ),
  'do' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DoWithDoSymExprList_50996a19',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DO_SYM',
        1 => 'expr_list',
      ),
    ),
  ),
  'drop' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropWithDropOptTemporaryTableOrTablesIfExistsTableListOptRestrict_b4ee2bcf',
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
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropWithDropIndexSymIdentOnTableIdentOptIndexLockAlgorithm_bcd665df',
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
        3 => 'ON',
        4 => 'table_ident',
        5 => 'opt_index_lock_algorithm',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropWithDropDatabaseIfExistsIdent_8fe4a9e9',
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
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropWithDropFunctionSymIfExistsIdentIdent_f085898c',
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
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropWithDropFunctionSymIfExistsIdent_f8e6dae1',
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
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropWithDropProcedureSymIfExistsSpName_d13280bf',
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
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropWithDropUserClearPrivilegesUserList_fb762bb8',
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
        2 => 'clear_privileges',
        3 => 'user_list',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropWithDropViewSymIfExistsTableListOptRestrict_6a85ac66',
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
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropWithDropEventSymIfExistsSpName_445f492e',
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
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropWithDropTriggerSymIfExistsSpName_b6baebd5',
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
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropWithDropTablespaceTablespaceNameDropTsOptionsList_4c71763d',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'TABLESPACE',
        2 => 'tablespace_name',
        3 => 'drop_ts_options_list',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropWithDropLogfileSymGroupSymLogfileGroupNameDropTsOptionsList_fcfa6281',
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
        3 => 'logfile_group_name',
        4 => 'drop_ts_options_list',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropWithDropServerSymIfExistsIdentOrText_5f9d5747',
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
  'table_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_name',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableListWithTableListTableName_8f8b1905',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_list',
        1 => ',',
        2 => 'table_name',
      ),
    ),
  ),
  'table_name' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_ident',
      ),
    ),
  ),
  'table_name_with_opt_use_partition' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableNameWithOptUsePartitionWithTableIdentOptUsePartition_28a69737',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'table_ident',
        1 => 'opt_use_partition',
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
        0 => 'table_alias_ref',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableAliasRefListWithTableAliasRefListTableAliasRef_a6214510',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_alias_ref_list',
        1 => ',',
        2 => 'table_alias_ref',
      ),
    ),
  ),
  'table_alias_ref' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_ident_opt_wild',
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
  'drop_ts_options_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropTsOptionsListWith_40dc6a6f',
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
        0 => 'drop_ts_options',
      ),
    ),
  ),
  'drop_ts_options' =>
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropTsOptionsWithDropTsOptionsDropTsOption_1faef5ac',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'drop_ts_options',
        1 => 'drop_ts_option',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DropTsOptionsWithDropTsOptionsListDropTsOption_43c8652c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'drop_ts_options_list',
        1 => ',',
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
        0 => 'opt_ts_engine',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ts_wait',
      ),
    ),
  ),
  'insert' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InsertWithInsertInsertLockOptionOptIgnoreInsert2InsertFieldSpecOptInsertUpdate_d0b84c1a',
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
        0 => 'INSERT',
        1 => 'insert_lock_option',
        2 => 'opt_ignore',
        3 => 'insert2',
        4 => 'insert_field_spec',
        5 => 'opt_insert_update',
      ),
    ),
  ),
  'replace' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ReplaceWithReplaceReplaceLockOptionInsert2InsertFieldSpec_d77d1c9e',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'REPLACE',
        1 => 'replace_lock_option',
        2 => 'insert2',
        3 => 'insert_field_spec',
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
  'insert2' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\Insert2WithIntoInsertTable_b946fa41',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'INTO',
        1 => 'insert_table',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'insert_table',
      ),
    ),
  ),
  'insert_table' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_name_with_opt_use_partition',
      ),
    ),
  ),
  'insert_field_spec' =>
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InsertFieldSpecWithInsertValues_8f731bb7',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InsertFieldSpecWithFieldsInsertValues_565b1b70',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'fields',
        2 => ')',
        3 => 'insert_values',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InsertFieldSpecWithSetIdentEqList_edfa37b7',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SET',
        1 => 'ident_eq_list',
      ),
    ),
  ),
  'fields' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldsWithFieldsInsertIdent_655406b3',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'fields',
        1 => ',',
        2 => 'insert_ident',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'insert_ident',
      ),
    ),
  ),
  'insert_values' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InsertValuesWithValuesValuesList_e6b55a61',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'VALUES',
        1 => 'values_list',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InsertValuesWithValueSymValuesList_91a90ece',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'VALUE_SYM',
        1 => 'values_list',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InsertValuesWithCreateSelectUnionClause_94029787',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'create_select',
        1 => 'union_clause',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InsertValuesWithCreateSelectUnionOpt_db22c996',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'create_select',
        2 => ')',
        3 => 'union_opt',
      ),
    ),
  ),
  'values_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ValuesListWithValuesListNoBraces_38a2ab42',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'values_list',
        1 => ',',
        2 => 'no_braces',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'no_braces',
      ),
    ),
  ),
  'ident_eq_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentEqListWithIdentEqListIdentEqValue_6bad7419',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ident_eq_list',
        1 => ',',
        2 => 'ident_eq_value',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ident_eq_value',
      ),
    ),
  ),
  'ident_eq_value' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\IdentEqValueWithSimpleIdentNospvarEqualExprOrDefault_c3cc7949',
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
  'no_braces' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NoBracesWithOptValues_dba7a0c7',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ExprOrDefaultWithDefault_9fdf86e9',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT',
      ),
    ),
  ),
  'opt_insert_update' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptInsertUpdateWith_52a18ed1',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptInsertUpdateWithOnDuplicateSymKeySymUpdateSymInsertUpdateList_47421fc2',
      'fields' =>
      array (
        0 => 4,
      ),
      'symbols' =>
      array (
        0 => 'ON',
        1 => 'DUPLICATE_SYM',
        2 => 'KEY_SYM',
        3 => 'UPDATE_SYM',
        4 => 'insert_update_list',
      ),
    ),
  ),
  'update' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UpdateWithUpdateSymOptLowPriorityOptIgnoreJoinTableListSetUpdateListWhereClauseOptOrd_b0fe6c0d',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 5,
        4 => 6,
        5 => 7,
        6 => 8,
      ),
      'symbols' =>
      array (
        0 => 'UPDATE_SYM',
        1 => 'opt_low_priority',
        2 => 'opt_ignore',
        3 => 'join_table_list',
        4 => 'SET',
        5 => 'update_list',
        6 => 'where_clause',
        7 => 'opt_order_clause',
        8 => 'delete_limit_clause',
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
  'insert_update_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InsertUpdateListWithInsertUpdateListInsertUpdateElem_22c65a33',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'insert_update_list',
        1 => ',',
        2 => 'insert_update_elem',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'insert_update_elem',
      ),
    ),
  ),
  'insert_update_elem' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InsertUpdateElemWithSimpleIdentNospvarEqualExprOrDefault_c775f8e3',
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
  'delete' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DeleteWithDeleteSymOptDeleteOptionsSingleMulti_a341e700',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DELETE_SYM',
        1 => 'opt_delete_options',
        2 => 'single_multi',
      ),
    ),
  ),
  'single_multi' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SingleMultiWithFromTableIdentOptUsePartitionWhereClauseOptOrderClauseDeleteLimitClause_59df5ae1',
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
        0 => 'FROM',
        1 => 'table_ident',
        2 => 'opt_use_partition',
        3 => 'where_clause',
        4 => 'opt_order_clause',
        5 => 'delete_limit_clause',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SingleMultiWithTableWildListFromJoinTableListWhereClause_ea33068f',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'table_wild_list',
        1 => 'FROM',
        2 => 'join_table_list',
        3 => 'where_clause',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SingleMultiWithFromTableAliasRefListUsingJoinTableListWhereClause_88f99312',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'FROM',
        1 => 'table_alias_ref_list',
        2 => 'USING',
        3 => 'join_table_list',
        4 => 'where_clause',
      ),
    ),
  ),
  'table_wild_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_wild_one',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableWildListWithTableWildListTableWildOne_948d7b57',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_wild_list',
        1 => ',',
        2 => 'table_wild_one',
      ),
    ),
  ),
  'table_wild_one' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableWildOneWithIdentOptWild_3b24e371',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableWildOneWithIdentIdentOptWild_7cd20743',
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
  'truncate' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TruncateWithTruncateSymOptTableSymTableName_7a93bd0e',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'TRUNCATE_SYM',
        1 => 'opt_table_sym',
        2 => 'table_name',
      ),
    ),
  ),
  'opt_table_sym' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptTableSymChoice_64f6cc2f::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptTableSymChoice_64f6cc2f::UseTable_52ca2fea',
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
  'opt_profile_args' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptProfileArgsWith_e9228330',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptProfileArgsWithForSymQuerySymNum_f21f344e',
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
  'show' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowWithShowShowParam_55980fc6',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'show_param',
      ),
    ),
  ),
  'show_param' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithDatabasesWildAndWhere_7dde5c68',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DATABASES',
        1 => 'wild_and_where',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithOptFullTablesOptDbWildAndWhere_7eff897e',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'opt_full',
        1 => 'TABLES',
        2 => 'opt_db',
        3 => 'wild_and_where',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithOptFullTriggersSymOptDbWildAndWhere_e92122a6',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'opt_full',
        1 => 'TRIGGERS_SYM',
        2 => 'opt_db',
        3 => 'wild_and_where',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithEventsSymOptDbWildAndWhere_98784fa6',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'EVENTS_SYM',
        1 => 'opt_db',
        2 => 'wild_and_where',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithTableSymStatusSymOptDbWildAndWhere_3a6c5f27',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'TABLE_SYM',
        1 => 'STATUS_SYM',
        2 => 'opt_db',
        3 => 'wild_and_where',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithOpenSymTablesOptDbWildAndWhere_5b35b606',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'OPEN_SYM',
        1 => 'TABLES',
        2 => 'opt_db',
        3 => 'wild_and_where',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithPluginsSym_381ddaad',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'PLUGINS_SYM',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithEngineSymKnownStorageEnginesShowEngineParam_02bd4fd5',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ENGINE_SYM',
        1 => 'known_storage_engines',
        2 => 'show_engine_param',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithEngineSymAllShowEngineParam_2fae534e',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ENGINE_SYM',
        1 => 'ALL',
        2 => 'show_engine_param',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithOptFullColumnsFromOrInTableIdentOptDbWildAndWhere_f45e288e',
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
        0 => 'opt_full',
        1 => 'COLUMNS',
        2 => 'from_or_in',
        3 => 'table_ident',
        4 => 'opt_db',
        5 => 'wild_and_where',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithMasterOrBinaryLogsSym_0cd3fb4e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'master_or_binary',
        1 => 'LOGS_SYM',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithSlaveHostsSym_fcca5cc0',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SLAVE',
        1 => 'HOSTS_SYM',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithBinlogSymEventsSymBinlogInBinlogFromOptLimitClauseInit_38ad2bb7',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'BINLOG_SYM',
        1 => 'EVENTS_SYM',
        2 => 'binlog_in',
        3 => 'binlog_from',
        4 => 'opt_limit_clause_init',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithRelaylogSymEventsSymBinlogInBinlogFromOptLimitClauseInit_4590c9cf',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'RELAYLOG_SYM',
        1 => 'EVENTS_SYM',
        2 => 'binlog_in',
        3 => 'binlog_from',
        4 => 'opt_limit_clause_init',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithKeysOrIndexFromOrInTableIdentOptDbWhereClause_7b825ea6',
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
        0 => 'keys_or_index',
        1 => 'from_or_in',
        2 => 'table_ident',
        3 => 'opt_db',
        4 => 'where_clause',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithOptStorageEnginesSym_740d7cb0',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'opt_storage',
        1 => 'ENGINES_SYM',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithPrivileges_abae7554',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'PRIVILEGES',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithCountSymWarnings_9a17a790',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'COUNT_SYM',
        1 => '(',
        2 => '*',
        3 => ')',
        4 => 'WARNINGS',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithCountSymErrors_b459abbf',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'COUNT_SYM',
        1 => '(',
        2 => '*',
        3 => ')',
        4 => 'ERRORS',
      ),
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithWarningsOptLimitClauseInit_5885ee82',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'WARNINGS',
        1 => 'opt_limit_clause_init',
      ),
    ),
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithErrorsOptLimitClauseInit_f9409598',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ERRORS',
        1 => 'opt_limit_clause_init',
      ),
    ),
    21 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithProfilesSym_09a56b35',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'PROFILES_SYM',
      ),
    ),
    22 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithProfileSymOptProfileDefsOptProfileArgsOptLimitClauseInit_d011d256',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'PROFILE_SYM',
        1 => 'opt_profile_defs',
        2 => 'opt_profile_args',
        3 => 'opt_limit_clause_init',
      ),
    ),
    23 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithOptVarTypeStatusSymWildAndWhere_03f9a20c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'opt_var_type',
        1 => 'STATUS_SYM',
        2 => 'wild_and_where',
      ),
    ),
    24 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithOptFullProcesslistSym_c7fcc52e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'opt_full',
        1 => 'PROCESSLIST_SYM',
      ),
    ),
    25 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithOptVarTypeVariablesWildAndWhere_ca4e2b8c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'opt_var_type',
        1 => 'VARIABLES',
        2 => 'wild_and_where',
      ),
    ),
    26 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithCharsetWildAndWhere_b4a114ed',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'charset',
        1 => 'wild_and_where',
      ),
    ),
    27 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithCollationSymWildAndWhere_7c0bd923',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'COLLATION_SYM',
        1 => 'wild_and_where',
      ),
    ),
    28 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithGrants_2c9b177e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'GRANTS',
      ),
    ),
    29 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithGrantsForSymUser_37298b83',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'GRANTS',
        1 => 'FOR_SYM',
        2 => 'user',
      ),
    ),
    30 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithCreateDatabaseOptIfNotExistsIdent_d31c3a19',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'DATABASE',
        2 => 'opt_if_not_exists',
        3 => 'ident',
      ),
    ),
    31 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithCreateTableSymTableIdent_7d6e0ba2',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'TABLE_SYM',
        2 => 'table_ident',
      ),
    ),
    32 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithCreateViewSymTableIdent_62756fa2',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'VIEW_SYM',
        2 => 'table_ident',
      ),
    ),
    33 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithMasterSymStatusSym_31d0330a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SYM',
        1 => 'STATUS_SYM',
      ),
    ),
    34 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithSlaveStatusSym_cb916921',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SLAVE',
        1 => 'STATUS_SYM',
      ),
    ),
    35 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithCreateProcedureSymSpName_4ce8700f',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'PROCEDURE_SYM',
        2 => 'sp_name',
      ),
    ),
    36 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithCreateFunctionSymSpName_c7a3dff9',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'FUNCTION_SYM',
        2 => 'sp_name',
      ),
    ),
    37 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithCreateTriggerSymSpName_f61112b6',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'TRIGGER_SYM',
        2 => 'sp_name',
      ),
    ),
    38 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithProcedureSymStatusSymWildAndWhere_f833b199',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'PROCEDURE_SYM',
        1 => 'STATUS_SYM',
        2 => 'wild_and_where',
      ),
    ),
    39 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithFunctionSymStatusSymWildAndWhere_16cd50a9',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'FUNCTION_SYM',
        1 => 'STATUS_SYM',
        2 => 'wild_and_where',
      ),
    ),
    40 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithProcedureSymCodeSymSpName_8e3a36bf',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'PROCEDURE_SYM',
        1 => 'CODE_SYM',
        2 => 'sp_name',
      ),
    ),
    41 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithFunctionSymCodeSymSpName_e0796ab6',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'FUNCTION_SYM',
        1 => 'CODE_SYM',
        2 => 'sp_name',
      ),
    ),
    42 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ShowParamWithCreateEventSymSpName_b14ad5e4',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'EVENT_SYM',
        2 => 'sp_name',
      ),
    ),
  ),
  'show_engine_param' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ShowEngineParamChoice_1bd880ee::UseStatus_8c2e4a03',
      'symbols' =>
      array (
        0 => 'STATUS_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ShowEngineParamChoice_1bd880ee::UseMutex_fadae40f',
      'symbols' =>
      array (
        0 => 'MUTEX_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\ShowEngineParamChoice_1bd880ee::UseLogs_34eadbb7',
      'symbols' =>
      array (
        0 => 'LOGS_SYM',
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
        0 => 'BINARY',
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
  'binlog_in' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BinlogInWith_c8f2f3c2',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BinlogInWithInSymTextStringSys_7ae65290',
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
  'wild_and_where' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WildAndWhereWith_ccd1ebaf',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WildAndWhereWithLikeTextStringSys_b2c2d29e',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LIKE',
        1 => 'TEXT_STRING_sys',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\WildAndWhereWithWhereExpr_c66c9b8d',
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
  'describe' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DescribeWithDescribeCommandTableIdentOptDescribeColumn_15b316b1',
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
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\DescribeWithDescribeCommandOptExtendedDescribeExplanableCommand_9fa92e8e',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'describe_command',
        1 => 'opt_extended_describe',
        2 => 'explanable_command',
      ),
    ),
  ),
  'explanable_command' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'select',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'insert',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'replace',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'update',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'delete',
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
  'opt_extended_describe' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptExtendedDescribeWith_baeae19d',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptExtendedDescribeWithExtendedSym_89f19dfd',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'EXTENDED_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptExtendedDescribeWithPartitionsSym_5bc0ff62',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'PARTITIONS_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptExtendedDescribeWithFormatSymEqIdentOrText_1bf41fe2',
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
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\FlushOptionChoice_7e76bc5d::UseErrorLogs_8ada0f24',
      'symbols' =>
      array (
        0 => 'ERROR_SYM',
        1 => 'LOGS_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\FlushOptionChoice_7e76bc5d::UseEngineLogs_7cf1ea00',
      'symbols' =>
      array (
        0 => 'ENGINE_SYM',
        1 => 'LOGS_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\FlushOptionChoice_7e76bc5d::UseGeneralLogs_6527f974',
      'symbols' =>
      array (
        0 => 'GENERAL',
        1 => 'LOGS_SYM',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\FlushOptionChoice_7e76bc5d::UseSlowLogs_2f5e9d53',
      'symbols' =>
      array (
        0 => 'SLOW',
        1 => 'LOGS_SYM',
      ),
    ),
    4 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\FlushOptionChoice_7e76bc5d::UseBinaryLogs_7106d868',
      'symbols' =>
      array (
        0 => 'BINARY',
        1 => 'LOGS_SYM',
      ),
    ),
    5 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\FlushOptionChoice_7e76bc5d::UseRelayLogs_2b156c1b',
      'symbols' =>
      array (
        0 => 'RELAY',
        1 => 'LOGS_SYM',
      ),
    ),
    6 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\FlushOptionChoice_7e76bc5d::UseQueryCache_78ed7f5b',
      'symbols' =>
      array (
        0 => 'QUERY_SYM',
        1 => 'CACHE_SYM',
      ),
    ),
    7 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\FlushOptionChoice_7e76bc5d::UseHosts_c44fdbc4',
      'symbols' =>
      array (
        0 => 'HOSTS_SYM',
      ),
    ),
    8 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\FlushOptionChoice_7e76bc5d::UsePrivileges_1bcab5f9',
      'symbols' =>
      array (
        0 => 'PRIVILEGES',
      ),
    ),
    9 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\FlushOptionChoice_7e76bc5d::UseLogs_34eadbb7',
      'symbols' =>
      array (
        0 => 'LOGS_SYM',
      ),
    ),
    10 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\FlushOptionChoice_7e76bc5d::UseStatus_8c2e4a03',
      'symbols' =>
      array (
        0 => 'STATUS_SYM',
      ),
    ),
    11 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\FlushOptionChoice_7e76bc5d::UseDesKeyFile_3c9bf0c5',
      'symbols' =>
      array (
        0 => 'DES_KEY_FILE',
      ),
    ),
    12 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\FlushOptionChoice_7e76bc5d::UseUserResources_09107372',
      'symbols' =>
      array (
        0 => 'RESOURCES',
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
  'reset_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ResetOptionWithSlaveSlaveResetOptions_1b5f6862',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SLAVE',
        1 => 'slave_reset_options',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ResetOptionWithMasterSym_09af240e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ResetOptionWithQuerySymCacheSym_24ddd50a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'QUERY_SYM',
        1 => 'CACHE_SYM',
      ),
    ),
  ),
  'slave_reset_options' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SlaveResetOptionsChoice_66c44b99::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SlaveResetOptionsChoice_66c44b99::UseAll_b5c7aed7',
      'symbols' =>
      array (
        0 => 'ALL',
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
  'load' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LoadWithLoadDataOrXmlLoadDataLockOptLocalInfileTextStringFilesystemOptDuplicateInto_905e40f5',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 5,
        4 => 6,
        5 => 9,
        6 => 10,
        7 => 11,
        8 => 12,
        9 => 13,
        10 => 14,
        11 => 15,
        12 => 16,
        13 => 17,
      ),
      'symbols' =>
      array (
        0 => 'LOAD',
        1 => 'data_or_xml',
        2 => 'load_data_lock',
        3 => 'opt_local',
        4 => 'INFILE',
        5 => 'TEXT_STRING_filesystem',
        6 => 'opt_duplicate',
        7 => 'INTO',
        8 => 'TABLE_SYM',
        9 => 'table_ident',
        10 => 'opt_use_partition',
        11 => 'opt_load_data_charset',
        12 => 'opt_xml_rows_identified_by',
        13 => 'opt_field_term',
        14 => 'opt_line_term',
        15 => 'opt_ignore_lines',
        16 => 'opt_field_or_var_spec',
        17 => 'opt_load_data_set_spec',
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
  'opt_duplicate' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptDuplicateChoice_37cc0109::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptDuplicateChoice_37cc0109::UseReplace_9b66c971',
      'symbols' =>
      array (
        0 => 'REPLACE',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptDuplicateChoice_37cc0109::UseIgnore_eff4f8c3',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptLoadDataSetSpecWithSetLoadDataSetList_f0085494',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SET',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LoadDataSetElemWithSimpleIdentNospvarEqualRememberNameExprOrDefaultRememberEnd_f98c432e',
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
        0 => 'simple_ident_nospvar',
        1 => 'equal',
        2 => 'remember_name',
        3 => 'expr_or_default',
        4 => 'remember_end',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\LiteralWithNullSym_08bd0f63',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NULL_SYM',
      ),
    ),
    4 =>
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
    5 =>
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
    6 =>
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
    7 =>
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
    8 =>
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
    9 =>
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
  'NUM_literal' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NumLiteralWithNum_6c905169',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NumLiteralWithLongNum_99a9f730',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\NumLiteralWithUlonglongNum_73ca3065',
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
    4 =>
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TemporalLiteralWithTimestampTextString_696a1b42',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'TIMESTAMP',
        1 => 'TEXT_STRING',
      ),
    ),
  ),
  'insert_ident' =>
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
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'table_wild',
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
  'order_ident' =>
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SimpleIdentQWithIdentIdent_00fae5f1',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => '.',
        1 => 'ident',
        2 => '.',
        3 => 'ident',
      ),
    ),
    2 =>
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
  'field_ident' =>
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldIdentWithIdentIdentIdent_5fc0a9e3',
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
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldIdentWithIdentIdent_6b6d1bd7',
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
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\FieldIdentWithIdent_813eeb05',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '.',
        1 => 'ident',
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
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TableIdentWithIdent_bd4ee753',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '.',
        1 => 'ident',
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
  'table_ident_nodb' =>
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
        0 => 'keyword',
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
        0 => 'keyword_sp',
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
  'user' =>
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UserWithIdentOrTextIdentOrText_c285abe4',
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
    2 =>
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
  'keyword' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'keyword_sp',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithAsciiSym_63d1ebf0',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ASCII_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithBackupSym_5155ab33',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BACKUP_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithBeginSym_f15cc606',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BEGIN_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithByteSym_51af34b2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BYTE_SYM',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithCacheSym_a2502cfc',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CACHE_SYM',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithCharset_6821594f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CHARSET',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithChecksumSym_288c2120',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CHECKSUM_SYM',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithCloseSym_375156ad',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CLOSE_SYM',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithCommentSym_5f9de437',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COMMENT_SYM',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithCommitSym_188cd66a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COMMIT_SYM',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithContainsSym_58240725',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CONTAINS_SYM',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithDeallocateSym_50a90f30',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DEALLOCATE_SYM',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithDoSym_14680752',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DO_SYM',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithEnd_99ab87b3',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'END',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithExecuteSym_6bb5f4ae',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EXECUTE_SYM',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithFlushSym_b2ead2e4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FLUSH_SYM',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithFormatSym_40a3dc5f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FORMAT_SYM',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithHandlerSym_1dfe5d94',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HANDLER_SYM',
      ),
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithHelpSym_56789d74',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HELP_SYM',
      ),
    ),
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithHostSym_d20d52be',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HOST_SYM',
      ),
    ),
    21 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithInstallSym_d9e7dd2c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'INSTALL_SYM',
      ),
    ),
    22 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithLanguageSym_93ebc4d8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LANGUAGE_SYM',
      ),
    ),
    23 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithNoSym_83e4cf4f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NO_SYM',
      ),
    ),
    24 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithOpenSym_8c2fa2dc',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'OPEN_SYM',
      ),
    ),
    25 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithOptionsSym_b6dd3af0',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'OPTIONS_SYM',
      ),
    ),
    26 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithOwnerSym_5fefcb9f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'OWNER_SYM',
      ),
    ),
    27 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithParserSym_fb6f7b0b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PARSER_SYM',
      ),
    ),
    28 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithPortSym_14bba51a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PORT_SYM',
      ),
    ),
    29 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithPrepareSym_1363f5cc',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PREPARE_SYM',
      ),
    ),
    30 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithRemoveSym_58c063d1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REMOVE_SYM',
      ),
    ),
    31 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithRepair_7cb6fa99',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REPAIR',
      ),
    ),
    32 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithResetSym_33811707',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RESET_SYM',
      ),
    ),
    33 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithRestoreSym_ee0a389d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RESTORE_SYM',
      ),
    ),
    34 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithRollbackSym_588ef6f3',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ROLLBACK_SYM',
      ),
    ),
    35 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithSavepointSym_b28401b3',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SAVEPOINT_SYM',
      ),
    ),
    36 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithSecuritySym_6b5d70c3',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SECURITY_SYM',
      ),
    ),
    37 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithServerSym_838485a0',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SERVER_SYM',
      ),
    ),
    38 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithSignedSym_d734fb90',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SIGNED_SYM',
      ),
    ),
    39 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithSocketSym_ad74e0ba',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOCKET_SYM',
      ),
    ),
    40 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithSlave_bfc42b50',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SLAVE',
      ),
    ),
    41 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithSonameSym_45544c2f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SONAME_SYM',
      ),
    ),
    42 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithStartSym_415ea7e4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'START_SYM',
      ),
    ),
    43 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithStopSym_bda67cc6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'STOP_SYM',
      ),
    ),
    44 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithTruncateSym_e1870f1d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TRUNCATE_SYM',
      ),
    ),
    45 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithUnicodeSym_56e3a231',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UNICODE_SYM',
      ),
    ),
    46 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithUninstallSym_fa5fac2c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UNINSTALL_SYM',
      ),
    ),
    47 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithWrapperSym_411c4b63',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'WRAPPER_SYM',
      ),
    ),
    48 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithXaSym_bdc4767e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'XA_SYM',
      ),
    ),
    49 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordWithUpgradeSym_7db6ca39',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UPGRADE_SYM',
      ),
    ),
  ),
  'keyword_sp' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithAction_3e1f4418',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithAdddateSym_832892ec',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ADDDATE_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithAfterSym_326c8ea8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'AFTER_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithAgainst_a58bf516',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'AGAINST',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithAggregateSym_db97db1d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'AGGREGATE_SYM',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithAlgorithmSym_6b2ef367',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ALGORITHM_SYM',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithAnalyseSym_48beded3',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ANALYSE_SYM',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithAnySym_887cbe9a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ANY_SYM',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithAtSym_183ca5a5',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'AT_SYM',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithAutoInc_13acfd68',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'AUTO_INC',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithAutoextendSizeSym_7bca2300',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'AUTOEXTEND_SIZE_SYM',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithAvgRowLength_19ef9c90',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'AVG_ROW_LENGTH',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithAvgSym_a86d04d4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'AVG_SYM',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithBinlogSym_6d0d957d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BINLOG_SYM',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithBitSym_2170bfa3',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BIT_SYM',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithBlockSym_2f8ba8a1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BLOCK_SYM',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithBoolSym_b396674f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BOOL_SYM',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithBooleanSym_91b72df7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BOOLEAN_SYM',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithBtreeSym_bf761269',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BTREE_SYM',
      ),
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithCascaded_c22537e3',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CASCADED',
      ),
    ),
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithCatalogNameSym_22b0f6c5',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CATALOG_NAME_SYM',
      ),
    ),
    21 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithChainSym_6b4488c9',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CHAIN_SYM',
      ),
    ),
    22 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithChanged_cea32844',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CHANGED',
      ),
    ),
    23 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithCipherSym_1f1de4d0',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CIPHER_SYM',
      ),
    ),
    24 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithClientSym_b4da802c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CLIENT_SYM',
      ),
    ),
    25 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithClassOriginSym_457b3672',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CLASS_ORIGIN_SYM',
      ),
    ),
    26 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithCoalesce_c44ba478',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COALESCE',
      ),
    ),
    27 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithCodeSym_48f0f272',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CODE_SYM',
      ),
    ),
    28 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithCollationSym_3f691848',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COLLATION_SYM',
      ),
    ),
    29 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithColumnNameSym_97392da5',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COLUMN_NAME_SYM',
      ),
    ),
    30 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithColumnFormatSym_b9dfe832',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COLUMN_FORMAT_SYM',
      ),
    ),
    31 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithColumns_62074139',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COLUMNS',
      ),
    ),
    32 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithCommittedSym_03b81564',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COMMITTED_SYM',
      ),
    ),
    33 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithCompactSym_db5ee797',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COMPACT_SYM',
      ),
    ),
    34 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithCompletionSym_bf6dd1ad',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COMPLETION_SYM',
      ),
    ),
    35 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithCompressedSym_3d388c01',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COMPRESSED_SYM',
      ),
    ),
    36 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithConcurrent_291948f8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CONCURRENT',
      ),
    ),
    37 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithConnectionSym_1249eae5',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CONNECTION_SYM',
      ),
    ),
    38 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithConsistentSym_ef8c4864',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CONSISTENT_SYM',
      ),
    ),
    39 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithConstraintCatalogSym_f80a1fb0',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CONSTRAINT_CATALOG_SYM',
      ),
    ),
    40 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithConstraintSchemaSym_85625660',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CONSTRAINT_SCHEMA_SYM',
      ),
    ),
    41 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithConstraintNameSym_715ad850',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CONSTRAINT_NAME_SYM',
      ),
    ),
    42 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithContextSym_fa3e77a0',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CONTEXT_SYM',
      ),
    ),
    43 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithCpuSym_49b38f90',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CPU_SYM',
      ),
    ),
    44 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithCubeSym_9024a0b4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CUBE_SYM',
      ),
    ),
    45 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithCurrentSym_e884ccbb',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CURRENT_SYM',
      ),
    ),
    46 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithCursorNameSym_3977c7bc',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CURSOR_NAME_SYM',
      ),
    ),
    47 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithDataSym_65711e97',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DATA_SYM',
      ),
    ),
    48 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithDatafileSym_a7c47c01',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DATAFILE_SYM',
      ),
    ),
    49 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithDatetime_9b82df73',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DATETIME',
      ),
    ),
    50 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithDateSym_6174ff39',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DATE_SYM',
      ),
    ),
    51 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithDaySym_7a669dae',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DAY_SYM',
      ),
    ),
    52 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithDefaultAuthSym_94c9d671',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT_AUTH_SYM',
      ),
    ),
    53 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithDefinerSym_408ed638',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DEFINER_SYM',
      ),
    ),
    54 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithDelayKeyWriteSym_3b65e958',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DELAY_KEY_WRITE_SYM',
      ),
    ),
    55 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithDesKeyFile_56c972a6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DES_KEY_FILE',
      ),
    ),
    56 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithDiagnosticsSym_85cad7c2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DIAGNOSTICS_SYM',
      ),
    ),
    57 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithDirectorySym_37eb5094',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DIRECTORY_SYM',
      ),
    ),
    58 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithDisableSym_83b3179f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DISABLE_SYM',
      ),
    ),
    59 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithDiscard_4865e782',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DISCARD',
      ),
    ),
    60 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithDiskSym_0d11cf37',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DISK_SYM',
      ),
    ),
    61 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithDumpfile_3e54feac',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DUMPFILE',
      ),
    ),
    62 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithDuplicateSym_58f0457e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DUPLICATE_SYM',
      ),
    ),
    63 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithDynamicSym_af195533',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DYNAMIC_SYM',
      ),
    ),
    64 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithEndsSym_8cde007a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ENDS_SYM',
      ),
    ),
    65 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithEnum_5a9e70fb',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ENUM',
      ),
    ),
    66 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithEngineSym_a387fd9e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ENGINE_SYM',
      ),
    ),
    67 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithEnginesSym_7e49a899',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ENGINES_SYM',
      ),
    ),
    68 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithErrorSym_f4937355',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ERROR_SYM',
      ),
    ),
    69 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithErrors_38fcf370',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ERRORS',
      ),
    ),
    70 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithEscapeSym_4df3fb32',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ESCAPE_SYM',
      ),
    ),
    71 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithEventSym_27b50a19',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EVENT_SYM',
      ),
    ),
    72 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithEventsSym_46992b09',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EVENTS_SYM',
      ),
    ),
    73 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithEverySym_82ab5e6b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EVERY_SYM',
      ),
    ),
    74 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithExchangeSym_44de2e64',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EXCHANGE_SYM',
      ),
    ),
    75 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithExpansionSym_689af339',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EXPANSION_SYM',
      ),
    ),
    76 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithExpireSym_ffc4e92a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EXPIRE_SYM',
      ),
    ),
    77 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithExportSym_5dfaf29f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EXPORT_SYM',
      ),
    ),
    78 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithExtendedSym_8a04ab7b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EXTENDED_SYM',
      ),
    ),
    79 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithExtentSizeSym_ec941578',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EXTENT_SIZE_SYM',
      ),
    ),
    80 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithFaultsSym_9ca65eee',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FAULTS_SYM',
      ),
    ),
    81 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithFastSym_485f6d8d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FAST_SYM',
      ),
    ),
    82 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithFoundSym_9451547d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FOUND_SYM',
      ),
    ),
    83 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithEnableSym_29563587',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ENABLE_SYM',
      ),
    ),
    84 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithFull_0364a2b5',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FULL',
      ),
    ),
    85 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithFileSym_36b2c466',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FILE_SYM',
      ),
    ),
    86 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithFirstSym_7f40ceda',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FIRST_SYM',
      ),
    ),
    87 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithFixedSym_1ca66d65',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FIXED_SYM',
      ),
    ),
    88 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithGeneral_89f68024',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'GENERAL',
      ),
    ),
    89 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithGeometrySym_c8abfbc1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'GEOMETRY_SYM',
      ),
    ),
    90 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithGeometrycollection_2c9df7bc',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'GEOMETRYCOLLECTION',
      ),
    ),
    91 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithGetFormat_8971acb4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'GET_FORMAT',
      ),
    ),
    92 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithGrants_a039032a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'GRANTS',
      ),
    ),
    93 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithGlobalSym_46e4563f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'GLOBAL_SYM',
      ),
    ),
    94 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithHashSym_e933e081',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HASH_SYM',
      ),
    ),
    95 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithHostsSym_cc347d0c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HOSTS_SYM',
      ),
    ),
    96 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithHourSym_a783cc03',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'HOUR_SYM',
      ),
    ),
    97 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithIdentifiedSym_81b9acdf',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'IDENTIFIED_SYM',
      ),
    ),
    98 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithIgnoreServerIdsSym_adefcc00',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'IGNORE_SERVER_IDS_SYM',
      ),
    ),
    99 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithInvokerSym_99362287',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'INVOKER_SYM',
      ),
    ),
    100 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithImport_7444808a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'IMPORT',
      ),
    ),
    101 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithIndexes_7836ff4d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'INDEXES',
      ),
    ),
    102 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithInitialSizeSym_ed01fbab',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'INITIAL_SIZE_SYM',
      ),
    ),
    103 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithIoSym_30198771',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'IO_SYM',
      ),
    ),
    104 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithIpcSym_bc5779fb',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'IPC_SYM',
      ),
    ),
    105 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithIsolation_c518d674',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ISOLATION',
      ),
    ),
    106 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithIssuerSym_d0c36fa7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ISSUER_SYM',
      ),
    ),
    107 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithInsertMethod_c66b85dd',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'INSERT_METHOD',
      ),
    ),
    108 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithKeyBlockSize_d8c068a1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'KEY_BLOCK_SIZE',
      ),
    ),
    109 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithLastSym_f6cfe574',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LAST_SYM',
      ),
    ),
    110 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithLeaves_7406aa0b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LEAVES',
      ),
    ),
    111 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithLessSym_148dd071',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LESS_SYM',
      ),
    ),
    112 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithLevelSym_17fa1e04',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LEVEL_SYM',
      ),
    ),
    113 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithLinestring_c918f6ad',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LINESTRING',
      ),
    ),
    114 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithListSym_29c9fdfe',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LIST_SYM',
      ),
    ),
    115 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithLocalSym_31fb9ea6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LOCAL_SYM',
      ),
    ),
    116 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithLocksSym_0caea3dc',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LOCKS_SYM',
      ),
    ),
    117 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithLogfileSym_23470278',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LOGFILE_SYM',
      ),
    ),
    118 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithLogsSym_5484090b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LOGS_SYM',
      ),
    ),
    119 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMaxRows_da921a6d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MAX_ROWS',
      ),
    ),
    120 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterSym_b127b99b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SYM',
      ),
    ),
    121 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterHeartbeatPeriodSym_0f33eb54',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_HEARTBEAT_PERIOD_SYM',
      ),
    ),
    122 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterHostSym_c939792c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_HOST_SYM',
      ),
    ),
    123 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterPortSym_697d83f6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_PORT_SYM',
      ),
    ),
    124 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterLogFileSym_3186a725',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_LOG_FILE_SYM',
      ),
    ),
    125 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterLogPosSym_9af9804d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_LOG_POS_SYM',
      ),
    ),
    126 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterUserSym_3e786556',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_USER_SYM',
      ),
    ),
    127 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterPasswordSym_cc241666',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_PASSWORD_SYM',
      ),
    ),
    128 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterServerIdSym_e2c7e688',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SERVER_ID_SYM',
      ),
    ),
    129 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterConnectRetrySym_85822ccb',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_CONNECT_RETRY_SYM',
      ),
    ),
    130 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterRetryCountSym_71fa3056',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_RETRY_COUNT_SYM',
      ),
    ),
    131 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterDelaySym_c0263571',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_DELAY_SYM',
      ),
    ),
    132 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterSslSym_6fc15aa1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_SYM',
      ),
    ),
    133 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterSslCaSym_6b18461d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CA_SYM',
      ),
    ),
    134 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterSslCapathSym_f84afa8e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CAPATH_SYM',
      ),
    ),
    135 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterSslCertSym_80b20e3a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CERT_SYM',
      ),
    ),
    136 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterSslCipherSym_72bb35b1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CIPHER_SYM',
      ),
    ),
    137 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterSslCrlSym_4392101b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CRL_SYM',
      ),
    ),
    138 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterSslCrlpathSym_6817f629',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_CRLPATH_SYM',
      ),
    ),
    139 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterSslKeySym_a5296f17',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_SSL_KEY_SYM',
      ),
    ),
    140 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMasterAutoPositionSym_ec4db303',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MASTER_AUTO_POSITION_SYM',
      ),
    ),
    141 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMaxConnectionsPerHour_ac05d4ab',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MAX_CONNECTIONS_PER_HOUR',
      ),
    ),
    142 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMaxQueriesPerHour_5e4d5cb1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MAX_QUERIES_PER_HOUR',
      ),
    ),
    143 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMaxSizeSym_7be88da7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MAX_SIZE_SYM',
      ),
    ),
    144 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMaxUpdatesPerHour_d85e307b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MAX_UPDATES_PER_HOUR',
      ),
    ),
    145 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMaxUserConnectionsSym_f15bfab9',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MAX_USER_CONNECTIONS_SYM',
      ),
    ),
    146 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMediumSym_52d3eef1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MEDIUM_SYM',
      ),
    ),
    147 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMemorySym_c2df81a0',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MEMORY_SYM',
      ),
    ),
    148 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMergeSym_b838510b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MERGE_SYM',
      ),
    ),
    149 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMessageTextSym_20380791',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MESSAGE_TEXT_SYM',
      ),
    ),
    150 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMicrosecondSym_379ca855',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MICROSECOND_SYM',
      ),
    ),
    151 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMigrateSym_f0933549',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MIGRATE_SYM',
      ),
    ),
    152 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMinuteSym_5738681c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MINUTE_SYM',
      ),
    ),
    153 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMinRows_da4d0b26',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MIN_ROWS',
      ),
    ),
    154 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithModifySym_942c5adf',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MODIFY_SYM',
      ),
    ),
    155 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithModeSym_28f8cfeb',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MODE_SYM',
      ),
    ),
    156 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMonthSym_634d92a7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MONTH_SYM',
      ),
    ),
    157 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMultilinestring_99d90ee7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MULTILINESTRING',
      ),
    ),
    158 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMultipoint_663666e5',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MULTIPOINT',
      ),
    ),
    159 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMultipolygon_8da6263b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MULTIPOLYGON',
      ),
    ),
    160 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMutexSym_3c7101af',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MUTEX_SYM',
      ),
    ),
    161 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithMysqlErrnoSym_a823ac95',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'MYSQL_ERRNO_SYM',
      ),
    ),
    162 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithNameSym_17f78e21',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NAME_SYM',
      ),
    ),
    163 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithNamesSym_6e3d6b38',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NAMES_SYM',
      ),
    ),
    164 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithNationalSym_1187f60a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NATIONAL_SYM',
      ),
    ),
    165 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithNcharSym_6597a315',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NCHAR_SYM',
      ),
    ),
    166 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithNdbclusterSym_01a6c0e9',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NDBCLUSTER_SYM',
      ),
    ),
    167 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithNextSym_fe82db48',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NEXT_SYM',
      ),
    ),
    168 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithNewSym_e87e9b09',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NEW_SYM',
      ),
    ),
    169 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithNoWaitSym_5ebdb933',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NO_WAIT_SYM',
      ),
    ),
    170 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithNodegroupSym_d79fd68f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NODEGROUP_SYM',
      ),
    ),
    171 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithNoneSym_2c5833cf',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NONE_SYM',
      ),
    ),
    172 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithNumberSym_f494344d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NUMBER_SYM',
      ),
    ),
    173 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithNvarcharSym_9a3f59a6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NVARCHAR_SYM',
      ),
    ),
    174 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithOffsetSym_ab064f6e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'OFFSET_SYM',
      ),
    ),
    175 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithOldPassword_4c4d9943',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'OLD_PASSWORD',
      ),
    ),
    176 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithOneSym_8552fe4c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ONE_SYM',
      ),
    ),
    177 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithOnlySym_fe261197',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ONLY_SYM',
      ),
    ),
    178 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithPackKeysSym_b7b039cc',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PACK_KEYS_SYM',
      ),
    ),
    179 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithPageSym_e897e42f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PAGE_SYM',
      ),
    ),
    180 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithPartial_0490db6f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PARTIAL',
      ),
    ),
    181 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithPartitioningSym_c4ce4548',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PARTITIONING_SYM',
      ),
    ),
    182 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithPartitionsSym_2dc6fe66',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PARTITIONS_SYM',
      ),
    ),
    183 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithPassword_99eecdf4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
      ),
    ),
    184 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithPhaseSym_9e8d1098',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PHASE_SYM',
      ),
    ),
    185 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithPluginDirSym_c8e64bbb',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PLUGIN_DIR_SYM',
      ),
    ),
    186 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithPluginSym_1c724aaa',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PLUGIN_SYM',
      ),
    ),
    187 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithPluginsSym_53b1b7a4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PLUGINS_SYM',
      ),
    ),
    188 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithPointSym_4bea4de4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'POINT_SYM',
      ),
    ),
    189 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithPolygon_cea6c217',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'POLYGON',
      ),
    ),
    190 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithPreserveSym_648ee819',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PRESERVE_SYM',
      ),
    ),
    191 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithPrevSym_beadd68d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PREV_SYM',
      ),
    ),
    192 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithPrivileges_d61dd0ec',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PRIVILEGES',
      ),
    ),
    193 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithProcess_573f13ce',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PROCESS',
      ),
    ),
    194 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithProcesslistSym_78789599',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PROCESSLIST_SYM',
      ),
    ),
    195 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithProfileSym_1916a514',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PROFILE_SYM',
      ),
    ),
    196 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithProfilesSym_986f6c4d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PROFILES_SYM',
      ),
    ),
    197 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithProxySym_20717c4b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'PROXY_SYM',
      ),
    ),
    198 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithQuarterSym_5f99e5f2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'QUARTER_SYM',
      ),
    ),
    199 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithQuerySym_0f417d34',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'QUERY_SYM',
      ),
    ),
    200 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithQuick_054439c8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'QUICK',
      ),
    ),
    201 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithReadOnlySym_4cf7b65e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'READ_ONLY_SYM',
      ),
    ),
    202 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithRebuildSym_0c20f4e7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REBUILD_SYM',
      ),
    ),
    203 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithRecoverSym_44a52e80',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RECOVER_SYM',
      ),
    ),
    204 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithRedoBufferSizeSym_d4feb273',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REDO_BUFFER_SIZE_SYM',
      ),
    ),
    205 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithRedofileSym_e306ea7c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REDOFILE_SYM',
      ),
    ),
    206 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithRedundantSym_46c8f60d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REDUNDANT_SYM',
      ),
    ),
    207 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithRelay_dc09be70',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RELAY',
      ),
    ),
    208 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithRelaylogSym_52d23fab',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RELAYLOG_SYM',
      ),
    ),
    209 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithRelayLogFileSym_0c8937b8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RELAY_LOG_FILE_SYM',
      ),
    ),
    210 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithRelayLogPosSym_9e772a25',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RELAY_LOG_POS_SYM',
      ),
    ),
    211 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithRelayThread_cc662a7f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RELAY_THREAD',
      ),
    ),
    212 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithReload_77dac642',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RELOAD',
      ),
    ),
    213 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithReorganizeSym_66549d30',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REORGANIZE_SYM',
      ),
    ),
    214 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithRepeatableSym_4dbf2772',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REPEATABLE_SYM',
      ),
    ),
    215 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithReplication_cb604e39',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REPLICATION',
      ),
    ),
    216 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithResources_31f7f86a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RESOURCES',
      ),
    ),
    217 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithResumeSym_d4ad7eeb',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RESUME_SYM',
      ),
    ),
    218 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithReturnedSqlstateSym_eb7b7073',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RETURNED_SQLSTATE_SYM',
      ),
    ),
    219 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithReturnsSym_24234586',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RETURNS_SYM',
      ),
    ),
    220 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithReverseSym_3b0bdf15',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'REVERSE_SYM',
      ),
    ),
    221 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithRollupSym_03828c5f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ROLLUP_SYM',
      ),
    ),
    222 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithRoutineSym_5dbad769',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ROUTINE_SYM',
      ),
    ),
    223 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithRowsSym_bcc49052',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ROWS_SYM',
      ),
    ),
    224 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithRowCountSym_6a614b31',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ROW_COUNT_SYM',
      ),
    ),
    225 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithRowFormatSym_591c97a1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ROW_FORMAT_SYM',
      ),
    ),
    226 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithRowSym_1c37c3bc',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ROW_SYM',
      ),
    ),
    227 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithRtreeSym_8eda3838',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RTREE_SYM',
      ),
    ),
    228 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithScheduleSym_1682fba7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SCHEDULE_SYM',
      ),
    ),
    229 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSchemaNameSym_13a84239',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SCHEMA_NAME_SYM',
      ),
    ),
    230 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSecondSym_91845e64',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SECOND_SYM',
      ),
    ),
    231 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSerialSym_51c44d8b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SERIAL_SYM',
      ),
    ),
    232 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSerializableSym_db66b60f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SERIALIZABLE_SYM',
      ),
    ),
    233 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSessionSym_c8eb07e2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SESSION_SYM',
      ),
    ),
    234 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSimpleSym_ebf20e84',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SIMPLE_SYM',
      ),
    ),
    235 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithShareSym_ebcbf402',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SHARE_SYM',
      ),
    ),
    236 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithShutdown_854e2ae4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SHUTDOWN',
      ),
    ),
    237 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSlow_e53e74a4',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SLOW',
      ),
    ),
    238 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSnapshotSym_771d74a8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SNAPSHOT_SYM',
      ),
    ),
    239 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSoundsSym_e75626d5',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOUNDS_SYM',
      ),
    ),
    240 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSourceSym_e2f09510',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SOURCE_SYM',
      ),
    ),
    241 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSqlAfterGtids_50cb6cb5',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SQL_AFTER_GTIDS',
      ),
    ),
    242 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSqlAfterMtsGaps_3f4866af',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SQL_AFTER_MTS_GAPS',
      ),
    ),
    243 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSqlBeforeGtids_b59eacd8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SQL_BEFORE_GTIDS',
      ),
    ),
    244 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSqlCacheSym_9fcd2768',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SQL_CACHE_SYM',
      ),
    ),
    245 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSqlBufferResult_6ef60f7a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SQL_BUFFER_RESULT',
      ),
    ),
    246 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSqlNoCacheSym_5e949419',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SQL_NO_CACHE_SYM',
      ),
    ),
    247 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSqlThread_f615dd7f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SQL_THREAD',
      ),
    ),
    248 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithStartsSym_472ff0ec',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'STARTS_SYM',
      ),
    ),
    249 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithStatsAutoRecalcSym_998b41b0',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'STATS_AUTO_RECALC_SYM',
      ),
    ),
    250 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithStatsPersistentSym_f0c29443',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'STATS_PERSISTENT_SYM',
      ),
    ),
    251 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithStatsSamplePagesSym_7e1d6de5',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'STATS_SAMPLE_PAGES_SYM',
      ),
    ),
    252 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithStatusSym_ffd932b1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'STATUS_SYM',
      ),
    ),
    253 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithStorageSym_94f7c184',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'STORAGE_SYM',
      ),
    ),
    254 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithStringSym_36924174',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'STRING_SYM',
      ),
    ),
    255 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSubclassOriginSym_9a4f0612',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SUBCLASS_ORIGIN_SYM',
      ),
    ),
    256 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSubdateSym_add7ef37',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SUBDATE_SYM',
      ),
    ),
    257 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSubjectSym_4be43e0b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SUBJECT_SYM',
      ),
    ),
    258 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSubpartitionSym_d6642196',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SUBPARTITION_SYM',
      ),
    ),
    259 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSubpartitionsSym_aa7e2afc',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SUBPARTITIONS_SYM',
      ),
    ),
    260 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSuperSym_e7b3e22a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SUPER_SYM',
      ),
    ),
    261 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSuspendSym_b5057375',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SUSPEND_SYM',
      ),
    ),
    262 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSwapsSym_e0d0bf43',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SWAPS_SYM',
      ),
    ),
    263 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithSwitchesSym_77f742c2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SWITCHES_SYM',
      ),
    ),
    264 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithTableNameSym_6cdc0c9a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TABLE_NAME_SYM',
      ),
    ),
    265 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithTables_38cccecb',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TABLES',
      ),
    ),
    266 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithTableChecksumSym_dfb1aad9',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TABLE_CHECKSUM_SYM',
      ),
    ),
    267 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithTablespace_8d069f32',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TABLESPACE',
      ),
    ),
    268 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithTemporary_b163640c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TEMPORARY',
      ),
    ),
    269 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithTemptableSym_a3458ec8',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TEMPTABLE_SYM',
      ),
    ),
    270 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithTextSym_83ea0d67',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TEXT_SYM',
      ),
    ),
    271 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithThanSym_0d0a15f9',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'THAN_SYM',
      ),
    ),
    272 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithTransactionSym_e5884084',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TRANSACTION_SYM',
      ),
    ),
    273 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithTriggersSym_70e1fbfd',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TRIGGERS_SYM',
      ),
    ),
    274 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithTimestamp_d8ad4770',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TIMESTAMP',
      ),
    ),
    275 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithTimestampAdd_c81378d7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TIMESTAMP_ADD',
      ),
    ),
    276 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithTimestampDiff_e0348b3e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TIMESTAMP_DIFF',
      ),
    ),
    277 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithTimeSym_22197d75',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TIME_SYM',
      ),
    ),
    278 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithTypesSym_f88282fe',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TYPES_SYM',
      ),
    ),
    279 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithTypeSym_d78e1a72',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TYPE_SYM',
      ),
    ),
    280 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithUdfReturnsSym_b050820b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UDF_RETURNS_SYM',
      ),
    ),
    281 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithFunctionSym_9414cb38',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'FUNCTION_SYM',
      ),
    ),
    282 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithUncommittedSym_96e48c56',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UNCOMMITTED_SYM',
      ),
    ),
    283 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithUndefinedSym_5d7f095b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UNDEFINED_SYM',
      ),
    ),
    284 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithUndoBufferSizeSym_d1885f06',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UNDO_BUFFER_SIZE_SYM',
      ),
    ),
    285 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithUndofileSym_fd751abb',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UNDOFILE_SYM',
      ),
    ),
    286 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithUnknownSym_84f324c1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UNKNOWN_SYM',
      ),
    ),
    287 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithUntilSym_76a6f8bc',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'UNTIL_SYM',
      ),
    ),
    288 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithUser_4ba55b6c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'USER',
      ),
    ),
    289 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithUseFrm_4521402c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'USE_FRM',
      ),
    ),
    290 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithVariables_2e82b196',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'VARIABLES',
      ),
    ),
    291 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithViewSym_abd2c7c3',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'VIEW_SYM',
      ),
    ),
    292 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithValueSym_e461bcdb',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'VALUE_SYM',
      ),
    ),
    293 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithWarnings_f70f88b3',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'WARNINGS',
      ),
    ),
    294 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithWaitSym_c59a4706',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'WAIT_SYM',
      ),
    ),
    295 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithWeekSym_52538726',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'WEEK_SYM',
      ),
    ),
    296 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithWorkSym_348e9860',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'WORK_SYM',
      ),
    ),
    297 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithWeightStringSym_f7c0837f',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'WEIGHT_STRING_SYM',
      ),
    ),
    298 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithX509Sym_a4add4d0',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'X509_SYM',
      ),
    ),
    299 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithXmlSym_e7ad5567',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'XML_SYM',
      ),
    ),
    300 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\KeywordSpWithYearSym_3ea7f34d',
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
  'set' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SetWithSetStartOptionValueList_148b93d5',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SET',
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
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptionTypeChoice_adabe993::UseGlobal_e7440dd3',
      'symbols' =>
      array (
        0 => 'GLOBAL_SYM',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptionTypeChoice_adabe993::UseLocal_646c1937',
      'symbols' =>
      array (
        0 => 'LOCAL_SYM',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptionTypeChoice_adabe993::UseSession_cb66ec75',
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
  'opt_var_ident_type' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptVarIdentTypeChoice_3c76b304::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptVarIdentTypeChoice_3c76b304::UseGlobal_d19f0c78',
      'symbols' =>
      array (
        0 => 'GLOBAL_SYM',
        1 => '.',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptVarIdentTypeChoice_3c76b304::UseLocal_04ef36f9',
      'symbols' =>
      array (
        0 => 'LOCAL_SYM',
        1 => '.',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptVarIdentTypeChoice_3c76b304::UseSession_ffe3f182',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptionValueFollowingOptionTypeWithInternalVariableNameEqualSetExprOrDefault_68c3e7db',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'internal_variable_name',
        1 => 'equal',
        2 => 'set_expr_or_default',
      ),
    ),
  ),
  'option_value_no_option_type' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptionValueNoOptionTypeWithInternalVariableNameEqualSetExprOrDefault_57cb6494',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'internal_variable_name',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptionValueNoOptionTypeWithOptVarIdentTypeInternalVariableNameEqualSetExprOrDefault_5c12c1a4',
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
        2 => 'opt_var_ident_type',
        3 => 'internal_variable_name',
        4 => 'equal',
        5 => 'set_expr_or_default',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptionValueNoOptionTypeWithCharsetOldOrNewCharsetNameOrDefault_56dc93ee',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'charset',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptionValueNoOptionTypeWithNamesSymCharsetNameOrDefaultOptCollate_edfa3be7',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'NAMES_SYM',
        1 => 'charset_name_or_default',
        2 => 'opt_collate',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptionValueNoOptionTypeWithPasswordEqualTextOrPassword_772ae4d0',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'equal',
        2 => 'text_or_password',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptionValueNoOptionTypeWithPasswordForSymUserEqualTextOrPassword_ec89947a',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => 'FOR_SYM',
        2 => 'user',
        3 => 'equal',
        4 => 'text_or_password',
      ),
    ),
  ),
  'internal_variable_name' =>
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InternalVariableNameWithIdentIdent_1eb81c5f',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InternalVariableNameWithDefaultIdent_5b18bebd',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT',
        1 => '.',
        2 => 'ident',
      ),
    ),
  ),
  'transaction_characteristics' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'transaction_access_mode',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'isolation_level',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TransactionCharacteristicsWithTransactionAccessModeIsolationLevel_954fbcaf',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'transaction_access_mode',
        1 => ',',
        2 => 'isolation_level',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TransactionCharacteristicsWithIsolationLevelTransactionAccessMode_a993459f',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'isolation_level',
        1 => ',',
        2 => 'transaction_access_mode',
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
  'text_or_password' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TextOrPasswordWithTextString_d2cd3b9c',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TextOrPasswordWithPasswordTextString_e1ec1a7f',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'PASSWORD',
        1 => '(',
        2 => 'TEXT_STRING',
        3 => ')',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TextOrPasswordWithOldPasswordTextString_607b6669',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'OLD_PASSWORD',
        1 => '(',
        2 => 'TEXT_STRING',
        3 => ')',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SetExprOrDefaultWithDefault_5031ac71',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SetExprOrDefaultWithOn_7b2f00b8',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ON',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SetExprOrDefaultWithBinary_24667b2d',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'BINARY',
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
  ),
  'handler' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\HandlerWithHandlerSymTableIdentOpenSymOptTableAlias_eb2e5caa',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\HandlerWithHandlerSymTableIdentNodbCloseSym_025fdf8b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'HANDLER_SYM',
        1 => 'table_ident_nodb',
        2 => 'CLOSE_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\HandlerWithHandlerSymTableIdentNodbReadSymHandlerReadOrScanWhereClauseOptLimitClause_5ba815d2',
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
        1 => 'table_ident_nodb',
        2 => 'READ_SYM',
        3 => 'handler_read_or_scan',
        4 => 'where_clause',
        5 => 'opt_limit_clause',
      ),
    ),
  ),
  'handler_read_or_scan' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'handler_scan_function',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\HandlerReadOrScanWithIdentHandlerRkeyFunction_79b7ee6c',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ident',
        1 => 'handler_rkey_function',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\HandlerRkeyFunctionWithFirstSym_14c7ff71',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'FIRST_SYM',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\HandlerRkeyFunctionWithNextSym_3a8b5d8f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NEXT_SYM',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\HandlerRkeyFunctionWithPrevSym_3d513752',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'PREV_SYM',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\HandlerRkeyFunctionWithLastSym_cac483ba',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'LAST_SYM',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\HandlerRkeyFunctionWithHandlerRkeyModeValues_000b2fb3',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'handler_rkey_mode',
        1 => '(',
        2 => 'values',
        3 => ')',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RevokeWithRevokeClearPrivilegesRevokeCommand_a6faac64',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'REVOKE',
        1 => 'clear_privileges',
        2 => 'revoke_command',
      ),
    ),
  ),
  'revoke_command' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RevokeCommandWithGrantPrivilegesOnOptTableGrantIdentFromGrantList_e0d2bee3',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'grant_privileges',
        1 => 'ON',
        2 => 'opt_table',
        3 => 'grant_ident',
        4 => 'FROM',
        5 => 'grant_list',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RevokeCommandWithGrantPrivilegesOnFunctionSymGrantIdentFromGrantList_994dfc87',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'grant_privileges',
        1 => 'ON',
        2 => 'FUNCTION_SYM',
        3 => 'grant_ident',
        4 => 'FROM',
        5 => 'grant_list',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RevokeCommandWithGrantPrivilegesOnProcedureSymGrantIdentFromGrantList_8b9dfa12',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'grant_privileges',
        1 => 'ON',
        2 => 'PROCEDURE_SYM',
        3 => 'grant_ident',
        4 => 'FROM',
        5 => 'grant_list',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RevokeCommandWithAllOptPrivilegesGrantOptionFromGrantList_6a2bf150',
      'fields' =>
      array (
        0 => 1,
        1 => 6,
      ),
      'symbols' =>
      array (
        0 => 'ALL',
        1 => 'opt_privileges',
        2 => ',',
        3 => 'GRANT',
        4 => 'OPTION',
        5 => 'FROM',
        6 => 'grant_list',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\RevokeCommandWithProxySymOnUserFromGrantList_748b5f18',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'PROXY_SYM',
        1 => 'ON',
        2 => 'user',
        3 => 'FROM',
        4 => 'grant_list',
      ),
    ),
  ),
  'grant' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantWithGrantClearPrivilegesGrantCommand_7560798e',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'GRANT',
        1 => 'clear_privileges',
        2 => 'grant_command',
      ),
    ),
  ),
  'grant_command' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantCommandWithGrantPrivilegesOnOptTableGrantIdentToSymGrantListRequireClauseGrantOptions_50dfc410',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 5,
        4 => 6,
        5 => 7,
      ),
      'symbols' =>
      array (
        0 => 'grant_privileges',
        1 => 'ON',
        2 => 'opt_table',
        3 => 'grant_ident',
        4 => 'TO_SYM',
        5 => 'grant_list',
        6 => 'require_clause',
        7 => 'grant_options',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantCommandWithGrantPrivilegesOnFunctionSymGrantIdentToSymGrantListRequireClauseGrantOptio_3c46043f',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
        2 => 5,
        3 => 6,
        4 => 7,
      ),
      'symbols' =>
      array (
        0 => 'grant_privileges',
        1 => 'ON',
        2 => 'FUNCTION_SYM',
        3 => 'grant_ident',
        4 => 'TO_SYM',
        5 => 'grant_list',
        6 => 'require_clause',
        7 => 'grant_options',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantCommandWithGrantPrivilegesOnProcedureSymGrantIdentToSymGrantListRequireClauseGrantOpti_c31de914',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
        2 => 5,
        3 => 6,
        4 => 7,
      ),
      'symbols' =>
      array (
        0 => 'grant_privileges',
        1 => 'ON',
        2 => 'PROCEDURE_SYM',
        3 => 'grant_ident',
        4 => 'TO_SYM',
        5 => 'grant_list',
        6 => 'require_clause',
        7 => 'grant_options',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantCommandWithProxySymOnUserToSymGrantListOptGrantOption_14d62736',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'PROXY_SYM',
        1 => 'ON',
        2 => 'user',
        3 => 'TO_SYM',
        4 => 'grant_list',
        5 => 'opt_grant_option',
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
  'grant_privileges' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'object_privilege_list',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantPrivilegesWithAllOptPrivileges_5a8f6ab3',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ALL',
        1 => 'opt_privileges',
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
  'object_privilege_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'object_privilege',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeListWithObjectPrivilegeListObjectPrivilege_aa95a989',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'object_privilege_list',
        1 => ',',
        2 => 'object_privilege',
      ),
    ),
  ),
  'object_privilege' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithSelectSymOptColumnList_ec2c3470',
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
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithInsertOptColumnList_a5ad5eb0',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'INSERT',
        1 => 'opt_column_list',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithUpdateSymOptColumnList_53ca2120',
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
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithReferencesOptColumnList_f7acfa1e',
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
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithDeleteSym_5ab62f02',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DELETE_SYM',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithUsage_eeab304b',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'USAGE',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithIndexSym_b83a7308',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'INDEX_SYM',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithAlter_8e4026d1',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithCreate_aaa53659',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithDrop_7f74e8f6',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DROP',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithExecuteSym_3af7cd70',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'EXECUTE_SYM',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithReload_2c39991f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'RELOAD',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithShutdown_7e34f4ec',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SHUTDOWN',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithProcess_1bc08449',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'PROCESS',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithFileSym_6cc08a07',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'FILE_SYM',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithGrantOption_a07f73dc',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'GRANT',
        1 => 'OPTION',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithShowDatabases_8ed02d0d',
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
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithSuperSym_380adcec',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SUPER_SYM',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithCreateTemporaryTables_5ee54350',
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
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithLockSymTables_77c87e58',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'LOCK_SYM',
        1 => 'TABLES',
      ),
    ),
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithReplicationSlave_c84dbf0a',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'REPLICATION',
        1 => 'SLAVE',
      ),
    ),
    21 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithReplicationClientSym_7bf16771',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'REPLICATION',
        1 => 'CLIENT_SYM',
      ),
    ),
    22 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithCreateViewSym_bfcc1a64',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'VIEW_SYM',
      ),
    ),
    23 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithShowViewSym_29fb91d2',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SHOW',
        1 => 'VIEW_SYM',
      ),
    ),
    24 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithCreateRoutineSym_ed81b2fd',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'ROUTINE_SYM',
      ),
    ),
    25 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithAlterRoutineSym_b7f07ba2',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'ROUTINE_SYM',
      ),
    ),
    26 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithCreateUser_4f6bbb1e',
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
    27 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithEventSym_350bbdcb',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'EVENT_SYM',
      ),
    ),
    28 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithTriggerSym_d86f1486',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'TRIGGER_SYM',
      ),
    ),
    29 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ObjectPrivilegeWithCreateTablespace_ada3758c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'CREATE',
        1 => 'TABLESPACE',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantIdentWithIdent_9997515a',
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
        0 => 'table_ident',
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
  'grant_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'grant_user',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantListWithGrantListGrantUser_53bd58f5',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'grant_list',
        1 => ',',
        2 => 'grant_user',
      ),
    ),
  ),
  'grant_user' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantUserWithUserIdentifiedSymByTextString_46e8e039',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'IDENTIFIED_SYM',
        2 => 'BY',
        3 => 'TEXT_STRING',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantUserWithUserIdentifiedSymByPasswordTextString_5edfa118',
      'fields' =>
      array (
        0 => 0,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'IDENTIFIED_SYM',
        2 => 'BY',
        3 => 'PASSWORD',
        4 => 'TEXT_STRING',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantUserWithUserIdentifiedSymWithIdentOrText_5166bebd',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'IDENTIFIED_SYM',
        2 => 'WITH',
        3 => 'ident_or_text',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantUserWithUserIdentifiedSymWithIdentOrTextAsTextStringSys_03334c6f',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'user',
        1 => 'IDENTIFIED_SYM',
        2 => 'WITH',
        3 => 'ident_or_text',
        4 => 'AS',
        5 => 'TEXT_STRING_sys',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'user',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ColumnListWithColumnListColumnListId_26eb534e',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'column_list',
        1 => ',',
        2 => 'column_list_id',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'column_list_id',
      ),
    ),
  ),
  'column_list_id' =>
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantOptionsWith_06da85ff',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantOptionsWithWithGrantOptionList_7f7d5bbd',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'grant_option_list',
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
  'grant_option_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantOptionListWithGrantOptionListGrantOption_154aa279',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'grant_option_list',
        1 => 'grant_option',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'grant_option',
      ),
    ),
  ),
  'grant_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantOptionWithGrantOption_f69b5afa',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'GRANT',
        1 => 'OPTION',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantOptionWithMaxQueriesPerHourUlongNum_b87bd0ab',
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
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantOptionWithMaxUpdatesPerHourUlongNum_fcf8cb82',
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
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantOptionWithMaxConnectionsPerHourUlongNum_9c6ff306',
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
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\GrantOptionWithMaxUserConnectionsSymUlongNum_419e08e5',
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
  'begin' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\BeginWithBeginSymOptWork_609f10f0',
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
  'union_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UnionClauseWith_96d61279',
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
        0 => 'union_list',
      ),
    ),
  ),
  'union_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UnionListWithUnionSymUnionOptionSelectInit_f440f9c5',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'UNION_SYM',
        1 => 'union_option',
        2 => 'select_init',
      ),
    ),
  ),
  'union_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UnionOptWith_bed11b47',
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
        0 => 'union_list',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'union_order_or_limit',
      ),
    ),
  ),
  'opt_union_order_or_limit' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptUnionOrderOrLimitWith_d97725f1',
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
        0 => 'union_order_or_limit',
      ),
    ),
  ),
  'union_order_or_limit' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'order_or_limit',
      ),
    ),
  ),
  'order_or_limit' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OrderOrLimitWithOrderClauseOptLimitClauseInit_bfc39d46',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'order_clause',
        1 => 'opt_limit_clause_init',
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
  'query_specification' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QuerySpecificationWithSelectSymSelectInit2Derived_cb2c9ac3',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SELECT_SYM',
        1 => 'select_init2_derived',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QuerySpecificationWithSelectParenDerived_a7ed1cea',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'select_paren_derived',
        2 => ')',
      ),
    ),
  ),
  'query_expression_body' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryExpressionBodyWithQuerySpecificationOptUnionOrderOrLimit_c93fd014',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'query_specification',
        1 => 'opt_union_order_or_limit',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryExpressionBodyWithQueryExpressionBodyUnionSymUnionOptionQuerySpecificationOptUnionOrderOrLimi_5c96fe8e',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'query_expression_body',
        1 => 'UNION_SYM',
        2 => 'union_option',
        3 => 'query_specification',
        4 => 'opt_union_order_or_limit',
      ),
    ),
  ),
  'subselect' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SubselectWithSubselectStartQueryExpressionBodySubselectEnd_4d4f9abf',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'subselect_start',
        1 => 'query_expression_body',
        2 => 'subselect_end',
      ),
    ),
  ),
  'subselect_start' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SubselectStartChoice_055539df::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
  ),
  'subselect_end' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\SubselectEndChoice_055539df::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
  ),
  'opt_query_expression_options' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptQueryExpressionOptionsWith_63cb7ff3',
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
        0 => 'query_expression_option_list',
      ),
    ),
  ),
  'query_expression_option_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryExpressionOptionListWithQueryExpressionOptionListQueryExpressionOption_d48e0c57',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'query_expression_option_list',
        1 => 'query_expression_option',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'query_expression_option',
      ),
    ),
  ),
  'query_expression_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryExpressionOptionWithStraightJoin_579ab3ad',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryExpressionOptionWithHighPriority_9acb891f',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryExpressionOptionWithDistinct_59b3ced1',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryExpressionOptionWithSqlSmallResult_bb11c9fd',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryExpressionOptionWithSqlBigResult_20c9f9f1',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryExpressionOptionWithSqlBufferResult_de7cfff8',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryExpressionOptionWithSqlCalcFoundRows_94113e14',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\QueryExpressionOptionWithAll_cc071984',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ALL',
      ),
    ),
  ),
  'view_or_trigger_or_sp_or_event' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ViewOrTriggerOrSpOrEventWithDefinerDefinerTail_8814fb93',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'definer',
        1 => 'definer_tail',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ViewOrTriggerOrSpOrEventWithNoDefinerNoDefinerTail_4f8c2880',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'no_definer',
        1 => 'no_definer_tail',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ViewOrTriggerOrSpOrEventWithViewReplaceOrAlgorithmDefinerOptViewTail_35de15a4',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'view_replace_or_algorithm',
        1 => 'definer_opt',
        2 => 'view_tail',
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
        1 => 'REPLACE',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ViewTailWithViewSuidViewSymTableIdentViewListOptAsViewSelect_6c99009e',
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
        3 => 'view_list_opt',
        4 => 'AS',
        5 => 'view_select',
      ),
    ),
  ),
  'view_list_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ViewListOptWith_ab03bc8c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ViewListOptWithViewList_84584db7',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'view_list',
        2 => ')',
      ),
    ),
  ),
  'view_list' =>
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ViewListWithViewListIdent_3c91ca14',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'view_list',
        1 => ',',
        2 => 'ident',
      ),
    ),
  ),
  'view_select' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ViewSelectWithViewSelectAuxViewCheckOption_8fd363d7',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'view_select_aux',
        1 => 'view_check_option',
      ),
    ),
  ),
  'view_select_aux' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ViewSelectAuxWithCreateViewSelectUnionClause_9e46b84a',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'create_view_select',
        1 => 'union_clause',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\ViewSelectAuxWithCreateViewSelectParenUnionOpt_a7302c97',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'create_view_select_paren',
        2 => ')',
        3 => 'union_opt',
      ),
    ),
  ),
  'create_view_select_paren' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'create_view_select',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateViewSelectParenWithCreateViewSelectParen_dc706592',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => '(',
        1 => 'create_view_select_paren',
        2 => ')',
      ),
    ),
  ),
  'create_view_select' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\CreateViewSelectWithSelectSymSelectPart2_a78d2113',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SELECT_SYM',
        1 => 'select_part2',
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
  'trigger_tail' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\TriggerTailWithTriggerSymRememberNameSpNameTrgActionTimeTrgEventOnRememberNameTableIdentFo_fb660824',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 6,
        5 => 7,
        6 => 9,
        7 => 12,
      ),
      'symbols' =>
      array (
        0 => 'TRIGGER_SYM',
        1 => 'remember_name',
        2 => 'sp_name',
        3 => 'trg_action_time',
        4 => 'trg_event',
        5 => 'ON',
        6 => 'remember_name',
        7 => 'table_ident',
        8 => 'FOR_SYM',
        9 => 'remember_name',
        10 => 'EACH_SYM',
        11 => 'ROW_SYM',
        12 => 'sp_proc_stmt',
      ),
    ),
  ),
  'udf_tail' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UdfTailWithAggregateSymRememberNameFunctionSymIdentReturnsSymUdfTypeSonameSymTextStrin_99f86657',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 5,
        3 => 7,
      ),
      'symbols' =>
      array (
        0 => 'AGGREGATE_SYM',
        1 => 'remember_name',
        2 => 'FUNCTION_SYM',
        3 => 'ident',
        4 => 'RETURNS_SYM',
        5 => 'udf_type',
        6 => 'SONAME_SYM',
        7 => 'TEXT_STRING_sys',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\UdfTailWithRememberNameFunctionSymIdentReturnsSymUdfTypeSonameSymTextStringSys_4c27bf37',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
        3 => 6,
      ),
      'symbols' =>
      array (
        0 => 'remember_name',
        1 => 'FUNCTION_SYM',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SfTailWithRememberNameFunctionSymSpNameSpFdparamListReturnsSymTypeWithOptCollateSpCCh_82363a27',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
        3 => 7,
        4 => 8,
        5 => 9,
      ),
      'symbols' =>
      array (
        0 => 'remember_name',
        1 => 'FUNCTION_SYM',
        2 => 'sp_name',
        3 => '(',
        4 => 'sp_fdparam_list',
        5 => ')',
        6 => 'RETURNS_SYM',
        7 => 'type_with_opt_collate',
        8 => 'sp_c_chistics',
        9 => 'sp_proc_stmt',
      ),
    ),
  ),
  'sp_tail' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\SpTailWithProcedureSymRememberNameSpNameSpPdparamListSpCChisticsSpProcStmt_abbd5d82',
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
        1 => 'remember_name',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\XaWithXaSymRecoverSym_17f4ba2b',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'XA_SYM',
        1 => 'RECOVER_SYM',
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
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSuspendWith_421738be',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\OptSuspendWithSuspendSymOptMigrate_81673c91',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SUSPEND_SYM',
        1 => 'opt_migrate',
      ),
    ),
  ),
  'opt_migrate' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptMigrateChoice_f3441398::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\MySql\\Choice\\OptMigrateChoice_f3441398::UseForMigrate_4fae2f87',
      'symbols' =>
      array (
        0 => 'FOR_SYM',
        1 => 'MIGRATE_SYM',
      ),
    ),
  ),
  'install' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\MySql\\Value\\InstallWithInstallSymPluginSymIdentSonameSymTextStringSys_5abcf3ef',
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
  ),
), array (
), array (
));
