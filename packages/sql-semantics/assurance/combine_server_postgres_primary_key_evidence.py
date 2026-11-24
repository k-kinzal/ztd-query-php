"""Reconcile PostgreSQL primary-key receipts for both stock releases."""

from combine_server_join_evidence import main
import run_server_postgres_primary_key

if __name__ == "__main__":
    main(run_server_postgres_primary_key)
