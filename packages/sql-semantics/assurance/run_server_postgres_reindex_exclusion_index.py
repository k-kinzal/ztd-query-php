"""Check native and direct rebuilds of declared PostgreSQL exclusion indexes."""

from copy import deepcopy
from itertools import product
import json
from random import Random
import sys

from evidence_transcript import canonical
from postgres_create_btree_cases import refusal
from postgres_reindex_cases import CONCURRENT, FORMS
from postgres_reindex_exclusion_cases import AXES, COVERAGE, DECOY_ROWS, PERSISTENCE, SPELLINGS, STATUS
from postgres_reindex_exclusion_cases import analyzed, case, concurrent_forms, original, rules, valid
from run import Failure, TOOL, digest, require
from run_server import rejects, same
from run_server_drop_table import specifications as drop_contract
from run_server_join import main, probe
from run_server_postgres_create_btree import check_preparation, observation, observer_request, prepare
from sqlite_domain import RenderFault

LABEL = "postgres-reindex-exclusion-index"
CONTRACT_NAME = "postgres-reindex-exclusion-index-v2.json"
CONTRACT = "edaf3da1f4463d9e8f39e63626df943e681d83f8390ee154b41a927b7207a9c7"
OBSERVER = "postgres_reindex_exclusion_observer.py"
COMPARATOR_FAULTS = {"Accept an observation with a stale constraint ownership link": "physical-exclusion-link"}
INVALID = "postgres-reindex-exclusion-invalid-published"
VALUE = "postgres-reindex-exclusion-value"
BOUNDARY = refusal("persistent exclusion index requires a standard rebuild")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-reindex-exclusion-contract")
    spec = json.loads(path.read_text())
    require(spec["exploration"]["allowedExclusions"] == [], "postgres-reindex-exclusion-exclusions")
    return spec, {"postgres": drop_contract()[1]["postgres"]}


def grid(spec):
    return tuple(product(*(spec["finite"][axis] for axis in AXES)))


def finite_ids(_, spec, _tag):
    accepted = {(storage, coverage, concurrent, active, spelling)
                for storage in PERSISTENCE for coverage in COVERAGE for active in (True, False)
                for spelling in SPELLINGS for concurrent in ((True, False) if storage == "temporary" else (False,))}
    require(len(accepted) == 32 and len(grid(spec)) == 48
            and accepted == {point for point in grid(spec) if valid(*point)}, "postgres-reindex-exclusion-finite-grid")
    return accepted


def rejected_finite_ids(spec, tag):
    rejected = set(grid(spec)) - finite_ids("postgres", spec, tag)
    require(len(rejected) == 16, "postgres-reindex-exclusion-negative-grid")
    return rejected


def finite_id(_, result, item):
    coordinates = tuple(item["coordinate"])
    same(case(item["release"], *coordinates)["reindex"], result["reindex"])
    return coordinates


def direct_finite_id(engine, result, item, request):
    coordinate = finite_id(engine, result, item)
    same(canonical(case(item["release"], *coordinate, coordinate=list(coordinate))), canonical(request))
    return coordinate


def analysis_finite_id(engine, result, item, authored):
    coordinate = finite_id(engine, result, item)
    same(original(case(item["release"], *coordinate)), authored)
    return coordinate


def observe_rejection(spec, item, actual, command):
    coordinate = tuple(item["coordinate"])
    same(original(case(item["release"], *coordinate)), command)
    require(coordinate in rejected_finite_ids(spec, item["release"]), "postgres-reindex-exclusion-unexpected-refusal")
    check_observation(item, actual, command)
    return coordinate


def generated(tag, seed, total):
    rng = Random(seed)
    forms = list(FORMS)
    rng.shuffle(forms)
    for ordinal in range(total):
        form = forms[ordinal % len(forms)]
        storage = "temporary" if form in CONCURRENT else rng.choice(tuple(PERSISTENCE))
        yield case(tag, storage, rng.choice(COVERAGE), form in CONCURRENT, bool(rng.getrandbits(1)),
                   rng.choice(SPELLINGS), form=form, schema=rng.choice(("fixture", "Other region")),
                   index=rng.choice(("checked", '索引 "名"')), table=rng.choice(("orders", "注文 表")),
                   base=rng.choice((-101, 0, 17, 2014)))


