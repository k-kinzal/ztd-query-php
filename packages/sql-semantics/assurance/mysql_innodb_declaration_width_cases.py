"""Independent declaration recipes and the native observations they require."""

from itertools import product
from random import Random

from run_server_mysql_session_create_table import table

TAG_56 = "mysql-5.6.51"
OLD = (TAG_56, "mysql-5.7.44")
SCHEMA, NAME, COLLATION = "ir_fixture", "ir_width_data", "utf8mb4_bin"
WIDTHS = (1, 128, 129, 996, 997, 998, 1012, 1013, 1014, 1017, 1018)
CLUSTERS, SCOPES = ("implicit", "explicit"), ("persistent", "temporary")
SIGNED = {"TINYINT": 1, "SMALLINT": 2, "MEDIUMINT": 3, "INTEGER": 4, "BIGINT": 8}
STATUS = "mysql-innodb-declaration-width"
BASE_TYPE = "BIGINT"
WIDTH_LIMIT = "An InnoDB table can declare at most 1017 columns."
PHYSICAL_LIMIT = "The declared clustered InnoDB record must fit the native 8126-byte boundary."
LOGICAL_LIMIT = "The declared logical record cannot exceed 65535 bytes."
EMPTY_LIST = "CREATE TABLE needs a nonempty ordered column list."


def quote(name):
    return "`" + name.replace("`", "``") + "`"


def literal(value):
    return ["null"] if value is None else ["integer", str(value)] if type(value) is int else ["text", value]


def column(index, storage, nullable, default):
    return [f"c{index}", storage, nullable, literal(default)]


def key_explicit(recipe):
    return "primaryKey" in recipe["create"]


def definition(tag, scope, columns, primary=None, guard=False):
    kind = "temporary-table" if scope == "temporary" else "table"
    create = {"target": [SCHEMA, NAME], "columns": columns, "ifNotExists": guard, "tableCollation": COLLATION}
    if primary is not None:
        create["primaryKey"] = primary
    return {"dialect": "mysql", "release": tag, "createKind": kind, "create": create}


def coordinate(tag, point):
    width, nullable, cluster, scope = point
    slot = min(1016, width - 1) if cluster == "explicit" else None
    fields = [column(i, BASE_TYPE, nullable and i != slot, None if nullable and i != slot else 7 + (i % 37))
              for i in range(width)]
    if width == 1 and slot == 0 and nullable:
        fields[0][3] = literal(-7)
    primary = None if slot is None else [[slot, False]]
    return definition(tag, scope, fields, primary)


def coordinates():
    return set(product(WIDTHS, (False, True), CLUSTERS, SCOPES))


def coordinate_failure(tag, point):
    width, nullable, cluster, _ = point
    if width == 1018:
        return WIDTH_LIMIT
    maximum = 997 if nullable else 1012 if cluster == "implicit" else 1013
    if tag != TAG_56 and width > maximum:
        return PHYSICAL_LIMIT
    return None


def varchar(length):
    return ["varchar", length, COLLATION]


def edges(tag):
    for scope in SCOPES:
        for cluster, fixed in (("implicit", 1012), ("explicit", 1013)):
            primary = [[fixed - 1, False]] if cluster == "explicit" else None
            initial = [column(i, BASE_TYPE, False, 7) for i in range(fixed)]
            first, second = ("MEDIUMINT", "SMALLINT") if cluster == "implicit" else ("SMALLINT", "TINYINT")
            for boundary in (8125, 8126):
                last = second if boundary == 8125 else first
                # The explicit record needs three or four bytes; the implicit record needs five or six.
                if cluster == "explicit" and boundary == 8126:
                    last = "SMALLINT"
                mixed = [*initial, column(fixed, first, False, 3), column(fixed + 1, last, False, 41)]
                failure = PHYSICAL_LIMIT if tag != TAG_56 and boundary == 8126 else None
                yield f"{scope}/{cluster}-{boundary}", definition(tag, scope, mixed, primary), failure
        for length in (10, 11, 64):
            for count in (196, 197):
                # This is the real DYNAMIC boundary with 25 bytes of nullable flags.
                widths = [column(i, varchar(length), True, None) for i in range(count)]
                failure = PHYSICAL_LIMIT if tag != TAG_56 and count == 197 else None
                yield f"{scope}/varchar-{length}-{count}", definition(tag, scope, widths), failure
        for extra in (2, 3):
            fields = [column(i, varchar(255), False, "a") for i in range(64)]
            fields.append(column(64, varchar(31), False, "a"))
            fields.extend(column(65 + i, "TINYINT", False, 1) for i in range(extra))
            yield f"{scope}/logical-{65533 + extra}", definition(tag, scope, fields), LOGICAL_LIMIT if extra == 3 else None
        full = [column(i, "TINYINT", False, i % 127) for i in range(1017)]
        key = [[1016, tag not in OLD]]
        yield f"{scope}/native-maximum", definition(tag, scope, full, key), None
        yield f"{scope}/guarded-maximum", definition(tag, scope, full, key, True), None


