from bot.core.i18n import tr
from bot.db.mysql import cursor
from bot.core.recommendations import build_recommendations


async def list_tests() -> list[dict]:
    async with cursor() as cur:
        await cur.execute(
            """
            SELECT id, title, description, scoring_type, emoji, intro_text
            FROM tests
            WHERE is_active = 1
            ORDER BY sort_order, title
            """
        )
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


def scale_result_summary(scale_results: list[dict]) -> str:
    if not scale_results:
        return ""

    severity_weight = {"critical": 4, "risk": 3, "good": 2, "excellent": 1}
    ordered = sorted(
        scale_results,
        key=lambda item: (
            severity_weight.get((item.get("result") or {}).get("severity"), 0),
            int(item.get("score") or 0),
        ),
        reverse=True,
    )

    parts = [
        "Ваши результаты по системам организма готовы. Ниже направления, которым стоит уделить внимание в первую очередь."
    ]
    for item in ordered[:3]:
        result = item.get("result") or {}
        title = result.get("title") or "результат"
        summary = (result.get("summary_text") or "").strip()
        line = f"{item['title']}: {title} ({item['score']})."
        if summary:
            line += f" {summary}"
        parts.append(line)
    parts.append("Для персонального разбора и подбора программы лучше обсудить результат с консультантом.")
    return "\n\n".join(parts)


async def save_scale_scores(cur, test_id: int, session_id: int, answer_ids: list[int]) -> list[dict]:
    await cur.execute(
        "SELECT id, title FROM test_scales WHERE test_id = %s ORDER BY sort_order, id",
        (test_id,),
    )
    scales = await cur.fetchall()
    if not scales:
        return []

    scores = {int(scale["id"]): 0 for scale in scales}
    if answer_ids:
        placeholders = ",".join(["%s"] * len(answer_ids))
        await cur.execute(
            f"""
            SELECT tass.scale_id, SUM(tass.score) AS score
            FROM test_answer_scale_scores tass
            INNER JOIN test_answers ta ON ta.id = tass.answer_id
            INNER JOIN test_questions tq ON tq.id = ta.question_id
            WHERE tq.test_id = %s AND tass.answer_id IN ({placeholders})
            GROUP BY tass.scale_id
            """,
            (test_id, *answer_ids),
        )
        for row in await cur.fetchall():
            scores[int(row["scale_id"])] = int(row["score"] or 0)

    await cur.execute("DELETE FROM user_test_scale_scores WHERE session_id = %s", (session_id,))
    saved = []
    for scale in scales:
        scale_id = int(scale["id"])
        score = scores.get(scale_id, 0)
        await cur.execute(
            """
            SELECT id, title, summary_text, advice_text, severity
            FROM test_scale_results
            WHERE scale_id = %s AND min_score <= %s AND max_score >= %s
            ORDER BY sort_order, id
            LIMIT 1
            """,
            (scale_id, score, score),
        )
        result = await cur.fetchone()
        await cur.execute(
            """
            INSERT INTO user_test_scale_scores (session_id, scale_id, score, result_id)
            VALUES (%s, %s, %s, %s)
            """,
            (session_id, scale_id, score, result["id"] if result else None),
        )
        saved.append({
            "scale_id": scale_id,
            "title": scale["title"],
            "score": score,
            "result": result,
        })
    return saved


async def get_or_create_test_session(end_user_id: int, test_id: int, reset: bool = False) -> dict:
    async with cursor() as cur:
        if reset:
            await cur.execute(
                """
                DELETE FROM user_test_sessions
                WHERE end_user_id = %s AND test_id = %s AND completed_at IS NULL
                """,
                (end_user_id, test_id),
            )

        await cur.execute(
            """
            SELECT *
            FROM user_test_sessions
            WHERE end_user_id = %s AND test_id = %s AND completed_at IS NULL
            ORDER BY id DESC
            LIMIT 1
            """,
            (end_user_id, test_id),
        )
        session = await cur.fetchone()
        if session:
            session["is_new"] = False
            return session

        await cur.execute(
            "INSERT INTO user_test_sessions (end_user_id, test_id) VALUES (%s, %s)",
            (end_user_id, test_id),
        )
        return {"id": cur.lastrowid, "end_user_id": end_user_id, "test_id": test_id, "is_new": True}


async def session_answered_question_ids(session_id: int) -> set[int]:
    async with cursor() as cur:
        await cur.execute(
            "SELECT DISTINCT question_id FROM user_test_answers WHERE session_id = %s",
            (session_id,),
        )
        return {int(row["question_id"]) for row in await cur.fetchall()}


async def save_session_answer(
    session_id: int,
    question_id: int,
    answer_ids: list[int] | None = None,
    text_answer: str | None = None,
) -> None:
    answer_ids = answer_ids or []
    async with cursor() as cur:
        await cur.execute(
            "DELETE FROM user_test_answers WHERE session_id = %s AND question_id = %s",
            (session_id, question_id),
        )

        if answer_ids:
            for answer_id in answer_ids:
                await cur.execute(
                    "SELECT score FROM test_answers WHERE id = %s AND question_id = %s",
                    (answer_id, question_id),
                )
                row = await cur.fetchone()
                if not row:
                    continue
                score = int(row["score"])
                await cur.execute(
                    """
                    INSERT INTO user_test_answers (session_id, question_id, answer_id, text_answer, score)
                    VALUES (%s, %s, %s, %s, %s)
                    """,
                    (session_id, question_id, answer_id, None, score),
                )
            return

        await cur.execute(
            """
            INSERT INTO user_test_answers (session_id, question_id, answer_id, text_answer, score)
            VALUES (%s, %s, %s, %s, %s)
            """,
            (session_id, question_id, None, text_answer, 0),
        )


async def complete_test_session(end_user_id: int, test_id: int, session_id: int) -> dict:
    async with cursor() as cur:
        await cur.execute(
            "SELECT COALESCE(SUM(score), 0) AS total_score FROM user_test_answers WHERE session_id = %s",
            (session_id,),
        )
        total_score = int((await cur.fetchone())["total_score"] or 0)

        await cur.execute(
            "SELECT answer_id FROM user_test_answers WHERE session_id = %s AND answer_id IS NOT NULL",
            (session_id,),
        )
        selected_answer_ids = [int(row["answer_id"]) for row in await cur.fetchall()]

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
        scale_results = await save_scale_scores(cur, test_id, session_id, selected_answer_ids)
        summary = scale_result_summary(scale_results) or "\n\n".join(
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
        "scale_results": scale_results,
    }


async def save_test_result(end_user_id: int, test_id: int, answers: list[dict]) -> dict:
    async with cursor() as cur:
        await cur.execute(
            "INSERT INTO user_test_sessions (end_user_id, test_id) VALUES (%s, %s)",
            (end_user_id, test_id),
        )
        session_id = cur.lastrowid
        total_score = 0
        selected_answer_ids: list[int] = []

        for answer in answers:
            answer_id = answer.get("answer_id")
            question_id = answer["question_id"]
            score = 0

            if answer_id:
                await cur.execute("SELECT score FROM test_answers WHERE id = %s", (answer_id,))
                row = await cur.fetchone()
                score = int(row["score"]) if row else 0
                selected_answer_ids.append(int(answer_id))

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
        scale_results = await save_scale_scores(cur, test_id, session_id, selected_answer_ids)
        summary = scale_result_summary(scale_results) or "\n\n".join(
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
        "scale_results": scale_results,
    }
