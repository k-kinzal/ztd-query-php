"""Check all resolved PostgreSQL rebuild roots with native ordered diagnostics."""

from itertools import product
import json
from random import Random
import sys

from evidence_transcript import canonical
from postgres_create_btree_cases import refusal
from postgres_reindex_verbose_cases import DATABASE, EXPLORED, NATIVE_REFUSALS, OPTIONS, ROLES
from postgres_reindex_verbose_cases import analyzed, case, query, refusal_query, transport, valid
from postgres_reindex_verbose_oracle import check_observation, identity, info, observed_notices
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_drop_table import specifications as drop_contract
from run_server_join import main, probe
from run_server_postgres_create_btree import observation, observer_request, prepare
from run_server_postgres_create_btree import check_preparation as check_libpq
from sqlite_domain import RenderFault

LABEL = "postgres-reindex-verbose"
CONTRACT_NAME = "postgres-reindex-verbose-v1.json"
CONTRACT = "cdebb346ea3a7e8d2d05fd2d8577c124e2179dbfd8adb17a17fdc09ae9777675"
OBSERVER = "postgres_reindex_verbose_observer.py"
AXES = ("role", "form", "active")
VALUE = "postgres-verbose-effective-policy"
COMPARATOR_FAULTS = {"Ignore a missing or changed diagnostic frame in the comparator": "ordered-report-required"}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-verbose-contract")
    spec = json.loads(path.read_text())
    require(spec["exploration"]["allowedExclusions"] == [], "postgres-verbose-exclusions")
    return spec, {"postgres": drop_contract()[1]["postgres"]}


def check_preparation(engine, environment, preparation):
    check_libpq(engine, environment, preparation)
    same(DATABASE, environment["currentDatabase"])
    same("C", environment["locale"])


def grid(spec):
    return tuple(product(*(spec["finite"][dim] for dim in AXES)))


def finite_ids(_, spec, _tag):
    wanted = {(role, form, active) for role in ROLES for form in OPTIONS for active in (False, True)
              if role != "system" or not OPTIONS[form][2]}
    require(len(wanted) == 236 and len(grid(spec)) == 240
            and wanted == {point for point in grid(spec) if valid(*point)}, "postgres-verbose-grid")
    return wanted


def finite_id(_, actual, item):
    coordinate = tuple(item["coordinate"])
    shape = case(item["release"], *coordinate)
    same(shape["verbose"], actual["verbose"])
    same(transport(shape), transport(actual))
    return coordinate


def direct_finite_id(engine, result, item, request):
    coordinate = finite_id(engine, result, item)
    same(canonical(case(item["release"], *coordinate, coordinate=list(coordinate))), canonical(request))
    return coordinate


def analysis_finite_id(engine, result, item, authored):
    coordinate = finite_id(engine, result, item)
    same(query(case(item["release"], *coordinate)), authored)
    return coordinate


def generated(tag, seed, total):
    rng = Random(seed)
    roles = list(EXPLORED)
    rng.shuffle(roles)
    options = list(OPTIONS)
    rng.shuffle(options)
    for ordinal in range(total):
        role = roles[ordinal % len(roles)]
        form = options[ordinal % len(options)]
        active = bool(rng.getrandbits(1))
        if not valid(role, form, active) or (role == "exclusion-index" and OPTIONS[form][2]):
            form = rng.choice([name for name in OPTIONS if not OPTIONS[name][2]])
        yield case(tag, role, form, active,
                   label=rng.choice(("", ' "境界"', "-A")),
                   nested=bool(rng.getrandbits(1)), extra=(ordinal + seed) % 5,
                   base=rng.choice((-800, 0, 1307)), explicit=bool(rng.getrandbits(1)))


def negatives(tag):
    item = case(tag, "ordinary-index", "bare", False)
    for name in NATIVE_REFUSALS:
        witness = {**item, "nativeRejection": name}
        sql, _, diagnostic = refusal_query(witness)
        yield {**analyzed(item), "sql": sql}, witness, refusal(diagnostic, "SemanticException")
    request = analyzed(item)
    yield {**request, "sql": 'REINDEX (VERBOSE, TABLESPACE pg_default) INDEX fixture.chosen'}, None, \
        refusal("cannot move indexes to a tablespace")


def rejected_finite_ids(_, _tag):
    return set(NATIVE_REFUSALS)


def observe_rejection(_, item, actual, sql):
    same(refusal_query(item)[0], sql)
    check_observation(item, actual, sql)
    return item["nativeRejection"]


