"""Compare PostgreSQL text-bearing source projections and positional INSERT effects."""

from itertools import chain, product
import json
import random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main
from run_server_postgres_text_insert import expected as expected_values
from run_server_postgres_text_returning import (
    DECLARATIONS, OBSERVER, TABLE, address, analysis_request, check_preparation as check_mutation_preparation,
    condition, int4, mutation, observation, prepare as prepare_mutations, reference, sorted_rows,
)
from run_server_postgres_typed_columns import DIAGNOSTICS
from server_join_cases import slot
from server_truth_model import admits
from sqlite_domain import RenderFault

LABEL = "postgres-text-insert-query"
CONTRACT = "c96925357b9e8be0e1cac7a3a4143e660ddb0c996ee770bd08d9e7b96ef8b2ad"
INPUT = "ir_text_inputs"
HEADING = ("note", "id", "label")
KINDS = ("text", "int4", "text")
INPUT_DEFINITION = f"CREATE TABLE public.{INPUT} (note pg_catalog.text, id integer, label pg_catalog.text)"
ROWS = [["other", 7, "copied α"], ["other", 7, "copied α"], ["memo", 8, None], ["blank", 9, ""], [None, 10, "東京"]]
LAYOUTS = {"text": ("label",), "int-text": ("id", "label"), "text-int": ("label", "id")}
ALIASES = {"id": "number", "label": "v", "note": "memo"}


