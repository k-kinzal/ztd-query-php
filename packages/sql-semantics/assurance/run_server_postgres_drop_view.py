"""Compare PostgreSQL view removal with an independent catalog effect model."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_drop_table import specifications as drop_contract
from run_server_join import main, probe
from sqlite_domain import RenderFault

LABEL = "postgres-drop-view"
OBSERVER = "postgres_drop_view_observer.php"
CONTRACT_NAME = "postgres-drop-view-v1.json"
CONTRACT = "f38844b5c9ed143cbd9b46e344f721b384c5ce9a969ec85d08e6319e95e8cd4d"
COMPLETE_SEEDS = True
ORDINARY, MATERIALIZED = "VIEW", "MATERIALIZED VIEW"
A = ["public", "ir_rv_a"]
B = ["public", "ir_rv_b"]
MISSING = ["public", "ir_rv_missing"]
DECOY = ["ir_rv_decoy", "ir_rv_a"]
CHILD = ["public", "ir_rv_child"]
GRANDCHILD = ["public", "ir_rv_grandchild"]
AXES = ("kind", "targets", "ifExists", "cascade", "dependent")
SEQUENCES = {
    "first": [A], "second": [B], "both": [A, B], "first-missing": [A, MISSING],
    "missing-first": [MISSING, A], "missing": [MISSING], "cross-schema": [A, DECOY],
}
INPUT = "public.ir_rv_input"
DIAGNOSTICS = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\"
INVALID = DIAGNOSTICS + "InvalidConstruction"
SEMANTIC = DIAGNOSTICS + "SemanticException"


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-drop-view-contract-mismatch")
    return json.loads(path.read_text()), {"postgres": drop_contract()[1]["postgres"]}


def case(tag, kind, views, optional=False, cascade=False, dependent=False):
    return {
        "dialect": "postgres", "release": tag, "dependent": dependent,
        "dropView": {"kind": kind, "targets": views, "ifExists": optional, "cascade": cascade},
    }


def finite(tag, kind, sequence, optional, cascade, dependent):
    return case(tag, kind, SEQUENCES[sequence], optional, cascade, dependent)


def finite_ids(_, spec, _tag):
    grid = set(product(*(spec["finite"][axis] for axis in AXES)))
    require(len(grid) == spec["finite"]["identities"], "postgres-drop-view-finite-size")
    return grid


def finite_id(_, actual, item):
    drop = actual["dropView"]
    targets = next(name for name, values in SEQUENCES.items() if values == drop["targets"])
    return drop["kind"], targets, drop["ifExists"], drop["cascade"], item["dependent"]


def request(tag, sql, kind=ORDINARY, complete=True):
    other = MATERIALIZED if kind == ORDINARY else ORDINARY
    declarations = [
        f"CREATE TABLE {INPUT} (value integer)",
        *(f"CREATE {kind} {'.'.join(name)} AS SELECT value FROM {INPUT}" for name in (A, B, DECOY)),
        f"CREATE {other} public.ir_rv_other AS SELECT value FROM {INPUT}",
    ]
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": declarations, "complete": complete}


def analyzed(tag):
    yield "DROP VIEW ir_rv_a", finite(tag, ORDINARY, "first", False, False, False)
    yield "DROP MATERIALIZED VIEW ir_rv_b", finite(tag, MATERIALIZED, "second", False, False, False)
    yield "DROP VIEW public.ir_rv_a, ir_rv_decoy.ir_rv_a RESTRICT", finite(tag, ORDINARY, "cross-schema", False, False, False)
    yield "DROP MATERIALIZED VIEW public.ir_rv_a, public.ir_rv_b CASCADE", finite(tag, MATERIALIZED, "both", False, True, True)
    yield "DROP VIEW IF EXISTS public.ir_rv_missing", finite(tag, ORDINARY, "missing", True, False, False)
    yield "DROP MATERIALIZED VIEW IF EXISTS public.ir_rv_missing", finite(tag, MATERIALIZED, "missing", True, False, True)
    yield "DROP VIEW public.ir_rv_a CASCADE", finite(tag, ORDINARY, "first", False, True, True)
    yield "DROP VIEW public.ir_rv_a, public.ir_rv_b RESTRICT", finite(tag, ORDINARY, "both", False, False, True)


def negatives(tag):
    for names, message in (
        ([], "DROP VIEW needs a nonempty target list."),
        ([A, A], "DROP VIEW cannot repeat a target."),
        ([["pg_temp", A[1]]], "A persistent PostgreSQL view requires an absolute non-temporary schema."),
    ):
        yield case(tag, ORDINARY, names), {"exception": INVALID, "message": message}
    yield request(tag, "DROP MATERIALIZED VIEW public.ir_rv_a"), {"exception": SEMANTIC, "message": "not a materialized view"}
    yield request(tag, "DROP VIEW public.ir_rv_other"), {"exception": SEMANTIC, "message": "not a view"}
    yield request(tag, "DROP VIEW public.ir_rv_missing"), {"exception": SEMANTIC, "message": "ir_rv_missing"}
    yield request(tag, "DROP VIEW IF EXISTS outside_fixture.ir_rv_missing"), {
        "exception": INVALID, "message": "Optional DROP VIEW needs a known explicit schema.",
    }
    yield request(tag, "DROP VIEW IF EXISTS public.ir_rv_missing", complete=False), {
        "exception": INVALID, "message": "Optional DROP VIEW needs a known absence in a complete catalog.",
    }


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        names = rng.sample((A, B, MISSING, DECOY), 1 + rng.randrange(4))
        yield case(tag, (ORDINARY, MATERIALIZED)[index % 2], names, bool(index % 3), bool(index % 5), bool(index % 7))


def requests(_, tag, spec):
    for index, dimensions in enumerate(product(*(spec["finite"][axis] for axis in AXES))):
        item = finite(tag, *dimensions)
        yield f"finite/{index}", item, item, None
    authored = list(analyzed(tag))
    require(len(authored) == len(spec["analysis"]), "postgres-drop-view-analysis-count")
    for index, (sql, item) in enumerate(authored):
        yield f"analyze/{index}", request(tag, sql, item["dropView"]["kind"]), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, boundary) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, boundary


def check_value(item, reply):
    require(reply["status"] == "constructed"
            and reply["profile"] == item["release"] + "/utf8/standard-conforming-strings/native",
            "postgres-drop-view-rejected", reply)
    witness = {"expected": item["dropView"], "observed": reply["dropView"], "sql": reply["sql"]}
    require(item["dropView"] == reply["dropView"], "postgres-drop-view-graph", witness)


def snapshot(kind, dependent, removed=(), active=False):
    materialized = kind == MATERIALIZED
    value = 9 if materialized else 17
    objects = [
        (A, "m" if materialized else "v", [None, 3, 3, value]),
        (B, "m" if materialized else "v", [None, 20, 20, value + 17]),
        (DECOY, "m" if materialized else "v", [None, 64, 64, value + 61]),
        (["public", "ir_rv_input"], "r", [None, 3, 3, 17]),
        (["public", "ir_rv_marker"], "r", [0, 7] if active else [0]),
    ]
    if dependent:
        objects += [(CHILD, "v", [None, 6, 6, 2 * value]), (GRANDCHILD, "m", [None, 7, 7, 19])]
    return sorted([*name, relkind, ["value", "int4", 23], values] for name, relkind, values in objects if name not in removed)


def prediction(item):
    drop = item["dropView"]
    targets = drop["targets"]
    has_dependents = item["dependent"] and A in targets
    initial = snapshot(drop["kind"], item["dependent"])
    if MISSING in targets and not drop["ifExists"]:
        result = ["db-error", "42P01"]
    elif has_dependents and not drop["cascade"]:
        result = ["db-error", "2BP01"]
    else:
        result = ["dropped"]
    removed = targets + ([CHILD, GRANDCHILD] if has_dependents else [])
    return {
        "status": "view-removal", "before": initial, "result": result, "active": True,
        "effect": snapshot(drop["kind"], item["dependent"], removed, active=True) if result == ["dropped"] else None,
        "restored": initial,
    }


def prepare(_, db):
    return {"initial": db.environment["initial"]}


def check_preparation(_, environment, prepared):
    same(snapshot(MATERIALIZED, True), prepared["initial"])
    same(prepared["initial"], environment["initial"])


def observer_request(item, sql):
    return {"sql": sql, "dependent": item["dependent"], "kind": item["dropView"]["kind"]}


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    expected = prediction(item)
    witness = {"expected": expected, "observed": actual, "sql": sql}
    require(actual["status"] == "view-removal", "postgres-drop-view-observer-rejected", witness)
    require(actual == expected, "meaning-mismatch", witness)


def sample_forms(forms, reply, _, item):
    drop = reply["dropView"]
    views = drop["targets"]
    forms[f"kind/{drop['kind']}"] += 1
    forms[f"count/{len(views)}"] += 1
    forms[f"optional/{drop['ifExists']}"] += 1
    forms[f"cascade/{drop['cascade']}"] += 1
    forms[f"dependent/{item['dependent']}"] += 1
    forms[f"absent/{MISSING in views}"] += 1
    forms[f"decoy/{DECOY in views}"] += 1


def required_forms(*_):
    return {
        f"kind/{ORDINARY}", f"kind/{MATERIALIZED}", *(f"count/{count}" for count in (1, 2, 3, 4)),
        *(f"{axis}/{choice}" for axis in ("optional", "cascade", "dependent", "absent", "decoy") for choice in (True, False)),
    }


def controls():
    item = finite("pg-17.2", MATERIALIZED, "first", False, True, True)
    check_observation(item, prediction(item), "view removal control")
    restricted = finite("pg-17.2", MATERIALIZED, "first", False, False, True)
    try:
        check_observation(item, prediction(restricted), "a RESTRICT error substituted for CASCADE")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"wrong-dependency-effect": {"input": item, **error.detail}}
    raise Failure("postgres-drop-view-comparator-accepted")


def boundary(item, reply):
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "postgres-drop-view-mutant-response", reply)
    raise Failure("postgres-drop-view-invalid-published",
                  {"sql": reply["sql"], "observed": reply, "expected": item["boundary"]})


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
    raise Failure("postgres-drop-view-undetected-fault", rule.name)


def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/Schema/View/DropView.php"
    invalid, reason = list(negatives(tag))[1]
    return [
        RenderFault("drop-view-change-kind", path, "'DROP ' . $this->kind->value . ' '", "'DROP VIEW '",
                    finite(tag, MATERIALIZED, "first", False, False, False), "meaning-mismatch"),
        RenderFault("drop-view-substitute-decoy", path, "$names[] = $target->quoted();",
                    '$names[] = \'"ir_rv_decoy"."ir_rv_a"\';',
                    finite(tag, ORDINARY, "first", False, True, True), "meaning-mismatch"),
        RenderFault("drop-view-omit-second-target", path, "foreach ($this->targets as $target)",
                    "foreach (array_slice($this->targets, 0, 1) as $target)",
                    finite(tag, MATERIALIZED, "both", False, True, True), "meaning-mismatch"),
        RenderFault("drop-view-change-dependency-policy", path, "$this->cascade ? ' CASCADE'",
                    "!$this->cascade ? ' CASCADE'",
                    finite(tag, ORDINARY, "first", False, True, True), "meaning-mismatch"),
        RenderFault("drop-view-change-absence-policy", path, "$this->ifExists ? 'IF EXISTS '",
                    "!$this->ifExists ? 'IF EXISTS '",
                    finite(tag, MATERIALIZED, "missing-first", True, True, True), "meaning-mismatch"),
        RenderFault("drop-view-admit-repeated-target", path, "!isset($seen[$key]),", "true,", {"boundary": reason},
                    "postgres-drop-view-invalid-published", invalid),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
