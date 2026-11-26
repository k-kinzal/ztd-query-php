"""Replay schema-wide rebuild evidence for both checked PostgreSQL releases."""

from combine_server_join_evidence import main
import run_server_postgres_reindex_schema as domain


if __name__ == "__main__":
    main(domain)
