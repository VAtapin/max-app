from bot.core.i18n import tr
from bot.db.mysql import cursor
from bot.core.recommendations import build_recommendations


async def list_tests() -> list[dict]:
    async with cursor() as cur:
        await cur.execute("SELECT id, title, description FROM tests WHERE is_active = 1 ORDER BY sort_order, title")
        return await cur.fetchall()


async def get_test(test_id: int) -> dict | None:
    async with cursor() as cur:
        await cur.execute("SELECT * FROM tests WHERE id = %s AND is_active = 1 LIMIT 1", (test_id,))
        test = await cur.fetchone()
        if not test:
            return None

        await cur.execute(
            "SELECT * FROM test_questions WHERE test_id = %s ORDER BY sort_order, id",
            (test_id,),
        )
        questions = await cur.fetchall()
        for question in questions:
            await cur.execute(
                "SELECT * FROM test_answers WHERE question_id = %s ORDER BY sort_order, id",
                (question["id"],),
            )
            question["answers"] = await cur.fetchall()

        test["questions"] = questions
        return test


async def save_test_result(end_user_id: int, test_id: int, answers: list[dict]) -> dict:
    async with cursor() as cur:
        await cur.execute(
            "INSERT INTO user_test_sessions (end_user_id, test_id) VALUES (%s, %s)",
            (end_user_id, test_id),
        )
        session_id = cur.lastrowid
        total_score = 0

        for answer in answers:
            answer_id = answer.get("answer_id")
            question_id = answer["question_id"]
            score = 0

            if answer_id:
                await cur.execute("SELECT score FROM test_answers WHERE id = %s", (answer_id,))
                row = await cur.fetchone()
                score = int(row["score"]) if row else 0

            total_score += score
            await cur.execute(
                """
                INSERT INTO user_test_answers (session_id, question_id, answer_id, text_answer, score)
                VALUES (%s, %s, %s, %s, %s)
                """,
                (session_id, question_id, answer_id, answer.get("text_answer"), score),
            )

        await cur.execute(
            """
            SELECT title, summary_text, advice_text
            FROM test_results
            WHERE test_id = %s
              AND min_score <= %s
              AND max_score >= %s
            ORDER BY sort_order, id
            LIMIT 1
            """,
            (test_id, total_score, total_score),
        )
        result = await cur.fetchone()
        summary = "\n\n".join(
            part for part in [
                result.get("summary_text") if result else None,
                result.get("advice_text") if result else None,
            ]
            if part
        ) or tr("test.result_summary")

        await cur.execute(
            """
            UPDATE user_test_sessions
            SET completed_at = NOW(), total_score = %s, result_summary = %s
            WHERE id = %s
            """,
            (total_score, summary, session_id),
        )
        await cur.execute(
            """
            INSERT INTO activity_logs (actor_type, actor_id, action, entity_type, entity_id)
            VALUES ('end_user', %s, 'complete_test', 'user_test_sessions', %s)
            """,
            (end_user_id, session_id),
        )

    await build_recommendations(end_user_id, session_id)

    return {
        "session_id": session_id,
        "total_score": total_score,
        "title": result.get("title") if result else tr("test.result_title"),
        "summary": summary,
    }
