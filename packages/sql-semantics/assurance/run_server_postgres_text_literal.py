"""Compare resolved PostgreSQL text literals by exact UTF-8 value and native type."""

from itertools import product
import json
import random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, check_preparation, main, observer_request, prepare
from server_cases import CHILD_ROWS, observation
from server_compound_cases import case as compound, leaf, without_profiles
from server_join_cases import DATA, NAMES_BY_TABLE, declarations, table
from sqlite_domain import RenderFault

LABEL = "postgres-text-literal"
CONTRACT = "3eacae517d170d2a433eba45a7fafe5860ef6503a251a0f5cbde69a358da8e48"
DIAGNOSTICS = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\"


def specifications():
    path, profile = TOOL / "postgres-text-literal-v1.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "postgres-text-literal-contract-mismatch")
    return json.loads(path.read_text()), {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def text(value, label="value"):
    return [["text", value], label]


def finite(tag, value, projection, source):
    columns = [text(value)]
    if projection == "literal-and-int":
        columns.append([["integer", "9"], "ordinal"])
    if projection == "repeated":
        columns.append(text(value, 'again "値"'))
    rows = None if source == "none" else table("postgres", "accounts", columns=[])
    return leaf("postgres", tag, columns, rows)


def analysis_request(tag, sql):
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": declarations("postgres")}


def analyzed(tag):
    for sql, value, label in (
        ("SELECT 'text'", "text", "?column?"),
        ("SELECT 'it''s here' AS value", "it's here", "value"),
        (r"SELECT 'a\b' AS value", "a\\b", "value"),
        (r"SELECT E'line\nnext\t' AS value", "line\nnext\t", "value"),
        ("SELECT $body$it's \\\nline$body$ AS value", "it's \\\nline", "value"),
        (r"SELECT U&'\+010437e\0301' AS value", "𐐷é", "value"),
        ("SELECT 'first'\n'second' AS value", "firstsecond", "value"),
        ("SELECT CAST('don''t' AS pg_catalog.text) AS value", "don't", "value"),
    ):
        yield sql, leaf("postgres", tag, [text(value, label)])
    source = table("postgres", "accounts", columns=[])
    yield f"SELECT 'same value' AS value FROM public.{NAMES_BY_TABLE['accounts']}", leaf(
        "postgres", tag, [text("same value")], source)
    sql = "SELECT 'a'::pg_catalog.text AS left_text UNION ALL SELECT CAST('値' AS pg_catalog.text) AS right_text"
    yield sql, compound("postgres", tag, leaf("postgres", tag, [text("a", "left_text")]),
                        "UNION", True, leaf("postgres", tag, [text("値", "right_text")]))


def negatives(tag):
    invalid = {"exception": DIAGNOSTICS + "InvalidConstruction", "message": "requires valid UTF-8 without a zero byte"}
    for term in (["text", "head\x00tail"], ["text-bytes", "f08080ff"]):
        yield leaf("postgres", tag, [[term, "value"]]), invalid
    yield compound("postgres", tag, leaf("postgres", tag, [text("1")]), "UNION", True,
                   leaf("postgres", tag, [[["integer", "2"], "value"]])), {
        "exception": DIAGNOSTICS + "InvalidConstruction", "message": "compound output types are incompatible"}


def requests(_, tag, spec):
    bounds = spec["finite"]
    for index, (value, projection, source) in enumerate(product(bounds["values"], bounds["projection"], bounds["source"])):
        item = finite(tag, value, projection, source)
        yield f"finite/{index}", item, item, None
    analyze = list(analyzed(tag))
    require(len(analyze) == len(spec["analysis"]), "postgres-text-literal-analysis-incomplete")
    for index, (sql, item) in enumerate(analyze):
        yield f"analyze/{index}", analysis_request(tag, sql), item, None
    rare = ("'", "\\", "\r", "\n", "\x01", "𐐷", "é", "", "$body$", "\t")
    for seed in spec["exploration"]["seeds"]:
        rng = random.Random(seed)
        for index in range(spec["exploration"]["casesPerSeed"]):
            def scalar():
                if rng.getrandbits(1):
                    return rng.choice(rare)
                return chr(rng.choice((rng.randrange(1, 0xD800), rng.randrange(0xE000, 0x110000))))
            fields = [text("".join(scalar() for _ in range(rng.randint(1, 28))),
                           rng.choice(("value", "値", 'a " label'))) for _ in range(rng.randint(1, 5))]
            origins = rng.choice((None, table("postgres", "accounts", only=True, columns=[])))
            item = leaf("postgres", tag, fields, origins)
            yield f"seed/{seed}/{index}", item, item, None
    for index, (request, boundary) in enumerate(negatives(tag)):
        yield f"negative/{index}", request, None, boundary


def finite_id(_engine, observed, _item):
    columns = observed["columns"]
    projection = "literal" if len(columns) == 1 else "literal-and-int" if columns[1][0][0] == "integer" else "repeated"
    return columns[0][0][1], projection, "none" if observed["source"] is None else "declared"


def finite_ids(_, spec, _tag):
    b = spec["finite"]
    identities = {(value, mode, origin) for value in b["values"] for mode in b["projection"] for origin in b["source"]}
    require(len(identities) == b["identities"], "wrong-postgres-text-literal-domain")
    return identities


def expected(item):
    if "compound" in item:
        operation = item["compound"]
        require(operation["operator"] == "UNION" and operation["all"], "postgres-text-literal-unexpected-set-role")
        a, b = expected(operation["left"]), expected(operation["right"])
        require([kind for _, kind in a["fields"]] == [kind for _, kind in b["fields"]], "postgres-text-literal-incompatible-oracle")
        return observation({"status": "rows", "fields": a["fields"], "rows": a["rows"] + b["rows"]})
    row = []
    fields = []
    for expression, name in item["columns"]:
        kind, value = expression
        require(kind in {"text", "integer"}, "postgres-text-literal-unexpected-scalar", expression)
        row.append(value if kind == "text" else int(value))
        fields.append([name, "text" if kind == "text" else "int4"])
    from_ = item["source"]
    cardinality = 1
    if from_ is not None:
        require(from_["table"] == NAMES_BY_TABLE["accounts"], "postgres-text-literal-unexpected-source", from_)
        cardinality = len(DATA["accounts"]) + (0 if from_["only"] else len(CHILD_ROWS))
    return observation({"status": "rows", "fields": fields, "rows": [row] * cardinality})


def check_value(item, response):
    require(response["status"] == "constructed", "postgres-text-literal-positive-rejected", response)
    require(response["profile"].startswith(item["release"] + "/"), "postgres-text-literal-profile", response)
    if "compound" in item:
        require(response.get("compound") == without_profiles(item)["compound"], "postgres-text-literal-set-shape", response)
    else:
        require(all(item[key] == response.get(key) for key in ("source", "columns", "where")),
                "postgres-text-literal-value-shape", {"expected": item, "actual": response})


def check_observation(item, actual, sql):
    prediction = expected(item)
    witness = {"sql": sql, "expected": prediction, "observed": actual}
    require(actual["status"] == "rows", "postgres-text-literal-native-rejection", witness)
    require(actual == prediction, "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "postgres-text-literal-fault-native")


def sample_forms(forms, reply, *_):
    forms["from/" + ("table" if reply["source"] else "scalar")] += 1
    forms[f"width/{len(reply['columns'])}"] += 1
    for (_, value), label in reply["columns"]:
        if "'" in value:
            forms["apostrophe"] += 1
        if "\\" in value:
            forms["backslash"] += 1
        if any(ord(point) > 0xFFFF for point in value):
            forms["supplementary"] += 1
        if any(ord(point) < 32 for point in value):
            forms["control"] += 1
        if '"' in label:
            forms["quoted-label"] += 1


def required_forms(*_):
    return {"from/table", "from/scalar", "width/1", "width/2", "width/3", "width/4", "width/5",
            "apostrophe", "backslash", "supplementary", "control", "quoted-label"}


def controls():
    item = finite("pg-17.2", "a'b", "literal-and-int", "declared")
    prediction = expected(item)
    check_observation(item, prediction, "known text value")
    altered = {**prediction, "fields": [["value", "varchar"], prediction["fields"][1]]}
    try:
        check_observation(item, altered, "changed text type")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "wrong-postgres-text-literal-control", failure.detail)
    else:
        raise Failure("postgres-text-literal-comparator-accepted")
    return {"healthy": prediction, "typeMismatch": altered}


def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/Expression/TextLiteral.php"
    item = finite(tag, "a\\b'値", "literal", "none")
    return [
        RenderFault("empty-decoded-text", path, '$this->value)', "'')", item, "meaning-mismatch"),
        RenderFault("erase-text-backslash", path, '$this->value)', "str_replace(chr(92), '', $this->value))", item, "meaning-mismatch"),
        RenderFault("change-native-result-type", path, "pg_catalog.text)", "pg_catalog.varchar)", item, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
