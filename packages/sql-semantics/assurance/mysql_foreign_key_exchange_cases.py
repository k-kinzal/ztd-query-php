"""Independent native catalog transitions for MySQL foreign-key exchanges."""

from itertools import product
from random import Random

from mysql_add_foreign_keys_cases import PROFILE as ADD_PROFILE, key_fact
from mysql_create_btree_index_cases import OLD

SCHEMA = "ir_fixture"
ALTERNATE = "ir_fk_exchange_alt"
TABLE = "ir_fk_exchange_child"
PARENT = "ir_fk_exchange_parent"
OWNER = [SCHEMA, TABLE, "table"]
STATUS = "mysql-foreign-key-exchange"
PROFILE = "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/alter-foreign-keys"
AXES = ("originalSymbol", "additionSymbol", "actionOrder", "repeatRemoval", "retainedConstraint", "algorithm")
HEAD = ("rid", "pid", "code_id", "peer_id", "spare_id", "tag_id")
ROWS = [[1, 1, 10, 1, 2, 2], [2, None, None, None, None, None]]
UPDATE = "CASCADE"
DELETE = "SET NULL"


def quoted(name):
    return "`" + name.replace("`", "``") + "`"


def qualified(owner):
    return ".".join(map(quoted, owner[:2]))


def grid(spec):
    return product(*(spec["finite"][axis] for axis in AXES))


def model(tag, original="explicit", requested="allocated", order="drop-before-add", repeat=False, retained=False,
          algorithm="DEFAULT", *, composite=False, extra=None, cross=False, origin="explicit", kind="exchange",
          name=None, padding=0, lock="DEFAULT"):
    prior_symbol = {"explicit": "Original", "ordinal-one": TABLE + "_ibfk_1", "ordinal-nine": TABLE + "_ibfk_9"}[original]
    prior_ordinal = {"explicit": 0, "ordinal-one": 1, "ordinal-nine": 9}[original]
    retained_symbol = TABLE + "_ibfk_4" if retained else None
    parent = [ALTERNATE if cross else SCHEMA, PARENT, "table"]
    cols = [("code_id", "code"), ("peer_id", "id")] if composite else [("code_id", "code")]
    origin_field, origin_target = ("code_id", "code") if origin != "explicit" else ("pid", "id")
    old_key = "prior_pid" if origin == "explicit" else "code_id"
    prior_symbol = TABLE + "_ibfk_1" if origin != "explicit" else prior_symbol
    prior_ordinal = 1 if origin != "explicit" else prior_ordinal
    copied = kind == "copy"
    additions = [{
        "symbol": TABLE + "_ibfk_" + str(max(0 if copied else prior_ordinal, 4 if retained else 0) + 1)
        if requested == "allocated" else prior_symbol.upper() if requested == "same-folded-name" else (name or "Replacement"),
        "requested": requested, "pairs": cols, "effects": [UPDATE, DELETE],
    }]
    if extra is not None:
        additions.append({"symbol": extra, "requested": "distinct-explicit", "pairs": [("tag_id", "id")],
                          "effects": [UPDATE, DELETE]})
    replacement = origin != "explicit" and composite
    created = [addition["pairs"][0][0] if addition["requested"] == "allocated" else addition["symbol"] for addition in additions]
    if origin != "explicit" and not composite:
        created[0] = None
    drop = [prior_symbol] * (2 if repeat else 1) if kind == "exchange" else []
    edits = [*drop, *range(len(additions))] if order == "drop-before-add" else [*range(len(additions)), *drop]
    error = ("repeated-drop" if repeat and tag not in OLD else "duplicate-symbol" if requested == "same-folded-name"
             else "copy-policy" if algorithm == "INPLACE" or lock == "NONE" else None)
    return {"release": tag, "original": original, "requested": requested, "order": order, "repeat": repeat,
            "retained": retained, "algorithm": algorithm, "lock": lock, "owner": OWNER, "parent": parent,
            "origin": origin, "kind": kind, "old": prior_symbol, "oldKey": old_key, "retainedSymbol": retained_symbol,
            "oldPair": [origin_field, origin_target], "edits": edits, "additions": additions, "created": created,
            "removed": [old_key] if replacement else [], "padding": padding, "error": error}


def exploratory(tag, seed, limit, specification):
    random = Random(seed)
    axes = specification["finite"]
    for index in range(limit):
        yield model(tag, random.choice(axes["originalSymbol"]), random.choice(axes["additionSymbol"][:2]),
                    random.choice(axes["actionOrder"]), tag in OLD and index % 4 == 2,
                    bool(index % 3), random.choice(axes["algorithm"]), composite=bool(index % 2),
                    extra=f"Extra_{seed}_{index}" if index % 2 == 0 else None,
                    cross=bool(index % 4), name=f"Named_{seed}_{index}")


