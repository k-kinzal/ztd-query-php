"""Replay the PostgreSQL typed-column evidence for both pinned releases."""

import run_server_postgres_typed_columns
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_typed_columns)
