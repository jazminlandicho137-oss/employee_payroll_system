<?php
declare(strict_types=1);

const SUPABASE_URL = 'https://pbxnnfowppzespkdwpfq.supabase.co';
const SUPABASE_KEY = 'sb_publishable_tNd6M5CUefjOgqDHmWRPkw_EKI-v03V';

/**
 * Thrown when Supabase/PostgREST answers with an error. $pgCode carries the
 * Postgres error code (e.g. '23505' for a duplicate key) so callers can
 * react to it the same way they used to react to MySQL error 1062.
 */
final class SupabaseException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $pgCode = null,
        int $httpStatus = 0
    ) {
        parent::__construct($message, $httpStatus);
    }
}

/**
 * Tiny Supabase (PostgREST) client. Replaces the old MySQL/SupabaseClient $conn:
 * every page still gets a $conn variable, but it is now this client and the
 * database calls are HTTPS requests to Supabase instead of SQL over a
 * database connection.
 *
 * Usage:
 *   $rows  = $conn->select('employee_info', ['employee_id=eq.5'], limit: 1);
 *   $row   = $conn->selectOne('admin', ['username=eq.' . $name]);
 *   $id    = $conn->insert('dependent', ['employee_id' => 1, ...]);
 *   $conn->update('admin', ['admin_id=eq.5'], ['username' => 'x']);
 *   $conn->delete('dependent', ['dependent_id=eq.3', 'employee_id=eq.1']);
 */
final class SupabaseClient
{
    private string $endpoint;

    public function __construct(string $url = SUPABASE_URL, string $key = SUPABASE_KEY)
    {
        $this->endpoint = rtrim($url, '/') . '/rest/v1/';
        $this->key = $key;
    }

    private string $key;

    /** Returns all matching rows (or just a column list) as associative arrays. */
    public function select(
        string $table,
        array $filters = [],
        ?string $order = null,
        ?int $limit = null,
        string $columns = '*'
    ): array {
        $params = ['select' => $columns];
        foreach ($filters as $filter) {
            // filters look like 'employee_id=eq.5' or an 'or=(...)' clause
            [$key, $value] = array_pad(explode('=', $filter, 2), 2, '');
            $params[$key] = $value;
        }
        if ($order !== null) {
            $params['order'] = $order;
        }
        if ($limit !== null) {
            $params['limit'] = (string)$limit;
        }
        $result = $this->request('GET', $table, $params, null, false);
        return is_array($result) ? $result : [];
    }

    /** Returns the first matching row, or null. */
    public function selectOne(string $table, array $filters = [], string $columns = '*'): ?array
    {
        $rows = $this->select($table, $filters, null, 1, $columns);
        return $rows[0] ?? null;
    }

    /** Inserts one row and returns its new id (from insert_with) or the row. */
    public function insert(string $table, array $data): array
    {
        $result = $this->request('POST', $table, [], $data, true);
        return (is_array($result) && isset($result[0])) ? $result[0] : [];
    }

    public function update(string $table, array $filters, array $data): void
    {
        $params = [];
        foreach ($filters as $filter) {
            [$key, $value] = array_pad(explode('=', $filter, 2), 2, '');
            $params[$key] = $value;
        }
        $this->request('PATCH', $table, $params, $data, false);
    }

    public function delete(string $table, array $filters): void
    {
        $params = [];
        foreach ($filters as $filter) {
            [$key, $value] = array_pad(explode('=', $filter, 2), 2, '');
            $params[$key] = $value;
        }
        $this->request('DELETE', $table, $params, null, false);
    }

    private function request(string $method, string $table, array $params, ?array $body, bool $returnRow)
    {
        $url = $this->endpoint . rawurlencode($table);
        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        $headers = [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if ($returnRow) {
            $headers[] = 'Prefer: return=representation';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            error_log('Supabase request failed: ' . $curlError);
            throw new SupabaseException('Unable to reach the database right now. Please try again later.');
        }

        $decoded = $raw !== '' ? json_decode($raw, true) : null;

        if ($status >= 400) {
            $message = is_array($decoded) ? ($decoded['message'] ?? $decoded['error'] ?? 'Database error') : 'Database error';
            $pgCode  = is_array($decoded) ? ($decoded['code'] ?? null) : null;
            error_log("Supabase error (HTTP $status): " . $raw);
            throw new SupabaseException((string)$message, is_string($pgCode) ? $pgCode : null, $status);
        }

        return $decoded ?? [];
    }
}

try {
    $conn = new SupabaseClient();
} catch (Throwable $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    die('Unable to connect to the system right now. Please try again later.');
}
