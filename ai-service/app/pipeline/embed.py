"""Text embeddings (spec 6.3: multilingual model via fastembed, 384 dimensions).

B0 stub: returns zero vectors of the right shape so PHP can integrate early.
B2/B3 replace ``embed_texts`` with the real fastembed model, loaded once at startup.
"""

from __future__ import annotations

from collections.abc import Sequence

EMBEDDING_DIM = 384


def embed_texts(texts: Sequence[str]) -> list[list[float]]:
    """Return one ``EMBEDDING_DIM``-long vector per text, in the same order."""
    return [[0.0] * EMBEDDING_DIM for _ in texts]
