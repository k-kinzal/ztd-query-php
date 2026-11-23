"""Compare MySQL table declarations in persistent and session-local namespaces."""

from copy import deepcopy
from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import main, probe
from run_server_mysql_create_primary import OLD
from run_server_mysql_create_varchar import COLLATION, DIAGNOSTIC, specifications as profiles, text_column
from server_delete_cases import literal
from sqlite_domain import RenderFault

LABEL = "mysql-session-create-table"
OBSERVER = "mysql_session_create_observer.php"
CONTRACT_NAME = "mysql-session-create-table-v1.json"
CONTRACT = "d128967df8d5696561122f4064c976acc904293f4a301f5b8b432f3d4cdce79a"
METADATA_NAME = "mysql-session-create-default-metadata-v1.json"
METADATA = "65115e93163daa4b82ad6da66983e499b4a9a3d8a75de6063cd0aa3da8de906f"
RESET_NAME = "mysql-session-create-fixture-reset-v1.json"
RESET = "aea2a0bf89099ac8ee449ac0d2c80bf5948e934995bc1e3d510346d6ea68ed4f"
COMPLETE_SEEDS = True
SCHEMAS = {"primary": "ir_fixture", "decoy": "ir_screate_decoy"}
NAME, TEMP = "ir_screate_data", "temporary-table"
STORAGE = " ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin"
AXES = ("schema", "fixture", "kind", "ifNotExists", "layout")
SIGNED = {"TINYINT": ("tinyint", 4), "SMALLINT": ("smallint", 6), "MEDIUMINT": ("mediumint", 9),
          "INTEGER": ("int", 11), "BIGINT": ("bigint", 20)}
INVALID = "mysql-session-create-invalid-published"
OWNER = "A primary key must reference an existing required signed column."


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-session-create-contract-mismatch")
    require(digest(TOOL / METADATA_NAME) == METADATA, "mysql-session-create-metadata-contract-mismatch")
    require(digest(TOOL / RESET_NAME) == RESET, "mysql-session-create-fixture-reset-contract-mismatch")
    return json.loads(path.read_text()), profiles()[1]


def columns(tag, layout):
    if layout == "signed":
        return [["n0", "SMALLINT", False, literal(-7)], ["n1", "BIGINT", True, ["null"]]], None
    return [text_column("n2", 4, True, ["text", "é "]), ["n0", "INTEGER", False, literal(31)],
            ["n1", "SMALLINT", False, literal(-21)]], [[2, tag not in OLD], [1, False]]


def case(tag, schema, fixture, kind, optional, layout, variant=0):
    fields, key = columns(tag, layout)
    create = {"target": [SCHEMAS[schema], NAME], "columns": fields, "ifNotExists": optional, "tableCollation": COLLATION}
    if key is not None:
        create["primaryKey"] = key
    return {"dialect": "mysql", "release": tag, "create": create, "createKind": kind, "fixture": fixture, "variant": variant}


def finite_ids(_, specification, _tag):
    b = specification["finite"]
    ids = {(schema, fixture, kind, flag, layout) for schema in SCHEMAS
           for fixture in b["fixture"] for kind in ("table", TEMP) for flag in (False, True)
           for layout in ("signed", "varchar-key")}
    require(len(ids) == b["identities"], "mysql-session-create-grid-size")
    return ids


def finite_id(_, response, item):
    definition = response["create"]
    schema, name = definition["target"]
    matches = [label for label, literal_name in SCHEMAS.items() if schema == literal_name]
    require(len(matches) == 1 and name == NAME, "mysql-session-create-unregistered-coordinate", definition)
    layout = "varchar-key" if "primaryKey" in definition else "signed"
    expected_columns, expected_key = columns(item["release"], layout)
    same(expected_columns, definition["columns"])
    same(expected_key, definition.get("primaryKey"))
    return matches[0], item["fixture"], response["createKind"], definition["ifNotExists"], layout


def direct_exploration(tag, specification, rng, index):
    boundary = specification["finite"]
    schema, fixture, kind, optional = (rng.choice(boundary[axis]) for axis in AXES[:-1])
    item = case(tag, schema, fixture, kind, optional, "signed", rng.randint(0, 40))
    child_count = 1 + index % 8
    names = rng.sample(range(8), child_count)
    fields, key_candidates = [], []
    for slot, identifier in enumerate(names):
        name = f"n{identifier}"
        nullable = rng.choice((False, True))
        if index % 2 == 0 and slot % 2 == 1:
            text = rng.choice(("", "a", "é ", "x'y", "𐐷"))
            length = rng.randint(max(1, len(text)), 8)
            field = text_column(name, length, nullable, ["text", text])
        else:
            kind = rng.choice(tuple(SIGNED))
            default = ["null"] if nullable and index % 3 == 0 else literal(rng.randint(-128, 127))
            field = [name, kind, nullable, default]
            if not nullable:
                key_candidates.append(slot)
        fields.append(field)
    layout = item["create"]
    layout["columns"] = fields
    if key_candidates and index % 3 != 0:
        key = rng.sample(key_candidates, rng.randint(1, len(key_candidates)))
        layout["primaryKey"] = [[slot, tag not in OLD and (index + position) % 2 == 0] for position, slot in enumerate(key)]
    return item


