"""Read the frozen production lists as data and expose unresolved semantic classification."""

import hashlib
import json
import re

from grammar_contexts import statement_contexts
from scope import CONTRACTS, TOOL

BASELINE = "df59b1f5e920748a19a5bca3df5e93dfa38bdbafcad6617a464c5b533624a898"
RULE = re.compile(r"^    '([A-Za-z_][A-Za-z0-9_]*)' => \[$")
ALTERNATIVE = re.compile(r"^        '([^'\\]*)',$")
SQLITE = "packages/sql-semantics-sqlite/resources/productions/sqlite-3.47.2.php"
RELATED_SQLITE_DOMAINS = {
    "expr: expr likeop expr": ("sqlite-pattern-v1.json",),
    "expr: expr likeop expr ESCAPE expr": ("sqlite-pattern-v1.json",),
    "likeop: LIKE_KW|MATCH": ("sqlite-pattern-v1.json",),
    "likeop: NOT LIKE_KW|MATCH": ("sqlite-pattern-v1.json",),
    "expr: CAST LP expr AS typetoken RP": ("sqlite-cast-v1.json", "sqlite-index-expression-spelling-v1.json"),
    "expr: CASE case_operand case_exprlist case_else END": ("sqlite-case-v1.json",),
    "case_exprlist: case_exprlist WHEN expr THEN expr": ("sqlite-case-v1.json",),
    "case_exprlist: WHEN expr THEN expr": ("sqlite-case-v1.json",),
    "case_else: ELSE expr": ("sqlite-case-v1.json",),
    "case_else:": ("sqlite-case-v1.json",),
    "case_operand: expr": ("sqlite-case-v1.json",),
    "case_operand:": ("sqlite-case-v1.json",),
    "groupby_opt: GROUP BY nexprlist": ("sqlite-grouped-v2.json", "sqlite-group-filter-v1.json"),
    "having_opt: HAVING expr": ("sqlite-group-filter-v1.json",),
    "on_using: USING LP idlist RP": ("sqlite-merged-join-v3.json", "sqlite-merged-prefix-join-v1.json", "sqlite-merged-right-join-v2.json", "sqlite-merged-full-join-v1.json"),
    "joinop: JOIN_KW JOIN": ("sqlite-merged-join-v3.json", "sqlite-merged-prefix-join-v1.json", "sqlite-merged-right-join-v2.json", "sqlite-merged-full-join-v1.json"),
    "joinop: JOIN_KW nm JOIN": ("sqlite-merged-join-v3.json", "sqlite-merged-prefix-join-v1.json", "sqlite-merged-right-join-v2.json", "sqlite-merged-full-join-v1.json"),
    "joinop: JOIN_KW nm nm JOIN": ("sqlite-merged-join-v3.json", "sqlite-merged-prefix-join-v1.json", "sqlite-merged-right-join-v2.json", "sqlite-merged-full-join-v1.json"),
    "where_opt: WHERE expr": ("sqlite-relational-v4.json", "sqlite-group-filter-v1.json"),
    "selectnowith: selectnowith multiselect_op oneselect": ("sqlite-compound-v1.json", "sqlite-recursive-source-v1.json"),
    "term: NULL|FLOAT|BLOB": ("sqlite-scalars-v2.json",),
    "term: STRING": ("sqlite-scalars-v2.json",),
    "term: INTEGER": ("sqlite-scalars-v2.json",),
    "expr: expr AND expr": ("sqlite-scalars-v2.json",),
    "expr: expr OR expr": ("sqlite-scalars-v2.json",),
    "expr: expr STAR|SLASH|REM expr": ("sqlite-scalars-v2.json", "sqlite-numeric-operators-v1.json"),
    "expr: expr BITAND|BITOR|LSHIFT|RSHIFT expr": ("sqlite-numeric-operators-v1.json",),
    "expr: NOT expr": ("sqlite-unary-v2.json",),
    "expr: BITNOT expr": ("sqlite-unary-v2.json",),
    "expr: PLUS|MINUS expr": ("sqlite-unary-v2.json",),
    "expr: expr ISNULL|NOTNULL": ("sqlite-unary-v2.json",),
    "expr: expr in_op LP exprlist RP": ("sqlite-membership-v1.json", "sqlite-folded-membership-v1.json"),
    "expr: expr CONCAT expr": ("sqlite-concatenation-v1.json",),
    "expr: expr COLLATE ids": ("sqlite-collation-v1.json",),
    "expr: idj LP distinct exprlist RP": ("sqlite-coalesce-v1.json", "sqlite-index-expression-spelling-v1.json"),
    "expr: expr between_op expr AND expr": ("sqlite-between-v1.json",),
    "expr: expr NOT NULL": ("sqlite-unary-v2.json",),
    "expr: expr LT|GT|GE|LE expr": ("sqlite-scalars-v2.json",),
    "expr: expr EQ|NE expr": ("sqlite-scalars-v2.json",),
    "expr: idj": ("sqlite-truth-v2.json",),
    "expr: expr IS expr": ("sqlite-scalars-v2.json", "sqlite-truth-v2.json"),
    "expr: expr IS NOT expr": ("sqlite-scalars-v2.json", "sqlite-truth-v2.json"),
    "expr: expr IS NOT DISTINCT FROM expr": ("sqlite-scalars-v2.json", "sqlite-truth-v2.json"),
    "expr: expr IS DISTINCT FROM expr": ("sqlite-scalars-v2.json", "sqlite-truth-v2.json"),
    "cmd: select": ("contract-v1.json", "sqlite-relational-v4.json", "sqlite-result-v1.json",
                    "sqlite-values-v1.json", "sqlite-local-source-v2.json", "sqlite-recursive-source-v1.json", "sqlite-joined-local-source-v1.json", "sqlite-resolved-view-input-v2.json", "sqlite-merged-join-v3.json", "sqlite-merged-prefix-join-v1.json", "sqlite-merged-right-join-v2.json", "sqlite-merged-full-join-v1.json"),
    "cmd: with DELETE FROM xfullname indexed_opt where_opt_ret": ("sqlite-delete-v1.json", "sqlite-delete-returning-v1.json"),
    "cmd: with insert_cmd INTO xfullname idlist_opt select upsert": (
        "sqlite-insert-v1.json", "sqlite-insert-query-v1.json", "sqlite-insert-input-v2.json", "sqlite-insert-returning-v1.json",
        "sqlite-mutation-conflicts-v2.json", "sqlite-upsert-nothing-v1.json"),
    "cmd: with insert_cmd INTO xfullname idlist_opt DEFAULT VALUES returning": ("sqlite-insert-input-v2.json", "sqlite-insert-returning-v1.json"),
    "cmd: with UPDATE orconf xfullname indexed_opt SET setlist from where_opt_ret": (
        "sqlite-update-v1.json", "sqlite-update-returning-v1.json", "sqlite-mutation-conflicts-v2.json"),
    "orconf: OR resolvetype": ("sqlite-mutation-conflicts-v2.json",),
    "insert_cmd: INSERT orconf": ("sqlite-mutation-conflicts-v2.json",),
    "insert_cmd: REPLACE": ("sqlite-mutation-conflicts-v2.json", "sqlite-upsert-nothing-v1.json"),
    "upsert: ON CONFLICT DO NOTHING returning": ("sqlite-upsert-nothing-v1.json",),
    "cmd: createkw temp VIEW ifnotexists nm dbnm eidlist_opt AS select": ("sqlite-create-view-v2.json", "sqlite-create-view-grouped-v1.json", "sqlite-create-view-compound-v1.json", "sqlite-create-view-local-v1.json"),
    "cmd: DROP VIEW ifexists fullname": ("sqlite-drop-view-v1.json",),
    "cmd: DROP TABLE ifexists fullname": ("sqlite-drop-table-v2.json",),
    "cmd: DROP INDEX ifexists fullname": ("sqlite-drop-index-v3.json", "sqlite-constraint-index-names-v2.json"),
    "cmd: DROP TRIGGER ifexists fullname": ("sqlite-drop-triggers-v4.json",),
    "cmd: REINDEX": ("sqlite-reindex-v2.json",),
    "cmd: REINDEX nm dbnm": ("sqlite-reindex-v2.json",),
    "cmd: ANALYZE": ("sqlite-analyze-statistics-v1.json",),
    "cmd: ANALYZE nm dbnm": ("sqlite-analyze-statistics-v1.json",),
    "cmd: PRAGMA nm dbnm": ("sqlite-flag-pragmas-v2.json", "sqlite-table-metadata-v2.json", "sqlite-connection-inventory-v1.json", "sqlite-index-metadata-v1.json", "sqlite-foreign-key-audit-v1.json", "sqlite-integrity-audit-v2.json", "sqlite-header-pragmas-v1.json", "sqlite-page-pragmas-v2.json", "sqlite-reclaim-pragmas-v2.json", "sqlite-cache-pragmas-v2.json"),
    "cmd: VACUUM vinto": ("sqlite-vacuum-v2.json",),
    "cmd: createkw trigger_decl BEGIN trigger_cmd_list END": ("sqlite-audit-triggers-v2.json", "sqlite-trigger-mutations-v2.json", "sqlite-trigger-insert-batches-v1.json", "sqlite-trigger-raise-v1.json"),
    "trigger_decl: temp TRIGGER ifnotexists nm dbnm trigger_time trigger_event ON fullname foreach_clause when_clause": ("sqlite-audit-triggers-v2.json",),
    "trigger_time:": ("sqlite-audit-triggers-v2.json",),
    "trigger_time: BEFORE|AFTER": ("sqlite-audit-triggers-v2.json",),
    "trigger_time: INSTEAD OF": ("sqlite-audit-triggers-v2.json",),
    "trigger_event: DELETE|INSERT": ("sqlite-audit-triggers-v2.json",),
    "trigger_event: UPDATE": ("sqlite-audit-triggers-v2.json",),
    "trigger_event: UPDATE OF idlist": ("sqlite-audit-triggers-v2.json",),
    "foreach_clause:": ("sqlite-audit-triggers-v2.json",),
    "foreach_clause: FOR EACH ROW": ("sqlite-audit-triggers-v2.json",),
    "when_clause:": ("sqlite-audit-triggers-v2.json",),
    "when_clause: WHEN expr": ("sqlite-audit-triggers-v2.json",),
    "trigger_cmd_list: trigger_cmd_list trigger_cmd SEMI": ("sqlite-audit-triggers-v2.json", "sqlite-trigger-mutations-v2.json"),
    "trigger_cmd_list: trigger_cmd SEMI": ("sqlite-audit-triggers-v2.json", "sqlite-trigger-mutations-v2.json"),
    "trigger_cmd: scanpt insert_cmd INTO trnm idlist_opt select upsert scanpt": ("sqlite-audit-triggers-v2.json", "sqlite-trigger-insert-batches-v1.json"),
    "trigger_cmd: UPDATE orconf trnm tridxby SET setlist from where_opt scanpt": ("sqlite-trigger-mutations-v2.json",),
    "trigger_cmd: DELETE FROM trnm tridxby where_opt scanpt": ("sqlite-trigger-mutations-v2.json",),
    "trigger_cmd: scanpt select scanpt": ("sqlite-trigger-raise-v1.json",),
    "expr: RAISE LP IGNORE RP": ("sqlite-trigger-raise-v1.json",),
    "expr: RAISE LP raisetype COMMA expr RP": ("sqlite-trigger-raise-v1.json",),
    "trnm: nm": ("sqlite-audit-triggers-v2.json",),
    "cmd: VACUUM nm vinto": ("sqlite-vacuum-v2.json",),
    "vinto:": ("sqlite-vacuum-v2.json",),
    "vinto: INTO expr": ("sqlite-vacuum-v2.json",),
    "cmd: ATTACH database_kw_opt expr AS expr key_opt": ("sqlite-connection-commands-v1.json",),
    "cmd: DETACH database_kw_opt expr": ("sqlite-connection-commands-v1.json",),
    "database_kw_opt:": ("sqlite-connection-commands-v1.json",),
    "database_kw_opt: DATABASE": ("sqlite-connection-commands-v1.json",),
    "key_opt:": ("sqlite-connection-commands-v1.json",),
    "key_opt: KEY expr": ("sqlite-connection-commands-v1.json",),
    "cmd: PRAGMA nm dbnm EQ nmnum": ("sqlite-flag-pragmas-v2.json", "sqlite-table-metadata-v2.json", "sqlite-connection-inventory-v1.json", "sqlite-index-metadata-v1.json", "sqlite-foreign-key-audit-v1.json", "sqlite-integrity-audit-v2.json", "sqlite-header-pragmas-v1.json", "sqlite-page-pragmas-v2.json", "sqlite-reclaim-pragmas-v2.json", "sqlite-cache-pragmas-v2.json"),
    "cmd: PRAGMA nm dbnm LP nmnum RP": ("sqlite-flag-pragmas-v2.json", "sqlite-table-metadata-v2.json", "sqlite-connection-inventory-v1.json", "sqlite-index-metadata-v1.json", "sqlite-foreign-key-audit-v1.json", "sqlite-integrity-audit-v2.json", "sqlite-header-pragmas-v1.json", "sqlite-page-pragmas-v2.json", "sqlite-reclaim-pragmas-v2.json", "sqlite-cache-pragmas-v2.json"),
    "cmd: PRAGMA nm dbnm EQ minus_num": ("sqlite-flag-pragmas-v2.json", "sqlite-table-metadata-v2.json", "sqlite-connection-inventory-v1.json", "sqlite-index-metadata-v1.json", "sqlite-foreign-key-audit-v1.json", "sqlite-integrity-audit-v2.json", "sqlite-header-pragmas-v1.json", "sqlite-page-pragmas-v2.json", "sqlite-reclaim-pragmas-v2.json", "sqlite-cache-pragmas-v2.json"),
    "cmd: PRAGMA nm dbnm LP minus_num RP": ("sqlite-flag-pragmas-v2.json", "sqlite-table-metadata-v2.json", "sqlite-connection-inventory-v1.json", "sqlite-index-metadata-v1.json", "sqlite-foreign-key-audit-v1.json", "sqlite-integrity-audit-v2.json", "sqlite-header-pragmas-v1.json", "sqlite-page-pragmas-v2.json", "sqlite-reclaim-pragmas-v2.json", "sqlite-cache-pragmas-v2.json"),
    "nmnum: plus_num": ("sqlite-flag-pragmas-v2.json", "sqlite-table-metadata-v2.json", "sqlite-connection-inventory-v1.json", "sqlite-index-metadata-v1.json", "sqlite-foreign-key-audit-v1.json", "sqlite-integrity-audit-v2.json", "sqlite-header-pragmas-v1.json", "sqlite-page-pragmas-v2.json", "sqlite-reclaim-pragmas-v2.json", "sqlite-cache-pragmas-v2.json"),
    "nmnum: nm": ("sqlite-flag-pragmas-v2.json", "sqlite-table-metadata-v2.json", "sqlite-connection-inventory-v1.json", "sqlite-index-metadata-v1.json", "sqlite-foreign-key-audit-v1.json", "sqlite-integrity-audit-v2.json", "sqlite-header-pragmas-v1.json", "sqlite-page-pragmas-v2.json", "sqlite-reclaim-pragmas-v2.json", "sqlite-cache-pragmas-v2.json"),
    "nmnum: ON": ("sqlite-flag-pragmas-v2.json", "sqlite-table-metadata-v2.json", "sqlite-connection-inventory-v1.json", "sqlite-index-metadata-v1.json", "sqlite-foreign-key-audit-v1.json", "sqlite-integrity-audit-v2.json", "sqlite-header-pragmas-v1.json", "sqlite-page-pragmas-v2.json", "sqlite-reclaim-pragmas-v2.json", "sqlite-cache-pragmas-v2.json"),
    "nmnum: DELETE": ("sqlite-flag-pragmas-v2.json", "sqlite-table-metadata-v2.json", "sqlite-connection-inventory-v1.json", "sqlite-index-metadata-v1.json", "sqlite-foreign-key-audit-v1.json", "sqlite-integrity-audit-v2.json", "sqlite-header-pragmas-v1.json", "sqlite-page-pragmas-v2.json", "sqlite-reclaim-pragmas-v2.json", "sqlite-cache-pragmas-v2.json"),
    "nmnum: DEFAULT": ("sqlite-flag-pragmas-v2.json", "sqlite-table-metadata-v2.json", "sqlite-connection-inventory-v1.json", "sqlite-index-metadata-v1.json", "sqlite-foreign-key-audit-v1.json", "sqlite-integrity-audit-v2.json", "sqlite-header-pragmas-v1.json", "sqlite-page-pragmas-v2.json", "sqlite-reclaim-pragmas-v2.json", "sqlite-cache-pragmas-v2.json"),
    "cmd: ALTER TABLE fullname RENAME TO nm": ("sqlite-rename-v4.json",),
    "cmd: ALTER TABLE fullname RENAME kwcolumn_opt nm TO nm": ("sqlite-rename-v4.json",),
    "cmd: ALTER TABLE add_column_fullname ADD kwcolumn_opt columnname carglist": ("sqlite-add-column-v3.json", "sqlite-add-generated-column-v1.json", "sqlite-appended-column-checks-v2.json", "sqlite-appended-implicit-checks-v1.json"),
    "cmd: createkw uniqueflag INDEX ifnotexists nm dbnm ON nm LP sortlist RP where_opt": ("sqlite-create-index-v5.json", "sqlite-index-expression-spelling-v1.json"),
    "cmd: ALTER TABLE fullname DROP kwcolumn_opt nm": ("sqlite-drop-column-v3.json",),
    "add_column_fullname: fullname": ("sqlite-add-column-v3.json", "sqlite-add-generated-column-v1.json", "sqlite-appended-column-checks-v2.json", "sqlite-appended-implicit-checks-v1.json"),
    "cmd: create_table create_table_args": ("sqlite-create-table-v3.json", "sqlite-defaults-v4.json", "sqlite-computed-column-default-v3.json", "sqlite-not-null-conflict-v2.json", "sqlite-strict-tables-v2.json", "sqlite-primary-keys-v2.json", "sqlite-unique-keys-v1.json", "sqlite-create-as-v5.json", "sqlite-named-checks-v4.json", "sqlite-generated-columns-v2.json"),
    "generated: LP expr RP ID": ("sqlite-generated-columns-v2.json",),
    "generated: LP expr RP": ("sqlite-generated-columns-v2.json",),
    "ccons: AS generated": ("sqlite-generated-columns-v2.json",),
    "ccons: GENERATED ALWAYS AS generated": ("sqlite-generated-columns-v2.json",),
    "ccons: CONSTRAINT nm": ("sqlite-named-checks-v4.json", "sqlite-appended-column-checks-v2.json", "sqlite-appended-implicit-checks-v1.json"),
    "ccons: CHECK LP expr RP": ("sqlite-named-checks-v4.json", "sqlite-folded-checks-v1.json", "sqlite-implicit-checks-v1.json", "sqlite-appended-column-checks-v2.json", "sqlite-appended-implicit-checks-v1.json"),
    "tcons: CONSTRAINT nm": ("sqlite-named-checks-v4.json",),
    "tcons: CHECK LP expr RP onconf": ("sqlite-named-checks-v4.json", "sqlite-folded-checks-v1.json", "sqlite-implicit-checks-v1.json"),
    "ccons: UNIQUE onconf": ("sqlite-unique-keys-v1.json",),
    "ccons: COLLATE ids": ("sqlite-unique-keys-v1.json",),
    "tcons: UNIQUE LP sortlist RP onconf": ("sqlite-unique-keys-v1.json",),
    "create_table_args: AS select": ("sqlite-create-as-v5.json",),
    "create_table_args: LP columnlist conslist_opt RP table_option_set": ("sqlite-strict-tables-v2.json", "sqlite-primary-keys-v2.json", "sqlite-generated-columns-v2.json"),
    "table_option_set:": ("sqlite-strict-tables-v2.json",),
    "table_option_set: table_option": ("sqlite-strict-tables-v2.json", "sqlite-primary-keys-v2.json"),
    "table_option_set: table_option_set COMMA table_option": ("sqlite-strict-tables-v2.json", "sqlite-primary-keys-v2.json"),
    "table_option: nm": ("sqlite-strict-tables-v2.json",),
    "table_option: WITHOUT nm": ("sqlite-primary-keys-v2.json",),
    "ccons: PRIMARY KEY sortorder onconf autoinc": ("sqlite-primary-keys-v2.json", "sqlite-auto-rowid-v1.json"),
    "tcons: PRIMARY KEY LP sortlist autoinc RP onconf": ("sqlite-primary-keys-v2.json", "sqlite-auto-rowid-v1.json"),
    "autoinc: AUTOINCR": ("sqlite-auto-rowid-v1.json",),
    "autoinc:": ("sqlite-auto-rowid-v1.json",),
    "ccons: DEFAULT scantok term": ("sqlite-defaults-v4.json",),
    "ccons: DEFAULT LP expr RP": ("sqlite-defaults-v4.json", "sqlite-computed-column-default-v3.json"),
    "ccons: DEFAULT PLUS scantok term": ("sqlite-defaults-v4.json",),
    "ccons: DEFAULT MINUS scantok term": ("sqlite-defaults-v4.json",),
    "ccons: DEFAULT scantok id": ("sqlite-defaults-v4.json",),
    "ccons: NOT NULL onconf": ("sqlite-not-null-conflict-v2.json",),
    "onconf:": ("sqlite-not-null-conflict-v2.json",),
    "onconf: ON CONFLICT resolvetype": ("sqlite-not-null-conflict-v2.json",),
    "resolvetype: raisetype": ("sqlite-not-null-conflict-v2.json",),
    "resolvetype: IGNORE": ("sqlite-not-null-conflict-v2.json",),
    "resolvetype: REPLACE": ("sqlite-not-null-conflict-v2.json",),
    "raisetype: ROLLBACK": ("sqlite-not-null-conflict-v2.json",),
    "raisetype: ABORT": ("sqlite-not-null-conflict-v2.json",),
    "raisetype: FAIL": ("sqlite-not-null-conflict-v2.json",),
    "cmd: BEGIN transtype trans_opt": ("sqlite-transaction-v1.json",),
    "cmd: COMMIT|END trans_opt": ("sqlite-transaction-v1.json",),
    "cmd: ROLLBACK trans_opt": ("sqlite-transaction-v1.json",),
    "cmd: SAVEPOINT nm": ("sqlite-transaction-v1.json",),
    "cmd: RELEASE savepoint_opt nm": ("sqlite-transaction-v1.json",),
    "cmd: ROLLBACK trans_opt TO savepoint_opt nm": ("sqlite-transaction-v1.json",),
}


