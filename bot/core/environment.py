import os
from pathlib import Path

from dotenv import load_dotenv


PROJECT_ROOT = Path(__file__).resolve().parents[2]
ENV_FILE = Path(
    os.getenv("SWPRO_ENV_FILE", str(PROJECT_ROOT / "deploy" / "plesk" / "live.env"))
)
REQUIRED_KEYS = (
    "DB_HOST",
    "DB_PORT",
    "DB_DATABASE",
    "DB_USERNAME",
    "DB_PASSWORD",
    "SWPRO_PUBLIC_URL",
    "SWPRO_MINI_APP_URL",
)


def load_project_env() -> Path:
    if not ENV_FILE.is_file():
        raise RuntimeError(f"Application configuration not found: {ENV_FILE}")

    load_dotenv(ENV_FILE, override=True)
    missing = [name for name in REQUIRED_KEYS if not os.getenv(name)]
    if missing:
        raise RuntimeError(
            f"Missing {', '.join(missing)} in application configuration: {ENV_FILE}"
        )
    return ENV_FILE


def required_env(name: str) -> str:
    value = os.getenv(name)
    if value is None or value == "":
        raise RuntimeError(f"Missing {name} in application configuration: {ENV_FILE}")
    return value
