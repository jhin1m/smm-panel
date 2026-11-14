// Livewire đã kèm Alpine — không import Alpine ở đây.

// Sao chép text, báo bằng toast chung của layout.
window.copyText = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Đã sao chép ' + text } }));
    } catch {
        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Không sao chép được, hãy chọn và copy thủ công' } }));
    }
};
