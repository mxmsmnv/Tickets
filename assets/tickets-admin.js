(function () {
	'use strict';

	var samples = {
		ticket_key: 'BAF3103C3CF8',
		subject: 'Unable to update my profile',
		customer_name: 'Guest',
		customer_email: 'guest@example.com',
		support_name: 'Support team',
		message: 'Thanks for the details. We have reviewed the update and will keep you informed here.',
		ticket_url: 'https://example.com/support/BAF3103C3CF8/'
	};

	function replaceVariables(value) {
		return String(value || '').replace(/\{\{([a-z_]+)\}\}/g, function (match, key) {
			return Object.prototype.hasOwnProperty.call(samples, key) ? samples[key] : match;
		});
	}

	function editorContent(form) {
		var textarea = form.querySelector('.InputfieldTinyMCEEditor, textarea[name^="html_body_"]');
		if (!textarea) return '';
		var editor = window.tinymce && window.tinymce.get(textarea.id);
		return editor ? editor.getContent() : textarea.value;
	}

	function renderPreview(form) {
		var subject = form.querySelector('[data-ticket-template-subject]');
		var subjectPreview = form.querySelector('[data-ticket-preview-subject]');
		var frame = form.querySelector('[data-ticket-preview-frame]');
		if (!subject || !subjectPreview || !frame) return;
		subjectPreview.textContent = replaceVariables(subject.value) || 'Email subject';
		var body = replaceVariables((form.dataset.ticketMailHeader || '') + editorContent(form) + (form.dataset.ticketMailFooter || ''));
		frame.srcdoc = '<!doctype html><html><head><meta charset="utf-8"><style>'
			+ 'body{margin:0;padding:24px;background:#fff;color:#242424;font:16px/1.55 Arial,sans-serif}a{color:#72203f}blockquote{margin:16px 0;padding:12px 16px;border-left:3px solid #72203f;background:#f4f4f4}img{max-width:100%;height:auto}'
			+ '</style></head><body>' + body + '</body></html>';
	}

	function queuePreview(form) {
		window.clearTimeout(form.ticketPreviewTimer);
		form.ticketPreviewTimer = window.setTimeout(function () { renderPreview(form); }, 120);
	}

	function insertVariable(button) {
		var editorId = button.getAttribute('data-ticket-editor');
		var value = button.getAttribute('data-ticket-variable') || '';
		var textarea = document.getElementById(editorId);
		var editor = window.tinymce && window.tinymce.get(editorId);
		if (editor) {
			editor.focus();
			editor.insertContent(value);
			editor.fire('change');
		} else if (textarea) {
			textarea.focus();
			textarea.setRangeText(value, textarea.selectionStart, textarea.selectionEnd, 'end');
			textarea.dispatchEvent(new Event('input', { bubbles: true }));
		}
	}

	function renderDraftSources(container, sources) {
		if (!container) return;
		var list = container.querySelector('ul');
		if (!list) return;
		list.replaceChildren();
		(sources || []).forEach(function (source) {
			var item = document.createElement('li');
			var link = document.createElement('a');
			link.href = String(source.url || '#');
			link.target = '_blank';
			link.rel = 'noopener';
			link.textContent = String(source.title || 'Source');
			item.appendChild(link);
			list.appendChild(item);
		});
		container.hidden = !sources || !sources.length;
	}

	function prepareAiDraft(form) {
		var button = form.querySelector('button[type="submit"]');
		var label = button && button.querySelector('span');
		var status = form.querySelector('[data-ticket-ai-status]');
		var reply = document.querySelector('[data-ticket-reply]');
		var sources = document.querySelector('[data-ticket-draft-sources]');
		if (!button || !reply) return;
		button.disabled = true;
		button.setAttribute('aria-busy', 'true');
		if (label) label.textContent = button.dataset.loadingLabel || 'Preparing draft…';
		if (status) status.textContent = '';
		window.fetch(form.action || window.location.href, {
			method: 'POST', body: new FormData(form), credentials: 'same-origin',
			headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
		}).then(function (response) {
			return response.json().catch(function () { throw new Error('The server returned an invalid response.'); }).then(function (payload) {
				if (!response.ok || !payload.ok) throw new Error(payload.message || 'The draft could not be prepared.');
				return payload;
			});
		}).then(function (payload) {
			reply.value = payload.draft || '';
			reply.dispatchEvent(new Event('input', { bubbles: true }));
			renderDraftSources(sources, payload.sources || []);
			if (status) status.textContent = payload.message || 'Draft prepared.';
			reply.focus();
		}).catch(function (error) {
			if (status) status.textContent = error.message || 'The draft could not be prepared.';
		}).finally(function () {
			button.disabled = false;
			button.removeAttribute('aria-busy');
			if (label) label.textContent = button.dataset.idleLabel || 'Prepare AI draft';
		});
	}

	function polishReply(button) {
		var reply = document.querySelector('[data-ticket-reply]');
		var replyForm = reply && reply.closest('form');
		if (!reply || !replyForm || !reply.value.trim() || !window.fetch) {
			if (reply) reply.focus();
			return;
		}
		var original = button.innerHTML;
		var data = new FormData(replyForm);
		data.set('ticket_action', 'polish');
		data.set('tickets_ajax', '1');
		data.set('body', reply.value);
		button.disabled = true;
		button.setAttribute('aria-busy', 'true');
		button.textContent = 'Improving…';
		window.fetch(button.dataset.endpoint || window.location.href, {
			method: 'POST', body: data, credentials: 'same-origin',
			headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
		}).then(function (response) {
			return response.json().catch(function () { throw new Error('The server returned an invalid response.'); }).then(function (payload) {
				if (!response.ok || !payload.ok) throw new Error(payload.message || 'The reply could not be improved.');
				return payload;
			});
		}).then(function (payload) {
			reply.value = payload.draft || reply.value;
			reply.dispatchEvent(new Event('input', { bubbles: true }));
			reply.focus();
		}).catch(function (error) {
			window.alert(error.message || 'The reply could not be improved.');
		}).finally(function () {
			button.disabled = false;
			button.removeAttribute('aria-busy');
			button.innerHTML = original;
		});
	}

	var queueRowsMedia = window.matchMedia ? window.matchMedia('(max-width: 959px)') : null;

	function syncQueueRows() {
		var interactive = queueRowsMedia && queueRowsMedia.matches;
		document.querySelectorAll('[data-ticket-row]').forEach(function (row) {
			if (interactive) {
				row.setAttribute('role', 'link');
				row.setAttribute('tabindex', '0');
				row.setAttribute('aria-label', row.getAttribute('data-ticket-label') || 'Open ticket');
			} else {
				row.removeAttribute('role');
				row.removeAttribute('tabindex');
				row.removeAttribute('aria-label');
			}
		});
	}

	function openQueueRow(event) {
		var row = event.target.closest && event.target.closest('[data-ticket-row]');
		if (!row || !queueRowsMedia || !queueRowsMedia.matches) return;
		if (event.type === 'click' && event.target.closest('a, button, input, select, textarea, label')) return;
		if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') return;
		var url = row.getAttribute('data-ticket-url');
		if (!url) return;
		event.preventDefault();
		window.location.assign(url);
	}

	function closeReceiptPopovers(event) {
		var active = event.target.closest && event.target.closest('.TicketsMessageReceipt');
		document.querySelectorAll('.TicketsMessageReceipt[open]').forEach(function (receipt) {
			if (receipt !== active) receipt.removeAttribute('open');
		});
	}

	document.addEventListener('click', openQueueRow);
	document.addEventListener('click', closeReceiptPopovers);
	document.addEventListener('keydown', openQueueRow);
	document.addEventListener('keydown', function (event) {
		if (event.key !== 'Escape') return;
		document.querySelectorAll('.TicketsMessageReceipt[open]').forEach(function (receipt) {
			receipt.removeAttribute('open');
		});
	});

	document.addEventListener('click', function (event) {
		var polish = event.target.closest('[data-ticket-polish]');
		if (polish) {
			event.preventDefault();
			polishReply(polish);
			return;
		}
		var button = event.target.closest('[data-ticket-variable]');
		if (!button) return;
		insertVariable(button);
		var form = button.closest('[data-ticket-template-form]');
		if (form) queuePreview(form);
	});

	document.addEventListener('submit', function (event) {
		var form = event.target.closest && event.target.closest('[data-ticket-ai-draft]');
		if (!form || !window.fetch) return;
		event.preventDefault();
		prepareAiDraft(form);
	});

	document.addEventListener('input', function (event) {
		var form = event.target.closest('[data-ticket-template-form]');
		if (form) queuePreview(form);
	});

	document.addEventListener('change', function (event) {
		if (event.target.matches('[data-ticket-macro]')) {
			var option = event.target.options[event.target.selectedIndex];
			var reply = document.querySelector('[data-ticket-reply]');
			if (reply && option && option.dataset.body) {
				reply.value = option.dataset.body;
				reply.focus();
			}
		}
		var form = event.target.closest('[data-ticket-template-form]');
		if (form) queuePreview(form);
	});

	document.addEventListener('toggle', function (event) {
		var card = event.target.closest && event.target.closest('.TicketsTemplateCard');
		if (!card || !card.open || !window.InputfieldTinyMCE || !window.jQuery) return;
		window.InputfieldTinyMCE.initEditorsIn(window.jQuery(card));
		var form = card.querySelector('[data-ticket-template-form]');
		if (form) window.setTimeout(function () { renderPreview(form); }, 250);
	}, true);

	document.addEventListener('DOMContentLoaded', function () {
		syncQueueRows();
		if (queueRowsMedia) {
			if (queueRowsMedia.addEventListener) queueRowsMedia.addEventListener('change', syncQueueRows);
			else if (queueRowsMedia.addListener) queueRowsMedia.addListener(syncQueueRows);
		}
		var forms = document.querySelectorAll('[data-ticket-template-form]');
		forms.forEach(function (form) {
			renderPreview(form);
			form.addEventListener('submit', function () {
				if (window.tinymce) window.tinymce.triggerSave();
			});
		});
		window.setTimeout(function () { forms.forEach(renderPreview); }, 500);
		window.setTimeout(function () { forms.forEach(renderPreview); }, 1500);
		if (window.jQuery) {
			window.jQuery(document).on('change', '.TicketsTemplateTinyMCE', function () {
				var form = this.closest('[data-ticket-template-form]');
				if (form) queuePreview(form);
			});
		}

			document.querySelectorAll('[data-tickets-form-builder]').forEach(function (builder) {
			var fields = builder.querySelector('[data-tickets-fields]');
			var json = builder.querySelector('[data-tickets-fields-json]');
			var title = builder.querySelector('[data-tickets-form-title]');
			var slug = builder.querySelector('[data-tickets-form-slug]');
			var slugEdited = slug && slug.value !== '';
			var preview = builder.querySelector('[data-tickets-form-preview]');
			function renderFormPreview(value) {
				if (!preview) return;
				preview.replaceChildren();
				var form = document.createElement('div');
				form.className = 'TicketsFormPreview-grid';
				value.forEach(function (item) {
					var field = document.createElement('label');
					field.className = item.width === 'half' ? 'is-half' : '';
					var label = document.createElement('span');
					label.textContent = (item.label || 'Untitled field') + (item.required ? ' *' : '');
					field.appendChild(label);
					var control;
					if (item.type === 'section') {
						field = document.createElement('section'); field.className = 'TicketsFormPreview-section';
						var heading = document.createElement('h3'); heading.textContent = item.label || 'Section'; field.appendChild(heading);
						if (item.help) { var sectionHelp = document.createElement('small'); sectionHelp.textContent = item.help; field.appendChild(sectionHelp); }
						form.appendChild(field); return;
					}
					if (item.type === 'textarea') control = document.createElement('textarea');
					else if (item.type === 'select') { control = document.createElement('select'); (item.options || []).forEach(function (text) { var option = document.createElement('option'); option.textContent = text; control.appendChild(option); }); }
					else { control = document.createElement('input'); control.type = item.type === 'checkbox' ? 'checkbox' : (item.type || 'text'); }
					control.disabled = true;
					if (item.placeholder) control.placeholder = item.placeholder;
					field.appendChild(control);
					if (item.help) { var help = document.createElement('small'); help.textContent = item.help; field.appendChild(help); }
					form.appendChild(field);
				});
				preview.appendChild(form);
			}
			function serialize() {
				var value = [];
				fields.querySelectorAll('[data-tickets-field]').forEach(function (row) {
					var item = {};
					row.querySelectorAll('[data-field]').forEach(function (control) {
						var key = control.dataset.field;
						if (key === 'required') item[key] = control.checked;
						else if (key === 'options') item[key] = control.value.split(/\r?\n/).map(function (option) { return option.trim(); }).filter(Boolean);
						else item[key] = control.value;
					});
					value.push(item);
				});
				json.value = JSON.stringify(value);
				renderFormPreview(value);
			}
			function refresh(row) {
				var type = row.querySelector('[data-field="type"]');
				var options = row.querySelector('.TicketsFormField-options');
				var label = row.querySelector('[data-field="label"]');
				if (options && type) options.hidden = type.value !== 'select';
				var heading = row.querySelector('[data-tickets-field-title]');
				if (heading && label) heading.textContent = label.value || 'Untitled field';
			}
			fields.querySelectorAll('[data-tickets-field]').forEach(refresh);
			builder.addEventListener('input', function (event) {
				var row = event.target.closest('[data-tickets-field]');
				if (row) refresh(row);
				serialize();
			});
			builder.addEventListener('change', function (event) {
				var row = event.target.closest('[data-tickets-field]');
				if (row) refresh(row);
				serialize();
			});
			builder.addEventListener('click', function (event) {
				var add = event.target.closest('[data-tickets-add-field]');
				if (add) {
					var source = fields.querySelector('[data-tickets-field]');
					if (!source) return;
					var row = source.cloneNode(true);
					row.querySelectorAll('[data-field]').forEach(function (control) {
						if (control.dataset.field === 'type') control.value = 'text';
						else if (control.dataset.field === 'width') control.value = 'full';
						else if (control.type === 'checkbox') control.checked = false;
						else control.value = '';
					});
					fields.appendChild(row); refresh(row); serialize();
					row.querySelector('[data-field="label"]').focus(); return;
				}
				var remove = event.target.closest('[data-tickets-remove-field]');
				if (remove) { remove.closest('[data-tickets-field]').remove(); serialize(); return; }
				var up = event.target.closest('[data-tickets-field-up]');
				var down = event.target.closest('[data-tickets-field-down]');
				var current = (up || down) && (up || down).closest('[data-tickets-field]');
				if (up && current.previousElementSibling) fields.insertBefore(current, current.previousElementSibling);
				if (down && current.nextElementSibling) fields.insertBefore(current.nextElementSibling, current);
				if (up || down) serialize();
			});
			if (slug) slug.addEventListener('input', function () { slugEdited = true; });
			if (title) title.addEventListener('input', function () {
				if (!slug || slugEdited) return;
				slug.value = title.value.toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
			});
			builder.addEventListener('submit', serialize);
			serialize();
		});

		document.querySelectorAll('[data-tickets-select-all]').forEach(function (toggle) {
			toggle.addEventListener('change', function () {
				var form = toggle.closest('form');
				if (!form) return;
				form.querySelectorAll('input[name="ticket_ids[]"]').forEach(function (checkbox) { checkbox.checked = toggle.checked; });
			});
		});
	});
})();
