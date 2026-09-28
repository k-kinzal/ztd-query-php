<?php

declare(strict_types=1);

/** Generated construction recipes; never retained by a Statement. */
return new \SqlSemantics\Core\Analysis\Vocabulary(array (
  'input' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'cmdlist',
      ),
    ),
  ),
  'cmdlist' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdlistWithCmdlistEcmd_d6dd245b',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'cmdlist',
        1 => 'ecmd',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'ecmd',
      ),
    ),
  ),
  'ecmd' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\EcmdWithSemi_ed1109f6',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'SEMI',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\EcmdWithCmdxSemi_b7577a8f',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'cmdx',
        1 => 'SEMI',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\EcmdWithExplainCmdxSemi_177c493a',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'explain',
        1 => 'cmdx',
        2 => 'SEMI',
      ),
    ),
  ),
  'explain' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExplainWithExplain_d272947b',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'EXPLAIN',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExplainWithExplainQueryPlan_cea054e4',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'EXPLAIN',
        1 => 'QUERY',
        2 => 'PLAN',
      ),
    ),
  ),
  'cmdx' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'cmd',
      ),
    ),
  ),
  'cmd' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithBeginTranstypeTransOpt_d437fc17',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'BEGIN',
        1 => 'transtype',
        2 => 'trans_opt',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithCommitEndTransOpt_ccca6149',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'COMMIT|END',
        1 => 'trans_opt',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithRollbackTransOpt_00f0c935',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ROLLBACK',
        1 => 'trans_opt',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithSavepointNm_bf1c3338',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'SAVEPOINT',
        1 => 'nm',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithReleaseSavepointOptNm_f80bbcd2',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'RELEASE',
        1 => 'savepoint_opt',
        2 => 'nm',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithRollbackTransOptToSavepointOptNm_89eb621f',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'ROLLBACK',
        1 => 'trans_opt',
        2 => 'TO',
        3 => 'savepoint_opt',
        4 => 'nm',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithCreateTableCreateTableArgs_51837b7b',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'create_table',
        1 => 'create_table_args',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithDropTableIfexistsFullname_cdf29e83',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'TABLE',
        2 => 'ifexists',
        3 => 'fullname',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithCreatekwTempViewIfnotexistsNmDbnmEidlistOptAsSelect_982c3d9a',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 6,
        6 => 8,
      ),
      'symbols' =>
      array (
        0 => 'createkw',
        1 => 'temp',
        2 => 'VIEW',
        3 => 'ifnotexists',
        4 => 'nm',
        5 => 'dbnm',
        6 => 'eidlist_opt',
        7 => 'AS',
        8 => 'select',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithDropViewIfexistsFullname_a7db3715',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'VIEW',
        2 => 'ifexists',
        3 => 'fullname',
      ),
    ),
    10 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'select',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithWithDeleteFromXfullnameIndexedOptWhereOptRet_9ab314d3',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
        2 => 4,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'with',
        1 => 'DELETE',
        2 => 'FROM',
        3 => 'xfullname',
        4 => 'indexed_opt',
        5 => 'where_opt_ret',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithWithUpdateOrconfXfullnameIndexedOptSetSetlistFromWhereOptRet_c2eca6e1',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 6,
        5 => 7,
        6 => 8,
      ),
      'symbols' =>
      array (
        0 => 'with',
        1 => 'UPDATE',
        2 => 'orconf',
        3 => 'xfullname',
        4 => 'indexed_opt',
        5 => 'SET',
        6 => 'setlist',
        7 => 'from',
        8 => 'where_opt_ret',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithWithInsertCmdIntoXfullnameIdlistOptSelectUpsert_8f9aafcd',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 6,
      ),
      'symbols' =>
      array (
        0 => 'with',
        1 => 'insert_cmd',
        2 => 'INTO',
        3 => 'xfullname',
        4 => 'idlist_opt',
        5 => 'select',
        6 => 'upsert',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithWithInsertCmdIntoXfullnameIdlistOptDefaultValuesReturning_6fb1fd0c',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
        3 => 4,
        4 => 7,
      ),
      'symbols' =>
      array (
        0 => 'with',
        1 => 'insert_cmd',
        2 => 'INTO',
        3 => 'xfullname',
        4 => 'idlist_opt',
        5 => 'DEFAULT',
        6 => 'VALUES',
        7 => 'returning',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithCreatekwUniqueflagIndexIfnotexistsNmDbnmOnNmLpSortlistRpWhereOpt_0880662b',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 7,
        6 => 9,
        7 => 11,
      ),
      'symbols' =>
      array (
        0 => 'createkw',
        1 => 'uniqueflag',
        2 => 'INDEX',
        3 => 'ifnotexists',
        4 => 'nm',
        5 => 'dbnm',
        6 => 'ON',
        7 => 'nm',
        8 => 'LP',
        9 => 'sortlist',
        10 => 'RP',
        11 => 'where_opt',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithDropIndexIfexistsFullname_44a2d8ce',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'INDEX',
        2 => 'ifexists',
        3 => 'fullname',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithVacuumVinto_5a4cdae3',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'VACUUM',
        1 => 'vinto',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithVacuumNmVinto_13d1a32b',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'VACUUM',
        1 => 'nm',
        2 => 'vinto',
      ),
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithPragmaNmDbnm_5bebe510',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'PRAGMA',
        1 => 'nm',
        2 => 'dbnm',
      ),
    ),
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithPragmaNmDbnmEqNmnum_e6b5c848',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'PRAGMA',
        1 => 'nm',
        2 => 'dbnm',
        3 => 'EQ',
        4 => 'nmnum',
      ),
    ),
    21 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithPragmaNmDbnmLpNmnumRp_c4072dd8',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'PRAGMA',
        1 => 'nm',
        2 => 'dbnm',
        3 => 'LP',
        4 => 'nmnum',
        5 => 'RP',
      ),
    ),
    22 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithPragmaNmDbnmEqMinusNum_88ca56f4',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'PRAGMA',
        1 => 'nm',
        2 => 'dbnm',
        3 => 'EQ',
        4 => 'minus_num',
      ),
    ),
    23 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithPragmaNmDbnmLpMinusNumRp_74bb878f',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'PRAGMA',
        1 => 'nm',
        2 => 'dbnm',
        3 => 'LP',
        4 => 'minus_num',
        5 => 'RP',
      ),
    ),
    24 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithCreatekwTriggerDeclBeginTriggerCmdListEnd_8217e10b',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'createkw',
        1 => 'trigger_decl',
        2 => 'BEGIN',
        3 => 'trigger_cmd_list',
        4 => 'END',
      ),
    ),
    25 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithDropTriggerIfexistsFullname_582f5f4c',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DROP',
        1 => 'TRIGGER',
        2 => 'ifexists',
        3 => 'fullname',
      ),
    ),
    26 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithAttachDatabaseKwOptExprAsExprKeyOpt_87608024',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 4,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'ATTACH',
        1 => 'database_kw_opt',
        2 => 'expr',
        3 => 'AS',
        4 => 'expr',
        5 => 'key_opt',
      ),
    ),
    27 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithDetachDatabaseKwOptExpr_f7c66c8d',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DETACH',
        1 => 'database_kw_opt',
        2 => 'expr',
      ),
    ),
    28 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithReindex_e9f85aaa',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'REINDEX',
      ),
    ),
    29 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithReindexNmDbnm_c0d1653e',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'REINDEX',
        1 => 'nm',
        2 => 'dbnm',
      ),
    ),
    30 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithAnalyze_4726546f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'ANALYZE',
      ),
    ),
    31 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithAnalyzeNmDbnm_88c42263',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ANALYZE',
        1 => 'nm',
        2 => 'dbnm',
      ),
    ),
    32 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithAlterTableFullnameRenameToNm_f687ccf9',
      'fields' =>
      array (
        0 => 2,
        1 => 5,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'TABLE',
        2 => 'fullname',
        3 => 'RENAME',
        4 => 'TO',
        5 => 'nm',
      ),
    ),
    33 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithAlterTableAddColumnFullnameAddKwcolumnOptColumnnameCarglist_868ede0d',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 5,
        3 => 6,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'TABLE',
        2 => 'add_column_fullname',
        3 => 'ADD',
        4 => 'kwcolumn_opt',
        5 => 'columnname',
        6 => 'carglist',
      ),
    ),
    34 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithAlterTableFullnameDropKwcolumnOptNm_73d19d46',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 5,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'TABLE',
        2 => 'fullname',
        3 => 'DROP',
        4 => 'kwcolumn_opt',
        5 => 'nm',
      ),
    ),
    35 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithAlterTableFullnameRenameKwcolumnOptNmToNm_e1bbf896',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
        2 => 5,
        3 => 7,
      ),
      'symbols' =>
      array (
        0 => 'ALTER',
        1 => 'TABLE',
        2 => 'fullname',
        3 => 'RENAME',
        4 => 'kwcolumn_opt',
        5 => 'nm',
        6 => 'TO',
        7 => 'nm',
      ),
    ),
    36 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'create_vtab',
      ),
    ),
    37 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithCreateVtabLpVtabarglistRp_f47fc34c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'create_vtab',
        1 => 'LP',
        2 => 'vtabarglist',
        3 => 'RP',
      ),
    ),
  ),
  'trans_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TransOptWith_6ac05548',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TransOptWithTransaction_ea573324',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'TRANSACTION',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TransOptWithTransactionNm_f694d58b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'TRANSACTION',
        1 => 'nm',
      ),
    ),
  ),
  'transtype' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\TranstypeChoice_9594d9a4::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\TranstypeChoice_9594d9a4::UseDeferred_3e43ac1f',
      'symbols' =>
      array (
        0 => 'DEFERRED',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\TranstypeChoice_9594d9a4::UseImmediate_def178df',
      'symbols' =>
      array (
        0 => 'IMMEDIATE',
      ),
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\TranstypeChoice_9594d9a4::UseExclusive_a6632be9',
      'symbols' =>
      array (
        0 => 'EXCLUSIVE',
      ),
    ),
  ),
  'savepoint_opt' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\SavepointOptChoice_74ffdd90::UseSavepoint_7e4dde5b',
      'symbols' =>
      array (
        0 => 'SAVEPOINT',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\SavepointOptChoice_74ffdd90::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
  ),
  'create_table' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CreateTableWithCreatekwTempTableIfnotexistsNmDbnm_a2506378',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
        3 => 4,
        4 => 5,
      ),
      'symbols' =>
      array (
        0 => 'createkw',
        1 => 'temp',
        2 => 'TABLE',
        3 => 'ifnotexists',
        4 => 'nm',
        5 => 'dbnm',
      ),
    ),
  ),
  'createkw' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\CreatekwChoice_bb5df1f3::UseCreate_fde9c501',
      'symbols' =>
      array (
        0 => 'CREATE',
      ),
    ),
  ),
  'ifnotexists' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\IfnotexistsWith_0b0b9d93',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\IfnotexistsWithIfNotExists_89782dca',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'IF',
        1 => 'NOT',
        2 => 'EXISTS',
      ),
    ),
  ),
  'temp' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TempWithTemp_af9945b3',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'TEMP',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TempWith_582c64db',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
  ),
  'create_table_args' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CreateTableArgsWithLpColumnlistConslistOptRpTableOptionSet_9c826ddf',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'LP',
        1 => 'columnlist',
        2 => 'conslist_opt',
        3 => 'RP',
        4 => 'table_option_set',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CreateTableArgsWithAsSelect_edfabb86',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'AS',
        1 => 'select',
      ),
    ),
  ),
  'table_option_set' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TableOptionSetWith_cfb78063',
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
        0 => 'table_option',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TableOptionSetWithTableOptionSetCommaTableOption_ffea1324',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'table_option_set',
        1 => 'COMMA',
        2 => 'table_option',
      ),
    ),
  ),
  'table_option' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TableOptionWithWithoutNm_d908fceb',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'WITHOUT',
        1 => 'nm',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'nm',
      ),
    ),
  ),
  'columnlist' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ColumnlistWithColumnlistCommaColumnnameCarglist_106fb537',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'columnlist',
        1 => 'COMMA',
        2 => 'columnname',
        3 => 'carglist',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ColumnlistWithColumnnameCarglist_a318faf8',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'columnname',
        1 => 'carglist',
      ),
    ),
  ),
  'columnname' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ColumnnameWithNmTypetoken_92826a89',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'nm',
        1 => 'typetoken',
      ),
    ),
  ),
  'nm' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\NmWithIdj_a2015ecf',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'idj',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\NmWithString_4b1d9359',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'STRING',
      ),
    ),
  ),
  'typetoken' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TypetokenWith_6b66532f',
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
        0 => 'typename',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TypetokenWithTypenameLpSignedRp_53bc47cf',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'typename',
        1 => 'LP',
        2 => 'signed',
        3 => 'RP',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TypetokenWithTypenameLpSignedCommaSignedRp_f13470a9',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'typename',
        1 => 'LP',
        2 => 'signed',
        3 => 'COMMA',
        4 => 'signed',
        5 => 'RP',
      ),
    ),
  ),
  'typename' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TypenameWithIds_cf980f54',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ids',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TypenameWithTypenameIds_bae45eaf',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'typename',
        1 => 'ids',
      ),
    ),
  ),
  'signed' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'plus_num',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'minus_num',
      ),
    ),
  ),
  'scanpt' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\ScanptChoice_055539df::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
  ),
  'scantok' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\ScantokChoice_055539df::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
  ),
  'carglist' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CarglistWithCarglistCcons_d6c91d8e',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'carglist',
        1 => 'ccons',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CarglistWith_b1a2e344',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
  ),
  'ccons' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithConstraintNm_21d95a03',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'CONSTRAINT',
        1 => 'nm',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithDefaultScantokTerm_16444cab',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT',
        1 => 'scantok',
        2 => 'term',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithDefaultLpExprRp_c9989710',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT',
        1 => 'LP',
        2 => 'expr',
        3 => 'RP',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithDefaultPlusScantokTerm_27a5c06c',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT',
        1 => 'PLUS',
        2 => 'scantok',
        3 => 'term',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithDefaultMinusScantokTerm_aee20286',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT',
        1 => 'MINUS',
        2 => 'scantok',
        3 => 'term',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithDefaultScantokId_263f0ac8',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT',
        1 => 'scantok',
        2 => 'id',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithNullOnconf_fb3a3126',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'NULL',
        1 => 'onconf',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithNotNullOnconf_11cbf2a3',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'NOT',
        1 => 'NULL',
        2 => 'onconf',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithPrimaryKeySortorderOnconfAutoinc_23337c2b',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'PRIMARY',
        1 => 'KEY',
        2 => 'sortorder',
        3 => 'onconf',
        4 => 'autoinc',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithUniqueOnconf_426d13d8',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'UNIQUE',
        1 => 'onconf',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithCheckLpExprRp_a257354c',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'CHECK',
        1 => 'LP',
        2 => 'expr',
        3 => 'RP',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithReferencesNmEidlistOptRefargs_77b203d0',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'REFERENCES',
        1 => 'nm',
        2 => 'eidlist_opt',
        3 => 'refargs',
      ),
    ),
    12 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'defer_subclause',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithCollateIds_f026273f',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'COLLATE',
        1 => 'ids',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithGeneratedAlwaysAsGenerated_49de3479',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'GENERATED',
        1 => 'ALWAYS',
        2 => 'AS',
        3 => 'generated',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithAsGenerated_a8c85419',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'AS',
        1 => 'generated',
      ),
    ),
  ),
  'generated' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\GeneratedWithLpExprRp_b1a94f09',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LP',
        1 => 'expr',
        2 => 'RP',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\GeneratedWithLpExprRpId_5d74118d',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'LP',
        1 => 'expr',
        2 => 'RP',
        3 => 'ID',
      ),
    ),
  ),
  'autoinc' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\AutoincChoice_d1be63bd::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\AutoincChoice_d1be63bd::UseAutoincrement_5e629590',
      'symbols' =>
      array (
        0 => 'AUTOINCR',
      ),
    ),
  ),
  'refargs' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\RefargsWith_679d6942',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\RefargsWithRefargsRefarg_1dce60fb',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'refargs',
        1 => 'refarg',
      ),
    ),
  ),
  'refarg' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\RefargWithMatchNm_8834e4f9',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'MATCH',
        1 => 'nm',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\RefargWithOnInsertRefact_dedcfed8',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ON',
        1 => 'INSERT',
        2 => 'refact',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\RefargWithOnDeleteRefact_1b1d35b4',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ON',
        1 => 'DELETE',
        2 => 'refact',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\RefargWithOnUpdateRefact_739fd957',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ON',
        1 => 'UPDATE',
        2 => 'refact',
      ),
    ),
  ),
  'refact' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\RefactWithSetNull_db0e85ba',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SET',
        1 => 'NULL',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\RefactWithSetDefault_af2a4c92',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'SET',
        1 => 'DEFAULT',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\RefactWithCascade_57ac19da',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'CASCADE',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\RefactWithRestrict_2b270421',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'RESTRICT',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\RefactWithNoAction_aa8fa031',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NO',
        1 => 'ACTION',
      ),
    ),
  ),
  'defer_subclause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\DeferSubclauseWithNotDeferrableInitDeferredPredOpt_60b33c1f',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'NOT',
        1 => 'DEFERRABLE',
        2 => 'init_deferred_pred_opt',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\DeferSubclauseWithDeferrableInitDeferredPredOpt_1dbafeef',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DEFERRABLE',
        1 => 'init_deferred_pred_opt',
      ),
    ),
  ),
  'init_deferred_pred_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\InitDeferredPredOptWith_3c679ef0',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\InitDeferredPredOptWithInitiallyDeferred_f3a776bf',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'INITIALLY',
        1 => 'DEFERRED',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\InitDeferredPredOptWithInitiallyImmediate_64aae120',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'INITIALLY',
        1 => 'IMMEDIATE',
      ),
    ),
  ),
  'conslist_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ConslistOptWith_0b11f7d2',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ConslistOptWithCommaConslist_04f617dc',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'COMMA',
        1 => 'conslist',
      ),
    ),
  ),
  'conslist' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ConslistWithConslistTconscommaTcons_c4260c11',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'conslist',
        1 => 'tconscomma',
        2 => 'tcons',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'tcons',
      ),
    ),
  ),
  'tconscomma' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\TconscommaChoice_d7313e6d::Use_d03502c4',
      'symbols' =>
      array (
        0 => 'COMMA',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\TconscommaChoice_d7313e6d::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
  ),
  'tcons' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TconsWithConstraintNm_ca354ac2',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'CONSTRAINT',
        1 => 'nm',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TconsWithPrimaryKeyLpSortlistAutoincRpOnconf_724fc4cd',
      'fields' =>
      array (
        0 => 3,
        1 => 4,
        2 => 6,
      ),
      'symbols' =>
      array (
        0 => 'PRIMARY',
        1 => 'KEY',
        2 => 'LP',
        3 => 'sortlist',
        4 => 'autoinc',
        5 => 'RP',
        6 => 'onconf',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TconsWithUniqueLpSortlistRpOnconf_1bb4e282',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'UNIQUE',
        1 => 'LP',
        2 => 'sortlist',
        3 => 'RP',
        4 => 'onconf',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TconsWithCheckLpExprRpOnconf_f04e4700',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'CHECK',
        1 => 'LP',
        2 => 'expr',
        3 => 'RP',
        4 => 'onconf',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TconsWithForeignKeyLpEidlistRpReferencesNmEidlistOptRefargsDeferSubclauseOpt_a2145070',
      'fields' =>
      array (
        0 => 3,
        1 => 6,
        2 => 7,
        3 => 8,
        4 => 9,
      ),
      'symbols' =>
      array (
        0 => 'FOREIGN',
        1 => 'KEY',
        2 => 'LP',
        3 => 'eidlist',
        4 => 'RP',
        5 => 'REFERENCES',
        6 => 'nm',
        7 => 'eidlist_opt',
        8 => 'refargs',
        9 => 'defer_subclause_opt',
      ),
    ),
  ),
  'defer_subclause_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\DeferSubclauseOptWith_34952d9b',
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
        0 => 'defer_subclause',
      ),
    ),
  ),
  'onconf' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\OnconfWith_28b2b21e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\OnconfWithOnConflictResolvetype_4edd828c',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ON',
        1 => 'CONFLICT',
        2 => 'resolvetype',
      ),
    ),
  ),
  'orconf' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\OrconfWith_b80e7ae1',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\OrconfWithOrResolvetype_f0ef4a84',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'OR',
        1 => 'resolvetype',
      ),
    ),
  ),
  'resolvetype' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'raisetype',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ResolvetypeWithIgnore_7fdbfe29',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'IGNORE',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ResolvetypeWithReplace_ee120ee4',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'REPLACE',
      ),
    ),
  ),
  'ifexists' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\IfexistsWithIfExists_ae50ded3',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'IF',
        1 => 'EXISTS',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\IfexistsWith_7b0c06e4',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
  ),
  'select' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SelectWithWithWqlistSelectnowith_dc3f10ec',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'wqlist',
        2 => 'selectnowith',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SelectWithWithRecursiveWqlistSelectnowith_5f4629ff',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'RECURSIVE',
        2 => 'wqlist',
        3 => 'selectnowith',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'selectnowith',
      ),
    ),
  ),
  'selectnowith' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'oneselect',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SelectnowithWithSelectnowithMultiselectOpOneselect_7000656a',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'selectnowith',
        1 => 'multiselect_op',
        2 => 'oneselect',
      ),
    ),
  ),
  'multiselect_op' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\MultiselectOpWithUnion_45e0b834',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'UNION',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\MultiselectOpWithUnionAll_1d6b9617',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'UNION',
        1 => 'ALL',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\MultiselectOpWithExceptIntersect_f86e511a',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'EXCEPT|INTERSECT',
      ),
    ),
  ),
  'oneselect' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\OneselectWithSelectDistinctSelcollistFromWhereOptGroupbyOptHavingOptOrderbyOptLimitOpt_218e0475',
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
        0 => 'SELECT',
        1 => 'distinct',
        2 => 'selcollist',
        3 => 'from',
        4 => 'where_opt',
        5 => 'groupby_opt',
        6 => 'having_opt',
        7 => 'orderby_opt',
        8 => 'limit_opt',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\OneselectWithSelectDistinctSelcollistFromWhereOptGroupbyOptHavingOptWindowClauseOrderbyO_9a3dd714',
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
      ),
      'symbols' =>
      array (
        0 => 'SELECT',
        1 => 'distinct',
        2 => 'selcollist',
        3 => 'from',
        4 => 'where_opt',
        5 => 'groupby_opt',
        6 => 'having_opt',
        7 => 'window_clause',
        8 => 'orderby_opt',
        9 => 'limit_opt',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'values',
      ),
    ),
    3 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'mvalues',
      ),
    ),
  ),
  'values' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ValuesWithValuesLpNexprlistRp_eab5da94',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'VALUES',
        1 => 'LP',
        2 => 'nexprlist',
        3 => 'RP',
      ),
    ),
  ),
  'mvalues' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\MvaluesWithValuesCommaLpNexprlistRp_cd8c14f9',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'values',
        1 => 'COMMA',
        2 => 'LP',
        3 => 'nexprlist',
        4 => 'RP',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\MvaluesWithMvaluesCommaLpNexprlistRp_fe975bc4',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'mvalues',
        1 => 'COMMA',
        2 => 'LP',
        3 => 'nexprlist',
        4 => 'RP',
      ),
    ),
  ),
  'distinct' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\DistinctChoice_deb627ba::UseDistinct_d879f806',
      'symbols' =>
      array (
        0 => 'DISTINCT',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\DistinctChoice_deb627ba::UseAll_b5c7aed7',
      'symbols' =>
      array (
        0 => 'ALL',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\DistinctChoice_deb627ba::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
  ),
  'sclp' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SclpWithSelcollistComma_fd0bc772',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'selcollist',
        1 => 'COMMA',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SclpWith_2c42dcc1',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
  ),
  'selcollist' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SelcollistWithSclpScanptExprScanptAs_62f68771',
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
        0 => 'sclp',
        1 => 'scanpt',
        2 => 'expr',
        3 => 'scanpt',
        4 => 'as',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SelcollistWithSclpScanptStar_bae6710f',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'sclp',
        1 => 'scanpt',
        2 => 'STAR',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SelcollistWithSclpScanptNmDotStar_bbe31973',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'sclp',
        1 => 'scanpt',
        2 => 'nm',
        3 => 'DOT',
        4 => 'STAR',
      ),
    ),
  ),
  'as' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\AsWithAsNm_f6e3a168',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'AS',
        1 => 'nm',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\AsWithIds_1689a84e',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ids',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\AsWith_9dc353c5',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
  ),
  'from' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FromWith_1d96487f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FromWithFromSeltablist_6e414a5b',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'FROM',
        1 => 'seltablist',
      ),
    ),
  ),
  'stl_prefix' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\StlPrefixWithSeltablistJoinop_d0edaade',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'seltablist',
        1 => 'joinop',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\StlPrefixWith_4d42bc16',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
  ),
  'seltablist' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SeltablistWithStlPrefixNmDbnmAsOnUsing_848295d2',
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
        0 => 'stl_prefix',
        1 => 'nm',
        2 => 'dbnm',
        3 => 'as',
        4 => 'on_using',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SeltablistWithStlPrefixNmDbnmAsIndexedByOnUsing_cb25a795',
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
        0 => 'stl_prefix',
        1 => 'nm',
        2 => 'dbnm',
        3 => 'as',
        4 => 'indexed_by',
        5 => 'on_using',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SeltablistWithStlPrefixNmDbnmLpExprlistRpAsOnUsing_8d2708de',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 4,
        4 => 6,
        5 => 7,
      ),
      'symbols' =>
      array (
        0 => 'stl_prefix',
        1 => 'nm',
        2 => 'dbnm',
        3 => 'LP',
        4 => 'exprlist',
        5 => 'RP',
        6 => 'as',
        7 => 'on_using',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SeltablistWithStlPrefixLpSelectRpAsOnUsing_201b7267',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'stl_prefix',
        1 => 'LP',
        2 => 'select',
        3 => 'RP',
        4 => 'as',
        5 => 'on_using',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SeltablistWithStlPrefixLpSeltablistRpAsOnUsing_e659a8d5',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'stl_prefix',
        1 => 'LP',
        2 => 'seltablist',
        3 => 'RP',
        4 => 'as',
        5 => 'on_using',
      ),
    ),
  ),
  'dbnm' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\DbnmWith_dce39c19',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\DbnmWithDotNm_710678c7',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'DOT',
        1 => 'nm',
      ),
    ),
  ),
  'fullname' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'nm',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FullnameWithNmDotNm_4e1d9c77',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'nm',
        1 => 'DOT',
        2 => 'nm',
      ),
    ),
  ),
  'xfullname' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'nm',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\XfullnameWithNmDotNm_abe868cc',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'nm',
        1 => 'DOT',
        2 => 'nm',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\XfullnameWithNmDotNmAsNm_16c38d68',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'nm',
        1 => 'DOT',
        2 => 'nm',
        3 => 'AS',
        4 => 'nm',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\XfullnameWithNmAsNm_0204e3b8',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'nm',
        1 => 'AS',
        2 => 'nm',
      ),
    ),
  ),
  'joinop' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\JoinopWithCommaJoin_2b032ba0',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'COMMA|JOIN',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\JoinopWithJoinKwJoin_4110763c',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'JOIN_KW',
        1 => 'JOIN',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\JoinopWithJoinKwNmJoin_869cddff',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'JOIN_KW',
        1 => 'nm',
        2 => 'JOIN',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\JoinopWithJoinKwNmNmJoin_bfbfa5b2',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'JOIN_KW',
        1 => 'nm',
        2 => 'nm',
        3 => 'JOIN',
      ),
    ),
  ),
  'on_using' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\OnUsingWithOnExpr_b5a899fb',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'ON',
        1 => 'expr',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\OnUsingWithUsingLpIdlistRp_691e2367',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'USING',
        1 => 'LP',
        2 => 'idlist',
        3 => 'RP',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\OnUsingWith_e9e4278d',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
  ),
  'indexed_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\IndexedOptWith_f2a08353',
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
        0 => 'indexed_by',
      ),
    ),
  ),
  'indexed_by' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\IndexedByWithIndexedByNm_70f3751d',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'INDEXED',
        1 => 'BY',
        2 => 'nm',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\IndexedByWithNotIndexed_d668bbbe',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NOT',
        1 => 'INDEXED',
      ),
    ),
  ),
  'orderby_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\OrderbyOptWith_87ddfe45',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\OrderbyOptWithOrderBySortlist_5aa58f40',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'ORDER',
        1 => 'BY',
        2 => 'sortlist',
      ),
    ),
  ),
  'sortlist' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SortlistWithSortlistCommaExprSortorderNulls_beac8704',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'sortlist',
        1 => 'COMMA',
        2 => 'expr',
        3 => 'sortorder',
        4 => 'nulls',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SortlistWithExprSortorderNulls_988a1693',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'sortorder',
        2 => 'nulls',
      ),
    ),
  ),
  'sortorder' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\SortorderChoice_01affc0e::UseAsc_323b087e',
      'symbols' =>
      array (
        0 => 'ASC',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\SortorderChoice_01affc0e::UseDesc_984da4fe',
      'symbols' =>
      array (
        0 => 'DESC',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\SortorderChoice_01affc0e::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
  ),
  'nulls' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\NullsWithNullsFirst_dbfb53e1',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NULLS',
        1 => 'FIRST',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\NullsWithNullsLast_e1f7498f',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NULLS',
        1 => 'LAST',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\NullsWith_c9558528',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
  ),
  'groupby_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\GroupbyOptWith_305dfc25',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\GroupbyOptWithGroupByNexprlist_4a73c2cf',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'GROUP',
        1 => 'BY',
        2 => 'nexprlist',
      ),
    ),
  ),
  'having_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\HavingOptWith_cb82962d',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\HavingOptWithHavingExpr_f311112e',
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
  'limit_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\LimitOptWith_aab92ff6',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\LimitOptWithLimitExpr_b827c6a0',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LIMIT',
        1 => 'expr',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\LimitOptWithLimitExprOffsetExpr_d2261940',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'LIMIT',
        1 => 'expr',
        2 => 'OFFSET',
        3 => 'expr',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\LimitOptWithLimitExprCommaExpr_1d4d2695',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'LIMIT',
        1 => 'expr',
        2 => 'COMMA',
        3 => 'expr',
      ),
    ),
  ),
  'where_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WhereOptWith_765a2f1c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WhereOptWithWhereExpr_93445e09',
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
  'where_opt_ret' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WhereOptRetWith_b0796d34',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WhereOptRetWithWhereExpr_eede6fba',
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
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WhereOptRetWithReturningSelcollist_f5e1123e',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'RETURNING',
        1 => 'selcollist',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WhereOptRetWithWhereExprReturningSelcollist_679930c1',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'WHERE',
        1 => 'expr',
        2 => 'RETURNING',
        3 => 'selcollist',
      ),
    ),
  ),
  'setlist' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SetlistWithSetlistCommaNmEqExpr_08ee1b3e',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'setlist',
        1 => 'COMMA',
        2 => 'nm',
        3 => 'EQ',
        4 => 'expr',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SetlistWithSetlistCommaLpIdlistRpEqExpr_7f66f27b',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
        2 => 5,
        3 => 6,
      ),
      'symbols' =>
      array (
        0 => 'setlist',
        1 => 'COMMA',
        2 => 'LP',
        3 => 'idlist',
        4 => 'RP',
        5 => 'EQ',
        6 => 'expr',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SetlistWithNmEqExpr_6216c267',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'nm',
        1 => 'EQ',
        2 => 'expr',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SetlistWithLpIdlistRpEqExpr_1ebc160f',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'LP',
        1 => 'idlist',
        2 => 'RP',
        3 => 'EQ',
        4 => 'expr',
      ),
    ),
  ),
  'upsert' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\UpsertWith_a11dcd45',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\UpsertWithReturningSelcollist_c53e1ecb',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'RETURNING',
        1 => 'selcollist',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\UpsertWithOnConflictLpSortlistRpWhereOptDoUpdateSetSetlistWhereOptUpsert_b3d7f7ee',
      'fields' =>
      array (
        0 => 3,
        1 => 5,
        2 => 9,
        3 => 10,
        4 => 11,
      ),
      'symbols' =>
      array (
        0 => 'ON',
        1 => 'CONFLICT',
        2 => 'LP',
        3 => 'sortlist',
        4 => 'RP',
        5 => 'where_opt',
        6 => 'DO',
        7 => 'UPDATE',
        8 => 'SET',
        9 => 'setlist',
        10 => 'where_opt',
        11 => 'upsert',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\UpsertWithOnConflictLpSortlistRpWhereOptDoNothingUpsert_820f0d71',
      'fields' =>
      array (
        0 => 3,
        1 => 5,
        2 => 8,
      ),
      'symbols' =>
      array (
        0 => 'ON',
        1 => 'CONFLICT',
        2 => 'LP',
        3 => 'sortlist',
        4 => 'RP',
        5 => 'where_opt',
        6 => 'DO',
        7 => 'NOTHING',
        8 => 'upsert',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\UpsertWithOnConflictDoNothingReturning_162a31bb',
      'fields' =>
      array (
        0 => 4,
      ),
      'symbols' =>
      array (
        0 => 'ON',
        1 => 'CONFLICT',
        2 => 'DO',
        3 => 'NOTHING',
        4 => 'returning',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\UpsertWithOnConflictDoUpdateSetSetlistWhereOptReturning_18ff9ed4',
      'fields' =>
      array (
        0 => 5,
        1 => 6,
        2 => 7,
      ),
      'symbols' =>
      array (
        0 => 'ON',
        1 => 'CONFLICT',
        2 => 'DO',
        3 => 'UPDATE',
        4 => 'SET',
        5 => 'setlist',
        6 => 'where_opt',
        7 => 'returning',
      ),
    ),
  ),
  'returning' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ReturningWithReturningSelcollist_e8199903',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'RETURNING',
        1 => 'selcollist',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ReturningWith_46f90fc5',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
  ),
  'insert_cmd' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\InsertCmdWithInsertOrconf_17d8eea1',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'INSERT',
        1 => 'orconf',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\InsertCmdWithReplace_7e70f0cf',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'REPLACE',
      ),
    ),
  ),
  'idlist_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\IdlistOptWith_1fa4fcd2',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\IdlistOptWithLpIdlistRp_62ca8e2d',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LP',
        1 => 'idlist',
        2 => 'RP',
      ),
    ),
  ),
  'idlist' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\IdlistWithIdlistCommaNm_91e2994c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'idlist',
        1 => 'COMMA',
        2 => 'nm',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'nm',
      ),
    ),
  ),
  'expr' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'term',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithLpExprRp_ad646753',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LP',
        1 => 'expr',
        2 => 'RP',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithIdj_e1794d68',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'idj',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithNmDotNm_c54b4845',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'nm',
        1 => 'DOT',
        2 => 'nm',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithNmDotNmDotNm_7d0c5b5a',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'nm',
        1 => 'DOT',
        2 => 'nm',
        3 => 'DOT',
        4 => 'nm',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithVariable_0aa27fc0',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'VARIABLE',
      ),
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprCollateIds_f6a2b322',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'COLLATE',
        2 => 'ids',
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithCastLpExprAsTypetokenRp_15f33207',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'CAST',
        1 => 'LP',
        2 => 'expr',
        3 => 'AS',
        4 => 'typetoken',
        5 => 'RP',
      ),
    ),
    8 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithIdjLpDistinctExprlistRp_7162d1a1',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'idj',
        1 => 'LP',
        2 => 'distinct',
        3 => 'exprlist',
        4 => 'RP',
      ),
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithIdjLpDistinctExprlistOrderBySortlistRp_6aa5130a',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 6,
      ),
      'symbols' =>
      array (
        0 => 'idj',
        1 => 'LP',
        2 => 'distinct',
        3 => 'exprlist',
        4 => 'ORDER',
        5 => 'BY',
        6 => 'sortlist',
        7 => 'RP',
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithIdjLpStarRp_41781688',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'idj',
        1 => 'LP',
        2 => 'STAR',
        3 => 'RP',
      ),
    ),
    11 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithIdjLpDistinctExprlistRpFilterOver_62523790',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'idj',
        1 => 'LP',
        2 => 'distinct',
        3 => 'exprlist',
        4 => 'RP',
        5 => 'filter_over',
      ),
    ),
    12 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithIdjLpDistinctExprlistOrderBySortlistRpFilterOver_076d2e47',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 6,
        4 => 8,
      ),
      'symbols' =>
      array (
        0 => 'idj',
        1 => 'LP',
        2 => 'distinct',
        3 => 'exprlist',
        4 => 'ORDER',
        5 => 'BY',
        6 => 'sortlist',
        7 => 'RP',
        8 => 'filter_over',
      ),
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithIdjLpStarRpFilterOver_27a4a7eb',
      'fields' =>
      array (
        0 => 0,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'idj',
        1 => 'LP',
        2 => 'STAR',
        3 => 'RP',
        4 => 'filter_over',
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithLpNexprlistCommaExprRp_b677307a',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'LP',
        1 => 'nexprlist',
        2 => 'COMMA',
        3 => 'expr',
        4 => 'RP',
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprAndExpr_33592aae',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'AND',
        2 => 'expr',
      ),
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprOrExpr_fcc306b3',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'OR',
        2 => 'expr',
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprLtGtGeLeExpr_c64bb14c',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'LT|GT|GE|LE',
        2 => 'expr',
      ),
    ),
    18 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprEqNeExpr_49d16f16',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'EQ|NE',
        2 => 'expr',
      ),
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprBitandBitorLshiftRshiftExpr_67d895c2',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'BITAND|BITOR|LSHIFT|RSHIFT',
        2 => 'expr',
      ),
    ),
    20 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprPlusMinusExpr_82e360dc',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'PLUS|MINUS',
        2 => 'expr',
      ),
    ),
    21 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprStarSlashRemExpr_6ca99fe8',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'STAR|SLASH|REM',
        2 => 'expr',
      ),
    ),
    22 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprConcatExpr_2239f2bc',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'CONCAT',
        2 => 'expr',
      ),
    ),
    23 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprLikeopExpr_e761ed21',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'likeop',
        2 => 'expr',
      ),
    ),
    24 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprLikeopExprEscapeExpr_5d4dd842',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'likeop',
        2 => 'expr',
        3 => 'ESCAPE',
        4 => 'expr',
      ),
    ),
    25 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprIsnullNotnull_b1183766',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'ISNULL|NOTNULL',
      ),
    ),
    26 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprNotNull_025d71af',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'NOT',
        2 => 'NULL',
      ),
    ),
    27 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprIsExpr_cfeb7fba',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'IS',
        2 => 'expr',
      ),
    ),
    28 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprIsNotExpr_a631d709',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'IS',
        2 => 'NOT',
        3 => 'expr',
      ),
    ),
    29 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprIsNotDistinctFromExpr_06cc5755',
      'fields' =>
      array (
        0 => 0,
        1 => 5,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'IS',
        2 => 'NOT',
        3 => 'DISTINCT',
        4 => 'FROM',
        5 => 'expr',
      ),
    ),
    30 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprIsDistinctFromExpr_2d05c076',
      'fields' =>
      array (
        0 => 0,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'IS',
        2 => 'DISTINCT',
        3 => 'FROM',
        4 => 'expr',
      ),
    ),
    31 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithNotExpr_22095ba5',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'NOT',
        1 => 'expr',
      ),
    ),
    32 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithBitnotExpr_fb4e23b4',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'BITNOT',
        1 => 'expr',
      ),
    ),
    33 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithPlusMinusExpr_657f0f03',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'PLUS|MINUS',
        1 => 'expr',
      ),
    ),
    34 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprPtrExpr_6dab14bf',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'PTR',
        2 => 'expr',
      ),
    ),
    35 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprBetweenOpExprAndExpr_5e5d6d1b',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'between_op',
        2 => 'expr',
        3 => 'AND',
        4 => 'expr',
      ),
    ),
    36 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprInOpLpExprlistRp_a0b7c2b5',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'in_op',
        2 => 'LP',
        3 => 'exprlist',
        4 => 'RP',
      ),
    ),
    37 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithLpSelectRp_2c2be90a',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LP',
        1 => 'select',
        2 => 'RP',
      ),
    ),
    38 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprInOpLpSelectRp_a20110e2',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'in_op',
        2 => 'LP',
        3 => 'select',
        4 => 'RP',
      ),
    ),
    39 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprInOpNmDbnmParenExprlist_be04cc02',
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
        0 => 'expr',
        1 => 'in_op',
        2 => 'nm',
        3 => 'dbnm',
        4 => 'paren_exprlist',
      ),
    ),
    40 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExistsLpSelectRp_c7d40730',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'EXISTS',
        1 => 'LP',
        2 => 'select',
        3 => 'RP',
      ),
    ),
    41 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithCaseCaseOperandCaseExprlistCaseElseEnd_f3c36399',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
        2 => 3,
      ),
      'symbols' =>
      array (
        0 => 'CASE',
        1 => 'case_operand',
        2 => 'case_exprlist',
        3 => 'case_else',
        4 => 'END',
      ),
    ),
    42 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithRaiseLpIgnoreRp_ccfbbaf2',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'RAISE',
        1 => 'LP',
        2 => 'IGNORE',
        3 => 'RP',
      ),
    ),
    43 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithRaiseLpRaisetypeCommaExprRp_5b2f59dd',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
      ),
      'symbols' =>
      array (
        0 => 'RAISE',
        1 => 'LP',
        2 => 'raisetype',
        3 => 'COMMA',
        4 => 'expr',
        5 => 'RP',
      ),
    ),
  ),
  'term' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TermWithNullFloatBlob_0bfe6b30',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'NULL|FLOAT|BLOB',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TermWithString_e66cd9ed',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'STRING',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TermWithInteger_298801b2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'INTEGER',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TermWithCtimeKw_9aa2d016',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'CTIME_KW',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TermWithQnumber_45fd67fa',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'QNUMBER',
      ),
    ),
  ),
  'likeop' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\LikeopWithLikeKwMatch_1a9a5ee1',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'LIKE_KW|MATCH',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\LikeopWithNotLikeKwMatch_e63ce34e',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'NOT',
        1 => 'LIKE_KW|MATCH',
      ),
    ),
  ),
  'between_op' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\BetweenOpWithBetween_1ed31440',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'BETWEEN',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\BetweenOpWithNotBetween_161fe3d3',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NOT',
        1 => 'BETWEEN',
      ),
    ),
  ),
  'in_op' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\InOpWithIn_369f7c19',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'IN',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\InOpWithNotIn_704e0ad0',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NOT',
        1 => 'IN',
      ),
    ),
  ),
  'case_exprlist' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CaseExprlistWithCaseExprlistWhenExprThenExpr_1993e882',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'case_exprlist',
        1 => 'WHEN',
        2 => 'expr',
        3 => 'THEN',
        4 => 'expr',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CaseExprlistWithWhenExprThenExpr_fc6d9fa6',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'WHEN',
        1 => 'expr',
        2 => 'THEN',
        3 => 'expr',
      ),
    ),
  ),
  'case_else' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CaseElseWithElseExpr_f8f4f7a0',
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
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CaseElseWith_4209e8da',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
  ),
  'case_operand' =>
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
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CaseOperandWith_b8923944',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
  ),
  'exprlist' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'nexprlist',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprlistWith_b354471e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
  ),
  'nexprlist' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\NexprlistWithNexprlistCommaExpr_15af8daa',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'nexprlist',
        1 => 'COMMA',
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
  'paren_exprlist' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ParenExprlistWith_f604e4a2',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ParenExprlistWithLpExprlistRp_6d8812f5',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LP',
        1 => 'exprlist',
        2 => 'RP',
      ),
    ),
  ),
  'uniqueflag' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\UniqueflagChoice_996fc274::UseUnique_64636a12',
      'symbols' =>
      array (
        0 => 'UNIQUE',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\UniqueflagChoice_996fc274::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
  ),
  'eidlist_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\EidlistOptWith_c2a4c23e',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\EidlistOptWithLpEidlistRp_7b6e32ba',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'LP',
        1 => 'eidlist',
        2 => 'RP',
      ),
    ),
  ),
  'eidlist' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\EidlistWithEidlistCommaNmCollateSortorder_6ea64936',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'eidlist',
        1 => 'COMMA',
        2 => 'nm',
        3 => 'collate',
        4 => 'sortorder',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\EidlistWithNmCollateSortorder_b27838a9',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'nm',
        1 => 'collate',
        2 => 'sortorder',
      ),
    ),
  ),
  'collate' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CollateWith_de5f77fb',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CollateWithCollateIds_8425b902',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'COLLATE',
        1 => 'ids',
      ),
    ),
  ),
  'vinto' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\VintoWithIntoExpr_bafc6484',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'INTO',
        1 => 'expr',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\VintoWith_b8f3b382',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
  ),
  'nmnum' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'plus_num',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'nm',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\NmnumWithOn_2e23b74d',
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
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\NmnumWithDelete_0e682fa5',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DELETE',
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\NmnumWithDefault_eda4f18c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'DEFAULT',
      ),
    ),
  ),
  'plus_num' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\PlusNumWithPlusNumber_039178d8',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'PLUS',
        1 => 'number',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\PlusNumWithNumber_52f2d00d',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'number',
      ),
    ),
  ),
  'minus_num' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\MinusNumWithMinusNumber_9e51d37f',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'MINUS',
        1 => 'number',
      ),
    ),
  ),
  'trigger_decl' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TriggerDeclWithTempTriggerIfnotexistsNmDbnmTriggerTimeTriggerEventOnFullnameForeachClauseW_09bb7d8c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 6,
        6 => 8,
        7 => 9,
        8 => 10,
      ),
      'symbols' =>
      array (
        0 => 'temp',
        1 => 'TRIGGER',
        2 => 'ifnotexists',
        3 => 'nm',
        4 => 'dbnm',
        5 => 'trigger_time',
        6 => 'trigger_event',
        7 => 'ON',
        8 => 'fullname',
        9 => 'foreach_clause',
        10 => 'when_clause',
      ),
    ),
  ),
  'trigger_time' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TriggerTimeWithBeforeAfter_fa3bc32b',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'BEFORE|AFTER',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TriggerTimeWithInsteadOf_85c866b5',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'INSTEAD',
        1 => 'OF',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TriggerTimeWith_053237f2',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
  ),
  'trigger_event' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TriggerEventWithDeleteInsert_67056f08',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'DELETE|INSERT',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TriggerEventWithUpdate_6cc7e649',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'UPDATE',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TriggerEventWithUpdateOfIdlist_1483bd2b',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'UPDATE',
        1 => 'OF',
        2 => 'idlist',
      ),
    ),
  ),
  'foreach_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ForeachClauseWith_6244f88c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ForeachClauseWithForEachRow_f9f14ce7',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'FOR',
        1 => 'EACH',
        2 => 'ROW',
      ),
    ),
  ),
  'when_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WhenClauseWith_c6e08bf6',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WhenClauseWithWhenExpr_6456c232',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'WHEN',
        1 => 'expr',
      ),
    ),
  ),
  'trigger_cmd_list' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TriggerCmdListWithTriggerCmdListTriggerCmdSemi_a3990e43',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'trigger_cmd_list',
        1 => 'trigger_cmd',
        2 => 'SEMI',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TriggerCmdListWithTriggerCmdSemi_34d876f1',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'trigger_cmd',
        1 => 'SEMI',
      ),
    ),
  ),
  'trnm' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'nm',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TrnmWithNmDotNm_3ea19d9c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'nm',
        1 => 'DOT',
        2 => 'nm',
      ),
    ),
  ),
  'tridxby' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TridxbyWith_da0128c6',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TridxbyWithIndexedByNm_c201f940',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'INDEXED',
        1 => 'BY',
        2 => 'nm',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TridxbyWithNotIndexed_6c190bec',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NOT',
        1 => 'INDEXED',
      ),
    ),
  ),
  'trigger_cmd' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TriggerCmdWithUpdateOrconfTrnmTridxbySetSetlistFromWhereOptScanpt_a7c2ea42',
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
        0 => 'UPDATE',
        1 => 'orconf',
        2 => 'trnm',
        3 => 'tridxby',
        4 => 'SET',
        5 => 'setlist',
        6 => 'from',
        7 => 'where_opt',
        8 => 'scanpt',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TriggerCmdWithScanptInsertCmdIntoTrnmIdlistOptSelectUpsertScanpt_362fe131',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 6,
        6 => 7,
      ),
      'symbols' =>
      array (
        0 => 'scanpt',
        1 => 'insert_cmd',
        2 => 'INTO',
        3 => 'trnm',
        4 => 'idlist_opt',
        5 => 'select',
        6 => 'upsert',
        7 => 'scanpt',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TriggerCmdWithDeleteFromTrnmTridxbyWhereOptScanpt_587bfb70',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'DELETE',
        1 => 'FROM',
        2 => 'trnm',
        3 => 'tridxby',
        4 => 'where_opt',
        5 => 'scanpt',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TriggerCmdWithScanptSelectScanpt_2cdaedbb',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'scanpt',
        1 => 'select',
        2 => 'scanpt',
      ),
    ),
  ),
  'raisetype' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\RaisetypeChoice_0819de2d::UseRollback_587fa628',
      'symbols' =>
      array (
        0 => 'ROLLBACK',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\RaisetypeChoice_0819de2d::UseAbort_315a1f25',
      'symbols' =>
      array (
        0 => 'ABORT',
      ),
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\RaisetypeChoice_0819de2d::UseFail_425305e2',
      'symbols' =>
      array (
        0 => 'FAIL',
      ),
    ),
  ),
  'key_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\KeyOptWith_d69758aa',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\KeyOptWithKeyExpr_a14c915a',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'KEY',
        1 => 'expr',
      ),
    ),
  ),
  'database_kw_opt' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\DatabaseKwOptChoice_a5a26b1d::UseDatabase_8e663907',
      'symbols' =>
      array (
        0 => 'DATABASE',
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\DatabaseKwOptChoice_a5a26b1d::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
  ),
  'add_column_fullname' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'fullname',
      ),
    ),
  ),
  'kwcolumn_opt' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\KwcolumnOptChoice_eefdbfac::Use_e3b0c442',
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\KwcolumnOptChoice_eefdbfac::UseColumn_83a8e21d',
      'symbols' =>
      array (
        0 => 'COLUMNKW',
      ),
    ),
  ),
  'create_vtab' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CreateVtabWithCreatekwVirtualTableIfnotexistsNmDbnmUsingNm_51382012',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
        2 => 4,
        3 => 5,
        4 => 7,
      ),
      'symbols' =>
      array (
        0 => 'createkw',
        1 => 'VIRTUAL',
        2 => 'TABLE',
        3 => 'ifnotexists',
        4 => 'nm',
        5 => 'dbnm',
        6 => 'USING',
        7 => 'nm',
      ),
    ),
  ),
  'vtabarglist' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'vtabarg',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\VtabarglistWithVtabarglistCommaVtabarg_297de28e',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'vtabarglist',
        1 => 'COMMA',
        2 => 'vtabarg',
      ),
    ),
  ),
  'vtabarg' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\VtabargWith_f1ee34eb',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\VtabargWithVtabargVtabargtoken_627d8ccd',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'vtabarg',
        1 => 'vtabargtoken',
      ),
    ),
  ),
  'vtabargtoken' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\VtabargtokenWithAny_4183ebc7',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'ANY',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\VtabargtokenWithLpAnylistRp_95c44fdc',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'lp',
        1 => 'anylist',
        2 => 'RP',
      ),
    ),
  ),
  'lp' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\LpChoice_0ce700cc::Use_32ebb1ab',
      'symbols' =>
      array (
        0 => 'LP',
      ),
    ),
  ),
  'anylist' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\AnylistWith_b1da56a9',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\AnylistWithAnylistLpAnylistRp_0c30e0aa',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'anylist',
        1 => 'LP',
        2 => 'anylist',
        3 => 'RP',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\AnylistWithAnylistAny_940e683b',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'anylist',
        1 => 'ANY',
      ),
    ),
  ),
  'with' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WithWith_41afa5c9',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WithWithWithWqlist_cbd69b56',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'wqlist',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WithWithWithRecursiveWqlist_4b8fcbef',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'WITH',
        1 => 'RECURSIVE',
        2 => 'wqlist',
      ),
    ),
  ),
  'wqas' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WqasWithAs_12fe9239',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'AS',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WqasWithAsMaterialized_b6e563eb',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'AS',
        1 => 'MATERIALIZED',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WqasWithAsNotMaterialized_4bc5108c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'AS',
        1 => 'NOT',
        2 => 'MATERIALIZED',
      ),
    ),
  ),
  'wqitem' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WqitemWithWithnmEidlistOptWqasLpSelectRp_ba45400b',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 4,
      ),
      'symbols' =>
      array (
        0 => 'withnm',
        1 => 'eidlist_opt',
        2 => 'wqas',
        3 => 'LP',
        4 => 'select',
        5 => 'RP',
      ),
    ),
  ),
  'withnm' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'nm',
      ),
    ),
  ),
  'wqlist' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'wqitem',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WqlistWithWqlistCommaWqitem_3ab99d95',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'wqlist',
        1 => 'COMMA',
        2 => 'wqitem',
      ),
    ),
  ),
  'windowdefn_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'windowdefn',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WindowdefnListWithWindowdefnListCommaWindowdefn_384a44d1',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
      'symbols' =>
      array (
        0 => 'windowdefn_list',
        1 => 'COMMA',
        2 => 'windowdefn',
      ),
    ),
  ),
  'windowdefn' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WindowdefnWithNmAsLpWindowRp_10e246a1',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'nm',
        1 => 'AS',
        2 => 'LP',
        3 => 'window',
        4 => 'RP',
      ),
    ),
  ),
  'window' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WindowWithPartitionByNexprlistOrderbyOptFrameOpt_1f69e129',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'PARTITION',
        1 => 'BY',
        2 => 'nexprlist',
        3 => 'orderby_opt',
        4 => 'frame_opt',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WindowWithNmPartitionByNexprlistOrderbyOptFrameOpt_8a8b5059',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
        2 => 4,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'nm',
        1 => 'PARTITION',
        2 => 'BY',
        3 => 'nexprlist',
        4 => 'orderby_opt',
        5 => 'frame_opt',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WindowWithOrderBySortlistFrameOpt_72d3f775',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
      'symbols' =>
      array (
        0 => 'ORDER',
        1 => 'BY',
        2 => 'sortlist',
        3 => 'frame_opt',
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WindowWithNmOrderBySortlistFrameOpt_f01cd5ad',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
        2 => 4,
      ),
      'symbols' =>
      array (
        0 => 'nm',
        1 => 'ORDER',
        2 => 'BY',
        3 => 'sortlist',
        4 => 'frame_opt',
      ),
    ),
    4 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'frame_opt',
      ),
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WindowWithNmFrameOpt_1ab36cb4',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'nm',
        1 => 'frame_opt',
      ),
    ),
  ),
  'frame_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FrameOptWith_3ee819af',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FrameOptWithRangeOrRowsFrameBoundSFrameExcludeOpt_6d30675d',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
        2 => 2,
      ),
      'symbols' =>
      array (
        0 => 'range_or_rows',
        1 => 'frame_bound_s',
        2 => 'frame_exclude_opt',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FrameOptWithRangeOrRowsBetweenFrameBoundSAndFrameBoundEFrameExcludeOpt_4d98dc77',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
        2 => 4,
        3 => 5,
      ),
      'symbols' =>
      array (
        0 => 'range_or_rows',
        1 => 'BETWEEN',
        2 => 'frame_bound_s',
        3 => 'AND',
        4 => 'frame_bound_e',
        5 => 'frame_exclude_opt',
      ),
    ),
  ),
  'range_or_rows' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\RangeOrRowsWithRangeRowsGroups_a1c269da',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'RANGE|ROWS|GROUPS',
      ),
    ),
  ),
  'frame_bound_s' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'frame_bound',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FrameBoundSWithUnboundedPreceding_3bf546a5',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'UNBOUNDED',
        1 => 'PRECEDING',
      ),
    ),
  ),
  'frame_bound_e' =>
  array (
    0 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'frame_bound',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FrameBoundEWithUnboundedFollowing_f324bf40',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'UNBOUNDED',
        1 => 'FOLLOWING',
      ),
    ),
  ),
  'frame_bound' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FrameBoundWithExprPrecedingFollowing_8e76d151',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'expr',
        1 => 'PRECEDING|FOLLOWING',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FrameBoundWithCurrentRow_9e076822',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'CURRENT',
        1 => 'ROW',
      ),
    ),
  ),
  'frame_exclude_opt' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FrameExcludeOptWith_51524e1c',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FrameExcludeOptWithExcludeFrameExclude_e06b1845',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'EXCLUDE',
        1 => 'frame_exclude',
      ),
    ),
  ),
  'frame_exclude' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FrameExcludeWithNoOthers_cc545c43',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'NO',
        1 => 'OTHERS',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FrameExcludeWithCurrentRow_6eed7df3',
      'fields' =>
      array (
      ),
      'symbols' =>
      array (
        0 => 'CURRENT',
        1 => 'ROW',
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FrameExcludeWithGroupTies_9fb942f2',
      'fields' =>
      array (
        0 => 0,
      ),
      'symbols' =>
      array (
        0 => 'GROUP|TIES',
      ),
    ),
  ),
  'window_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WindowClauseWithWindowWindowdefnList_0b8faffc',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'WINDOW',
        1 => 'windowdefn_list',
      ),
    ),
  ),
  'filter_over' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FilterOverWithFilterClauseOverClause_2bea3cc5',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
      'symbols' =>
      array (
        0 => 'filter_clause',
        1 => 'over_clause',
      ),
    ),
    1 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'over_clause',
      ),
    ),
    2 =>
    array (
      'forward' => 0,
      'symbols' =>
      array (
        0 => 'filter_clause',
      ),
    ),
  ),
  'over_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\OverClauseWithOverLpWindowRp_f91aff11',
      'fields' =>
      array (
        0 => 2,
      ),
      'symbols' =>
      array (
        0 => 'OVER',
        1 => 'LP',
        2 => 'window',
        3 => 'RP',
      ),
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\OverClauseWithOverNm_34b90399',
      'fields' =>
      array (
        0 => 1,
      ),
      'symbols' =>
      array (
        0 => 'OVER',
        1 => 'nm',
      ),
    ),
  ),
  'filter_clause' =>
  array (
    0 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FilterClauseWithFilterLpWhereExprRp_8633e3ce',
      'fields' =>
      array (
        0 => 3,
      ),
      'symbols' =>
      array (
        0 => 'FILTER',
        1 => 'LP',
        2 => 'WHERE',
        3 => 'expr',
        4 => 'RP',
      ),
    ),
  ),
), array (
  'BEFORE|AFTER' =>
  array (
    0 => 'BEFORE',
    1 => 'AFTER',
  ),
  'BITAND|BITOR|LSHIFT|RSHIFT' =>
  array (
    0 => 'BITAND',
    1 => 'BITOR',
    2 => 'LSHIFT',
    3 => 'RSHIFT',
  ),
  'COMMA|JOIN' =>
  array (
    0 => 'COMMA',
    1 => 'JOIN',
  ),
  'COMMIT|END' =>
  array (
    0 => 'COMMIT',
    1 => 'END',
  ),
  'DELETE|INSERT' =>
  array (
    0 => 'DELETE',
    1 => 'INSERT',
  ),
  'EQ|NE' =>
  array (
    0 => 'EQ',
    1 => 'NE',
  ),
  'EXCEPT|INTERSECT' =>
  array (
    0 => 'EXCEPT',
    1 => 'INTERSECT',
  ),
  'GROUP|TIES' =>
  array (
    0 => 'GROUP',
    1 => 'TIES',
  ),
  'ISNULL|NOTNULL' =>
  array (
    0 => 'ISNULL',
    1 => 'NOTNULL',
  ),
  'LIKE_KW|MATCH' =>
  array (
    0 => 'LIKE_KW',
    1 => 'MATCH',
  ),
  'LT|GT|GE|LE' =>
  array (
    0 => 'LT',
    1 => 'GT',
    2 => 'GE',
    3 => 'LE',
  ),
  'NULL|FLOAT|BLOB' =>
  array (
    0 => 'NULL',
    1 => 'FLOAT',
    2 => 'BLOB',
  ),
  'PLUS|MINUS' =>
  array (
    0 => 'PLUS',
    1 => 'MINUS',
  ),
  'PRECEDING|FOLLOWING' =>
  array (
    0 => 'PRECEDING',
    1 => 'FOLLOWING',
  ),
  'RANGE|ROWS|GROUPS' =>
  array (
    0 => 'RANGE',
    1 => 'ROWS',
    2 => 'GROUPS',
  ),
  'STAR|SLASH|REM' =>
  array (
    0 => 'STAR',
    1 => 'SLASH',
    2 => 'REM',
  ),
  'id' =>
  array (
    0 => 'ID',
    1 => 'INDEXED',
  ),
  'idj' =>
  array (
    0 => 'ID',
    1 => 'INDEXED',
    2 => 'JOIN_KW',
  ),
  'ids' =>
  array (
    0 => 'ID',
    1 => 'STRING',
  ),
  'number' =>
  array (
    0 => 'INTEGER',
    1 => 'FLOAT',
  ),
), array (
  'ABORT' => 'ID',
  'ACTION' => 'ID',
  'AFTER' => 'ID',
  'ALWAYS' => 'ID',
  'ANALYZE' => 'ID',
  'ASC' => 'ID',
  'ATTACH' => 'ID',
  'BEFORE' => 'ID',
  'BEGIN' => 'ID',
  'BY' => 'ID',
  'CASCADE' => 'ID',
  'CAST' => 'ID',
  'COLUMNKW' => 'ID',
  'CONFLICT' => 'ID',
  'CTIME_KW' => 'ID',
  'CURRENT' => 'ID',
  'DATABASE' => 'ID',
  'DEFERRED' => 'ID',
  'DESC' => 'ID',
  'DETACH' => 'ID',
  'DO' => 'ID',
  'EACH' => 'ID',
  'END' => 'ID',
  'EXCLUDE' => 'ID',
  'EXCLUSIVE' => 'ID',
  'EXPLAIN' => 'ID',
  'FAIL' => 'ID',
  'FIRST' => 'ID',
  'FOLLOWING' => 'ID',
  'FOR' => 'ID',
  'GENERATED' => 'ID',
  'GROUPS' => 'ID',
  'IF' => 'ID',
  'IGNORE' => 'ID',
  'IMMEDIATE' => 'ID',
  'INITIALLY' => 'ID',
  'INSTEAD' => 'ID',
  'KEY' => 'ID',
  'LAST' => 'ID',
  'LIKE_KW' => 'ID',
  'MATCH' => 'ID',
  'MATERIALIZED' => 'ID',
  'NO' => 'ID',
  'NULLS' => 'ID',
  'OF' => 'ID',
  'OFFSET' => 'ID',
  'OTHERS' => 'ID',
  'PARTITION' => 'ID',
  'PLAN' => 'ID',
  'PRAGMA' => 'ID',
  'PRECEDING' => 'ID',
  'QUERY' => 'ID',
  'RAISE' => 'ID',
  'RANGE' => 'ID',
  'RECURSIVE' => 'ID',
  'REINDEX' => 'ID',
  'RELEASE' => 'ID',
  'RENAME' => 'ID',
  'REPLACE' => 'ID',
  'RESTRICT' => 'ID',
  'ROLLBACK' => 'ID',
  'ROW' => 'ID',
  'ROWS' => 'ID',
  'SAVEPOINT' => 'ID',
  'TEMP' => 'ID',
  'TIES' => 'ID',
  'TRIGGER' => 'ID',
  'UNBOUNDED' => 'ID',
  'VACUUM' => 'ID',
  'VIEW' => 'ID',
  'VIRTUAL' => 'ID',
  'WITH' => 'ID',
  'WITHOUT' => 'ID',
));
