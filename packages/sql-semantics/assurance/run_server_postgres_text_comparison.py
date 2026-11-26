"""Compare native PostgreSQL text predicates, filters and physical effects."""

from collections import Counter
from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import check_value as check_select, main
from run_server_postgres_collated_text import PROFILE_PROBE
from run_server_postgres_text_returning import (
    COLUMN_TYPES, DECLARATIONS, HEADING, INITIAL, OBSERVER, TABLE, check_preparation as check_fixture,
    check_value as check_mutation, expected as expected_mutation, int4, mutation, observation,
    prepare as prepare_fixture,
)
from run_server_postgres_typed_columns import DIAGNOSTICS
from server_compound_cases import case as compound, without_profiles
from server_grouped_cases import aggregate, case as grouped
from server_join_cases import case, join, slot
from server_truth_model import admits, predicate_value
from sqlite_domain import RenderFault

LABEL = "postgres-text-comparison"
CONTRACT = "9472050feb9e35dbdd2464c07962d258ece36e17ab76fbab9a09ce38abc51668"
INPUT = "ir_text_inputs"
INPUT_HEADING = ("note", "id", "label")
INPUT_ROWS = [["other", 7, "copied α"], ["other", 7, "copied α"], ["memo", 8, None], ["blank", 9, ""], [None, 10, "東京"]]
DATA = {TABLE: (HEADING, INITIAL), INPUT: (INPUT_HEADING, INPUT_ROWS)}
KINDS = dict(zip(HEADING, COLUMN_TYPES))
COLLATE = 'COLLATE pg_catalog."C"'
PROFILE_SQL = (
    "SELECT o.oprname::text AS operator, p.proisstrict AS strict, o.oprresult::integer AS result "
    "FROM pg_catalog.pg_operator o JOIN pg_catalog.pg_proc p ON p.oid = o.oprcode "
    "JOIN pg_catalog.pg_namespace n ON n.oid = o.oprnamespace "
    "WHERE n.nspname = 'pg_catalog' AND o.oprleft = 25 AND o.oprright = 25 "
    "AND o.oprname IN ('=', '<>', '<', '<=', '>', '>=') "
    'ORDER BY o.oprname::text COLLATE pg_catalog."C"'
)


