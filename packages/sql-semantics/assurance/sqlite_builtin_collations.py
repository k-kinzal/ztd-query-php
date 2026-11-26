"""Byte keys for SQLite's three builtin comparison sequences."""

from sqlite_relational_cases import children


def collation_key(text, collation):
    encoded = text.encode(errors="surrogateescape")
    if collation == "NOCASE":
        prefix = encoded.partition(b"\x00")[0].lower()
        return prefix.ljust(len(encoded), b"\x00")
    if collation == "RTRIM":
        return encoded.rstrip(b" ")
    assert collation == "BINARY"
    return encoded


def explicit_collation(term):
    return next((child[1] for child in children(term) if child[0] == "collate"), None)
