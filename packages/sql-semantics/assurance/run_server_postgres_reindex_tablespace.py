"""Check resolved PostgreSQL relocation through typed construction and native effects."""

from itertools import product
import json
from random import Random
import sys

from native_postgres_fixture import TABLESPACES
from postgres_create_btree_cases import refusal
from postgres_reindex_tablespace_cases import DATABASE, DESTINATIONS, FORMS, RESERVED, ROLE_MAP, UNKNOWN
from postgres_reindex_tablespace_cases import analyzed, case, direct, sql
from postgres_reindex_tablespace_oracle import check_observation, compare_placement, outcome
from postgres_reindex_verbose_cases import TOP_LEVEL, transport
from run import Failure, TOOL, digest, require
from run_server import rejects, same
from run_server_drop_table import specifications as drop_contract
from run_server_join import main
from run_server_postgres_create_btree import check_preparation as check_libpq
from run_server_postgres_create_btree import observation, prepare
from sqlite_domain import RenderFault

LABEL = "postgres-reindex-tablespace"
CONTRACT_NAME = "postgres-reindex-tablespace-v1.json"
CONTRACT = "82f3464a67fa737ccce15b085eba6d47f36a69291b4f6d1ab5d661b30f629437"
OBSERVER = "postgres_reindex_tablespace_observer.py"
AXES = ("roles", "destinations", "reporting", "instructionForms")
DIRECT_AXES = AXES[:-1]
REFUSAL = refusal("requires a declared tablespace")
ESCAPED = "postgres-relocation-unbound-published"
INVALID = {"unknown", "case-distinct", "missing-argument", "shared"}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-relocation-contract")
    spec = json.loads(path.read_text())
    require(spec["exploration"]["allowedExclusions"] == [], "postgres-relocation-exclusions")
    return spec, {"postgres": drop_contract()[1]["postgres"]}


def check_preparation(engine, environment, preparation):
    check_libpq(engine, environment, preparation)
    same(DATABASE, environment["currentDatabase"])
    same(sorted(TABLESPACES), environment["tablespaces"])


def direct_finite_ids(_, spec, _tag):
    expected = set(product(ROLE_MAP, ("pg_default", "application"), (False, True)))
    require(len(expected) == 28 and expected == set(product(*(spec["finite"][name] for name in DIRECT_AXES))),
            "postgres-relocation-direct-grid")
    return expected


def finite_ids(engine, spec, tag):
    expected = {(*coords, form) for coords in direct_finite_ids(engine, spec, tag) for form in FORMS}
    require(len(expected) == 84 and expected == set(product(*(spec["finite"][name] for name in AXES))),
            "postgres-relocation-analysis-grid")
    return expected


def direct_finite_id(_, response, item, supplied):
    coordinate = tuple(item["coordinate"])
    value = case(item["release"], *coordinate, "identifier")
    same(direct(value), supplied)
    check_value(value, response)
    return coordinate


def analysis_finite_id(_, response, item, supplied):
    coordinate = tuple(item["coordinate"])
    value = case(item["release"], *coordinate)
    same(sql(value), supplied)
    check_value(value, response)
    return coordinate


def rejected_finite_ids(_, _tag):
    return INVALID


def observe_rejection(_, item, actual, supplied):
    same(sql(item), supplied)
    check_observation(item, actual, supplied)
    return item["failureCase"]


def generate(tag, seed, count):
    rng = Random(seed)
    for _ in range(count):
        role = rng.choice(tuple(ROLE_MAP))
        concurrent = role != "system" and bool(rng.getrandbits(1))
        active = ROLE_MAP[role] not in TOP_LEVEL and not concurrent and bool(rng.getrandbits(1))
        yield case(tag, role, rng.choice(tuple(DESTINATIONS)), bool(rng.getrandbits(1)), rng.choice(FORMS),
                   label=rng.choice(("", ' 頁."片"')), nested=bool(rng.getrandbits(1)), extra=rng.randrange(1, 4),
                   base=rng.randrange(-43, 45), concurrent=concurrent, active=active)


def negative_item(tag, reason):
    values = {
        "unknown": (UNKNOWN, "identifier", "42704", f'tablespace "{UNKNOWN}" does not exist'),
        "case-distinct": (TABLESPACES[0].upper(), "identifier", "42704", f'tablespace "{TABLESPACES[0].upper()}" does not exist'),
        "missing-argument": ("pg_default", "missing-argument", "42601", "tablespace requires a parameter"),
        "shared": ("pg_global", "identifier", "22023", "only shared relations can be placed in pg_global tablespace"),
    }
    space, form, state, message = values[reason]
    return {**case(tag, "ordinary-index", "pg_default", False, form), "tablespace": space,
            "failureCase": reason, "nativeFailure": [state, message]}


def negatives(tag):
    for reason in ("unknown", "case-distinct", "missing-argument", "shared"):
        item = negative_item(tag, reason)
        boundary = {
            "missing-argument": refusal("tablespace requires a parameter", "SemanticException"),
            "shared": refusal("requires an application tablespace or pg_default"),
        }.get(reason, REFUSAL)
        yield analyzed(item), item, boundary
    normal = case(tag, "ordinary-index", "application", False, "identifier")
    request = analyzed(normal)
    yield {**request, "declarations": [request["declarations"][0], *request["declarations"]]}, None, refusal("only once")
    yield {**direct(normal), "tablespace": RESERVED}, None, refusal("requires an application tablespace or pg_default")


