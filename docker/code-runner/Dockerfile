FROM node:20-bookworm-slim

RUN apt-get update \
    && apt-get install -y --no-install-recommends php-cli python3 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY server.js /app/server.js

EXPOSE 9001
CMD ["node", "/app/server.js"]
