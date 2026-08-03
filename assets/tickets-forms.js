(function () {
	'use strict';

	function status(form, message, error) {
		var node = form.querySelector('[data-tickets-form-status]');
		if (!node) return;
		node.textContent = message || '';
		node.dataset.error = error ? 'true' : 'false';
	}

	function bind(container) {
		var form = container.querySelector('[data-tickets-custom-form]');
		if (!form || form.dataset.ticketsBound) return;
		applyDefaults(container, form);
		form.dataset.ticketsBound = 'true';
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			var button = form.querySelector('[type="submit"]');
			if (button) button.disabled = true;
			status(form, 'Sending…', false);
			fetch(container.dataset.ticketsFormUrl, {
				method: 'POST', body: new FormData(form), credentials: 'same-origin',
				headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
			}).then(function (response) {
				return response.json().then(function (data) { if (!response.ok) throw new Error(data.error || 'The form could not be sent.'); return data; });
			}).then(function (data) {
				container.innerHTML = '<div class="TicketsCustomForm-success" role="status"><h2>' + escapeHtml(data.title || 'Request sent') + '</h2><p>' + escapeHtml(data.message || '') + '</p>' + (data.ticket_url ? '<p><a href="' + escapeHtml(data.ticket_url) + '">Open support ticket</a></p>' : '') + '</div>';
			}).catch(function (error) {
				status(form, error.message, true);
				if (button) button.disabled = false;
			});
		});
	}

	function applyDefaults(container, form) {
		var defaults = {};
		try { defaults = JSON.parse(container.dataset.ticketsFormDefaults || '{}'); }
		catch (error) { defaults = {}; }
		Object.keys(defaults).forEach(function (name) {
			var control = form.elements.namedItem(name);
			if (!control) return;
			if (control.type === 'checkbox') control.checked = defaults[name] === true || defaults[name] === 'Yes' || defaults[name] === '1';
			else if (!control.value) control.value = String(defaults[name]);
		});
	}

	function escapeHtml(value) {
		var node = document.createElement('div');
		node.textContent = value == null ? '' : String(value);
		return node.innerHTML;
	}

	function load(container) {
		if (container.dataset.ticketsLoaded) return;
		container.dataset.ticketsLoaded = 'true';
		container.classList.add('is-loading');
		fetch(container.dataset.ticketsFormUrl, {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
			.then(function (response) { if (!response.ok) throw new Error('The form is unavailable.'); return response.text(); })
			.then(function (html) { container.innerHTML = html; container.classList.remove('is-loading'); bind(container); })
			.catch(function () { container.innerHTML = '<p class="TicketsCustomForm-error" role="alert">The form is temporarily unavailable. Please try again.</p>'; container.classList.remove('is-loading'); });
	}

	function init(root) {
		(root || document).querySelectorAll('.TicketsFormEmbed[data-tickets-form-url]').forEach(load);
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); });
	else init(document);
	window.TicketsForms = {init: init};
}());
