"""Replay PostgreSQL's UPDATE returned rows and effects on both pinned releases."""

import run_server_postgres_update_returning
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_update_returning)
