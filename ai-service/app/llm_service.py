from __future__ import annotations

import json
import os
import re
from typing import Any

import httpx


class LLMService:
    """
    Provider-independent LLM service.

    The rest of the application only knows about:
        improve()
        grammar()
        shorten()
        translate()

    Provider-specific HTTP logic stays here.
    """

    def __init__(self):

        self.provider = os.getenv(
            "LLM_PROVIDER",
            "openai"
        )

        self.api_key = os.getenv(
            "LLM_API_KEY",
            ""
        )

        self.model = os.getenv(
            "LLM_MODEL",
            "gpt-4o-mini"
        )

        self.base_url = os.getenv(
            "LLM_BASE_URL",
            "https://api.openai.com/v1"
        ).rstrip("/")

        self.timeout = float(
            os.getenv(
                "LLM_TIMEOUT",
                "90"
            )
        )

    async def improve(self, text: str) -> str:
        prompt = """
Improve this subtitle for natural, concise,
professional spoken language.

Rules:
- Preserve the original meaning.
- Do not add information.
- Keep it suitable for subtitles.
- Do not explain your changes.
- Return only the improved subtitle.
"""
        return await self.generate(text=text, instruction=prompt)

    async def grammar(self, text: str) -> str:
        prompt = """
Correct grammar, spelling, punctuation and capitalization.

Rules:
- Preserve the original meaning.
- Do not add information.
- Keep the same language as the input.
- Make it natural for subtitles.
- Return only the corrected subtitle.
"""
        return await self.generate(text=text, instruction=prompt)

    async def shorten(self, text: str) -> str:
        prompt = """
Shorten this subtitle while preserving its meaning.

Rules:
- Remove unnecessary words.
- Keep the most important information.
- Make it natural for spoken subtitles.
- Do not add new information.
- Return only the shortened subtitle.
"""
        return await self.generate(text=text, instruction=prompt)

    async def translate(self, text: str, target_language: str) -> str:
        language_name = {"en": "English", "bn": "Bangla"}.get(target_language, target_language)
        prompt = f"""
Translate the following subtitle into {language_name}.

Rules:
- Preserve the exact meaning.
- Keep names and numbers accurate.
- Keep the result natural for spoken dialogue.
- Do not explain the translation.
- Return only the translated subtitle.
"""
        return await self.generate(text=text, instruction=prompt)

    async def generate(self, text: str, instruction: str) -> str:
        if not self.api_key:
            raise RuntimeError("LLM_API_KEY is not configured.")

        if self.provider == "openai":
            return await self._openai(text=text, instruction=instruction)

        raise RuntimeError(f"Unsupported LLM provider: {self.provider}")

    async def _openai(self, text: str, instruction: str) -> str:
        url = f"{self.base_url}/chat/completions"

        payload = {
            "model": self.model,
            "messages": [
                {"role": "system", "content": "You are a professional subtitle editor and translator."},
                {"role": "user", "content": f"{instruction}\n\nSubtitle:\n{text}"},
            ],
            "temperature": 0.2,
            "max_tokens": 500,
        }

        headers = {
            "Authorization": "Bearer " + self.api_key,
            "Content-Type": "application/json",
        }

        async with httpx.AsyncClient(timeout=self.timeout) as client:
            response = await client.post(url, headers=headers, json=payload)

        if response.status_code >= 400:
            try:
                detail = response.json()
            except Exception:
                detail = response.text
            raise RuntimeError(f"LLM request failed: {detail}")

        data = response.json()
        result = data.get("choices", [{}])[0].get("message", {}).get("content", "")
        result = self.clean_result(result)

        if not result:
            raise RuntimeError("LLM returned an empty response.")

        return result

    def clean_result(self, result: str) -> str:
        result = result.strip()
        result = re.sub(r"^```(?:text|plaintext)?\s*", "", result, flags=re.IGNORECASE)
        result = re.sub(r"\s*```$", "", result)

        if len(result) >= 2 and result[0] == '"' and result[-1] == '"':
            result = result[1:-1]

        return result.strip()
