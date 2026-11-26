"""Native MySQL foreign-key structure and release-sensitive table removal effects."""

from itertools import product
from random import Random

FIRST, OTHER = "ir_fixture", "ir_drop_fk_decoy"
LEGACY = ("mysql-5.6.51", "mysql-5.7.44")
GRAPHS = ("single-edge", "self-edge", "chain", "cycle", "cross-database")
SCOPES = ("persistent", "child-shadow", "parent-shadow")
ORDERINGS = ("child-first", "parent-first", "parent-only", "child-only")
STATUS = "mysql-drop-table-foreign-keys"


def identifier(schema, label):
    return [schema, "ir_fk_drop_" + label]


def quote(key):
    return ".".join(f"\x60{part}\x60" for part in key)


def model(tag, graph, scope, ordering, marker=0, populated=True, if_exists=False):
    parent = identifier(FIRST, "parent")
    child = identifier(OTHER if graph == "cross-database" else FIRST, "child")
    extra = identifier(FIRST, "grand")
    nodes = [parent, child]
    relationships = [[child, parent]]
    if graph == "self-edge":
        relationships = [[parent, parent]]
    if graph == "chain":
        relationships.append([extra, child])
        nodes.append(extra)
    if graph == "cycle":
        relationships.append([parent, child])
    shadow = None if scope == "persistent" else child if scope == "child-shadow" else parent
    choices = {"child-first": list(reversed(nodes)), "parent-first": nodes,
               "parent-only": [parent], "child-only": [child]}
    selections = choices[ordering]
    targets = [[*name, "temporary-table" if shadow == name else "table"] for name in selections]
    selected_persistent = [entry[:2] for entry in targets if entry[2] == "table"]
    references = [[[ *child, "table"], [*parent, "table"]] for child, parent in relationships if parent in selected_persistent]
    return {"dialect": "mysql", "release": tag, "graph": graph, "scope": scope, "ordering": ordering, "offset": marker,
            "populated": populated, "nodes": nodes, "edges": relationships, "shadow": shadow,
            "drop": {"targets": targets, "ifExists": if_exists, "temporaryOnly": False, "references": references}}


def finite_coords(finite):
    return set(product(finite["graph"], finite["scope"], finite["ordering"]))


def coordinate(tag, point):
    return model(tag, *point)


def explored(tag, seed, limit):
    rng = Random(seed)
    for i in range(limit):
        graph = GRAPHS[i % len(GRAPHS)]
        scope = SCOPES[(i // 2 + seed) % len(SCOPES)]
        order = ORDERINGS[(i + seed) % len(ORDERINGS)]
        yield model(tag, graph, scope, order, rng.randrange(60), bool(rng.randrange(2)), bool(rng.randrange(2)))


def declarations(item):
    schemas = []
    for table in item["nodes"]:
        foreign = [f"FOREIGN KEY (pid) REFERENCES {quote(parent)}(id)" for child, parent in item["edges"] if table == child]
        columns = ", ".join(["id INT PRIMARY KEY","pid INT",*foreign])
        schemas.append(f"CREATE TABLE {quote(table)} ({columns}) ENGINE=InnoDB")
    if item["shadow"] is not None:
        schemas.append(f"CREATE TEMPORARY TABLE {quote(item['shadow'])} (id INT PRIMARY KEY, pid INT) ENGINE=InnoDB")
    return schemas


def sql(item, original=False):
    body = item["drop"]
    flags = "TEMPORARY " if body["temporaryOnly"] else ""
    exists = "IF EXISTS " if body["ifExists"] else ""
    end = " CASCADE" if original and body["ifExists"] else ""
    return "DROP " + flags + "TABLE " + exists + ", ".join(quote(table[:2]) for table in body["targets"]) + end


def analyze(item):
    return {"dialect": "mysql", "release": item["release"], "currentDatabase": FIRST, "declarations": declarations(item), "sql": sql(item, True)}


def predicted(item):
    targets = item["drop"]["targets"]
    legacy = item["release"] in LEGACY
    removed_base = {tuple(table[:2]) for table in targets if table[2] == "table"}
    remaining = {tuple(node) for node in item["nodes"]}
    dependencies = [(tuple(child),tuple(parent)) for child,parent in item["edges"]]
    blocked = {parent for child,parent in dependencies if parent in removed_base and child != parent and child not in removed_base}
    shadow = item["shadow"] is not None
    starting_shadow = shadow
    error = False
    if not legacy and blocked:
        error = True
    else:
        for table in targets:
            name = tuple(table[:2])
            if table[2] == "temporary-table":
                shadow = False
            elif legacy and any(parent == name and child != name and child in remaining for child,parent in dependencies):
                error = True
            else:
                remaining.remove(name)
    fault = ["db-error", "23000", 1217] if legacy else ["db-error", "HY000", 3730]
    return {"status": STATUS, "before": state(item, {tuple(node) for node in item["nodes"]}, starting_shadow),
            "after": state(item, remaining, shadow), "effect": fault if error else ["executed"], "affected": None if error else 0,
            "marker": 7, "active": False, "settings": [1,1]}


def state(item, live, shadow):
    rows = [[*name, 101 + item["offset"] + 100*pos if pos != 1 or item["populated"] else None]
            for pos,name in enumerate(item["nodes"]) if tuple(name) in live]
    edges = [[*child,*parent] for child,parent in item["edges"] if tuple(child) in live]
    return {"tables": sorted(rows), "edges": sorted(edges), "shadow": 901 + item["offset"] if shadow else None}
