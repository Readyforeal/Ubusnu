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
        // Typewriter state: incoming tokens land in pendingChars and get
        // pumped into liveAssistant a few chars at a time so the user sees
        // smooth character flow even when the server delivers in bursts.
        pendingChars: '',
        typewriterTimer: null,
        init() {
            this.scrollToBottom();
        },
        scrollToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.messages;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },
        startTypewriter() {
            if (this.typewriterTimer) return;
            this.typewriterTimer = setInterval(() => {
                if (this.pendingChars.length === 0) return;
                // Adaptive: emit ~3% of the backlog per tick, min 1 char.
                // Keeps cadence smooth for slow streams and bounded latency
                // for big bursts.
                const chunk = Math.max(1, Math.floor(this.pendingChars.length / 30));
                this.liveAssistant += this.pendingChars.slice(0, chunk);
                this.pendingChars = this.pendingChars.slice(chunk);
                this.scrollToBottom();
            }, 18);
        },
        stopTypewriter() {
            if (this.typewriterTimer) {
                clearInterval(this.typewriterTimer);
                this.typewriterTimer = null;
            }
        },
        async drainTypewriter() {
            while (this.pendingChars.length > 0) {
                await new Promise((r) => setTimeout(r, 30));
            }
            this.stopTypewriter();
        },
        async send() {
            if (!this.text.trim() || this.sending) return;
            this.sending = true;
            const messageText = this.text;
            this.optimisticUserMessages.push({ id: Date.now(), text: messageText });
            // Track whether we created the thread this turn so we can
            // notify the parent AFTER streaming completes. Notifying
            // immediately would trigger a Livewire re-render that changes
            // the child's :key and re-mounts this Alpine instance mid-
            // stream, orphaning the fetch reader and discarding tokens.
            let createdNewThread = false;
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
                    createdNewThread = true;
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
                                this.pendingChars += event.content;
                                this.startTypewriter();
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

                // Drain any buffered typewriter characters so the user sees
                // the full reply before we clear the optimistic state.
                await this.drainTypewriter();

                // Refresh from the DB BEFORE clearing optimistic state so
                // the canonical messages land in the DOM in the same morph
                // — no blank flicker between Alpine clearing and Livewire
                // catching up.
                if (createdNewThread) {
                    // Tells the parent index to set $threadId. The child has
                    // a stable :key and Reactive threadId, so this is a
                    // prop update, NOT a re-mount.
                    this.$dispatch('thread-created', { id: this.threadId });
                    // Wait a couple frames for the Livewire round-trip + morph.
                    await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
                    await new Promise((r) => setTimeout(r, 50));
                } else if (typeof this.$wire !== 'undefined') {
                    await this.$wire.refreshMessages();
                }

                this.optimisticUserMessages = [];
                this.liveAssistant = '';
                this.scrollToBottom();
            }
        },
    }));
});
