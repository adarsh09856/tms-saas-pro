FROM node:22.22.0-alpine3.22 AS builder

WORKDIR /console
ENV PNPM_HOME=/root/.pnpm
ENV PATH=/root/.pnpm/bin:$PATH
ARG ENVIRONMENT=development

RUN npm install -g ember-cli pnpm && apk add --no-cache git openssh-client
COPY console/package.json console/pnpm-lock.yaml console/pnpm-workspace.yaml ./
COPY packages/ /packages/
COPY patches/upstream/ /patches/upstream/
COPY scripts/apply-upstream-package-patches.mjs /apply-upstream-package-patches.mjs
RUN node /apply-upstream-package-patches.mjs /packages /patches/upstream
RUN pnpm install --frozen-lockfile

COPY console/ ./
COPY packages/fleetops/ /local-packages/fleetops/
COPY packages/dev-engine/ /local-packages/dev-engine/
COPY packages/ember-ui/ /local-packages/ember-ui/
COPY packages/iam-engine/ /local-packages/iam-engine/

# pnpm installs packages as symlinks into its store. Overlay their resolved
# targets so Ember resolves the local addons with the installed dependency graph.
RUN for mapping in \
      '@fleetbase/fleetops-engine:fleetops' \
      '@fleetbase/dev-engine:dev-engine' \
      '@fleetbase/ember-ui:ember-ui' \
      '@fleetbase/iam-engine:iam-engine'; do \
      package="${mapping%%:*}"; source="${mapping#*:}"; \
      target="$(readlink -f "node_modules/$package")"; \
      test -d "$target" || exit 1; \
      cp -a "/local-packages/$source/." "$target/"; \
    done

RUN pnpm build --environment "$ENVIRONMENT"

FROM nginx:alpine
COPY --from=builder /console/dist /usr/share/nginx/html
COPY console/nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 4200
CMD ["nginx", "-g", "daemon off;"]