def specifications():
    path, profile = TOOL / "postgres-text-comparison-v2.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "postgres-text-comparison-contract-mismatch")
    specification = json.loads(path.read_text())
    previous = specification["previous"]
    require(digest(TOOL / previous["path"]) == previous["sha256"], "postgres-text-comparison-previous-contract-mismatch")
    return specification, {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def prepare(engine, db):
    return {"fixture": prepare_fixture(engine, db), "operators": db.request({"sql": PROFILE_SQL, "observeReadOnly": True}),
            "collation": db.request({"sql": PROFILE_PROBE, "observeReadOnly": True})}


def check_preparation(engine, environment, prepared):
    check_fixture(engine, environment, prepared["fixture"])
    same({"status": "rows", "fields": [["oid", "int4"], ["deterministic", "bool"], ["default_name", "text"]],
          "rows": [[950, True, "en_US.utf8"]]}, prepared["collation"])
    same(observation({"rows": INPUT_ROWS})["rows"], observation({"rows": environment["input"]})["rows"])
    same({"status": "rows", "fields": [["operator", "text"], ["strict", "bool"], ["result", "int4"]],
          "rows": [[op, True, 16] for op in ("<", "<=", "<>", "=", ">", ">=")]}, prepared["operators"])


def observer_request(item, sql):
    if "target" in item:
        return {"sql": sql, "collatedComparisons": True}
    return {"sql": sql, "observeReadOnly": True}


def table(name=TABLE, heading=None):
    layout = DATA[name][0] if heading is None else heading
    return {"schema": "public", "table": name, "columns": list(layout), "columnKinds": [KINDS[column] for column in layout], "only": False}


def text(value):
    return ["text", value]


def field(index, occurrence=0):
    return ["text-slot", index, occurrence]


def comparison(operator, left, right):
    return ["text-comparison", operator, "C", left, right]


def select(tag, source, columns, where=None):
    return {**case("postgres", tag, source, columns, where), "orderBy": [], "limit": None, "distinct": False}


def place(tag, context, term, join_kind="LEFT", layout=None, right_layout=None):
    source = table(heading=layout)
    heading = source["columns"]
    if context == "result":
        return select(tag, source, [[term, "matches"]])
    if context == "where":
        return select(tag, source, [[field(heading.index("label")), "value"]], term)
    if context == "on":
        rhs = table(INPUT, right_layout)
        source = join(join_kind, source, rhs, term)
        return select(tag, source, [[field(heading.index("label")), "left_text"], [field(rhs["columns"].index("label"), 1), "right_text"]])
    require(context in ("update", "delete"), "postgres-text-comparison-placement", context)
    return mutation(tag, context, [("label", "value"), ("id", "id")], where=term)


def finite(tag, operator, left, right, context):
    rhs = field(2, 1) if context == "on" else field(2)
    lhs = {"field": field(1), "literal": text("A"), "null": ["null"]}[left]
    rhs = {"field": rhs, "literal": text("a"), "null": ["null"]}[right]
    return place(tag, context, comparison(operator, lhs, rhs))


def finite_ids(_, specification, _tag):
    bounds = specification["finite"]
    identities = set(product(*(bounds[key] for key in ("operators", "left", "right", "contexts"))))
    require(len(identities) == bounds["identities"], "postgres-text-comparison-finite-size", len(identities))
    return identities


def placed_term(response):
    if "target" in response:
        return ("update" if "assignments" in response else "delete"), response["where"]
    if "kind" in response["source"]:
        return "on", response["source"]["on"]
    if response["where"] is not None:
        return "where", response["where"]
    return "result", response["columns"][0][0]


def finite_id(_, response, _item):
    placement, (role, operator, collation, left, right) = placed_term(response)
    require(role == "text-comparison" and collation == "C", "postgres-text-comparison-finite-role", response)
    roles = {"text-slot": "field", "text": "literal", "null": "null"}
    return operator, roles[left[0]], roles[right[0]], placement


def analysis(tag, sql):
    return {"dialect": "postgres", "release": tag, "sql": sql,
            "declarations": [*DECLARATIONS, f"CREATE TABLE public.{INPUT} (note pg_catalog.text, id integer, label pg_catalog.text)"]}


def analyzed(tag):
    left = table(heading=("label",))
    name, right = f"public.{TABLE}", f"public.{INPUT}"
    term = comparison("<", text("A"), text("a"))
    yield f"SELECT ('A' {COLLATE}) < 'a' AS matches", select(tag, None, [[term, "matches"]])
    yield f"SELECT 'A' OPERATOR(pg_catalog.<) ('a' {COLLATE}) AS matches", select(tag, None, [[term, "matches"]])
    term = comparison("<>", text("é"), text("é"))
    yield f"SELECT ('é' {COLLATE}) != ('é' {COLLATE}) AS matches", select(tag, None, [[term, "matches"]])
    yield (f"SELECT a.label AS left_text, b.label AS right_text FROM {name} a LEFT JOIN {right} b "
           f"ON a.label {COLLATE} < b.label",
           place(tag, "on", comparison("<", field(0), field(0, 1)), layout=("label",), right_layout=("label",)))
    term = comparison(">", field(1), text("Z"))
    yield f"UPDATE {name} SET id = 9 WHERE label {COLLATE} > 'Z' RETURNING label AS value, id", place(tag, "update", term)
    yield f"DELETE FROM {name} WHERE label {COLLATE} > 'Z' RETURNING label AS value, id", place(tag, "delete", term)
    term = comparison("=", field(0), ["null"])
    yield (f"SELECT (label {COLLATE} = NULL) IS UNKNOWN AS matches FROM {name}",
           select(tag, left, [[["truth-test", "IS UNKNOWN", term], "matches"]]))


def compositions(tag):
    name = f"public.{TABLE}"
    term = comparison(">", field(0), text("Z"))
    integer_case = ["int4-case", [[term, int4(9)]], int4(-1)]
    yield (f"SELECT CASE WHEN label {COLLATE} > 'Z' THEN 9 ELSE -1 END AS v FROM {name}",
           select(tag, table(heading=("label",)), [[integer_case, "v"]]))

    condition = comparison(">", field(1), text("Z"))
    group = grouped("postgres", tag, table(heading=("id", "label")), [slot(0)],
                    [[slot(0), "key"], [aggregate("count"), "count"]], condition)
    yield f"SELECT id AS key, pg_catalog.count(*) AS count FROM {name} WHERE label {COLLATE} > 'Z' GROUP BY id", group

    left = select(tag, None, [[comparison("<", text("Z"), text("a")), "matches"]])
    right = select(tag, None, [[comparison("=", ["null"], text("a")), "ignored"]])
    query = f"SELECT ('Z' {COLLATE}) < 'a' AS matches UNION ALL SELECT (NULL {COLLATE}) = 'a' AS ignored"
    yield query, compound("postgres", tag, left, "UNION", True, right)


def negatives(tag):
    scoped = "A predicate slot must belong to a visible input occurrence."
    yield place(tag, "where", comparison("=", field(0), text("A"))), "InvalidConstruction", scoped
    yield place(tag, "on", comparison("=", field(1), field(1, 2))), "InvalidConstruction", scoped
    query = analysis(tag, f"SELECT ('A' {COLLATE}) OPERATOR(public.<) 'B' AS v")
    yield query, "InvalidConstruction", "A text comparison must resolve to a pg_catalog operator."
    query = analysis(tag, f"""SELECT ('A' {COLLATE}) < ('B' COLLATE pg_catalog."POSIX") AS v""")
    yield query, "InvalidConstruction", "A text projection requires the explicit pg_catalog.C collation."
    condition = comparison("=", field(1), field(2, 1))
    yield place(tag, "on", condition, join_kind="FULL"), "InvalidConstruction", "A PostgreSQL FULL join requires integer equality between opposite inputs or a true condition."


def seeded(tag, seed, count, operators, contexts):
    rng = Random(seed)
    texts = ["", "A", "a", "Z", "é", "é", "𐐷", "old 値", "copied α", "it’s", "a\nline", "it''s"]

    def terms(placement, depth):
        fields = [field(1), field(2)]
        if placement == "on":
            fields.extend((field(0, 1), field(2, 1)))
        leaves = [*fields, ["null"], *map(text, texts)]
        if depth == 0 or rng.randrange(3) == 0:
            return comparison(rng.choice(operators), rng.choice(leaves), rng.choice(leaves))
        mode = rng.choice(("AND", "OR", "NOT", "IS NULL"))
        first = terms(placement, depth - 1)
        if mode == "IS NULL":
            return ["null-test", bool(rng.getrandbits(1)), first]
        return ["logic", mode, first, None if mode == "NOT" else terms(placement, depth - 1)]

    for index in range(count):
        placement = contexts[index % len(contexts)]
        term = terms(placement, 0 if placement == "result" else 2)
        yield place(tag, placement, term, join_kind=rng.choice(("INNER", "LEFT", "RIGHT")))


def requests(_, tag, specification):
    bounds = specification["finite"]
    for index, point in enumerate(product(*(bounds[key] for key in ("operators", "left", "right", "contexts")))):
        item = finite(tag, *point)
        yield f"finite/{index}", item, item, None
    cases = list(analyzed(tag))
    require(len(cases) == len(specification["analysis"]), "postgres-text-comparison-analysis-incomplete")
    for index, (sql, item) in enumerate(cases):
        yield f"analysis/{index}", analysis(tag, sql), item, None
    combined = list(compositions(tag))
    require(len(combined) == len(specification["compositions"]), "postgres-text-comparison-compositions-incomplete")
    for index, (sql, item) in enumerate(combined):
        yield f"composition/direct/{index}", item, item, None
        yield f"composition/analyze/{index}", analysis(tag, sql), item, None
    for seed in specification["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, specification["exploration"]["casesPerSeed"], bounds["operators"], bounds["contexts"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, diagnosis, message) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + diagnosis, "message": message}


def operand(term, bindings):
    if term[0] == "text":
        return term[1]
    if term[0] == "null":
        return None
    require(term[0] == "text-slot", "postgres-text-comparison-invalid-oracle-operand", term)
    _, position, occurrence = term
    row = bindings[occurrence]
    return row[position]


def row_product(source):
    if source is None:
        return [()]
    if "kind" not in source:
        heading, rows = DATA[source["table"]]
        indexes = [heading.index(name) for name in source["columns"]]
        return [(tuple(row[index] for index in indexes),) for row in rows]
    left, right = row_product(source["left"]), row_product(source["right"])
    null_left = (tuple(None for _ in source["left"]["columns"]),)
    null_right = (tuple(None for _ in source["right"]["columns"]),)
    matches = [(lix, rix, lhs + rhs) for lix, lhs in enumerate(left) for rix, rhs in enumerate(right)
               if admits(source["on"], lambda term: operand(term, lhs + rhs))]
    rows = [row for _, _, row in matches]
    if source["kind"] == "LEFT":
        found = {index for index, _, _ in matches}
        rows.extend(row + null_right for index, row in enumerate(left) if index not in found)
    elif source["kind"] == "RIGHT":
        found = {index for _, index, _ in matches}
        rows.extend(null_left + row for index, row in enumerate(right) if index not in found)
    else:
        require(source["kind"] == "INNER", "postgres-text-comparison-oracle-join-role", source["kind"])
    return rows


def expected(item):
    if "target" in item:
        return expected_mutation(item)
    if "compound" in item:
        node = item["compound"]
        require((node["operator"], node["all"]) == ("UNION", True), "postgres-text-comparison-oracle-set-role")
        left, right = expected(node["left"]), expected(node["right"])
        return observation({**left, "rows": left["rows"] + right["rows"]})
    if "grouped" in item:
        node = item["grouped"]
        require(node["keys"] == [slot(0)] and node["columns"] == [[slot(0), "key"], [aggregate("count"), "count"]],
                "postgres-text-comparison-oracle-group-role", node)
        counts = Counter(rows[0][0] for rows in row_product(node["source"])
                         if admits(node["where"], lambda term: operand(term, rows)))
        return observation({"status": "rows", "fields": [["key", "int4"], ["count", "int8"]],
                            "rows": [[key, count] for key, count in counts.items()]})
    values = row_product(item["source"])
    columns = item["columns"]

    def project(term, row):
        if term[0] == "text-slot":
            return operand(term, row)
        if term[0] == "int4-case":
            arms, otherwise = term[1:]
            result = next((value for condition, value in arms if admits(condition, lambda child: operand(child, row))), otherwise)
            require(result[0] == "integer", "postgres-text-comparison-oracle-case-role", result)
            return int(result[1])
        return predicate_value(term, lambda child: operand(child, row))

    kinds = {"text-slot": "text", "int4-case": "int4", "text-comparison": "bool", "truth-test": "bool"}
    return observation({
        "status": "rows",
        "fields": [[name, kinds[term[0]]] for term, name in columns],
        "rows": [[project(term, row) for term, _ in columns] for row in values
                 if admits(item["where"], lambda term: operand(term, row))],
    })


def check_value(item, response):
    if "target" in item:
        check_mutation(item, response)
    elif "grouped" in item or "compound" in item:
        kind = "grouped" if "grouped" in item else "compound"
        wanted = item["grouped"] if kind == "grouped" else without_profiles(item)["compound"]
        require(response["status"] == "constructed" and response["profile"].startswith(item["release"] + "/"),
                "postgres-text-comparison-composition-rejected", response)
        require(response[kind] == wanted, "postgres-text-comparison-composition-shape", {"expected": wanted, "observed": response})
    else:
        check_select(item, response)
        require(all(item[key] == response[key] for key in ("orderBy", "limit", "distinct")),
                "postgres-text-comparison-result-shape", response)


def check_observation(item, raw, sql):
    target = expected(item)
    require(target["status"] == raw["status"], "postgres-text-comparison-native-rejection", {"sql": sql, "observed": raw})
    require(target == raw, "meaning-mismatch", {"sql": sql, "expected": target, "observed": raw})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == witness["expected"]["status"], "postgres-text-comparison-fault-native")


def sample_forms(forms, response, *_):
    placement, term = placed_term(response)
    forms["place/" + placement] += 1
    if placement == "on":
        forms["join/" + response["source"]["kind"]] += 1

    def visit(node):
        if node[0] == "text-comparison":
            forms["operator/" + node[1]] += 1
            for atom in node[3:]:
                forms["role/" + atom[0]] += 1
                if atom[0] == "text-slot":
                    forms[f"occurrence/{atom[2]}"] += 1
        elif node[0] == "logic":
            forms["logic/" + node[1]] += 1
            visit(node[2])
            if node[3] is not None:
                visit(node[3])
        elif node[0] == "null-test":
            forms["logic/null-test"] += 1
            visit(node[2])
        else:
            raise Failure("postgres-text-comparison-unsampled-role", node)

    visit(term)


def required_forms(*_):
    return ({"role/text-slot", "role/text", "role/null", "occurrence/0", "occurrence/1",
             "logic/AND", "logic/OR", "logic/NOT", "logic/null-test"}
            | {"place/" + name for name in ("on", "where", "result", "update", "delete")}
            | {"join/" + name for name in ("LEFT", "RIGHT", "INNER")}
            | {"operator/" + name for name in ("=", "<>", "<", "<=", ">", ">=")})


def controls():
    item = select("pg-17.2", None, [[comparison("<", text("Z"), text("a")), "matches"]])
    wanted = expected(item)
    wrong = {**wanted, "rows": [[False]]}
    check_observation(item, wanted, "known byte comparison")
    try:
        check_observation(item, wrong, "reverse the byte ordering")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "postgres-text-comparison-wrong-control", failure.detail)
    else:
        raise Failure("postgres-text-comparison-comparator-accepted")
    return {"input": item, "expected": wanted, "mismatch": wrong}


