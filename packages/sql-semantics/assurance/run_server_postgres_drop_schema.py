"""Compare ordered schema removal and native dependency effects."""

from itertools import product
import json
from random import Random
import re
import sys

from postgres_create_btree_cases import refusal
from postgres_drop_schema_cases import CATALOG, PLANS, ROLES, STATUS, analyzed, case, direct, outcome, sql
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_drop_table import specifications as drop_contract
from run_server_join import main
from run_server_postgres_create_btree import check_preparation as check_libpq
from run_server_postgres_create_btree import observation, prepare
from sqlite_domain import RenderFault

LABEL = "postgres-drop-schema"
CONTRACT_NAME = "postgres-drop-schema-v2.json"
CONTRACT = "0cbded9faaeb487e6eb711a1f2be7a93caa5e1b1c580e612806a0c432018b58d"
OBSERVER = "postgres_drop_schema_observer.py"
AXES = ("targetLists", "guarded", "cascade", "transactional")
NO_GUARD = refusal("An absent schema removal target requires IF EXISTS.")
FAMILIES = ("external", "local", "empty")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-drop-schema-contract")
    spec = json.loads(path.read_text())
    require(spec["exploration"]["allowedExclusions"] == [], "postgres-drop-schema-exclusions")
    spec["additionalPostgreSqlNegatives"] = list(range(spec["negativeFinite"]["coordinates"]))
    return spec, {"postgres": drop_contract()[1]["postgres"]}


def check_preparation(engine, environment, preparation):
    check_libpq(engine, environment, preparation)
    same(CATALOG, environment["currentDatabase"])


def grid(spec):
    observed = {(tuple(plan), guard, cascade, active) for plan, guard, cascade, active in
                product(*(spec["finite"][key] for key in AXES))}
    expected = set(product(PLANS, (False, True), (False, True), (False, True)))
    require(observed == expected and len(observed) == 56, "postgres-drop-schema-grid")
    return expected


def rejected_finite_ids(spec, _tag):
    missing = {coordinate for coordinate in grid(spec) if not coordinate[1] and "missing" in coordinate[0]}
    require(len(missing) == spec["negativeFinite"]["coordinates"], "postgres-drop-schema-invalid-finite-grid")
    return missing


def finite_ids(_, spec, tag):
    return grid(spec) - rejected_finite_ids(spec, tag)


def ordinal(item):
    roles, *flags = item["coordinate"]
    return tuple(roles), *flags


def direct_finite_id(_, result, item, request):
    coordinate = ordinal(item)
    same(direct(case(item["release"], *coordinate)), request)
    check_value(item, result)
    return coordinate


def analysis_finite_id(_, result, item, command):
    coordinate = ordinal(item)
    same(sql(case(item["release"], *coordinate), explicit=False), command)
    check_value(item, result)
    return coordinate


def observe_rejection(_, item, actual, command):
    same(sql(item, explicit=False), command)
    check_observation(item, actual, command)
    return ordinal(item)


def generated(tag, seed, count):
    rng = Random(seed)
    for _ in range(count):
        plan = rng.choices(ROLES, k=rng.randrange(1, 6))
        yield case(tag, plan, "missing" in plan or bool(rng.getrandbits(1)), bool(rng.getrandbits(1)),
                   bool(rng.getrandbits(1)), quoted_names=bool(rng.getrandbits(1)), family=rng.choice(FAMILIES),
                   base=rng.randrange(-17, 18))


def typed_refusals(tag):
    empty = case(tag, (), True, False, False)
    yield direct(empty), refusal("requires a nonempty ordered selector list")
    absent = case(tag, ("missing",), False, False, False)
    yield direct(absent), NO_GUARD
    repeated = case(tag, ("selected", "selected"), True, False, False)
    contradictory = direct(repeated)
    contradictory["dropSchema"]["targets"][1][1] = False
    yield contradictory, refusal("cannot disagree about a named target")
    guarded = analyzed(case(tag, ("missing",), True, True, False))
    yield {**guarded, "complete": False}, refusal("requires a complete namespace declaration context")
    yield {**guarded, "sql": "DROP SCHEMA IF EXISTS pg_temp"}, refusal("needs a persistent namespace coordinate")
    yield {**guarded, "sql": "DROP SCHEMA IF EXISTS fixture.incomplete"}, refusal("Unexpected '.'", "AnalysisException")


