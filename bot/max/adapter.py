import os
from typing import Any

import aiohttp


class MaxBotAdapter:
    def __init__(self, token: str | None = None, base_url: str | None = None) -> None:
        self.token = token or os.getenv("MAX_BOT_TOKEN", "")
        self.base_url = (base_url or os.getenv("MAX_API_BASE_URL", "https://botapi.max.ru")).rstrip("/")

    async def request(self, method: str, path: str, payload: dict[str, Any] | None = None) -> dict[str, Any]:
        if not self.token:
            raise RuntimeError("MAX_BOT_TOKEN is not set")

        url = f"{self.base_url}/{path.lstrip('/')}"
        headers = {"Authorization": f"Bearer {self.token}"}
        async with aiohttp.ClientSession(headers=headers) as session:
            async with session.request(method, url, json=payload) as response:
                response.raise_for_status()
                return await response.json()

    async def send_message(self, chat_id: str, text: str) -> dict[str, Any]:
        return await self.request("POST", "messages", {"chat_id": chat_id, "text": text})
