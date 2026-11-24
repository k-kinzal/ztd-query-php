"""Use disposable SQLite files and record physical state without consulting the IR."""

from pathlib import Path

from run import require
from run_sqlite_create_table import quoted
from sqlite_header_fixture import databases, rows
from sqlite_reclaim_cases import MODES


def populate(db, item):
    db.execute("ATTACH 'attached.sqlite' AS " + quoted(item["attachment"]))
    for index, target in enumerate(("main", item["attachment"])):
        schema = quoted(target)
        db.execute(f"PRAGMA {schema}.auto_vacuum={MODES[item['initial']]}")
        if item["initial"] != "empty-none":
            db.execute(
                f"CREATE TABLE {schema}.kept(id INTEGER PRIMARY KEY, value INT);"
                f"INSERT INTO {schema}.kept VALUES ({index+1},{9+item['marker']}),({index+3},NULL);"
                f"CREATE TABLE {schema}.released(content BLOB);"
                f"INSERT INTO {schema}.released VALUES (zeroblob(16384)); DROP TABLE {schema}.released;"
            )


def physical_header(filename, evidence):
    path = Path(filename)
    require(path.parent.resolve() == Path.cwd() and path.name in ("primary.sqlite", "attached.sqlite"),
            "sqlite-reclaim-unexpected-path", filename)
    size = path.stat().st_size
    if size == 0:
        evidence.setdefault(path.name, []).append({"bytes": 0})
        return None
    with path.open("rb") as file:
        header = file.read(100)
    require(len(header) == 100 and header.startswith(b"SQLite format 3\x00"), "sqlite-reclaim-file-header", filename)
    fields = {field: int.from_bytes(header[offset:offset + 4], "big")
              for field, offset in (("pages", 28), ("free", 36), ("root", 52), ("incremental", 64))}
    evidence.setdefault(path.name, []).append({"bytes": size, "pageOneHex": header.hex(), "fields": fields})
    require(fields["pages"] * int.from_bytes(header[16:18], "big") == size, "sqlite-reclaim-file-length", fields)
    return fields


def snapshot(db, files):
    attached, states = databases(db), {}
    for _, name, file in attached:
        target, filename = name[1], file[1]
        schema = quoted(target)
        state = {}
        for field, pragma in (("mode", "auto_vacuum"), ("pages", "page_count"), ("free", "freelist_count")):
            data = rows(db, f"PRAGMA {schema}.{pragma}")
            require(len(data) == 1 and len(data[0]) == 1 and data[0][0][0] == "integer",
                    "sqlite-reclaim-native-state-shape", [target, field, data])
            state[field] = int(data[0][0][1])
        state["physical"] = physical_header(filename, files) if filename else None
        if filename:
            file[1] = Path(filename).name
        state["tables"] = rows(db, f"SELECT name FROM {schema}.sqlite_schema WHERE type='table' ORDER BY name")
        state["population"] = rows(db, f"SELECT id,value FROM {schema}.kept ORDER BY id") if state["tables"] else []
        states[target] = state
    return {"attached": attached, "databases": states}
