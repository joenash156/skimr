# Skimr AI service. Runs on Render (Docker runtime, root directory ai-service).
# Secrets are NOT baked in: Render injects them as environment variables.
FROM python:3.11-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1 \
    PORT=8000

# Tesseract is a program, not a Python package (spec 8). eng ships with tesseract-ocr.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        tesseract-ocr tesseract-ocr-fra tesseract-ocr-spa \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /srv

# Dependencies first, so code changes don't reinstall them.
COPY requirements.txt .
RUN pip install -r requirements.txt

COPY app ./app

# Run as an unprivileged user.
RUN useradd --system --uid 10001 --no-create-home skimr
USER skimr

EXPOSE 8000

# Shell form so Render's $PORT is expanded; exec so uvicorn receives stop signals.
CMD ["sh", "-c", "exec uvicorn app.main:app --host 0.0.0.0 --port \"$PORT\""]
