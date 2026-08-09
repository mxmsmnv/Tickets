<?php namespace ProcessWire;

/** Same-origin, session-authenticated JSON transport for TicketsAgentApi. */
final class TicketsRestApi extends Wire {

	private const MAX_BODY_BYTES = 65536;
	private const RATE_SESSION_KEY = 'TicketsRestRate';
	private Tickets $tickets;

	public function __construct(Tickets $tickets) {
		$this->tickets = $tickets;
	}

	public function handle(string $resource): string {
		$this->headers();
		try {
			$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
			$resource = strtolower(trim($resource));
			if(!preg_match('/^[a-z-]{2,32}$/', $resource)) return $this->response(404, null, 'Not found.');
			$this->rateLimit($method !== 'GET');
			if($resource === 'session') return $this->sessionResponse($method);

			$api = $this->tickets->api($this->wire()->user);
			if(!$api->canRead()) throw new WirePermissionException('Tickets API access denied.');
			$body = $method === 'POST' ? $this->jsonBody() : [];
			if($method === 'POST') $this->validateCsrf($body);

			switch($resource) {
				case 'capabilities':
					$this->allow($method, ['GET']);
					$result = $api->capabilities();
					break;
				case 'dashboard':
					$this->allow($method, ['GET']);
					$result = $api->dashboard();
					break;
				case 'queue':
					$this->allow($method, ['GET']);
					$result = $api->queue($this->queueFilters(), $this->positiveQuery('page', 1, 100000), $this->positiveQuery('limit', 50, 100), $this->scope());
					break;
				case 'ticket':
					$this->allow($method, ['GET']);
					$result = $api->ticket($this->identifier());
					break;
				case 'messages':
					$this->allow($method, ['GET']);
					$result = $api->messages($this->identifier());
					break;
				case 'report':
					$this->allow($method, ['GET']);
					$result = $api->report(['days' => $this->positiveQuery('days', 30, 3650)]);
					break;
				case 'forms':
					$this->allow($method, ['GET']);
					$result = $api->forms();
					break;
				case 'update':
					$this->allow($method, ['POST']);
					$result = $api->update($this->bodyIdentifier($body), $body);
					break;
				case 'reply':
					$this->allow($method, ['POST']);
					$result = $api->reply($this->bodyIdentifier($body), $this->bodyText($body));
					break;
				case 'note':
					$this->allow($method, ['POST']);
					$result = $api->note($this->bodyIdentifier($body), $this->bodyText($body));
					break;
				default:
					return $this->response(404, null, 'Not found.');
			}
			return $this->response(200, $result);
		} catch(WirePermissionException $error) {
			return $this->response(403, null, $error->getMessage());
		} catch(TicketsRestException $error) {
			return $this->response($error->status(), null, $error->getMessage());
		} catch(Wire404Exception $error) {
			return $this->response(404, null, $error->getMessage());
		} catch(\InvalidArgumentException|WireException $error) {
			return $this->response(400, null, $error->getMessage());
		} catch(\Throwable $error) {
			$this->wire()->log->save('tickets', 'REST request failed (' . get_class($error) . ').');
			return $this->response(500, null, 'Tickets request failed.');
		}
	}

	private function sessionResponse(string $method): string {
		$this->allow($method, ['GET']);
		$api = $this->tickets->api($this->wire()->user);
		$result = [
			'isLogin' => (bool)$this->wire()->user->isLoggedin(),
			'canRead' => $api->canRead(),
			'canWrite' => $api->canWrite(),
			'canAdmin' => $api->canAdmin(),
		];
		if($result['canRead']) {
			$token = $this->wire()->session->CSRF->getToken('tickets-rest');
			$result['csrf'] = ['name' => $token['name'], 'value' => $token['value'], 'header' => 'X-' . $token['name']];
		}
		return $this->response(200, $result);
	}

	private function queueFilters(): array {
		$filters = [];
		foreach(['status', 'category', 'topic', 'priority', 'q', 'assigned_user_id', 'date_from', 'date_to'] as $name) {
			$value = trim((string)$this->wire()->input->get($name));
			if($value !== '') $filters[$name] = mb_substr($value, 0, 100);
		}
		return $filters;
	}

