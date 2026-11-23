"""Compare MySQL persistent view creation with an independent catalog and row model."""

from itertools import product
import json
from random import Random
import sys

from evidence_transcript import canonical
from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_drop_table import specifications as native_profiles
from run_server_join import main, probe
from run_server_mysql_external_views import COLLATION, DIAGNOSTICS
from server_join_cases import compare, slot, truth, value
from sqlite_domain import RenderFault

LABEL = "mysql-create-view"
OBSERVER = "mysql_create_view_observer.php"
CONTRACT_NAME = "mysql-create-view-v2.json"
CONTRACT = "db587d801c66d83202d9a3841e293f2e82d47565d0fcdc230a6c9b46ec154108"
BASE_CONTRACT = "7465a47332cd41088ffc7ed5bc997353a35a553e8aade2c51e8392b09e904d1c"
COMPLETE_SEEDS = True
AXES = ("algorithm", "security", "policy", "body", "fixture")
ALGORITHMS = {"automatic": "UNDEFINED", "merge": "MERGE", "temporary": "TEMPTABLE"}
SCHEMA, MAIN, DECOY, BASE = "ir_fixture", "ir_cv_main", "ir_cv_decoy", "ir_cv_source"
TARGET = f"{SCHEMA}.{MAIN}"
ORIGIN = f"{SCHEMA}.{BASE}"
HEADING = ("id", "label")
TYPES = {"slot": ["int", None, None, "YES"], "text-slot": ["varchar", 8, COLLATION, "YES"],
         "integer": ["int", None, None, "NO"]}
DATA = [(1, "alpha"), (2, "beta"), (2, "beta"), (3, None), (None, "orphan")]
DECLARATIONS = [f"CREATE TABLE {ORIGIN} (id INT, label VARCHAR(8) COLLATE {COLLATION})"]
INVALID = DIAGNOSTICS + "InvalidConstruction"


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT and digest(TOOL / "mysql-create-view-v1.json") == BASE_CONTRACT, "mysql-create-view-contract-mismatch")
    return json.loads(path.read_text()), {"mysql": native_profiles()[1]["mysql"]}


def source(identity=BASE, relation="table"):
    return {"schema": SCHEMA, "table": identity, "columns": list(HEADING),
            "columnTypes": ["int", ["varchar", 8, COLLATION]], **({"relationKind": relation} if relation != "table" else {})}


def query(branch=None, columns=None, where=None):
    return {"source": branch, "columns": list(columns), "where": where, "distinct": False, "orderBy": [], "limit": None}


def scan(filtered=False, reversed_fields=False, threshold=1):
    columns = [[slot(0), "id"], [["text-slot", 1, 0], "label"]]
    return query(source(), columns[::-1] if reversed_fields else columns,
                 compare(">", slot(0), ["integer", str(threshold)]) if filtered else None)


def constant(number=42, *, where=None):
    return query(None, [[["integer", str(number)], "value"]], where)


def case(tag, body, fields=("id", "label"), policy="require-new", algorithm="UNDEFINED", security="DEFINER", existing=False):
    return {"dialect": "mysql", "release": tag, "existing": existing,
            "createView": {"target": [SCHEMA, MAIN], "query": body, "columns": list(fields),
                           "policy": policy, "algorithm": algorithm, "security": security}}


def finite(tag, algorithm, security, policy, body, fixture):
    return case(tag, scan(body == "base-filtered"), policy=policy, algorithm=ALGORITHMS[algorithm], security=security.upper(),
                existing=fixture == "existing-view")


def finite_ids(_, spec, _tag):
    grid = set(product(*(spec["finite"][axis] for axis in AXES)))
    require(len(grid) == spec["finite"]["identities"], "mysql-create-view-finite-size")
    return grid


def finite_id(_, actual, item):
    view = actual["createView"]
    return (next(name for name, role in ALGORITHMS.items() if role == view["algorithm"]), view["security"].lower(),
            view["policy"], "base-filtered" if view["query"]["where"] else "base-all", "existing-view" if item["existing"] else "absent")


def request(tag, sql):
    return {"dialect": "mysql", "release": tag, "sql": sql, "declarations": DECLARATIONS}


