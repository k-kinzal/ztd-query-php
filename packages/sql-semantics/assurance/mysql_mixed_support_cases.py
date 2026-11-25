"""Independent interpretation of simultaneous explicit and foreign supporting keys."""

from itertools import product
from random import Random

from mysql_cascade_model import permits_staged_row
from mysql_create_btree_index_cases import OLD
from mysql_foreign_key_exchange_cases import qualified, quoted

OWNER = ["ir_fixture", "ir_mixed_child", "table"]
PARENT = "ir_mixed_parent"
ALTERNATE = "ir_mixed_alt"
FIELDS = ("id", "pid", "extra", "spare")
CHILDREN = [[1, 1, 11, 99], [2, 2, 21, 88], [3, None, None, 99]]
PARENTS = [[1, 11], [2, 21], [3, 31]]
AXES = ("originalSupport", "indexDeclaration", "foreignNaming", "clauseOrder")
PROFILE = "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/alter-foreign-keys"
EFFECTS = ["CASCADE", "CASCADE"]


def physical(field, descending=False):
    return [field, descending]


def index(label, fields, unique=False):
    return {"label": label, "fields": fields, "role": "unique" if unique else "ordinary"}


def finite(spec):
    return product(*(spec[axis] for axis in AXES))


def allocation(active, stem, label):
    if label is not None:
        return None if label.lower() in active else label
    suffix = 2
    available = stem
    while available.lower() in active:
        available = f"{stem}_{suffix}"
        suffix += 1
    return available


def leading(fields, needed):
    return fields[:len(needed)] == needed


def initial(prior):
    primary = ["PRIMARY", "primary", [physical("id")], False]
    if prior == "vacant":
        return [primary], []
    if prior == "occupied":
        return [primary, ["pid", "ordinary", [physical("spare")], False],
                ["pid_2", "ordinary", [physical("extra")], False]], []
    return [primary, ["backing", "ordinary", [physical("pid")], prior == "implicit"]], [
        ["backing" if prior == "implicit" else "before_fk", [["pid", "k"]]]]


def declarations(point):
    pid = physical("pid")
    extra = physical("extra")
    spare = physical("spare")
    definitions = {
        "unnamed-leading": [index(None, [pid])],
        "unnamed-descending": [index(None, [physical("pid", True)])],
        "unnamed-later": [index(None, [extra, pid])],
        "same-old-name": [index("backing", [pid, extra])],
        "same-foreign-symbol": [index("added", [extra, pid])],
        "same-automatic-fk-key": [index("pid", [spare])],
        "distinct": [index("writer", [pid, extra], True)],
        "second-explicit": [index(None, [physical("pid", True)]), index("pid", [spare])],
    }
    return definitions[point]


def model(tag, prior, selector, naming, order, *, indexes=None, cross=False, drop=False):
    originals, references = initial(prior)
    prior_plan = [["index", edit] for edit in (declarations(selector) if indexes is None else indexes)]
    pairs = [["pid", "k"], ["extra", "n"]] if naming == "long" else [["pid", "k"]]
    constraint = "added" if naming == "selected" else f"{OWNER[1]}_ibfk_1"
    edge = {"label": "added" if naming == "selected" else "pid" if naming == "named-key" else None,
            "pairs": pairs, "symbol": constraint, "generated": naming != "selected"}
    edge_plan = [["foreign", edge]]
    actions = prior_plan + edge_plan if order == "key-first" else edge_plan + prior_plan
    if drop:
        actions.insert(1, ["drop", references[0][0]])
    parent = [ALTERNATE if cross else OWNER[0], PARENT, "table"]
    return {"coordinate": [prior, selector, naming, order], "tag": tag, "actions": actions,
            "originals": originals, "references": references, "parent": parent, "legacy": tag in OLD,
            "drop": drop}


def key_result(value):
    plan = value["actions"]
    originals = value["originals"]
    selected = []
    for role, effect in plan:
        if role == "index":
            fields = [[name, direction and not value["legacy"]] for name, direction in effect["fields"]]
            selected.append(fields)
    edge = next(effect for role, effect in plan if role == "foreign")
    required = [physical(child) for child, _ in edge["pairs"]]
    cover = any(leading(terms, required) for terms in selected) or any(
        leading(terms, required) and (len(terms) > len(required) or not implicit)
        for _, _, terms, implicit in originals)
    additions = selected + ([] if cover else [required])
    removed = [name for name, _, prefix, implicit in originals
               if implicit and any(leading(terms, prefix) for terms in additions)]
    original_names = {name.lower() for name, _, _, _ in originals if name not in removed}
    applied = []
    collision = None
    current = iter(selected)
    for role, effect in plan:
        if role == "drop" or (role == "foreign" and cover):
            applied.append(None)
            continue
        terms = next(current) if role == "index" else required
        name = allocation(original_names, terms[0][0], effect["label"])
        if name is None:
            name = effect["label"]
            if collision is None:
                collision = name
        original_names.add(name.lower())
        applied.append([name, effect.get("role", "ordinary"), terms, role == "foreign"])
    return {"applied": applied, "removed": removed, "collision": collision}