def requests(_, tag, spec):
    for index, keys in enumerate(product(*(spec["finite"][axis] for axis in AXES))):
        item = case(tag, *keys, coordinate=list(keys))
        if "missing" in keys[0] and not keys[1]:
            yield f"negative-finite/{index}", analyzed(item), item, NO_GUARD
        else:
            yield f"finite/{index}", analyzed(item), item, None
            yield f"direct/{index}", direct(item), item, None
    lower = case(tag, ("selected",), False, True, True)
    yield "edge/relation-bound-namespace", analyzed(lower, explicit=False), lower, None
    settings = spec["exploration"]
    for seed in settings["seeds"]:
        for index, item in enumerate(generated(tag, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", analyzed(item), item, None
            yield f"direct-seed/{seed}/{index}", direct(item), item, None
    for index, (request, boundary) in enumerate(typed_refusals(tag)):
        yield f"negative/{index}", request, None, boundary


def check_value(item, result):
    expected = direct(item)["dropSchema"]
    require(result.get("status") == "constructed" and result.get("profile") == item["release"] + "/utf8/standard-conforming-strings/native"
            and result.get("dropSchema") == expected, "postgres-drop-schema-value", {"expected": expected, "observed": result})


def observer_request(item, command):
    return {"trial": item, "sql": command}


def notices(frames):
    missing, count = [], 0
    for level, state, message, detail, hint in frames:
        require(level == "NOTICE" and state == "00000" and hint is None, "postgres-drop-schema-unexpected-diagnostic", frames)
        if message.startswith('schema "') and message.endswith('" does not exist, skipping'):
            same(None, detail)
            missing.append([level, state, message])
        elif message.startswith("drop cascades to table "):
            same(None, detail)
            count += 1
        else:
            match = re.fullmatch(r"drop cascades to ([0-9]+) other objects", message)
            require(match is not None and type(detail) is str, "postgres-drop-schema-cascade-diagnostic", frames)
            details = detail.splitlines()
            same(int(match[1]), len(details))
            require(all(line.startswith("drop cascades to ") for line in details), "postgres-drop-schema-cascade-objects", details)
            count += len(details)
    return missing, count


def check_observation(item, actual, command):
    model = outcome(item)
    require(actual.get("status") == STATUS, "postgres-drop-schema-observer-scope", {"sql": command, "observed": actual})
    try:
        values = {key: actual[key] for key in model if key not in ("missingNotices", "cascadeCount")}
        values["missingNotices"], values["cascadeCount"] = notices(actual["notices"])
        same(model, values)
        if model["sqlState"] == "00000":
            same(None, actual["error"])
        else:
            same(["ERROR", model["sqlState"]], actual["error"][:2])
    except Failure as failure:
        raise Failure("meaning-mismatch", {"sql": command, "expected": model, "observed": actual,
                                          "difference": failure.detail}) from failure


def sample_forms(forms, _response, _engine, item):
    for field in ("ifExists", "cascade", "active", "quotedNames", "family"):
        forms[f"{field}/{item[field]}"] += 1
    forms["roles/" + ("repeat" if len(set(item["roles"])) != len(item["roles"]) else "distinct")] += 1
    forms["roles/width/" + str(len(item["roles"]))] += 1


def required_forms(*_):
    return {*(f"{axis}/{member}" for axis in ("ifExists", "cascade", "active", "quotedNames") for member in (True, False)),
            *(f"family/{family}" for family in FAMILIES), "roles/repeat", "roles/distinct",
            "roles/width/1", "roles/width/5"}


def controls():
    trial = case("pg-17.2", ("missing", "missing"), True, False, True)
    model = outcome(trial)
    healthy = {key: value for key, value in model.items() if key not in ("missingNotices", "cascadeCount")}
    healthy.update(notices=[[*notice, None, None] for notice in model["missingNotices"]], error=None)
    check_observation(trial, healthy, sql(trial))
    mutant = {**healthy, "notices": healthy["notices"][:1]}
    try:
        check_observation(trial, mutant, sql(trial))
    except Failure as failure:
        same("meaning-mismatch", failure.signal)
        return {"repeat-missing-diagnostic": failure.detail}
    raise Failure("postgres-drop-schema-accepted-duplicate-loss")


def check_fault_witness(item, witness):
    same(outcome(item), witness["expected"])
    require(witness["observed"]["status"] == STATUS, "postgres-drop-schema-fault-native")


def faults(_, tag):
    file = "sql-semantics-postgres/src/Ir/Schema/Namespace/DropSchema.php"
    selected = case(tag, ("selected",), False, True, False)
    repeated = case(tag, ("missing", "missing"), True, False, True)
    return [
        RenderFault("drop-schema-decoy-name", file, "$names[] = $target->name->quoted();",
                    "$names[] = $target->name->value === 'ir_schema_drop_front' ? '\"ir_schema_drop_later\"' : $target->name->quoted();",
                    selected, "meaning-mismatch", direct(selected)),
        RenderFault("drop-schema-erase-cascade", file, "($this->cascade ? ' CASCADE' : ' RESTRICT')",
                    "' RESTRICT'", selected, "meaning-mismatch", direct(selected)),
        RenderFault("drop-schema-deduplicate-missing", file, "implode(', ', $names)",
                    "implode(', ', array_unique($names))", repeated, "meaning-mismatch", direct(repeated)),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