def specifications():
    path, profile = TOOL / "postgres-text-insert-query-v2.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "postgres-text-insert-query-contract-mismatch")
    return json.loads(path.read_text()), {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def observer_request(_, sql):
    return {"sql": sql, "observeInput": True}


def prepare(engine, db):
    prepared = prepare_mutations(engine, db)
    invalid = (
        f"INSERT INTO public.{TABLE} (label) SELECT label FROM public.arbitrary",
        f"INSERT INTO public.{TABLE} (label) SELECT 'test' UNION ALL SELECT 'more'",
    )
    return {"mutation": prepared, "guard": [{"sql": sql, "observed": db.request(observer_request(None, sql))} for sql in invalid]}


def check_preparation(engine, environment, prepared):
    check_mutation_preparation(engine, environment, prepared["mutation"])
    same(sorted_rows(ROWS), sorted_rows(environment["input"]))
    require(len(prepared["guard"]) == 2 and all(result["observed"] == {"status": "outside-observer-scope"}
            for result in prepared["guard"]), "postgres-text-insert-query-observer-scope", prepared["guard"])


def source():
    return {"schema": "public", "table": INPUT, "columns": list(HEADING), "columnKinds": list(KINDS), "only": False}


def read(name):
    index = HEADING.index(name)
    return slot(index) if name == "id" else ["text-slot", index, 0]


def write_query(tag, destination, projection, table=None, where=None, distinct=False,
                returned=(("id", "id"), ("label", "label"), ("note", "note"))):
    item = mutation(tag, "insert-query", returned)
    item["written"] = [reference(name) for name in destination]
    item["insertQuery"].update(columns=projection, source=table, where=where, distinct=distinct)
    return item


def finite(tag, origin, layout, mode, duplicates):
    columns = LAYOUTS[layout]
    table = source() if origin == "table" else None
    where = ["boolean", False] if mode == "no-row" else None
    if table is not None:
        if mode == "nullable":
            where = ["null-test", False, read("label")]
        projection = [[read(column), ALIASES[column]] for column in columns]
    else:
        atoms = {"id": int4(8), "label": ["null"] if mode == "nullable" else ["text", "finite α"]}
        projection = [[atoms[column], ALIASES[column]] for column in columns]
    return write_query(tag, columns, projection, table, where, duplicates == "distinct")


def signed_finite(tag, origin, layout, mode, duplicates):
    table = source() if origin == "signed-table" else None
    atoms = {"id": read("id") if table else int4(8), "label": read("id") if table else int4(int(mode))}
    where = None
    if table:
        where = {"value": None, "nullable": ["null-test", False, read("label")], "no-row": ["boolean", False]}[mode]
    targets = LAYOUTS[layout]
    projection = [[atoms[column], ALIASES[column]] for column in targets]
    return write_query(tag, targets, projection, table, where, duplicates == "distinct")


def signed_points(bounds):
    for origin in bounds["additionalSources"]:
        modes = bounds["mode"] if origin == "signed-table" else [str(value) for value in bounds["signedLiterals"]]
        yield from product((origin,), bounds["layout"], modes, bounds["duplicates"])


def finite_ids(_, spec, _tag):
    b = spec["finite"]
    points = set(product(b["source"], b["layout"], b["mode"], b["duplicates"])) | set(signed_points(b))
    require(len(points) == b["identities"], "postgres-text-insert-query-finite-size", points)
    return points


def source_term(expression, layout):
    if expression is None:
        return None
    tag = expression[0]
    if tag in ("slot", "text-slot"):
        require(layout is not None and (len(expression) == 2 or expression[2] == 0),
                "postgres-text-insert-query-missing-scope", expression)
        position = expression[1]
        name = layout["columns"][position]
        kind = layout.get("columnKinds", ["int4"] * len(layout["columns"]))[position]
        require(kind == KINDS[HEADING.index(name)] == ("text" if tag == "text-slot" else "int4"),
                "postgres-text-insert-query-wrong-source-kind", [expression, layout])
        return tag, name
    if tag == "null-test":
        return tag, expression[1], source_term(expression[2], layout)
    require(tag in ("text", "integer", "boolean", "null"), "postgres-text-insert-query-unknown-source-term", expression)
    return tuple(expression)


def graph(item):
    target = item["target"]
    query = item["insertQuery"]
    layout = query["source"]
    require(query["orderBy"] == [] and query["limit"] is None, "postgres-text-insert-query-unexpected-extent", query)
    return (
        (target["schema"], target["table"], target["only"]),
        tuple(address(term, target) for term in item["written"]),
        tuple((address(term, target), name) for term, name in item["returning"]),
        None if layout is None else (layout["schema"], layout["table"], layout["only"]),
        tuple((source_term(term, layout), name) for term, name in query["columns"]),
        source_term(query["where"], layout),
        query["distinct"],
    )


def finite_id(_, response, _item):
    _, written, _, origin, columns, where, distinct = graph(response)
    name = {value: key for key, value in LAYOUTS.items()}[written]
    mode = "no-row" if where == ("boolean", False) else (
        "nullable" if where is not None or any(term[0] == "null" for term, _ in columns) else "value")
    text_input = columns[written.index("label")][0]
    if text_input[0] == "integer":
        origin_kind, mode = "signed-constant", text_input[1]
    elif text_input[0] == "slot":
        origin_kind = "signed-table"
    else:
        origin_kind = "constant" if origin is None else "table"
    return origin_kind, name, mode, ("distinct" if distinct else "all")


def analyzed(tag):
    target, input_ = f"public.{TABLE}", f"public.{INPUT}"
    tail = "RETURNING id, label, note"
    yield (f"INSERT INTO {target} (label) SELECT 'literal α' AS v {tail}",
           write_query(tag, ("label",), [[["text", "literal α"], "v"]]))
    yield (f"INSERT INTO {target} (label, id) SELECT label AS v, id AS number FROM {input_} {tail}",
           finite(tag, "table", "text-int", "value", "all"))
    yield (f"INSERT INTO {target} (id, label) SELECT DISTINCT 8 AS number, NULL AS v {tail}",
           finite(tag, "constant", "int-text", "nullable", "distinct"))
    yield (f"INSERT INTO {target} (label) SELECT s.label AS v FROM {input_} AS s WHERE s.label IS NOT NULL {tail}",
           write_query(tag, ("label",), [[read("label"), "v"]], source(), ["null-test", True, read("label")]))
    yield (f"INSERT INTO {target} (label) SELECT -2147483648 AS v {tail}",
           signed_finite(tag, "signed-constant", "text", "-2147483648", "all"))
    yield (f"INSERT INTO {target} (label) SELECT DISTINCT id AS v FROM {input_} {tail}",
           signed_finite(tag, "signed-table", "text", "value", "distinct"))


def analysis_sql(tag, sql):
    item = analysis_request(tag, sql)
    item["declarations"] = [*DECLARATIONS, INPUT_DEFINITION]
    return item


def negatives(tag):
    yield write_query(tag, ("label",), [[["parameter", 1], "v"]]), "A query INSERT text target requires text, a checked signed leaf or NULL."
    yield write_query(tag, ("id",), [[["text", "value"], "v"]]), "Each query INSERT field requires a signed value or NULL."
    yield write_query(tag, ("id", "label"), [[int4(3), "v"]]), "Each row of the INSERT query must match the target width."
    bad = finite(tag, "constant", "text", "value", "all")
    bad["returning"][1][0] = ["text-slot", 1, 1]
    yield bad, "A returned field must read the single target occurrence."


def exploratory(tag, seed, count):
    rng = random.Random(seed)
    conversions = random.Random(seed + 1)
    for index in range(count):
        targets = rng.choice(tuple(LAYOUTS.values()))
        from_ = source() if rng.getrandbits(1) else None
        choices = (["text", ""], ["text", "multi\n値"], ["null"])
        projection = []
        for name in targets:
            if name == "id":
                value = rng.choice((int4(-9), read("id"))) if from_ else int4(rng.randrange(-9, 10))
            else:
                value = rng.choice((*choices, read("note"), read("label"))) if from_ else rng.choice(choices)
                if index % 6 == 0:
                    value = read("id") if from_ else int4(conversions.randrange(-2147483648, 2147483648))
            projection.append([value, rng.choice(("value", 'field " q', "same"))])
        where = rng.choice((None, ["boolean", False], ["null-test", True, read("note")])) if from_ else (
            rng.choice((None, ["boolean", False])))
        returned = [(rng.choice(HEADING), rng.choice(("same", "result"))) for _ in range(rng.randint(1, 4))]
        yield write_query(tag, targets, projection, from_, where, bool(rng.getrandbits(1)), returned)


def requests(_, tag, spec):
    b = spec["finite"]
    base = product(b["source"], b["layout"], b["mode"], b["duplicates"])
    for index, point in enumerate(chain(base, signed_points(b))):
        make = signed_finite if point[0] in b["additionalSources"] else finite
        item = make(tag, *point)
        yield f"finite/{index}", item, item, None
    analyzed_inputs = tuple(analyzed(tag))
    require(len(analyzed_inputs) == len(spec["analysis"]), "postgres-text-insert-query-analysis-incomplete")
    for index, (sql, item) in enumerate(analyzed_inputs):
        yield f"analyze/{index}", analysis_sql(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(exploratory(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, message) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": message}


def expected(item):
    query = item["insertQuery"]
    layout = query["source"]
    incoming = ROWS if layout is not None else [()]

    def value(expression, row):
        tag, *parts = source_term(expression, layout)
        if tag in ("slot", "text-slot"):
            return row[HEADING.index(parts[0])]
        return {"integer": lambda: int(parts[0]), "text": lambda: parts[0], "null": lambda: None}[tag]()

    projected = [[value(expression, row) for expression, _ in query["columns"]] for row in incoming
                 if admits(query["where"], lambda expression: value(expression, row))]
    if query["distinct"]:
        projected = list(dict.fromkeys(tuple(row) for row in projected))
    targets = [address(term, item["target"]) for term in item["written"]]
    cells = [[None if value_ is None else str(value_) if target == "id" else ["text", str(value_)]
              for target, value_ in zip(targets, row)] for row in projected]
    values = expected_values({**item, "rows": cells})
    return observation({**values, "inputBefore": ROWS, "inputAfter": ROWS, "inputRestored": ROWS})


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
            "postgres-text-insert-query-positive-rejected", reply)
    require(graph(item) == graph(reply), "postgres-text-insert-query-graph-differs", {"expected": item, "actual": reply})


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(actual["status"] == "mutation", "postgres-text-insert-query-native-rejection", {"sql": sql, "actual": actual})
    require(prediction == actual, "meaning-mismatch", {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "mutation", "postgres-text-insert-query-fault-native")


def sample_forms(forms, reply, *_):
    _, written, _, origin, columns, where, distinct = graph(reply)
    forms[f"origin/{origin is not None}"] += 1
    forms[f"where/{where is not None}"] += 1
    forms[f"distinct/{distinct}"] += 1
    forms[f"written/{','.join(written)}"] += 1
    for term, _ in columns:
        forms[f"operand/{term[0]}"] += 1
    if "label" in written:
        term = columns[written.index("label")][0]
        if term[0] in ("integer", "slot"):
            forms[f"assignment/int4-to-text/{term[0]}"] += 1
    forms[f"inserted/{bool(expected(reply)['count'])}"] += 1


def required_forms(*_):
    return {"origin/True", "origin/False", "where/True", "where/False", "distinct/True", "distinct/False",
            "written/id,label", "written/label,id", "written/label", "operand/text-slot", "operand/text",
            "operand/integer", "operand/null", "inserted/True", "inserted/False",
            "assignment/int4-to-text/integer", "assignment/int4-to-text/slot"}


def controls():
    item = finite("pg-17.2", "table", "text-int", "value", "all")
    prediction = expected(item)
    check_observation(item, prediction, "known text-producing source")
    altered = {**prediction, "inputAfter": prediction["inputAfter"][:-1]}
    try:
        check_observation(item, altered, "mutated independent SELECT input")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "postgres-text-insert-query-control-signal", failure.detail)
    else:
        raise Failure("postgres-text-insert-query-comparator-accepted")
    return {"healthy": prediction, "inputChanged": altered}


def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/SelectSql.php"
    return [
        RenderFault("use-other-producing-text-field", path, "$input->columns[$expression->slot]->quoted()",
                    "$input->columns[$expression instanceof TextColumnReference ? 0 : $expression->slot]->quoted()",
                    finite(tag, "table", "text", "value", "all"), "meaning-mismatch"),
        RenderFault("discard-insert-query-distinct", path, "$select->distinct ? 'SELECT DISTINCT ' : 'SELECT '",
                    "'SELECT '", finite(tag, "table", "text-int", "value", "distinct"), "meaning-mismatch"),
        RenderFault("discard-insert-query-filter", path, "if ($select->where !== null)", "if (false)",
                    finite(tag, "table", "text", "no-row", "all"), "meaning-mismatch"),
        RenderFault("discard-signed-value-assigned-to-text", path,
                    r"$expression instanceof IntegerLiteral => 'CAST(' . $expression->decimal . ' AS pg_catalog.int4)',",
                    r"$expression instanceof IntegerLiteral => 'CAST(0 AS pg_catalog.int4)',",
                    signed_finite(tag, "signed-constant", "text", "-17", "all"), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
