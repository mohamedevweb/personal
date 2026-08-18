# syntax=docker/dockerfile:1
#
# Nuxt builds to a self-contained Nitro server, so the runtime image carries the
# build output and production dependencies only — no source, no dev toolchain.

# ---------------------------------------------------------------- dependencies
FROM node:22-alpine AS deps

WORKDIR /app

COPY package.json package-lock.json ./

# `nuxt prepare` (the postinstall script) needs the project files, which are not
# copied yet, so it is skipped here and run in the build stage instead.
RUN --mount=type=cache,target=/root/.npm \
    npm ci --ignore-scripts

# ----------------------------------------------------------------------- build
FROM node:22-alpine AS build

WORKDIR /app

COPY --from=deps /app/node_modules ./node_modules
COPY . .

RUN npm run postinstall && npm run build

# --------------------------------------------------------------------- runtime
FROM node:22-alpine AS runtime

ENV NODE_ENV=production \
    HOST=0.0.0.0 \
    PORT=3000

WORKDIR /app

COPY --from=build --chown=node:node /app/.output ./.output

USER node

EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD node -e "fetch('http://127.0.0.1:3000/').then(r => process.exit(r.ok ? 0 : 1)).catch(() => process.exit(1))"

CMD ["node", ".output/server/index.mjs"]
