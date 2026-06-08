"""
GBERT Hassaniya — inference pour la plateforme fake news.
Combine AraBERT + AraGPT2 avec les poids fine-tunés (Phase 3).
"""

import os
import logging

logger = logging.getLogger(__name__)

ARABERT = "aubmindlab/bert-base-arabertv2"
ARAGPT2 = "aubmindlab/aragpt2-base"

_MODEL = None
_TOKENIZER_BERT = None
_TOKENIZER_GPT = None


def _default_weights_path() -> str:
    base = os.path.dirname(os.path.abspath(__file__))
    return os.getenv(
        "GBERT_MODEL_PATH",
        os.path.join(base, "models", "gbert_hassaniya_FINAL.pt"),
    )


def _load():
    global _MODEL, _TOKENIZER_BERT, _TOKENIZER_GPT
    if _MODEL is not None:
        return _MODEL

    import torch
    import torch.nn as nn
    from transformers import AutoTokenizer, AutoModel

    weights_path = _default_weights_path()
    if not os.path.isfile(weights_path):
        raise FileNotFoundError(
            f"Poids GBERT introuvables : {weights_path}. "
            "Copiez gbert_hassaniya_FINAL.pt dans python-ai/models/ "
            "ou définissez GBERT_MODEL_PATH."
        )

    logger.info("Chargement GBERT depuis %s", weights_path)

    _TOKENIZER_BERT = AutoTokenizer.from_pretrained(ARABERT)
    _TOKENIZER_GPT = AutoTokenizer.from_pretrained(ARAGPT2)
    _TOKENIZER_GPT.pad_token = _TOKENIZER_GPT.eos_token

    class GBERTModel(nn.Module):
        def __init__(self, num_labels=2):
            super().__init__()
            self.bert = AutoModel.from_pretrained(ARABERT)
            self.gpt = AutoModel.from_pretrained(ARAGPT2)
            self.gpt.config.pad_token_id = _TOKENIZER_GPT.eos_token_id
            hidden = self.bert.config.hidden_size
            self.classifier = nn.Sequential(
                nn.Linear(hidden * 2, 512),
                nn.ReLU(),
                nn.Dropout(0.3),
                nn.Linear(512, num_labels),
            )

        def forward(self, ids_bert, mask_bert, ids_gpt, mask_gpt):
            bert_out = self.bert(
                input_ids=ids_bert, attention_mask=mask_bert
            ).last_hidden_state[:, 0, :]
            gpt_out = self.gpt(
                input_ids=ids_gpt, attention_mask=mask_gpt
            ).last_hidden_state[:, -1, :]
            return self.classifier(torch.cat([bert_out, gpt_out], dim=1))

    model = GBERTModel(num_labels=2)
    model.load_state_dict(
        torch.load(weights_path, map_location="cpu", weights_only=True)
    )
    model.eval()
    _MODEL = model
    logger.info("GBERT chargé avec succès")
    return _MODEL


def predict(text: str) -> tuple[int, float, dict]:
    """
    Prédire fake news vs fiable.
    Retourne (label, confiance_pct, probas) avec label 0=Fiable, 1=Fake News.
    """
    import torch

    text = (text or "").strip()
    if not text:
        return 0, 50.0, {"fiable": 0.5, "fake_news": 0.5}

    model = _load()
    eb = _TOKENIZER_BERT(
        text, return_tensors="pt", padding=True, truncation=True, max_length=128
    )
    eg = _TOKENIZER_GPT(
        text, return_tensors="pt", padding=True, truncation=True, max_length=128
    )

    with torch.no_grad():
        logits = model(
            eb["input_ids"],
            eb["attention_mask"],
            eg["input_ids"],
            eg["attention_mask"],
        )
        probs = torch.softmax(logits, dim=1)[0]
        pred = int(torch.argmax(probs).item())
        conf = float(probs[pred].item()) * 100

    return pred, conf, {
        "fiable": float(probs[0].item()),
        "fake_news": float(probs[1].item()),
    }
