<?php

namespace App\Livewire;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Services\ChatService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Nút chat nổi trên trang khách. Thread LUÔN suy từ auth()->user() — không nhận thread id từ client.
 * Poll: mở 5s, đóng 20s (chỉ badge), chỉ khi tab hiển thị; không có tin mới → skipRender.
 */
class ChatWidget extends Component
{
    public bool $open = false;

    #[Locked]
    public int $lastSeenId = 0;

    public string $body = '';

    public function mount(): void
    {
        $this->lastSeenId = $this->maxMessageId();
    }

    public function toggle(ChatService $chat): void
    {
        $this->open = ! $this->open;

        if ($this->open && $this->lastSeenId > 0) {
            $chat->markReadByCustomer(auth()->user(), $this->lastSeenId);
        }
    }

    public function send(ChatService $chat): void
    {
        $user = auth()->user();
        $key = 'chat-send:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('body', __('chat.too_fast', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }

        $this->validate(['body' => ['required', 'string', 'max:'.ChatService::MAX_LENGTH]]);

        try {
            $msg = $chat->postCustomerMessage($user, $this->body);
        } catch (InvalidArgumentException $e) {
            $this->addError('body', $e->getMessage());

            return;
        }

        RateLimiter::hit($key, 60);
        $this->body = '';
        $this->lastSeenId = $msg->id;
        $chat->markReadByCustomer($user, $msg->id);
    }

    public function refreshIfNew(ChatService $chat): void
    {
        $max = $this->maxMessageId();

        if ($max === $this->lastSeenId) {
            $this->skipRender();

            return;
        }

        $this->lastSeenId = $max;

        if ($this->open) {
            $chat->markReadByCustomer(auth()->user(), $max);
        }
    }

    /** Thread của chính user đăng nhập (truy vấn mới, không dùng quan hệ đã cache). */
    private function thread(): ?ChatThread
    {
        return ChatThread::where('user_id', auth()->id())->first();
    }

    private function maxMessageId(): int
    {
        return (int) $this->thread()?->messages()->max('id');
    }

    /** @return Collection<int, ChatMessage> */
    private function recentMessages(): Collection
    {
        $thread = $this->thread();

        return $thread ? $thread->messages()->latest('id')->limit(50)->get()->reverse()->values() : collect();
    }

    public function render(ChatService $chat)
    {
        return view('livewire.chat-widget', [
            'messages' => $this->open ? $this->recentMessages() : collect(),
            'unread' => $this->open ? 0 : $chat->unreadCountForCustomer(auth()->user()),
        ]);
    }
}