def negatives(tag):
    positive = case(tag, "persistent", "partial", False, False, "qualified")
    for target, reason in (
        (["fixture", "orders", "orders"], "index and its table must have distinct relation names"),
        (["pg_catalog", "checked", "orders"], "must belong to an application schema or pg_temp"),
        (["pg_temp_42", "checked", "orders"], "must belong to an application schema or pg_temp"),
    ):
        yield {**positive, "reindex": {**positive["reindex"], "target": target}}, refusal(reason)
    request = analyzed(positive)
    yield {**request, "sql": 'DROP INDEX "fixture"."checked"'}, refusal("protected in the ordinary index profile")
    yield {**request, "sql": 'REINDEX INDEX "fixture"."missing"'}, refusal("requires a declared index")


def requests(_, tag, spec):
    for ordinal, coordinates in enumerate(grid(spec)):
        item = case(tag, *coordinates, coordinate=list(coordinates))
        if valid(*coordinates):
            yield f"finite/{ordinal}", analyzed(item), item, None
            yield f"direct/{ordinal}", item, item, None
        else:
            yield f"negative/analyzed/{ordinal}", analyzed(item), item, BOUNDARY
            yield f"negative/direct/{ordinal}", item, None, BOUNDARY
    for seed in spec["exploration"]["seeds"]:
        for ordinal, item in enumerate(generated(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", analyzed(item) if ordinal % 2 else item, item, None
    for ordinal, (request, reason) in enumerate(negatives(tag)):
        yield f"negative/{ordinal}", request, None, reason


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["reindex"] == item["reindex"]
            and reply["profile"] == item["release"] + "/utf8/standard-conforming-strings/native",
            VALUE, {"expected": item["reindex"], "observed": reply, "sql": reply.get("sql")})


def metadata(item, schema, oid, owner, file, constraint_oid):
    selected, name, table = item["reindex"]["target"]
    chosen = schema == selected
    partial = chosen and item["coverage"] == "partial"
    constraint = None
    if chosen:
        constraint = {
            "oid": constraint_oid, "index": oid, "owner": owner, "kind": "x", "name": name,
            "operators": "{96}", "valid": True, "deferrable": False, "deferred": False,
            "defined": "EXCLUDE USING btree (amount WITH =)" + (" WHERE (ready)" if partial else ""),
        }
    return {
        "rows": item["rows"] if chosen else DECOY_ROWS,
        "index": {"oid": oid, "file": file, "owner": owner, "name": name, "ownerName": table, "namespace": schema,
                  "accessMethod": "btree", "exclusion": chosen, "immediate": True, "unique": False, "valid": True,
                  "ready": True, "live": True, "persistence": PERSISTENCE[item["storage"]] if chosen else "p",
                  "slots": "2", "options": None, "predicate": "ready" if partial else None, "constraint": constraint},
    }


def check_phase(item, before, later, moved):
    chosen = item["reindex"]["target"][0]
    same(sorted((chosen, "decoy")), sorted(before))
    same(sorted(before), sorted(later))
    for schema, observed in later.items():
        first, last = before[schema]["index"], observed["index"]
        for numeric in ("oid", "file", "owner"):
            require(type(last[numeric]) == str and last[numeric].isdigit(), "postgres-reindex-exclusion-catalog-id", last)
        owner_link = None
        if schema == chosen:
            owner_link = first["constraint"]["oid"]
            require(type(owner_link) == str and owner_link.isdigit(), "postgres-reindex-exclusion-constraint-id", first)
        same(metadata(item, schema, first["oid"], first["owner"], last["file"], owner_link), observed)
        same(moved and schema == chosen, first["file"] != last["file"])


def check_observation(item, actual, sql):
    require(actual.get("status") == STATUS, "postgres-reindex-exclusion-observer-scope", {"sql": sql, "observed": actual})
    want = rules(item)
    try:
        same(want, {key: actual[key] for key in want})
        before = actual["before"]
        check_phase(item, before, before, False)
        if want["sqlState"] == "00000":
            check_phase(item, before, actual["during"], True)
        else:
            same(None, actual["during"])
        check_phase(item, before, actual["settled"], want["sqlState"] == "00000" and not item["active"])
    except Failure as error:
        raise Failure("meaning-mismatch", {"sql": sql, "expected": want, "observed": actual,
                                          "difference": error.detail}) from error


def sample_forms(forms, reply, _, item):
    for key in ("storage", "coverage", "active", "spelling", "form"):
        forms[f"{key}/{item[key]}"] += 1
    value = reply["reindex"]
    forms[f"mode/{value['mode']}"] += 1
    forms[f"scope/{value['target'][0]}"] += 1
    forms[f"role/{value['exclusionOwned']}"] += 1
    forms[f"native/{rules(item)['sqlState']}"] += 1


def required_forms(*_):
    return {*(f"storage/{mode}" for mode in PERSISTENCE), *(f"coverage/{mode}" for mode in COVERAGE),
            *(f"spelling/{mode}" for mode in SPELLINGS), *(f"form/{form}" for form in concurrent_forms("temporary")),
            "active/True", "active/False", "mode/standard", "mode/concurrently", "scope/fixture", "scope/Other region",
            "scope/pg_temp", "role/True", "native/00000", "native/25001"}


def controls():
    item = case("pg-17.2", "persistent", "partial", False, False, "qualified")
    before = {"fixture": metadata(item, "fixture", "42", "12", "100", "53"),
              "decoy": metadata(item, "decoy", "43", "13", "101", None)}
    changed = {**before, "fixture": metadata(item, "fixture", "42", "12", "102", "53")}
    good = {**rules(item), "before": before, "during": changed, "settled": changed}
    check_observation(item, good, original(item))
    wrong = deepcopy(good)
    wrong["settled"]["fixture"]["index"]["constraint"]["index"] = "84"
    try:
        check_observation(item, wrong, original(item))
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"physical-exclusion-link": {"input": item, "witness": error.detail}}
    raise Failure("postgres-reindex-exclusion-comparator-accepted-stale-owner")


