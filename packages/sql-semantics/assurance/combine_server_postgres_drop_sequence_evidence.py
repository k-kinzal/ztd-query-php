"""Reconcile the native PostgreSQL sequence removal evidence across releases."""

from combine_server_join_evidence import main
import run_server_postgres_drop_sequence

if __name__ == "__main__":
    main(run_server_postgres_drop_sequence)
