"""Compare PostgreSQL table and column name changes against an independent catalog model."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import main, probe
from run_server_postgres_values import DIAGNOSTIC, TYPES, specifications as profiles
from sqlite_domain import RenderFault

LABEL = "postgres-table-rename"
OBSERVER = "postgres_table_rename_observer.php"
CONTRACT_NAME = "postgres-table-rename-v2.json"
CONTRACT = "5ed9f2c09a8b9caa59b9a0fc9c9f20ffd0c1eeed02fedca55c56dd824e30b730"
COMPLETE_SEEDS = True
SCHEMA, DECOY = "public", "ir_rn_decoy"
NAME, CHILD, OTHER, VIEW = "ir_rn_target", "ir_rn_child", "ir_rn_occupied", "ir_rn_reader"
PARAMETERS = ("fixture", "target", "optional", "destination")
AXES = {"table": PARAMETERS, "column": (*PARAMETERS, "only")}
SEMANTIC = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\SemanticException"
FIELDS = [[1, "id", *TYPES["integer"]], [2, "label", *TYPES["text"]]]
MAIN_ROWS = [[1, "main"], [None, "unset"], [1, "main"]]
CHILD_ROWS = [[19, "descendant", "local"]]


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-rename-contract-mismatch")
    return json.loads(path.read_text()), profiles()[1]


def change(tag, role, fixture="plain", schema=SCHEMA, if_exists=False, destination=None, only=False, column="id"):
    fallback = "total" if role == "column" else "ir_rn_archive"
    action = {"role": role, "target": [schema, NAME], "ifExists": if_exists,
              "destination": fallback if destination is None else destination}
    if role == "column":
        action.update(column=column, only=only)
    return {"dialect": "postgres", "release": tag, "fixture": fixture, "rename": action}


def occupied(role, column):
    if role == "table":
        return OTHER
    return "label" if column == "id" else "id"


def finite(tag, role, fixture, target, optional, destination, only=False):
    return change(tag, role, fixture, SCHEMA if target == "main" else DECOY, optional,
                  occupied(role, "id") if destination == "occupied" else None, only)


def finite_ids(_, spec, _tag):
    expected = set()
    for role, axes in AXES.items():
        fixed = spec["finite"][role]
        values = set(product(*(fixed[axis] for axis in axes)))
        require(len(values) == fixed["identities"], "postgres-rename-grid-size", role)
        expected.update((role, *identity) for identity in values)
    return expected


def finite_id(_, reply, item):
    action = reply["rename"]
    role = action["role"]
    return (role, item["fixture"], "main" if action["target"][0] == SCHEMA else "decoy", action["ifExists"],
            "occupied" if action["destination"] == occupied(role, action.get("column")) else "fresh",
            *([action["only"]] if role == "column" else []))


def analysis_request(tag, sql, fixture="plain", wrong_kind=False):
    declarations = [f"CREATE TABLE {DECOY}.{NAME} (id integer,label pg_catalog.text)"]
    if fixture != "absent":
        declarations.append(f"CREATE TABLE {SCHEMA}.{NAME} (id integer,label pg_catalog.text)" if not wrong_kind else
                            f"CREATE VIEW {SCHEMA}.{NAME} AS SELECT 4 AS id")
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": declarations}


def analyzed(tag):
    yield f"ALTER TABLE {NAME} RENAME TO ir_rn_archive", change(tag, "table")
    yield f"ALTER TABLE ONLY {NAME} RENAME TO ir_rn_archive", change(tag, "table", "inherited")
    yield f'ALTER TABLE {SCHEMA}.{NAME} RENAME TO "ir_rn_Archived Row"', change(tag, "table", destination="ir_rn_Archived Row")
    yield f"ALTER TABLE IF EXISTS {SCHEMA}.{NAME} RENAME TO ir_rn_archive", change(tag, "table", "absent", if_exists=True)
    yield f"ALTER TABLE {NAME} RENAME id TO total", change(tag, "column")
    yield f"ALTER TABLE ONLY {SCHEMA}.{NAME} RENAME COLUMN id TO total", change(tag, "column", only=True)
    yield f"ALTER TABLE {SCHEMA}.{NAME} RENAME COLUMN id TO total", change(tag, "column", "inherited")
    yield f"ALTER TABLE IF EXISTS {SCHEMA}.{NAME} RENAME COLUMN id TO total", change(tag, "column", "absent", if_exists=True)
    yield f"ALTER TABLE IF EXISTS {SCHEMA}.{NAME} RENAME TO {NAME}", change(tag, "table", "absent", if_exists=True, destination=NAME)
    yield f"ALTER TABLE IF EXISTS {SCHEMA}.{NAME} RENAME COLUMN id TO id", change(tag, "column", "absent", if_exists=True, destination="id")


def negatives(tag):
    yield change(tag, "table", destination=NAME), DIAGNOSTIC, "A table rename requires a distinct destination name."
    yield change(tag, "column", destination="id"), DIAGNOSTIC, "A column rename requires a distinct destination name."
    yield change(tag, "column", column="ctid"), DIAGNOSTIC, "A column rename requires user-column identifiers."
    yield change(tag, "column", destination="ctid"), DIAGNOSTIC, "A column rename requires user-column identifiers."
    yield analysis_request(tag, f"ALTER TABLE IF EXISTS {NAME} RENAME TO unused", "absent"), DIAGNOSTIC, \
        "An optional rename requires a known explicit schema."
    yield analysis_request(tag, f"ALTER TABLE {NAME} RENAME TO unused", "absent"), SEMANTIC, "does not exist"
    yield analysis_request(tag, f"ALTER TABLE {NAME} RENAME TO unused", wrong_kind=True), DIAGNOSTIC, \
        "ALTER TABLE RENAME requires a persistent base table."
    yield analysis_request(tag, f"ALTER TABLE {NAME} RENAME COLUMN absent TO unused"), SEMANTIC, "does not exist"
    yield analysis_request(tag, f"ALTER TABLE {NAME} RENAME COLUMN id TO label"), SEMANTIC, "already exists"
    yield analysis_request(tag, f"ALTER TABLE {NAME} RENAME CONSTRAINT old_name TO new_name"), DIAGNOSTIC, \
        "The checked rename needs a table or column operation."


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        role = ("table", "column")[index % 2]
        column = ("id", "label")[rng.randrange(2)]
        fresh = ("ir_rn_move", "ir_rn_X\"quoted", "ir_rn_å")[rng.randrange(3)]
        item = change(tag, role, rng.choice(("absent", "plain", "inherited")), rng.choice((SCHEMA, DECOY)),
                      bool(rng.randrange(2)), occupied(role, column) if rng.randrange(3) == 0 else fresh,
                      bool(rng.randrange(2)), column)
        yield item


def requests(_, tag, spec):
    for role, axes in AXES.items():
        for index, dimensions in enumerate(product(*(spec["finite"][role][axis] for axis in axes))):
            item = finite(tag, role, *dimensions)
            yield f"finite/{role}/{index}", item, item, None
    authored = list(analyzed(tag))
    require(len(authored) == len(spec["analysis"]), "postgres-rename-analysis-count")
    for index, (sql, item) in enumerate(authored):
        yield f"analyze/{index}", analysis_request(tag, sql, item["fixture"]), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    malformed = list(negatives(tag))
    require(len(malformed) == len(spec["negative"]), "postgres-rename-negative-count")
    for index, (item, exception, message) in enumerate(malformed):
        yield f"negative/{index}", item, None, {"exception": exception, "message": message}


def qualified(schema, name):
    return f"{schema}.{name}"


def bag(rows):
    return sorted(rows, key=json.dumps)


def relation(schema, name, fields, rows, own, kind="r"):
    return {"identity": qualified(schema, name), "kind": kind, "fields": fields, "rows": bag(rows),
            "own": None if own is None else bag(own)}


def fixture_state(fixture):
    catalog = {}
    for schema, occupied_value in ((SCHEMA, 91), (DECOY, 92)):
        catalog[qualified(schema, OTHER)] = relation(schema, OTHER, [[1, "value", *TYPES["integer"]]],
                                                     [[occupied_value]], [[occupied_value]])
    for schema, own in ((SCHEMA, MAIN_ROWS), (DECOY, [[33, "other"]])):
        if schema == SCHEMA and fixture == "absent":
            continue
        inherited = schema == SCHEMA and fixture == "inherited"
        rows = [*own, CHILD_ROWS[0][:2]] if inherited else own
        catalog[qualified(schema, NAME)] = relation(schema, NAME, FIELDS, rows, own)
        catalog[qualified(schema, VIEW)] = relation(schema, VIEW, FIELDS, rows, None, "v")
        if inherited:
            catalog[qualified(schema, CHILD)] = relation(schema, CHILD, [*FIELDS, [3, "note", *TYPES["text"]]], CHILD_ROWS, CHILD_ROWS)
    return catalog


def rename_field(relation, old, destination):
    return {**relation, "fields": [[ordinal, destination if field == old else field, kind, oid]
                                    for ordinal, field, kind, oid in relation["fields"]]}


def prediction(item):
    action = item["rename"]
    original = fixture_state(item["fixture"])
    catalog = dict(original)
    effect = ["executed"]
    identity = qualified(*action["target"])
    schema = action["target"][0]
    destination = action["destination"]
    child = qualified(schema, CHILD)
    if identity not in catalog:
        if not action["ifExists"]:
            effect = ["db-error", "42P01"]
    elif action["role"] == "table":
        new_identity = qualified(schema, destination)
        if new_identity in catalog:
            effect = ["db-error", "42P07"]
        else:
            catalog[new_identity] = catalog.pop(identity)
    elif action["only"] and child in catalog:
        effect = ["db-error", "42P16"]
    elif destination in ("id", "label"):
        effect = ["db-error", "42701"]
    else:
        for name in (identity, child):
            if name in catalog:
                catalog[name] = rename_field(catalog[name], action["column"], destination)
    initial = {"catalog": original, "marker": [0]}
    return {"status": "table-rename-effect", "objectScope": [SCHEMA, SCHEMA, ["pg_catalog", SCHEMA]],
            "original": initial, "before": {"catalog": original, "marker": [0, 7]}, "effect": effect,
            "after": {"catalog": catalog, "marker": [0, 7]}, "active": True, "restored": initial}


def normalize(state):
    return {**state, "catalog": {name: {**entry, "rows": bag(entry["rows"]),
                                       "own": None if entry["own"] is None else bag(entry["own"])}
                                 for name, entry in state["catalog"].items()}}


def observation(raw):
    if raw["status"] != "table-rename-effect":
        return raw
    return {**raw, **{phase: normalize(raw[phase]) for phase in ("original", "before", "after", "restored")}}


def check_value(item, response):
    require(response["status"] == "constructed" and response["profile"] == item["release"] + "/utf8/standard-conforming-strings/native",
            "postgres-rename-positive-rejected", response)
    same(item["rename"], response["rename"])


def observer_request(item, sql):
    return {"sql": sql, "fixture": item["fixture"]}


def prepare(_, db):
    return {"initial": db.environment["initial"]}


def check_preparation(_, environment, preparation):
    same(environment["initial"], preparation["initial"])
    same({"catalog": fixture_state("inherited"), "marker": [0]}, normalize(environment["initial"]))


def check_observation(item, actual, sql):
    wanted = prediction(item)
    witness = {"sql": sql, "expected": wanted, "observed": actual}
    require(actual["status"] == "table-rename-effect", "postgres-rename-observer-rejected", witness)
    try:
        same(wanted, actual)
    except Failure as error:
        raise Failure(error.signal, witness) from error


def sample_forms(forms, reply, _, item):
    action = reply["rename"]
    role = action["role"]
    dimensions = {"role": role, "schema": action["target"][0], "fixture": item["fixture"], "optional": action["ifExists"],
                  "collision": action["destination"] == occupied(role, action.get("column"))}
    if role == "column":
        dimensions.update(only=action["only"], column=action["column"])
    for dimension, choice in dimensions.items():
        forms[f"{dimension}/{choice}"] += 1


def required_forms(*_):
    return {*(f"role/{role}" for role in AXES), *(f"schema/{schema}" for schema in [SCHEMA, DECOY]),
            *(f"fixture/{kind}" for kind in ["absent", "plain", "inherited"]),
            *(f"{dimension}/{value}" for dimension in ["optional", "collision", "only"] for value in [False, True]),
            "column/id", "column/label"}


def controls():
    item = change("pg-17.2", "column", "inherited", only=True)
    check_observation(item, prediction(item), "correct ONLY")
    wrong = prediction(change("pg-17.2", "column", "inherited"))
    try:
        check_observation(item, wrong, "descendants changed despite ONLY")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"erased-descendant-policy": {"input": item, **error.detail}}
    raise Failure("postgres-rename-comparator-missed-descendants")


def boundary(item, response):
    if rejects(response, item["boundary"]):
        return
    require(response["status"] == "constructed", "postgres-rename-mutant-response", response)
    raise Failure("postgres-rename-invalid-published", {"sql": response["sql"], "observed": response, "expected": item["boundary"]})


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
    raise Failure("postgres-rename-missed-fault", rule.name)


def faults(_, tag):
    root = "sql-semantics-postgres/src/Ir/Schema/Alter"
    table, column = f"{root}/RenameTable.php", f"{root}/RenameColumn.php"
    invalid, exception, message = list(negatives(tag))[0]
    operand = "$this->column->quoted() . ' TO ' . $this->destination->quoted()"
    reversed_operand = "$this->destination->quoted() . ' TO ' . $this->column->quoted()"
    return [
        RenderFault("rename-redirect-to-decoy", table, "$this->target->quoted()", "'\"ir_rn_decoy\".\"ir_rn_target\"'",
                    change(tag, "table"), "meaning-mismatch"),
        RenderFault("rename-erase-optional", table, "($this->ifExists ? 'IF EXISTS ' : '')", "''",
                    change(tag, "table", "absent", if_exists=True), "meaning-mismatch"),
        RenderFault("rename-erase-only", column, "($this->only ? 'ONLY ' : '')", "''",
                    change(tag, "column", "inherited", only=True), "meaning-mismatch"),
        RenderFault("rename-swap-column-operands", column, operand, reversed_operand,
                    change(tag, "column"), "meaning-mismatch"),
        RenderFault("rename-change-destination", table, "$this->destination->quoted()", "'\"ir_rn_replaced\"'",
                    change(tag, "table"), "meaning-mismatch"),
        RenderFault("rename-admit-same-table-name", table, "$target->table->value !== $destination->value,", "true,",
                    {"boundary": {"exception": exception, "message": message}}, "postgres-rename-invalid-published", invalid),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