def exploration(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        scope = SCOPES[index % 2]
        width = rng.randrange(1, 48) if index < 4 else rng.randrange(129, 353)
        fields = [column(slot, rng.choice(tuple(SIGNED)), bool(rng.randrange(2)), rng.randint(-120, 120))
                  for slot in range(width)]
        fields[0][0] = f"odd ` field{seed}"
        if index % 4 == 0:
            fields[0] = [fields[0][0], varchar(rng.choice((0, 1, 10, 11, 63, 64, 255))), True, literal(None)]
        if index % 3 == 0:
            fields[-1] = column(width - 1, BASE_TYPE, True, 31)
        if index % 2 == 1:
            fields[-1][2] = False
            key = [[width - 1, tag not in OLD]]
        else:
            key = None
        yield definition(tag, scope, fields, key)


def command(item):
    layout = item["create"]
    fields = []
    for name, storage, nullable, default in layout["columns"]:
        storage = (f"VARCHAR({storage[1]}) CHARACTER SET utf8mb4 COLLATE {storage[2]}"
                   if isinstance(storage, list) else storage)
        datatype = " NULL" if nullable else " NOT NULL"
        kind, *value = default
        spelling = "NULL" if kind == "null" else value[0] if kind == "integer" else "'" + value[0] + "'"
        fields.append(quote(name) + " " + storage + datatype + " DEFAULT " + spelling)
    if key_explicit(item):
        fields.append("PRIMARY KEY (" + ", ".join(quote(layout["columns"][slot][0]) + (" DESC" if descending else "")
                                                  for slot, descending in layout["primaryKey"]) + ")")
    temporary = "TEMPORARY " if item["createKind"] == "temporary-table" else ""
    guard = "IF NOT EXISTS " if layout["ifNotExists"] else ""
    target = ".".join(map(quote, layout["target"]))
    return f"CREATE {temporary}TABLE {guard}{target} ({', '.join(fields)}) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE {COLLATION}"


def analyze(item):
    return {"dialect": "mysql", "release": item["release"], "sql": command(item), "currentDatabase": SCHEMA, "declarations": []}


def record_size(item, actual=False):
    """Calculate the dictionary warning boundary and the actual 5.6 probe row size."""
    fields = item["create"]["columns"]
    length = 18 if key_explicit(item) else 24
    length += -(-sum(column[2] for column in fields) // 8)
    for _, storage, _, default in fields:
        if actual and default[0] == "null":
            continue
        if isinstance(storage, str):
            length += SIGNED[storage]
        elif actual:
            length += len(default[1].encode()) + 1
        else:
            length += min(storage[1] * 4, 40) + 1
    return length


def prediction(item, failure=None):
    fields = item["create"]["columns"]
    temporary = item["createKind"] == "temporary-table"
    guard = item["create"]["ifNotExists"]
    if failure is not None:
        sqlstate, number = ("HY000", 1117) if failure == WIDTH_LIMIT else ("42000", 1118)
        warnings = [["Error", number]]
        if failure == PHYSICAL_LIMIT:
            warnings.append(["Error", 1030])
        return {"status": STATUS, "effect": ["db-error", sqlstate, number], "warnings": warnings,
                "visible": None, "global": False, "write": None}
    if guard:
        scope, ddl = item["createKind"], [["prior", "INTEGER", False, literal(61)]]
        visible = table(item["release"], scope, ddl, [], [[61]])
        return {"status": STATUS, "effect": ["executed", 0], "warnings": [["Note", 1050]],
                "visible": visible, "global": not temporary, "write": None, "active": temporary, "committedMarker": 0 if temporary else 7}
    bad_write = item["release"] == TAG_56 and record_size(item, actual=True) >= 8126
    warn = item["release"] == TAG_56 and record_size(item) >= 8126
    row = [None if default[0] == "null" else int(default[1]) if default[0] == "integer" else default[1]
           for _, _, _, default in fields]
    visible = table(item["release"], item["createKind"], fields, item["create"].get("primaryKey", []), [] if bad_write else [row])
    return {"status": STATUS, "effect": ["executed", 0], "warnings": [["Warning", 139]] if warn else [],
            "visible": visible, "global": not temporary, "write": ["db-error", "42000", 1118] if bad_write else ["executed", 1],
            "active": temporary, "committedMarker": 0 if temporary else 7}