	private function scope(): string {
		$scope = strtolower(trim((string)$this->wire()->input->get('scope')));
		return in_array($scope, ['active', 'closed', 'all'], true) ? $scope : 'active';
	}

	private function identifier() {
		$value = trim((string)($this->wire()->input->get('key') ?: $this->wire()->input->get('id')));
		if(!preg_match('/^(?:[1-9][0-9]*|[A-Za-z0-9]{6,24})$/', $value)) throw new \InvalidArgumentException('A valid ticket id or key is required.');
		return ctype_digit($value) ? (int)$value : strtoupper($value);
	}

	private function bodyIdentifier(array $body) {
		$value = trim((string)($body['key'] ?? $body['id'] ?? ''));
		if(!preg_match('/^(?:[1-9][0-9]*|[A-Za-z0-9]{6,24})$/', $value)) throw new \InvalidArgumentException('A valid ticket id or key is required.');
		return ctype_digit($value) ? (int)$value : strtoupper($value);
	}

	private function bodyText(array $body): string {
		if(!isset($body['body']) || !is_string($body['body'])) throw new \InvalidArgumentException('body must be a JSON string.');
		return $body['body'];
	}

	private function positiveQuery(string $name, int $default, int $maximum): int {
		$value = (int)$this->wire()->input->get($name);
		return max(1, min($maximum, $value ?: $default));
	}

	private function jsonBody(): array {
		$contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
		if($contentType !== 'application/json') throw new \InvalidArgumentException('Content-Type application/json is required.');
		$length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
		if($length > self::MAX_BODY_BYTES) throw new \InvalidArgumentException('JSON request body is too large.');
		$raw = file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
		if(!is_string($raw) || strlen($raw) > self::MAX_BODY_BYTES) throw new \InvalidArgumentException('JSON request body is too large.');
		$body = json_decode($raw, true);
		if(!is_array($body)) throw new \InvalidArgumentException('A JSON object is required.');
		return $body;
	}

	private function validateCsrf(array $body): void {
		$token = $this->wire()->session->CSRF->getToken('tickets-rest');
		$serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', 'X-' . $token['name']));
		$provided = (string)($body[$token['name']] ?? ($_SERVER[$serverKey] ?? ''));
		if($provided === '' || !hash_equals((string)$token['value'], $provided)) throw new WirePermissionException('Invalid CSRF token.');
	}

	private function rateLimit(bool $mutation): void {
		$state = $this->wire()->session->get(self::RATE_SESSION_KEY);
		$now = time();
		if(!is_array($state) || (int)($state['started'] ?? 0) <= $now - 60) $state = ['started' => $now, 'reads' => 0, 'writes' => 0];
		$key = $mutation ? 'writes' : 'reads';
		$state[$key] = (int)$state[$key] + 1;
		$this->wire()->session->set(self::RATE_SESSION_KEY, $state);
		if($state[$key] > ($mutation ? 30 : 120)) throw new TicketsRestException('Tickets API rate limit exceeded.', 429);
	}

	private function allow(string $method, array $allowed): void {
		if(in_array($method, $allowed, true)) return;
		header('Allow: ' . implode(', ', $allowed));
		throw new TicketsRestException('Method not allowed.', 405);
	}

	private function headers(): void {
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: private, no-store, max-age=0');
		header('Pragma: no-cache');
		header('X-Content-Type-Options: nosniff');
		header('X-Frame-Options: DENY');
		header('X-Robots-Tag: noindex, nofollow, noarchive');
	}

	private function response(int $status, $result = null, string $error = ''): string {
		http_response_code($status);
		$payload = $error === '' ? ['ok' => true, 'result' => $result] : ['ok' => false, 'error' => $error];
		return (string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}
}

final class TicketsRestException extends \RuntimeException {
	private int $httpStatus;

	public function __construct(string $message, int $status) {
		parent::__construct($message);
		$this->httpStatus = $status;
	}

	public function status(): int {
		return $this->httpStatus;
	}
}
