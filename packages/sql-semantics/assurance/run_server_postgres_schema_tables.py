"""Compare checked PostgreSQL CREATE SCHEMA table members and their native effects."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import refusal
from postgres_schema_tables_cases import DECOY, SHAPES, STATUS, analyzed, direct, expected, sql, trial
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main
from run_server_postgres_create_schema import check_preparation, observation, observer_request, prepare
from run_server_postgres_create_schema import specifications as namespace_specifications
from sqlite_domain import RenderFault

LABEL = "postgres-schema-tables"
CONTRACT_NAME = "postgres-schema-tables-v2.json"
CONTRACT = "daf19b6c0480964d129f12c3677bc1a546ba3da2ecf95f1daab3700cd2df50df"
OBSERVER = "postgres_schema_tables_observer.py"
AXES = ("memberShapes", "owner", "qualification", "active")
NAME = "Schema tables and sequences require an unguarded, statically named namespace."
CHILD = "A schema table must belong to its namespace and release."
REPEAT = "A repeated schema table requires its own IF NOT EXISTS guard."


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-schema-tables-contract")
    spec = json.loads(path.read_text())
    same([], spec["exploration"]["allowedExclusions"])
    # Namespace and release violations exercise the same constructor condition.
    spec["additionalPostgreSqlNegatives"] = ["cross-release"]
    return spec, namespace_specifications()[1]


def grid(spec):
    return product(*(spec["finite"][axis] for axis in AXES))


def finite_ids(_, spec, _tag):
    observed = set(grid(spec))
    fixed = set(product(SHAPES, ("effective", "named"), ("written", "relative"), (False, True)))
    require(observed == fixed and len(observed) == 32, "postgres-schema-tables-grid")
    return fixed


def analysis_finite_id(_, reply, item, command):
    key = tuple(item["coordinate"])
    same(sql(trial(item["release"], *key)), command)
    check_value(item, reply)
    return key


def direct_finite_id(_, reply, item, request):
    key = tuple(item["coordinate"])
    same(direct(trial(item["release"], *key)), request)
    check_value(item, reply)
    return key


def generated(tag, seed, count):
    rng = Random(seed)
    order = list(SHAPES)
    rng.shuffle(order)
    for index in range(count):
        layout = order[index % len(order)]
        owner = rng.choice(("effective", "named"))
        qualified = rng.choice(("written", "relative"))
        width = rng.randrange(2, 6)
        yield trial(tag, layout, owner, qualified, bool(rng.getrandbits(1)),
                    width=width, base=rng.randrange(-35, 81), quoted_name=bool(rng.getrandbits(1)),
                    derived=owner == "named" and bool(rng.getrandbits(1)))


def negatives(tag):
    item = trial(tag, "guarded-repeat", "named", "written", True)
    def create(path, value):
        request = deepcopy(direct(item))
        field = request["createSchema"]
        for part in path[:-1]:
            field = field[part]
        field[path[-1]] = value
        return request
    yield create(["ifNotExists"], True), refusal(NAME)
    yield create(["tables", 0, "target", 0], DECOY), refusal(CHILD)
    yield create(["tables", 0, "release"], "pg-17.2" if tag == "pg-16.6" else "pg-16.6"), refusal(CHILD)
    yield create(["tables", -1, "ifNotExists"], False), refusal(REPEAT)
    yield {**analyzed(item), "sql": "CREATE SCHEMA AUTHORIZATION CURRENT_USER CREATE TABLE data (n integer)"}, refusal(NAME)
    wrong = {**analyzed(item), "sql": "CREATE SCHEMA making CREATE VIEW public.feed AS SELECT 1"}
    yield wrong, refusal("CREATE specifies a schema (public) different from the one being created (making)", "SemanticException")


def requests(_, tag, spec):
    for index, key in enumerate(grid(spec)):
        item = trial(tag, *key, coordinate=list(key))
        yield f"finite/{index}", analyzed(item), item, None
        yield f"direct/{index}", direct(item), item, None
    prior = trial(tag, "one", "effective", "relative", False)
    prior.update(namespace="owning_schema", explicit="owning_schema", source="CREATE SCHEMA owning_schema CREATE TABLE items (id integer)")
    prior["members"][0].update(target=["owning_schema", "items"], columns=[["id", "INTEGER", True, None]])
    yield "edge/former-table-refusal", analyzed(prior), prior, None
    named = trial(tag, "guarded-repeat", "named", "relative", True, derived=True, quoted_name=True)
    yield "edge/quoted-owner-derived-schema", analyzed(named), named, None
    occupied = trial(tag, "independent", "named", "written", True, occupied=True)
    yield "edge/existing-containing-namespace", analyzed(occupied), occupied, None
    search = spec["exploration"]
    for seed in search["seeds"]:
        for index, item in enumerate(generated(tag, seed, search["casesPerSeed"])):
            yield f"seed/{seed}/{index}", analyzed(item), item, None
            yield f"direct-seed/{seed}/{index}", direct(item), item, None
    for index, (request, boundary) in enumerate(negatives(tag)):
        yield f"negative/{index}", request, None, boundary


def check_value(item, response):
    same([], response.get("schemaSequences"))
    same([], response.get("schemaViews"))
    schema = direct(item)["createSchema"]
    header = {**schema, "name": item["namespace"]}
    del header["tables"]
    same(["constructed", item["release"] + "/utf8/standard-conforming-strings/native", header, item["members"]],
         [response.get("status"), response.get("profile"), response.get("createSchema"), response.get("schemaTables")])


def check_observation(item, actual, statement):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": statement}
    require(actual.get("status") == STATUS, "postgres-schema-tables-observer-scope", witness)
    require({key: actual[key] for key in model} == model, "meaning-mismatch", witness)
    error = (["ERROR", "42P06", f'schema "{item["namespace"]}" already exists', None, None] if item["occupied"] else None)
    require(actual["error"] == error, "meaning-mismatch", witness)


def sample_forms(forms, response, _engine, item):
    forms["layout/" + item["layout"]] += 1
    forms["owner/" + item["owner"]] += 1
    for field in ("qualified", "active", "quoted"):
        forms[f"{field}/{item[field]}"] += 1
    members = response["schemaTables"]
    forms["width/" + str(len(members))] += 1
    for member in members:
        for _, kind, _nullable, default in member["columns"]:
            forms["type/" + kind] += 1
            forms["default/" + (default[0] if default else "omitted")] += 1


def required_forms(*_):
    return {*(f"layout/{layout}" for layout in SHAPES), "owner/effective", "owner/named", "width/1", "width/5",
            *(f"{key}/{value}" for key in ("qualified", "active", "quoted") for value in (False, True)),
            "type/INTEGER", "type/BIGINT", "type/pg_catalog.bool", "type/pg_catalog.text",
            "default/integer", "default/wide-integer", "default/text", "default/boolean", "default/omitted"}


def controls():
    item = trial("pg-17.2", "guarded-repeat", "named", "written", True)
    healthy = {**expected(item), "error": None}
    check_observation(item, healthy, sql(item))
    mutant = deepcopy(healthy)
    mutant["during"]["tables"][0][4].reverse()
    try:
        check_observation(item, mutant, sql(item))
    except Failure as failure:
        same("meaning-mismatch", failure.signal)
        return {"reordered-member-heading": failure.detail}
    raise Failure("postgres-schema-tables-comparator-ignored-order")


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])


def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/Schema/Namespace/CreateSchema.php"
    once = trial(tag, "one", "effective", "relative", False)
    twice = trial(tag, "guarded-repeat", "named", "written", False)
    return [
        RenderFault("schema-tables-omit-child", path, "$owner . $elements;", "$owner;", once, "meaning-mismatch", direct(once)),
        RenderFault("schema-tables-reverse-children", path, "foreach ($this->tables as $table)",
                    "foreach (array_reverse($this->tables) as $table)", twice, "meaning-mismatch", direct(twice)),
        RenderFault("schema-tables-cross-namespace", path, "$elements .= ' ' . $table->toString();",
                    "$elements .= ' ' . str_replace($this->name->quoted(), '\"" + DECOY + "\"', $table->toString());",
                    once, "meaning-mismatch", direct(once)),
    ]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-schema-tables-disposable-service-required")
    main(sys.modules[__name__])
