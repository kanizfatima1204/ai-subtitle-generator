from __future__ import annotations

from app.llm_service import LLMService


class AIAssistant:

    def __init__(
        self,
        llm: LLMService,
    ):
        self.llm = llm

    async def process(
        self,
        text: str,
        action: str,
        target_language: str | None = None,
    ) -> dict:

        text = text.strip()

        if not text:
            return {
                "text": "",
                "action": action,
            }

        if action == "improve":

            result = await self.llm.improve(
                text
            )

        elif action == "grammar":

            result = await self.llm.grammar(
                text
            )

        elif action == "shorten":

            result = await self.llm.shorten(
                text
            )

        elif action == "translate":

            if target_language not in {
                "bn",
                "en",
            }:
                raise ValueError(
                    "Translation target language "
                    "must be bn or en."
                )

            result = await self.llm.translate(
                text,
                target_language,
            )

        else:

            raise ValueError(
                f"Unsupported AI action: {action}"
            )

        return {
            "text": result,
            "action": action,
            "target_language":
                target_language,
        }