"""Independently specify the wide effective headings of query-backed InnoDB tables."""

from copy import deepcopy
from itertools import product
from random import Random

from mysql_innodb_declaration_width_cases import COLLATION, SCHEMA, SCOPES, SIGNED, TAG_56, quote
from run_server_mysql_create_table_as import cast, evaluate_field, select
from run_server_mysql_session_create_table import native_type, table

NAME, SOURCE = "ir_ctas_wide", "ir_ctas_wide_source"
STATUS = "mysql-create-table-as-width"
WIDTH_LIMIT = "The effective InnoDB table heading can have at most 1017 columns."
LOGICAL_LIMIT = "The projected logical table record cannot exceed 65535 bytes."
RECORD_LIMIT = "The projected clustered InnoDB record must fit the native 8126-byte boundary."
FIXTURE = [
    ["required", "INTEGER", False, ["integer", "29"]],
    ["optional", "INTEGER", True, None],
    ["pad", ["varchar", 64, COLLATION], False, ["text", "a"]],
    ["short", ["varchar", 10, COLLATION], True, None],
    ["huge", ["varchar", 1023, COLLATION], True, None],
]
VALUES = [[17, None, "first", None, "larger"], [29, -7, "second", "s", None], [17, None, "first", None, "larger"]]
WIDTHS = {
    "small-int": (128, 129, 1017, 1018),
    "long-int": (997, 998, 1012, 1013, 1017),
    "signed-cast": (129, 1012, 1013, 1017),
    "signed-null": (997, 998, 1017),
    "untyped-null": (129, 1017, 1018),
    "explicit-same-name": (129, 1012, 1013, 1017, 1018),
    "explicit-extra-name": (128, 129, 1016, 1017),
    "inherited-signed": (129, 1017, 1018),
    "inherited-varchar-nullable": (196, 197),
    "inherited-varchar-required": (197, 198),
}


def integer(value):
    return ["integer", str(value)]


def fixture_type(field):
    storage = field[1]
    if isinstance(storage, list):
        return [*storage, False] if not field[2] else storage
    return "int" if field[2] else "int-not-null"


def reference(field, slot):
    return ["text-slot", slot, 0] if isinstance(field[1], list) else ["slot", slot]


def bound_source(field_numbers, aliases):
    seen = list(dict.fromkeys(field_numbers))
    columns = [FIXTURE[slot] for slot in seen]
    source = {"schema": SCHEMA, "table": SOURCE, "columns": [field[0] for field in columns],
              "columnTypes": [fixture_type(field) for field in columns]}
    return source, [[reference(FIXTURE[slot], seen.index(slot)), alias] for slot, alias in zip(field_numbers, aliases)]


def definition(tag, scope, populated, expressions, declarations=(), guard=False, field_numbers=(), aliases=None):
    if aliases is None:
        aliases = [f"c{i}" for i in range(len(field_numbers) or len(expressions))]
    if field_numbers:
        source, columns = bound_source(field_numbers, aliases)
    else:
        source, columns = None, list(map(list, zip(expressions, aliases)))
    query = select(columns, where=None if populated else ["boolean", False])
    query["source"] = source
    kind = "table" if scope == "persistent" else "temporary-table"
    create = {"target": [SCHEMA, NAME, kind], "ifNotExists": guard, "query": query}
    if declarations:
        create["declaredColumns"] = list(declarations)
    return {"dialect": "mysql", "release": tag, "mysqlCreateTableAs": create}


def coordinates():
    return {(family, width, scope, populated) for family, widths in WIDTHS.items()
            for width, scope, populated in product(widths, SCOPES, (False, True))}


def coordinate(tag, point):
    family, width, scope, populated = point
    regular = integer(17)
    signed = cast(regular)
    literals = {"small-int": regular, "long-int": integer(1000000000), "signed-cast": signed,
                "signed-null": cast(["null"]), "untyped-null": ["null"]}
    if family in literals:
        return definition(tag, scope, populated, [literals[family]] * width)
    if family == "explicit-same-name":
        return definition(tag, scope, populated, [signed] * width, [["c0", "TINYINT", False, integer(7)]])
    if family == "explicit-extra-name":
        return definition(tag, scope, populated, [regular] * width, [["leading", "TINYINT", False, integer(37)]])
    inherited = {"inherited-signed": 0, "inherited-varchar-required": 2, "inherited-varchar-nullable": 3}
    return definition(tag, scope, populated, [], field_numbers=[inherited[family]] * width)


def edge_cases(tag):
    for scope, populated in product(SCOPES, (False, True)):
        for tail in (0, 1):
            yield f"{scope}/{populated}/mixed-null/{tail}", definition(tag, scope, populated, [], field_numbers=[2] * 197 + [tail] * 6)
        for extra in ("MEDIUMINT", "INTEGER"):
            explicit = [[f"d{i}", "BIGINT", False, integer(7)] for i in range(1012)]
            explicit.append(["leading", extra, False, integer(17)])
            yield f"{scope}/{populated}/bare-null/{extra}", definition(tag, scope, populated, [["null"]], explicit)
        for count in (16, 17):
            yield f"{scope}/{populated}/logical-varchar/{count}", definition(tag, scope, populated, [], field_numbers=[4] * count)
    for scope in SCOPES:
        guarded = definition(tag, scope, True, [integer(17)] * 1017, guard=True)
        yield f"{scope}/guarded", guarded