def build_keys(item):
    facts = [("PRIMARY", ["rid"], "primary", False), (item["oldKey"], [item["oldPair"][0]], "ordinary", item["origin"] != "explicit")]
    if item["retained"]:
        facts.append(("retained_index", ["spare_id"], "ordinary", False))
    facts.extend((f"f{index}", [f"c{index}"], "ordinary", False) for index in range(item["padding"]))
    return facts


def ddl(item):
    parent = qualified(item["parent"])
    owner = qualified(item["owner"])
    declarations = [f"CREATE TABLE {parent} (id INT NOT NULL PRIMARY KEY, code INT NOT NULL UNIQUE, UNIQUE KEY by_both(code,id)) ENGINE=InnoDB"]
    target = qualified([SCHEMA, TABLE + "_source"]) if item["kind"] == "copy" else owner
    cols = ["rid INT PRIMARY KEY", *(f"{name} INT" for name in HEAD[1:])]
    keys = [f"KEY {quoted(name)} ({quoted(fields[0])})" for name, fields, role, implicit in build_keys(item)
            if role != "primary" and not implicit]
    cols.extend(f"c{index} INT" for index in range(item["padding"]))
    constraints = []
    first, second = item["oldPair"]
    label = f"CONSTRAINT {quoted(item['old'])} " if item["origin"] == "explicit" else ""
    actions = "" if item["origin"] == "explicit" else " ON UPDATE CASCADE ON DELETE SET NULL"
    constraints.append(f"{label}FOREIGN KEY ({quoted(first)}) REFERENCES {parent} ({quoted(second)}){actions}")
    if item["retained"]:
        label = quoted(item["retainedSymbol"])
        constraints.append(f"CONSTRAINT {label} FOREIGN KEY (`spare_id`) REFERENCES {parent} (`id`) ON DELETE SET NULL")
    declarations.append(f"CREATE TABLE {target} ({', '.join(cols + keys + constraints)}) ENGINE=InnoDB")
    if item["kind"] == "copy":
        declarations.append(f"CREATE TABLE {owner} LIKE {target}")
    return declarations


def statement(item, original):
    pieces = []
    for edit in item["edits"]:
        if isinstance(edit, str):
            pieces.append("DROP FOREIGN KEY " + quoted(edit.upper() if original else edit))
            continue
        addition = item["additions"][edit]
        fields, targets = zip(*addition["pairs"])
        label = "" if addition["requested"] == "allocated" else "CONSTRAINT " + quoted(addition["symbol"]) + " "
        update, delete = addition["effects"]
        pieces.append("ADD " + label + f"FOREIGN KEY ({', '.join(map(quoted, fields))}) REFERENCES "
                      + qualified(item["parent"]) + f" ({', '.join(map(quoted, targets))}) ON UPDATE {update} ON DELETE {delete}")
    parts = [f"ALTER TABLE {qualified(item['owner'])} " + ", ".join(pieces)]
    if item["algorithm"] != "DEFAULT" or original:
        parts.append("ALGORITHM=" + item["algorithm"])
    if item["lock"] != "DEFAULT":
        parts.append("LOCK=" + item["lock"])
    return ", ".join(parts)


def analyzed(item):
    return {"dialect": "mysql", "release": item["release"], "declarations": ddl(item),
            "currentDatabase": SCHEMA, "sql": statement(item, True)}


def shapes(item):
    sides = []
    for position, addition in enumerate(item["additions"]):
        sides.append({
            "parent": item["parent"], "symbol": addition["symbol"], "generated": addition["requested"] == "allocated",
            "pairs": [[child, target, "INTEGER", False, True, False] for child, target in addition["pairs"]],
            "effects": [*addition["effects"], "NONE"], "created": item["created"][position], "indexName": None,
        })
    result = {"owner": item["owner"], "policy": [item["algorithm"], item["lock"]], "ignore": False}
    if item["kind"] == "exchange":
        return {"mysqlAlterForeignKeys": {**result, "actions": [
            {"drop": [part, "constraint"]} if isinstance(part, str) else {"add": sides[part]} for part in item["edits"]
        ]}}
    return {"mysqlAddForeignKeys": {**result, "additions": sides}}


def original_edges(item):
    if item["kind"] == "copy":
        return []
    default = "RESTRICT" if item["release"] in OLD else "NO ACTION"
    effects = [default, default] if item["origin"] == "explicit" else [UPDATE, DELETE]
    prior = [(item["old"], [item["oldPair"]], effects)]
    if item["retained"]:
        prior.append((item["retainedSymbol"], [("spare_id", "id")], [default, "SET NULL"]))
    return prior


