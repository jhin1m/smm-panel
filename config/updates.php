<?php

/*
| Cập nhật qua web. Repo phát hành KHÔNG đọc từ .env: app chỉ gửi chuỗi version cho updater, còn
| nguồn tải bundle/image do image updater (RELEASE_REPO lúc build) và compose.yml quyết định.
| Repo ở đây chỉ dùng để hiển thị bản mới nhất + changelog.
*/
return [
    // Thư mục chung với container updater (volume `updates`): updater ghi status.json, heartbeat, history.jsonl ở gốc
    // (thuộc root); app chỉ ghi inbox/request.json.
    'path' => '/updates',

    'repo' => 'jhin1m/smm-panel',

    // Cho phép hiện/cập nhật lên bản -rc.N. Updater đọc cùng biến này và tự kiểm lại.
    'allow_prerelease' => (bool) env('UPDATER_ALLOW_PRERELEASE', false),

    // Heartbeat cũ hơn ngưỡng này (giây) = updater đã chết; bản đang "running" coi như failed (stale).
    'stale_after' => 60,

    // Cache thông tin bản mới nhất (giây) để không chạm giới hạn 60 req/giờ của GitHub API.
    'release_cache_ttl' => 3600,
];
