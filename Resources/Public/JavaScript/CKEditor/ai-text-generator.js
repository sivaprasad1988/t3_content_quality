import { Plugin } from '@ckeditor/ckeditor5-core';
import { ButtonView } from '@ckeditor/ckeditor5-ui';

const ROUTE_PATH = TYPO3?.settings?.ajaxUrls?.t3contentquality_generate_text ?? '/typo3/ajax/content-quality/generate-text';

class AiTextGenerator extends Plugin {
    static get pluginName() {
        return 'AiTextGenerator';
    }

    init() {
        const editor = this.editor;

        editor.ui.componentFactory.add('aiTextGenerator', locale => {
            const button = new ButtonView(locale);

            button.set({
                label: '✦ AI',
                tooltip: 'AI Text Generator',
                withText: true,
            });

            button.on('execute', () => this._openModal(editor));

            return button;
        });
    }

    _openModal(editor) {
        const existing = document.getElementById('t3cq-ai-modal-overlay');
        if (existing) {
            existing.remove();
        }

        const overlay = this._buildModal();
        document.body.appendChild(overlay);

        overlay.querySelector('#t3cq-ai-prompt').focus();

        overlay.querySelector('#t3cq-ai-cancel').addEventListener('click', () => overlay.remove());

        overlay.addEventListener('mousedown', e => e.stopPropagation(), true);

        overlay.addEventListener('click', e => {
            if (e.target === overlay) overlay.remove();
        });

        overlay.querySelector('#t3cq-ai-form').addEventListener('submit', async e => {
            e.preventDefault();
            await this._generate(editor, overlay);
        });

        overlay.querySelector('#t3cq-ai-maxchars').addEventListener('input', e => {
            overlay.querySelector('#t3cq-ai-maxchars-value').textContent = e.target.value;
        });
    }

    _buildModal() {
        const overlay = document.createElement('div');
        overlay.id = 't3cq-ai-modal-overlay';
        overlay.style.cssText = `
            position: fixed; inset: 0; background: rgba(0,0,0,.5);
            display: flex; align-items: center; justify-content: center;
            z-index: 99999; font-family: sans-serif;
        `;

        overlay.innerHTML = `
            <div style="background:#fff; border-radius:6px; padding:24px; width:480px; max-width:95vw; box-shadow:0 8px 32px rgba(0,0,0,.25);">
                <h3 style="margin:0 0 16px; font-size:16px; font-weight:600; color:#1a1a1a;">AI Text Generator</h3>
                <form id="t3cq-ai-form">
                    <div style="margin-bottom:14px;">
                        <label style="display:block; font-size:13px; font-weight:500; color:#444; margin-bottom:4px;">
                            Prompt <span style="color:#d00">*</span>
                        </label>
                        <textarea
                            id="t3cq-ai-prompt"
                            placeholder="Describe the text you want to generate…"
                            required
                            rows="4"
                            style="width:100%; box-sizing:border-box; border:1px solid #ccc; border-radius:4px; padding:8px; font-size:13px; resize:vertical;"
                        ></textarea>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:500; color:#444; margin-bottom:4px;">Tone</label>
                            <select id="t3cq-ai-tone" style="width:100%; border:1px solid #ccc; border-radius:4px; padding:6px 8px; font-size:13px;">
                                <option value="neutral">Neutral</option>
                                <option value="formal">Formal</option>
                                <option value="friendly">Friendly</option>
                                <option value="professional">Professional</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:500; color:#444; margin-bottom:4px;">Language</label>
                            <select id="t3cq-ai-language" style="width:100%; border:1px solid #ccc; border-radius:4px; padding:6px 8px; font-size:13px;">
                                <option value="auto">Auto-detect</option>
                                <option value="German">German</option>
                                <option value="English">English</option>
                                <option value="French">French</option>
                                <option value="Spanish">Spanish</option>
                            </select>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:500; color:#444; margin-bottom:4px;">Format</label>
                            <select id="t3cq-ai-format" style="width:100%; border:1px solid #ccc; border-radius:4px; padding:6px 8px; font-size:13px;">
                                <option value="paragraph">Paragraph(s)</option>
                                <option value="bullets">Bullet points</option>
                                <option value="headline">Headline only</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:500; color:#444; margin-bottom:4px;">
                                Max characters: <strong id="t3cq-ai-maxchars-value">500</strong>
                            </label>
                            <input
                                type="range"
                                id="t3cq-ai-maxchars"
                                min="50" max="3000" step="50" value="500"
                                style="width:100%; margin-top:6px;"
                            />
                        </div>
                    </div>

                    <div id="t3cq-ai-preview" style="display:none; margin-bottom:14px;">
                        <label style="display:block; font-size:13px; font-weight:500; color:#444; margin-bottom:4px;">
                            Generated text
                            <span id="t3cq-ai-charcount" style="font-weight:400; color:#666;"></span>
                        </label>
                        <textarea
                            id="t3cq-ai-result"
                            rows="5"
                            style="width:100%; box-sizing:border-box; border:1px solid #a3c97a; border-radius:4px; padding:8px; font-size:13px; background:#f8fff3; resize:vertical;"
                        ></textarea>
                    </div>

                    <div id="t3cq-ai-error" style="display:none; margin-bottom:14px; padding:8px 12px; background:#fff0f0; border:1px solid #f5c0c0; border-radius:4px; color:#c00; font-size:13px;"></div>

                    <div style="display:flex; justify-content:flex-end; gap:8px;">
                        <button type="button" id="t3cq-ai-cancel" style="padding:8px 16px; border:1px solid #ccc; border-radius:4px; background:#fff; font-size:13px; cursor:pointer;">
                            Cancel
                        </button>
                        <button type="submit" id="t3cq-ai-submit" style="padding:8px 16px; border:none; border-radius:4px; background:#0078d4; color:#fff; font-size:13px; cursor:pointer; font-weight:500;">
                            Generate
                        </button>
                        <button type="button" id="t3cq-ai-insert" style="display:none; padding:8px 16px; border:none; border-radius:4px; background:#107c10; color:#fff; font-size:13px; cursor:pointer; font-weight:500;">
                            Insert into editor
                        </button>
                    </div>
                </form>
            </div>
        `;

        return overlay;
    }

