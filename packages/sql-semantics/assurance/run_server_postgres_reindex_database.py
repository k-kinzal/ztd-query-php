"""Check database and system scopes against complete stock catalog observations."""

import json
from itertools import product
from random import Random
import sys

from evidence_transcript import canonical
from postgres_create_btree_cases import refusal
from postgres_reindex_cases import qualified
from postgres_reindex_database_cases import DATABASE, FIRST_APPLICATION, FORMS, NATIVE_REFUSALS, STATUS
from postgres_reindex_database_cases import analyzed, expected_owners, outcome, owners, physical_changes, sql, stock, trial
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_drop_table import specifications as drop_contract
from run_server_join import main, probe
from run_server_postgres_create_btree import check_preparation as check_libpq
from run_server_postgres_create_btree import observation, observer_request, prepare
from run_server_postgres_reindex_table import snapshot
from sqlite_domain import RenderFault

LABEL = "postgres-reindex-database"
CONTRACT_NAME = "postgres-reindex-database-v2.json"
CONTRACT = "026c42cda0966a04adf26b3e416e3a5af9ff37538d04454d9e9c36741438e66f"
OBSERVER = "postgres_reindex_database_observer.py"
AXES = ("selectors", "spellings", "transactions", "exclusion")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-reindex-database-contract")
    spec = json.loads(path.read_text())
    require(spec["exploration"]["allowedExclusions"] == [], "postgres-reindex-database-exclusions")
    return spec, {"postgres": drop_contract()[1]["postgres"]}


def check_preparation(engine, environment, preparation):
    check_libpq(engine, environment, preparation)
    same(DATABASE, environment["currentDatabase"])


def finite_ids(_, spec, _tag):
    model = {(role, spelling, active, exclusion) for role in FORMS for spelling in ("implicit", "explicit")
             for active in (False,True) for exclusion in (False,True)}
    require(len(model) == 24 and model == set(product(*(spec["finite"][name] for name in AXES))),
            "postgres-reindex-database-grid")
    return model


def finite_id(_, result, item):
    coordinate = tuple(item["coordinate"])
    same(trial(item["release"], *coordinate)["databaseRebuild"], result["databaseRebuild"])
    return coordinate


def direct_finite_id(engine, result, item, request):
    coordinate = finite_id(engine, result, item)
    same(canonical(trial(item["release"], *coordinate, coordinate=list(coordinate))), canonical(request))
    return coordinate


def analysis_finite_id(engine, result, item, authored):
    coordinate = finite_id(engine, result, item)
    same(sql(trial(item["release"], *coordinate)), authored)
    return coordinate


def generated(tag, seed, total):
    rng = Random(seed)
    for _ in range(total):
        role = rng.choice(tuple(FORMS))
        yield trial(tag, role, rng.choice(("implicit", "explicit")), bool(rng.getrandbits(1)), bool(rng.getrandbits(1)),
                    form=rng.choice(tuple(FORMS[role])), base=rng.choice((-120, 11, 947)), quoted_name=bool(rng.getrandbits(1)))


def negatives(tag):
    item = trial(tag, "ordinary-database", "implicit", False, True)
    good = analyzed(item)
    unbound = {key: value for key, value in good.items() if key != "currentCatalog"}
    for statement in ("REINDEX DATABASE", 'REINDEX SYSTEM "ir_fixture"'):
        yield {**unbound, "sql": statement}, None, refusal("requires a declared current database")
    for name, command in NATIVE_REFUSALS.items():
        reason = refusal("concurrent", "SemanticException") if name == "concurrent-system" else refusal("must select the declared current database")
        yield {**good, "sql": command}, {**item, "nativeRejection": name}, reason
    yield {**good, "sql": "REINDEX (TABLESPACE pg_default) DATABASE ir_fixture"}, None, refusal("cannot move indexes to a tablespace")


def rejected_finite_ids(_, _tag):
    return set(NATIVE_REFUSALS)


def observe_rejection(_, item, actual, command):
    name = item["nativeRejection"]
    same(NATIVE_REFUSALS[name], command)
    check_observation(item, actual, command)
    return name