def location(schema):
    return f"{schema}.{NAME}"


def analyze_request(item, sql, named=True):
    request = {"dialect": "mysql", "release": item["release"], "sql": sql, "declarations": []}
    if named:
        request["currentDatabase"] = SCHEMAS["primary"]
    return request


def analyzed(tag):
    signed = "(n0 SMALLINT NOT NULL DEFAULT -7, n1 BIGINT NULL DEFAULT NULL)"
    keyed = "(n2 VARCHAR(4) COLLATE utf8mb4_bin NULL DEFAULT 'é ', n0 INT NOT NULL DEFAULT 31, n1 SMALLINT NOT NULL DEFAULT -21, PRIMARY KEY (n1 DESC, n0 ASC))"
    cases = (
        ("primary", "base", TEMP, False, "signed", NAME, signed),
        ("decoy", "base", TEMP, False, "signed", location(SCHEMAS["decoy"]), signed),
        ("primary", "view", TEMP, False, "signed", location(SCHEMAS["primary"]), signed),
        ("decoy", "shadow-view", TEMP, True, "varchar-key", location(SCHEMAS["decoy"]), keyed),
        ("primary", "temporary", "table", False, "signed", NAME, signed),
        ("primary", "absent", "table", False, "varchar-key", NAME, keyed),
        ("decoy", "absent", TEMP, False, "varchar-key", location(SCHEMAS["decoy"]), keyed),
    )
    for schema, fixture, kind, optional, layout, name, body in cases:
        prefix = "CREATE TEMPORARY TABLE " if kind == TEMP else "CREATE TABLE "
        guard = "IF NOT EXISTS " if optional else ""
        item = case(tag, schema, fixture, kind, optional, layout)
        yield analyze_request(item, prefix + guard + name + " " + body + STORAGE), item


def negatives(tag):
    item = case(tag, "primary", "absent", TEMP, False, "signed")
    definition = item["create"]
    yield {**item, "createKind": "view"}, "A resolved table identity requires a persistent or temporary table."
    yield {**item, "create": {**definition, "target": [SCHEMAS["primary"], "wrong/name"]}}, "without path separators"
    yield {**item, "create": {**definition, "columns": []}}, "CREATE TABLE needs a nonempty ordered column list."
    yield analyze_request(item, f"CREATE TEMPORARY TABLE {NAME} (n0 INT)", named=False), "requires a named declaration schema"
    yield analyze_request(item, f"CREATE TEMPORARY TABLE {NAME} (n0 INT) ENGINE=MyISAM"), "requires the native InnoDB storage role"
    yield {**item, "create": {**definition, "primaryKey": [[2, False]]}}, OWNER
    yield {**item, "create": {**definition, "primaryKey": [[1, False]]}}, OWNER


def requests(_, tag, specification):
    axes = specification["finite"]
    for at, coordinate in enumerate(product(*(axes[name] for name in AXES))):
        item = case(tag, *coordinate)
        yield f"finite/{at}", item, item, None
    authored = list(analyzed(tag))
    require(len(authored) == len(specification["analysis"]), "mysql-session-create-authored-size")
    for at, (request, item) in enumerate(authored):
        yield f"analyze/{at}", request, item, None
    exploration = specification["exploration"]
    for seed in exploration["seeds"]:
        rng = Random(seed)
        for index in range(exploration["casesPerSeed"]):
            item = direct_exploration(tag, specification, rng, index)
            yield f"seed/{seed}/{index}", item, item, None
    negative = list(negatives(tag))
    require(len(negative) == len(specification["negative"]), "mysql-session-create-negative-size")
    for at, (request, message) in enumerate(negative):
        yield f"negative/{at}", request, None, {"exception": DIAGNOSTIC, "message": message}


def native_type(tag, kind):
    if isinstance(kind, list):
        return f"varchar({kind[1]})", kind[2]
    name, width = SIGNED[kind]
    return (f"{name}({width})" if tag in OLD else name), None


def value(default):
    if default is None or default[0] == "null":
        return None
    return int(default[1]) if default[0] == "integer" else default[1]


