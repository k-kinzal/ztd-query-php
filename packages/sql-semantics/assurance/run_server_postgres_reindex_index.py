"""Check resolved PostgreSQL REINDEX INDEX against measured native phases."""

import json
from itertools import product
from random import Random
import sys

from evidence_transcript import canonical
from postgres_create_btree_cases import refusal
from postgres_reindex_cases import FORMS, ORDERS, POLICIES, analyzed, case, expected
from run import Failure, TOOL, digest, require
from run_server import rejects, same
from run_server_drop_table import specifications as drop_contract
from run_server_join import main, probe
from run_server_postgres_create_btree import check_preparation, observation, observer_request, prepare
from sqlite_domain import RenderFault

LABEL = "postgres-reindex-index"
CONTRACT_NAME = "postgres-reindex-index-v1.json"
CONTRACT = "961195c02b9dd75c7a3f8bc8b5a93d3922b01620ddb23ae0da219ecdc54cf237"
OBSERVER = "postgres_reindex_observer.py"
COMPLETE_SEEDS = True
INVALID = "postgres-reindex-index-invalid-published"
AXES = ("scopes", "forms", "transactions", "spellings", "policies", "filtered")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-reindex-index-contract")
    spec = json.loads(path.read_text())
    require(spec["exploration"]["allowedExclusions"] == [], "postgres-reindex-index-exclusions")
    spec["additionalPostgreSqlNegatives"] = ["Index selector qualified with another database"]
    return spec, {"postgres": drop_contract()[1]["postgres"]}


def finite_ids(_, specification, _tag):
    dimensions = specification["finite"]
    observed = {
        (scope, form, state, spelling, policy, filtered)
        for scope in ("fixture", "pg_temp")
        for form in ("ordinary", "keyword", "option-enabled", "option-disabled")
        for spelling in ("absolute", "local")
        for policy in POLICIES
        for state in (True, False)
        for filtered in (True, False)
    }
    require(len(observed) == 192 and observed == set(product(*(dimensions[axis] for axis in AXES))),
            "postgres-reindex-index-grid")
    return observed


def finite_id(_, observed, item):
    coordinate = tuple(item["coordinate"])
    require(observed["reindex"] == case(item["release"], *coordinate)["reindex"], "postgres-reindex-observed-coordinate")
    return coordinate


def direct_finite_id(engine, observed, item, _request):
    return finite_id(engine, observed, item)


def analysis_finite_id(engine, observed, item, _authored):
    return finite_id(engine, observed, item)


