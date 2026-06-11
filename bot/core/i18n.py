from __future__ import annotations

import json
from functools import lru_cache
from pathlib import Path

DEFAULT_LANGUAGE = "ru"


@lru_cache(maxsize=8)
def translations(language: str = DEFAULT_LANGUAGE) -> dict[str, str]:
    path = Path(__file__).resolve().parents[1] / "i18n" / f"{language}.json"
    if not path.is_file():
        return {}
    return json.loads(path.read_text(encoding="utf-8"))


def tr(key: str, language: str = DEFAULT_LANGUAGE, **params: object) -> str:
    text = translations(language).get(key, key)
    for name, value in params.items():
        text = text.replace("{" + name + "}", str(value))
    return text
