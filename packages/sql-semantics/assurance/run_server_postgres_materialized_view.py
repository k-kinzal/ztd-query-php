"""Compare native PostgreSQL materialized view snapshots with a catalog state model."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import main, probe
from run_server_postgres_create_view import BASE, BASE_FIELDS, HEADING, ordered_base, query as view_query
from run_server_postgres_local_values import graph, request
from run_server_postgres_values import DIAGNOSTIC, TYPES, predict as evaluate_values, specifications as profiles
from run_server_postgres_with_projection import literal_scan, scan
from sqlite_domain import RenderFault

LABEL = "postgres-materialized-view"
OBSERVER = "postgres_materialized_view_observer.php"
CONTRACT_NAME = "postgres-materialized-view-v1.json"
CONTRACT = "b4fa05f96b6348b486f07924beabd4208e7a4aa1fed524a8385e1b2109e47e47"
COMPLETE_SEEDS = True
SCHEMA, DECOY_SCHEMA = "public", "ir_mv_decoy"
NAME, INPUT = "ir_mv_view", "ir_mv_input"
MAIN, DECOY = [SCHEMA, NAME], [DECOY_SCHEMA, NAME]
SEMANTIC = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\SemanticException"
AXES = {"create": ("fixture", "ifNotExists", "body", "withData"), "refresh": ("fixture", "target", "withData")}
DECLARATIONS = [f"CREATE TABLE {SCHEMA}.{INPUT} (id integer, label pg_catalog.text)",
                f"CREATE MATERIALIZED VIEW {SCHEMA}.{NAME} AS SELECT id,label FROM {SCHEMA}.{INPUT}",
                f"CREATE MATERIALIZED VIEW {DECOY_SCHEMA}.{NAME} AS SELECT id,label FROM {SCHEMA}.{INPUT}",
                f"CREATE VIEW {SCHEMA}.ordinary AS SELECT id,label FROM {SCHEMA}.{INPUT}"]


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-materialized-contract-mismatch")
    return json.loads(path.read_text()), profiles()[1]


def query(role, order=(0, 1)):
    if role == "values":
        return view_query("values", order)
    result = view_query("base-filtered" if role == "filtered" else "base-all", order)
    result["source"] = {**result["source"], "table": INPUT}
    return result


def creation(tag, defining, columns=HEADING, optional=False, populated=True, fixture="absent"):
    return {"dialect": "postgres", "release": tag, "fixture": fixture,
            "materialization": {"operation": "create", "target": MAIN, "withData": populated,
                                "query": defining, "columns": columns, "ifNotExists": optional}}


def refresh(tag, target, populated=True, fixture="populated"):
    return {"dialect": "postgres", "release": tag, "fixture": fixture,
            "materialization": {"operation": "refresh", "target": target, "withData": populated}}


def finite(tag, operation, *dimensions):
    if operation == "create":
        fixture, optional, role, with_data = dimensions
        return creation(tag, query(role), optional=optional, populated=with_data, fixture=fixture)
    fixture, target, with_data = dimensions
    return refresh(tag, MAIN if target == "main" else DECOY, with_data, fixture)


def finite_ids(_, spec, _tag):
    identities = set()
    for operation, axes in AXES.items():
        fixed = spec["finite"][operation]
        choices = set(product(*(fixed[axis] for axis in axes)))
        require(len(choices) == fixed["identities"], "postgres-materialized-grid-size", operation)
        identities.update((operation, *choice) for choice in choices)
    return identities


def finite_id(_, reply, item):
    action = reply["materialization"]
    if action["operation"] == "create":
        role = "values" if "postgresValues" in action["query"] else "base"
        return "create", item["fixture"], action["ifNotExists"], role, action["withData"]
    return "refresh", item["fixture"], "main" if action["target"] == MAIN else "decoy", action["withData"]


def analyzed_request(tag, sql):
    return {**request(tag, sql), "declarations": DECLARATIONS}


def analyzed(tag):
    base = f"SELECT id,label FROM {INPUT}"
    yield f"CREATE MATERIALIZED VIEW {NAME} AS {base}", creation(tag, query("base"))
    values = "(19,'value'),(19,'value'),(CAST(NULL AS integer),NULL)"
    yield f"CREATE MATERIALIZED VIEW {NAME} (id,label) AS VALUES {values}", creation(tag, query("values"))
    yield f"CREATE MATERIALIZED VIEW {NAME} USING heap AS {base} WITH NO DATA", creation(tag, query("base"), populated=False)
    yield f"CREATE MATERIALIZED VIEW IF NOT EXISTS {NAME} AS {base} WITH DATA", creation(
        tag, query("base"), optional=True, fixture="unpopulated")
    yield f"REFRESH MATERIALIZED VIEW {NAME}", refresh(tag, MAIN)
    yield f"REFRESH MATERIALIZED VIEW {SCHEMA}.{NAME} WITH NO DATA", refresh(tag, MAIN, False)
    yield f"REFRESH MATERIALIZED VIEW {DECOY_SCHEMA}.{NAME} WITH DATA", refresh(tag, DECOY, True, "unpopulated")
    yield f"CREATE MATERIALIZED VIEW {NAME} (selected) AS {base}", creation(tag, query("base"), ["selected", "label"])


def negatives(tag):
    yield creation(tag, query("base"), ["n"]), DIAGNOSTIC, "The view heading must match its query"
    yield creation(tag, query("values"), ["n", "n"]), DIAGNOSTIC, "A persistent view cannot repeat a column name."
    parameter = scan(None, [[["parameter", 1], "bound"]], source=False)
    yield creation(tag, parameter, ["bound"]), DIAGNOSTIC, "A persistent view definition cannot bind a native parameter."
    yield analyzed_request(tag, f"CREATE MATERIALIZED VIEW {NAME} WITH (fillfactor=80) AS SELECT 1 AS item"), DIAGNOSTIC, (
        "CREATE MATERIALIZED VIEW requires persistent native heap storage and the default tablespace.")
    yield analyzed_request(tag, f"REFRESH MATERIALIZED VIEW CONCURRENTLY {NAME}"), DIAGNOSTIC, "A checked materialized refresh must be nonconcurrent."
    yield analyzed_request(tag, "REFRESH MATERIALIZED VIEW ordinary"), SEMANTIC, "is not a table or materialized view"
    yield analyzed_request(tag, "REFRESH MATERIALIZED VIEW absent"), SEMANTIC, "Relation absent does not exist."


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        populated = bool(rng.randrange(2))
        if index % 2 == 0:
            role = ("base", "filtered", "values", "literal")[index // 2 % 4]
            order = (1, 0) if rng.randrange(2) else (0, 1)
            if role == "literal":
                definition = literal_scan(rng.randrange(88), rng.randrange(88))
            else:
                definition = query(role, order)
            names = HEADING if rng.randrange(2) else [f"ordered {index}", "value"]
            yield creation(tag, definition, names, bool(rng.randrange(2)), populated, rng.choice(("absent", "populated", "unpopulated")))
        else:
            target = MAIN if rng.randrange(2) else DECOY
            yield refresh(tag, target, populated, rng.choice(("absent", "ordinary", "populated", "unpopulated")))


def requests(_, tag, spec):
    for op, axes in AXES.items():
        for index, dimensions in enumerate(product(*(spec["finite"][op][axis] for axis in axes))):
            item = finite(tag, op, *dimensions)
            yield f"finite/{op}/{index}", item, item, None
    author = list(analyzed(tag))
    require(len(author) == len(spec["analysis"]), "postgres-materialized-analysis-count")
    for index, (sql, item) in enumerate(author):
        yield f"analyze/{index}", analyzed_request(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    malformed = list(negatives(tag))
    require(len(malformed) == len(spec["negative"]), "postgres-materialized-negative-count")
    for index, (item, exception, message) in enumerate(malformed):
        yield f"negative/{index}", item, None, {"exception": exception, "message": message}


def materialized(fields, rows, populated):
    return {"kind": "m", "populated": populated, "access": "heap", "fields": fields,
            "read": ["rows", sorted(rows, key=json.dumps)] if populated else ["db-error", "55000"]}


def phase_rows(last, decoy=False):
    offset = 70 if decoy else 0
    return [[None if key is None else (last if key == 8 else key) + offset, text] for key, text in BASE]


def initial_views(fixture):
    views = {DECOY_SCHEMA: materialized(BASE_FIELDS, phase_rows(8, True), True)}
    if fixture == "ordinary":
        views[SCHEMA] = {"kind": "v", "populated": True, "access": None,
                         "fields": BASE_FIELDS, "read": ["rows", sorted(BASE, key=json.dumps)]}
    elif fixture != "absent":
        views[SCHEMA] = materialized(BASE_FIELDS, BASE, fixture == "populated")
    return views


def current(views, last, active):
    present = dict(views)
    main = present.get(SCHEMA)
    if main and main["kind"] == "v":
        present[SCHEMA] = {**main, "read": ["rows", sorted(phase_rows(last), key=json.dumps)]}
    return {"views": present, "base": ordered_base(phase_rows(last)), "marker": [0, 7] if active else [0]}


def definition_rows(action):
    body = action["query"]
    heading = action["columns"]
    if "postgresValues" in body:
        values_result = evaluate_values(body)
        return ([[label, *field[1:]] for label, field in zip(heading, values_result["fields"])], values_result["rows"])
    if body["source"] is None:
        return [[label, *TYPES["integer"]] for label in heading], [[int(term[1]) for term, _ in body["columns"]]]
    input_fields = body["source"]["columns"]
    slots = [HEADING.index(input_fields[term[1]]) for term, _ in body["columns"]]
    rows = phase_rows(12)
    if body["where"] is not None:
        require(body["where"] == ["comparison", ">", ["slot", input_fields.index("id")], ["integer", "0"]],
                "postgres-materialized-oracle-filter")
        rows = [row for row in rows if row[0] is not None and row[0] > 0]
    return [[name, *BASE_FIELDS[slot][1:]] for name, slot in zip(heading, slots)], [[row[slot] for slot in slots] for row in rows]


def prediction(item):
    action = item["materialization"]
    fixture = item["fixture"]
    target = action["target"][0]
    original = initial_views(fixture)
    result = dict(original)
    effect = ["executed"]
    if action["operation"] == "create":
        if target in original:
            if not action["ifNotExists"]:
                effect = ["db-error", "42P07"]
        else:
            fields, rows = definition_rows(action)
            result[target] = materialized(fields, rows, action["withData"])
    elif target not in original:
        effect = ["db-error", "42P01"]
    elif original[target]["kind"] == "v":
        effect = ["db-error", "42809"]
    else:
        result[target] = materialized(BASE_FIELDS, phase_rows(12, target == DECOY_SCHEMA), action["withData"])
    return {
        "status": "materialized-view-effect", "objectScope": ["public", "public", ["pg_catalog", "public"]],
        "original": current(original, 8, False), "before": current(original, 12, True), "effect": effect,
        "after": current(result, 12, True), "later": current(result, 21, True), "active": True, "restored": current(original, 8, False),
    }


def check_value(item, response):
    require(response["status"] == "constructed" and response["profile"] == item["release"] + "/utf8/standard-conforming-strings/native",
            "postgres-materialized-positive-rejected", response)
    action = dict(item["materialization"])
    if action["operation"] == "create":
        action["query"] = graph(action["query"])
    same(action, response["materialization"])


def observation(raw):
    if raw["status"] != "materialized-view-effect":
        return raw
    def relation(view):
        return {**view, "read": ["rows", sorted(view["read"][1], key=json.dumps)]} if view["read"][0] == "rows" else view
    return {**raw, **{phase: {**raw[phase], "views": {name: relation(view) for name, view in raw[phase]["views"].items()}}
                      for phase in ("original", "before", "after", "later", "restored")}}


def observer_request(item, sql):
    return {"sql": sql, "fixture": item["fixture"]}


def prepare(_, db):
    return {"initial": db.environment["initial"]}


def check_preparation(_, environment, preparation):
    state = environment["initial"]
    same(state, preparation["initial"])
    normalized = {**state, "views": {name: {**view, "read": ["rows", sorted(view["read"][1], key=json.dumps)]}
                                     for name, view in state["views"].items()}}
    same(current(initial_views("populated"), 8, False), normalized)


def check_observation(item, actual, sql):
    expected = prediction(item)
    witness = {"sql": sql, "expected": expected, "observed": actual}
    require(actual["status"] == "materialized-view-effect", "postgres-materialized-observer-rejected", witness)
    try:
        same(expected, actual)
    except Failure as error:
        raise Failure(error.signal, witness) from error


def sample_forms(forms, reply, _, item):
    action = reply["materialization"]
    forms[f"operation/{action['operation']}"] += 1
    forms[f"fixture/{item['fixture']}"] += 1
    forms[f"population/{action['withData']}"] += 1
    forms[f"schema/{action['target'][0]}"] += 1
    if action["operation"] == "create":
        query = action["query"]
        kind = "values" if "postgresValues" in query else (
            "literal" if query["source"] is None else ("filtered" if query["where"] is not None else "base"))
        forms[f"definition/{kind}"] += 1
        forms[f"optional/{action['ifNotExists']}"] += 1


def required_forms(*_):
    return {*(f"operation/{op}" for op in AXES),
            *(f"fixture/{fixture}" for fixture in ("absent", "ordinary", "populated", "unpopulated")),
            *(f"definition/{kind}" for kind in ("values", "literal", "filtered", "base")),
            f"schema/{SCHEMA}", f"schema/{DECOY_SCHEMA}", "population/True", "population/False", "optional/True", "optional/False"}


def controls():
    item = refresh("pg-17.2", MAIN)
    correct = prediction(item)
    check_observation(item, correct, "materialization control")
    stale = prediction(refresh("pg-17.2", DECOY))
    try:
        check_observation(item, stale, "lookup redirected to the decoy")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"wrong-materialized-identity": {"input": item, **error.detail}}
    raise Failure("postgres-materialized-comparator-missed-view")


def boundary(item, reply):
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "postgres-materialized-mutant-response", reply)
    raise Failure("postgres-materialized-invalid-published", {"sql": reply["sql"], "observed": reply, "expected": item["boundary"]})


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
    raise Failure("postgres-materialized-missed-fault", rule.name)


def faults(_, tag):
    root = "sql-semantics-postgres/src/Ir/Schema/View"
    invalid, exception, message = list(negatives(tag))[0]
    footer = "$this->withData ? ' WITH DATA' : ' WITH NO DATA'"
    return [
        RenderFault("materialized-erase-creation-data", f"{root}/CreateMaterializedView.php", footer, "true ? ' WITH DATA' : ' WITH NO DATA'",
                    creation(tag, query("values"), populated=False), "meaning-mismatch"),
        RenderFault("materialized-erase-optional-collision", f"{root}/CreateMaterializedView.php", "($this->ifNotExists ? 'IF NOT EXISTS ' : '')", "''",
                    creation(tag, query("base"), optional=True, fixture="unpopulated"), "meaning-mismatch"),
        RenderFault("materialized-swap-heading", f"{root}/CreateMaterializedView.php", "implode(', ', $columns)", "implode(', ', array_reverse($columns))",
                    creation(tag, query("base")), "meaning-mismatch"),
        RenderFault("materialized-refresh-decoy", f"{root}/RefreshMaterializedView.php", "$this->name->quoted()", "'\"ir_mv_decoy\".\"ir_mv_view\"'",
                    refresh(tag, MAIN), "meaning-mismatch"),
        RenderFault("materialized-erase-refresh-data", f"{root}/RefreshMaterializedView.php", footer, "true ? ' WITH DATA' : ' WITH NO DATA'",
                    refresh(tag, MAIN, False), "meaning-mismatch"),
        RenderFault("materialized-admit-wrong-width", f"{root}/ViewHeading.php", "count($columns) === $width && $width <= 1600,", "true,",
                    {"boundary": {"exception": exception, "message": message}}, "postgres-materialized-invalid-published", invalid),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