    async _generate(editor, overlay) {
        const submitBtn = overlay.querySelector('#t3cq-ai-submit');
        const insertBtn = overlay.querySelector('#t3cq-ai-insert');
        const previewEl = overlay.querySelector('#t3cq-ai-preview');
        const resultEl  = overlay.querySelector('#t3cq-ai-result');
        const errorEl   = overlay.querySelector('#t3cq-ai-error');
        const countEl   = overlay.querySelector('#t3cq-ai-charcount');

        const prompt      = overlay.querySelector('#t3cq-ai-prompt').value.trim();
        const maxChars    = parseInt(overlay.querySelector('#t3cq-ai-maxchars').value, 10);
        const tone        = overlay.querySelector('#t3cq-ai-tone').value;
        const language    = overlay.querySelector('#t3cq-ai-language').value;
        const format      = overlay.querySelector('#t3cq-ai-format').value;
        const languageUid = this._detectContentLanguageUid();

        errorEl.style.display = 'none';
        previewEl.style.display = 'none';
        insertBtn.style.display = 'none';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Generating…';

        try {
            const response = await fetch(ROUTE_PATH, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ prompt, maxChars, tone, language, format, languageUid }),
            });

            const data = await response.json();

            if (!response.ok || data.error) {
                throw new Error(data.error ?? `Server error ${response.status}`);
            }

            const text = data.text ?? '';
            resultEl.value = text;
            countEl.textContent = ` (${text.length} chars)`;
            previewEl.style.display = 'block';
            insertBtn.style.display = 'inline-block';

            insertBtn.onclick = () => {
                this._insertText(editor, resultEl.value);
                overlay.remove();
            };
        } catch (err) {
            errorEl.textContent = err.message;
            errorEl.style.display = 'block';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Regenerate';
        }
    }

    _detectContentLanguageUid() {
        const searchDoc = window.top?.document ?? document;
        const field = searchDoc.querySelector('input[name*="[sys_language_uid]"], select[name*="[sys_language_uid]"]');
        if (field) {
            const val = parseInt(field.value, 10);
            return isNaN(val) ? null : val;
        }
        return null;
    }

    _insertText(editor, text) {
        editor.model.change(writer => {
            const selection = editor.model.document.selection;
            const range = selection.getFirstRange();

            if (range && !range.isCollapsed) {
                editor.model.deleteContent(editor.model.document.selection);
            }

            const insertPosition = editor.model.document.selection.getFirstPosition();
            writer.insertText(text, insertPosition);
        });
    }
}

export { AiTextGenerator };
