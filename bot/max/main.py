import asyncio
import logging
import os
from pathlib import Path

from dotenv import load_dotenv

from bot.db.mysql import close_pool, init_pool
from bot.max.adapter import MaxBotAdapter


async def main() -> None:
    load_dotenv(Path(__file__).resolve().parents[1] / ".env")
    logging.basicConfig(level=os.getenv("LOG_LEVEL", "INFO"))
    await init_pool()

    adapter = MaxBotAdapter()
    logging.info("MAX bot adapter is ready. Add webhook/server loop for production.")
    _ = adapter

    await close_pool()


if __name__ == "__main__":
    asyncio.run(main())
