import os
import uuid
from pathlib import Path

from fastapi import (
    FastAPI,
    File,
    Form,
    UploadFile,
)

from app.transcriber import Transcriber

from app.subtitle import (
    ProfessionalSubtitleProcessor,
)

from app.llm_service import (
    LLMService,
)

from app.ai_assistant import (
    AIAssistant,
)


app = FastAPI(
    title="AI Subtitle Service",
    version="2.1.0",
)


UPLOAD_DIR = Path(
    "storage/uploads"
)

UPLOAD_DIR.mkdir(
    parents=True,
    exist_ok=True,
)


processor = (
    ProfessionalSubtitleProcessor()
)


llm_service = LLMService()

ai_assistant = AIAssistant(
    llm=llm_service
)


transcribers: dict[
    str,
    Transcriber
] = {}


def get_transcriber(
    model_name: str,
) -> Transcriber:

    if model_name not in transcribers:

        transcribers[model_name] = (
            Transcriber(
                model_name=model_name
            )
        )

    return transcribers[
        model_name
    ]


@app.get("/")
async def root():

    return {
        "service":
            "AI Subtitle Service",

        "version":
            "2.1.0",

        "status":
            "running",
    }


@app.get("/health")
async def health():

    return {
        "status": "ok"
    }


@app.post("/transcribe")
async def transcribe(
    file: UploadFile = File(...),
    language: str | None = Form(None),
    model: str = Form("base"),
):

    extension = (
        Path(
            file.filename or ""
        ).suffix
        or ".media"
    )

    filename = (
        f"{uuid.uuid4()}"
        f"{extension}"
    )

    file_path = (
        UPLOAD_DIR /
        filename
    )

    with open(
        file_path,
        "wb"
    ) as output:

        while True:

            chunk = await file.read(
                1024 * 1024
            )

            if not chunk:
                break

            output.write(chunk)

    try:

        transcriber = (
            get_transcriber(
                model
            )
        )

        result = (
            transcriber.transcribe(
                str(file_path),
                language=language,
            )
        )

        subtitles = (
            processor.create_subtitles(
                result.get(
                    "segments",
                    []
                )
            )
        )

        return {
            "language":
                result.get("language"),

            "segments":
                result.get(
                    "segments",
                    []
                ),

            "subtitles":
                subtitles,
        }

    finally:

        try:
            file_path.unlink(
                missing_ok=True
            )

        except Exception:
            pass

@app.get("/subtitle-config")
async def subtitle_config():

    return {
        "max_characters": 42,
        "max_lines": 2,
        "min_duration": 1.0,
        "max_duration": 7.0,
        "target_cps": 14.0,
        "max_cps": 20.0,
    }
    
@app.post("/ai-assist")
async def ai_assist(
    text: str = Form(...),
    action: str = Form(...),
    target_language:
        str | None = Form(None),
):

    result = await ai_assistant.process(
        text=text,
        action=action,
        target_language=target_language,
    )

    return result