def key_clause(label, role, fields):
    style = "UNIQUE INDEX" if role == "unique" else "INDEX"
    operand = ", ".join(quoted(field) + (" DESC" if descending else "") for field, descending in fields)
    return style + (" " + quoted(label) if label is not None else "") + " (" + operand + ")"


def edge_clause(pairs, parent):
    child = ", ".join(quoted(item[0]) for item in pairs)
    target = ", ".join(quoted(item[1]) for item in pairs)
    return f"({child}) REFERENCES {qualified(parent)} ({target}) ON UPDATE CASCADE ON DELETE CASCADE"


def statement(value):
    edits = []
    for role, edit in value["actions"]:
        if role == "index":
            edits.append("ADD " + key_clause(edit["label"], edit["role"], edit["fields"]))
        elif role == "drop":
            edits.append("DROP FOREIGN KEY " + quoted(edit))
        else:
            label = edit["label"]
            prefix = "CONSTRAINT " + quoted(label) + " " if label is not None and not edit["generated"] else ""
            suffix = " " + quoted(label) if label is not None and edit["generated"] else ""
            edits.append("ADD " + prefix + "FOREIGN KEY" + suffix + " " + edge_clause(edit["pairs"], value["parent"]))
    return f"ALTER TABLE {qualified(OWNER)} " + ", ".join(edits)


def ddl(value):
    parent = f"CREATE TABLE {qualified(value['parent'])} (k INT NOT NULL PRIMARY KEY, n INT NOT NULL, UNIQUE KEY pair(k,n)) ENGINE=InnoDB"
    columns = ["id INT NOT NULL PRIMARY KEY", "pid INT NULL", "extra INT NULL", "spare INT NOT NULL"]
    keys = [key_clause(name, role, fields) for name, role, fields, implicit in value["originals"]
            if role != "primary" and not implicit]
    constraints = [f"CONSTRAINT {quoted(symbol)} FOREIGN KEY " + edge_clause(pairs, value["parent"])
                   for symbol, pairs in value["references"]]
    return [parent, f"CREATE TABLE {qualified(OWNER)} ({', '.join(columns + keys + constraints)}) ENGINE=InnoDB"]


def analyze(value):
    return {"dialect": "mysql", "release": value["tag"], "declarations": ddl(value),
            "currentDatabase": OWNER[0], "sql": statement(value)}


def checked_key(owner, name, role, terms, *, implicit=False):
    return {"owner": owner, "name": name, "role": role, "fields": terms, "full": True,
            "autoIncrement": None, "implicitForeignKey": implicit}


def published(value, result):
    actions = []
    for (role, request), introduced in zip(value["actions"], result["applied"]):
        if role == "drop":
            actions.append({"drop": [request, "constraint"]})
        elif role == "index":
            name, key_role, fields, _ = introduced
            actions.append({"index": {"owner": OWNER, "name": name, "role": key_role, "fields": fields,
                                      "automatic": request["label"] is None}})
        else:
            actions.append({"add": {
                "parent": value["parent"], "symbol": request["symbol"], "generated": request["generated"],
                "pairs": [[child, target, "INTEGER", False, True, False] for child, target in request["pairs"]],
                "effects": [*EFFECTS, "NONE"], "created": None if introduced is None else introduced[0],
                "indexName": request["label"] if introduced is not None and request["generated"] else None,
            }})
    return {"owner": OWNER, "actions": actions, "removedIndexes": result["removed"], "policy": ["DEFAULT", "DEFAULT"], "ignore": False}


def direct(value, result):
    parent = value["parent"]
    keys = [checked_key(OWNER, name, role, fields, implicit=implicit)
            for name, role, fields, implicit in value["originals"]]
    keys += [checked_key(parent, "PRIMARY", "primary", [physical("k")]),
             checked_key(parent, "pair", "unique", [physical("k"), physical("n")])]
    observed = published(value, result)
    return {"dialect": "mysql", "release": value["tag"], "mysqlAlterForeignKeys": observed,
            "mysqlForeignKeyCatalog": {"keys": keys, "constraints": [[OWNER, label] for label, _ in value["references"]]}}