def requests(_, tag, spec):
    for ordinal, axes in enumerate(product(*(spec["finite"][name] for name in AXES))):
        item = trial(tag, *axes, coordinate=list(axes))
        yield f"finite/{ordinal}", analyzed(item), item, None
        yield f"direct/{ordinal}", item, item, None
    ordinal = 0
    for group, forms in FORMS.items():
        for form in tuple(forms)[1:]:
            item = trial(tag, group, "explicit", False, True, form=form)
            yield f"edge/{ordinal}", analyzed(item), item, None
            ordinal += 1
    for seed in spec["exploration"]["seeds"]:
        for ordinal, item in enumerate(generated(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", analyzed(item), item, None
    for ordinal, (request, item, failure) in enumerate(negatives(tag)):
        yield f"negative/{ordinal}", request, item, failure


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["databaseRebuild"] == item["databaseRebuild"]
            and reply["profile"] == item["release"] + "/utf8/standard-conforming-strings/native",
            "postgres-reindex-database-value", reply)


def catalog_coordinates(raw):
    return {tuple(entry[:3]): entry[3:] for entry in raw["catalog"]}


def requested_app_indexes(item, actual):
    expected = []
    for role, (schema, name, key, _, persistence) in owners(item).items():
        actual_owner = actual["owners"][qualified(schema, name)]
        ordinary = actual_owner["indexes"][0]
        expected.append([schema, key, name, actual_owner["oid"], False, persistence, True, role == "exclusion",
                         ordinary["oid"], ordinary["file"]])
        if role == "exclusion":
            continue
        auxiliary = actual_owner["toastIndexes"][0]
        defined = "pg_toast_" + actual_owner["oid"]
        expected.append(["pg_toast_temp" if persistence == "t" else "pg_toast", defined + "_index", defined,
                         actual_owner["toastOid"], False, persistence, True, False, auxiliary["oid"], auxiliary["file"]])
    return sorted(expected)


def check_catalog(item, first, last):
    before, settled = catalog_coordinates(first), catalog_coordinates(last)
    same(len(first["catalog"]), len(before))
    same(len(last["catalog"]), len(settled))
    same(sorted(before), sorted(settled))
    for catalog in (first, last):
        pinned = sorted(entry[:8] for entry in catalog["catalog"] if int(entry[3]) < FIRST_APPLICATION)
        app = sorted(entry for entry in catalog["catalog"] if int(entry[3]) >= FIRST_APPLICATION)
        same(stock(item["release"]), pinned)
        same(requested_app_indexes(item, catalog), app)
    for name, metadata in before.items():
        after = settled[name]
        same(metadata[:-2], after[:-2])
        owner_oid, _, persistence, _, exclusion, oid, file = metadata
        observed = [file != after[-1], oid != after[-2]]
        wanted = physical_changes(item, persistence, owner_oid, exclusion)
        require(wanted == observed, "postgres-reindex-selected-index", {"index": name, "expected": wanted, "observed": observed})


def check_observation(item, actual, emitted):
    require(actual.get("status") == STATUS, "postgres-reindex-database-unrelated", {"sql": emitted, "actual": actual})
    want = outcome(item)
    try:
        same(want, {key: actual[key] for key in want})
        before, settled = actual["before"], actual["settled"]
        first, _ = snapshot(before["owners"], before["owners"])
        end, _ = snapshot(before["owners"], settled["owners"])
        expected = expected_owners(item)
        same(expected, first)
        same(expected, end)
        check_catalog(item, before, settled)
    except Failure as error:
        raise Failure("meaning-mismatch", {"sql": emitted, "expected": want, "observed": actual,
                                          "difference": error.detail}) from error


def sample_forms(forms, reply, _, item):
    for key in ("selection", "spelling", "active", "exclusion", "form"):
        forms[f"{key}/{item[key]}"] += 1
    value = reply["databaseRebuild"]
    forms[f"selector/{value['selector']}"] += 1
    if value["selector"] == "database":
        forms[f"mode/{value['mode']}"] += 1


def required_forms(*_):
    return {*(f"selection/{role}" for role in FORMS),
            *(f"form/{form}" for group in FORMS.values() for form in group),
            "spelling/implicit", "spelling/explicit", "active/True", "active/False",
            "exclusion/True", "exclusion/False", "selector/system", "selector/database", "mode/standard", "mode/concurrently"}


def controls():
    probe = trial("pg-17.2", "system-catalogs", "implicit", False, True)
    same([True,False], physical_changes(probe, "p", "1247", False))
    same([False,False], physical_changes(probe, "p", "13200", False))
    concurrent = trial("pg-17.2", "concurrent-database", "explicit", False, True)
    same([True,True], physical_changes(concurrent, "p", "13200", False))
    same([False,False], physical_changes(concurrent, "p", "16385", True))
    same([True,False], physical_changes(concurrent, "t", "16387", False))
    return {"system-follows-pinned-owner": stock("pg-17.2")[:2], "application-retains-exclusion": outcome(concurrent)["notices"]}


def fault_probe(item, response, db):
    probe(item, response, db, sys.modules[__name__])


def replay_fault(rule, record):
    check_value(rule.item, record["healthyResponse"])
    check_value(rule.item, record["mutantResponse"])
    witness = record["detail"]
    try:
        check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("postgres-reindex-database-fault-not-replayed", rule.name)


def faults(_, tag):
    directory = "sql-semantics-postgres/src/Ir/Schema/Index/Scope/"
    database, system = directory + "ReindexDatabase.php", directory + "ReindexSystemCatalogs.php"
    ordinary = trial(tag, "ordinary-database", "explicit", False, False)
    concurrent = trial(tag, "concurrent-database", "explicit", False, True)
    catalogs = trial(tag, "system-catalogs", "explicit", False, True)
    return [
        RenderFault("postgres-database-rebuild-system", database, "'DATABASE '", "'SYSTEM '",
                    ordinary, "meaning-mismatch"),
        RenderFault("postgres-system-rebuild-application", system, "'SYSTEM '", "'DATABASE '",
                    catalogs, "meaning-mismatch"),
        RenderFault("postgres-database-rebuild-discard-mode", database, "$this->mode === IndexRebuildMode::Concurrent", "false",
                    concurrent, "meaning-mismatch"),
        RenderFault("postgres-database-rebuild-wrong-identity", database, "$this->database->quoted()", """'"other"'""",
                    ordinary, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