def generated(tag, seed, count):
    rng = Random(seed)
    for at in range(count):
        width = rng.randrange(1, 32) if at < 4 else rng.randrange(129, 171)
        scope = SCOPES[at % 2]
        populated = (at // 2) % 2 == 0
        labels = [f"c{position}" for position in rng.sample(range(500), width)]
        labels[-1] = f"odd ` field{seed}"
        if at % 3 == 0:
            slots = [rng.randrange(4) for _ in range(width)]
            yield definition(tag, scope, populated, [], field_numbers=slots, aliases=labels)
            continue
        atoms = [integer(-17), integer(17), integer(99999999), integer(100000000), integer(1000000000),
                 ["null"], cast(["null"]), cast(integer(17)), ["boolean", False]]
        values = [rng.choice(atoms) for _ in range(width)]
        extra = [[labels[-1], "TINYINT", True, ["null"]], ["leading", "SMALLINT", False, integer(37)]] if at % 3 == 1 else []
        if extra:
            values[-1] = cast(integer(-11))
        yield definition(tag, scope, populated, values, extra, aliases=labels)


def key(value):
    return value.lower()


def inherited(expr, source):
    name = source["columns"][expr[1]]
    ordinal, field = next((i, item) for i, item in enumerate(FIXTURE) if item[0] == name)
    return ordinal, field


def stored_shape(item):
    """Read the constraints of the inferred relation without invoking the PHP model."""
    tag = item["release"]
    model = item["mysqlCreateTableAs"]
    query = model["query"]
    explicit = {key(field[0]): field for field in model.get("declaredColumns", [])}
    projected = {key(name) for _, name in query["columns"]}
    fields = [[field, None] for name, field in explicit.items() if name not in projected]
    for expr, name in query["columns"]:
        if key(name) in explicit:
            chosen = explicit[key(name)]
            fields.append([[chosen[0], *chosen[1:]], expr])
        elif expr[0] in {"slot", "text-slot"}:
            _, original = inherited(expr, query["source"])
            fields.append([[name, *original[1:]], expr])
        else:
            heading, _ = evaluate_field(tag, expr, name)
            _, physical, nullable, default, _ = heading
            if physical.startswith("binary"):
                type_ = "BINARY0"
            elif physical.startswith("varbinary"):
                type_ = "VARBINARY0"
            else:
                type_ = "BIGINT" if physical.startswith("bigint") else "INTEGER"
            fields.append([[name, type_, nullable, None if default is None else integer(int(default))], expr])
    return fields


def width_error(item):
    fields = stored_shape(item)
    if len(fields) > 1017:
        return WIDTH_LIMIT
    logical, clustered, nullable, packed = 0, 24, 0, False
    for (_, type_, is_nullable, _), _ in fields:
        nullable += is_nullable
        if type_ in ("BINARY0", "VARBINARY0"):
            variable = type_ == "VARBINARY0"
            logical += variable
            clustered += 1
            packed = packed or variable
        elif isinstance(type_, list):
            encoded = type_[1] * 4
            logical += encoded + (1 if encoded < 256 else 2)
            clustered += 1 + min(encoded, 40)
            packed = True
        else:
            logical += SIGNED[type_]
            clustered += SIGNED[type_]
    if logical + (nullable + (not packed) + 7) // 8 > 65535:
        return LOGICAL_LIMIT
    if item["release"] != TAG_56 and clustered + (nullable + 7) // 8 >= 8126:
        return RECORD_LIMIT
    return None


def declarations():
    fields = [field_sql(field) for field in FIXTURE]
    return [f"CREATE TABLE {quote(SCHEMA)}.{quote(SOURCE)} ({', '.join(fields)}) ENGINE=InnoDB"]


def atom_sql(term, source=None):
    role = term[0]
    if role in {"slot", "text-slot"}:
        return quote("__ztd_source") + "." + quote(source["columns"][term[1]])
    if role == "signed-cast":
        return f"CAST({atom_sql(term[1])} AS SIGNED)"
    if role == "integer":
        return term[1]
    if role == "boolean":
        return "TRUE" if term[1] else "FALSE"
    if role == "null":
        return "NULL"
    if role == "text":
        return "'" + term[1].replace("'", "''") + "'"
    raise ValueError(role)


def field_sql(field):
    name, storage, nullable, default = field
    type_ = (f"VARCHAR({storage[1]}) CHARACTER SET utf8mb4 COLLATE {storage[2]}"
             if isinstance(storage, list) else storage)
    return quote(name) + " " + type_ + (" NULL" if nullable else " NOT NULL") + (
        "" if default is None else " DEFAULT " + atom_sql(default)
    )


def command(item):
    create = item["mysqlCreateTableAs"]
    query = create["query"]
    schema, target, kind = create["target"]
    explicit = create.get("declaredColumns", [])
    heading = "" if not explicit else " (" + ", ".join(map(field_sql, explicit)) + ")"
    environment = query["source"]
    temporary = "TEMPORARY " if kind == "temporary-table" else ""
    guard = "IF NOT EXISTS " if create["ifNotExists"] else ""
    parts = ", ".join(atom_sql(expr, environment) + " AS " + quote(name) for expr, name in query["columns"])
    source = (f" FROM {quote(SCHEMA)}.{quote(SOURCE)} AS `__ztd_source`" if environment else
              " FROM DUAL" if query["where"] is not None else "")
    where = "" if query["where"] is None else " WHERE FALSE"
    return f"CREATE {temporary}TABLE {guard}{quote(schema)}.{quote(target)}{heading} ENGINE=InnoDB AS SELECT {parts}{source}{where}"


def analyze(item):
    return {"dialect": "mysql", "release": item["release"], "sql": command(item), "currentDatabase": SCHEMA, "declarations": declarations()}


def source_nullability(model):
    return [next(field[2] for field in FIXTURE if field[0] == name)
            for name in model["query"]["source"]["columns"]] if model["query"]["source"] else []


def observed_create(item):
    model = deepcopy(item["mysqlCreateTableAs"])
    source = model["query"]["source"]
    if source:
        display = ["int" if isinstance(type_, str) else type_ for type_ in source["columnTypes"]]
        if display == ["int"] * len(display):
            source.pop("columnTypes")
        else:
            source["columnTypes"] = display
    return model


def expression_value(tag, expr, source, data):
    if expr[0] in {"slot", "text-slot"}:
        ordinal, _ = inherited(expr, source)
        return data[ordinal]
    return evaluate_field(tag, expr, "value")[1]


def evaluate_default(default):
    if default is None or default[0] == "null":
        return None
    return int(default[1]) if default[0] == "integer" else default[1]


def row_size(fields, row, worst):
    payload = 24
    nullable = sum(field[2] for field, _ in fields)
    for index, ((_, storage, _, _), _) in enumerate(fields):
        if not worst and row[index] is None:
            continue
        if isinstance(storage, list):
            payload += (min(storage[1] * 4, 40) if worst else len(row[index].encode())) + 1
        elif storage in ("VARBINARY0", "BINARY0"):
            payload += 1
        else:
            payload += SIGNED[storage]
    return payload + (nullable + 7) // 8


def affected_fields(item, fields, rows):
    tag = item["release"]
    create = item["mysqlCreateTableAs"]
    explicit = {key(field[0]): field for field in create.get("declaredColumns", [])}
    heading = []
    for field, expr in fields:
        name, storage, nullable, default = field
        if key(name) not in explicit and expr[0] not in {"slot", "text-slot"}:
            heading.append(evaluate_field(tag, expr, name)[0])
        else:
            kind, collation = native_type(tag, storage)
            value = evaluate_default(default)
            heading.append([name, kind, nullable, str(value) if type(value) is int else value, collation])
    return {"kind": create["target"][2], "engine": "InnoDB", "collation": COLLATION, "columns": heading, "key": [], "rows": rows}


def prediction(item, failure):
    tag = item["release"]
    model = item["mysqlCreateTableAs"]
    kind = model["target"][2]
    temporary = kind == "temporary-table"
    if failure:
        sqlstate, number = ("HY000", 1117) if failure == WIDTH_LIMIT else ("42000", 1118)
        warnings = [["Error", number]] + ([["Error", 1030]] if failure == RECORD_LIMIT else [])
        return {"status": STATUS, "effect": ["db-error", sqlstate, number], "warnings": warnings,
                "visible": None, "global": False}
    if model["ifNotExists"]:
        prior = table(tag, kind, [["prior", "INTEGER", False, integer(61)]], [], [[61]])
        return {"status": STATUS, "effect": ["executed", 0], "warnings": [["Note", 1050]],
                "visible": prior, "global": not temporary}
    query = model["query"]
    fields = stored_shape(item)
    records = VALUES if query["source"] else [None]
    rows = [] if query["where"] is not None else [
        [evaluate_default(field[3]) if expr is None else expression_value(tag, expr, query["source"], data)
         for field, expr in fields] for data in records
    ]
    if tag == TAG_56 and any(row_size(fields, row, False) >= 8126 for row in rows):
        return {"status": STATUS, "effect": ["db-error", "42000", 1118],
                "warnings": [["Warning", 139], ["Error", 1118], ["Error", 1030]], "visible": None, "global": False}
    warn = tag == TAG_56 and row_size(fields, None, True) >= 8126
    return {"status": STATUS, "effect": ["executed", len(rows)], "warnings": [["Warning", 139]] if warn else [],
            "visible": affected_fields(item, fields, rows), "global": not temporary}
