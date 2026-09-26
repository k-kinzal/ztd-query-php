<?php

declare(strict_types=1);

/** Generated construction recipes; never retained by a Statement. */
return new \SqlSemantics\Core\Analysis\ValueReader(array (
  'input' =>
  array (
    0 =>
    array (
      'forward' => 0,
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
    ),
    1 =>
    array (
      'forward' => 0,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\EcmdWithCmdxSemi_b7577a8f',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
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
    ),
  ),
  'explain' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\ExplainChoice_417275df::UseExplain_a42f134d',
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\ExplainChoice_417275df::UseExplainQueryPlan_6195a6a3',
    ),
  ),
  'cmdx' =>
  array (
    0 =>
    array (
      'forward' => 0,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithCommitEndTransOpt_ccca6149',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithRollbackTransOpt_00f0c935',
      'fields' =>
      array (
        0 => 1,
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithSavepointNm_bf1c3338',
      'fields' =>
      array (
        0 => 1,
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
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithCreateTableCreateTableArgs_51837b7b',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
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
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithDropViewIfexistsFullname_a7db3715',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
    ),
    10 =>
    array (
      'forward' => 0,
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
    ),
    16 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithDropIndexIfexistsFullname_44a2d8ce',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
    ),
    17 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithVacuumVinto_5a4cdae3',
      'fields' =>
      array (
        0 => 1,
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
    ),
    19 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithPragmaNmDbnm_5bebe510',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
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
    ),
    25 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithDropTriggerIfexistsFullname_582f5f4c',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
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
    ),
    27 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithDetachDatabaseKwOptExpr_f7c66c8d',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
    ),
    28 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithReindex_e9f85aaa',
      'fields' =>
      array (
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
    ),
    30 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithAnalyze_4726546f',
      'fields' =>
      array (
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
    ),
    32 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithAlterTableFullnameRenameToNm_f687ccf9',
      'fields' =>
      array (
        0 => 2,
        1 => 5,
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
    ),
    36 =>
    array (
      'forward' => 0,
    ),
    37 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CmdWithCreateVtabLpVtabarglistRp_f47fc34c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TransOptWithTransaction_ea573324',
      'fields' =>
      array (
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TransOptWithTransactionNm_f694d58b',
      'fields' =>
      array (
        0 => 1,
      ),
    ),
  ),
  'transtype' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\TranstypeChoice_9594d9a4::Use_e3b0c442',
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\TranstypeChoice_9594d9a4::UseDeferred_3e43ac1f',
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\TranstypeChoice_9594d9a4::UseImmediate_def178df',
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\TranstypeChoice_9594d9a4::UseExclusive_a6632be9',
    ),
  ),
  'savepoint_opt' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\SavepointOptChoice_74ffdd90::UseSavepoint_7e4dde5b',
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\SavepointOptChoice_74ffdd90::Use_e3b0c442',
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
    ),
  ),
  'createkw' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\CreatekwChoice_bb5df1f3::UseCreate_fde9c501',
    ),
  ),
  'ifnotexists' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\IfnotexistsChoice_f381f4e9::Use_e3b0c442',
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\IfnotexistsChoice_f381f4e9::UseIfNotExists_addd9a5c',
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TempWith_582c64db',
      'fields' =>
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CreateTableArgsWithAsSelect_edfabb86',
      'fields' =>
      array (
        0 => 1,
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
    ),
    1 =>
    array (
      'forward' => 0,
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TableOptionSetWithTableOptionSetCommaTableOption_ffea1324',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
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
    ),
    1 =>
    array (
      'forward' => 0,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ColumnlistWithColumnnameCarglist_a318faf8',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\NmWithString_4b1d9359',
      'fields' =>
      array (
        0 => 0,
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
    ),
    1 =>
    array (
      'forward' => 0,
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TypetokenWithTypenameLpSignedRp_53bc47cf',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TypenameWithTypenameIds_bae45eaf',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
    ),
  ),
  'signed' =>
  array (
    0 =>
    array (
      'forward' => 0,
    ),
    1 =>
    array (
      'forward' => 0,
    ),
  ),
  'scanpt' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\ScanptChoice_055539df::Use_e3b0c442',
    ),
  ),
  'scantok' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\ScantokChoice_055539df::Use_e3b0c442',
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CarglistWith_b1a2e344',
      'fields' =>
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithDefaultScantokTerm_16444cab',
      'fields' =>
      array (
        0 => 1,
        1 => 2,
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithDefaultLpExprRp_c9989710',
      'fields' =>
      array (
        0 => 2,
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
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithDefaultMinusScantokTerm_aee20286',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
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
    ),
    6 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithNullOnconf_fb3a3126',
      'fields' =>
      array (
        0 => 1,
      ),
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithNotNullOnconf_11cbf2a3',
      'fields' =>
      array (
        0 => 2,
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
    ),
    9 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithUniqueOnconf_426d13d8',
      'fields' =>
      array (
        0 => 1,
      ),
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithCheckLpExprRp_a257354c',
      'fields' =>
      array (
        0 => 2,
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
    ),
    12 =>
    array (
      'forward' => 0,
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithCollateIds_f026273f',
      'fields' =>
      array (
        0 => 1,
      ),
    ),
    14 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithGeneratedAlwaysAsGenerated_49de3479',
      'fields' =>
      array (
        0 => 3,
      ),
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CconsWithAsGenerated_a8c85419',
      'fields' =>
      array (
        0 => 1,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\GeneratedWithLpExprRpId_5d74118d',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
      ),
    ),
  ),
  'autoinc' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\AutoincChoice_d1be63bd::Use_e3b0c442',
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\AutoincChoice_d1be63bd::UseAutoincrement_5e629590',
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\RefargsWithRefargsRefarg_1dce60fb',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\RefargWithOnInsertRefact_dedcfed8',
      'fields' =>
      array (
        0 => 2,
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\RefargWithOnDeleteRefact_1b1d35b4',
      'fields' =>
      array (
        0 => 2,
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\RefargWithOnUpdateRefact_739fd957',
      'fields' =>
      array (
        0 => 2,
      ),
    ),
  ),
  'refact' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\RefactChoice_2802cc9d::UseSetNull_a5f7c4e6',
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\RefactChoice_2802cc9d::UseSetDefault_639a6c2d',
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\RefactChoice_2802cc9d::UseCascade_86844e57',
    ),
    3 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\RefactChoice_2802cc9d::UseRestrict_bd7a04e6',
    ),
    4 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\RefactChoice_2802cc9d::UseNoAction_25595c7c',
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\DeferSubclauseWithDeferrableInitDeferredPredOpt_1dbafeef',
      'fields' =>
      array (
        0 => 1,
      ),
    ),
  ),
  'init_deferred_pred_opt' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\InitDeferredPredOptChoice_ed380d4f::Use_e3b0c442',
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\InitDeferredPredOptChoice_ed380d4f::UseInitiallyDeferred_6e9191be',
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\InitDeferredPredOptChoice_ed380d4f::UseInitiallyImmediate_0e67cf0a',
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ConslistOptWithCommaConslist_04f617dc',
      'fields' =>
      array (
        0 => 1,
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
    ),
    1 =>
    array (
      'forward' => 0,
    ),
  ),
  'tconscomma' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\TconscommaChoice_d7313e6d::Use_d03502c4',
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\TconscommaChoice_d7313e6d::Use_e3b0c442',
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
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TconsWithUniqueLpSortlistRpOnconf_1bb4e282',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
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
    ),
    1 =>
    array (
      'forward' => 0,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\OnconfWithOnConflictResolvetype_4edd828c',
      'fields' =>
      array (
        0 => 2,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\OrconfWithOrResolvetype_f0ef4a84',
      'fields' =>
      array (
        0 => 1,
      ),
    ),
  ),
  'resolvetype' =>
  array (
    0 =>
    array (
      'forward' => 0,
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ResolvetypeWithIgnore_7fdbfe29',
      'fields' =>
      array (
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ResolvetypeWithReplace_ee120ee4',
      'fields' =>
      array (
      ),
    ),
  ),
  'ifexists' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\IfexistsChoice_1b8cb991::UseIfExists_82cdccc7',
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\IfexistsChoice_1b8cb991::Use_e3b0c442',
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SelectWithWithRecursiveWqlistSelectnowith_5f4629ff',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
      ),
    ),
    2 =>
    array (
      'forward' => 0,
    ),
  ),
  'selectnowith' =>
  array (
    0 =>
    array (
      'forward' => 0,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\MultiselectOpWithUnionAll_1d6b9617',
      'fields' =>
      array (
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\MultiselectOpWithExceptIntersect_f86e511a',
      'fields' =>
      array (
        0 => 0,
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
    ),
    2 =>
    array (
      'forward' => 0,
    ),
    3 =>
    array (
      'forward' => 0,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\MvaluesWithMvaluesCommaLpNexprlistRp_fe975bc4',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
      ),
    ),
  ),
  'distinct' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\DistinctChoice_deb627ba::UseDistinct_d879f806',
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\DistinctChoice_deb627ba::UseAll_b5c7aed7',
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\DistinctChoice_deb627ba::Use_e3b0c442',
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SclpWith_2c42dcc1',
      'fields' =>
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\SelcollistWithSclpScanptStar_bae6710f',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\AsWithIds_1689a84e',
      'fields' =>
      array (
        0 => 0,
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\AsWith_9dc353c5',
      'fields' =>
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FromWithFromSeltablist_6e414a5b',
      'fields' =>
      array (
        0 => 1,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\StlPrefixWith_4d42bc16',
      'fields' =>
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\DbnmWithDotNm_710678c7',
      'fields' =>
      array (
        0 => 1,
      ),
    ),
  ),
  'fullname' =>
  array (
    0 =>
    array (
      'forward' => 0,
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FullnameWithNmDotNm_4e1d9c77',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
    ),
  ),
  'xfullname' =>
  array (
    0 =>
    array (
      'forward' => 0,
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\XfullnameWithNmDotNm_abe868cc',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
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
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\XfullnameWithNmAsNm_0204e3b8',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\JoinopWithJoinKwJoin_4110763c',
      'fields' =>
      array (
        0 => 0,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\OnUsingWithUsingLpIdlistRp_691e2367',
      'fields' =>
      array (
        0 => 2,
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\OnUsingWith_e9e4278d',
      'fields' =>
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
    ),
    1 =>
    array (
      'forward' => 0,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\IndexedByWithNotIndexed_d668bbbe',
      'fields' =>
      array (
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\OrderbyOptWithOrderBySortlist_5aa58f40',
      'fields' =>
      array (
        0 => 2,
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
    ),
  ),
  'sortorder' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\SortorderChoice_01affc0e::UseAsc_323b087e',
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\SortorderChoice_01affc0e::UseDesc_984da4fe',
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\SortorderChoice_01affc0e::Use_e3b0c442',
    ),
  ),
  'nulls' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\NullsChoice_6b2c7d75::UseNullsFirst_898b9843',
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\NullsChoice_6b2c7d75::UseNullsLast_7faeba30',
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\NullsChoice_6b2c7d75::Use_e3b0c442',
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\GroupbyOptWithGroupByNexprlist_4a73c2cf',
      'fields' =>
      array (
        0 => 2,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\HavingOptWithHavingExpr_f311112e',
      'fields' =>
      array (
        0 => 1,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\LimitOptWithLimitExpr_b827c6a0',
      'fields' =>
      array (
        0 => 1,
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
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\LimitOptWithLimitExprCommaExpr_1d4d2695',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WhereOptWithWhereExpr_93445e09',
      'fields' =>
      array (
        0 => 1,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WhereOptRetWithWhereExpr_eede6fba',
      'fields' =>
      array (
        0 => 1,
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WhereOptRetWithReturningSelcollist_f5e1123e',
      'fields' =>
      array (
        0 => 1,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\UpsertWithReturningSelcollist_c53e1ecb',
      'fields' =>
      array (
        0 => 1,
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
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\UpsertWithOnConflictDoNothingReturning_162a31bb',
      'fields' =>
      array (
        0 => 4,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ReturningWith_46f90fc5',
      'fields' =>
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\InsertCmdWithReplace_7e70f0cf',
      'fields' =>
      array (
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\IdlistOptWithLpIdlistRp_62ca8e2d',
      'fields' =>
      array (
        0 => 1,
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
    ),
    1 =>
    array (
      'forward' => 0,
    ),
  ),
  'expr' =>
  array (
    0 =>
    array (
      'forward' => 0,
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithLpExprRp_ad646753',
      'fields' =>
      array (
        0 => 1,
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithIdj_e1794d68',
      'fields' =>
      array (
        0 => 0,
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
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithVariable_0aa27fc0',
      'fields' =>
      array (
        0 => 0,
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
    ),
    7 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithCastLpExprAsTypetokenRp_15f33207',
      'fields' =>
      array (
        0 => 2,
        1 => 4,
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
    ),
    10 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithIdjLpStarRp_41781688',
      'fields' =>
      array (
        0 => 0,
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
    ),
    13 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithIdjLpStarRpFilterOver_27a4a7eb',
      'fields' =>
      array (
        0 => 0,
        1 => 4,
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
    ),
    15 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprAndExpr_33592aae',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
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
    ),
    22 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprConcatExpr_2239f2bc',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
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
    ),
    25 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprIsnullNotnull_b1183766',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
    ),
    26 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprNotNull_025d71af',
      'fields' =>
      array (
        0 => 0,
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
    ),
    28 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprIsNotExpr_a631d709',
      'fields' =>
      array (
        0 => 0,
        1 => 3,
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
    ),
    30 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExprIsDistinctFromExpr_2d05c076',
      'fields' =>
      array (
        0 => 0,
        1 => 4,
      ),
    ),
    31 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithNotExpr_22095ba5',
      'fields' =>
      array (
        0 => 1,
      ),
    ),
    32 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithBitnotExpr_fb4e23b4',
      'fields' =>
      array (
        0 => 1,
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
    ),
    37 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithLpSelectRp_2c2be90a',
      'fields' =>
      array (
        0 => 1,
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
    ),
    40 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithExistsLpSelectRp_c7d40730',
      'fields' =>
      array (
        0 => 2,
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
    ),
    42 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprWithRaiseLpIgnoreRp_ccfbbaf2',
      'fields' =>
      array (
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TermWithString_e66cd9ed',
      'fields' =>
      array (
        0 => 0,
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TermWithInteger_298801b2',
      'fields' =>
      array (
        0 => 0,
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TermWithCtimeKw_9aa2d016',
      'fields' =>
      array (
        0 => 0,
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TermWithQnumber_45fd67fa',
      'fields' =>
      array (
        0 => 0,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\LikeopWithNotLikeKwMatch_e63ce34e',
      'fields' =>
      array (
        0 => 1,
      ),
    ),
  ),
  'between_op' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\BetweenOpChoice_06f4f5f4::UseBetween_c8d5a3e5',
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\BetweenOpChoice_06f4f5f4::UseNotBetween_d916108b',
    ),
  ),
  'in_op' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\InOpChoice_0915428e::UseIn_fed1d872',
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\InOpChoice_0915428e::UseNotIn_d63494f8',
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CaseExprlistWithWhenExprThenExpr_fc6d9fa6',
      'fields' =>
      array (
        0 => 1,
        1 => 3,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CaseElseWith_4209e8da',
      'fields' =>
      array (
      ),
    ),
  ),
  'case_operand' =>
  array (
    0 =>
    array (
      'forward' => 0,
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CaseOperandWith_b8923944',
      'fields' =>
      array (
      ),
    ),
  ),
  'exprlist' =>
  array (
    0 =>
    array (
      'forward' => 0,
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ExprlistWith_b354471e',
      'fields' =>
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
    ),
    1 =>
    array (
      'forward' => 0,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\ParenExprlistWithLpExprlistRp_6d8812f5',
      'fields' =>
      array (
        0 => 1,
      ),
    ),
  ),
  'uniqueflag' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\UniqueflagChoice_996fc274::UseUnique_64636a12',
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\UniqueflagChoice_996fc274::Use_e3b0c442',
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\EidlistOptWithLpEidlistRp_7b6e32ba',
      'fields' =>
      array (
        0 => 1,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\CollateWithCollateIds_8425b902',
      'fields' =>
      array (
        0 => 1,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\VintoWith_b8f3b382',
      'fields' =>
      array (
      ),
    ),
  ),
  'nmnum' =>
  array (
    0 =>
    array (
      'forward' => 0,
    ),
    1 =>
    array (
      'forward' => 0,
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\NmnumWithOn_2e23b74d',
      'fields' =>
      array (
      ),
    ),
    3 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\NmnumWithDelete_0e682fa5',
      'fields' =>
      array (
      ),
    ),
    4 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\NmnumWithDefault_eda4f18c',
      'fields' =>
      array (
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\PlusNumWithNumber_52f2d00d',
      'fields' =>
      array (
        0 => 0,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TriggerTimeWithInsteadOf_85c866b5',
      'fields' =>
      array (
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TriggerTimeWith_053237f2',
      'fields' =>
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TriggerEventWithUpdate_6cc7e649',
      'fields' =>
      array (
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TriggerEventWithUpdateOfIdlist_1483bd2b',
      'fields' =>
      array (
        0 => 2,
      ),
    ),
  ),
  'foreach_clause' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\ForeachClauseChoice_a60da7c2::Use_e3b0c442',
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\ForeachClauseChoice_a60da7c2::UseForEachRow_eac7d28e',
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WhenClauseWithWhenExpr_6456c232',
      'fields' =>
      array (
        0 => 1,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TriggerCmdListWithTriggerCmdSemi_34d876f1',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
    ),
  ),
  'trnm' =>
  array (
    0 =>
    array (
      'forward' => 0,
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TrnmWithNmDotNm_3ea19d9c',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TridxbyWithIndexedByNm_c201f940',
      'fields' =>
      array (
        0 => 2,
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\TridxbyWithNotIndexed_6c190bec',
      'fields' =>
      array (
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
    ),
  ),
  'raisetype' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\RaisetypeChoice_0819de2d::UseRollback_587fa628',
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\RaisetypeChoice_0819de2d::UseAbort_315a1f25',
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\RaisetypeChoice_0819de2d::UseFail_425305e2',
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\KeyOptWithKeyExpr_a14c915a',
      'fields' =>
      array (
        0 => 1,
      ),
    ),
  ),
  'database_kw_opt' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\DatabaseKwOptChoice_a5a26b1d::UseDatabase_8e663907',
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\DatabaseKwOptChoice_a5a26b1d::Use_e3b0c442',
    ),
  ),
  'add_column_fullname' =>
  array (
    0 =>
    array (
      'forward' => 0,
    ),
  ),
  'kwcolumn_opt' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\KwcolumnOptChoice_eefdbfac::Use_e3b0c442',
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\KwcolumnOptChoice_eefdbfac::UseColumn_83a8e21d',
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
    ),
  ),
  'vtabarglist' =>
  array (
    0 =>
    array (
      'forward' => 0,
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\VtabarglistWithVtabarglistCommaVtabarg_297de28e',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\VtabargWithVtabargVtabargtoken_627d8ccd',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\VtabargtokenWithLpAnylistRp_95c44fdc',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
      ),
    ),
  ),
  'lp' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\LpChoice_0ce700cc::Use_32ebb1ab',
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\AnylistWithAnylistLpAnylistRp_0c30e0aa',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WithWithWithWqlist_cbd69b56',
      'fields' =>
      array (
        0 => 1,
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WithWithWithRecursiveWqlist_4b8fcbef',
      'fields' =>
      array (
        0 => 2,
      ),
    ),
  ),
  'wqas' =>
  array (
    0 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\WqasChoice_5d67ccbe::UseAs_de148153',
    ),
    1 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\WqasChoice_5d67ccbe::UseAsMaterialized_64837926',
    ),
    2 =>
    array (
      'constant' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Choice\\WqasChoice_5d67ccbe::UseAsNotMaterialized_6050d450',
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
    ),
  ),
  'withnm' =>
  array (
    0 =>
    array (
      'forward' => 0,
    ),
  ),
  'wqlist' =>
  array (
    0 =>
    array (
      'forward' => 0,
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WqlistWithWqlistCommaWqitem_3ab99d95',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
      ),
    ),
  ),
  'windowdefn_list' =>
  array (
    0 =>
    array (
      'forward' => 0,
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WindowdefnListWithWindowdefnListCommaWindowdefn_384a44d1',
      'fields' =>
      array (
        0 => 0,
        1 => 2,
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
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WindowWithOrderBySortlistFrameOpt_72d3f775',
      'fields' =>
      array (
        0 => 2,
        1 => 3,
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
    ),
    4 =>
    array (
      'forward' => 0,
    ),
    5 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\WindowWithNmFrameOpt_1ab36cb4',
      'fields' =>
      array (
        0 => 0,
        1 => 1,
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
    ),
  ),
  'frame_bound_s' =>
  array (
    0 =>
    array (
      'forward' => 0,
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FrameBoundSWithUnboundedPreceding_3bf546a5',
      'fields' =>
      array (
      ),
    ),
  ),
  'frame_bound_e' =>
  array (
    0 =>
    array (
      'forward' => 0,
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FrameBoundEWithUnboundedFollowing_f324bf40',
      'fields' =>
      array (
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FrameBoundWithCurrentRow_9e076822',
      'fields' =>
      array (
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FrameExcludeOptWithExcludeFrameExclude_e06b1845',
      'fields' =>
      array (
        0 => 1,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FrameExcludeWithCurrentRow_6eed7df3',
      'fields' =>
      array (
      ),
    ),
    2 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\FrameExcludeWithGroupTies_9fb942f2',
      'fields' =>
      array (
        0 => 0,
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
    ),
    1 =>
    array (
      'forward' => 0,
    ),
    2 =>
    array (
      'forward' => 0,
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
    ),
    1 =>
    array (
      'class' => 'SqlSemantics\\Statement\\Model\\Sqlite\\Value\\OverClauseWithOverNm_34b90399',
      'fields' =>
      array (
        0 => 1,
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
    ),
  ),
));