def faults(_, tag):
    src = "sql-semantics-postgres/src/Ir/"
    file = src + "SelectSql.php"
    left = "$this->collated(new CollatedText($comparison->left, $comparison->collation), $source, $offset, $qualify)"
    right = "$this->expression($comparison->right, $source, $offset, $qualify)"
    term = comparison("<", text("Z"), text("a"))
    witness = select(tag, None, [[term, "value"]])
    native_operator = "$comparison->operator->value"
    return [
        RenderFault("reverse-text-comparison", file, native_operator, "'>'", witness, "meaning-mismatch"),
        RenderFault("swap-text-comparison-operands", file, f"$left = {left};\n        $right = {right};",
                    f"$right = {left};\n        $left = {right};", witness, "meaning-mismatch"),
        RenderFault("replace-text-comparison-collation", file, left,
                    "$this->expression($comparison->left, $source, $offset, $qualify)", witness, "meaning-mismatch"),
        RenderFault("omit-later-comparison-occurrence", file, "$this->alias($offset + $expression->occurrence)",
                    "$this->alias($offset)",
                    place(tag, "on", comparison("<", field(1), field(2, 1))), "meaning-mismatch"),
        RenderFault("drop-text-update-filter", src + "Mutation/Update.php", "if ($this->where !== null)",
                    "if (false)", place(tag, "update", comparison(">", field(1), text("Z"))), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