def requests(_, tag, spec):
    for ordinal, coordinate in enumerate(grid(spec)):
        if not valid(*coordinate):
            continue
        item = case(tag, *coordinate, coordinate=list(coordinate))
        yield f"finite/{ordinal}", analyzed(item), item, None
        yield f"direct/{ordinal}", item, item, None
    for seed in spec["exploration"]["seeds"]:
        for ordinal, item in enumerate(generated(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", analyzed(item) if ordinal % 2 else item, item, None
    for ordinal, (request, item, rejection) in enumerate(negatives(tag)):
        yield f"negative/{ordinal}", request, item, rejection


def check_value(item, response):
    wanted = {"verbose": item["verbose"], **transport(item)}
    actual = {"verbose": response.get("verbose"), **transport(response)}
    require(response["status"] == "constructed" and response["profile"] == item["release"] + "/utf8/standard-conforming-strings/native"
            and actual == wanted, VALUE, {"expected": wanted, "observed": response, "sql": response.get("sql")})


def sample_forms(forms, response, _, item):
    for field in ("role", "form", "active", "nested", "explicit"):
        forms[f"{field}/{item[field]}"] += 1
    roots = transport(response)
    forms.update(f"root/{root}" for root in roots)
    forms[f"verbose/{response['verbose']}"] += 1

def sample_native_forms(forms, measured, _, item):
    forms[f"native/{measured['sqlState']}"] += 1
    count = len(measured["notices"])
    forms[f"reports/count/{count}"] += 1
    forms["reports/zero" if count == 0 else "reports/single" if count == 1 else "reports/multiple"] += 1
    source = item["tables"]["source"]["name"]
    before = measured["before"]
    owner, = (entry["id"] for entry in before["owners"] if identity(entry) == ("fixture", source))
    width = sum(index["owner"] == owner for index in before["indexes"])
    forms[f"fixture/source-indexes/{width}"] += 1
    branches = sum(kind == "p" for kind, *_ in before["links"])
    forms[f"fixture/inner-branches/{branches}"] += 1


def required_forms(*_):
    return {*(f"role/{role}" for role in EXPLORED), *(f"form/{form}" for form in OPTIONS),
            *(f"{axis}/{flag}" for axis in ("active", "nested", "explicit", "verbose") for flag in (True, False)),
            "root/reindex", "root/reindexTable", "root/reindexPartitionTable", "root/reindexSchema", "root/databaseRebuild",
            "native/00000", "native/25001", "reports/zero", "reports/single", "reports/multiple",
            "fixture/inner-branches/0", "fixture/inner-branches/1",
            *(f"fixture/source-indexes/{width}" for width in range(1, 6))}


def controls():
    expected = [info("index", "main", True), info("index", "unrelated", False)]
    witnessed = [["INFO", "00000", 'index "main" was reindexed', "CPU: user: 0.10 s, system: 0.00 s, elapsed: 0.02 s", None],
                 ["INFO", "00000", 'index "unrelated" was reindexed', None, None]]
    same(expected, observed_notices(witnessed))
    for fault in (witnessed[1:], list(reversed(witnessed)),
                  [[*witnessed[0][0:3], "changed diagnostic detail", None], witnessed[1]]):
        try:
            same(expected, observed_notices(fault))
        except Failure as error:
            same("meaning-mismatch", error.signal)
        else:
            raise Failure("postgres-verbose-comparator-accepted-loss", fault)
    return {"ordered-report-required": {"expected": expected, "native": witnessed, "mutant": witnessed[1:]}}


def fault_probe(item, response, db):
    probe(item, response, db, sys.modules[__name__])


def replay_fault(rule, record):
    check_value(rule.item, record["healthyResponse"])
    witness = record["detail"]
    try:
        check_value(rule.item, record["mutantResponse"])
        check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("postgres-verbose-fault-not-replayed", rule.name)


def faults(_, tag):
    base = "sql-semantics-postgres/src/"
    index = base + "Ir/Schema/Index/ReindexIndex.php"
    statement = base + "Statement/Utility/Maintenance/Reindex.php"
    on = case(tag, "ordinary-index", "bare", False)
    off = case(tag, "leaf-index", "last-off", False)
    precedence = case(tag, "ordinary-index", "last-on", False)
    concurrent = case(tag, "temporary-index", "option-combined", True)
    return [
        RenderFault("postgres-verbose-omit-report", index, "$this->verbose ? '(VERBOSE) ' : ''",
                    "false ? '(VERBOSE) ' : ''", on, "meaning-mismatch"),
        RenderFault("postgres-verbose-invert-report", index, "$this->verbose ? '(VERBOSE) ' : ''",
                    "!$this->verbose ? '(VERBOSE) ' : ''", off, "meaning-mismatch"),
        RenderFault("postgres-verbose-first-option", statement, "enabled($this->options, 'verbose', false)",
                    "enabled(array_reverse($this->options), 'verbose', false)", precedence, VALUE, analyzed(precedence)),
        RenderFault("postgres-verbose-erased-concurrent", index, "$this->mode === IndexRebuildMode::Concurrent",
                    "!$this->verbose && $this->mode === IndexRebuildMode::Concurrent", concurrent, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