def direct(item):
    parent, owner = item["parent"], item["owner"]
    old_keys = [key_fact(owner, name, parts, {"owner": owner, "descending": False}, role)
                | {"implicitForeignKey": generated} for name, parts, role, generated in build_keys(item)]
    new_keys = [key_fact(parent, name, parts, {"owner": owner, "descending": False}, role)
                for name, parts, role in (("PRIMARY", ["id"], "primary"), ("code", ["code"], "unique"),
                                           ("by_both", ["code", "id"], "unique"))]
    constraints = [[owner, name] for name, _, _ in original_edges(item)]
    return {"dialect": "mysql", "release": item["release"], **shapes(item),
            "mysqlForeignKeyCatalog": {"keys": [*old_keys, *new_keys], "constraints": constraints}}


def diagnostic(item):
    if item["error"] == "repeated-drop":
        return ["db-error", "42000", 1091]
    if item["error"] == "duplicate-symbol":
        return ["db-error", "23000", 1022] if item["release"] in OLD else ["db-error", "HY000", 1826]
    if item["error"] == "copy-policy":
        return ["db-error", "0A000", 1846]
    return ["executed"]


def fixture_rows(item):
    rows = [row.copy() for row in ROWS]
    if item["kind"] != "exchange":
        rows[0][3] = None
    return rows


def metadata(item, keys, edges):
    physical = [[name, int(role == "ordinary"), position, field, "A", "BTREE"]
                for name, parts, role, _ in sorted(keys) for position, field in enumerate(parts, 1)]
    fields, rules = [], []
    for symbol, pairs, (update, delete) in sorted(edges):
        rules.append([symbol, update, delete, "NONE"])
        fields.extend([symbol, ordinal, field, item["parent"][0], item["parent"][1], target]
                      for ordinal, (field, target) in enumerate(pairs, 1))
    return {"keys": physical, "fields": fields, "rules": rules, "rows": fixture_rows(item)}


def after_parent(rows, edges, parent_rows, old, new):
    delete = new is None
    next_rows = [row.copy() for row in rows]
    after = [row.copy() for row in parent_rows if row != old] if delete else [new if row == old else row for row in parent_rows]
    for _, pairs, effects in sorted(edges):
        child_coordinates = [HEAD.index(field) for field, _ in pairs]
        parent_coordinates = [("id", "code").index(field) for _, field in pairs]
        previous = [old[slot] for slot in parent_coordinates]
        replacement = None if delete else [new[slot] for slot in parent_coordinates]
        if replacement == previous:
            continue
        for row in next_rows:
            if [row[slot] for slot in child_coordinates] != previous:
                continue
            action = effects[delete]
            if action in ("RESTRICT", "NO ACTION"):
                return [["db-error", "23000", 1451], rows, parent_rows]
            values = [None] * len(previous) if action == "SET NULL" else replacement
            if values is None:
                raise ValueError(action)
            for slot, value in zip(child_coordinates, values):
                row[slot] = value
    return [["executed", 1], next_rows, after]


def insertion(rows, edges, parent, new):
    for _, pairs, _ in edges:
        values = [new[HEAD.index(child)] for child, _ in pairs]
        if None not in values and not any(
            values == [row[("id", "code").index(target)] for _, target in pairs] for row in parent
        ):
            return [["db-error", "23000", 1452], rows, parent]
    return [["executed", 1], [*rows, new], parent]


def prediction(item):
    existing_keys = build_keys(item)
    existing_edges = original_edges(item)
    effect = diagnostic(item)
    if effect == ["executed"]:
        keys = [key for key in existing_keys if key[0] not in item["removed"]]
        edges = [edge for edge in existing_edges if edge[0] not in item["edits"]]
        for introduced, addition in zip(item["created"], item["additions"]):
            edges.append((addition["symbol"], addition["pairs"], addition["effects"]))
            if introduced is not None:
                keys.append((introduced, [child for child, _ in addition["pairs"]], "ordinary", True))
    else:
        keys = existing_keys
        edges = existing_edges

    parents = [[1, 10], [2, 20]]
    rows = fixture_rows(item)
    effects = {
        "update-primary": after_parent(rows, edges, parents, [1, 10], [1, 11]),
        "delete-primary": after_parent(rows, edges, parents, [1, 10], None),
        "delete-secondary": after_parent(rows, edges, parents, [2, 20], None),
        "removed-reference": insertion(rows, edges, parents, [91, 99, None, None, None, None]),
        "added-reference": insertion(rows, edges, parents, [92, None, 99, 1, None, None]),
        "nil-reference": insertion(rows, edges, parents, [93, None, None, None, None, None]),
    }
    return {"status": STATUS, "before": metadata(item, existing_keys, existing_edges),
            "after": metadata(item, keys, edges), "probes": effects, "settings": [1, 1],
            "effect": effect, "marker": 7, "active": False}
