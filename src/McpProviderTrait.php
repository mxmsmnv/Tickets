<?php namespace ProcessWire;

/** Bounded MCP provider surface. Private ticket records are never exposed. */
trait TicketsMcpProviderTrait {
    public function mcpProviderInfo(): array {
        return ['name' => 'tickets', 'title' => 'Tickets', 'version' => '1.0.50'];
    }

    public function mcpTools(): array {
        return [[
            'name' => 'tickets_status',
            'title' => 'Tickets operational status',
            'description' => 'Return aggregate support readiness and workflow counts without ticket messages, customer data, attachments, tokens, or internal notes.',
            'handler' => [$this, 'mcpTicketsStatus'],
            'scope' => 'read', 'read_only' => true, 'destructive' => false,
            'idempotent' => true, 'open_world' => false,
            'input_schema' => ['type' => 'object', 'properties' => new \stdClass(), 'required' => [], 'additionalProperties' => false],
        ]];
    }

    public function mcpTicketsStatus(): array {
        $stats = $this->dashboardStats();
        $summary = (array)($stats['summary'] ?? []);
        $allowed = ['total', 'active', 'waiting_staff', 'waiting_customer', 'urgent', 'sla_breached', 'unassigned', 'created_7d', 'created_30d', 'resolved_30d'];
        return [
            'version' => '1.0.50',
            'counts' => array_intersect_key($summary, array_flip($allowed)),
            'statuses' => (array)($stats['statuses'] ?? []),
            'workflow' => [
                'types' => array_keys($this->types()), 'topics' => array_keys($this->topics()),
                'priorities' => array_keys($this->priorities()), 'statuses' => array_keys($this->statuses()),
            ],
            'channels' => (array)($this->capabilities()['channels'] ?? []),
            'private_records_exposed' => false,
        ];
    }
}
