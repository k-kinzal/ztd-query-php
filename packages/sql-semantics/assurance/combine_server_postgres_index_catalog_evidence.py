"""Replay the exact native PostgreSQL qualified index operation receipts."""

import run_server_postgres_index_catalog
from combine_server_join_evidence import main

if __name__ == "__main__":
    main(run_server_postgres_index_catalog)
