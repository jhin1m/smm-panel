<?php

namespace App\Filament\Pages;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Services\ChatService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

/**
 * Hộp chat admin. Đánh dấu đã đọc CHỈ khi chọn thread hoặc focus ô trả lời (tab hiển thị); poll không mark read.
 */
class ChatInbox extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 35;

    protected string $view = 'filament.pages.chat-inbox';

    #[Url(as: 'thread')]
    public ?int $initialThread = null;

    #[Locked]
    public ?int $threadId = null;

    #[Locked]
    public int $lastSeenId = 0;

    public string $body = '';

    public static function getNavigationLabel(): string
    {
        return __('admin.chat.title');
    }

    public function getTitle(): string
    {
        return __('admin.chat.title');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = app(ChatService::class)->unreadThreadsForAdmin();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function mount(): void
    {
        $this->lastSeenId = $this->globalMaxId();

        if ($this->initialThread && ChatThread::whereKey($this->initialThread)->exists()) {
            $this->selectThread($this->initialThread);
        }
    }

    public function selectThread(int $id): void
    {
        $thread = ChatThread::findOrFail($id);
        $this->threadId = $thread->id;
        $this->body = '';
        $this->resetErrorBag();
        app(ChatService::class)->markReadByAdmin($thread->id, (int) $thread->messages()->max('id'));
    }

    /** Alpine gọi khi focus ô trả lời và document.visibilityState === 'visible'. */
    public function replyFocused(): void
    {
        $thread = $this->currentThread();
        if ($thread) {
            app(ChatService::class)->markReadByAdmin($thread->id, (int) $thread->messages()->max('id'));
        }
        $this->skipRender();
    }

    public function reply(ChatService $chat): void
    {
        $thread = $this->currentThread();
        if (! $thread) {
            return;
        }

        $this->validate(['body' => ['required', 'string', 'max:'.ChatService::MAX_LENGTH]], [], ['body' => __('admin.chat.body_attribute')]);

        try {
            $msg = $chat->postAdminMessage($thread->id, auth()->user(), $this->body);
        } catch (InvalidArgumentException $e) {
            $this->addError('body', $e->getMessage());

            return;
        }

        $this->body = '';
        $this->lastSeenId = max($this->lastSeenId, $msg->id);
    }

    /** Poll: chỉ render lại khi có tin mới ở bất kỳ thread nào. Không mark read. */
    public function refreshIfNew(): void
    {
        $max = $this->globalMaxId();

        if ($max === $this->lastSeenId) {
            $this->skipRender();

            return;
        }

        $this->lastSeenId = $max;
    }

    private function currentThread(): ?ChatThread
    {
        return $this->threadId ? ChatThread::find($this->threadId) : null;
    }

    private function globalMaxId(): int
    {
        return (int) DB::table('chat_messages')->max('id');
    }

    protected function getViewData(): array
    {
        $chat = app(ChatService::class);
        $thread = $this->currentThread()?->load('user');

        return [
            'threads' => ChatThread::with('user')->whereNotNull('last_message_at')->orderByDesc('last_message_at')->limit(100)->get(),
            'unreadCounts' => $chat->unreadCountsForAdmin(),
            'lastMessages' => ChatMessage::whereIn('id', ChatThread::whereNotNull('last_message_id')->pluck('last_message_id'))->get()->keyBy('thread_id'),
            'thread' => $thread,
            'messages' => $thread ? $thread->messages()->latest('id')->limit(100)->get()->reverse()->values() : collect(),
        ];
    }
}