def analyzed(tag):
    statement = f"SELECT id,label FROM {ORIGIN}"
    yield f"CREATE VIEW {TARGET} AS {statement}", case(tag, scan())
    yield f"CREATE VIEW {TARGET} (item,detail) AS {statement}", case(tag, scan(), ("item", "detail"))
    yield f"CREATE ALGORITHM=MERGE SQL SECURITY DEFINER VIEW {TARGET} AS {statement}", case(tag, scan(), algorithm="MERGE")
    yield f"CREATE ALGORITHM=TEMPTABLE SQL SECURITY INVOKER VIEW {TARGET} AS {statement}", case(
        tag, scan(), algorithm="TEMPTABLE", security="INVOKER")
    yield f"CREATE VIEW {TARGET} AS {statement} WHERE id > 1", case(tag, scan(True))
    yield f"CREATE VIEW {TARGET} AS SELECT 42 AS value", case(tag, constant(), ("value",))
    yield f"CREATE OR REPLACE VIEW {TARGET} (item,detail) AS {statement}", case(
        tag, scan(), ("item", "detail"), policy="replace", existing=True)
    yield f"CREATE VIEW {TARGET} AS SELECT 42 AS value FROM DUAL WHERE FALSE", case(
        tag, constant(where=["boolean", False]), ("value",))


def optional(tag):
    sql = f"CREATE VIEW IF NOT EXISTS {TARGET} AS SELECT id,label FROM {ORIGIN}"
    for existing in (True, False):
        item = case(tag, scan(), policy="preserve", existing=existing)
        yield f"optional/{int(existing)}", request(tag, sql), item, None


def negatives(tag):
    body = scan()
    for item, message in (
        (case(tag, body, ("label",)), "The view heading must match the query width."),
        (case(tag, body, ("label", "Label")), "The view heading cannot repeat a column name."),
        (case(tag, query(None, [[["parameter", 1], "v"]]), ("v",)), "A checked view result must read a native field or a literal without parameters."),
        (case(tag, {**body, "source": source(MAIN)}), "A view cannot read its own target identity."),
        (case(tag, {**body, "source": source(relation="view")}), "A checked view reads one persistent base table or an independent literal projection."),
        (request(tag, f"CREATE DEFINER=CURRENT_USER VIEW {TARGET} AS SELECT id,label FROM {ORIGIN}"),
         "CREATE VIEW needs an implicit definer and one admitted creation policy without a check option."),
        (case(tag, {**body, "release": "mysql-9.0.1"}, policy="preserve"), "CREATE VIEW IF NOT EXISTS requires MySQL 9.1."),
    ):
        yield item, {"exception": INVALID, "message": message}


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        body = scan(index % 2 == 0, index % 3 == 0, rng.randrange(1, 3))
        labels = rng.choice((("item", "field"), ("code", "note"), ("whitespace label", "position")))
        yield case(tag, body, labels, rng.choice(("require-new", "replace")), rng.choice(tuple(ALGORITHMS.values())),
                   rng.choice(("DEFINER", "INVOKER")), bool(rng.getrandbits(1)))


def requests(_, tag, spec):
    for index, dimensions in enumerate(product(*(spec["finite"][axis] for axis in AXES))):
        item = finite(tag, *dimensions)
        yield f"finite/{index}", item, item, None
    inputs = list(analyzed(tag))
    require(len(inputs) == len(spec["analysis"]), "mysql-create-view-analysis-count")
    for index, (sql, item) in enumerate(inputs):
        yield f"analyze/{index}", request(tag, sql), item, None
    if tag in spec["optionalNativeForms"]:
        yield from optional(tag)
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, reason


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"), "mysql-create-view-rejected", reply)
    require(reply["createView"] == item["createView"], "mysql-create-view-graph", {"expected": item, "observed": reply, "sql": reply["sql"]})


def relation(body, names, algorithm, security, data):
    table = body["source"]
    rows = [(tuple(row),) for row in data] if table is not None else [()]
    fields = [[label, *TYPES[term[0]]] for (term, _), label in zip(body["columns"], names)]
    contents = [[value(term, row, "mysql") for term, _ in body["columns"]] for row in rows if truth(body["where"], row, "mysql")]
    return {"algorithm": algorithm, "security": security, "updatable": "YES" if table is not None and algorithm != "TEMPTABLE" else "NO",
            "check": "NONE", "columns": fields, "rows": sorted(contents, key=canonical)}


def fixture(existing, data, marker):
    views = {DECOY: relation(constant(71), ["decoy"], "UNDEFINED", "DEFINER", data)}
    if existing:
        saved = query(source(), [[slot(0), "id"]], compare(">", slot(0), ["integer", "2"]))
        views[MAIN] = relation(saved, ["original"], "TEMPTABLE", "INVOKER", data)
    return {"views": views, "marker": marker}


