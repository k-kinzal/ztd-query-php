"""Independently author nested partition tables and each selected physical owner."""

from postgres_reindex_cases import qualified, quoted
from postgres_reindex_schema_cases import columns, index, name, owner_sql
from postgres_reindex_table_cases import NATIVE_FORMS, literals

TARGETS = {
    "root-empty": ("empty", "root"), "root-two-leaves": ("flat", "root"),
    "root-nested": ("nested", "root"), "leaf-local": ("nested", "low"),
    "leaf-remote": ("flat", "high"), "branch-remote": ("nested", "high"),
}
DECOY = "decoy"


def trial(tag, target, concurrent, active, spelling, *, root="orders", base=34, form=None, coordinate=None):
    layout, selected = TARGETS[target]
    branch = selected == "root" or selected == "high" and layout == "nested"
    schema = DECOY if selected == "high" else "fixture"
    relation = root if selected == "root" else selected
    return {"dialect": "postgres", "release": tag, "selection": target, "base": base, "root": root,
            "reindexPartitionTable": {"target": [schema, relation], "kind": "branch" if branch else "leaf",
                                      "mode": "concurrently" if concurrent else "standard"},
            "layout": layout, "spelling": spelling, "active": active, "coordinate": coordinate,
            "form": form or ("keyword" if concurrent else "ordinary")}


def owner(name, schema, values, *, parent=None, bounds=None, branch=False, toast=True, key=None):
    return {"name": name, "schema": schema, "rows": values, "kind": "p" if branch else "r", "note": toast,
            "index": key or (name + "_id_idx" if parent else "extra"),
            **({"parent": parent, "bounds": bounds} if parent is not None else {})}


def tree(item):
    layout, base = item["layout"], item["base"]
    outside = owner("outside", "fixture", [[59, base]], toast=False)
    decoy = owner(item["root"], DECOY, [[97, -base]], toast=False)
    lower = [[7, base, "here"]]
    high_zero = [[14, base + 7, None]]
    high_one = [[19, base - 6, "child"]]
    root = owner(item["root"], "fixture", [] if layout == "empty" else lower + high_zero + high_one,
                 branch=True, key=item.get("rootIndex", "root_key"))
    if layout == "empty":
        return [root, outside, decoy]
    low = owner("low", "fixture", lower, parent=root, bounds=(0, 10))
    high = owner("high", DECOY, high_zero + high_one, parent=root, bounds=(10, 20), branch=layout == "nested")
    if layout == "flat":
        return [root, low, high, outside, decoy]
    left = owner("high0", DECOY, high_zero, parent=high, bounds=(10, 15))
    right = owner("high1", "fixture", high_one, parent=high, bounds=(15, 20))
    return [root, low, high, left, right, outside, decoy]


def fixture(item, populate=False):
    commands = ["CREATE SCHEMA fixture", "CREATE SCHEMA decoy"]
    for table in tree(item):
        coordinate = name(table)
        commands.append(owner_sql(table))
        if "parent" not in table:
            key = table["index"] if table["kind"] == "p" else table["name"] + "_standalone"
            commands.append(f"CREATE INDEX {quoted(key)} ON {coordinate} (id)")
        if item["layout"] == "nested" and table["name"] == "high1":
            commands.append(f"CREATE INDEX payload ON {coordinate} (note)")
        if populate and table["kind"] == "r":
            commands.append(f"INSERT INTO {coordinate} VALUES {literals(table['rows'])}")
    return commands


def path(item):
    selected = item["reindexPartitionTable"]["target"][0]
    return [selected, "decoy" if selected == "fixture" else "fixture"]


def analyzed(item):
    schema, target = item["reindexPartitionTable"]["target"]
    if item["spelling"] == "local":
        schema = None
    return {"dialect": "postgres", "release": item["release"], "declarations": fixture(item),
            "schemaPath": path(item), "sql": NATIVE_FORMS[item["form"]] + qualified(schema, target)}


def index_name(table):
    if table["kind"] == "p" or "parent" in table:
        return table["index"]
    return table["name"] + "_standalone"


def attachments(tables):
    links = []
    for node in tables:
        if "parent" not in node:
            continue
        ancestor = node["parent"]
        links.extend([
            [ancestor["schema"], ancestor["name"], node["schema"], node["name"]],
            [ancestor["schema"], index_name(ancestor), node["schema"], index_name(node)],
        ])
    return sorted(links)


def stable_shape(item, table):
    toast = table["note"] and table["kind"] == "r"
    indexes = [index([1], index_name(table))]
    if item["layout"] == "nested" and table["name"] == "high1":
        indexes.append(index([3], "payload"))
    return {"kind": table["kind"], "persistence": "p", "hasToast": toast,
            "columns": columns(table), "rows": table["rows"], "indexes": indexes,
            "toastIndexes": [index([1, 2])] if toast else []}


def expected(item):
    scope = item["reindexPartitionTable"]
    branch, requested, active = scope["kind"] == "branch", scope["mode"] == "concurrently", item["active"]
    refused = active and (branch or requested)
    model = tree(item)
    _, selected = TARGETS[item["selection"]]
    eligible = {
        "root": {"low", "high", "high0", "high1"},
        "high": ({"high0", "high1"} if branch else {"high"}),
        "low": {"low"},
    }[selected]
    physical = {name(node) for node in model if node["kind"] == "r" and "parent" in node and node["name"] in eligible}
    states = {name(table): stable_shape(item, table) for table in model}

    def changed(applies):
        return {
            key: {group: [[applies and key in physical, applies and key in physical and requested] for _ in state[group]]
                  for group in ("indexes", "toastIndexes")}
            for key, state in states.items()
        }

    topology = attachments(model)
    return {"status": "partition-rebuild", "command": None if refused else "REINDEX",
            "sqlState": "25001" if refused else "00000", "notices": [],
            "transactionAfter": "failed" if refused else ("active" if active else "idle"),
            "before": states, "during": None if refused else states, "settled": states,
            "changedDuring": None if refused else changed(True), "changedSettled": changed(not active),
            "attachmentsBefore": topology, "attachmentsDuring": None if refused else topology, "attachmentsSettled": topology}
