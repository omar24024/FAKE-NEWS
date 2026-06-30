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

from analyze import ai_classify, load_model
import os


def _uses_gbert():
    return True


def _warmup_model() -> None:
    logger.info("Préchargement du modèle IA…")
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
    return {"status": "ok", "model": "gbert-hassaniya"}


@app.post("/analyze")
def analyze_text(body: AnalyzeTextRequest):
    try:
        return ai_classify(body.text)
    except Exception as e:
        logger.exception("Erreur analyse texte")
        raise HTTPException(status_code=500, detail=str(e)) from e