def requests(_, version, spec):
    for number, axes in enumerate(product(*(spec["finite"][axis] for axis in AXES))):
        item = case(version, *axes, coordinate=list(axes))
        yield f"finite/{number}", analyzed(item), item, None
        yield f"direct/{number}", item, item, None
    for number, form in enumerate(("off-word", "last-disabled", "last-enabled", "keyword-wins", "value-default")):
        item = case(version, "fixture", form, True, "local", "unique", True)
        yield f"edge/{number}", analyzed(item), item, None
    for seed in spec["exploration"]["seeds"]:
        for ordinal, item in enumerate(generated(version, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", item, item, None
    for ordinal, (item, reason) in enumerate(negatives(version)):
        yield f"negative/{ordinal}", item, None, reason


def generated(version, seed, total):
    rng = Random(seed)
    for ordinal in range(total):
        yield case(
            version, rng.choice(("fixture", "pg_temp")), rng.choice(tuple(FORMS)), bool(rng.getrandbits(1)),
            rng.choice(("absolute", "local")), rng.choice(tuple(POLICIES)), bool(rng.getrandbits(1)),
            index=rng.choice(("cover", 'Cover "名"')), table=rng.choice(("orders", 'Orders 名')),
            order=rng.choice(tuple(ORDERS)), covering=rng.choice(((), (0,), (2,), (0, 2))),
            fill=rng.choice((45, 80, 95)),
        )


def negatives(version):
    base = case(version, "fixture", "ordinary", False, "absolute", "plain", False)
    request = analyzed(base)
    missing = {**request, "sql": 'REINDEX INDEX "fixture"."missing"'}
    yield missing, refusal("requires a declared index")
    yield {**request, "sql": 'REINDEX INDEX "unknown"."cover"'}, refusal("requires a declared index")
    yield {**request, "sql": 'REINDEX INDEX "fixture"."orders"'}, refusal("not an ordinary index")
    yield {**missing, "complete": False}, refusal("cannot infer absence in an open index namespace")
    yield {**request, "declarations": [*request["declarations"], request["declarations"][3]]}, refusal("conflicting index declarations")
    yield {**request, "sql": 'REINDEX SCHEMA "fixture"'}, refusal("needs a named index")
    yield {**request, "sql": 'REINDEX (TABLESPACE pg_default) INDEX "fixture"."cover"'}, refusal("accepts only concurrency options")
    yield {**request, "sql": """REINDEX (CONCURRENTLY 'yes') INDEX "fixture"."cover\""""}, refusal("concurrently", "SemanticException")
    yield {**request, "sql": 'REINDEX INDEX "other"."fixture"."cover"'}, refusal("requires a local schema and index")


def check_value(item, response):
    require(response["status"] == "constructed" and response["reindex"] == item["reindex"]
            and response["profile"] == item["release"] + "/utf8/standard-conforming-strings/native",
            "postgres-reindex-value", response)


def strip_identity(snapshot):
    if snapshot is None:
        return None
    return {
        schema: {"rows": state["rows"], "index": {key: value for key, value in state["index"].items()
                                                 if key not in ("oid", "file")}}
        for schema, state in snapshot.items()
    }


def changed(before, later):
    if later is None:
        return None
    return {
        schema: [state["index"][key] != later[schema]["index"][key] for key in ("oid", "file")]
        for schema, state in before.items()
    }


def check_observation(item, actual, sql):
    require(actual["status"] == "index-rebuild", "postgres-reindex-unrelated-result", {"sql": sql, "actual": actual})
    wanted = expected(item)
    normalized = {**actual, **{field: strip_identity(actual[field]) for field in ("before", "during", "settled")},
                  "changedDuring": changed(actual["before"], actual["during"]),
                  "changedSettled": changed(actual["before"], actual["settled"])}
    require(canonical(normalized) == canonical(wanted), "meaning-mismatch",
            {"expected": wanted, "observed": actual, "sql": sql})


def sample_forms(forms, response, _, item):
    root = response["reindex"]
    forms[f"scope/{root['target'][0]}"] += 1
    forms[f"mode/{root['mode']}"] += 1
    forms[f"runtime/{expected(item)['sqlState']}"] += 1
    for field in ("policy", "active", "filtered", "order"):
        forms[f"{field}/{item[field]}"] += 1
    forms[f"covering/{len(item['covering'])}"] += 1


def required_forms(*_):
    return {"scope/fixture", "scope/pg_temp", "mode/standard", "mode/concurrently", "runtime/00000", "runtime/25001",
            "active/True", "active/False", "filtered/True", "filtered/False",
            *(f"policy/{policy}" for policy in POLICIES), *(f"order/{order}" for order in ORDERS),
            *(f"covering/{size}" for size in range(3))}


def controls():
    item = case("pg-17.2", "fixture", "ordinary", False, "absolute", "plain", True)
    described = expected(item)
    first, later = described["before"], described["during"]
    before = {name: {"rows": entry["rows"], "index": {**entry["index"], "oid": name, "file": name}}
              for name, entry in first.items()}
    during = {name: {"rows": entry["rows"], "index": {**entry["index"], "oid": name, "file": name + ("-rebuilt" if name == "fixture" else "")}}
              for name, entry in later.items()}
    positive = {name: item for name, item in described.items() if name not in ("changedDuring", "changedSettled")}
    positive.update(before=before, during=during, settled=during)
    check_observation(item, positive, "storage changed for the selected index")
    erased = {**positive, "during": before}
    try:
        check_observation(item, erased, "changed storage observation overwritten")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"physical-rebuild-required": {"input": item, "witness": error.detail}}
    raise Failure("postgres-reindex-comparator-accepted")


def fault_probe(item, response, database):
    if "boundary" not in item:
        probe(item, response, database, sys.modules[__name__])
        return
    if rejects(response, item["boundary"]):
        return
    require(response["status"] == "constructed", "postgres-reindex-unrelated-fault", response)
    raise Failure(INVALID, {"expected": item["boundary"], "observed": response, "sql": response["sql"]})


def replay_fault(rule, record):
    witness = record["detail"]
    try:
        if "boundary" in rule.item:
            fault_probe(rule.item, record["healthyResponse"], None)
            fault_probe(rule.item, record["mutantResponse"], None)
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(witness, error.detail)
        same(rule.signal, error.signal)
        return
    raise Failure("postgres-reindex-fault-not-replayed", rule.name)


def faults(_, tag):
    directory = "sql-semantics-postgres/src/Ir/Schema/Index/"
    root, lowering = directory + "ReindexIndex.php", directory + "ReindexIndexLowering.php"
    ordinary = case(tag, "fixture", "ordinary", True, "absolute", "plain", True)
    concurrent = case(tag, "fixture", "keyword", True, "absolute", "unique", False)
    missing, reason = next(negatives(tag))
    selector = "(new IndexTargetLowering())->resolveRebuild($context, $statement->object)"
    fake = ('new IndexReference(new \\SqlSemantics\\Platform\\PostgreSql\\Ir\\Identifier("fixture"), '
            'new \\SqlSemantics\\Platform\\PostgreSql\\Ir\\Identifier("missing"), '
            'new \\SqlSemantics\\Platform\\PostgreSql\\Ir\\Identifier("orders"))')
    return [
        RenderFault("postgres-reindex-wrong-namespace", root, "$this->target->quoted()",
                    """'"decoy".' . $this->target->index->quoted()""", ordinary, "meaning-mismatch"),
        RenderFault("postgres-reindex-drop-concurrent-request", root, "$this->mode === IndexRebuildMode::Concurrent",
                    "false", concurrent, "meaning-mismatch"),
        RenderFault("postgres-reindex-invent-concurrent-request", root, "$this->mode === IndexRebuildMode::Concurrent",
                    "true", ordinary, "meaning-mismatch"),
        RenderFault("postgres-reindex-fabricated-bound-target", lowering, selector, fake,
                    {"boundary": reason}, INVALID, missing),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
