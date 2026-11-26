"""Reconcile stock PostgreSQL unique-key evidence."""

from combine_server_join_evidence import main
import run_server_postgres_unique_key

if __name__ == "__main__":
    main(run_server_postgres_unique_key)