def explorations(tag, seed, count):
    rng = Random(seed)
    for ordinal in range(count):
        parts = rng.sample(list(FIELDS[1:]), 1 + ordinal % 3)
        primary = [index(None if ordinal % 2 else f"Seed_{seed}_{ordinal}",
                         [physical(field, bool(rng.getrandbits(1))) for field in parts], unique=ordinal % 3 == 1)]
        if ordinal % 3 == 2:
            primary.append(index("Aux", [physical("pid")]))
        prior = ("vacant", "implicit", "explicit", "occupied")[ordinal % 4]
        yield model(tag, prior, "seeded", ("selected", "generated", "long")[ordinal % 3],
                    ("key-first", "foreign-first")[ordinal % 2], indexes=primary, cross=bool(ordinal % 5),
                    drop=prior in ("implicit", "explicit") and ordinal % 5 == 0)


def catalog(value, keys, edges):
    physical_keys = [[name, int(role == "ordinary"), ordinal, field, "D" if descending else "A", "BTREE"]
                     for name, role, terms, _ in sorted(keys) for ordinal, (field, descending) in enumerate(terms, 1)]
    fields = [[label, ordinal, child, *value["parent"][:2], target]
              for label, pairs in sorted(edges) for ordinal, (child, target) in enumerate(pairs, 1)]
    rules = [[label, *EFFECTS, "NONE"] for label, _ in sorted(edges)]
    return {"keys": physical_keys, "fields": fields, "rules": rules, "rows": CHILDREN}


def parent_write(rows, edges, position, new):
    parent = PARENTS[position]
    changed = [row.copy() for row in rows]
    for symbol, pairs in sorted(edges):
        child_slots = [FIELDS.index(child) for child, _ in pairs]
        parent_slots = [("k", "n").index(target) for _, target in pairs]
        old_cells = [parent[slot] for slot in parent_slots]
        if new is None:
            changed = [row for row in changed if [row[slot] for slot in child_slots] != old_cells]
        else:
            for row in changed:
                if [row[slot] for slot in child_slots] == old_cells:
                    staged = row.copy()
                    for child, target in zip(child_slots, parent_slots):
                        staged[child] = new[target]
                    if not permits_staged_row(row, staged, edges, symbol, FIELDS, ("k", "n"), PARENTS):
                        return [["db-error", "23000", 1452], rows, PARENTS]
                    row[:] = staged
    next_parents = [row for index, row in enumerate(PARENTS) if index != position]
    if new is not None:
        next_parents.append(new)
    return [["executed", 1], changed, sorted(next_parents)]


def insert(rows, keys, edges, new):
    for _, pairs in edges:
        child = [new[FIELDS.index(column)] for column, _ in pairs]
        parent_slots = [("k", "n").index(target) for _, target in pairs]
        if None not in child and not any(child == [row[slot] for slot in parent_slots] for row in PARENTS):
            return [["db-error", "23000", 1452], rows, PARENTS]
    for _, role, terms, _ in keys:
        if role == "ordinary":
            continue
        slots = [FIELDS.index(field) for field, _ in terms]
        wanted = [new[slot] for slot in slots]
        if None not in wanted and any(wanted == [row[slot] for slot in slots] for row in rows):
            return [["db-error", "23000", 1062], rows, PARENTS]
    return [["executed", 1], [*rows, new], PARENTS]


def prediction(value, result):
    previous, relations = value["originals"], value["references"]
    proposed, edges = previous, relations
    failure = result["collision"]
    if failure is None:
        proposed = [term for term in previous if term[0] not in result["removed"]]
        proposed += [term for term in result["applied"] if term is not None]
        edges = list(relations)
        for role, edit in value["actions"]:
            if role == "foreign":
                edges.append([edit["symbol"], edit["pairs"]])
            elif role == "drop":
                edges = [record for record in edges if record[0] != edit]
    return {
        "status": "mysql-mixed-support", "settings": [1, 1], "marker": 7, "active": False,
        "before": catalog(value, previous, relations), "after": catalog(value, proposed, edges),
        "effect": ["executed"] if failure is None else ["db-error", "42000", 1061],
        "affected": len(CHILDREN) if failure is None else None,
        "probes": {"update": parent_write(CHILDREN, edges, 1, [12, 21]),
                   "delete": parent_write(CHILDREN, edges, 0, None),
                   "duplicate": insert(CHILDREN, proposed, edges, [4, 1, 11, 99]),
                   "missing": insert(CHILDREN, proposed, edges, [4, 99, 21, 77]),
                   "nullable": insert(CHILDREN, proposed, edges, [4, None, None, 77])},
    }
