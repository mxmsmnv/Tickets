<?php namespace ProcessWire;

/** Optional, one-way migration adapter for Pro FormBuilder definitions. */
final class TicketsFormBuilderImporter {

	private Tickets $tickets;
	private array $warnings = [];
	private bool $allowAttachment = false;

	public function __construct(Tickets $tickets) {
		$this->tickets = $tickets;
	}

	public function available(): bool {
		$modules = $this->tickets->wire('modules');
		return $modules->isInstalled('FormBuilder') && (bool)$modules->get('FormBuilder');
	}

	public function candidates(User $user): array {
		$this->assertAdmin($user);
		if (!$this->available()) return [];
		$forms = [];
		foreach ((array)$this->tickets->wire('modules')->get('FormBuilder')->loadAll() as $source) {
			if (!is_object($source) || !method_exists($source, 'getArray')) continue;
			$definition = $this->convert((array)$source->getArray());
			$definition['source_id'] = (int)($source->id ?? 0);
			$definition['source_name'] = (string)($source->name ?? '');
			$definition['target_name'] = $this->targetName($definition['source_name'], $definition['source_id']);
			$definition['existing'] = (bool)$this->tickets->customForm($definition['target_name']);
			$forms[] = $definition;
		}
		return $forms;
	}

	public function import($identifier, User $user): array {
		$this->assertAdmin($user);
		if (!$this->available()) throw new WireException('FormBuilder is not installed.');
		$source = $this->tickets->wire('modules')->get('FormBuilder')->load($identifier);
		if (!$source || !method_exists($source, 'getArray')) throw new WireException('The FormBuilder form could not be loaded.');
		$sourceId = (int)($source->id ?? 0);
		$sourceName = (string)($source->name ?? '');
		$definition = $this->convert((array)$source->getArray());
		if (!$definition['fields']) throw new WireException('The FormBuilder form has no importable fields.');
		$name = $this->targetName($sourceName, $sourceId);
		$existing = $this->tickets->customForm($name);
		$description = trim((string)$definition['description']);
		$origin = 'Imported from FormBuilder: ' . ($sourceName !== '' ? $sourceName : '#' . $sourceId) . '.';
		if ($description === '') $description = $origin;
		elseif (!str_contains($description, $origin)) $description .= "\n\n" . $origin;
		return $this->tickets->saveCustomForm([
			'id' => (int)($existing['id'] ?? 0),
			'name' => $name,
			'title' => $definition['title'],
			'description' => $description,
			'success_message' => $definition['success_message'],
			'submit_label' => $definition['submit_label'],
			'category' => 'other',
			'topic' => 'general',
			'priority' => 'normal',
			'allow_guests' => 1,
			'allow_attachment' => $definition['allow_attachment'] ? 1 : 0,
			// Imports remain drafts until routing, legal copy and field mapping are reviewed.
			'enabled' => 0,
			'fields' => $definition['fields'],
		], $user);
	}

	public function convert(array $source): array {
		$this->warnings = [];
		$this->allowAttachment = false;
		$name = trim((string)($source['name'] ?? ''));
		$title = trim((string)($source['label'] ?? '')) ?: $this->humanize($name ?: 'Imported form');
		$fields = [];
		$used = [];
		$this->appendChildren((array)($source['children'] ?? []), $fields, $used);
		return [
			'title' => $title,
			'description' => trim((string)($source['description'] ?? '')),
			'submit_label' => trim((string)($source['submitText'] ?? '')) ?: 'Send request',
			'success_message' => trim((string)($source['successMessage'] ?? '')) ?: 'Thank you. Your request was sent.',
			'fields' => $fields,
			'allow_attachment' => $this->allowAttachment,
			'warnings' => array_values(array_unique($this->warnings)),
		];
	}

