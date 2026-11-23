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
    "expr: CAST LP expr AS typetoken RP": ("sqlite-cast-v1.json",),
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
    "expr: NOT expr": ("sqlite-unary-v1.json",),
    "expr: BITNOT expr": ("sqlite-unary-v1.json",),
    "expr: PLUS|MINUS expr": ("sqlite-unary-v1.json",),
    "expr: expr ISNULL|NOTNULL": ("sqlite-unary-v1.json",),
    "expr: expr in_op LP exprlist RP": ("sqlite-membership-v1.json", "sqlite-folded-membership-v1.json"),
    "expr: expr CONCAT expr": ("sqlite-concatenation-v1.json",),
    "expr: expr COLLATE ids": ("sqlite-collation-v1.json",),
    "expr: idj LP distinct exprlist RP": ("sqlite-coalesce-v1.json",),
    "expr: expr between_op expr AND expr": ("sqlite-between-v1.json",),
    "expr: expr NOT NULL": ("sqlite-unary-v1.json",),
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
    "cmd: DROP TABLE ifexists fullname": ("sqlite-drop-table-v1.json",),
    "cmd: ALTER TABLE fullname RENAME TO nm": ("sqlite-rename-v4.json",),
    "cmd: ALTER TABLE fullname RENAME kwcolumn_opt nm TO nm": ("sqlite-rename-v4.json",),
    "cmd: create_table create_table_args": ("sqlite-create-table-v3.json", "sqlite-defaults-v3.json", "sqlite-not-null-conflict-v2.json", "sqlite-strict-tables-v2.json", "sqlite-primary-keys-v2.json", "sqlite-unique-keys-v1.json", "sqlite-create-as-v5.json", "sqlite-named-checks-v4.json", "sqlite-generated-columns-v2.json"),
    "generated: LP expr RP ID": ("sqlite-generated-columns-v2.json",),
    "generated: LP expr RP": ("sqlite-generated-columns-v2.json",),
    "ccons: AS generated": ("sqlite-generated-columns-v2.json",),
    "ccons: GENERATED ALWAYS AS generated": ("sqlite-generated-columns-v2.json",),
    "ccons: CONSTRAINT nm": ("sqlite-named-checks-v4.json",),
    "ccons: CHECK LP expr RP": ("sqlite-named-checks-v4.json", "sqlite-folded-checks-v1.json", "sqlite-implicit-checks-v1.json"),
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
    "ccons: DEFAULT scantok term": ("sqlite-defaults-v3.json",),
    "ccons: DEFAULT LP expr RP": ("sqlite-defaults-v3.json",),
    "ccons: DEFAULT PLUS scantok term": ("sqlite-defaults-v3.json",),
    "ccons: DEFAULT MINUS scantok term": ("sqlite-defaults-v3.json",),
    "ccons: DEFAULT scantok id": ("sqlite-defaults-v3.json",),
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
        postgres = "sql-semantics-postgres/resources/" in path
        rules = parse(root, path, record)
        related = RELATED_SQLITE_DOMAINS if path == SQLITE else {}
        if "sql-semantics-mysql/resources/" in path:
            entry = "simple_statement" if "simple_statement" in rules else "statement"
            related = {alternative: ("server-delete-v2.json",) for alternative in rules.get("delete_stmt", [])}
            related.update({alternative: ("mysql-signed-quotient-v1.json",) for alternative in (
                "bit_expr: bit_expr % bit_expr", "bit_expr: bit_expr DIV_SYM bit_expr", "bit_expr: bit_expr MOD_SYM bit_expr",
            )})
            related.update({alternative: ("mysql-signed-range-v3.json",) for alternative in rules["predicate"]
                            if " BETWEEN_SYM " in alternative})
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
            families = ("join_table", "normal_join", "joined_table", "inner_join_type", "outer_join_type", "where_clause")
        elif postgres:
            entry = "stmt"
            related = {alternative: ("server-delete-v2.json", "postgres-delete-returning-v1.json") for alternative in rules.get("DeleteStmt", [])}
            related["returning_clause: RETURNING target_list"] = ("postgres-delete-returning-v1.json", "postgres-update-returning-v2.json",
                                                                  "postgres-insert-returning-v1.json")
            related.update({alternative: ("postgres-int4-operators-v2.json",) for alternative in (
                "a_expr: a_expr / a_expr", "a_expr: a_expr % a_expr", "a_expr: a_expr qual_Op a_expr",
                "b_expr: b_expr / b_expr", "b_expr: b_expr % b_expr", "b_expr: b_expr qual_Op b_expr",
            )})
            related.update({alternative: ("postgres-int4-case-v1.json",) for alternative in (
                "c_expr: case_expr", "case_expr: CASE case_arg when_clause_list case_default END_P",
                "when_clause_list: when_clause", "when_clause_list: when_clause_list when_clause",
                "when_clause: WHEN a_expr THEN a_expr", "case_default: ELSE a_expr", "case_default:", "case_arg:",
            )})
            related.update({alternative: ("postgres-int4-range-v1.json",) for alternative in rules["a_expr"]
                            if " BETWEEN " in alternative})
            related.update({alternative: ("postgres-int4-distinctness-v1.json",) for rule in ("a_expr", "b_expr")
                            for alternative in rules[rule] if " DISTINCT FROM " in alternative})
            related.update({alternative: ("postgres-boolean-tests-v1.json",) for alternative in rules["a_expr"]
                            if any(alternative.endswith(" " + mode) for mode in ("TRUE_P", "FALSE_P", "UNKNOWN"))
                            and " IS " in alternative})
            related.update({alternative: ("postgres-builtin-casts-v3.json",) for alternative in (
                "a_expr: a_expr TYPECAST Typename", "b_expr: b_expr TYPECAST Typename",
                "func_expr_common_subexpr: CAST ( a_expr AS Typename )",
            )})
            parameters = ("c_expr", "a_expr")
            local = ("table_ref", "with_clause", "cte_list", "common_table_expr")
            compound = ("simple_select", "set_quantifier")
            create = ("CreateStmt", "ColumnDef", "ColConstraintElem")
            drop = ("DropStmt", "object_type_any_name")
            transaction = ("TransactionStmt", "TransactionStmtLegacy", "opt_transaction_chain")
            update = ("UpdateStmt", "set_clause_list", "set_clause")
            insert = ("InsertStmt", "insert_rest", "insert_column_list")
            selection = ("distinct_clause", "sort_clause", "sortby", "limit_clause", "offset_clause", "select_limit")
            families = ("joined_table", "join_type", "join_qual", "join_using_alias", "where_clause")
        else:
            entry = "cmd"
            families = selection = insert = update = transaction = drop = compound = create = parameters = local = ()
        for rule in families:
            for alternative in rules.get(rule, []):
                if postgres and ("NATURAL" in alternative or "USING" in alternative or "join_using_alias:" in alternative):
                    related[alternative] = ("postgres-merged-join-v1.json",)
                elif not any(excluded in alternative for excluded in ("STRAIGHT", "NATURAL", "USING", "natural_join")):
                    related[alternative] = ("server-join-v2.json",)
                elif not postgres and ("NATURAL" in alternative or "USING" in alternative or "natural_join" in alternative):
                    related[alternative] = ("mysql-merged-join-v1.json",)
        for rule in selection:
            for alternative in rules.get(rule, []):
                related[alternative] = ("server-result-v1.json",)
        for rule in insert:
            for alternative in rules.get(rule, []):
                related[alternative] = (("server-insert-v1.json", "postgres-insert-returning-v1.json")
                                        if rule == "InsertStmt" else ("server-insert-v1.json",))
        for rule in update:
            for alternative in rules.get(rule, []):
                related[alternative] = (("server-update-v2.json", "postgres-update-returning-v2.json")
                                        if rule == "UpdateStmt" else ("server-update-v2.json",))
        for rule in transaction:
            for alternative in rules.get(rule, []):
                if not any(excluded in alternative for excluded in ("PREPARED", "PREPARE", "REPLICA", "SLAVE")):
                    related[alternative] = ("server-transaction-v1.json",)
        for rule in drop:
            for alternative in rules.get(rule, []):
                if any(related_token in alternative for related_token in ("table_or_tables", "object_type_any_name", ": TABLE")):
                    related[alternative] = ("server-drop-table-v1.json",)
        for rule in compound:
            for alternative in rules.get(rule, []):
                if any(operator in alternative for operator in ("UNION", "INTERSECT", "EXCEPT", "DISTINCT", "ALL")):
                    related[alternative] = ("server-compound-v2.json",)
        for rule in create:
            for alternative in rules.get(rule, []):
                related[alternative] = ("server-create-table-v1.json",)
        for rule in parameters:
            for alternative in rules.get(rule, []):
                if any(token in alternative for token in ("PARAM", "param_marker", "CAST", "TYPECAST", "SIGNED")):
                    related[alternative] = (*related.get(alternative, ()), "server-parameter-v5.json")
                    if postgres:
                        related[alternative] = (*related[alternative], "postgres-typed-parameters-v1.json")
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
        signatures = {alternative for alternatives in rules.values() for alternative in alternatives}
        for alternative in sorted(signatures):
            if (alternative.startswith(("insert_stmt:", "insert_query_expression:")) and "insert_query_expression" in alternative.split()
                    or alternative == "insert_rest: ( insert_column_list ) SelectStmt"):
                related[alternative] = (*related.get(alternative, ()), "server-insert-query-v2.json")
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
