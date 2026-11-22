"""Read the frozen production lists as data and expose unresolved semantic classification."""

import hashlib
import json
import re

from scope import CONTRACTS, TOOL

BASELINE = "df59b1f5e920748a19a5bca3df5e93dfa38bdbafcad6617a464c5b533624a898"
RULE = re.compile(r"^    '([A-Za-z_][A-Za-z0-9_]*)' => \[$")
ALTERNATIVE = re.compile(r"^        '([^'\\]*)',$")
SQLITE = "packages/sql-semantics-sqlite/resources/productions/sqlite-3.47.2.php"
RELATED_SQLITE_DOMAINS = {
    "expr: CAST LP expr AS typetoken RP": ("sqlite-cast-v1.json",),
    "expr: CASE case_operand case_exprlist case_else END": ("sqlite-case-v1.json",),
    "case_exprlist: case_exprlist WHEN expr THEN expr": ("sqlite-case-v1.json",),
    "case_exprlist: WHEN expr THEN expr": ("sqlite-case-v1.json",),
    "case_else: ELSE expr": ("sqlite-case-v1.json",),
    "case_else:": ("sqlite-case-v1.json",),
    "case_operand: expr": ("sqlite-case-v1.json",),
    "case_operand:": ("sqlite-case-v1.json",),
    "groupby_opt: GROUP BY nexprlist": ("sqlite-grouped-v1.json", "sqlite-group-filter-v1.json"),
    "having_opt: HAVING expr": ("sqlite-group-filter-v1.json",),
    "where_opt: WHERE expr": ("sqlite-relational-v1.json", "sqlite-group-filter-v1.json"),
    "selectnowith: selectnowith multiselect_op oneselect": ("sqlite-compound-v1.json", "sqlite-recursive-source-v1.json"),
    "term: NULL|FLOAT|BLOB": ("sqlite-scalars-v1.json",),
    "term: STRING": ("sqlite-scalars-v1.json",),
    "term: INTEGER": ("sqlite-scalars-v1.json",),
    "expr: expr AND expr": ("sqlite-scalars-v1.json",),
    "expr: expr OR expr": ("sqlite-scalars-v1.json",),
    "expr: expr STAR|SLASH|REM expr": ("sqlite-scalars-v1.json", "sqlite-numeric-operators-v1.json"),
    "expr: expr BITAND|BITOR|LSHIFT|RSHIFT expr": ("sqlite-numeric-operators-v1.json",),
    "expr: NOT expr": ("sqlite-unary-v1.json",),
    "expr: BITNOT expr": ("sqlite-unary-v1.json",),
    "expr: PLUS|MINUS expr": ("sqlite-unary-v1.json",),
    "expr: expr ISNULL|NOTNULL": ("sqlite-unary-v1.json",),
    "expr: expr NOT NULL": ("sqlite-unary-v1.json",),
    "expr: expr LT|GT|GE|LE expr": ("sqlite-scalars-v1.json",),
    "expr: expr EQ|NE expr": ("sqlite-scalars-v1.json",),
    "expr: idj": ("sqlite-truth-v2.json",),
    "expr: expr IS expr": ("sqlite-scalars-v1.json", "sqlite-truth-v2.json"),
    "expr: expr IS NOT expr": ("sqlite-scalars-v1.json", "sqlite-truth-v2.json"),
    "expr: expr IS NOT DISTINCT FROM expr": ("sqlite-scalars-v1.json", "sqlite-truth-v2.json"),
    "expr: expr IS DISTINCT FROM expr": ("sqlite-scalars-v1.json", "sqlite-truth-v2.json"),
    "cmd: select": ("contract-v1.json", "sqlite-relational-v1.json", "sqlite-result-v1.json",
                    "sqlite-values-v1.json", "sqlite-local-source-v1.json", "sqlite-recursive-source-v1.json"),
    "cmd: with DELETE FROM xfullname indexed_opt where_opt_ret": ("sqlite-delete-v1.json", "sqlite-delete-returning-v1.json"),
    "cmd: with insert_cmd INTO xfullname idlist_opt select upsert": (
        "sqlite-insert-v1.json", "sqlite-insert-query-v1.json", "sqlite-insert-input-v2.json", "sqlite-insert-returning-v1.json"),
    "cmd: with insert_cmd INTO xfullname idlist_opt DEFAULT VALUES returning": ("sqlite-insert-input-v2.json", "sqlite-insert-returning-v1.json"),
    "cmd: with UPDATE orconf xfullname indexed_opt SET setlist from where_opt_ret": ("sqlite-update-v1.json", "sqlite-update-returning-v1.json"),
    "cmd: DROP TABLE ifexists fullname": ("sqlite-drop-table-v1.json",),
    "cmd: create_table create_table_args": ("sqlite-create-table-v1.json", "sqlite-defaults-v1.json"),
    "ccons: DEFAULT scantok term": ("sqlite-defaults-v1.json",),
    "ccons: DEFAULT LP expr RP": ("sqlite-defaults-v1.json",),
    "ccons: DEFAULT PLUS scantok term": ("sqlite-defaults-v1.json",),
    "ccons: DEFAULT MINUS scantok term": ("sqlite-defaults-v1.json",),
    "ccons: DEFAULT scantok id": ("sqlite-defaults-v1.json",),
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
        rules = parse(root, path, record)
        related = RELATED_SQLITE_DOMAINS if path == SQLITE else {}
        if "sql-semantics-mysql/resources/" in path:
            related = {alternative: ("server-delete-v2.json",) for alternative in rules.get("delete_stmt", [])}
            parameters = ("param_marker", "simple_expr", "cast_type")
            local = ("table_factor", "derived_table", "with_clause", "with_list", "common_table_expr")
            compound = ("union_list", "union_clause", "query_expression_body")
            create = ("create_table_stmt", "create_table", "column_def", "field_def", "column_attribute")
            drop = ("drop_table_stmt", "drop")
            transaction = ("begin", "start", "commit", "rollback", "savepoint", "release", "opt_chain", "opt_release")
            update = ("update", "update_stmt", "update_list", "update_elem")
            insert = ("insert", "insert_stmt", "insert_values", "insert_from_constructor", "insert_from_constructor_opt", "values_list")
            selection = ("order_clause", "opt_order_clause", "limit_clause", "opt_limit_clause", "select_options", "select_option")
            families = ("join_table", "normal_join", "joined_table", "inner_join_type", "outer_join_type", "where_clause")
        elif "sql-semantics-postgres/resources/" in path:
            related = {alternative: ("server-delete-v2.json",) for alternative in rules.get("DeleteStmt", [])}
            parameters = ("c_expr", "a_expr")
            local = ("table_ref", "with_clause", "cte_list", "common_table_expr")
            compound = ("simple_select", "set_quantifier")
            create = ("CreateStmt", "ColumnDef", "ColConstraintElem")
            drop = ("DropStmt", "object_type_any_name")
            transaction = ("TransactionStmt", "TransactionStmtLegacy", "opt_transaction_chain")
            update = ("UpdateStmt", "set_clause_list", "set_clause")
            insert = ("InsertStmt", "insert_rest", "insert_column_list")
            selection = ("distinct_clause", "sort_clause", "sortby", "limit_clause", "offset_clause", "select_limit")
            families = ("joined_table", "join_type", "join_qual", "where_clause")
        else:
            families = selection = insert = update = transaction = drop = compound = create = parameters = local = ()
        for rule in families:
            for alternative in rules.get(rule, []):
                if not any(excluded in alternative for excluded in ("STRAIGHT", "NATURAL", "USING", "natural_join")):
                    related[alternative] = ("server-join-v1.json",)
        for rule in selection:
            for alternative in rules.get(rule, []):
                related[alternative] = ("server-result-v1.json",)
        for rule in insert:
            for alternative in rules.get(rule, []):
                related[alternative] = ("server-insert-v1.json",)
        for rule in update:
            for alternative in rules.get(rule, []):
                related[alternative] = ("server-update-v2.json",)
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
                    related[alternative] = ("server-parameter-v1.json",)
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
        }
    return {
        "requirementsDigest": BASELINE, "artifacts": artifacts,
        "totalAlternatives": sum(record["totalAlternatives"] for record in artifacts.values()),
        "emptyAlternatives": sum(record["emptyAlternatives"] for record in artifacts.values()),
        "classification": "Each rule and alternative requires semantic classification. Related domain records exercise part of the named production family.",
    }
