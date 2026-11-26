"""Observe an exact PostgreSQL collation over native text and ordered results."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, check_value as check_select, main
from server_cases import observation as unordered
from server_compound_cases import case as compound, leaf, without_profiles
from sqlite_domain import RenderFault

LABEL = "postgres-collated-text"
CONTRACT = "52db155a439071c10ada64947f7c34b66514ef6745d6056a135b58717c6585eb"
DIAGNOSTICS = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\"
TABLE = "ir_collated_text"
HEADING = ("label", "id", "alternate")
KINDS = {"label": "text", "id": "int4", "alternate": "text"}
DEFINITION = f"CREATE TABLE public.{TABLE} (label pg_catalog.text, id integer, alternate pg_catalog.text)"
VALUES = ("z", "Z", "a", "a", "A", "é", "é", "𐐷", "", None)
ROWS = [[label, index, None if index == 0 else f"replacement-{index}"] for index, label in enumerate(VALUES)]
COLLATION_SQL = 'COLLATE pg_catalog."C"'
PROFILE_PROBE = (
    "SELECT c.oid::integer AS oid, c.collisdeterministic AS deterministic, d.datcollate::text AS default_name "
    "FROM pg_catalog.pg_collation c CROSS JOIN pg_catalog.pg_database d "
    "WHERE c.oid = 'pg_catalog.\"C\"'::regcollation AND d.datname = current_database()"
)


def specifications():
    spec, profile = TOOL / "postgres-collated-text-v2.json", TOOL / "server-projection-v1.json"
    require(digest(spec) == CONTRACT and digest(profile) == PROJECTION, "postgres-collated-text-contract-mismatch")
    specification = json.loads(spec.read_text())
    previous = specification["previous"]
    require(digest(TOOL / previous["path"]) == previous["sha256"], "postgres-collated-text-previous-contract-mismatch")
    return specification, {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def prepare(_, db):
    def atom(value):
        if value is None:
            return "NULL"
        if isinstance(value, int):
            return str(value)
        return f"pg_catalog.convert_from(pg_catalog.decode('{value.encode().hex()}','hex'),'UTF8')"

    rows = ", ".join("(" + ", ".join(map(atom, row)) + ")" for row in ROWS)
    setup = db.request({"fixture": [f"DROP TABLE IF EXISTS public.{TABLE}", DEFINITION, f"INSERT INTO public.{TABLE} VALUES {rows}"]})
    return {"fixture": setup, "profile": db.request({"sql": PROFILE_PROBE})}


def check_preparation(_, _environment, prepared):
    same({"status": "fixture"}, prepared["fixture"])
    same({"status": "rows", "fields": [["oid", "int4"], ["deterministic", "bool"], ["default_name", "text"]],
          "rows": [[950, True, "en_US.utf8"]]}, prepared["profile"])


def observer_request(_, sql):
    return {"sql": sql, "observePostgresCollation": True}


def observation(raw):
    return raw


def table(heading=HEADING):
    return {"schema": "public", "table": TABLE, "columns": list(heading), "only": False,
            "columnKinds": [KINDS[item] for item in heading]}


def field(slot=0, occurrence=0):
    return ["text-slot", slot, occurrence]


def text(payload):
    return ["text", payload]


def collated(operand, label="value"):
    return [["collated-text", "C", operand], label]


def select(tag, operands, source=None, order=(), distinct=False):
    return {**leaf("postgres", tag, operands, source), "orderBy": list(order), "distinct": distinct}


def finite(tag, origin, direction, placement, distinct):
    scope = table() if origin == "field" else None
    operand = field() if scope else text("é")
    return select(tag, [collated(operand)], scope, [[1, direction == "desc", placement == "first"]], distinct)


def finite_id(_, actual, _item):
    (term, _), = actual["columns"]
    (position, descending, first), = actual["orderBy"]
    require(position == 1 and term[:2] == ["collated-text", "C"], "postgres-collated-text-wrong-finite-role", actual)
    return {"text": "literal", "text-slot": "field"}[term[2][0]], "desc" if descending else "asc", "first" if first else "last", actual["distinct"]


def finite_ids(_, specification, _tag):
    b = specification["finite"]
    points = set(product(b["sources"], b["directions"], b["nulls"], b["distinct"]))
    require(len(points) == b["identities"], "postgres-collated-text-finite-size")
    return points


def analysis(tag, sql):
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": [DEFINITION]}


def analyzed(tag):
    yield (analysis(tag, f"SELECT 'é' {COLLATION_SQL} AS value"), select(tag, [collated(text("é"))]))
    yield (analysis(tag, f"SELECT label {COLLATION_SQL} AS value FROM public.{TABLE}"),
           select(tag, [collated(field())], table(("label",))))
    yield (analysis(tag, f"SELECT CAST(NULL AS pg_catalog.text) {COLLATION_SQL} AS value"),
           select(tag, [collated(["null"])]))
    yield (analysis(tag, f"SELECT DISTINCT label {COLLATION_SQL} AS value FROM public.{TABLE} ORDER BY 1 DESC NULLS LAST"),
           select(tag, [collated(field())], table(("label",)), [[1, True, False]], True))


def negatives(tag):
    wrong_slot = "A text slot must belong to the SELECT source with its native kind."
    yield select(tag, [collated(field(1))], table()), "InvalidConstruction", wrong_slot
    yield select(tag, [collated(field(0, 1))], table()), "InvalidConstruction", wrong_slot
    query = analysis(tag, """SELECT 'z' COLLATE pg_catalog."POSIX" AS value""")
    yield query, "InvalidConstruction", "A text projection requires the explicit pg_catalog.C collation."
    yield analysis(tag, f"SELECT 1 {COLLATION_SQL} AS value"), "SemanticException", "Collations are not supported by type integer."


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        scope = table() if index % 3 else None
        options = [["null"], *map(text, (v for v in VALUES if v is not None))]
        if scope:
            options.extend((field(), field(2), field()))
        width = rng.randrange(1, 5)
        fields = [collated(rng.choice(options), f"c{i}" if i > 0 else "value") for i in range(width)]
        keys = [[i + 1, bool(rng.getrandbits(1)), bool(rng.getrandbits(1))] for i in range(width)] if index % 2 else []
        yield select(tag, fields, scope, keys, bool(rng.getrandbits(1)))


def duplicate_labels(tag):
    return select(tag, [collated(field(), "same_label"), collated(text("A"), "same_label")], table())


def requests(_, tag, specification):
    b = specification["finite"]
    for index, (origin, direction, nulls, distinct) in enumerate(product(b["sources"], b["directions"], b["nulls"], b["distinct"])):
        item = finite(tag, origin, direction, nulls, distinct)
        yield f"finite/{index}", item, item, None
    cases = list(analyzed(tag))
    require(len(cases) == len(specification["analysis"]), "postgres-collated-text-analysis-incomplete")
    for index, (query, expected_value) in enumerate(cases):
        yield f"analysis/{index}", query, expected_value, None
    first = select(tag, [collated(text("é"))], table())
    last = select(tag, [collated(["null"], "right")])
    pair = compound("postgres", tag, first, "UNION", True, last)
    yield "compound/0", pair, pair, None
    empty = {**duplicate_labels(tag), "limit": [0, 0]}
    yield "observation/empty", empty, empty, None
    for seed in specification["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, diagnosis, message) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + diagnosis, "message": message}


def expected(item):
    if "compound" in item:
        node = item["compound"]
        require(node["operator"] == "UNION" and node["all"], "postgres-collated-text-unknown-set-role", node)
        left, right = expected(node["left"]), expected(node["right"])
        return {**left, "rows": left["rows"] + right["rows"]}

    source = item["source"]
    rows = ROWS if source else [()]

    def value(term, row):
        role, collation, operand = term
        require(role == "collated-text" and collation == "C", "postgres-collated-text-unknown-oracle-role", term)
        if operand[0] == "null":
            return None
        if operand[0] == "text":
            return operand[1]
        require(operand[0] == "text-slot" and operand[2] == 0 and source is not None, "postgres-collated-text-invalid-oracle-field", operand)
        return row[HEADING.index(source["columns"][operand[1]])]

    results = [[value(term, row) for term, _ in item["columns"]] for row in rows]
    if item["distinct"]:
        results = list(map(list, dict.fromkeys(map(tuple, results))))
    for position, descending, nulls_first in reversed(item["orderBy"]):
        index = position - 1
        missing = [row for row in results if row[index] is None]
        present = sorted((row for row in results if row[index] is not None), key=lambda row: row[index].encode(), reverse=descending)
        results = missing + present if nulls_first else present + missing
    if item["limit"] is not None:
        count, offset = item["limit"]
        results = results[offset:offset + count if count is not None else None]
    return {"status": "rows", "fields": [[name, "text"] for _, name in item["columns"]], "rows": results,
            "collations": ['"C"'] * len(item["columns"])}


def check_value(item, actual):
    if "compound" in item:
        require(actual["status"] == "constructed" and actual["profile"].startswith(item["release"] + "/")
                and actual["compound"] == without_profiles(item)["compound"], "postgres-collated-text-compound-shape", actual)
    else:
        check_select(item, actual)
        require(all(item[key] == actual[key] for key in ("orderBy", "limit", "distinct")), "postgres-collated-text-result-shape", actual)


def normalize(item, rows):
    return rows if item.get("orderBy") else unordered(rows)


def check_observation(item, raw, sql):
    wanted, actual = normalize(item, expected(item)), normalize(item, raw)
    witness = {"sql": sql, "expected": wanted, "observed": actual}
    require(actual["status"] == "rows", "postgres-collated-text-native-rejection", witness)
    require(actual == wanted, "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(normalize(item, expected(item)), witness["expected"])
    require(witness["observed"]["status"] == "rows", "postgres-collated-text-fault-native")


def sample_forms(forms, actual, *_):
    forms["source/" + ("scalar" if actual["source"] is None else "table")] += 1
    forms["width/" + ("single" if len(actual["columns"]) == 1 else "multiple")] += 1
    forms[f"distinct/{actual['distinct']}"] += 1
    for term, _ in actual["columns"]:
        forms["operand/" + term[2][0]] += 1
    for _, descending, nulls_first in actual["orderBy"]:
        forms[f"order/{descending}/{nulls_first}"] += 1


def required_forms(*_):
    return {"source/scalar", "source/table", "width/single", "width/multiple", "distinct/True", "distinct/False",
            "operand/text", "operand/text-slot", "operand/null", *(f"order/{d}/{n}" for d, n in product((True, False), repeat=2))}


def controls():
    item = finite("pg-17.2", "field", "asc", "first", False)
    wanted = expected(item)
    check_observation(item, wanted, "known byte order")
    changed = {**wanted, "rows": list(reversed(wanted["rows"]))}
    labels = duplicate_labels("pg-17.2")
    labeled = expected(labels)
    different_collation = {**labeled, "collations": ['"C"', '"default"']}
    for graph, mismatch in ((item, changed), (labels, different_collation)):
        try:
            check_observation(graph, mismatch, "change exactly one prescribed observation")
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "postgres-collated-text-wrong-control", failure.detail)
        else:
            raise Failure("postgres-collated-text-comparator-accepted")
    return {"order": {"input": item, "expected": wanted, "mismatch": changed},
            "laterCollation": {"input": labels, "expected": labeled, "mismatch": different_collation}}



def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/"
    sql = path + "SelectSql.php"
    field_order = finite(tag, "field", "asc", "first", False)
    return [
        RenderFault("omit-explicit-text-collation", sql, r'''return '(' . $operand . ' COLLATE pg_catalog."' . $expression->collation->value . '")';''',
                    "return $operand;", field_order, "meaning-mismatch"),
        RenderFault("reverse-collated-order", path + "Ordering/ResultSql.php", "$key->descending ? ' DESC' : ' ASC'",
                    "$key->descending ? ' ASC' : ' DESC'", field_order, "meaning-mismatch"),
        RenderFault("read-wrong-collated-field", sql, "$input->columns[$expression->slot]->quoted()",
                    "$input->columns[$expression instanceof TextColumnReference ? 2 : $expression->slot]->quoted()",
                    field_order, "meaning-mismatch"),
        RenderFault("discard-collated-distinct", sql, "$select->distinct ? 'SELECT DISTINCT ' : 'SELECT '", "'SELECT '",
                    finite(tag, "field", "desc", "last", True), "meaning-mismatch"),
        RenderFault("discard-only-second-collation", sql, ": $this->expression($expression, $source);",
                    ": $this->expression($slot === 1 && $expression instanceof CollatedText "
                    "? $expression->operand : $expression, $source);",
                    duplicate_labels(tag), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