	private function appendChildren(array $children, array &$fields, array &$used, string $prefix = ''): void {
		foreach ($children as $name => $field) {
			if (!is_array($field)) continue;
			$type = strtolower((string)($field['type'] ?? 'text'));
			$label = trim((string)($field['label'] ?? '')) ?: $this->humanize((string)$name);
			$fieldName = $this->uniqueName($prefix . (string)$name, $used);
			if (in_array($type, ['fieldset', 'formbuilderpagebreak'], true)) {
				$fields[] = $this->field($fieldName, $label, 'section', $field, (string)($field['description'] ?? ''));
				if (!empty($field['children'])) $this->appendChildren((array)$field['children'], $fields, $used, $prefix . (string)$name . '_');
				continue;
			}
			if ($type === 'combo') {
				$fields[] = $this->field($fieldName, $label, 'section', $field);
				$quantity = max(0, min(20, (int)($field['qty'] ?? 0)));
				for ($index = 1; $index <= $quantity; $index++) {
					if (empty($field['i' . $index . '_name'])) continue;
					$child = [
						'type' => $field['i' . $index . '_type'] ?? 'Text',
						'label' => $field['i' . $index . '_label'] ?? $field['i' . $index . '_name'],
						'columnWidth' => $field['i' . $index . '_columnWidth'] ?? 100,
						'minlength' => $field['i' . $index . '_minlength'] ?? 0,
						'maxlength' => $field['i' . $index . '_maxlength'] ?? 0,
					];
					$this->appendChildren([(string)$field['i' . $index . '_name'] => $child], $fields, $used, $prefix . (string)$name . '_');
				}
				continue;
			}
			if (in_array($type, ['formbuilderfile', 'file'], true)) {
				$this->allowAttachment = true;
				$this->warnings[] = sprintf('%s uses the shared protected ticket attachment control.', $label);
				continue;
			}
			if ($type === 'hidden') continue;
			if (in_array($type, ['checkboxes', 'selectmultiple'], true)) {
				$options = $this->options((string)($field['options'] ?? ''));
				foreach ($options as $option) {
					$optionName = $this->uniqueName($fieldName . '_' . $this->tickets->wire('sanitizer')->name($option), $used);
					$fields[] = $this->field($optionName, $label . ': ' . $option, 'checkbox', $field);
				}
				if (!$options) $this->warnings[] = sprintf('%s had no portable options and was skipped.', $label);
				continue;
			}
			$mapped = match ($type) {
				'textarea' => 'textarea', 'email' => 'email', 'url' => 'url',
				'integer', 'float', 'number' => 'number', 'checkbox', 'toggle' => 'checkbox',
				'select', 'radios', 'radio', 'timezone' => 'select', 'datetime' => 'date',
				'tel', 'telephone' => 'tel', default => 'text',
			};
			if (in_array($type, ['page', 'pageselect'], true)) $this->warnings[] = sprintf('%s was converted from a dynamic page selector to text.', $label);
			$fields[] = $this->field($fieldName, $label, $mapped, $field);
		}
	}

	private function field(string $name, string $label, string $type, array $source, string $extraHelp = ''): array {
		$help = array_filter([
			trim((string)($source['description'] ?? '')),
			trim((string)($source['notes'] ?? '')),
			trim($extraHelp),
		]);
		return [
			'name' => $name,
			'label' => $label,
			'type' => $type,
			'required' => !empty($source['required']),
			'width' => (int)($source['columnWidth'] ?? 100) > 0 && (int)($source['columnWidth'] ?? 100) <= 50 ? 'half' : 'full',
			'min_length' => max(0, (int)($source['minlength'] ?? 0)),
			'max_length' => max(0, (int)($source['maxlength'] ?? ($type === 'textarea' ? 10000 : 1000))),
			'placeholder' => trim((string)($source['placeholder'] ?? '')),
			'help' => implode(' ', array_unique($help)),
			'options' => $type === 'select' ? $this->options((string)($source['options'] ?? '')) : [],
		];
	}

	private function options(string $value): array {
		$options = [];
		foreach (preg_split('/\R/u', $value) ?: [] as $line) {
			$line = trim(html_entity_decode(strip_tags($line), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
			if ($line === '') continue;
			$label = str_contains($line, '=') ? trim(substr($line, strpos($line, '=') + 1)) : $line;
			if ($label !== '' && !in_array($label, $options, true)) $options[] = $label;
		}
		return array_slice($options, 0, 200);
	}

	private function uniqueName(string $value, array &$used): string {
		$base = $this->tickets->wire('sanitizer')->name(str_replace(['.', '-'], '_', strtolower($value))) ?: 'field';
		if (in_array($base, ['customer_email', 'attachment', 'form_name', 'website', 'ticket_action'], true)) $base = 'submitted_' . $base;
		$name = substr($base, 0, 80);
		$suffix = 2;
		while (isset($used[$name])) $name = substr($base, 0, 74) . '_' . $suffix++;
		$used[$name] = true;
		return $name;
	}

	private function targetName(string $sourceName, int $sourceId): string {
		$name = $this->tickets->wire('sanitizer')->pageName($sourceName);
		return 'fb-' . ($name !== '' ? $name : 'form-' . $sourceId);
	}

	private function humanize(string $value): string {
		return ucwords(trim(str_replace(['-', '_'], ' ', $value)));
	}

	private function assertAdmin(User $user): void {
		if (!$this->tickets->canAdmin($user)) throw new WirePermissionException('You cannot import ticket forms.');
	}
}
