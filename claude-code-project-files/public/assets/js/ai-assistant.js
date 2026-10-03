/**
 * RJM Boardinghouse — Local AI Assistant Client
 *
 * Integrates local Ollama AI (localhost:11434) via PHP backend endpoints.
 * Handles:
 *  - AI Health status check and badge updates
 *  - Boarder: "Improve Description" with AI
 *  - Boarder: "Auto-Detect Category" from description text
 *  - Boarder: Real-time Live Priority Preview (client-side keyword scoring)
 *  - Staff: Per-ticket AI analysis (root cause, action steps, safety warnings)
 *  - Staff: Queue summary generation
 *  - Floating AI Chat Drawer with quick prompt chips & session persistence
 */

(function () {
    'use strict';

    // ────────────────────────────────────────────────────────
    //  State & Config
    // ────────────────────────────────────────────────────────
    let isAiOnline = false;
    let isCheckingStatus = false;

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) return meta.content;
        const hidden = document.querySelector('input[name="csrf_token"]');
        if (hidden && hidden.value) return hidden.value;
        return '';
    }

    async function apiRequest(url, data = {}) {
        const token = getCsrfToken();
        const payload = Object.assign({}, data, { csrf_token: token });

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': token
            },
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        });

        const json = await response.json().catch(() => ({ ok: false, error: 'Invalid JSON response from server.' }));
        return { status: response.status, ok: response.ok && json.ok, data: json };
    }

    // ────────────────────────────────────────────────────────
    //  Ollama Health Status Check
    // ────────────────────────────────────────────────────────
    async function checkAiStatus() {
        if (isCheckingStatus) return;
        isCheckingStatus = true;

        const badges = document.querySelectorAll('.ai-status-badge');
        badges.forEach(b => {
            b.className = 'ai-status-badge ai-status-checking';
            b.innerHTML = '<span class="ai-status-dot"></span> Checking AI...';
        });

        try {
            const res = await fetch('/api/assistant/status', {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            const json = await res.json().catch(() => ({ ok: false, available: false }));

            isAiOnline = !!(json.ok && json.available);
            updateAiStatusUI(isAiOnline);
        } catch (e) {
            isAiOnline = false;
            updateAiStatusUI(false);
        } finally {
            isCheckingStatus = false;
        }
    }

    function updateAiStatusUI(online) {
        const badges = document.querySelectorAll('.ai-status-badge');
        badges.forEach(b => {
            if (online) {
                b.className = 'ai-status-badge ai-status-online';
                b.innerHTML = '<span class="ai-status-dot"></span> Ollama AI Online';
                b.title = 'Local LLM (llama3.2:3b) active and responsive';
            } else {
                b.className = 'ai-status-badge ai-status-offline';
                b.innerHTML = '<span class="ai-status-dot"></span> AI Offline';
                b.title = 'Ollama is offline. Start Ollama locally on port 11434 for AI features.';
            }
        });

        // Toggle AI action buttons
        const aiButtons = document.querySelectorAll('.ai-action-btn, #btn-queue-summary');
        aiButtons.forEach(btn => {
            if (!online) {
                btn.setAttribute('disabled', 'disabled');
                btn.title = 'Ollama is currently offline. AI features are unavailable.';
            } else {
                btn.removeAttribute('disabled');
                btn.title = '';
            }
        });

        // Update chat drawer badge if exists
        const chatStatus = document.getElementById('ai-chat-status-dot');
        if (chatStatus) {
            chatStatus.className = 'ai-status-dot ' + (online ? 'bg-emerald-400' : 'bg-slate-400');
        }
    }

    // ────────────────────────────────────────────────────────
    //  Live Priority Preview — scored by the server, same rules as on submit
    // ────────────────────────────────────────────────────────
    async function fetchPriorityScore(text) {
        const body = new URLSearchParams({
            description: text,
            category: (document.getElementById('category') || {}).value || 'other',
            csrf_token: getCsrfToken()
        });
        const res = await fetch('/api/maintenance/score-preview', {
            method: 'POST', body, credentials: 'same-origin', headers: { 'Accept': 'application/json' }
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    }

    function initLivePriorityPreview() {
        const descTextarea = document.getElementById('description');
        const previewCard = document.getElementById('priority-preview-card');
        if (!descTextarea || !previewCard) return;

        let debounceTimer = null;

        async function updatePreview() {
            let result;
            try {
                result = await fetchPriorityScore(descTextarea.value);
            } catch (e) {
                return; // keep the last preview; the real score is assigned on submit anyway
            }

            const scoreEl = document.getElementById('preview-score-val');
            const tierEl = document.getElementById('preview-tier-badge');
            const keywordsContainer = document.getElementById('preview-keywords-list');

            previewCard.className = 'priority-preview-card tier-' + result.tier;

            if (scoreEl) scoreEl.textContent = result.score + '/100';

            if (tierEl) {
                tierEl.textContent = result.tier.toUpperCase();
                tierEl.className = 'badge uppercase font-bold text-[11px] ' +
                    (result.tier === 'critical' ? 'badge-error' :
                     result.tier === 'high' ? 'badge-warning' :
                     result.tier === 'medium' ? 'badge-info' : 'badge-neutral');
            }

            if (keywordsContainer) {
                if (result.matches.length === 0) {
                    keywordsContainer.innerHTML = '<span class="text-neutral-400 italic text-caption">No high-urgency keywords detected yet.</span>';
                } else {
                    keywordsContainer.innerHTML = result.matches.map(m =>
                        `<span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full font-mono font-medium ${
                            m.weight >= 35 ? 'bg-red-100 text-red-700 border border-red-200' :
                            m.weight >= 20 ? 'bg-amber-100 text-amber-700 border border-amber-200' :
                            'bg-blue-100 text-blue-700 border border-blue-200'
                        }"><span>${escapeHtml(m.keyword)}</span><span class="text-[10px] opacity-75">+${m.weight}</span></span>`
                    ).join(' ');
                }
            }
        }

        descTextarea.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(updatePreview, 250);
        });

        const categorySelect = document.getElementById('category');
        if (categorySelect) categorySelect.addEventListener('change', updatePreview);

        // Initial run
        updatePreview();
    }

    // ────────────────────────────────────────────────────────
    //  Boarder: Description Improvement
    // ────────────────────────────────────────────────────────
    function initImproveDescription() {
        const btn = document.getElementById('btn-improve-desc');
        const descTextarea = document.getElementById('description');
        const categorySelect = document.getElementById('category');
        const previewModal = document.getElementById('ai-improve-modal');

        if (!btn || !descTextarea) return;

        btn.addEventListener('click', async function () {
            const draft = descTextarea.value.trim();
            if (!draft) {
                alert('Please type a brief description first before asking AI to improve it.');
                descTextarea.focus();
                return;
            }

            const category = categorySelect ? categorySelect.value : 'other';

            const origHtml = btn.innerHTML;
            btn.classList.add('ai-loading');
            btn.innerHTML = '<span class="inline-block animate-spin mr-1">⏳</span> AI is polishing...';
            btn.setAttribute('disabled', 'disabled');

            // Show a progress hint if taking > 3.5s
            const slowHintTimer = setTimeout(() => {
                if (btn.classList.contains('ai-loading')) {
                    btn.innerHTML = '<span class="inline-block animate-spin mr-1">⏳</span> Waking up AI...';
                }
            }, 3500);

            try {
                const res = await apiRequest('/api/assistant/suggest-description', {
                    draft: draft,
                    category: category
                });

                clearTimeout(slowHintTimer);

                if (!res.ok) {
                    alert(res.data.error || 'Failed to improve description. Ollama might be busy or offline.');
                    return;
                }

                const improved = res.data.reply;
                showImprovementModal(draft, improved, descTextarea);
            } catch (e) {
                alert('Error connecting to AI service.');
            } finally {
                clearTimeout(slowHintTimer);
                btn.classList.remove('ai-loading');
                btn.innerHTML = origHtml;
                if (isAiOnline) btn.removeAttribute('disabled');
            }
        });
    }

    function showImprovementModal(original, improved, targetTextarea) {
        let modal = document.getElementById('ai-improve-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'ai-improve-modal';
            modal.style.cssText = 'position:fixed;inset:0;background:rgba(10, 10, 10,0.6);z-index:9999;display:flex;align-items:center;justify-content:center;padding:1rem;backdrop-filter:blur(3px);';
            document.body.appendChild(modal);
        }

        modal.innerHTML = `
            <div style="background:#ffffff;border-radius:1rem;max-width:560px;width:100%;box-shadow:0 20px 40px rgba(0,0,0,0.25);overflow:hidden;border:1px solid #e6e5e2;animation:popIn 0.2s ease-out;">
                <div style="background:linear-gradient(135deg,#0a0a0a,#312e81);color:white;padding:1rem 1.25rem;display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:0.5rem;font-weight:700;font-size:0.95rem;">
                        <span>✨</span> AI-Improved Description
                    </div>
                    <button id="modal-close-btn" style="background:transparent;border:none;color:white;cursor:pointer;font-size:1.25rem;line-height:1;">&times;</button>
                </div>
                <div style="padding:1.25rem;display:flex;flex-direction:column;gap:1rem;">
                    <div>
                        <div style="font-size:0.75rem;font-weight:700;color:#6b6b6b;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.35rem;">Original Draft:</div>
                        <div style="font-size:0.8125rem;color:#555452;background:#f8f7f5;padding:0.75rem;border-radius:0.5rem;border:1px solid #e6e5e2;line-height:1.5;">${escapeHtml(original)}</div>
                    </div>
                    <div>
                        <div style="font-size:0.75rem;font-weight:700;color:#b15f2c;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.35rem;">AI Recommendation:</div>
                        <div id="ai-improved-text-val" style="font-size:0.875rem;color:#0a0a0a;background:#f5f3ff;padding:0.875rem;border-radius:0.5rem;border:1.5px solid #c4b5fd;line-height:1.55;font-weight:500;">${escapeHtml(improved)}</div>
                    </div>
                    <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:0.5rem;">
                        <button id="modal-discard-btn" style="padding:0.5rem 1rem;font-size:0.8125rem;font-weight:600;border:1px solid #d4d2ce;background:#ffffff;color:#555452;border-radius:0.5rem;cursor:pointer;">Keep Original</button>
                        <button id="modal-apply-btn" style="padding:0.5rem 1.25rem;font-size:0.8125rem;font-weight:600;border:none;background:#b15f2c;color:#ffffff;border-radius:0.5rem;cursor:pointer;box-shadow:0 2px 6px rgba(109,40,217,0.3);">Apply Improved Text</button>
                    </div>
                </div>
            </div>
        `;

        modal.style.display = 'flex';

        const close = () => { modal.style.display = 'none'; };
        modal.querySelector('#modal-close-btn').onclick = close;
        modal.querySelector('#modal-discard-btn').onclick = close;
        modal.querySelector('#modal-apply-btn').onclick = () => {
            targetTextarea.value = improved;
            // Trigger input event to refresh preview
            targetTextarea.dispatchEvent(new Event('input', { bubbles: true }));
            close();
        };
    }

    // ────────────────────────────────────────────────────────
    //  Boarder: Auto-Detect Category
    // ────────────────────────────────────────────────────────
    function initAutoCategory() {
        const btn = document.getElementById('btn-auto-category');
        const descTextarea = document.getElementById('description');
        const categorySelect = document.getElementById('category');

        if (!btn || !descTextarea || !categorySelect) return;

        btn.addEventListener('click', async function () {
            const desc = descTextarea.value.trim();
            if (!desc) {
                alert('Please enter a description first so AI can analyze the problem category.');
                descTextarea.focus();
                return;
            }

            const origHtml = btn.innerHTML;
            btn.classList.add('ai-loading');
            btn.innerHTML = '<span class="inline-block animate-spin mr-1">⏳</span> Classifying...';
            btn.setAttribute('disabled', 'disabled');

            try {
                const res = await apiRequest('/api/assistant/suggest-category', {
                    description: desc
                });

                if (!res.ok) {
                    alert(res.data.error || 'Failed to detect category.');
                    return;
                }

                const category = res.data.reply;
                let found = false;
                for (let i = 0; i < categorySelect.options.length; i++) {
                    if (categorySelect.options[i].value === category) {
                        categorySelect.selectedIndex = i;
                        found = true;
                        break;
                    }
                }

                if (found) {
                    showToast('🏷️ Category set to: ' + category.toUpperCase(), 'success');
                } else {
                    showToast('Suggested category: ' + category, 'info');
                }
            } catch (e) {
                alert('Error connecting to AI service.');
            } finally {
                btn.classList.remove('ai-loading');
                btn.innerHTML = origHtml;
                if (isAiOnline) btn.removeAttribute('disabled');
            }
        });
    }

    // ────────────────────────────────────────────────────────
    //  Staff: Per-Ticket AI Analysis
    // ────────────────────────────────────────────────────────
    function initStaffTicketAnalysis() {
        const buttons = document.querySelectorAll('.btn-analyze-ticket');
        if (!buttons.length) return;

        buttons.forEach(btn => {
            btn.addEventListener('click', async function () {
                const ticketId = this.dataset.ticketId;
                if (!ticketId) return;

                const targetRow = document.getElementById('analysis-row-' + ticketId);
                const targetBox = document.getElementById('analysis-box-' + ticketId);

                if (!targetRow || !targetBox) return;

                // Toggle if already visible and populated
                if (!targetRow.classList.contains('hidden') && targetBox.dataset.loaded === 'true') {
                    targetRow.classList.add('hidden');
                    this.innerHTML = '🤖 AI Analyze';
                    return;
                }

                // Check cache in sessionStorage
                const cached = sessionStorage.getItem('ticket_analysis_' + ticketId);
                if (cached) {
                    renderAnalysis(targetBox, cached);
                    targetRow.classList.remove('hidden');
                    this.innerHTML = '▲ Hide AI Analysis';
                    return;
                }

                const origHtml = this.innerHTML;
                this.innerHTML = '<span class="inline-block animate-spin mr-1">⏳</span> Analyzing...';
                this.setAttribute('disabled', 'disabled');
                targetRow.classList.remove('hidden');
                targetBox.innerHTML = `
                    <div class="flex items-center gap-2 text-indigo-700 font-semibold py-2">
                        <span class="inline-block animate-spin">⏳</span>
                        <span>Ollama LLM is evaluating ticket #${ticketId} (identifying root cause & tools)...</span>
                    </div>
                `;

                try {
                    const res = await apiRequest('/api/assistant/analyze-ticket', {
                        ticket_id: ticketId
                    });

                    if (!res.ok) {
                        targetBox.innerHTML = `<div class="text-red-600 font-semibold py-2">⚠ ${escapeHtml(res.data.error || 'Failed to analyze ticket.')}</div>`;
                        return;
                    }

                    const analysisText = res.data.reply;
                    sessionStorage.setItem('ticket_analysis_' + ticketId, analysisText);
                    renderAnalysis(targetBox, analysisText);
                    this.innerHTML = '▲ Hide AI Analysis';
                } catch (e) {
                    targetBox.innerHTML = '<div class="text-red-600 font-semibold py-2">⚠ Error communicating with AI service.</div>';
                } finally {
                    this.removeAttribute('disabled');
                }
            });
        });
    }

    function renderAnalysis(container, text) {
        container.dataset.loaded = 'true';
        // Parse simple markdown-like lines for clean presentation
        const html = text
            .split('\n')
            .filter(line => line.trim() !== '')
            .map(line => {
                if (line.startsWith('#') || line.startsWith('**') && line.endsWith('**')) {
                    return `<div class="font-bold text-neutral-900 mt-2 text-xs uppercase tracking-wider">${escapeHtml(line.replace(/[*#]/g, ''))}</div>`;
                }
                if (line.startsWith('- ') || line.startsWith('* ')) {
                    return `<div class="flex items-start gap-1.5 ml-2 mt-1 text-neutral-700"><span class="text-indigo-600 font-bold">•</span><span>${escapeHtml(line.substring(2))}</span></div>`;
                }
                return `<p class="mt-1 text-neutral-800 leading-relaxed">${escapeHtml(line)}</p>`;
            })
            .join('');

        container.innerHTML = `
            <div class="space-y-1.5">
                <div class="flex items-center justify-between border-b border-indigo-100 pb-1.5 mb-2">
                    <span class="font-bold text-indigo-900 text-xs flex items-center gap-1.5">
                        <span>🤖</span> AI Diagnostic &amp; Resolution Assessment
                    </span>
                    <span class="text-[10px] text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded font-mono font-semibold">Local LLM</span>
                </div>
                ${html}
            </div>
        `;
    }

    // ────────────────────────────────────────────────────────
    //  Staff: Queue Summary
    // ────────────────────────────────────────────────────────
    function initStaffQueueSummary() {
        const btn = document.getElementById('btn-queue-summary');
        const container = document.getElementById('queue-summary-container');
        const contentBox = document.getElementById('queue-summary-content');

        if (!btn || !container || !contentBox) return;

        btn.addEventListener('click', async function () {
            if (!container.classList.contains('hidden') && contentBox.dataset.loaded === 'true') {
                container.classList.add('hidden');
                btn.innerHTML = '📊 AI Queue Summary';
                return;
            }

            // Check cache
            const cached = sessionStorage.getItem('queue_ai_summary');
            if (cached) {
                renderSummary(contentBox, cached);
                container.classList.remove('hidden');
                btn.innerHTML = '▲ Close Summary';
                return;
            }

            const origHtml = btn.innerHTML;
            btn.innerHTML = '<span class="inline-block animate-spin mr-1">⏳</span> Synthesizing Queue...';
            btn.setAttribute('disabled', 'disabled');
            container.classList.remove('hidden');
            contentBox.innerHTML = `
                <div class="flex items-center gap-2 text-indigo-700 font-semibold py-3">
                    <span class="inline-block animate-spin">⏳</span>
                    <span>Analyzing all active maintenance requests across dormitory buildings...</span>
                </div>
            `;

            try {
                const res = await apiRequest('/api/assistant/summarize-queue', {});

                if (!res.ok) {
                    contentBox.innerHTML = `<div class="text-red-600 font-semibold py-2">⚠ ${escapeHtml(res.data.error || 'Failed to generate queue summary.')}</div>`;
                    return;
                }

                const summaryText = res.data.reply;
                sessionStorage.setItem('queue_ai_summary', summaryText);
                renderSummary(contentBox, summaryText);
                btn.innerHTML = '▲ Close Summary';
            } catch (e) {
                contentBox.innerHTML = '<div class="text-red-600 font-semibold py-2">⚠ Error generating AI summary.</div>';
            } finally {
                btn.removeAttribute('disabled');
            }
        });
    }

    function renderSummary(box, text) {
        box.dataset.loaded = 'true';
        box.innerHTML = `
            <div class="prose prose-sm text-neutral-800 leading-relaxed space-y-2">
                ${text.split('\n\n').map(p => `<p>${escapeHtml(p)}</p>`).join('')}
            </div>
            <div class="mt-3 pt-2 border-t border-indigo-100 flex justify-end">
                <button id="btn-refresh-summary" class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold flex items-center gap-1 cursor-pointer">
                    🔄 Re-analyze Current Queue
                </button>
            </div>
        `;

        const refreshBtn = box.querySelector('#btn-refresh-summary');
        if (refreshBtn) {
            refreshBtn.onclick = function () {
                sessionStorage.removeItem('queue_ai_summary');
                const btn = document.getElementById('btn-queue-summary');
                if (btn) btn.click();
            };
        }
    }

    // ────────────────────────────────────────────────────────
    //  Interactive AI Chat Drawer
    // ────────────────────────────────────────────────────────
    function initChatDrawer() {
        // Only inject if user is logged in
        const userRole = document.body.dataset.userRole;
        if (!userRole) return;

        // Create FAB if not already present
        if (!document.getElementById('ai-chat-fab')) {
            const fab = document.createElement('button');
            fab.id = 'ai-chat-fab';
            fab.type = 'button';
            fab.innerHTML = `
                <span style="font-size: 1.1rem;">💬</span>
                <span>Ask AI</span>
                <span id="ai-chat-status-dot" class="ai-status-dot ${isAiOnline ? 'bg-emerald-400' : 'bg-slate-400'}"></span>
            `;
            document.body.appendChild(fab);

            fab.addEventListener('click', toggleChatWindow);
        }

        // Create Chat Window if not present
        if (!document.getElementById('ai-chat-window')) {
            const win = document.createElement('div');
            win.id = 'ai-chat-window';
            win.className = 'hidden';

            const quickPrompts = userRole === 'boarder' ? [
                'How do I submit a maintenance request?',
                'How do I pay my rent?',
                'What are the dorm rules and penalties?',
                'How do I use the SOS alert feature?'
            ] : userRole === 'admin' ? [
                'How do I manage rooms and beds?',
                'How do I view boarder payments?',
                'How do penalty rules work?',
                'How do I check occupancy reports?'
            ] : [
                'How do I handle maintenance requests?',
                'How do I log a boarder incident?',
                'What maintenance requests are pending?',
                'How do I resolve a maintenance request?'
            ];

            win.innerHTML = `
                <div class="ai-chat-header">
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <span style="font-size:1.15rem;">🤖</span>
                        <div>
                            <div style="font-weight:700;font-size:0.875rem;line-height:1.2;">RJM AI Assistant</div>
                            <div style="font-size:0.65rem;color:#d4d2ce;">Powered by local llama3.2:3b</div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <button id="ai-chat-clear" title="Clear chat history" style="background:transparent;border:none;color:#9d9b97;cursor:pointer;font-size:0.75rem;">Clear</button>
                        <button id="ai-chat-close" style="background:transparent;border:none;color:white;cursor:pointer;font-size:1.25rem;line-height:1;">&times;</button>
                    </div>
                </div>
                <div id="ai-chat-messages" class="ai-chat-messages">
                    <div class="ai-bubble ai-bubble-assistant">
                        Hello ${userRole === 'boarder' ? 'resident' : userRole === 'admin' ? 'Admin' : 'staff member'}! I am the local RJM Assistant. How can I help you with maintenance, dorm rules, or facilities today?
                    </div>
                </div>
                <div class="ai-chat-input-area">
                    <div class="ai-quick-chips">
                        ${quickPrompts.map(p => `<button type="button" class="ai-quick-chip" data-prompt="${escapeHtml(p)}">${escapeHtml(p)}</button>`).join('')}
                    </div>
                    <form id="ai-chat-form" style="display:flex;gap:0.35rem;margin-top:0.35rem;">
                        <input id="ai-chat-input" type="text" placeholder="Type a question..." class="input w-full text-xs" style="padding:0.45rem 0.65rem;border-radius:0.5rem;" autocomplete="off" />
                        <button id="ai-chat-send" type="submit" class="btn btn-primary !px-3 !py-1 !text-xs font-semibold" style="border-radius:0.5rem;">Send</button>
                    </form>
                </div>
            `;
            document.body.appendChild(win);

            // Wire chat event listeners
            win.querySelector('#ai-chat-close').onclick = toggleChatWindow;
            win.querySelector('#ai-chat-clear').onclick = () => {
                sessionStorage.removeItem('ai_chat_history');
                const msgBox = win.querySelector('#ai-chat-messages');
                msgBox.innerHTML = `
                    <div class="ai-bubble ai-bubble-assistant">
                        Chat cleared. How can I help you?
                    </div>
                `;
            };

            win.querySelectorAll('.ai-quick-chip').forEach(chip => {
                chip.onclick = function () {
                    const prompt = this.dataset.prompt;
                    const input = win.querySelector('#ai-chat-input');
                    input.value = prompt;
                    win.querySelector('#ai-chat-form').dispatchEvent(new Event('submit'));
                };
            });

            const form = win.querySelector('#ai-chat-form');
            form.onsubmit = async function (e) {
                e.preventDefault();
                const input = win.querySelector('#ai-chat-input');
                const text = input.value.trim();
                if (!text) return;

                input.value = '';
                appendChatMessage('user', text);

                const thinkingBubble = appendChatMessage('assistant', 'Thinking...', true);

                // Progress update if taking long
                const wakingTimer = setTimeout(() => {
                    if (thinkingBubble && thinkingBubble.dataset.thinking === 'true') {
                        thinkingBubble.innerHTML = '<span class="inline-block animate-spin mr-1">⏳</span> Waking up local AI model (first inference can take ~10s)...';
                    }
                }, 3500);

                try {
                    const res = await apiRequest('/api/assistant/chat', { message: text });
                    clearTimeout(wakingTimer);

                    if (!res.ok) {
                        thinkingBubble.innerHTML = `<span class="text-red-500">⚠ ${escapeHtml(res.data.error || 'AI is currently offline or unreachable.')}</span>`;
                        thinkingBubble.dataset.thinking = 'false';
                        return;
                    }

                    thinkingBubble.innerHTML = escapeHtml(res.data.reply).replace(/\n/g, '<br>');
                    thinkingBubble.dataset.thinking = 'false';
                    saveChatHistory();
                } catch (err) {
                    clearTimeout(wakingTimer);
                    thinkingBubble.innerHTML = '<span class="text-red-500">⚠ Connection failed. Ollama may be stopped.</span>';
                    thinkingBubble.dataset.thinking = 'false';
                }
            };

            // Restore chat history from sessionStorage
            restoreChatHistory();
        }
    }

    function toggleChatWindow() {
        const win = document.getElementById('ai-chat-window');
        if (!win) return;
        win.classList.toggle('hidden');
        if (!win.classList.contains('hidden')) {
            const input = win.querySelector('#ai-chat-input');
            if (input) setTimeout(() => input.focus(), 100);
        }
    }

    function appendChatMessage(sender, text, isThinking = false) {
        const msgBox = document.getElementById('ai-chat-messages');
        if (!msgBox) return null;

        const bubble = document.createElement('div');
        bubble.className = 'ai-bubble ai-bubble-' + sender;
        if (isThinking) {
            bubble.dataset.thinking = 'true';
            bubble.innerHTML = '<span class="inline-block animate-spin mr-1">⏳</span> ' + escapeHtml(text);
        } else {
            bubble.innerHTML = escapeHtml(text).replace(/\n/g, '<br>');
        }

        msgBox.appendChild(bubble);
        msgBox.scrollTop = msgBox.scrollHeight;
        return bubble;
    }

    function saveChatHistory() {
        const msgBox = document.getElementById('ai-chat-messages');
        if (!msgBox) return;
        const messages = [];
        msgBox.querySelectorAll('.ai-bubble').forEach(b => {
            if (b.dataset.thinking === 'true') return;
            const isUser = b.classList.contains('ai-bubble-user');
            messages.push({ sender: isUser ? 'user' : 'assistant', html: b.innerHTML });
        });
        sessionStorage.setItem('ai_chat_history', JSON.stringify(messages));
    }

    function restoreChatHistory() {
        const raw = sessionStorage.getItem('ai_chat_history');
        if (!raw) return;
        try {
            const messages = JSON.parse(raw);
            if (!Array.isArray(messages) || !messages.length) return;
            const msgBox = document.getElementById('ai-chat-messages');
            if (!msgBox) return;
            msgBox.innerHTML = '';
            messages.forEach(m => {
                const bubble = document.createElement('div');
                bubble.className = 'ai-bubble ai-bubble-' + m.sender;
                bubble.innerHTML = m.html;
                msgBox.appendChild(bubble);
            });
            msgBox.scrollTop = msgBox.scrollHeight;
        } catch (e) {}
    }

    // ────────────────────────────────────────────────────────
    //  Helper Utilities
    // ────────────────────────────────────────────────────────
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            background: ${type === 'success' ? '#245a3f' : '#0a0a0a'};
            color: #ffffff;
            font-size: 0.8125rem;
            font-weight: 600;
            padding: 0.6rem 1.2rem;
            border-radius: 9999px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: popIn 0.2s ease-out;
        `;
        toast.innerHTML = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // ────────────────────────────────────────────────────────
    //  Initialization
    // ────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        checkAiStatus();
        initLivePriorityPreview();
        initImproveDescription();
        initAutoCategory();
        initStaffTicketAnalysis();
        initStaffQueueSummary();
        initChatDrawer();
    });

    // Expose AiAssistant globally for inspection or page-specific triggers
    window.AiAssistant = {
        checkStatus: checkAiStatus,
        isOnline: () => isAiOnline,
        calculatePriority: fetchPriorityScore
    };
})();
