# PostgreSQL/pgvector

SupportFlow AI requires PostgreSQL with the `vector` extension. Embeddings use the official Laravel 13 API:

- `Schema::ensureVectorExtensionExists()`
- `vector(1536)` on `knowledge_chunks.embedding` with an HNSW cosine index
- Eloquent `array` cast
- `whereVectorSimilarTo()`/`selectVectorDistance()` for retrieval

## Local (Laravel Herd)

Herd 1.30+ ships pgvector with the PostgreSQL 18 binary. Enable it on the app database:

```sql
CREATE EXTENSION IF NOT EXISTS vector;
SELECT extversion FROM pg_extension WHERE extname = 'vector';
```

Do not compile pgvector into Herd (breaks on upgrades).

Pest uses PostgreSQL + pgvector (`supportflow_ai_testing` in `phpunit.xml`). Create that database once:

```bash
psql -U root -h 127.0.0.1 -p 5432 -d postgres -c "CREATE DATABASE supportflow_ai_testing;"
```

Local indexing and retrieval use the same OpenAI embeddings path as production (`text-embedding-3-small`). Set `OPENAI_API_KEY` before seeding or serving the demo. Feature tests call `Embeddings::fake()` and never hit OpenAI.

## Forge

Forge does **not** install pgvector automatically. On the app server (match major version, e.g. 17 or 18):

```bash
sudo apt update
sudo apt install postgresql-18-pgvector
sudo -u postgres psql -d supportflow_ai -c "CREATE EXTENSION IF NOT EXISTS vector;"
```

Then run `php artisan migrate --force`. Migrations fail if the driver is not PostgreSQL or the `vector` extension cannot be created.

GitHub Actions uses `pgvector/pgvector:pg17`.
