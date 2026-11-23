"""Check PostgreSQL table construction from a query against an independent state model."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import main, probe
from run_server_postgres_create_view import BASE_FIELDS, ordered_base
from run_server_postgres_local_values import graph, request
from run_server_postgres_materialized_view import definition_rows, phase_rows, query as snapshot_query
from run_server_postgres_values import DIAGNOSTIC, specifications as profiles
from run_server_postgres_with_projection import literal_scan, scan
from sqlite_domain import RenderFault

LABEL = "postgres-create-table-as"
OBSERVER = "postgres_create_table_as_observer.php"
CONTRACT_NAME = "postgres-create-table-as-v1.json"
CONTRACT = "2b22d4e05321c7e2b50b500bc4d63b5ca57c0626558af79d44443146ead4eb9d"
COMPLETE_SEEDS = True
SCHEMA, DECOY = "public", "ir_ct_decoy"
NAME, SOURCE = "ir_ct_target", "ir_ct_source"
HEADING = ["id", "label"]
AXES = ("fixture", "collision", "body", "persistence", "withData")
FIXTURE_FIELDS = [[*field, False] for field in BASE_FIELDS]


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-create-as-contract-mismatch")
    return json.loads(path.read_text()), profiles()[1]


def query(role, order=(0, 1)):
    value = snapshot_query(role, order)
    if value.get("source") is not None:
        value["source"] = {**value["source"], "table": SOURCE}
    return value


def creation(tag, body, names=HEADING, optional=False, unlogged=False, populated=True, fixture="absent"):
    return {"dialect": "postgres", "release": tag, "fixture": fixture,
            "createTableAs": {"target": [SCHEMA, NAME], "query": body, "columns": names,
                              "unlogged": unlogged, "ifNotExists": optional, "withData": populated}}


def finite(tag, fixture, optional, role, persistence, with_data):
    return creation(tag, query(role), optional=optional, unlogged=persistence == "unlogged", populated=with_data, fixture=fixture)


def finite_ids(_, spec, _tag):
    fixed = spec["finite"]
    expected = set(product(*(fixed[name] for name in AXES)))
    require(len(expected) == fixed["identities"], "postgres-create-as-grid-size", len(expected))
    return expected


def finite_id(_, reply, item):
    definition = reply["createTableAs"]
    return (item["fixture"], definition["ifNotExists"], "values" if "postgresValues" in definition["query"] else "base",
            "unlogged" if definition["unlogged"] else "logged", definition["withData"])


def analyzed_request(tag, sql):
    return {**request(tag, sql), "declarations": [f"CREATE TABLE {SCHEMA}.{SOURCE}(id integer, label pg_catalog.text)"]}


def analyzed(tag):
    yield f"CREATE TABLE {NAME} AS SELECT 5 AS id, 2 AS wide", creation(tag, literal_scan(5, 2), ["id", "wide"])
    yield f"CREATE TABLE {NAME} AS SELECT id,label FROM {SOURCE}", creation(tag, query("base"))
    yield f"CREATE UNLOGGED TABLE {SCHEMA}.{NAME} USING heap AS SELECT id,label FROM {SOURCE}", creation(tag, query("base"), unlogged=True)
    values = "(19,'value'),(19,'value'),(CAST(NULL AS integer),NULL)"
    yield f"CREATE TABLE {NAME} (exposed) AS VALUES {values}", creation(tag, query("values"), ["exposed", "column2"])
    yield f"CREATE TABLE {NAME} AS SELECT id,label FROM {SOURCE} WITH NO DATA", creation(tag, query("base"), populated=False)
    yield f"CREATE TABLE IF NOT EXISTS {NAME} AS SELECT id,label FROM {SOURCE}", creation(tag, query("base"), optional=True, fixture="view")
    yield f"CREATE TABLE {NAME} AS SELECT id,label FROM {SOURCE} WHERE id > 0", creation(tag, query("filtered"))
    reversed_scan = query("base", (1, 0))
    reversed_scan["source"] = {**reversed_scan["source"], "columns": ["label", "id"], "columnKinds": ["text", "int4"]}
    reversed_scan["columns"] = [[["text-slot", 0, 0], "label"], [["slot", 1], "id"]]
    yield f'CREATE UNLOGGED TABLE {NAME} ("K","k") AS SELECT label,id FROM {SOURCE}', creation(tag, reversed_scan, ["K", "k"], unlogged=True)


def negatives(tag):
    normal = creation(tag, query("base"))
    yield {**normal, "createTableAs": {**normal["createTableAs"], "target": ["pg_temp_12", NAME]}}, \
        "A persistent PostgreSQL relation requires an absolute schema."
    yield creation(tag, query("base"), ["only"]), "The new table heading must match its query within 1600 columns."
    yield creation(tag, query("values"), ["same", "same"]), "CREATE TABLE AS cannot repeat a column name."
    parameter = scan(None, [[["parameter", 1], "bound"]], source=False)
    yield creation(tag, parameter, ["bound"]), "CREATE TABLE AS requires a parameter-free defining query."
    yield analyzed_request(tag, f"CREATE TEMP TABLE {NAME} AS SELECT 5 AS item"), "CREATE TABLE AS requires a persistent table."
    for clause in ("WITH (fillfactor=80)", "ON COMMIT DROP"):
        yield analyzed_request(tag, f"CREATE TABLE {NAME} {clause} AS SELECT 5 AS item"), \
            "CREATE TABLE AS requires native heap storage, the default tablespace and no commit action."


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        role = ("base", "values", "filtered", "literal")[index % 4]
        order = (1, 0) if rng.randrange(2) else (0, 1)
        definition = literal_scan(rng.randrange(97), rng.randrange(97)) if role == "literal" else query(role, order)
        names = HEADING if rng.randrange(2) else [f"Title {index}", f"title {index}"]
        yield creation(tag, definition, names, bool(rng.randrange(2)), bool(rng.randrange(2)), bool(rng.randrange(2)),
                       rng.choice(("absent", "table", "view")))


def requests(_, tag, spec):
    for index, dimensions in enumerate(product(*(spec["finite"][axis] for axis in AXES))):
        item = finite(tag, *dimensions)
        yield f"finite/{index}", item, item, None
    author = list(analyzed(tag))
    require(len(author) == len(spec["analysis"]), "postgres-create-as-analysis-count")
    for index, (sql, item) in enumerate(author):
        yield f"analyze/{index}", analyzed_request(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    malformed = list(negatives(tag))
    require(len(malformed) == len(spec["negative"]), "postgres-create-as-negative-count")
    for index, (item, message) in enumerate(malformed):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTIC, "message": message}


def table(rows, fields=FIXTURE_FIELDS, unlogged=False, kind="r"):
    return {"kind": kind, "persistence": "u" if unlogged else "p", "access": "heap" if kind == "r" else None,
            "fields": fields, "rows": sorted(rows, key=json.dumps)}


def initial(fixture):
    result = {DECOY: table([[13, "decoy"]])}
    if fixture != "absent":
        result[SCHEMA] = table([[17, "retained"]], kind="v" if fixture == "view" else "r")
    return result


def state(tables, stage, active):
    return {"tables": tables, "source": ordered_base(phase_rows(stage)), "marker": [0, 7] if active else [0]}


def prediction(item):
    definition = item["createTableAs"]
    original = initial(item["fixture"])
    created = dict(original)
    effect, preserved = ["executed"], None
    if SCHEMA in original:
        preserved = True
        if not definition["ifNotExists"]:
            effect = ["db-error", "42P07"]
    else:
        fields, rows = definition_rows(definition)
        heading = [[*field, False] for field in fields]
        created[SCHEMA] = table(rows if definition["withData"] else [], heading, definition["unlogged"])
    written = dict(created)
    target = created[SCHEMA]
    if target["kind"] == "r":
        written[SCHEMA] = {**target, "rows": sorted([*target["rows"], [None] * len(target["fields"])], key=json.dumps)}
        write = ["executed"]
    else:
        write = ["db-error", "55000"]
    return {
        "status": "create-table-as-effect", "objectScope": [SCHEMA, SCHEMA, ["pg_catalog", SCHEMA], "heap", ""],
        "original": state(original, 8, False), "before": state(original, 12, True),
        "effect": effect, "after": state(created, 12, True), "preserved": preserved, "write": write,
        "later": state(written, 21, True), "active": True, "restored": state(original, 8, False),
    }


def check_value(item, response):
    require(response["status"] == "constructed" and response["profile"] == item["release"] + "/utf8/standard-conforming-strings/native",
            "postgres-create-as-positive-rejected", response)
    definition = item["createTableAs"]
    same({**definition, "query": graph(definition["query"])}, response["createTableAs"])


def normalize(state):
    return {**state, "tables": {name: {**rel, "rows": sorted(rel["rows"], key=json.dumps)} for name, rel in state["tables"].items()}}


def observation(raw):
    if raw["status"] != "create-table-as-effect":
        return raw
    phases = ("original", "before", "after", "later", "restored")
    return {**raw, **{phase: normalize(raw[phase]) for phase in phases}}


def observer_request(item, sql):
    return {"sql": sql, "fixture": item["fixture"]}


def prepare(_, db):
    return {"initial": db.environment["initial"]}


def check_preparation(_, environment, preparation):
    same(environment["initial"], preparation["initial"])
    same(state(initial("table"), 8, False), normalize(preparation["initial"]))


def check_observation(item, actual, sql):
    expected = prediction(item)
    witness = {"sql": sql, "expected": expected, "observed": actual}
    require(actual["status"] == "create-table-as-effect", "postgres-create-as-observer-rejected", witness)
    try:
        same(expected, actual)
    except Failure as error:
        raise Failure(error.signal, witness) from error


def sample_forms(forms, reply, _, item):
    definition = reply["createTableAs"]
    query = definition["query"]
    body = "values" if "postgresValues" in query else (
        "literal" if query["source"] is None else ("filtered" if query["where"] is not None else "base"))
    for dimension, choice in [("fixture", item["fixture"]), ("definition", body), ("population", definition["withData"]),
                              ("unlogged", definition["unlogged"]), ("optional", definition["ifNotExists"])]:
        forms[f"{dimension}/{choice}"] += 1


def required_forms(*_):
    return {*(f"fixture/{fixture}" for fixture in ("absent", "table", "view")),
            *(f"definition/{kind}" for kind in ("values", "literal", "filtered", "base")),
            *(f"{dimension}/{choice}" for dimension in ("population", "unlogged", "optional") for choice in (True, False))}


def controls():
    item = creation("pg-17.2", query("base"), populated=False)
    check_observation(item, prediction(item), "correct empty writable table")
    filled = prediction(creation("pg-17.2", query("base"), populated=True))
    try:
        check_observation(item, filled, "WITH NO DATA still copied the input")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"with-no-data-copied-rows": {"input": item, **error.detail}}
    raise Failure("postgres-create-as-comparator-missed-rows")


def boundary(item, reply):
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "postgres-create-as-mutant-response", reply)
    raise Failure("postgres-create-as-invalid-published", {"sql": reply["sql"], "observed": reply, "expected": item["boundary"]})


def fault_probe(item, reply, db):
    if "boundary" in item:
        boundary(item, reply)
    else:
        probe(item, reply, db, sys.modules[__name__])


def replay_fault(rule, record):
    witness = record["detail"]
    try:
        if "boundary" in rule.item:
            boundary(rule.item, record["healthyResponse"])
            boundary(rule.item, record["mutantResponse"])
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            same(prediction(rule.item), witness["expected"])
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("postgres-create-as-missed-fault", rule.name)


def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/Schema/Query/CreateTableAs.php"
    invalid, message = list(negatives(tag))[1]
    return [
        RenderFault("ctas-erase-persistence", path, "($this->unlogged ? 'UNLOGGED ' : '')", "''",
                    creation(tag, query("base"), unlogged=True), "meaning-mismatch"),
        RenderFault("ctas-copy-despite-empty", path, "($this->withData ? ' WITH DATA' : ' WITH NO DATA')", "' WITH DATA'",
                    creation(tag, query("values"), populated=False), "meaning-mismatch"),
        RenderFault("ctas-promote-decoy-identity", path, "$this->target->quoted()", "'\"ir_ct_decoy\".\"ir_ct_target\"'",
                    creation(tag, query("base")), "meaning-mismatch"),
        RenderFault("ctas-erase-optional", path, "($this->ifNotExists ? 'IF NOT EXISTS ' : '')", "''",
                    creation(tag, query("base"), optional=True, fixture="view"), "meaning-mismatch"),
        RenderFault("ctas-reverse-physical-heading", path, "implode(', ', $columns)", "implode(', ', array_reverse($columns))",
                    creation(tag, query("base")), "meaning-mismatch"),
        RenderFault("ctas-admit-invalid-heading", path, "count($heading) === $width && $width <= 1600,", "true,",
                    {"boundary": {"exception": DIAGNOSTIC, "message": message}}, "postgres-create-as-invalid-published", invalid),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
