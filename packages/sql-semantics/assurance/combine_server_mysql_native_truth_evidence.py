"""Independently replay MySQL native truth in all fixed releases."""

from combine_server_join_evidence import main
import run_server_mysql_native_truth


if __name__ == "__main__":
    main(run_server_mysql_native_truth)
