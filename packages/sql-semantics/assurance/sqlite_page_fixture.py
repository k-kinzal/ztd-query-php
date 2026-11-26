"""Define disposable pager fixtures and inspect file geometry independently."""

from pathlib import Path

from run import require
from run_sqlite_create_table import quoted
from sqlite_header_fixture import databases, rows

KINDS = ("page_size", "page_count", "max_page_count")


def populate(db, item):
    db.execute("ATTACH 'attached.sqlite' AS " + quoted(item["attachment"]))
    if item["initial"] != "empty":
        locations = [("main", 1), (item["attachment"], 3)]
        if item["initial"] == "temp-warm":
            locations.append(("temp", 7))
        for name, marker in locations:
            schema = quoted(name)
            db.execute(f"CREATE TABLE {schema}.kept(id INTEGER PRIMARY KEY, payload BLOB); "
                       f"INSERT INTO {schema}.kept VALUES ({marker},X'01'),({marker+4},NULL)")
        if item["initial"] == "temp-warm":
            db.execute("PRAGMA temp.max_page_count=93")
        db.execute("PRAGMA main.page_size=1024")
    db.execute("PRAGMA main.max_page_count=70; PRAGMA " + quoted(item["attachment"]) + ".max_page_count=110")


def physical_geometry(filename):
    with Path(filename).open("rb") as file:
        header = file.read(100)
    require(header.startswith(b"SQLite format 3\x00") and len(header) == 100, "sqlite-page-file-header", filename)
    size = int.from_bytes(header[16:18], "big")
    pages = int.from_bytes(header[28:32], "big")
    return 65536 if size == 1 else size, pages


def snapshot(db, item, files):
    attached = databases(db)
    states = {}
    for _, name, file in attached:
        target = name[1]
        location = file[1]
        if location:
            path = Path(location)
            require(path.parent.resolve() == Path.cwd() and path.name in ("primary.sqlite", "attached.sqlite"),
                    "sqlite-page-unexpected-path", location)
            file[1] = path.name
        state = {}
        for field in KINDS:
            result = rows(db, f"PRAGMA {quoted(target)}.{field}")
            require(len(result) == 1 and len(result[0]) == 1 and result[0][0][0] == "integer",
                    "sqlite-page-native-state-shape", [target, field, result])
            state[field] = int(result[0][0][1])
        if location and item["initial"] != "empty":
            size, length = physical_geometry(location)
            require((size, length) == (state["page_size"], state["page_count"]),
                    "sqlite-page-file-geometry", [state, size, length])
            files.setdefault(file[1], []).append({"bytes": Path(location).stat().st_size, "size": size, "pages": length})
        states[target] = state
    return {"attached": attached, "databases": states}


def try_growth(db, item, files):
    wrote = db._observe("INSERT INTO main.kept VALUES (91,zeroblob(8192))", {},
                       preserve_order=True, capture_evaluation_error=True)
    remaining = rows(db, "SELECT id,payload FROM main.kept ORDER BY id")
    return {"write": wrote, "rows": remaining, "autocommit": bool(db.get_autocommit(db.db)),
            "state": snapshot(db, item, files)}
