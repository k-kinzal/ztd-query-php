"""Define decoded catalog coordinates and independent index effect predictions."""

from postgres_reindex_cases import qualified, quoted

DATABASE = "ir_fixture"
SCHEMAS = {"front": "qual_front", "later": "qual_later"}
SPELLINGS = ("schema-only", "quoted-current", "folded-current")
ROLES = ("create", "drop", "rebuild-index", "rebuild-owner", "rebuild-key", "rebuild-exclusion",
         "rebuild-partition-index", "rebuild-partition-owner")
NEGATIVE_ROLES = ("create", "drop", "rebuild-index", "rebuild-owner")
FOREIGN_SPELLINGS = ("foreign", "case-distinct")
COLUMNS = ("id", "payload")
STATUS = "index-catalog-binding"


def case(tag, role, spelling, target, *, table="items", index="chosen", slot=0, base=7, coordinate=None):
    return {"dialect": "postgres", "release": tag, "role": role, "spelling": spelling, "target": target,
            "table": table, "index": index, "slot": slot, "base": base, "coordinate": coordinate}


def coordinate_name(schema, name, spelling):
    catalog = {
        "schema-only": "", "quoted-current": quoted(DATABASE) + ".", "folded-current": DATABASE.upper() + ".",
        "foreign": quoted("postgres") + ".", "case-distinct": quoted(DATABASE.upper()) + ".",
        "unknown-current": quoted(DATABASE) + ".",
    }[spelling]
    return catalog + qualified(schema, name)


def sql(item, spelling=None, schema=None, canonical=False):
    role = item["role"]
    schema = SCHEMAS[item["target"]] if schema is None else schema
    spelling = item["spelling"] if spelling is None else spelling
    owner = coordinate_name(schema, item["table"], spelling)
    index = coordinate_name(schema, item["index"], spelling)
    if role == "create":
        prefix = f"CREATE INDEX {quoted(item['index'])} ON {owner}"
        column = quoted(COLUMNS[item["slot"]])
        return f"{prefix} USING btree ({column} ASC NULLS LAST)" if canonical else f"{prefix} ({column})"
    if role == "drop":
        return "DROP INDEX " + index + (" RESTRICT" if canonical else "")
    if role in ("rebuild-owner", "rebuild-partition-owner"):
        return "REINDEX TABLE " + owner
    return "REINDEX INDEX " + index


def leaf_index(column):
    return "piece_" + column + "_idx"


def schema_fixture(item, which):
    schema = SCHEMAS[which]
    role = item["role"]
    partitioned = role in ("rebuild-partition-index", "rebuild-partition-owner")
    owner = qualified(schema, item["table"])
    selected = which == item["target"]
    slot = item["slot"] if selected else 1 - item["slot"]
    key = quoted(COLUMNS[slot])
    keeper = quoted(COLUMNS[1 - slot])
    built_in = {
        "rebuild-key": f", CONSTRAINT {quoted(item['index'])} PRIMARY KEY ({key})",
        "rebuild-exclusion": f", CONSTRAINT {quoted(item['index'])} EXCLUDE USING btree ({key} WITH =)",
    }.get(role, "")
    declarations = [f"CREATE TABLE {owner} (id INTEGER, payload INTEGER{built_in})"
                    + (" PARTITION BY RANGE (id)" if partitioned else "")]
    if partitioned:
        declarations.append(f"CREATE TABLE {qualified(schema, 'piece')} PARTITION OF {owner} FOR VALUES FROM (-100000) TO (100000)")
    declarations.append(f"CREATE INDEX keeper ON {owner} ({keeper})")
    if not built_in and (role != "create" or not selected):
        declarations.append(f"CREATE INDEX {quoted(item['index'])} ON {owner} ({key})")
    return declarations


def declarations(item):
    return [*(f"CREATE SCHEMA {schema}" for schema in SCHEMAS.values()),
            *(statement for which in SCHEMAS for statement in schema_fixture(item, which))]


def analyzed(item):
    return {"dialect": "postgres", "release": item["release"], "schemaPath": list(SCHEMAS.values()),
            "declarations": declarations(item), "sql": sql(item),
            **({"currentCatalog": DATABASE} if item["spelling"] != "unknown-current" else {})}


def rows(item, which):
    base = item["base"] + (0 if which == "front" else 500)
    return [[base, base * 2 + 5], [base + 1, base * 2 + 13]]


def expected(item, invalid=False):
    role, target = item["role"], item["target"]
    partitioned = role in ("rebuild-partition-index", "rebuild-partition-owner")
    name, table = item["index"], item["table"]
    before, after, changed, edges = [], [], [], []
    for which, schema in SCHEMAS.items():
        selected = which == target
        slot = item["slot"] if selected else 1 - item["slot"]
        kind = "I" if partitioned else "i"
        keys = [(name, slot), ("keeper", 1 - slot)]
        for label, position in keys:
            key = [schema, label]
            primary = label == name and role == "rebuild-key"
            exclusion = label == name and role == "rebuild-exclusion"
            entry = [*key, table, kind, str(position + 1), "btree", primary, exclusion, primary, True]
            if not (selected and label == name and role == "create"):
                before.append(entry)
            if not (selected and label == name and ((role == "drop" and not invalid) or (role == "create" and invalid))):
                after.append(entry)
            if partitioned:
                leaf = leaf_index(COLUMNS[position])
                entry = [schema, leaf, "piece", "i", str(position + 1), "btree", False, False, False, True]
                edges.append([schema, label, schema, leaf])
                before.append(entry)
                after.append(entry)
                key = [schema, leaf]
            if selected and not invalid and (role in ("rebuild-owner", "rebuild-partition-owner")
                    or (label == name and role.startswith("rebuild-"))):
                changed.append(key)
    command = {"create": "CREATE INDEX", "drop": "DROP INDEX"}.get(role, "REINDEX")
    return {"status": STATUS, "command": None if invalid else command, "sqlstate": "0A000" if invalid else "00000",
            "notices": [], "before": sorted(before), "after": sorted(after), "attachmentsBefore": sorted(edges),
            "attachmentsAfter": sorted(edges), "changedFiles": sorted(changed), "changedIds": [],
            "wrongFileRole": [], "rows": {schema: rows(item, which) for which, schema in SCHEMAS.items()}}
