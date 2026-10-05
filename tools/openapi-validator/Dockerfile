FROM python:3.12-slim@sha256:02108f5d322dd89f1c9e552442c25acb0543dfdbc455693a5599624f20d9155d

COPY tools/openapi-validator/requirements.txt /tmp/requirements.txt
RUN pip install --no-cache-dir --disable-pip-version-check --requirement /tmp/requirements.txt \
    && rm /tmp/requirements.txt

WORKDIR /workspace

ENTRYPOINT ["openapi-spec-validator"]