def table(tag, kind, fields, terms, rows):
    heading = []
    for name, storage, nullable, default in fields:
        physical, collation = native_type(tag, storage)
        default = value(default)
        metadata = str(default) if type(default) is int else default
        if default == "𐐷":
            metadata = "0x" + default.encode().hex().upper() if kind == TEMP and tag not in OLD else "?"
        heading.append([name, physical, nullable, metadata, collation])
    key = [["PRIMARY", 0, position, fields[slot][0], "D" if descending else "A", "BTREE"]
           for position, (slot, descending) in enumerate(terms, 1)]
    return {"kind": kind, "engine": "InnoDB", "collation": COLLATION, "columns": heading, "key": key, "rows": rows}


def initial_state(tag, target, fixture, variant):
    permanent = {}
    for schema, current in SCHEMAS.items():
        name = location(current)
        number = (11 if schema == "primary" else 41) + variant
        if name != target or fixture in ("base", "shadow-base"):
            permanent[name] = table(tag, "table", [["existing", "INTEGER", True, None]], [], [[None], [number]])
        elif fixture in ("view", "shadow-view"):
            permanent[name] = {"kind": "view", "columns": ["marker"], "rows": [[number + 60]]}
        else:
            permanent[name] = None
    session = {}
    if fixture in ("temporary", "shadow-base", "shadow-view"):
        session[target] = table(tag, TEMP, [["local_value", "SMALLINT", True, literal(9)]], [], [[None], [120 + variant]])
    return permanent, session


def snapshot(permanent, session, marker, committed):
    return {"visible": {**permanent, **session}, "permanent": dict(permanent), "marker": marker, "committedMarker": committed}


def prediction(item):
    tag, kind, definition = item["release"], item["createKind"], item["create"]
    target = location(definition["target"][0])
    permanent, session = initial_state(tag, target, item["fixture"], item["variant"])
    before = snapshot(permanent, session, [0], [0])
    temporary = kind == TEMP
    affected = session if temporary else permanent
    collision = affected.get(target) is not None
    optional = definition["ifNotExists"]
    effect = ["db-error", "42S01", 1050] if collision and not optional else ["executed", 0]
    diagnostics = [] if not collision else [["Note" if optional else "Error", 1050, f"Table '{NAME}' already exists"]]
    fields, key = definition["columns"], definition.get("primaryKey", [])
    row = [value(field[3]) for field in fields]
    if not collision:
        affected[target] = table(tag, kind, fields, key, [row] if temporary else [])
    pending = [0, 7]
    committed = [0] if temporary else pending
    after = snapshot(permanent, session, pending, committed)
    if not collision and temporary:
        affected[target] = {**affected[target], "rows": []}
    restored = snapshot(permanent, session, committed, committed)
    defaults = None
    if not collision:
        affected[target] = {**affected[target], "rows": [row] if key else [row, row]}
        defaults = {"writes": [["executed", 1], ["db-error", "23000", 1062] if key else ["executed", 1]],
                    "state": snapshot(permanent, session, committed, committed)}
    return {"status": "mysql-session-create", "initial": before, "effect": effect,
            "diagnostics": diagnostics, "warningCount": len(diagnostics), "active": temporary,
            "provisionalWrite": ["executed", 1] if temporary and not collision else None,
            "after": after, "restored": restored, "defaults": defaults,
            "afterDisconnect": snapshot(permanent, {}, committed, committed)}


def observer_request(item, sql):
    return {"sql": sql, "target": location(item["create"]["target"][0]), "kind": item["createKind"],
            "fixture": item["fixture"], "variant": item["variant"]}


def observation(raw):
    return raw


