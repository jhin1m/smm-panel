# syntax=docker/dockerfile:1
# Image updater: giữ docker.sock, chạy cập nhật theo yêu cầu của admin (smm-updater-loop) hoặc từ host (update.sh -> smm-update).
# RELEASE_REPO được đóng cứng lúc build (workflow release truyền github.repository) và KHÔNG đọc từ .env,
# nên kể cả khi app bị chiếm quyền cũng không đổi được nguồn tải bundle/image.
FROM docker:29-cli

ARG RELEASE_REPO
RUN test -n "$RELEASE_REPO" && printf '%s' "$RELEASE_REPO" | grep -Eq '^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$' \
    || { echo "Cần --build-arg RELEASE_REPO=<owner>/<repo>" >&2; exit 1; }
ENV RELEASE_REPO=${RELEASE_REPO}

RUN apk add --no-cache bash curl jq coreutils tar gzip util-linux \
    && docker compose version

# Cùng bố cục /updates với image app (www-data = uid 33): volume `updates` được khởi tạo từ image nào mount trước,
# mà updater không phụ thuộc postgres nên thường mount trước app. Thiếu dòng này, volume thuộc root và app không ghi được.
RUN mkdir -p /updates/inbox && chown 33:33 /updates/inbox

COPY smm-update smm-updater-loop /usr/local/bin/
RUN chmod 0755 /usr/local/bin/smm-update /usr/local/bin/smm-updater-loop

ENTRYPOINT []
CMD ["smm-updater-loop"]
