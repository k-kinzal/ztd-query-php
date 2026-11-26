"""Observe native MySQL definition copy through two independent connections."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import main, probe
from run_server_mysql_session_create_table import DIAGNOSTIC, TEMP, snapshot, specifications as profiles, table
from server_delete_cases import literal
from sqlite_domain import RenderFault

LABEL = "mysql-create-like"
OBSERVER = "mysql_create_like_observer.php"
CONTRACT_NAME = "mysql-create-like-v1.json"
CONTRACT = "0211e157e525a76943102e25042da8a860714b6c6e75c59fb0b529aab9439070"
OBSERVER_QUERIES = "49f174ba0179afd08a0c93ace3f250abda1398cbba584de9f753cddb33aa6b13"
COMPLETE_SEEDS = True
SOURCE, DESTINATION = "ir_slike_original", "ir_slike_copy"
SCHEMAS = {"primary": "ir_fixture", "decoy": "ir_slike_decoy"}
AXES = ("sourceSchema", "destinationSchema", "sourceTopology", "destinationKind", "destinationTopology", "ifNotExists")
SEMANTIC = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\SemanticException"
DISTINCT = "CREATE TABLE LIKE requires distinct source and destination names."
TABLE_KIND = "A resolved table identity requires a persistent or temporary table."
BINDING = "mysql-create-like-wrong-binding"
INVALID = "mysql-create-like-invalid-published"


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-create-like-contract-mismatch")
    queries = TOOL / "mysql-create-like-observer-queries-v1.json"
    require(digest(queries) == OBSERVER_QUERIES, "mysql-create-like-observer-queries-contract-mismatch")
    specification = json.loads(path.read_text())
    record = json.loads(queries.read_text())
    observed = specification["exploration"]
    require(record["semanticContract"] == CONTRACT_NAME and record["acceptance"]["finite"] == specification["finite"]["identities"]
            and record["acceptance"]["timeoutSecondsPerRelease"] == observed["timeoutSecondsPerRelease"]
            and record["acceptance"]["seedBudget"] == observed["casesPerSeed"] * len(observed["seeds"])
            and record["acceptance"]["exclusions"] == observed["allowedExclusions"], "mysql-create-like-observer-schedule-mismatch")
    return specification, profiles()[1]


def case(tag, source, destination, sources, op, targets, optional, variant=0):
    return {
        "dialect": "mysql", "release": tag, "variant": variant,
        "sourceTopology": sources, "destinationTopology": targets,
        "createLike": {
            "source": [SCHEMAS[source], SOURCE, "table" if sources == "permanent" else TEMP],
            "destination": [SCHEMAS[destination], DESTINATION, op],
            "ifNotExists": optional,
        },
    }


def finite_ids(_, specification, _tag):
    bound = specification["finite"]
    ids = {(origin, target, sources, op, targets, flag) for origin in SCHEMAS for target in SCHEMAS
           for sources in ("permanent", "temporary-only", "shadow") for op in ("table", TEMP)
           for targets in ("absent", "persistent", "temporary", "shadow") for flag in (False, True)}
    require(len(ids) == bound["identities"], "mysql-create-like-wrong-grid-size")
    return ids


def finite_id(_, response, item):
    model = response["createLike"]
    scopes = {schema: label for label, schema in SCHEMAS.items()}
    source, target = model["source"], model["destination"]
    require(source[1] == SOURCE and target[1] == DESTINATION, "mysql-create-like-wrong-coordinates", model)
    return scopes[source[0]], scopes[target[0]], item["sourceTopology"], target[2], item["destinationTopology"], model["ifNotExists"]


def qualification(identity):
    return ".".join(identity[:2])


def declarations(item):
    definition = item["createLike"]
    schema, source, _ = definition["source"]
    declarations = []
    for name in SCHEMAS.values():
        if name != schema or item["sourceTopology"] != "temporary-only":
            declarations.append(f"CREATE TABLE {name}.{source} (id INT, fid INT, payload BIGINT)")
        if name == schema and item["sourceTopology"] != "permanent":
            declarations.append(f"CREATE TEMPORARY TABLE {name}.{source} (id INT, fid SMALLINT, payload BIGINT)")
    return declarations


def analyze_request(item, sql, current=True, catalog=None, complete=True):
    result = {"dialect": "mysql", "release": item["release"], "sql": sql, "complete": complete,
              "declarations": declarations(item) if catalog is None else catalog}
    if current:
        result["currentDatabase"] = SCHEMAS["primary"]
    return result


def analyzed(tag):
    cases = (
        ("primary", "primary", "permanent", "table", "absent", False, False, False),
        ("primary", "primary", "permanent", "table", "absent", False, False, True),
        ("decoy", "primary", "permanent", "table", "absent", False, True, False),
        ("decoy", "primary", "shadow", TEMP, "absent", False, True, False),
        ("primary", "decoy", "temporary-only", "table", "absent", False, True, False),
        ("primary", "decoy", "permanent", TEMP, "absent", False, True, False),
        ("primary", "primary", "permanent", TEMP, "temporary", True, False, False),
    )
    for index, (*fields, qualified, parentheses) in enumerate(cases):
        item = case(tag, *fields, variant=index)
        definition = item["createLike"]
        source, target = definition["source"], definition["destination"]
        prefix = "CREATE TEMPORARY TABLE " if target[2] == TEMP else "CREATE TABLE "
        guard = "IF NOT EXISTS " if definition["ifNotExists"] else ""
        operand = "LIKE " + (qualification(source) if qualified else SOURCE)
        sql = prefix + guard + (qualification(target) if qualified else DESTINATION) + " "
        sql += "(" + operand + ")" if parentheses else operand
        yield analyze_request(item, sql), item


def negatives(tag):
    item = case(tag, "primary", "primary", "permanent", TEMP, "persistent", True)
    definition = item["createLike"]
    yield {**item, "createLike": {**definition, "source": [SCHEMAS["primary"], DESTINATION, "table"]}}, DIAGNOSTIC, DISTINCT
    for role in ("source", "destination"):
        yield {**item, "createLike": {**definition, role: [*definition[role][:2], "view"]}}, DIAGNOSTIC, TABLE_KIND
    sql = f"CREATE TABLE IF NOT EXISTS {DESTINATION} LIKE {SOURCE}"
    view = f"CREATE VIEW {SCHEMAS['primary']}.{SOURCE} AS SELECT 1 AS id"
    yield analyze_request(item, sql, catalog=[view]), SEMANTIC, "is not BASE TABLE"
    yield analyze_request(item, sql, catalog=["CREATE TABLE ir_fixture.other (id INT)"]), SEMANTIC, "does not exist"
    yield analyze_request(item, sql, catalog=["CREATE TABLE ir_fixture.other (id INT)"], complete=False), DIAGNOSTIC, "requires a resolved source table"
    yield analyze_request(item, f"CREATE TABLE {DESTINATION} LIKE ir_fixture.{SOURCE}", current=False), DIAGNOSTIC, "requires absolute source and destination names"
    yield analyze_request(item, f"CREATE TEMPORARY TABLE ir_fixture.{SOURCE} LIKE {SOURCE}"), DIAGNOSTIC, DISTINCT


def requests(_, tag, specification):
    axes = specification["finite"]
    for index, identity in enumerate(product(*(axes[axis] for axis in AXES))):
        item = case(tag, *identity)
        yield f"finite/{index}", item, item, None
    authored = list(analyzed(tag))
    require(len(authored) == len(specification["analysis"]), "mysql-create-like-authored-size")
    for index, (request, item) in enumerate(authored):
        yield f"analyze/{index}", request, item, None
    exploration = specification["exploration"]
    for seed in exploration["seeds"]:
        rng = Random(seed)
        for index in range(exploration["casesPerSeed"]):
            item = case(tag, *(rng.choice(axes[axis]) for axis in AXES), variant=rng.randrange(16))
            yield f"seed/{seed}/{index}", item, item, None
    invalid = list(negatives(tag))
    require(len(invalid) == len(specification["negative"]), "mysql-create-like-negative-size")
    for index, (request, exception, message) in enumerate(invalid):
        yield f"negative/{index}", request, None, {"exception": exception, "message": message}


def source_fields(ordinal, temporary, variant):
    base = 100 + 100 * ordinal + (300 if temporary else 0)
    return [
        ["id", "INTEGER", False, None],
        ["fid", "SMALLINT" if temporary else "INTEGER", False, literal(base + variant)],
        ["payload", "SMALLINT" if variant % 2 else "BIGINT", True, literal(-base + variant)],
    ], base


def source_table(tag, ordinal, temporary, variant):
    fields, base = source_fields(ordinal, temporary, variant)
    rows = [[base + index + 1, base + 20 + index, None if index == 0 else base + index]
            for index in range(2 + variant // 8)]
    result = table(tag, TEMP if temporary else "table", fields, [[0, False]], rows)
    parts = ("fid",) if variant % 2 else ("fid", "payload")
    result["key"].extend(["copy_key", int(temporary), index, column, "A", "BTREE"] for index, column in enumerate(parts, 1))
    return {**result, "allocatedColumns": ["id"], "foreignKeys": int(not temporary)}


def prior_target(tag, temporary, ordinal):
    fields = [["local_value", "BIGINT", True, None]] if temporary else [["existing", "INTEGER", True, None]]
    rows = [[None], [100 + 100 * ordinal + (79 if temporary else 61)]]
    return {**table(tag, TEMP if temporary else "table", fields, [], rows), "allocatedColumns": [], "foreignKeys": 0}


def initial_state(item):
    definition = item["createLike"]
    source, target = definition["source"][0], definition["destination"][0]
    sources, targets = item["sourceTopology"], item["destinationTopology"]
    tag, variant = item["release"], item["variant"]
    permanent, session = {}, {}
    for ordinal, schema in enumerate(SCHEMAS.values()):
        original, destination = f"{schema}.{SOURCE}", f"{schema}.{DESTINATION}"
        permanent[original] = None if schema == source and sources == "temporary-only" else source_table(tag, ordinal, False, variant)
        if schema == source and sources != "permanent":
            session[original] = source_table(tag, ordinal, True, variant)
        permanent[destination] = prior_target(tag, False, ordinal) if schema != target or targets in ("persistent", "shadow") else None
        if schema == target and targets in ("temporary", "shadow"):
            session[destination] = prior_target(tag, True, ordinal)
    return permanent, session


def prediction(item):
    define = item["createLike"]
    source, destination = qualification(define["source"]), qualification(define["destination"])
    permanent, session = initial_state(item)
    initial = snapshot(permanent, session, [0], [0])
    temporary = define["destination"][2] == TEMP
    target = session if temporary else permanent
    collision = target.get(destination) is not None
    optional = define["ifNotExists"]
    effect = ["db-error", "42S01", 1050] if collision and not optional else ["executed", 0]
    diagnostics = [] if not collision else [["Note" if optional else "Error", 1050, f"Table '{DESTINATION}' already exists"]]
    marker, durable = [0, 7], [0] if temporary else [0, 7]
    default, without_parent = None, None
    if not collision:
        original = session.get(source, permanent[source])
        target[destination] = {**original, "kind": define["destination"][2], "rows": [], "foreignKeys": 0}
        columns = original["columns"]
        default, without_parent = [int(columns[1][3]), int(columns[2][3])], [999, int(columns[2][3])]
    after_ddl = snapshot(permanent, session, marker, durable)
    provisional, before_rollback = None, after_ddl
    if not collision and temporary:
        provisional = ["executed", 1, "1"]
        target[destination] = {**target[destination], "rows": [[1, *default]]}
        before_rollback = snapshot(permanent, session, marker, durable)
        target[destination] = {**target[destination], "rows": []}
    restored = snapshot(permanent, session, durable, durable)
    writes = None
    if not collision:
        start = 2 if temporary else 1
        target[destination] = {**target[destination], "rows": [[start, *default], [start + 1, *without_parent]]}
        writes = {"defaults": ["executed", 1, str(start)], "unreferenced": ["executed", 1, str(start + 1)],
                  "state": snapshot(permanent, session, durable, durable)}
    foreign_key_probe = ["db-error", "23000", 1452] if item["sourceTopology"] == "permanent" else None
    return {"status": "mysql-create-like", "initial": initial, "effect": effect, "diagnostics": diagnostics,
            "warningCount": len(diagnostics), "active": temporary, "afterDdl": after_ddl,
            "provisionalWrite": provisional, "beforeRollback": before_rollback, "restored": restored,
            "writes": writes, "originalForeignKeyProbe": foreign_key_probe,
            "afterDisconnect": snapshot(permanent, {}, durable, durable)}


def observer_request(item, sql):
    model = item["createLike"]
    return {"sql": sql, "sourceSchema": model["source"][0], "destinationSchema": model["destination"][0],
            "destinationKind": model["destination"][2], "sourceTopology": item["sourceTopology"],
            "destinationTopology": item["destinationTopology"], "variant": item["variant"]}


def observation(raw):
    return raw


def check_value(item, response):
    require(response["status"] == "constructed"
            and response["profile"] == item["release"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native",
            "mysql-create-like-construction", response)
    require(response["createLike"] == item["createLike"], BINDING,
            {"sql": response["sql"], "expected": item["createLike"], "observed": response["createLike"]})


def check_observation(item, actual, sql):
    expected = prediction(item)
    detail = {"sql": sql, "expected": expected, "observed": actual}
    require(actual["status"] == "mysql-create-like", "mysql-create-like-outside-observation", detail)
    try:
        same(expected, actual)
    except Failure as error:
        raise Failure(error.signal, detail) from error


def sample_forms(forms, response, engine, item):
    for axis, value in zip(AXES, finite_id(engine, response, item)):
        forms[f"{axis}/{value}"] += 1
    forms[f"secondaryColumns/{2 - item['variant'] % 2}"] += 1
    forms[f"sourceRows/{2 + item['variant'] // 8}"] += 1


def required_forms(_, _tag):
    axes = specifications()[0]["finite"]
    return {f"{axis}/{value}" for axis in AXES for value in axes[axis]} | {
        "secondaryColumns/1", "secondaryColumns/2", "sourceRows/2", "sourceRows/3",
    }


def outsiders():
    return (f"DROP TABLE {DESTINATION}", f"CREATE TABLE irrelevant LIKE {SOURCE}",
            f"CREATE TABLE {DESTINATION} LIKE {SOURCE}; SELECT 1")


def prepare(_, db):
    release = db.environment["database"][0].split("-", 1)[0]
    item = case("mysql-" + release, "decoy", "primary", "shadow", TEMP, "absent", False)
    sql = f"CREATE TEMPORARY TABLE {DESTINATION} LIKE {SCHEMAS['decoy']}.{SOURCE}"
    native = db.request(observer_request(item, sql))
    check_observation(item, native, sql)
    return {"baseline": native, "outsiders": {invalid: db.request(observer_request(item, invalid)) for invalid in outsiders()}}


def check_preparation(_, environment, preparation):
    release = environment["database"][0].split("-", 1)[0]
    sample = case("mysql-" + release, "decoy", "primary", "shadow", TEMP, "absent", False)
    same(prediction(sample), preparation["baseline"])
    same({sql: {"status": "outside-observer-scope"} for sql in outsiders()}, preparation["outsiders"])


def controls():
    item = case("mysql-8.4.7", "primary", "decoy", "permanent", TEMP, "absent", False)
    expected = prediction(item)
    check_observation(item, expected, "known definition copy")
    mismatches = {"foreign-key": {**expected, "originalForeignKeyProbe": ["executed", 1, "999"]},
                  "allocation": {**expected, "provisionalWrite": ["executed", 1, "107"]},
                  "transaction": {**expected, "active": False}}
    detections = {}
    for name, output in mismatches.items():
        try:
            check_observation(item, output, name)
        except Failure as error:
            same("meaning-mismatch", error.signal)
            detections[name] = error.signal
        else:
            raise Failure("mysql-create-like-comparator-accepted", name)
    return {"input": item, "observed": expected, "mismatches": mismatches, "detections": detections}


def check_boundary(item, response):
    if rejects(response, item["boundary"]):
        return
    require(response["status"] == "constructed", "mysql-create-like-unexpected-fault-response", response)
    raise Failure(INVALID, {"sql": response["sql"], "expected": item["boundary"], "observed": response})


def fault_probe(item, response, db):
    if "boundary" in item:
        check_boundary(item, response)
    else:
        probe(item, response, db, sys.modules[__name__])


def replay_fault(rule, record):
    witness = record["detail"]
    try:
        if "boundary" in rule.item:
            check_boundary(rule.item, record["healthyResponse"])
            check_boundary(rule.item, record["mutantResponse"])
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            same(prediction(rule.item), witness["expected"])
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("mysql-create-like-replay-accepted-fault", rule.name)


def faults(_, tag):
    root = "sql-semantics-mysql/src/Ir/Schema/Like/"
    value = root + "CreateTableLike.php"
    fresh = case(tag, "decoy", "decoy", "permanent", TEMP, "absent", False)
    occupied = case(tag, "primary", "primary", "permanent", TEMP, "temporary", True)
    bad, exception, message = next(negatives(tag))
    authored, shadow = list(analyzed(tag))[3]
    return [
        RenderFault("erase-temporary-copy-keyword", value,
                    "$this->destination->kind === ExternalRelationKind::TemporaryTable ? 'TEMPORARY ' : ''",
                    "''", fresh, "meaning-mismatch"),
        RenderFault("redirect-source-to-default-schema", value, "$this->source->quoted()",
                    "$this->source->name->table->quoted()", fresh, "meaning-mismatch"),
        RenderFault("redirect-destination-to-default-schema", value, "$this->destination->quoted()",
                    "$this->destination->name->table->quoted()", fresh, "meaning-mismatch"),
        RenderFault("invert-copy-existence-guard", value, "$this->ifNotExists ? 'IF NOT EXISTS '",
                    "!$this->ifNotExists ? 'IF NOT EXISTS '", occupied, "meaning-mismatch"),
        RenderFault("accept-conflicting-coordinates", value, "$destination->quoted() !== $source->quoted()", "true",
                    {"boundary": {"exception": exception, "message": message}}, INVALID, bad),
        RenderFault("prefer-permanent-copy-source-through-shadow", root + "CreateTableLikeLowering.php",
                    "$resolution->table->temporary ? ExternalRelationKind::TemporaryTable : ExternalRelationKind::BaseTable",
                    "ExternalRelationKind::BaseTable", shadow, BINDING, authored),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