def requests(_, tag, spec):
    for ordinal, axes in enumerate(product(*(spec["finite"][name] for name in AXES))):
        item = case(tag, *axes, coordinate=list(axes))
        yield f"finite/{ordinal}", analyzed(item), item, None
    for ordinal, axes in enumerate(product(*(spec["finite"][name] for name in DIRECT_AXES))):
        item = case(tag, *axes, "identifier", coordinate=list(axes))
        yield f"direct/{ordinal}", direct(item), item, None
    for ordinal, role in enumerate(ROLE_MAP):
        item = case(tag, role, "pg_default", False, "bare-default")
        yield f"edge/stock/{ordinal}", analyzed(item), item, None
    item = case(tag, "ordinary-index", "second-application", True, "unknown-first")
    yield "edge/overwritten", analyzed(item), item, None
    for seed in spec["exploration"]["seeds"]:
        for ordinal, item in enumerate(generate(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", analyzed(item), item, None
    for ordinal, (supplied, item, boundary) in enumerate(negatives(tag)):
        yield f"negative/{ordinal}", supplied, item, boundary


def check_value(item, response):
    expected = {"verbose": item["verbose"], "tablespace": item["tablespace"], **transport(item)}
    require(response.get("status") == "constructed" and response.get("profile") == item["release"] + "/utf8/standard-conforming-strings/native"
            and {key: response.get(key) for key in expected} == expected, "postgres-relocation-value",
            {"expected": expected, "observed": response})


def observer_request(item, command):
    return {"trial": item, "sql": command}


def sample_forms(forms, _response, _engine, item):
    for label in ("relocationRole", "destination", "form", "verbose", "concurrent", "active", "nested"):
        forms[f"{label}/{item[label]}"] += 1


def required_forms(*_):
    return {*(f"relocationRole/{role}" for role in ROLE_MAP), *(f"destination/{space}" for space in DESTINATIONS),
            *(f"form/{form}" for form in FORMS), "verbose/True", "verbose/False", "concurrent/True", "concurrent/False",
            "active/True", "active/False", "nested/True", "nested/False"}


def controls():
    item = case("pg-17.2", "ordinary-index", "application", False, "identifier")
    start = {"owners": [], "links": [], "rows": {}, "indexes": [{"id": 301, "file": 22, "schema": "fixture", "name": "chosen"}],
             "placement": [["fixture", "chosen", TABLESPACES[1], "i", "301", "22"]]}
    moved = {**start, "indexes": [{**start["indexes"][0], "file": 23}],
             "placement": [["fixture", "chosen", TABLESPACES[0], "i", "301", "23"]]}
    compare_placement(item, start, moved, {301: False})
    wrong = {**moved, "placement": [["fixture", "chosen", "pg_default", "i", "301", "23"]]}
    try:
        compare_placement(item, start, wrong, {301: False})
    except Failure as error:
        same(error.signal, "postgres-relocation-storage")
        return {"wrong-allocated-tablespace": error.detail}
    raise Failure("postgres-relocation-comparator-accepted")


def fault_probe(item, response, db):
    if "nativeFailure" in item and rejects(response, REFUSAL):
        return
    require(response.get("status") == "constructed", "postgres-relocation-fault-unexpected-rejection", response)
    command = response["sql"]
    actual = db.request(observer_request(item, command))
    check_observation(item, actual, command)
    check_value(item, response)
    if "nativeFailure" in item:
        raise Failure(ESCAPED, {"sql": command, "observed": actual, "expected": outcome(item)})


def replay_fault(rule, record):
    item, detail = rule.item, record["detail"]
    same(outcome(item), detail["expected"])
    same(record["mutantResponse"]["sql"], detail["sql"])
    if rule.signal == ESCAPED:
        require(rejects(record["healthyResponse"], REFUSAL), "postgres-relocation-fault-healthy-refusal")
        check_value(item, record["mutantResponse"])
        check_observation(item, detail["observed"], detail["sql"])
        return
    check_value(item, record["healthyResponse"])
    try:
        check_observation(item, detail["observed"], detail["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(detail, error.detail)
        return
    raise Failure("postgres-relocation-fault-not-reproduced")


def faults(_, tag):
    base = "sql-semantics-postgres/src/"
    model = base + "Ir/Schema/Index/Placement/"
    repeated = case(tag, "ordinary-index", "application", True, "repeated")
    changed = case(tag, "ordinary-index", "second-application", False, "string")
    absent = negative_item(tag, "unknown")
    return [
        RenderFault("postgres-relocation-omit-placement", model + "IndexTablespace.php",
                    "return 'REINDEX (' . ($verbose ? 'VERBOSE, ' : '') . 'TABLESPACE ' . $this->name->quoted() . ') ';",
                    "return $verbose ? 'REINDEX (VERBOSE) ' : 'REINDEX ';", repeated, "meaning-mismatch"),
        RenderFault("postgres-relocation-original-option-order", base + "Statement/Utility/Maintenance/Reindex.php",
                    "find($this->options, 'tablespace')", "find(array_reverse($this->options), 'tablespace')",
                    repeated, "meaning-mismatch", analyzed(repeated)),
        RenderFault("postgres-relocation-declare-wrong-space", model + "IndexTablespaceLowering.php", "return $target;",
                    "return new IndexTablespace(new Identifier('pg_default'));", changed, "meaning-mismatch", analyzed(changed)),
        RenderFault("postgres-relocation-accept-unknown", model + "IndexTablespaceLowering.php",
                    "$context->knowsTablespace($name)", "true", absent, ESCAPED, analyzed(absent)),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
