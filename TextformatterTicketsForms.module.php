<?php namespace ProcessWire;

/** Embeds Tickets custom forms in rich-text fields without cache-sensitive HTML. */
class TextformatterTicketsForms extends Textformatter {
	public static function getModuleInfo(): array {
		return [
			'title' => 'Tickets Forms',
			'version' => Tickets::VERSION,
			'summary' => 'Render Tickets form shortcodes in text fields.',
			'author' => 'Maxim Semenov',
			'requires' => ['Tickets'],
		];
	}

	public function format(&$str): void {
		if (!is_string($str) || stripos($str, 'tickets-form') === false) return;
		/** @var Tickets $tickets */
		$tickets = $this->wire('modules')->get('Tickets');
		$str = preg_replace_callback('/\[\[tickets-form:([a-z0-9_-]+)\]\]/i', static fn(array $match): string => $tickets->renderFormEmbed($match[1]), $str);
		$str = preg_replace_callback('/\[tickets-form\s+name=["\']([a-z0-9_-]+)["\']\s*\]/i', static fn(array $match): string => $tickets->renderFormEmbed($match[1]), $str);
	}
}