def parse(root, path, expected):
    file = (root / path).read_bytes()
    if hashlib.sha256(file).hexdigest() != expected["sha256"]:
        raise ValueError(f"Grammar artifact differs from the required baseline: {path}")
    lines = iter(file.decode().splitlines())
    if not any(line == "return [" for line in lines):
        raise ValueError(f"Missing grammar list in {path}")
    rules = {}
    current = None
    end = False
    for line in lines:
        if current is None:
            if line == "];":
                end = True
                break
            match = RULE.fullmatch(line)
            if match is None or match[1] in rules:
                raise ValueError(f"Unexpected grammar rule in {path}: {line}")
            current = match[1]
            rules[current] = []
        elif line == "    ]," and rules[current]:
            current = None
        else:
            match = ALTERNATIVE.fullmatch(line)
            if match is None or not match[1].startswith(current + ":"):
                raise ValueError(f"Unexpected grammar alternative in {path}: {line}")
            rules[current].append(match[1])
    nonempty = sum(alternative != rule + ":" for rule, alternatives in rules.items() for alternative in alternatives)
    if not end or any(lines) or nonempty != expected["alternatives"]:
        raise ValueError(f"Incomplete grammar alternatives in {path}")
    return rules


def inventory(root):
    requirements = (TOOL / "required-grammars.json").read_bytes()
    if hashlib.sha256(requirements).hexdigest() != BASELINE:
        raise ValueError("The required grammar inventory differs from the baseline revision")
    artifacts = {}
    for path, record in json.loads(requirements)["artifacts"].items():
        mysql = "sql-semantics-mysql/resources/" in path
        postgres = "sql-semantics-postgres/resources/" in path
        rules = parse(root, path, record)
        related = RELATED_SQLITE_DOMAINS if path == SQLITE else {}
        if mysql:
            entry = "simple_statement" if "simple_statement" in rules else "statement"
            related = {alternative: ("server-delete-v2.json",) for alternative in rules.get("delete_stmt", [])}
            related.update({alternative: ("mysql-text-literal-v1.json",) for alternative in rules.get("text_literal", ())
                            if alternative in ("text_literal: TEXT_STRING", "text_literal: text_literal TEXT_STRING_literal")})
            related.update({alternative: ("mysql-signed-quotient-v1.json",) for alternative in (
                "bit_expr: bit_expr % bit_expr", "bit_expr: bit_expr DIV_SYM bit_expr", "bit_expr: bit_expr MOD_SYM bit_expr",
            )})
            related.update({alternative: ("mysql-signed-range-v3.json",) for alternative in rules["predicate"]
                            if " BETWEEN_SYM " in alternative})
            for rule in ("create_view", "view_tail", "view_query_block", "view_algorithm", "view_suid"):
                for alternative in rules.get(rule, ()):
                    if alternative != rule + ":":
                        related[alternative] = (*related.get(alternative, ()), "mysql-create-view-v2.json")
            for rule in ("drop", "drop_view", "drop_view_stmt"):
                for alternative in rules.get(rule, ()):
                    if " VIEW_SYM " in alternative or (rule != "drop" and alternative != rule + ":"):
                        related[alternative] = (*related.get(alternative, ()), "mysql-drop-view-v3.json")
            for rule in ("having_clause", "opt_having_clause"):
                for alternative in rules.get(rule, ()):
                    if " HAVING " in alternative or " HAVING_SYM " in alternative:
                        related[alternative] = (*related.get(alternative, ()), "mysql-grouped-having-v2.json")
            for rule in ("with_clause", "with_list", "common_table_expr"):
                for alternative in rules.get(rule, ()):
                    if alternative != rule + ":" and "RECURSIVE_SYM" not in alternative:
                        related[alternative] = (*related.get(alternative, ()), "mysql-with-projection-v1.json")
            for rule in ("table_factor", "single_table"):
                for alternative in rules.get(rule, ()):
                    if alternative in ("table_factor: single_table", "single_table: table_ident opt_use_partition opt_table_alias opt_key_definition",
                                       "table_factor: table_ident opt_use_partition opt_table_alias opt_key_definition"):
                        related[alternative] = (*related.get(alternative, ()), "mysql-external-views-v1.json")
            for rule in ("varchar", "simple_ident", "simple_ident_q", "table_wild", "opt_charset_with_opt_binary"):
                for alternative in rules.get(rule, ()):
                    related[alternative] = (*related.get(alternative, ()), "mysql-varchar-slots-v2.json")
            for rule, tokens in (("type", "varchar field_length"), ("column_attribute", "COLLATE_SYM collation_name")):
                for alternative in rules.get(rule, ()):
                    if tokens in alternative:
                        related[alternative] = (*related.get(alternative, ()), "mysql-varchar-slots-v2.json")
            for alternative in rules.get("bool_pri", ()):
                if alternative.upper().endswith((" IS NULL_SYM", " IS NOT NULL_SYM")):
                    related[alternative] = (*related.get(alternative, ()), "mysql-varchar-presence-v1.json")
            comparison_tokens = {"EQ", "EQUAL_SYM", "GE", "GT_SYM", "LE", "LT", "NE"}
            for alternative in rules.get("comp_op", ()):
                if alternative.split(": ", 1)[1] in comparison_tokens:
                    related[alternative] = (*related.get(alternative, ()), "mysql-varchar-comparison-v1.json")
            for alternative in rules.get("bool_pri", ()):
                if alternative in ("bool_pri: bool_pri comp_op predicate", "bool_pri: bool_pri EQUAL_SYM predicate"):
                    related[alternative] = (*related.get(alternative, ()), "mysql-varchar-comparison-v1.json")
            for rule in ("update_stmt", "delete_stmt"):
                for alternative in rules.get(rule, ()):
                    related[alternative] = (*related.get(alternative, ()), "mysql-varchar-effects-v1.json")
            for rule in ("update_stmt", "update_list", "update_elem"):
                for alternative in rules.get(rule, ()):
                    related[alternative] = (*related.get(alternative, ()), "mysql-varchar-assignment-v4.json")
            for rule in ("insert", "insert_stmt", "insert_values", "insert_from_constructor", "values_list", "insert_column"):
                for alternative in rules.get(rule, ()):
                    related[alternative] = (*related.get(alternative, ()), "mysql-varchar-insert-v2.json")
            query_insert = (
                *rules.get("insert", ()),
                *(alternative for alternative in rules.get("insert_stmt", ()) if " insert_query_expression " in alternative),
                *(alternative for alternative in rules.get("insert_values", ()) if " create_select " in alternative),
                *rules.get("insert_query_expression", ()),
            )
            for alternative in query_insert:
                related[alternative] = (*related.get(alternative, ()), "mysql-varchar-insert-query-v2.json")
            grouped_count = (
                *(alternative for alternative in rules.get("sum_expr", ()) if alternative.startswith("sum_expr: COUNT_SYM (")),
                *(alternative for alternative in [*rules.get("group_clause", ()), *rules.get("opt_group_clause", ())]
                  if " GROUP_SYM BY group_list " in alternative),
                *rules.get("group_list", ()),
            )
            for alternative in grouped_count:
                related[alternative] = (*related.get(alternative, ()), "mysql-varchar-count-v1.json")
            for alternative in rules.get("sum_expr", ()):
                if alternative.startswith(("sum_expr: MIN_SYM (", "sum_expr: MAX_SYM (")):
                    related[alternative] = (*related.get(alternative, ()), "mysql-varchar-extrema-v2.json")
            for rule in ("default_charset", "default_collation", "create_table_option"):
                for alternative in rules.get(rule, ()):
                    if alternative != rule + ":" and (
                        rule != "create_table_option" or "default_charset" in alternative or "default_collation" in alternative
                    ):
                        related[alternative] = (*related.get(alternative, ()), "mysql-varchar-inheritance-v2.json")
            for alternative in rules.get("simple_expr", ()):
                if alternative == "simple_expr: simple_expr COLLATE_SYM ident_or_text":
                    related[alternative] = (*related.get(alternative, ()), "mysql-explicit-text-collation-v1.json")
            derived_varchar = (
                *(alternative for alternative in rules.get("table_factor", ())
                  if "select_derived" in alternative or alternative == "table_factor: derived_table"),
                *(alternative for alternative in rules.get("derived_table", ())
                  if alternative == "derived_table: table_subquery opt_table_alias opt_derived_column_list"),
            )
            for alternative in derived_varchar:
                related[alternative] = (*related.get(alternative, ()), "mysql-derived-varchar-v1.json")
            parameters = ("param_marker", "simple_expr", "cast_type")
            local = ("table_factor", "derived_table", "with_clause", "with_list", "common_table_expr")
            compound = ("union_list", "union_clause", "query_expression_body")
            cast_suffix = "" if "/mysql-5." in path else " opt_array_cast"
            related.update({alternative: ("mysql-signed-casts-v2.json",) for alternative in (
                f"simple_expr: CAST_SYM ( expr AS cast_type{cast_suffix} )",
                "simple_expr: CONVERT_SYM ( expr , cast_type )",
                "cast_type: SIGNED_SYM", "cast_type: SIGNED_SYM INT_SYM",
            )})
            unsigned = "UNSIGNED" if "/mysql-5." in path else "UNSIGNED_SYM"
            for alternative in (f"simple_expr: CAST_SYM ( expr AS cast_type{cast_suffix} )",
                                "simple_expr: CONVERT_SYM ( expr , cast_type )",
                                f"cast_type: {unsigned}", f"cast_type: {unsigned} INT_SYM"):
                related[alternative] = (*related.get(alternative, ()), "mysql-unsigned-casts-v3.json")
            create = ("create_table_stmt", "create_table", "column_def", "field_def", "column_attribute")
            drop = ("drop_table_stmt", "drop")
            transaction = ("begin", "start", "commit", "rollback", "savepoint", "release", "opt_chain", "opt_release")
            update = ("update", "update_stmt", "update_list", "update_elem")
            insert = ("insert", "insert_stmt", "insert_values", "insert_from_constructor", "insert_from_constructor_opt", "values_list")
            selection = ("order_clause", "opt_order_clause", "limit_clause", "opt_limit_clause", "select_options", "select_option")
            for rule in ("from_clause", "from_tables", "table_reference_list", "table_reference_list_parens", "join_table_list"):
                for alternative in rules.get(rule, ()):
                    if "DUAL_SYM" not in alternative:
                        related[alternative] = ("mysql-from-list-v1.json",)
            families = ("join_table", "normal_join", "joined_table", "inner_join_type", "outer_join_type", "where_clause")
        elif postgres:
            entry = "stmt"
            related = {alternative: ("server-delete-v2.json", "postgres-delete-returning-v3.json") for alternative in rules.get("DeleteStmt", [])}
            related["returning_clause: RETURNING target_list"] = ("postgres-delete-returning-v3.json", "postgres-update-returning-v4.json",
                                                                  "postgres-boolean-returning-v1.json",
                                                                  "postgres-insert-returning-v2.json", "postgres-text-returning-v2.json")
            related.update({alternative: ("postgres-int4-operators-v2.json",) for alternative in (
                "a_expr: a_expr / a_expr", "a_expr: a_expr % a_expr", "a_expr: a_expr qual_Op a_expr",
                "b_expr: b_expr / b_expr", "b_expr: b_expr % b_expr", "b_expr: b_expr qual_Op b_expr",
            )})
            related.update({alternative: ("postgres-int4-case-v1.json",) for alternative in (
                "c_expr: case_expr", "case_expr: CASE case_arg when_clause_list case_default END_P",
                "when_clause_list: when_clause", "when_clause_list: when_clause_list when_clause",
                "when_clause: WHEN a_expr THEN a_expr", "case_default: ELSE a_expr", "case_default:", "case_arg:",
            )})
            related.update({alternative: ("postgres-int4-range-v1.json", "postgres-signed-range-widths-v1.json") for alternative in rules["a_expr"]
                            if " BETWEEN " in alternative})
            related.update({alternative: ("postgres-int4-distinctness-v1.json", "postgres-signed-range-widths-v1.json") for rule in ("a_expr", "b_expr")
                            for alternative in rules[rule] if " DISTINCT FROM " in alternative})
            related.update({alternative: ("postgres-boolean-tests-v1.json",) for alternative in rules["a_expr"]
                            if any(alternative.endswith(" " + mode) for mode in ("TRUE_P", "FALSE_P", "UNKNOWN"))
                            and " IS " in alternative})
            related.update({alternative: ("postgres-builtin-casts-v4.json", "postgres-text-conversion-v1.json", "postgres-boolean-write-inputs-v1.json", "postgres-int8-fields-v1.json") for alternative in (
                "a_expr: a_expr TYPECAST Typename", "b_expr: b_expr TYPECAST Typename",
                "func_expr_common_subexpr: CAST ( a_expr AS Typename )",
            )})
            for rule in ("a_expr", "b_expr"):
                for alternative in rules.get(rule, ()):
                    if alternative.endswith((" IS NULL_P", " IS NOT NULL_P")):
                        related[alternative] = (*related.get(alternative, ()), "postgres-text-null-tests-v1.json", "postgres-boolean-fields-v1.json", "postgres-int8-fields-v1.json")
                    if alternative.endswith((" IS TRUE_P", " IS FALSE_P", " IS UNKNOWN", " IS NOT TRUE_P", " IS NOT FALSE_P", " IS NOT UNKNOWN")):
                        related[alternative] = (*related.get(alternative, ()), "postgres-boolean-fields-v1.json")
            for alternative in rules.get("insert_rest", ()):
                if "SelectStmt" in alternative:
                    related[alternative] = (*related.get(alternative, ()), "postgres-text-insert-query-v2.json", "postgres-boolean-writes-v1.json", "postgres-boolean-write-inputs-v1.json")
            for rule in ("values_clause", "insert_column_list", "insert_column_item"):
                for alternative in rules.get(rule, ()):
                    related[alternative] = (*related.get(alternative, ()), "postgres-text-insert-v2.json", "postgres-boolean-writes-v1.json", "postgres-boolean-write-inputs-v1.json")
            for alternative in rules.get("set_clause", ()):
                if " '=' a_expr" in alternative or " = a_expr" in alternative:
                    related[alternative] = (*related.get(alternative, ()), "postgres-text-update-v2.json", "postgres-boolean-writes-v1.json", "postgres-boolean-write-inputs-v1.json")
            for alternative in rules.get("table_ref", ()):
                if "relation_expr opt_alias_clause" in alternative:
                    related[alternative] = (*related.get(alternative, ()), "postgres-external-source-kinds-v1.json")
                if "select_with_parens" in alternative and "LATERAL" not in alternative:
                    related[alternative] = (*related.get(alternative, ()), "postgres-derived-text-v1.json", "postgres-boolean-fields-v1.json", "postgres-int8-fields-v1.json")
            for alternative in rules.get("relation_expr", ()):
                if "qualified_name" in alternative:
                    related[alternative] = (*related.get(alternative, ()), "postgres-external-source-kinds-v1.json")
            for alternative in rules.get("having_clause", ()):
                if "HAVING a_expr" in alternative:
                    related[alternative] = (*related.get(alternative, ()), "postgres-grouped-having-v1.json")
            for rule in ("with_clause", "cte_list", "common_table_expr", "opt_materialized"):
                for alternative in rules.get(rule, ()):
                    if "RECURSIVE" not in alternative:
                        related[alternative] = (*related.get(alternative, ()), "postgres-with-projection-v1.json")
            for rule in ("ColumnDef", "GenericType"):
                for alternative in rules.get(rule, ()):
                    linked = related.get(alternative, ())
                    related[alternative] = (*linked, "postgres-text-declarations-v2.json")
            for rule in ("columnref", "target_el"):
                for alternative in rules.get(rule, ()):
                    related[alternative] = (*related.get(alternative, ()), "postgres-typed-columns-v2.json", "postgres-boolean-fields-v1.json", "postgres-int8-fields-v1.json")
            for rule in ("AexprConst", "Sconst", "c_expr"):
                for alternative in rules.get(rule, ()):
                    if "SCONST" in alternative or "Sconst" in alternative:
                        related[alternative] = (*related.get(alternative, ()), "postgres-text-literal-v1.json")
            for alternative in rules.get("a_expr", ()):
                if " COLLATE " in alternative:
                    related[alternative] = (*related.get(alternative, ()), "postgres-collated-text-v2.json")
                operator = alternative.removeprefix("a_expr: a_expr ").removesuffix(" a_expr")
                if operator in ("<", ">", "=", "LESS_EQUALS", "GREATER_EQUALS", "NOT_EQUALS", "qual_Op"):
                    related[alternative] = (*related.get(alternative, ()), "postgres-text-comparison-v2.json")
            parameters = ("c_expr", "a_expr")
            local = ("table_ref", "with_clause", "cte_list", "common_table_expr")
            compound = ("simple_select", "set_quantifier")
            create = ("CreateStmt", "ColumnDef", "ColConstraintElem")
            drop = ("DropStmt", "object_type_any_name")
            transaction = ("TransactionStmt", "TransactionStmtLegacy", "opt_transaction_chain")
            update = ("UpdateStmt", "set_clause_list", "set_clause")
            insert = ("InsertStmt", "insert_rest", "insert_column_list")
            selection = ("distinct_clause", "sort_clause", "sortby", "limit_clause", "offset_clause", "select_limit")
            for rule in ("from_clause", "from_list"):
                related.update({alternative: ("postgres-from-list-v2.json",) for alternative in rules.get(rule, ())})
            families = ("joined_table", "join_type", "join_qual", "join_using_alias", "where_clause")
        else:
            entry = "cmd"
            families = selection = insert = update = transaction = drop = compound = create = parameters = local = ()
        for rule in families:
            for alternative in rules.get(rule, []):
                if postgres and ("NATURAL" in alternative or "USING" in alternative or "join_using_alias:" in alternative):
                    related[alternative] = ("postgres-merged-join-v2.json", "postgres-full-merged-join-v1.json")
                elif not any(excluded in alternative for excluded in ("STRAIGHT", "NATURAL", "USING", "natural_join")):
                    related[alternative] = ("server-join-v2.json",)
                elif not postgres and ("NATURAL" in alternative or "USING" in alternative or "natural_join" in alternative):
                    related[alternative] = ("mysql-merged-join-v3.json",)
        for rule in selection:
            for alternative in rules.get(rule, []):
                related[alternative] = ("server-result-v2.json",)
                if postgres and rule in ("sort_clause", "sortby") and "USING" not in alternative:
                    related[alternative] = (*related[alternative], "postgres-output-order-names-v1.json", "postgres-source-order-v1.json")
                if postgres and rule != "distinct_clause" and "USING" not in alternative and "FETCH" not in alternative:
                    related[alternative] = (*related[alternative], "postgres-grouped-result-order-v1.json")
        for rule in insert:
            for alternative in rules.get(rule, []):
                related[alternative] = (("server-insert-v1.json", "postgres-insert-returning-v2.json")
                                        if rule == "InsertStmt" else ("server-insert-v1.json",))
        for rule in update:
            for alternative in rules.get(rule, []):
                related[alternative] = (("server-update-v2.json", "postgres-update-returning-v4.json")
                                        if rule == "UpdateStmt" else ("server-update-v2.json",))
        if postgres:
            mutations = ("returning_clause", "DeleteStmt", "InsertStmt", "insert_rest", "insert_column_list",
                         "UpdateStmt", "set_clause", "set_clause_list")
            for rule in mutations:
                for alternative in rules.get(rule, ()):
                    if alternative == rule + ":":
                        continue
                    if rule == "insert_rest" and alternative != "insert_rest: ( insert_column_list ) SelectStmt":
                        continue
                    if rule == "set_clause" and alternative != "set_clause: set_target = a_expr":
                        continue
                    related[alternative] = (*related.get(alternative, ()), "postgres-signed-write-widths-v1.json")
            for rule in ("a_expr", "b_expr"):
                comparison = {f"{rule}: {rule} {op} {rule}" for op in ("=", "<", ">", "LESS_EQUALS", "GREATER_EQUALS", "NOT_EQUALS", "qual_Op")}
                for alternative in rules.get(rule, ()):
                    if alternative in comparison:
                        related[alternative] = (*related.get(alternative, ()), "postgres-signed-comparison-widths-v1.json")
        for rule in transaction:
            for alternative in rules.get(rule, []):
                if not any(excluded in alternative for excluded in ("PREPARED", "PREPARE", "REPLICA", "SLAVE")):
                    related[alternative] = ("server-transaction-v1.json",)
        for rule in drop:
            for alternative in rules.get(rule, []):
                if any(related_token in alternative for related_token in ("table_or_tables", "object_type_any_name", ": TABLE")):
                    related[alternative] = ("server-drop-table-v3.json",)
                    if mysql and "opt_temporary" in alternative:
                        related[alternative] += ("mysql-session-drop-table-v1.json",)
        if postgres:
            view_removal = {
                "DropStmt: DROP object_type_any_name IF_P EXISTS any_name_list opt_drop_behavior",
                "DropStmt: DROP object_type_any_name any_name_list opt_drop_behavior",
                "object_type_any_name: VIEW",
                "object_type_any_name: MATERIALIZED VIEW",
                "opt_drop_behavior:",
                "opt_drop_behavior: CASCADE",
                "opt_drop_behavior: RESTRICT",
                "any_name_list: any_name",
                "any_name_list: any_name_list , any_name",
            }
            for rule in ("DropStmt", "object_type_any_name", "opt_drop_behavior", "any_name_list"):
                for alternative in rules.get(rule, []):
                    if alternative in view_removal:
                        related[alternative] = (*related.get(alternative, ()), "postgres-drop-view-v1.json")
        if postgres:
            index_removal = {
                "DropStmt: DROP object_type_any_name IF_P EXISTS any_name_list opt_drop_behavior",
                "DropStmt: DROP object_type_any_name any_name_list opt_drop_behavior",
                "DropStmt: DROP INDEX CONCURRENTLY any_name_list opt_drop_behavior",
                "DropStmt: DROP INDEX CONCURRENTLY IF_P EXISTS any_name_list opt_drop_behavior",
                "object_type_any_name: INDEX",
                "opt_drop_behavior:",
                "opt_drop_behavior: CASCADE",
                "opt_drop_behavior: RESTRICT",
                "any_name_list: any_name",
                "any_name_list: any_name_list , any_name",
            }
            for rule in ("DropStmt", "object_type_any_name", "opt_drop_behavior", "any_name_list"):
                for alternative in rules.get(rule, []):
                    if alternative in index_removal:
                        related[alternative] = (*related.get(alternative, ()), "postgres-drop-index-v1.json")
        if postgres:
            for rule in ("IndexStmt", "opt_unique", "opt_concurrently", "index_params", "opt_include",
                         "index_including_params", "opt_unique_null_treatment", "opt_asc_desc", "opt_nulls_order"):
                for alternative in rules.get(rule, ()):
                    related[alternative] = (*related.get(alternative, ()), "postgres-create-btree-v1.json")
            for alternative in rules.get("index_elem", ()):
                if alternative == "index_elem: ColId index_elem_options":
                    related[alternative] = (*related.get(alternative, ()), "postgres-create-btree-v1.json")
        if postgres:
            for rule in ("IndexStmt", "a_expr", "b_expr"):
                for alternative in rules.get(rule, ()):
                    if rule == "IndexStmt" or any(alternative.endswith(suffix) for suffix in ("IS NULL_P", "IS NOT NULL_P", "ISNULL", "NOTNULL")):
                        related[alternative] = (*related.get(alternative, ()), "postgres-partial-btree-v1.json")
        if postgres:
            boolean_filters = {
                "where_clause: WHERE a_expr",
                "a_expr: a_expr AND a_expr", "a_expr: a_expr OR a_expr",
                "a_expr: NOT a_expr", "c_expr: ( a_expr ) opt_indirection",
            }
            for rule in ("IndexStmt", "where_clause", "a_expr", "c_expr"):
                for alternative in rules.get(rule, ()):
                    if rule == "IndexStmt" or alternative in boolean_filters:
                        related[alternative] = (*related.get(alternative, ()), "postgres-boolean-index-predicate-v1.json")
        if postgres:
            truth_filters = {f"a_expr: a_expr IS {negated}{value}" for negated in ("", "NOT ")
                             for value in ("TRUE_P", "FALSE_P", "UNKNOWN")}
            for rule in ("IndexStmt", "a_expr"):
                for alternative in rules.get(rule, ()):
                    if rule == "IndexStmt" or alternative in truth_filters:
                        related[alternative] = (*related.get(alternative, ()), "postgres-index-truth-tests-v1.json")
        if postgres:
            native_column_predicates = {"c_expr: columnref", "columnref: ColId", "columnref: ColId indirection",
                                        "a_expr: a_expr AND a_expr", "a_expr: a_expr OR a_expr", "a_expr: NOT a_expr"}
            for rule in ("IndexStmt", "c_expr", "columnref", "a_expr"):
                for alternative in rules.get(rule, ()):
                    if rule == "IndexStmt" or alternative in native_column_predicates:
                        related[alternative] = (*related.get(alternative, ()), "postgres-index-boolean-values-v1.json")
        if postgres:
            rebuild = {
                "ReindexStmt: REINDEX opt_reindex_option_list reindex_target_relation opt_concurrently qualified_name",
                "reindex_target_relation: INDEX", "opt_reindex_option_list:",
                "opt_reindex_option_list: ( utility_option_list )",
            }
            for rule in ("ReindexStmt", "reindex_target_relation", "opt_reindex_option_list"):
                for alternative in rules.get(rule, ()):
                    if alternative in rebuild:
                        related[alternative] = (*related.get(alternative, ()), "postgres-reindex-index-v1.json")
                        if rule != "opt_reindex_option_list":
                            related[alternative] = (*related[alternative], "postgres-reindex-constraint-index-v1.json")
                            related[alternative] = (*related[alternative], "postgres-reindex-partition-index-v1.json")
                            related[alternative] = (*related[alternative], "postgres-reindex-exclusion-index-v2.json")
                    if alternative in (rebuild - {"reindex_target_relation: INDEX"}) | {"reindex_target_relation: TABLE"}:
                        related[alternative] = (*related.get(alternative, ()), "postgres-reindex-table-v3.json")
                        related[alternative] = (*related[alternative], "postgres-reindex-partition-table-v1.json")
                    if alternative in {"ReindexStmt: REINDEX opt_reindex_option_list SCHEMA opt_concurrently name",
                                       "opt_reindex_option_list:", "opt_reindex_option_list: ( utility_option_list )"}:
                        related[alternative] = (*related.get(alternative, ()), "postgres-reindex-schema-v2.json")
            for rule in ("ReindexStmt", "reindex_target_all", "opt_reindex_option_list", "opt_single_name"):
                for alternative in rules.get(rule, ()):
                    if alternative in {"ReindexStmt: REINDEX opt_reindex_option_list reindex_target_all opt_concurrently opt_single_name",
                                       "reindex_target_all: SYSTEM_P", "reindex_target_all: DATABASE",
                                       "opt_reindex_option_list:", "opt_reindex_option_list: ( utility_option_list )",
                                       "opt_single_name:", "opt_single_name: name"}:
                        related[alternative] = (*related.get(alternative, ()), "postgres-reindex-database-v2.json")
                        related[alternative] = (*related[alternative], "postgres-reindex-verbose-v1.json")
            for rule in ("ReindexStmt", "reindex_target_relation"):
                for alternative in rules.get(rule, ()):
                    if alternative in rebuild | {
                        "ReindexStmt: REINDEX opt_reindex_option_list SCHEMA opt_concurrently name",
                        "reindex_target_relation: TABLE",
                    }:
                        related[alternative] = (*related.get(alternative, ()), "postgres-reindex-verbose-v1.json")
        if postgres:
            for rule in ("ReindexStmt", "opt_reindex_option_list", "utility_option_elem", "CreateTableSpaceStmt"):
                for alternative in rules.get(rule, ()):
                    if rule in ("ReindexStmt", "CreateTableSpaceStmt") or alternative in (
                        "opt_reindex_option_list: ( utility_option_list )", "utility_option_elem: utility_option_name utility_option_arg",
                    ):
                        related[alternative] = (*related.get(alternative, ()), "postgres-reindex-tablespace-v2.json")
        if postgres:
            for rule in ("DropStmt", "drop_type_name", "name_list", "opt_drop_behavior"):
                for alternative in rules.get(rule, ()):
                    if alternative.startswith("DropStmt: DROP drop_type_name ") or alternative in (
                        "drop_type_name: SCHEMA", "name_list: name", "name_list: name_list , name",
                        "opt_drop_behavior: CASCADE", "opt_drop_behavior: RESTRICT",
                    ):
                        related[alternative] = (*related.get(alternative, ()), "postgres-drop-schema-v2.json")
            for rule in ("CreateSchemaStmt", "RoleSpec", "CreateRoleStmt", "CreateUserStmt", "CreateGroupStmt", "OptSchemaEltList"):
                for alternative in rules.get(rule, ()):
                    if rule != "OptSchemaEltList" or alternative == "OptSchemaEltList:":
                        related[alternative] = (*related.get(alternative, ()), "postgres-create-schema-v3.json")
            for rule in ("CreateSchemaStmt", "OptSchemaEltList", "schema_stmt", "CreateStmt"):
                for alternative in rules.get(rule, ()):
                    if rule == "CreateSchemaStmt" or alternative in {
                        "schema_stmt: CreateStmt", "OptSchemaEltList: OptSchemaEltList schema_stmt",
                    } or rule == "CreateStmt" and alternative.startswith("CreateStmt: CREATE OptTemp TABLE "):
                        related[alternative] = (*related.get(alternative, ()), "postgres-schema-tables-v1.json")
            for rule in ("RenameStmt", "AlterOwnerStmt", "RoleSpec"):
                for alternative in rules.get(rule, ()):
                    if rule == "RoleSpec" or alternative.startswith(rule + ": ALTER SCHEMA "):
                        related[alternative] = (*related.get(alternative, ()), "postgres-alter-schema-v1.json")
            for rule in ("CreateSeqStmt", "OptSeqOptList", "SeqOptList", "SeqOptElem", "OptTemp"):
                for alternative in rules.get(rule, ()):
                    if rule != "SeqOptElem" or alternative.split()[1] not in {"SEQUENCE", "LOGGED", "UNLOGGED"}:
                        related[alternative] = (*related.get(alternative, ()), "postgres-create-sequence-v3.json")
            for rule in ("CreateSeqStmt", "SeqOptElem", "any_name"):
                for alternative in rules.get(rule, ()):
                    if rule != "SeqOptElem" or alternative == "SeqOptElem: OWNED BY any_name":
                        related[alternative] = (*related.get(alternative, ()), "postgres-sequence-ownership-v3.json")
            for rule in ("CreateSchemaStmt", "OptSchemaEltList", "schema_stmt", "CreateSeqStmt"):
                for alternative in rules.get(rule, ()):
                    if rule in ("CreateSchemaStmt", "CreateSeqStmt") or alternative in {
                        "schema_stmt: CreateSeqStmt", "schema_stmt: CreateStmt", "OptSchemaEltList: OptSchemaEltList schema_stmt",
                    }:
                        related[alternative] = (*related.get(alternative, ()), "postgres-schema-sequences-v1.json")
            for rule in ("DropStmt", "drop_type_name", "any_name_list", "opt_drop_behavior"):
                for alternative in rules.get(rule, ()):
                    if rule in ("any_name_list", "opt_drop_behavior") or alternative.startswith("DropStmt: DROP drop_type_name ") \
                            or alternative == "drop_type_name: SEQUENCE":
                        related[alternative] = (*related.get(alternative, ()), "postgres-drop-sequence-v3.json")
            for alternative in rules.get("RenameStmt", ()):
                if alternative.startswith("RenameStmt: ALTER SEQUENCE "):
                    related[alternative] = (*related.get(alternative, ()), "postgres-rename-sequence-v1.json")
            for alternative in rules.get("AlterObjectSchemaStmt", ()):
                if alternative.startswith("AlterObjectSchemaStmt: ALTER SEQUENCE "):
                    related[alternative] = (*related.get(alternative, ()), "postgres-move-sequence-v1.json")
            for rule in ("AlterSeqStmt", "SeqOptList", "SeqOptElem"):
                for alternative in rules.get(rule, ()):
                    if alternative not in ("SeqOptElem: LOGGED", "SeqOptElem: UNLOGGED", "SeqOptElem: SEQUENCE NAME_P any_name"):
                        related[alternative] = (*related.get(alternative, ()), "postgres-reconfigure-sequence-v4.json")
        if postgres:
            index_catalog = {
                "qualified_name: ColId indirection",
                "indirection_el: . attr_name",
                "reindex_target_relation: INDEX",
                "reindex_target_relation: TABLE",
                "ReindexStmt: REINDEX opt_reindex_option_list reindex_target_relation opt_concurrently qualified_name",
            }
            for rule in ("qualified_name", "indirection_el", "IndexStmt", "DropStmt", "ReindexStmt", "reindex_target_relation"):
                for alternative in rules.get(rule, ()):
                    if rule == "IndexStmt" or alternative.startswith("DropStmt: DROP INDEX ") or alternative in index_catalog:
                        related[alternative] = (*related.get(alternative, ()), "postgres-index-catalog-binding-v1.json")
        if postgres:
            for rule in ("qualified_name", "columnref", "indirection_el"):
                for alternative in rules.get(rule, ()):
                    if alternative in {"qualified_name: ColId indirection", "columnref: ColId indirection",
                                       "indirection_el: . attr_name", "indirection_el: . *"}:
                        related[alternative] = (*related.get(alternative, ()), "postgres-local-catalog-binding-v1.json")
        if postgres:
            values_signatures = {
                "simple_select: values_clause",
                "values_clause: VALUES ( expr_list )",
                "values_clause: values_clause , ( expr_list )",
            }
            for rule in ("simple_select", "values_clause"):
                for alternative in rules.get(rule, []):
                    if alternative in values_signatures:
                        related[alternative] = (*related.get(alternative, ()), "postgres-values-v3.json")
        if postgres:
            for rule in ("common_table_expr", "with_clause", "table_ref", "simple_select"):
                for alternative in rules.get(rule, []):
                    if rule == "common_table_expr" or alternative in {
                        "with_clause: WITH cte_list", "with_clause: WITH_LA cte_list",
                        "table_ref: select_with_parens opt_alias_clause", "simple_select: values_clause",
                    }:
                        related[alternative] = (*related.get(alternative, ()), "postgres-local-values-v2.json")
                        if alternative == "simple_select: values_clause":
                            related[alternative] = (*related[alternative], "postgres-values-compound-v1.json")
        if postgres:
            for rule in ("ViewStmt", "opt_check_option", "opt_reloptions"):
                for alternative in rules.get(rule, []):
                    if "RECURSIVE" not in alternative:
                        related[alternative] = (*related.get(alternative, ()), "postgres-create-view-v2.json")
        if postgres:
            for rule in ("CreateMatViewStmt", "create_mv_target", "RefreshMatViewStmt", "opt_with_data", "OptNoLog", "opt_concurrently"):
                for alternative in rules.get(rule, []):
                    if alternative not in {"OptNoLog: UNLOGGED", "opt_concurrently: CONCURRENTLY"}:
                        related[alternative] = (*related.get(alternative, ()), "postgres-materialized-view-v1.json")
        if postgres:
            for rule in ("CreateAsStmt", "create_as_target", "OptTemp", "opt_with_data"):
                for alternative in rules.get(rule, []):
                    if rule != "OptTemp" or alternative in {"OptTemp:", "OptTemp: UNLOGGED"}:
                        related[alternative] = (*related.get(alternative, ()), "postgres-create-table-as-v1.json")
        if postgres:
            for alternative in rules.get("RenameStmt", []):
                if alternative.startswith("RenameStmt: ALTER TABLE ") and "CONSTRAINT" not in alternative:
                    related[alternative] = (*related.get(alternative, ()), "postgres-table-rename-v2.json")
        if postgres:
            for rule in ("TruncateStmt", "opt_restart_seqs", "relation_expr_list"):
                for alternative in rules.get(rule, []):
                    related[alternative] = (*related.get(alternative, ()), "postgres-truncate-v1.json")
        for rule in compound:
            for alternative in rules.get(rule, []):
                if any(operator in alternative for operator in ("UNION", "INTERSECT", "EXCEPT", "DISTINCT", "ALL")):
                    related[alternative] = ("server-compound-v2.json",)
                    if postgres and rule == "simple_select":
                        related[alternative] = (*related[alternative], "postgres-values-compound-v1.json")
        for rule in create:
            for alternative in rules.get(rule, []):
                related[alternative] = (("server-create-table-v2.json", "postgres-create-text-v1.json",
                                         "postgres-create-signed-defaults-v1.json", "postgres-create-boolean-v2.json") if postgres else ("server-create-table-v2.json",))
        for rule in parameters:
            for alternative in rules.get(rule, []):
                if any(token in alternative for token in ("PARAM", "param_marker", "CAST", "TYPECAST", "SIGNED")):
                    related[alternative] = (*related.get(alternative, ()), "server-parameter-v6.json")
                    if postgres:
                        related[alternative] = (*related[alternative], "postgres-typed-parameters-v2.json")
        local_signatures = {
            "table_factor: select_derived_init get_select_lex select_derived2",
            "table_factor: ( get_select_lex select_derived_union ) opt_table_alias",
            "table_factor: ( select_derived_union ) opt_table_alias",
            "table_factor: derived_table",
            "derived_table: table_subquery opt_table_alias opt_derived_column_list",
            "table_ref: select_with_parens opt_alias_clause",
            "with_clause: WITH with_list",
            "with_clause: WITH cte_list",
            "with_clause: WITH_LA cte_list",
            "with_list: common_table_expr",
            "cte_list: common_table_expr",
        }
        for rule in local:
            for alternative in rules.get(rule, []):
                if alternative in local_signatures or rule == "common_table_expr":
                    related[alternative] = (*related.get(alternative, ()), "server-local-projection-v4.json")
                    if mysql:
                        related[alternative] = (*related[alternative], "mysql-local-varchar-v2.json")
        signatures = {alternative for alternatives in rules.values() for alternative in alternatives}
        if mysql:
            for rule in ("rename", "table_to_table", "table_to_table_list"):
                for alternative in rules.get(rule, []):
                    if rule != "rename" or "table_to_table_list" in alternative:
                        related[alternative] = (*related.get(alternative, ()), "mysql-ordered-table-rename-v2.json")
            for rule in ("truncate", "truncate_stmt", "opt_table_sym", "opt_table"):
                for alternative in rules.get(rule, []):
                    related[alternative] = (*related.get(alternative, ()), "mysql-truncate-v2.json")
            declaration_roles = {
                "opt_temporary: TEMPORARY",
                "opt_if_not_exists: IF not EXISTS",
                "create_table_stmt: CREATE opt_temporary TABLE_SYM opt_if_not_exists table_ident ( table_element_list ) opt_create_table_options_etc",
                "create2: ( create2a",
                "create2a: create_field_list ) opt_create_table_options opt_create_partitioning create3",
            }
            for alternative in signatures & declaration_roles:
                related[alternative] = (*related.get(alternative, ()), "mysql-session-create-table-v1.json")
            checked_declared_query = {
                "create_table_stmt: CREATE opt_temporary TABLE_SYM opt_if_not_exists table_ident ( table_element_list ) opt_create_table_options_etc",
                "create2a: create_field_list ) opt_create_table_options opt_create_partitioning create3",
            }
            for alternative in signatures & checked_declared_query:
                related[alternative] = (*related.get(alternative, ()), "mysql-create-table-as-declarations-v1.json")
            for alternative in signatures & {
                "create2: LIKE table_ident",
                "create2: ( LIKE table_ident )",
                "create_table_stmt: CREATE opt_temporary TABLE_SYM opt_if_not_exists table_ident LIKE table_ident",
                "create_table_stmt: CREATE opt_temporary TABLE_SYM opt_if_not_exists table_ident ( LIKE table_ident )",
            }:
                related[alternative] = (*related.get(alternative, ()), "mysql-create-like-v1.json")
            query_backed = {
                "create2: opt_create_table_options opt_create_partitioning create3",
                "create3: opt_duplicate opt_as create_select union_clause",
                "create3: opt_duplicate opt_as create_select opt_union_clause",
                "create_select: SELECT_SYM select_options select_item_list opt_select_from",
                "create_select: SELECT_SYM select_options select_item_list table_expression",
                "create_table_stmt: CREATE opt_temporary TABLE_SYM opt_if_not_exists table_ident opt_create_table_options_etc",
                "opt_duplicate_as_qe: as_create_query_expression",
                "as_create_query_expression: AS query_expression_with_opt_locking_clauses",
            }
            for alternative in signatures & query_backed:
                related[alternative] = (*related.get(alternative, ()), "mysql-create-table-as-v1.json", "mysql-create-table-as-source-v1.json", "mysql-create-table-as-varchar-v1.json")
        if mysql:
            declarations = {
                "field_spec: field_ident type opt_attribute",
                "field_def: type opt_column_attribute_list",
                "type: varchar field_length opt_binary",
                "type: varchar field_length opt_charset_with_opt_binary",
                "varchar: VARCHAR",
                "varchar: VARCHAR_SYM",
                "create_table_option: default_charset",
                "create_table_option: default_collation",
                *(role + ": " + form for role, default in (("attribute", "DEFAULT"), ("column_attribute", "DEFAULT_SYM"))
                  for form in ("NULL_SYM", "not NULL_SYM", "COLLATE_SYM collation_name", default + " now_or_signed_literal")),
            }
            for alternative in sorted(signatures & declarations):
                related[alternative] = (*related.get(alternative, ()), "mysql-create-varchar-v1.json")
            signed_storage = {
                "type: int_type opt_field_length field_options",
                "int_type: INT_SYM",
                *("int_type: " + kind + suffix for kind in ("TINYINT", "SMALLINT", "MEDIUMINT", "BIGINT") for suffix in ("", "_SYM")),
            }
            for alternative in sorted(signatures & signed_storage):
                related[alternative] = (*related.get(alternative, ()), "mysql-create-signed-v2.json")
            primary_columns = {
                "opt_primary: PRIMARY_SYM",
                "constraint_key_type: PRIMARY_SYM KEY_SYM",
                "key_part: ident",
                "key_part: ident opt_ordering_direction",
            }
            for alternative in sorted(signatures & primary_columns):
                related[alternative] = (*related.get(alternative, ()), "mysql-create-primary-v2.json")
        for alternative in sorted(signatures):
            if (alternative.startswith(("insert_stmt:", "insert_query_expression:")) and "insert_query_expression" in alternative.split()
                    or alternative == "insert_rest: ( insert_column_list ) SelectStmt"):
                related[alternative] = (*related.get(alternative, ()), "server-insert-query-v3.json")
        wildcard_signatures = {
            "select_item: table_wild",
            "select_item_list: *",
            "target_list: *",
            "target_el: *",
            "selcollist: sclp STAR",
            "selcollist: sclp nm DOT STAR",
        }
        for alternative in sorted(wildcard_signatures & signatures):
            related[alternative] = (*related.get(alternative, ()), "wildcard-projection-v2.json")
        grouped_signatures = {
            "opt_group_clause: GROUP_SYM BY group_list olap_opt",
            "opt_group_clause: GROUP_SYM BY group_list",
            "group_clause: GROUP_P BY set_quantifier group_by_list",
            "group_by_item: a_expr",
        }
        for alternative in sorted(grouped_signatures & signatures):
            related[alternative] = (*related.get(alternative, ()), "server-grouped-v3.json")
        derived_join_signatures = {
            "table_factor: derived_table",
            "derived_table: table_subquery opt_table_alias opt_derived_column_list",
            "table_ref: select_with_parens opt_alias_clause",
            "table_factor: select_derived_init get_select_lex select_derived2",
            "table_factor: ( get_select_lex select_derived_union ) opt_table_alias",
            "table_factor: ( select_derived_union ) opt_table_alias",
        }
        for alternative in sorted(derived_join_signatures & signatures):
            related[alternative] = (*related.get(alternative, ()), "server-derived-join-v2.json")
        truth_signatures = {
            "expr: expr or expr", "expr: expr XOR expr", "expr: expr and expr", "expr: NOT_SYM expr",
            "bool_pri: bool_pri IS NULL_SYM", "bool_pri: bool_pri IS not NULL_SYM",
            "a_expr: a_expr AND a_expr", "a_expr: a_expr OR a_expr", "a_expr: NOT a_expr",
            "a_expr: a_expr IS NULL_P", "a_expr: a_expr IS NOT NULL_P", "a_expr: a_expr ISNULL", "a_expr: a_expr NOTNULL",
        }
        for alternative in sorted(truth_signatures & signatures):
            related[alternative] = (*related.get(alternative, ()), "server-truth-v2.json")
        for alternative in ("with_clause: WITH RECURSIVE_SYM with_list", "with_clause: WITH RECURSIVE cte_list"):
            if alternative in signatures:
                related[alternative] = (*related.get(alternative, ()), "server-recursive-series-v2.json")
        if not all(alternative in signatures and all(contract in CONTRACTS for contract in contracts)
                   for alternative, contracts in related.items()):
            raise ValueError(f"Unknown production or domain in scope correspondence: {path}")
        count = sum(map(len, rules.values()))
        artifacts[path] = {
            "sha256": record["sha256"],
            "nonemptyAlternatives": record["alternatives"],
            "emptyAlternatives": count - record["alternatives"],
            "totalAlternatives": count,
            "rules": rules,
            "relatedDomains": {alternative: list(contracts) for alternative, contracts in related.items()},
            "statementContexts": statement_contexts(rules, related, entry),
        }
    return {
        "requirementsDigest": BASELINE, "artifacts": artifacts,
        "totalAlternatives": sum(record["totalAlternatives"] for record in artifacts.values()),
        "alternativesWithRelatedDomain": sum(record["statementContexts"]["alternativesWithRelatedDomain"] for record in artifacts.values()),
        "emptyAlternatives": sum(record["emptyAlternatives"] for record in artifacts.values()),
        "classification": "Each rule and alternative requires semantic classification. Related domain records exercise part of the named production family.",
    }
