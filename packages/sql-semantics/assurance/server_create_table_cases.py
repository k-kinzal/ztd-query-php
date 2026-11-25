"""Predict native integer table declarations, default insertion and transaction effects."""

from itertools import product
from random import Random

from server_cases import SCHEMAS
from server_delete_cases import literal

TYPES = ("SMALLINT", "INTEGER", "BIGINT")
FORMS = ((True, None), (True, ["null"]), (True, literal(-3)), (False, literal(9)))


def case(engine, tag, columns, optional=False, occupied=False):
    return {"dialect": engine, "release": tag, "occupied": occupied, "create": {
        "target": [SCHEMAS[engine], "ir_create_target"], "columns": columns, "ifNotExists": optional,
    }}


def finite_cases(engine, tag):
    for storage, (nullable, default), optional, occupied in product(TYPES, FORMS, (False, True), (False, True)):
        yield case(engine, tag, [["c0", storage, nullable, default]], optional, occupied)


def finite_id(_, response, item):
    create = response["create"]
    _, storage, nullable, default = create["columns"][0]
    return storage, FORMS.index((nullable, default)), create["ifNotExists"], item["occupied"]


def finite_ids(_, specification, _tag):
    finite = specification["finite"]
    identities = set(product(finite["types"], range(len(finite["columnForms"])), finite["ifNotExists"], finite["priorTarget"]))
    if len(identities) != 48:
        raise ValueError("The finite native creation contract is inconsistent")
    return identities


def seeded(engine, tag, seed, count):
    rng = Random(seed)
    for _ in range(count):
        fields = []
        for index in range(rng.randint(1, 5)):
            storage, nullable = rng.choice(TYPES), bool(rng.getrandbits(1))
            bounds = (-32768, 32767) if storage == "SMALLINT" else (-2147483648, 2147483647)
            choices = [None, literal(bounds[0]), literal(-2), literal(0), literal(bounds[1])]
            if engine == "postgres" or nullable:
                choices.append(["null"])
            fields.append([f"c{index}", storage, nullable, rng.choice(choices)])
        yield case(engine, tag, fields, bool(rng.getrandbits(1)), bool(rng.getrandbits(1)))


def analyzed(engine, tag):
    base = SCHEMAS[engine] + ".ir_create_target"
    field_groups = [
        (f"CREATE TABLE {base} (c1 SMALLINT DEFAULT -7, c0 BIGINT NOT NULL DEFAULT 4)",
         case(engine, tag, [["c1", "SMALLINT", True, literal(-7)], ["c0", "BIGINT", False, literal(4)]])),
        (f"CREATE TABLE IF NOT EXISTS {base} (c0 INTEGER DEFAULT NULL)",
         case(engine, tag, [["c0", "INTEGER", True, ["null"]]], optional=True, occupied=True)),
        (f"CREATE TABLE {base} (c0 INT NOT NULL)",
         case(engine, tag, [["c0", "INTEGER", False, None]])),
    ]
    for sql, item in field_groups:
        yield {"dialect": engine, "release": tag, "sql": sql, "declarations": []}, item


def negatives(engine, tag):
    good = ["c0", "INTEGER", True, None]
    if engine == "postgres":
        invalid = case(engine, tag, [])
        invalid["create"]["columns"] = {"1": ["c1", "INTEGER", True, None]}
        yield invalid, "CREATE TABLE columns must form an ordered list of checked columns."
    else:
        yield case(engine, tag, []), "nonempty ordered column list"
    yield case(engine, tag, [good, good]), "cannot repeat a column name"
    table = SCHEMAS[engine] + ".ir_create_target" if engine == "mysql" else "ir_create_target"
    invalid_schema = "CREATE TABLE ir_create_target (c0 INTEGER)" if engine == "mysql" else "CREATE TABLE pg_temp.ir_create_target (c0 INTEGER)"
    unsupported = (f"CREATE TEMPORARY TABLE {table} (c0 INTEGER) ENGINE=MEMORY", "CREATE TABLE requires the native InnoDB storage role.")
    if engine == "postgres":
        unsupported = (f"CREATE TEMPORARY TABLE {table} (c0 INTEGER)", "persistent ordinary table")
    for sql, reason in (
        unsupported,
        (invalid_schema, "named declaration schema" if engine == "mysql" else "absolute schema"),
    ):
        yield {"dialect": engine, "release": tag, "sql": sql, "declarations": []}, reason
    if engine == "mysql":
        yield case(engine, tag, [["c0", "SMALLINT", True, literal(32768)]]), "outside the signed storage range"
        yield case(engine, tag, [["c0", "INTEGER", False, ["null"]]]), "cannot declare a NULL default"
        yield case(engine, tag, [["É", "INTEGER", True, None]]), "requires an ASCII column name"
    else:
        yield case(engine, tag, [["ctid", "INTEGER", True, None]]), "cannot declare a system column name"
        yield case(engine, tag, [["c0", "SMALLINT", True, ["text", "number"]]]), "A text default requires native text storage."


def requests(engine, tag, specification):
    for i, item in enumerate(finite_cases(engine, tag)):
        yield f"finite/{i}", item, item, None
    for seed in specification["exploration"]["seeds"]:
        for i, item in enumerate(seeded(engine, tag, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{i}", item, item, None
    for i, (request, item) in enumerate(analyzed(engine, tag)):
        yield f"analyzed/{i}", request, item, None
    for i, (item, signal) in enumerate(negatives(engine, tag)):
        yield f"negative/{i}", item, item, signal


def default_value(kind, default):
    if default is None or default == ["null"]:
        return None
    if kind == "pg_catalog.text":
        return default[1] if default[0] == "text" else str(int(default[1]))
    if kind == "pg_catalog.bool":
        return default[1]
    return int(default[1])


def catalog(engine, fields):
    types = {"SMALLINT": "smallint", "INTEGER": "int" if engine == "mysql" else "integer", "BIGINT": "bigint",
             "pg_catalog.text": "text", "pg_catalog.bool": "boolean"}
    result = []
    for index, (name, kind, nullable, default) in enumerate(fields, 1):
        value = default_value(kind, default)
        column = [index, name, types[kind], nullable, value, value is not None]
        result.append([*column, "pg_catalog.default"] if kind == "pg_catalog.text" else column)
    return result


def expected(item):
    engine = item["dialect"]
    mysql = engine == "mysql"
    model = item["create"]
    existing = [["prior", "INTEGER", False, literal(97)]]
    fields = existing if item["occupied"] else model["columns"]
    rejected = item["occupied"] and not model["ifNotExists"]
    status = ("42S01" if mysql else "42P07") if rejected else "executed"
    defaults = None
    if not rejected:
        values = []
        for _, storage, nullable, default in fields:
            if not nullable and (default is None or default == ["null"]):
                if not mysql:
                    defaults = {"status": "23502"}
                    break
                values.append(0)
            else:
                values.append(default_value(storage, default))
        else:
            defaults = {"status": "inserted", "values": values}
    engine_name = "InnoDB" if mysql else "heap"
    state = {"engine": engine_name, "columns": catalog(engine, fields)}
    before = {"engine": engine_name, "columns": catalog(engine, existing)} if item["occupied"] else None
    return {
        "status": status, "active": not mysql, "catalog": None if rejected and not mysql else state,
        "defaultInsertion": defaults, "afterRollback": state if mysql else before, "markerAfterRollback": [0, 7] if mysql else [0],
    }


def observation(raw):
    return raw
