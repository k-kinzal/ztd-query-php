"""Compare MySQL DROP VIEW effects against an independent catalog model."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_drop_table import specifications as drop_contract
from run_server_join import main, probe
from run_server_mysql_external_views import DIAGNOSTICS
from sqlite_domain import RenderFault

LABEL = "mysql-drop-view"
OBSERVER = "mysql_drop_view_observer.php"
CONTRACT_NAME = "mysql-drop-view-v2.json"
CONTRACT = "a9eb2d0e7a2b78cbdbfdf27b18a750004c297918dea417c2649a5dd3db1e25af"
BASE_CONTRACT = "5365cfb699a105d2c3a29fa5f4d00409867be1a6199542fb9468f4f65713f77c"
COMPLETE_SEEDS = True
SCHEMA, OTHER = "ir_fixture", "ir_dv_decoy"
A, B, MISSING, DECOY = [SCHEMA, "ir_dv_a"], [SCHEMA, "ir_dv_b"], [SCHEMA, "ir_dv_missing"], [OTHER, "ir_dv_a"]
CHILD = [SCHEMA, "ir_dv_child"]
AXES = ("targets", "ifExists", "dependent")
SEQUENCES = {"first": [A], "second": [B], "both": [A, B], "first-missing": [A, MISSING], "missing-first": [MISSING, A],
             "missing": [MISSING], "cross-schema": [A, DECOY]}
DECLARATIONS = [
    f"CREATE TABLE {SCHEMA}.ir_dv_base (value INT)",
    *(f"CREATE VIEW {schema}.{name} AS SELECT value FROM {SCHEMA}.ir_dv_base" for schema, name in (A, B, DECOY)),
]
INVALID = DIAGNOSTICS + "InvalidConstruction"
SEMANTIC = DIAGNOSTICS + "SemanticException"


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT and digest(TOOL / "mysql-drop-view-v1.json") == BASE_CONTRACT, "mysql-drop-view-contract-mismatch")
    spec = json.loads(path.read_text())
    groups = spec["absenceEffects"]
    require(set(groups["partialRemovalOnError"]).isdisjoint(groups["atomicRejection"])
            and set(groups["partialRemovalOnError"]) | set(groups["atomicRejection"]) == set(spec["profiles"]), "mysql-drop-view-release-partition")
    return spec, {"mysql": drop_contract()[1]["mysql"]}


def case(tag, views, optional=False, dependent=False):
    return {"dialect": "mysql", "release": tag, "dropView": {"targets": views, "ifExists": optional}, "dependent": dependent}


def finite(tag, sequence, optional, dependent):
    return case(tag, SEQUENCES[sequence], optional, dependent)


def finite_ids(_, spec, _tag):
    grid = set(product(*(spec["finite"][axis] for axis in AXES)))
    require(len(grid) == spec["finite"]["identities"], "mysql-drop-view-finite-size")
    return grid


def finite_id(_, actual, item):
    views = actual["dropView"]
    return next(name for name, choices in SEQUENCES.items() if choices == views["targets"]), views["ifExists"], item["dependent"]


def request(tag, sql):
    return {"dialect": "mysql", "release": tag, "sql": sql, "declarations": DECLARATIONS}


def analyzed(tag):
    yield f"DROP VIEW {'.'.join(A)}", finite(tag, "first", False, False)
    yield f"DROP VIEW {'.'.join(A)}, {'.'.join(B)}", finite(tag, "both", False, False)
    yield f"DROP VIEW IF EXISTS {'.'.join(MISSING)}", finite(tag, "missing", True, False)
    yield f"DROP VIEW {'.'.join(A)} RESTRICT", finite(tag, "first", False, True)
    yield f"DROP VIEW {'.'.join(A)} CASCADE", finite(tag, "first", False, True)
    yield f"DROP VIEW {'.'.join(A)}, {'.'.join(DECOY)}", finite(tag, "cross-schema", False, True)


def negatives(tag):
    for names, message in (
        ([], "DROP VIEW needs a nonempty target list."),
        ([A, A], "DROP VIEW cannot repeat a target."),
        ([[SCHEMA, "a.bad"]], "An absolute MySQL view name needs 1 to 64 characters"),
    ):
        yield case(tag, names), {"exception": INVALID, "message": message}
    yield request(tag, f"DROP VIEW {SCHEMA}.ir_dv_base"), {"exception": SEMANTIC, "message": "ir_dv_base"}
    yield request(tag, f"DROP VIEW {'.'.join(MISSING)}"), {"exception": SEMANTIC, "message": "ir_dv_missing"}
    yield request(tag, "DROP VIEW IF EXISTS outside_fixture.ir_dv_missing"), {
        "exception": INVALID, "message": "DROP VIEW requires a known absolute view identity."}


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        targets = rng.sample((A, B, MISSING, DECOY), 1 + rng.randrange(4))
        yield case(tag, targets, bool(index % 2), bool(index % 3))


def requests(_, tag, spec):
    for index, dimensions in enumerate(product(*(spec["finite"][axis] for axis in AXES))):
        item = finite(tag, *dimensions)
        yield f"finite/{index}", item, item, None
    authored = list(analyzed(tag))
    require(len(authored) == len(spec["analysis"]), "mysql-drop-view-analysis-count")
    for index, (sql, item) in enumerate(authored):
        yield f"analyze/{index}", request(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, boundary) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, boundary


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
            "mysql-drop-view-rejected", reply)
    require(item["dropView"] == reply["dropView"], "mysql-drop-view-graph", {"expected": item, "observed": reply, "sql": reply["sql"]})


def snapshot(views, committed):
    objects = [[schema, name, "VIEW"] for schema, name in views]
    objects.extend([[SCHEMA, "ir_dv_base", "BASE TABLE"], [SCHEMA, "ir_dv_marker", "BASE TABLE"]])
    return {"objects": sorted(objects), "base": [None, 7, 7, 31], "marker": [0, 7] if committed else [0]}


def prediction(item):
    targets = item["dropView"]["targets"]
    initial = [A, B, DECOY] + ([CHILD] if item["dependent"] else [])
    absent = MISSING in targets
    failed = absent and not item["dropView"]["ifExists"]
    partial_releases = specifications()[0]["absenceEffects"]["partialRemovalOnError"]
    retained = initial if failed and item["release"] not in partial_releases else [name for name in initial if name not in targets]
    final = snapshot(retained, True)
    diagnostics = [["Error" if failed else "Note", 1051, "Unknown table 'ir_fixture.ir_dv_missing'"]] if absent else []
    return {"status": "view-removal", "result": ["db-error", "42S02", 1051] if failed else ["dropped"],
            "diagnostics": diagnostics, "active": False, "initial": snapshot(initial, False),
            "after": final, "afterRollback": final}


def prepare(_, db):
    return {"initial": db.environment["initial"]}


def check_preparation(_, environment, prepared):
    same(snapshot([A, B, DECOY, CHILD], False), prepared["initial"])
    same(prepared["initial"], environment["initial"])


def observer_request(item, sql):
    return {"sql": sql, "dependent": item["dependent"]}


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    expected = prediction(item)
    witness = {"expected": expected, "observed": actual, "sql": sql}
    require(actual["status"] == "view-removal", "mysql-drop-view-observer-rejected", witness)
    require(actual == expected, "meaning-mismatch", witness)


def sample_forms(forms, reply, _, item):
    drop = reply["dropView"]
    views = drop["targets"]
    forms[f"count/{len(views)}"] += 1
    forms[f"optional/{drop['ifExists']}"] += 1
    forms[f"dependent/{item['dependent']}"] += 1
    forms[f"absent/{MISSING in views}"] += 1
    forms[f"decoy/{DECOY in views}"] += 1
    if MISSING in views and A in views:
        forms["missing-order/" + ("first" if views.index(MISSING) < views.index(A) else "later")] += 1


def required_forms(*_):
    return {"count/1", "count/2", "count/3", "count/4", "optional/True", "optional/False", "dependent/True", "dependent/False",
            "absent/True", "absent/False", "decoy/True", "decoy/False", "missing-order/first", "missing-order/later"}


def controls():
    item = finite("mysql-8.4.7", "first-missing", False, True)
    healthy = prediction(item)
    check_observation(item, healthy, "view removal control")
    mismatch = prediction({**item, "release": "mysql-5.7.44"})
    try:
        check_observation(item, mismatch, "partial removal obsolete profile")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"wrong-release-atomic-effect": {"input": item, **error.detail}}
    raise Failure("mysql-drop-view-comparator-accepted")


def boundary(item, reply):
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "mysql-drop-view-mutant-response", reply)
    raise Failure("mysql-drop-view-invalid-published", {"sql": reply["sql"], "observed": reply, "expected": item["boundary"]})


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
    raise Failure("mysql-drop-view-undetected-fault", rule.name)


def faults(_, tag):
    path = "sql-semantics-mysql/src/Ir/Schema/View/DropView.php"
    invalid, reason = list(negatives(tag))[1]
    return [
        RenderFault("drop-view-substitute-decoy", path, "$names[] = $target->quoted();", "$names[] = '`ir_dv_decoy`.`ir_dv_a`';",
                    finite(tag, "first", False, True), "meaning-mismatch"),
        RenderFault("drop-view-omit-second-target", path, "foreach ($this->targets as $target)", "foreach (array_slice($this->targets, 0, 1) as $target)",
                    finite(tag, "both", False, True), "meaning-mismatch"),
        RenderFault("drop-view-change-absence-policy", path, "$this->ifExists ? 'IF EXISTS '", "!$this->ifExists ? 'IF EXISTS '",
                    finite(tag, "missing-first", True, True), "meaning-mismatch"),
        RenderFault("drop-view-admit-repeated-target", path, "!isset($seen[$key]),", "true,",
                    {"boundary": reason}, "mysql-drop-view-invalid-published", invalid),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
