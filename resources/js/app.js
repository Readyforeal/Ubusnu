import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;
window.Chart = ApexCharts; // MaryUI's <x-chart> references the global as `Chart`

// Re-apply theme after Livewire SPA navigation
document.addEventListener('livewire:navigated', () => {
    const theme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', theme);
});

// Alpine component for the chat thread. Registered here (rather than via
// @script in the SFC) so it's on Alpine's registry before x-data evaluates
// — otherwise Alpine walks the DOM first and you get
// "sending is not defined" / "text is not defined" errors.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('chatThread', (initialThreadId, initialPrompt) => ({
        threadId: initialThreadId,
        text: initialPrompt || '',
        sending: false,
        liveAssistant: '',
        liveToolCalls: [],
        optimisticUserMessages: [],
        init() {
            this.scrollToBottom();
        },
        scrollToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.messages;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },
        async send() {
            if (!this.text.trim() || this.sending) return;
            this.sending = true;
            const messageText = this.text;
            this.optimisticUserMessages.push({ id: Date.now(), text: messageText });
            try {
                this.text = '';
                this.liveAssistant = '';
                this.liveToolCalls = [];
                this.scrollToBottom();

                const csrf = document.querySelector('meta[name="csrf-token"]').content;

                if (!this.threadId) {
                    const r = await fetch('/chat/threads', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                    });
                    if (!r.ok) throw new Error('thread create failed: ' + r.status);
                    const data = await r.json();
                    if (!data || !data.id) throw new Error('thread create returned no id');
                    this.threadId = data.id;
                    this.$dispatch('thread-created', { id: this.threadId });
                }

                const resp = await fetch('/chat/' + this.threadId + '/stream', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ message: messageText }),
                });
                if (!resp.ok) throw new Error('stream failed: ' + resp.status);

                const reader = resp.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                while (true) {
                    const { value, done } = await reader.read();
                    if (done) break;
                    buffer += decoder.decode(value, { stream: true });
                    let nl;
                    while ((nl = buffer.indexOf('\n')) !== -1) {
                        const line = buffer.slice(0, nl).trim();
                        buffer = buffer.slice(nl + 1);
                        if (!line) continue;
                        try {
                            const event = JSON.parse(line);
                            if (event.type === 'token' && event.content) {
                                this.liveAssistant += event.content;
                                this.scrollToBottom();
                            } else if (event.type === 'tool_call') {
                                this.liveToolCalls.push({ id: this.liveToolCalls.length, name: event.tool_name });
                            }
                        } catch (e) {
                            // Ignore non-JSON lines in the NDJSON stream.
                        }
                    }
                }
            } catch (err) {
                console.error('chat send failed:', err);
                this.liveAssistant = '(Error: ' + (err.message || 'request failed') + ')';
            } finally {
                this.sending = false;
                this.liveToolCalls = [];
                this.optimisticUserMessages = [];
                // $wire is provided by Livewire on the surrounding element.
                if (typeof this.$wire !== 'undefined') {
                    this.$wire.refreshMessages();
                }
                this.scrollToBottom();
            }
        },
    }));
});
