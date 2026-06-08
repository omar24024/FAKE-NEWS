#!/usr/bin/env python3
"""
Serveur IA persistant — GBERT Hassaniya
Le modèle reste en mémoire entre les requêtes (FastAPI + uvicorn).

Démarrage :
  ./start_ai_server.sh
  ou : uvicorn ai_server:app --host 127.0.0.1 --port 8765
"""

import logging
import sys
from contextlib import asynccontextmanager

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

logging.basicConfig(
    level=logging.INFO,
    format="[%(asctime)s] %(levelname)-8s | %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
    stream=sys.stderr,
)
logger = logging.getLogger(__name__)

from analyze import ai_classify, analyze_post, analyze_all_pending, load_model, _uses_gbert


def _warmup_model() -> None:
    logger.info("Préchargement du modèle IA…")
    if _uses_gbert():
        from gbert_model import _load
        _load()
    else:
        load_model()
    logger.info("Modèle prêt.")


@asynccontextmanager
async def lifespan(_app: FastAPI):
    _warmup_model()
    yield


app = FastAPI(title="GBERT Hassaniya AI", version="1.0.0", lifespan=lifespan)


class AnalyzeTextRequest(BaseModel):
    text: str = Field(..., min_length=1)


@app.get("/health")
def health():
    return {"status": "ok", "model": "gbert-hassaniya" if _uses_gbert() else "default"}


@app.post("/analyze")
def analyze_text(body: AnalyzeTextRequest):
    try:
        return ai_classify(body.text)
    except Exception as e:
        logger.exception("Erreur analyse texte")
        raise HTTPException(status_code=500, detail=str(e)) from e


@app.post("/analyze/post/{post_id}")
def analyze_single_post(post_id: int):
    try:
        result = analyze_post(post_id)
        if "error" in result:
            raise HTTPException(status_code=404, detail=result["error"])
        return result
    except HTTPException:
        raise
    except Exception as e:
        logger.exception("Erreur analyse post %s", post_id)
        raise HTTPException(status_code=500, detail=str(e)) from e


@app.post("/analyze/all")
def analyze_pending():
    try:
        return {"results": analyze_all_pending()}
    except Exception as e:
        logger.exception("Erreur analyse batch")
        raise HTTPException(status_code=500, detail=str(e)) from e
