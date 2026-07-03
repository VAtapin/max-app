from contextlib import asynccontextmanager

import aiomysql

from bot.core.environment import load_project_env, required_env

load_project_env()

_pool: aiomysql.Pool | None = None


async def init_pool() -> aiomysql.Pool:
    global _pool
    if _pool is None:
        _pool = await aiomysql.create_pool(
            host=required_env("DB_HOST"),
            port=int(required_env("DB_PORT")),
            user=required_env("DB_USERNAME"),
            password=required_env("DB_PASSWORD"),
            db=required_env("DB_DATABASE"),
            charset="utf8mb4",
            autocommit=True,
            minsize=1,
            maxsize=10,
        )
    return _pool


async def close_pool() -> None:
    global _pool
    if _pool is not None:
        _pool.close()
        await _pool.wait_closed()
        _pool = None


@asynccontextmanager
async def cursor():
    pool = await init_pool()
    async with pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            yield cur