def prediction(item):
    view, existing = item["createView"], item["existing"]
    update = [(key, "changed" if key == 2 else label) for key, label in DATA] + [(4, "new")]
    collision = existing and view["policy"] != "replace"
    failed = collision and view["policy"] == "require-new"
    after, later = (fixture(existing, data, [0, 7]) for data in (DATA, update))
    if not collision:
        for snapshot, data in ((after, DATA), (later, update)):
            snapshot["views"][MAIN] = relation(view["query"], view["columns"], view["algorithm"], view["security"], data)
    diagnostics = [["Error" if failed else "Note", 1050, "Table 'ir_cv_main' already exists"]] if collision else []
    return {"status": "view-definition", "result": ["db-error", "42S01", 1050] if failed else ["created"], "diagnostics": diagnostics,
            "active": False, "initial": fixture(existing, DATA, [0]), "after": after, "afterRollback": after, "afterSourceChange": later}


def observer_request(item, sql):
    return {"sql": sql, "existing": item["existing"]}


def observation(raw):
    if raw["status"] != "view-definition":
        return raw
    result = dict(raw)
    for phase in ("initial", "after", "afterRollback", "afterSourceChange"):
        state = raw[phase]
        result[phase] = {**state, "views": {name: {**view, "rows": sorted(view["rows"], key=canonical)}
                                           for name, view in state["views"].items()}}
    return result


def prepare(_, db):
    return {"initial": db.environment["initial"]}


def check_preparation(_, environment, prepared):
    same(environment["initial"], prepared["initial"])
    same(fixture(True, DATA, [0]), prepared["initial"])


def check_observation(item, actual, sql):
    expected = prediction(item)
    witness = {"expected": expected, "observed": actual, "sql": sql}
    require(actual["status"] == "view-definition", "mysql-create-view-observer-scope", witness)
    require(expected == actual, "meaning-mismatch", witness)


def sample_forms(forms, reply, _, item):
    value_ = reply["createView"]
    for attribute in ("policy", "algorithm", "security"):
        forms[attribute + "/" + value_[attribute]] += 1
    query_ = value_["query"]
    forms["filter/" + ("present" if query_["where"] else "absent")] += 1
    forms["slot/" + query_["columns"][0][0][0]] += 1
    forms["fixture/" + str(item["existing"])] += 1


def required_forms(*_):
    return {"filter/present", "filter/absent", "slot/slot", "slot/text-slot", "fixture/True", "fixture/False",
            "policy/require-new", "policy/replace", "security/DEFINER", "security/INVOKER",
            *("algorithm/" + role for role in ALGORITHMS.values())}


def controls():
    item = finite("mysql-9.1.0", "merge", "invoker", "replace", "base-all", "existing-view")
    healthy = prediction(item)
    check_observation(item, healthy, "view definition control")
    wrong = prediction({**item, "createView": {**item["createView"], "security": "DEFINER"}})
    try:
        check_observation(item, wrong, "wrong native security context")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"wrong-security": {"input": item, **error.detail}}
    raise Failure("mysql-create-view-comparator-accepted")


def boundary(item, reply):
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "mysql-create-view-mutant-response", reply)
    raise Failure("mysql-create-view-invalid-published", {"sql": reply["sql"], "observed": reply, "expected": item["boundary"]})


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
    raise Failure("mysql-create-view-undetected-fault", rule.name)


def faults(_, tag):
    path = "sql-semantics-mysql/src/Ir/Schema/View/CreateView.php"
    model = finite(tag, "merge", "invoker", "replace", "base-all", "existing-view")
    invalid, reason = list(negatives(tag))[1]
    return [
        RenderFault("replace-another-view-definition", path, "$this->name->quoted()", "'`ir_fixture`.`ir_cv_decoy`'", model, "meaning-mismatch"),
        RenderFault("swap-view-heading-positions", path, "implode(', ', $names)", "implode(', ', array_reverse($names))", model, "meaning-mismatch"),
        RenderFault("drop-view-replacement-policy", path, "'CREATE OR REPLACE' : 'CREATE'", "'CREATE' : 'CREATE'", model, "meaning-mismatch"),
        RenderFault("switch-view-security-mode", path, "$this->security->value", "'DEFINER'", model, "meaning-mismatch"),
        RenderFault("allow-repeated-view-heading-name", path, "!isset($seen[$key]),", "true,",
                    {"boundary": reason}, "mysql-create-view-invalid-published", invalid),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