def fault_probe(item, reply, db):
    if "boundary" not in item:
        probe(item, reply, db, sys.modules[__name__])
        return
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "postgres-reindex-exclusion-unrelated-fault", reply)
    raise Failure(INVALID, {"expected": item["boundary"], "observed": reply, "sql": reply["sql"]})


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
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("postgres-reindex-exclusion-fault-not-replayed", rule.name)


def faults(_, tag):
    directory = "sql-semantics-postgres/src/Ir/Schema/Index/"
    root, lookup = directory + "ReindexIndex.php", directory + "IndexTargetLowering.php"
    permanent = case(tag, "persistent", "partial", False, False, "qualified")
    temporary = case(tag, "temporary", "whole", True, True, "search-path")
    invalid = case(tag, "unlogged", "partial", True, False, "qualified")
    return [
        RenderFault("postgres-exclusion-reindex-admit-concurrency", root,
                    "$mode === IndexRebuildMode::Standard || !$target instanceof ExclusionIndexReference || $target->schema->value === 'pg_temp'",
                    "true", {"boundary": BOUNDARY}, INVALID, {**invalid, "constructionOnly": True}),
        RenderFault("postgres-exclusion-reindex-lost-role", lookup,
                    "IndexDeclarationKind::ExclusionConstraint => new ExclusionIndexReference($schema, $index, $table)",
                    "IndexDeclarationKind::ExclusionConstraint => new IndexReference($schema, $index, $table)",
                    permanent, VALUE, analyzed(permanent)),
        RenderFault("postgres-exclusion-reindex-wrong-schema", root, "$this->target->quoted()",
                    """'"decoy".' . $this->target->index->quoted()""", permanent, "meaning-mismatch"),
        RenderFault("postgres-exclusion-reindex-erased-temp-mode", root,
                    "$this->mode === IndexRebuildMode::Concurrent", "false", temporary, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
