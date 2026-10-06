<?php

declare(strict_types=1);

/**
 * The productions of the sqlite-3.47.2 grammar by rule and alternative; generated, do not edit.
 */
return [
    'input' => [
        'input: cmdlist',
    ],
    'cmdlist' => [
        'cmdlist: cmdlist ecmd',
        'cmdlist: ecmd',
    ],
    'ecmd' => [
        'ecmd: SEMI',
        'ecmd: cmdx SEMI',
        'ecmd: explain cmdx SEMI',
    ],
    'explain' => [
        'explain: EXPLAIN',
        'explain: EXPLAIN QUERY PLAN',
    ],
    'cmdx' => [
        'cmdx: cmd',
    ],
    'cmd' => [
        'cmd: BEGIN transtype trans_opt',
        'cmd: COMMIT|END trans_opt',
        'cmd: ROLLBACK trans_opt',
        'cmd: SAVEPOINT nm',
        'cmd: RELEASE savepoint_opt nm',
        'cmd: ROLLBACK trans_opt TO savepoint_opt nm',
        'cmd: create_table create_table_args',
        'cmd: DROP TABLE ifexists fullname',
        'cmd: createkw temp VIEW ifnotexists nm dbnm eidlist_opt AS select',
        'cmd: DROP VIEW ifexists fullname',
        'cmd: select',
        'cmd: with DELETE FROM xfullname indexed_opt where_opt_ret',
        'cmd: with UPDATE orconf xfullname indexed_opt SET setlist from where_opt_ret',
        'cmd: with insert_cmd INTO xfullname idlist_opt select upsert',
        'cmd: with insert_cmd INTO xfullname idlist_opt DEFAULT VALUES returning',
        'cmd: createkw uniqueflag INDEX ifnotexists nm dbnm ON nm LP sortlist RP where_opt',
        'cmd: DROP INDEX ifexists fullname',
        'cmd: VACUUM vinto',
        'cmd: VACUUM nm vinto',
        'cmd: PRAGMA nm dbnm',
        'cmd: PRAGMA nm dbnm EQ nmnum',
        'cmd: PRAGMA nm dbnm LP nmnum RP',
        'cmd: PRAGMA nm dbnm EQ minus_num',
        'cmd: PRAGMA nm dbnm LP minus_num RP',
        'cmd: createkw trigger_decl BEGIN trigger_cmd_list END',
        'cmd: DROP TRIGGER ifexists fullname',
        'cmd: ATTACH database_kw_opt expr AS expr key_opt',
        'cmd: DETACH database_kw_opt expr',
        'cmd: REINDEX',
        'cmd: REINDEX nm dbnm',
        'cmd: ANALYZE',
        'cmd: ANALYZE nm dbnm',
        'cmd: ALTER TABLE fullname RENAME TO nm',
        'cmd: ALTER TABLE add_column_fullname ADD kwcolumn_opt columnname carglist',
        'cmd: ALTER TABLE fullname DROP kwcolumn_opt nm',
        'cmd: ALTER TABLE fullname RENAME kwcolumn_opt nm TO nm',
        'cmd: create_vtab',
        'cmd: create_vtab LP vtabarglist RP',
    ],
    'trans_opt' => [
        'trans_opt:',
        'trans_opt: TRANSACTION',
        'trans_opt: TRANSACTION nm',
    ],
    'transtype' => [
        'transtype:',
        'transtype: DEFERRED',
        'transtype: IMMEDIATE',
        'transtype: EXCLUSIVE',
    ],
    'savepoint_opt' => [
        'savepoint_opt: SAVEPOINT',
        'savepoint_opt:',
    ],
    'create_table' => [
        'create_table: createkw temp TABLE ifnotexists nm dbnm',
    ],
    'createkw' => [
        'createkw: CREATE',
    ],
    'ifnotexists' => [
        'ifnotexists:',
        'ifnotexists: IF NOT EXISTS',
    ],
    'temp' => [
        'temp: TEMP',
        'temp:',
    ],
    'create_table_args' => [
        'create_table_args: LP columnlist conslist_opt RP table_option_set',
        'create_table_args: AS select',
    ],
    'table_option_set' => [
        'table_option_set:',
        'table_option_set: table_option',
        'table_option_set: table_option_set COMMA table_option',
    ],
    'table_option' => [
        'table_option: WITHOUT nm',
        'table_option: nm',
    ],
    'columnlist' => [
        'columnlist: columnlist COMMA columnname carglist',
        'columnlist: columnname carglist',
    ],
    'columnname' => [
        'columnname: nm typetoken',
    ],
    'nm' => [
        'nm: idj',
        'nm: STRING',
    ],
    'typetoken' => [
        'typetoken:',
        'typetoken: typename',
        'typetoken: typename LP signed RP',
        'typetoken: typename LP signed COMMA signed RP',
    ],
    'typename' => [
        'typename: ids',
        'typename: typename ids',
    ],
    'signed' => [
        'signed: plus_num',
        'signed: minus_num',
    ],
    'scanpt' => [
        'scanpt:',
    ],
    'scantok' => [
        'scantok:',
    ],
    'carglist' => [
        'carglist: carglist ccons',
        'carglist:',
    ],
    'ccons' => [
        'ccons: CONSTRAINT nm',
        'ccons: DEFAULT scantok term',
        'ccons: DEFAULT LP expr RP',
        'ccons: DEFAULT PLUS scantok term',
        'ccons: DEFAULT MINUS scantok term',
        'ccons: DEFAULT scantok id',
        'ccons: NULL onconf',
        'ccons: NOT NULL onconf',
        'ccons: PRIMARY KEY sortorder onconf autoinc',
        'ccons: UNIQUE onconf',
        'ccons: CHECK LP expr RP',
        'ccons: REFERENCES nm eidlist_opt refargs',
        'ccons: defer_subclause',
        'ccons: COLLATE ids',
        'ccons: GENERATED ALWAYS AS generated',
        'ccons: AS generated',
    ],
    'generated' => [
        'generated: LP expr RP',
        'generated: LP expr RP ID',
    ],
    'autoinc' => [
        'autoinc:',
        'autoinc: AUTOINCR',
    ],
    'refargs' => [
        'refargs:',
        'refargs: refargs refarg',
    ],
    'refarg' => [
        'refarg: MATCH nm',
        'refarg: ON INSERT refact',
        'refarg: ON DELETE refact',
        'refarg: ON UPDATE refact',
    ],
    'refact' => [
        'refact: SET NULL',
        'refact: SET DEFAULT',
        'refact: CASCADE',
        'refact: RESTRICT',
        'refact: NO ACTION',
    ],
    'defer_subclause' => [
        'defer_subclause: NOT DEFERRABLE init_deferred_pred_opt',
        'defer_subclause: DEFERRABLE init_deferred_pred_opt',
    ],
    'init_deferred_pred_opt' => [
        'init_deferred_pred_opt:',
        'init_deferred_pred_opt: INITIALLY DEFERRED',
        'init_deferred_pred_opt: INITIALLY IMMEDIATE',
    ],
    'conslist_opt' => [
        'conslist_opt:',
        'conslist_opt: COMMA conslist',
    ],
    'conslist' => [
        'conslist: conslist tconscomma tcons',
        'conslist: tcons',
    ],
    'tconscomma' => [
        'tconscomma: COMMA',
        'tconscomma:',
    ],
    'tcons' => [
        'tcons: CONSTRAINT nm',
        'tcons: PRIMARY KEY LP sortlist autoinc RP onconf',
        'tcons: UNIQUE LP sortlist RP onconf',
        'tcons: CHECK LP expr RP onconf',
        'tcons: FOREIGN KEY LP eidlist RP REFERENCES nm eidlist_opt refargs defer_subclause_opt',
    ],
    'defer_subclause_opt' => [
        'defer_subclause_opt:',
        'defer_subclause_opt: defer_subclause',
    ],
    'onconf' => [
        'onconf:',
        'onconf: ON CONFLICT resolvetype',
    ],
    'orconf' => [
        'orconf:',
        'orconf: OR resolvetype',
    ],
    'resolvetype' => [
        'resolvetype: raisetype',
        'resolvetype: IGNORE',
        'resolvetype: REPLACE',
    ],
    'ifexists' => [
        'ifexists: IF EXISTS',
        'ifexists:',
    ],
    'select' => [
        'select: WITH wqlist selectnowith',
        'select: WITH RECURSIVE wqlist selectnowith',
        'select: selectnowith',
    ],
    'selectnowith' => [
        'selectnowith: oneselect',
        'selectnowith: selectnowith multiselect_op oneselect',
    ],
    'multiselect_op' => [
        'multiselect_op: UNION',
        'multiselect_op: UNION ALL',
        'multiselect_op: EXCEPT|INTERSECT',
    ],
    'oneselect' => [
        'oneselect: SELECT distinct selcollist from where_opt groupby_opt having_opt orderby_opt limit_opt',
        'oneselect: SELECT distinct selcollist from where_opt groupby_opt having_opt window_clause orderby_opt limit_opt',
        'oneselect: values',
        'oneselect: mvalues',
    ],
    'values' => [
        'values: VALUES LP nexprlist RP',
    ],
    'mvalues' => [
        'mvalues: values COMMA LP nexprlist RP',
        'mvalues: mvalues COMMA LP nexprlist RP',
    ],
    'distinct' => [
        'distinct: DISTINCT',
        'distinct: ALL',
        'distinct:',
    ],
    'sclp' => [
        'sclp: selcollist COMMA',
        'sclp:',
    ],
    'selcollist' => [
        'selcollist: sclp scanpt expr scanpt as',
        'selcollist: sclp scanpt STAR',
        'selcollist: sclp scanpt nm DOT STAR',
    ],
    'as' => [
        'as: AS nm',
        'as: ids',
        'as:',
    ],
    'from' => [
        'from:',
        'from: FROM seltablist',
    ],
    'stl_prefix' => [
        'stl_prefix: seltablist joinop',
        'stl_prefix:',
    ],
    'seltablist' => [
        'seltablist: stl_prefix nm dbnm as on_using',
        'seltablist: stl_prefix nm dbnm as indexed_by on_using',
        'seltablist: stl_prefix nm dbnm LP exprlist RP as on_using',
        'seltablist: stl_prefix LP select RP as on_using',
        'seltablist: stl_prefix LP seltablist RP as on_using',
    ],
    'dbnm' => [
        'dbnm:',
        'dbnm: DOT nm',
    ],
    'fullname' => [
        'fullname: nm',
        'fullname: nm DOT nm',
    ],
    'xfullname' => [
        'xfullname: nm',
        'xfullname: nm DOT nm',
        'xfullname: nm DOT nm AS nm',
        'xfullname: nm AS nm',
    ],
    'joinop' => [
        'joinop: COMMA|JOIN',
        'joinop: JOIN_KW JOIN',
        'joinop: JOIN_KW nm JOIN',
        'joinop: JOIN_KW nm nm JOIN',
    ],
    'on_using' => [
        'on_using: ON expr',
        'on_using: USING LP idlist RP',
        'on_using:',
    ],
    'indexed_opt' => [
        'indexed_opt:',
        'indexed_opt: indexed_by',
    ],
    'indexed_by' => [
        'indexed_by: INDEXED BY nm',
        'indexed_by: NOT INDEXED',
    ],
    'orderby_opt' => [
        'orderby_opt:',
        'orderby_opt: ORDER BY sortlist',
    ],
    'sortlist' => [
        'sortlist: sortlist COMMA expr sortorder nulls',
        'sortlist: expr sortorder nulls',
    ],
    'sortorder' => [
        'sortorder: ASC',
        'sortorder: DESC',
        'sortorder:',
    ],
    'nulls' => [
        'nulls: NULLS FIRST',
        'nulls: NULLS LAST',
        'nulls:',
    ],
    'groupby_opt' => [
        'groupby_opt:',
        'groupby_opt: GROUP BY nexprlist',
    ],
    'having_opt' => [
        'having_opt:',
        'having_opt: HAVING expr',
    ],
    'limit_opt' => [
        'limit_opt:',
        'limit_opt: LIMIT expr',
        'limit_opt: LIMIT expr OFFSET expr',
        'limit_opt: LIMIT expr COMMA expr',
    ],
    'where_opt' => [
        'where_opt:',
        'where_opt: WHERE expr',
    ],
    'where_opt_ret' => [
        'where_opt_ret:',
        'where_opt_ret: WHERE expr',
        'where_opt_ret: RETURNING selcollist',
        'where_opt_ret: WHERE expr RETURNING selcollist',
    ],
    'setlist' => [
        'setlist: setlist COMMA nm EQ expr',
        'setlist: setlist COMMA LP idlist RP EQ expr',
        'setlist: nm EQ expr',
        'setlist: LP idlist RP EQ expr',
    ],
    'upsert' => [
        'upsert:',
        'upsert: RETURNING selcollist',
        'upsert: ON CONFLICT LP sortlist RP where_opt DO UPDATE SET setlist where_opt upsert',
        'upsert: ON CONFLICT LP sortlist RP where_opt DO NOTHING upsert',
        'upsert: ON CONFLICT DO NOTHING returning',
        'upsert: ON CONFLICT DO UPDATE SET setlist where_opt returning',
    ],
    'returning' => [
        'returning: RETURNING selcollist',
        'returning:',
    ],
    'insert_cmd' => [
        'insert_cmd: INSERT orconf',
        'insert_cmd: REPLACE',
    ],
    'idlist_opt' => [
        'idlist_opt:',
        'idlist_opt: LP idlist RP',
    ],
    'idlist' => [
        'idlist: idlist COMMA nm',
        'idlist: nm',
    ],
    'expr' => [
        'expr: term',
        'expr: LP expr RP',
        'expr: idj',
        'expr: nm DOT nm',
        'expr: nm DOT nm DOT nm',
        'expr: VARIABLE',
        'expr: expr COLLATE ids',
        'expr: CAST LP expr AS typetoken RP',
        'expr: idj LP distinct exprlist RP',
        'expr: idj LP distinct exprlist ORDER BY sortlist RP',
        'expr: idj LP STAR RP',
        'expr: idj LP distinct exprlist RP filter_over',
        'expr: idj LP distinct exprlist ORDER BY sortlist RP filter_over',
        'expr: idj LP STAR RP filter_over',
        'expr: LP nexprlist COMMA expr RP',
        'expr: expr AND expr',
        'expr: expr OR expr',
        'expr: expr LT|GT|GE|LE expr',
        'expr: expr EQ|NE expr',
        'expr: expr BITAND|BITOR|LSHIFT|RSHIFT expr',
        'expr: expr PLUS|MINUS expr',
        'expr: expr STAR|SLASH|REM expr',
        'expr: expr CONCAT expr',
        'expr: expr likeop expr',
        'expr: expr likeop expr ESCAPE expr',
        'expr: expr ISNULL|NOTNULL',
        'expr: expr NOT NULL',
        'expr: expr IS expr',
        'expr: expr IS NOT expr',
        'expr: expr IS NOT DISTINCT FROM expr',
        'expr: expr IS DISTINCT FROM expr',
        'expr: NOT expr',
        'expr: BITNOT expr',
        'expr: PLUS|MINUS expr',
        'expr: expr PTR expr',
        'expr: expr between_op expr AND expr',
        'expr: expr in_op LP exprlist RP',
        'expr: LP select RP',
        'expr: expr in_op LP select RP',
        'expr: expr in_op nm dbnm paren_exprlist',
        'expr: EXISTS LP select RP',
        'expr: CASE case_operand case_exprlist case_else END',
        'expr: RAISE LP IGNORE RP',
        'expr: RAISE LP raisetype COMMA expr RP',
    ],
    'term' => [
        'term: NULL|FLOAT|BLOB',
        'term: STRING',
        'term: INTEGER',
        'term: CTIME_KW',
        'term: QNUMBER',
    ],
    'likeop' => [
        'likeop: LIKE_KW|MATCH',
        'likeop: NOT LIKE_KW|MATCH',
    ],
    'between_op' => [
        'between_op: BETWEEN',
        'between_op: NOT BETWEEN',
    ],
    'in_op' => [
        'in_op: IN',
        'in_op: NOT IN',
    ],
    'case_exprlist' => [
        'case_exprlist: case_exprlist WHEN expr THEN expr',
        'case_exprlist: WHEN expr THEN expr',
    ],
    'case_else' => [
        'case_else: ELSE expr',
        'case_else:',
    ],
    'case_operand' => [
        'case_operand: expr',
        'case_operand:',
    ],
    'exprlist' => [
        'exprlist: nexprlist',
        'exprlist:',
    ],
    'nexprlist' => [
        'nexprlist: nexprlist COMMA expr',
        'nexprlist: expr',
    ],
    'paren_exprlist' => [
        'paren_exprlist:',
        'paren_exprlist: LP exprlist RP',
    ],
    'uniqueflag' => [
        'uniqueflag: UNIQUE',
        'uniqueflag:',
    ],
    'eidlist_opt' => [
        'eidlist_opt:',
        'eidlist_opt: LP eidlist RP',
    ],
    'eidlist' => [
        'eidlist: eidlist COMMA nm collate sortorder',
        'eidlist: nm collate sortorder',
    ],
    'collate' => [
        'collate:',
        'collate: COLLATE ids',
    ],
    'vinto' => [
        'vinto: INTO expr',
        'vinto:',
    ],
    'nmnum' => [
        'nmnum: plus_num',
        'nmnum: nm',
        'nmnum: ON',
        'nmnum: DELETE',
        'nmnum: DEFAULT',
    ],
    'plus_num' => [
        'plus_num: PLUS number',
        'plus_num: number',
    ],
    'minus_num' => [
        'minus_num: MINUS number',
    ],
    'trigger_decl' => [
        'trigger_decl: temp TRIGGER ifnotexists nm dbnm trigger_time trigger_event ON fullname foreach_clause when_clause',
    ],
    'trigger_time' => [
        'trigger_time: BEFORE|AFTER',
        'trigger_time: INSTEAD OF',
        'trigger_time:',
    ],
    'trigger_event' => [
        'trigger_event: DELETE|INSERT',
        'trigger_event: UPDATE',
        'trigger_event: UPDATE OF idlist',
    ],
    'foreach_clause' => [
        'foreach_clause:',
        'foreach_clause: FOR EACH ROW',
    ],
    'when_clause' => [
        'when_clause:',
        'when_clause: WHEN expr',
    ],
    'trigger_cmd_list' => [
        'trigger_cmd_list: trigger_cmd_list trigger_cmd SEMI',
        'trigger_cmd_list: trigger_cmd SEMI',
    ],
    'trnm' => [
        'trnm: nm',
        'trnm: nm DOT nm',
    ],
    'tridxby' => [
        'tridxby:',
        'tridxby: INDEXED BY nm',
        'tridxby: NOT INDEXED',
    ],
    'trigger_cmd' => [
        'trigger_cmd: UPDATE orconf trnm tridxby SET setlist from where_opt scanpt',
        'trigger_cmd: scanpt insert_cmd INTO trnm idlist_opt select upsert scanpt',
        'trigger_cmd: DELETE FROM trnm tridxby where_opt scanpt',
        'trigger_cmd: scanpt select scanpt',
    ],
    'raisetype' => [
        'raisetype: ROLLBACK',
        'raisetype: ABORT',
        'raisetype: FAIL',
    ],
    'key_opt' => [
        'key_opt:',
        'key_opt: KEY expr',
    ],
    'database_kw_opt' => [
        'database_kw_opt: DATABASE',
        'database_kw_opt:',
    ],
    'add_column_fullname' => [
        'add_column_fullname: fullname',
    ],
    'kwcolumn_opt' => [
        'kwcolumn_opt:',
        'kwcolumn_opt: COLUMNKW',
    ],
    'create_vtab' => [
        'create_vtab: createkw VIRTUAL TABLE ifnotexists nm dbnm USING nm',
    ],
    'vtabarglist' => [
        'vtabarglist: vtabarg',
        'vtabarglist: vtabarglist COMMA vtabarg',
    ],
    'vtabarg' => [
        'vtabarg:',
        'vtabarg: vtabarg vtabargtoken',
    ],
    'vtabargtoken' => [
        'vtabargtoken: ANY',
        'vtabargtoken: lp anylist RP',
    ],
    'lp' => [
        'lp: LP',
    ],
    'anylist' => [
        'anylist:',
        'anylist: anylist LP anylist RP',
        'anylist: anylist ANY',
    ],
    'with' => [
        'with:',
        'with: WITH wqlist',
        'with: WITH RECURSIVE wqlist',
    ],
    'wqas' => [
        'wqas: AS',
        'wqas: AS MATERIALIZED',
        'wqas: AS NOT MATERIALIZED',
    ],
    'wqitem' => [
        'wqitem: withnm eidlist_opt wqas LP select RP',
    ],
    'withnm' => [
        'withnm: nm',
    ],
    'wqlist' => [
        'wqlist: wqitem',
        'wqlist: wqlist COMMA wqitem',
    ],
    'windowdefn_list' => [
        'windowdefn_list: windowdefn',
        'windowdefn_list: windowdefn_list COMMA windowdefn',
    ],
    'windowdefn' => [
        'windowdefn: nm AS LP window RP',
    ],
    'window' => [
        'window: PARTITION BY nexprlist orderby_opt frame_opt',
        'window: nm PARTITION BY nexprlist orderby_opt frame_opt',
        'window: ORDER BY sortlist frame_opt',
        'window: nm ORDER BY sortlist frame_opt',
        'window: frame_opt',
        'window: nm frame_opt',
    ],
    'frame_opt' => [
        'frame_opt:',
        'frame_opt: range_or_rows frame_bound_s frame_exclude_opt',
        'frame_opt: range_or_rows BETWEEN frame_bound_s AND frame_bound_e frame_exclude_opt',
    ],
    'range_or_rows' => [
        'range_or_rows: RANGE|ROWS|GROUPS',
    ],
    'frame_bound_s' => [
        'frame_bound_s: frame_bound',
        'frame_bound_s: UNBOUNDED PRECEDING',
    ],
    'frame_bound_e' => [
        'frame_bound_e: frame_bound',
        'frame_bound_e: UNBOUNDED FOLLOWING',
    ],
    'frame_bound' => [
        'frame_bound: expr PRECEDING|FOLLOWING',
        'frame_bound: CURRENT ROW',
    ],
    'frame_exclude_opt' => [
        'frame_exclude_opt:',
        'frame_exclude_opt: EXCLUDE frame_exclude',
    ],
    'frame_exclude' => [
        'frame_exclude: NO OTHERS',
        'frame_exclude: CURRENT ROW',
        'frame_exclude: GROUP|TIES',
    ],
    'window_clause' => [
        'window_clause: WINDOW windowdefn_list',
    ],
    'filter_over' => [
        'filter_over: filter_clause over_clause',
        'filter_over: over_clause',
        'filter_over: filter_clause',
    ],
    'over_clause' => [
        'over_clause: OVER LP window RP',
        'over_clause: OVER nm',
    ],
    'filter_clause' => [
        'filter_clause: FILTER LP WHERE expr RP',
    ],
];
