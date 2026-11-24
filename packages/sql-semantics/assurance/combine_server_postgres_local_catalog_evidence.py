"""Replay the exact local PostgreSQL catalog-binding evidence."""

from combine_server_join_evidence import main
import run_server_postgres_local_catalog as domain


if __name__ == "__main__":
    main(domain)
