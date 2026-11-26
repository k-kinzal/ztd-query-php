"""Replay native PostgreSQL single-index rebuild evidence."""

from combine_server_join_evidence import main
import run_server_postgres_reindex_index

if __name__ == "__main__":
    main(run_server_postgres_reindex_index)