def check_value(item, response):
    require(response["status"] == "constructed"
            and response["profile"] == item["release"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb",
            "mysql-session-create-construction", response)
    same(item["createKind"], response["createKind"])
    same(item["create"], response["create"])


def check_observation(item, actual, sql):
    expected = prediction(item)
    detail = {"sql": sql, "expected": expected, "observed": actual}
    require(actual["status"] == "mysql-session-create", "mysql-session-create-observer-rejected", detail)
    try:
        same(expected, actual)
    except Failure as error:
        raise Failure(error.signal, detail) from error


def sample_forms(forms, response, _, item):
    result = response["create"]
    shape = result["columns"]
    schema = next(name for name, database in SCHEMAS.items() if database == result["target"][0])
    for form in (f"schema/{schema}", f"kind/{response['createKind']}", f"fixture/{item['fixture']}",
                 f"optional/{result['ifNotExists']}", f"width/{len(shape)}", f"key/{'primaryKey' in result}"):
        forms[form] += 1
    for _, storage, nullable, default in shape:
        forms[f"storage/{storage[0] if isinstance(storage, list) else storage}"] += 1
        forms[f"nullable/{nullable}"] += 1
        forms[f"default/{default[0]}"] += 1
    for _, descending in result.get("primaryKey", ()):
        forms[f"descending/{descending}"] += 1


def required_forms(_, tag):
    bound = specifications()[0]["finite"]
    names = {"schema": "schema", "kind": "kind", "fixture": "fixture", "optional": "ifNotExists"}
    base = {f"{family}/{member}" for family, axis in names.items() for member in bound[axis]}
    return base | {*(f"width/{width}" for width in range(1, 9)), *(f"storage/{kind}" for kind in SIGNED),
                   "storage/varchar", "key/True", "key/False", "nullable/True", "nullable/False",
                   "default/null", "default/integer", "default/text", "descending/False",
                   *(() if tag in OLD else ("descending/True",))}


def outsiders():
    return (f"CREATE TEMPORARY TABLE unrelated (n0 INT)", f"CREATE TABLE {NAME} (n0 INT); SELECT 1", f"DROP TABLE {NAME}")


def prepare(_, db):
    item = case("mysql-8.4.7", "primary", "shadow-view", TEMP, False, "signed")
    return {"initial": db.environment["initial"],
            "outsiders": {sql: db.request(observer_request(item, sql)) for sql in outsiders()}}


def check_preparation(_, environment, preparation):
    release = "mysql-" + environment["database"][0].split("-", 1)[0]
    target = location(SCHEMAS["primary"])
    same(snapshot(*initial_state(release, target, "shadow-view", 0), [0], [0]), preparation["initial"])
    same(environment["initial"], preparation["initial"])
    same({sql: {"status": "outside-observer-scope"} for sql in outsiders()}, preparation["outsiders"])


def controls():
    item = case("mysql-8.4.7", "primary", "view", TEMP, False, "varchar-key")
    item["create"]["columns"][0][3] = ["text", "𐐷"]
    expected = prediction(item)
    check_observation(item, expected, "known session declaration")
    changed = deepcopy(expected)
    changed["defaults"]["state"]["visible"][location(SCHEMAS["primary"])]["rows"][0][0] = "?"
    variants = {
        "implicit-commit": {**expected, "active": False},
        "temporary-owner-leak": {**expected, "afterDisconnect": expected["defaults"]["state"]},
        "lost-default-probe": {**expected, "defaults": None},
        "changed-inserted-supplementary": changed,
    }
    detected = {}
    for name, actual in variants.items():
        try:
            check_observation(item, actual, name)
        except Failure as error:
            same("meaning-mismatch", error.signal)
            detected[name] = error.signal
        else:
            raise Failure("mysql-session-create-comparator-accepted", name)
    return {"input": item, "prediction": expected, "mismatches": variants, "detections": detected}


def check_boundary(item, response):
    if rejects(response, item["boundary"]):
        return
    require(response["status"] == "constructed", "mysql-session-create-unexpected-fault-response", response)
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
    raise Failure("mysql-session-create-replay-accepted-fault", rule.name)


def faults(_, tag):
    root = "sql-semantics-mysql/src/Ir/Schema/"
    candidate = case(tag, "primary", "base", TEMP, False, "varchar-key")
    cross = case(tag, "decoy", "absent", TEMP, False, "signed")
    collision = case(tag, "primary", "temporary", TEMP, True, "signed")
    bad, message = next(negatives(tag))
    boundary = {"boundary": {"exception": DIAGNOSTIC, "message": message}}
    return [
        RenderFault("erase-temporary-declaration", root + "CreateTable.php",
                    "$this->target->kind === ExternalRelationKind::TemporaryTable ? 'TEMPORARY ' : ''", "''",
                    candidate, "meaning-mismatch"),
        RenderFault("erase-declaration-database", root + "CreateTable.php", "$this->target->quoted()",
                    "$this->target->name->table->quoted()", cross, "meaning-mismatch"),
        RenderFault("invert-temporary-existence-guard", root + "CreateTable.php",
                    "$this->ifNotExists ? 'IF NOT EXISTS '", "!$this->ifNotExists ? 'IF NOT EXISTS '",
                    collision, "meaning-mismatch"),
        RenderFault("replace-temporary-signed-default", root + "TableColumn.php", "' DEFAULT ' . $this->default->decimal",
                    "' DEFAULT 0'", candidate, "meaning-mismatch"),
        RenderFault("discard-temporary-primary-key", root + "CreateTable.php", "if ($this->primaryKey !== null)",
                    "if (false)", candidate, "meaning-mismatch"),
        RenderFault("admit-view-as-a-table-declaration", root + "Identity/TableIdentity.php", "$kind->isTable()", "true",
                    boundary, INVALID, bad),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
