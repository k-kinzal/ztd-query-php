"""Compare PostgreSQL view declarations against an independent transactional catalog model."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import main, probe
from run_server_postgres_local_values import graph, request, values
from run_server_postgres_values import (
    DIAGNOSTIC, TYPES, cell, predict as value_rows, specifications as profiles, typed_null,
)
from run_server_postgres_with_projection import literal_scan, scan
from sqlite_domain import RenderFault

LABEL = "postgres-create-view"
OBSERVER = "postgres_create_view_observer.php"
CONTRACT_NAME = "postgres-create-view-v2.json"
CONTRACT = "4433c5665a8860047588ef378b3dcfeecb0bc0b908bdfa8dcabca410da37024d"
COMPLETE_SEEDS = True
AXES = ("policy", "fixture", "body", "securityBarrier", "securityInvoker")
BOOL = {"omitted": None, "enabled": True, "disabled": False}
SCHEMA, DECOY = "public", "ir_cv_decoy"
NAME = "ir_cv_view"
TARGET = [SCHEMA, NAME]
SOURCE = "ir_cv_input"
DECLARATIONS = [f"CREATE TABLE {SCHEMA}.{SOURCE} (id integer, label pg_catalog.text)"]
BASE = [[None, "null"], [3, "same"], [3, "same"], [8, "changed"]]
HEADING = ["id", "label"]
BASE_FIELDS = [["id", *TYPES["integer"]], ["label", *TYPES["text"]]]
BASE_RELATION = {"schema": SCHEMA, "table": SOURCE, "columns": HEADING, "columnKinds": ["int4", "text"], "only": False}
CHECKS = ("", "LOCAL", "CASCADED")
DEFAULT_ROWS = [[cell("integer", "19"), cell("text", "value")], [cell("integer", "19"), cell("text", "value")],
                [typed_null("integer"), ["null"]]]


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-create-view-contract-mismatch")
    return json.loads(path.read_text()), profiles()[1]


def query(body, order=(0, 1)):
    if body == "values":
        return values([[row[slot] for slot in order] for row in DEFAULT_ROWS])
    pairs = [[["slot", 0], "id"], [["text-slot", 1, 0], "label"]]
    projection = scan(None, [pairs[slot] for slot in order], source=False)
    projection["source"] = BASE_RELATION
    if body == "base-filtered":
        projection["where"] = ["comparison", ">", ["slot", 0], ["integer", "0"]]
    return projection


def case(tag, definition, columns=HEADING, replace=False, existing=False, barrier=None, invoker=None, check="", write=False):
    return {
        "dialect": "postgres", "release": tag, "existing": existing, "write": write,
        "createView": {
            "target": TARGET, "query": definition, "columns": columns, "replace": replace,
            "securityBarrier": barrier, "securityInvoker": invoker, "checkOption": check,
        },
    }


def finite(tag, policy, fixture, body, barrier, invoker):
    return case(tag, query(body), replace=policy == "replace", existing=fixture == "existing",
                barrier=BOOL[barrier], invoker=BOOL[invoker])


def finite_ids(_, spec, _tag):
    product_ids = set(product(*(spec["finite"][dimension] for dimension in AXES)))
    require(len(product_ids) == spec["finite"]["identities"], "postgres-create-view-grid")
    return product_ids


def body_kind(body):
    if "postgresValues" in body:
        return "values"
    if body["source"] is None:
        return "constant-select"
    return "base-all" if body["where"] is None else "base-filtered"


def finite_id(_, reply, item):
    definition = reply["createView"]
    return ("replace" if definition["replace"] else "require-new", "existing" if item["existing"] else "absent",
            body_kind(definition["query"]),
            *(next(key for key, value in BOOL.items() if value is definition[option]) for option in ("securityBarrier", "securityInvoker")))


def analyze_request(tag, sql):
    return {**request(tag, sql), "declarations": DECLARATIONS}


def analyzed(tag):
    source = f"SELECT id,label FROM {SOURCE}"
    filtered = f"{source} WHERE id > 0"
    yield f"CREATE VIEW {NAME} AS {source}", case(tag, query("base-all"))
    yield f"CREATE VIEW {NAME} (n) AS {source}", case(tag, query("base-all"), ["n", "label"])
    yield f"CREATE VIEW {NAME} WITH (security_barrier=false, security_invoker=on) AS {source}", case(
        tag, query("base-all"), barrier=False, invoker=True)
    yield (f"CREATE VIEW {NAME} (id,label) AS VALUES (19,'value'), (19,'value'), (CAST(NULL AS integer),NULL)",
           case(tag, query("values")))
    yield f"CREATE VIEW {NAME} AS {filtered} WITH CHECK OPTION", case(
        tag, query("base-filtered"), check="CASCADED", write=True)
    yield f"CREATE VIEW {NAME} AS {filtered} WITH LOCAL CHECK OPTION", case(
        tag, query("base-filtered"), check="LOCAL", write=True)
    yield f"CREATE OR REPLACE VIEW {NAME} AS {source}", case(tag, query("base-all"), replace=True, existing=True)
    yield f"CREATE OR REPLACE VIEW {NAME} (changed,label) AS {source}", case(
        tag, query("base-all"), ["changed", "label"], replace=True, existing=True)


def negatives(tag):
    yield case(tag, query("values"), []), "A persistent view needs a nonempty ordered heading."
    yield case(tag, query("values"), ["id"]), "The view heading must match its query"
    yield case(tag, query("values"), ["dup", "dup"]), "A persistent view cannot repeat a column name."
    parameter = scan(None, [[["parameter", 1], "bound"]], source=False)
    yield case(tag, parameter, ["bound"]), "A persistent view definition cannot bind a native parameter."
    yield case(tag, query("values"), check="LOCAL"), "A view check option requires a simple base-table projection."
    yield analyze_request(tag, f"CREATE TEMP VIEW {NAME} AS SELECT 1 AS n"), "CREATE VIEW requires a persistent ordinary view"
    yield analyze_request(tag, f"CREATE VIEW {NAME} WITH (unexpected=true) AS SELECT 1 AS n"), "CREATE VIEW requires a distinct supported native option."


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        choice = ("base-all", "base-filtered", "values", "constant-select")[index % 4]
        positions = (0, 1) if rng.randrange(2) else (1, 0)
        if choice == "constant-select":
            selected = literal_scan(rng.randrange(50), rng.randrange(50))
            selected["columns"] = [selected["columns"][position] for position in positions]
        else:
            selected = query(choice, positions)
        labels = HEADING if rng.randrange(2) else [f"quoted {index}", "value"]
        check = CHECKS[rng.randrange(len(CHECKS))] if choice.startswith("base") else ""
        yield case(tag, selected, labels, bool(rng.randrange(2)), bool(rng.randrange(2)),
                   rng.choice(list(BOOL.values())), rng.choice(list(BOOL.values())), check)


def requests(_, tag, spec):
    for index, dimensions in enumerate(product(*(spec["finite"][axis] for axis in AXES))):
        item = finite(tag, *dimensions)
        yield f"finite/{index}", item, item, None
    written = list(analyzed(tag))
    require(len(written) == len(spec["analysis"]), "postgres-create-view-analysis-count")
    for index, (sql, item) in enumerate(written):
        yield f"analyze/{index}", analyze_request(tag, sql), item, None
    require(len(spec["writeProbes"]) == 1, "postgres-create-view-write-probe-contract")
    for index, mode in enumerate(CHECKS):
        item = case(tag, query("base-filtered"), check=mode, write=True)
        yield f"write/{index}", item, item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    invalid = list(negatives(tag))
    require(len(invalid) == len(spec["negative"]), "postgres-create-view-negative-count")
    for index, (item, message) in enumerate(invalid):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTIC, "message": message}


def options(definition=None, prior=False):
    return ({"securityBarrier": None, "securityInvoker": True if prior else None, "checkOption": ""}
            if definition is None else {key: definition[key] for key in ("securityBarrier", "securityInvoker", "checkOption")})


def view(fields, rows, flags, updatable):
    return {"kind": "v", "updatable": updatable, "insertable": updatable, "check": flags["checkOption"] or "NONE",
            "options": flags, "fields": fields, "rows": sorted(rows, key=json.dumps)}


def ordered_base(rows):
    return sorted(rows, key=lambda row: (row[0] is not None, 0 if row[0] is None else row[0], row[1]))


def snapshot(existing, rows=BASE, active=False):
    views = {DECOY: view(BASE_FIELDS, [[73, "decoy"]], options(), False)}
    if existing:
        views[SCHEMA] = view(BASE_FIELDS, [[37, "prior"]], options(prior=True), False)
    return {"views": views, "base": ordered_base(rows), "marker": [0, 7] if active else [0]}


def body_result(definition, records):
    q = definition["query"]
    if "postgresValues" in q:
        data = value_rows(q)
        return view([[label, *field[1:]] for label, field in zip(definition["columns"], data["fields"])],
                    data["rows"], options(definition), False)

    found = q["source"] is not None
    selected = q["columns"]
    if found:
        slots = [term[1] for term, _ in selected]
        require(q["where"] is None or q["where"] == ["comparison", ">", ["slot", 0], ["integer", "0"]],
                "postgres-view-oracle-filter")
        filtered = records if q["where"] is None else [row for row in records if row[0] is not None and row[0] > 0]
        fields = [[label, *BASE_FIELDS[slot][1:]] for label, slot in zip(definition["columns"], slots)]
        data = [[row[slot] for slot in slots] for row in filtered]
    else:
        fields = [[label, *TYPES["integer"]] for label in definition["columns"]]
        data = [[int(term[1]) for term, _ in selected]]
    return view(fields, data, options(definition), found)


def prediction(item):
    definition = item["createView"]
    initial = snapshot(item["existing"])
    proposed = body_result(definition, BASE)
    if item["existing"] and not definition["replace"]:
        result = ["db-error", "42P07"]
    elif item["existing"] and proposed["fields"] != BASE_FIELDS:
        result = ["db-error", "42P16"]
    else:
        result = ["created"]
    executed = result == ["created"]
    after = snapshot(item["existing"], active=True)
    if executed:
        after["views"][SCHEMA] = proposed
    write = None
    changed = [[11 if key == 8 else key, label] for key, label in BASE]
    if item["write"] and executed:
        write = ["db-error", "44000"] if definition["checkOption"] else ["written"]
        if write == ["written"]:
            changed.append([-7, "hidden"])
    updated = snapshot(item["existing"], changed, active=True)
    if executed:
        updated["views"][SCHEMA] = body_result(definition, changed)
    return {"status": "view-declaration", "creationScope": ["public", "public", ["pg_catalog", "public"]],
            "result": result, "write": write, "active": True,
            "sameIdentity": True if item["existing"] else None, "before": initial, "created": after,
            "afterUpdate": updated, "restored": initial}


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"] == item["release"] + "/utf8/standard-conforming-strings/native",
            "postgres-create-view-rejected", reply)
    model = item["createView"]
    same({**model, "query": graph(model["query"])}, reply["createView"])


def observation(raw):
    if raw["status"] != "view-declaration":
        return raw
    normalized = dict(raw)
    for key in ("before", "created", "afterUpdate", "restored"):
        state = raw[key]
        normalized[key] = {**state, "views": {name: {**view, "rows": sorted(view["rows"], key=json.dumps)}
                                             for name, view in state["views"].items()}}
    return normalized


def observer_request(item, sql):
    return {"sql": sql, "existing": item["existing"], "write": item["write"]}


def prepare(_, db):
    return {"initial": db.environment["initial"]}


def check_preparation(_, environment, preparation):
    same(snapshot(True), preparation["initial"])
    same(environment["initial"], preparation["initial"])


def check_observation(item, actual, sql):
    expected = prediction(item)
    witness = {"sql": sql, "expected": expected, "observed": actual}
    require(actual["status"] == "view-declaration", "postgres-create-view-observer-rejected", witness)
    try:
        same(expected, actual)
    except Failure as error:
        raise Failure(error.signal, witness) from error


def sample_forms(forms, reply, _, item):
    loaded = reply["createView"]
    forms[f"body/{body_kind(loaded['query'])}"] += 1
    forms[f"check/{loaded['checkOption']}"] += 1
    forms[f"existing/{item['existing']}"] += 1
    forms[f"replace/{loaded['replace']}"] += 1
    for flag in ("securityBarrier", "securityInvoker"):
        forms[f"{flag}/{loaded[flag]}"] += 1
    forms[f"heading/{loaded['columns'] == HEADING}"] += 1


def required_forms(*_):
    return {*(f"body/{form}" for form in ("base-all", "base-filtered", "values", "constant-select")),
            *(f"check/{mode}" for mode in CHECKS),
            *(f"{key}/{value}" for key in ("existing", "replace", "heading") for value in (True, False)),
            *(f"{key}/{value}" for key in ("securityBarrier", "securityInvoker") for value in (True, False, None))}


def controls():
    item = case("pg-17.2", query("base-filtered"), check="LOCAL", write=True)
    correct = prediction(item)
    check_observation(item, correct, "view control")
    wrong = prediction({**item, "createView": {**item["createView"], "checkOption": ""}})
    try:
        check_observation(item, wrong, "lost check effect")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"lost-write-effect": {"input": item, **error.detail}}
    raise Failure("postgres-create-view-comparator-undetected")


def boundary(item, reply):
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "postgres-create-view-mutant-response", reply)
    raise Failure("postgres-create-view-invalid-published", {"sql": reply["sql"], "observed": reply, "expected": item["boundary"]})


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
    raise Failure("postgres-create-view-undetected-fault", rule.name)


def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/Schema/View/CreateView.php"
    invalid, message = list(negatives(tag))[2]
    return [
        RenderFault("postgres-view-substitute-target", path, "$this->name->quoted()", "'\"ir_cv_decoy\".\"ir_cv_view\"'",
                    case(tag, query("base-all")), "meaning-mismatch"),
        RenderFault("postgres-view-switch-heading", path, "implode(', ', $columns)", "implode(', ', array_reverse($columns))",
                    case(tag, query("base-all")), "meaning-mismatch"),
        RenderFault("postgres-view-erase-replacement", path, "($this->replace ? 'OR REPLACE ' : '')", "''",
                    case(tag, query("base-all"), replace=True, existing=True), "meaning-mismatch"),
        RenderFault("postgres-view-erase-invoker", path, "$this->securityInvoker !== null", "false",
                    case(tag, query("base-all"), invoker=True), "meaning-mismatch"),
        RenderFault("postgres-view-lose-check", path, ". $check;", ";",
                    case(tag, query("base-filtered"), check="LOCAL", write=True), "meaning-mismatch"),
        RenderFault("postgres-view-accept-duplicate-heading", path.replace("CreateView.php", "ViewHeading.php"), "!isset($seen[$column->value]),", "true,",
                    {"boundary": {"exception": DIAGNOSTIC, "message": message}}, "postgres-create-view-invalid-published", invalid),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
