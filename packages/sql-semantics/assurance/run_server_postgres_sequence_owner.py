"""Compare ordered automatic dependencies and native sequence generation effects."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import refusal
from postgres_create_sequence_cases import DATABASE, DECOY, SCHEMA, direct as generator_direct
from postgres_create_sequence_cases import POLICIES, TYPES, resolved
from postgres_reindex_cases import qualified
from postgres_sequence_owner_cases import ALTERNATE, COLUMNS, FDW, FIXATION, ROLES, STATUS, analyzed, direct, expected, sql, trial
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_drop_table import specifications as drop_profiles
from run_server_join import main
from run_server_postgres_create_btree import observation, prepare
from run_server_postgres_create_btree import check_preparation as check_libpq
from run_server_postgres_create_sequence import check_identities, stripped
from sqlite_domain import RenderFault

LABEL = "postgres-sequence-owner"
CONTRACT_NAME = "postgres-sequence-ownership-v3.json"
CONTRACT = "69b0863ad0e50adf7a9139d57c2e578f7bb5e972425c1b67b6f77d69206b59ec"
OBSERVER = "postgres_sequence_owner_observer.py"
AXES = ("ownerRoles", "columnRoles", "fixation", "guarded", "transactional")
MISSING_OWNER = refusal("Sequence ownership requires a definite declared relation.")
MISSING_COLUMN = refusal("Sequence ownership requires one declared column.")
SAME_SCHEMA = refusal("A sequence owner must belong to the same namespace.")
OWNER_KIND = refusal("Sequence ownership requires an eligible table-like relation.")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-sequence-owner-contract")
    spec = json.loads(path.read_bytes())
    same([], spec["exploration"]["allowedExclusions"])
    return spec, {"postgres": drop_profiles()[1]["postgres"]}


def check_preparation(engine, environment, preparation):
    check_libpq(engine, environment, preparation)
    same([DATABASE, "postgres", [[FDW, "t"]]],
         [environment["currentDatabase"], environment["checkedOwner"], environment["fixtureWrapper"]])


def grid(spec):
    return product(*(spec["finite"][axis] for axis in AXES))


def finite_ids(_engine, spec, _tag):
    independent = set(product(ROLES, COLUMNS, FIXATION, (False, True), (False, True)))
    require(set(grid(spec)) == independent and len(independent) == 120, "postgres-sequence-owner-grid")
    return independent


def analysis_finite_id(_, observed, item, command):
    key = tuple(item["coordinate"])
    same(sql(trial(item["release"], *key)), command)
    check_value(item, observed)
    return key


def direct_finite_id(_, observed, item, request):
    key = tuple(item["coordinate"])
    same(direct(trial(item["release"], *key)), request)
    check_value(item, observed)
    return key


def generated(tag, seed, count):
    rng = Random(seed)
    roles = list(ROLES)
    rng.shuffle(roles)
    for index in range(count):
        occupied = {3: "sequence", 8: "table"}.get(index)
        yield trial(tag, roles[index % len(roles)], rng.choice(COLUMNS), rng.choice(FIXATION),
                    occupied is not None or bool(rng.getrandbits(1)), bool(rng.getrandbits(1)),
                    storage=rng.choice(tuple(TYPES)), direction=rng.choice((1, -1)), policy=rng.choice(POLICIES),
                    quoted_name=bool(rng.getrandbits(1)), occupied=occupied, base=rng.randrange(12, 50))


def edges(tag):
    old = trial(tag, "table", "leading", "schema", False, True, storage="bigint", policy="default")
    old.update(ownerColumns=["id", "trailing"], ownership=[SCHEMA, "owner", "id", "table"],
               command=f'CREATE SEQUENCE "{SCHEMA}"."ticket" OWNED BY {SCHEMA}.owner.id')
    yield "formerly-refused-owned-column", old
    system = trial(tag, "table", "trailing", "catalog", False, True)
    system.update(columnRole="system", ownership=[SCHEMA, "owner", "ctid", "table"])
    yield "system-attribute", system
    yield "guarded-existing-owner-state", trial(tag, "view", "trailing", "relative", True, True, occupied="sequence")
    wrong_role = trial(tag, "table", "trailing", "schema", False, True)
    yield "native-different-authorization", {**wrong_role, "nativeRefusal": "55000", "roleMismatch": True}
    yield "quoted-owner-attribute", trial(tag, "temporary-table", "trailing", "catalog", False, True, quoted_name=True)


def refusals(tag):
    item = trial(tag, "table", "leading", "schema", False, True)
    prefix = f"CREATE SEQUENCE {SCHEMA}.ticket OWNED BY "
    base = analyzed(item)
    index = f'CREATE INDEX owner_blocked ON {SCHEMA}.owner ("leading")'
    index_decoy = f"CREATE TABLE {DECOY}.owner_blocked (n integer)"
    negatives = [
        ("relation", f"{SCHEMA}.absent.leading", "42P01", MISSING_OWNER, []),
        ("column", f"{SCHEMA}.owner.absent", "42703", MISSING_COLUMN, []),
        ("namespace", f"{DECOY}.owner.leading", "55000", SAME_SCHEMA, []),
        ("database", f"outside.{SCHEMA}.owner.leading", "0A000",
         refusal("An explicit PostgreSQL catalog requires the matching current database."), []),
        ("materialized", f"{SCHEMA}.mv.n", "42809", refusal('sequence cannot be owned by relation "mv"', "SemanticException"),
         [f"CREATE MATERIALIZED VIEW {SCHEMA}.mv AS SELECT 42 AS n"]),
        ("index", "owner_blocked.n", "42809", OWNER_KIND, [index, index_decoy]),
        ("one-part", "owner", "42601", refusal("Sequence ownership requires NONE or an exact relation column."), []),
        ("wide", f"{DATABASE}.{DATABASE}.{SCHEMA}.owner.leading", None,
         refusal("Sequence ownership requires NONE or an exact relation column."), []),
        ("duplicate", f"NONE OWNED BY {SCHEMA}.owner.leading", "42601", refusal("A sequence option may be specified only once."), []),
    ]
    for label, name, state, boundary, more in negatives:
        chosen = {**item, "command": prefix + name, "nativeRefusal": state, "inputRefusal": True,
                  "extraRelations": more, "negative": label}
        yield analyzed(chosen), chosen if state else None, boundary
    other_table = f'CREATE TABLE {SCHEMA}.owner ("leading" integer, "trailing" text, extra boolean)'
    yield {**base, "declarations": [*base["declarations"], other_table]}, None, refusal(
        "Sequence ownership cannot resolve conflicting relation declarations.")
    wrong = direct(item)
    wrong["createSequence"]["owner"] = [DECOY, "owner", "leading", "table"]
    yield wrong, None, SAME_SCHEMA
    member = direct(item)["createSequence"]
    nested = {"dialect": "postgres", "release": tag, "createSchema": {
                  "name": SCHEMA, "owner": {"session": "CURRENT_USER"}, "ifNotExists": False,
                  "sequences": [{"release": tag, **member}],
              }}
    yield nested, None, refusal("A schema sequence cannot depend on a relation created after its sequence phase.")


def requests(_, tag, spec):
    for ordinal, key in enumerate(grid(spec)):
        item = trial(tag, *key, coordinate=list(key))
        yield f"finite/{ordinal}", analyzed(item), item, None
        yield f"direct/{ordinal}", direct(item), item, None
    for label, item in edges(tag):
        yield f"edge/{label}", analyzed(item), item, None
    settings = spec["exploration"]
    for seed in settings["seeds"]:
        for index, item in enumerate(generated(tag, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", analyzed(item), item, None
            yield f"direct-seed/{seed}/{index}", direct(item), item, None
    for index, (request, item, rejection) in enumerate(refusals(tag)):
        yield f"negative/{index}", request, item, rejection


def check_value(item, reply):
    expected_graph = generator_direct(resolved(item))["createSequence"]
    same(["constructed", item["release"] + "/utf8/standard-conforming-strings/native", expected_graph],
         [reply.get("status"), reply.get("profile"), reply.get("createSequence")])


def observer_request(item, command):
    return {"trial": item, "sql": command}


def check_observation(item, actual, command):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": command}
    require(actual.get("status") == STATUS, "sequence-owner-observer-scope", witness)
    stripped_state = {key: stripped(actual[key]) if key in ("before", "during", "rollback") else actual[key] for key in model}
    require(stripped_state == model, "meaning-mismatch", witness)
    error = actual["error"]
    require(error is None if model["sqlState"] == "00000"
            else error is not None and error[:2] == ["ERROR", model["sqlState"]], "meaning-mismatch", witness)
    check_identities(actual, witness)


def observe_rejection(_, item, actual, command):
    same(item["command"], command)
    check_observation(item, actual, command)
    return item["negative"]


def rejected_finite_ids(_spec, _tag):
    return {"relation", "column", "namespace", "database", "materialized", "index", "one-part", "duplicate"}


def sample_forms(forms, reply, _engine, item):
    built = reply["createSequence"]
    for key in ("ownerRole", "fixation", "columnRole", "quoted", "active", "occupied"):
        forms[f"{key}/{item[key]}"] += 1
    forms["kind/" + built["owner"][3]] += 1
    forms["integer/" + built["storage"].lower()] += 1
    forms["guard/" + str(built["ifNotExists"])] += 1


def required_forms(*_):
    return {*(f"ownerRole/{role}" for role in ROLES), *(f"fixation/{mode}" for mode in FIXATION),
            *(f"columnRole/{column}" for column in COLUMNS), *(f"integer/{type_}" for type_ in TYPES),
            *(f"{key}/{value}" for key in ("guard", "active", "quoted") for value in (False, True)),
            "occupied/None", "occupied/sequence", "occupied/table"}


def controls():
    item = trial("pg-17.2", "foreign", "trailing", "relative", False, True)
    sample = deepcopy({**expected(item), "error": None})
    for phase in ("before", "during", "rollback"):
        sample[phase]["decoy"]["oid"] = 25
    sample["during"]["selected"]["oid"] = 58
    check_observation(item, sample, sql(item))
    broken = deepcopy(sample)
    broken["during"]["selected"]["ownership"][0][4] = 1
    try:
        check_observation(item, broken, sql(item))
    except Failure as failure:
        same("meaning-mismatch", failure.signal)
        return {"different-native-attnum": failure.detail}
    raise Failure("postgres-sequence-owner-ignored-native-slot")


def check_fault_witness(item, result):
    same(expected(item), result["expected"])
    same([STATUS, "00000"], [result["observed"]["status"], result["observed"]["sqlState"]])


def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/Schema/Sequence/CreateSequence.php"
    case = trial(tag, "table", "leading", "schema", False, True)
    return [
        RenderFault("sequence-drop-owner-dependency", path, "' OWNED BY ' . $this->owner->quoted()", "''",
                    case, "meaning-mismatch", direct(case)),
        RenderFault("sequence-change-owner-attribute", path, "$this->owner->quoted()",
                    "str_replace('\"leading\"', '\"trailing\"', $this->owner->quoted())", case, "meaning-mismatch", direct(case)),
        RenderFault("sequence-change-owner-relation", path, "$this->owner->quoted()",
                    "str_replace('\"owner\"', '\"" + ALTERNATE + "\"', $this->owner->quoted())", case, "meaning-mismatch", direct(case)),
    ]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-sequence-owner-disposable-service-required")
    main(sys.modules[__name__])
