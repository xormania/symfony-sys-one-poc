# CLM runtime and reproduction

The PHP application needs an HTTP server implementing `POST /v1/systemone`. CLM's reference runtime has two processes: a Qwen3-8B pooling encoder and the CLM API/projection heads. The encoder is the large memory consumer.

The commands below follow the [CLM reference implementation](https://github.com/Contrastive-LM/CLM) inspected at `bb42c6c5bf914fd449bed2f6ca65be80602cb1f7`. They are a setup recipe, **not a GPU deployment validated by this PoC**. Use an isolated Python environment. Establish sufficient GPU memory before starting the unquantized encoder; the original Qwen weights alone are about 16.4 GB.

```bash
git clone https://github.com/Contrastive-LM/CLM.git
cd CLM
git checkout bb42c6c5bf914fd449bed2f6ca65be80602cb1f7
python3 -m venv .venv
. .venv/bin/activate
pip install -e .

# Terminal 1: encoder
vllm serve Qwen/Qwen3-8B \
  --served-model-name qwen3-8b \
  --runner pooling \
  --max-model-len 2048 \
  --host 127.0.0.1 \
  --port 8090

# Terminal 2, same virtual environment: CLM API
clm-serve --port 8700 --emb-url http://127.0.0.1:8090/v1/embeddings --max-tokens 2048
```

The reference CLM server may bind beyond loopback. Run it on a trusted local network or behind appropriate host/container networking, and set `CLM_API_KEY` on both server and client when authentication is needed. The PHP client's default destination is loopback. No cloud resources are created by this repository.

For a shareable experiment, capture:

- CLM source revision;
- the actual projection-head file SHA-256 and model revision;
- Qwen encoder revision, tokenizer/pooling recipe, and precision;
- installed Python, PyTorch and vLLM versions;
- GPU model and available memory;
- both context limits and truncation behavior;
- whether candidate/state caches were cleared or already populated.

Pass that provenance through `--runtime-label` or accompany the JSON report with the deployment manifest. `clm-latest` alone does not identify the head bytes. Save the resolved environment (`pip freeze`) and explicitly pin model revisions for repeated experiments. A change in quantization or pooling is an experimental variable and must be evaluated, not assumed equivalent.

The first eight fixtures are short and use no images. They do not test context truncation, a large candidate registry, fine-tuning, or the published agentic coding benchmarks. CLM's coding benchmark results use fine-tuned heads; the default released head's behavior must be measured independently.

Once the server is running:

```bash
# From the Symfony project
php bin/console app:system-one:run --provider=clm \
  --case=typed.calm --case=typed.angry \
  --output=var/reports/clm-state-sensitivity.json
```

A behavioral failure returns exit code 1 and still writes the full report. A connection, provider, or protocol failure returns 2. Preserve negative results; the purpose is to discover which layer needs work.
