"""Reconcile both PostgreSQL signed membership native profiles."""

from combine_server_join_evidence import main
import run_server_postgres_signed_membership

if __name__ == "__main__":
    main(run_server_postgres_signed_membership)
