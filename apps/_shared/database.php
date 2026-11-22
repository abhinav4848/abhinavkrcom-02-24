<?php

/**
 * PDO SQLite boundary for the archived PHP applications.
 *
 * Legacy query builders concatenate fragments and use scrollable result sets.
 * Keep those call sites working while binding input values through PDO.
 */
final class ProjectDatabase
{
    private PDO $pdo;
    private string $markerPrefix;
    private array $values = [];

    public function __construct(string $path)
    {
        $this->pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_STRINGIFY_FETCHES => true,
        ]);
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->markerPrefix = '__value_' . bin2hex(random_bytes(12)) . '_';
    }

    public function value(mixed $value): string
    {
        $marker = $this->markerPrefix . count($this->values) . '__';
        $this->values[$marker] = (string) $value;
        return $marker;
    }

    public function query(string $sql): ProjectRows|bool
    {
        $bindings = [];
        $marker = '/' . $this->markerPrefix . '\d+__/';
        $sql = preg_replace_callback(
            <<<'REGEX'
~'(?:[^']|'')*'|`[^`]*`|"(?:""|[^"])*"|\b(?:NOW|RAND)\s*\(\s*\)|__value_[0-9a-f]{24}_\d+__~i
REGEX,
            function (array $match) use (&$bindings, $marker): string {
                $token = $match[0];
                if (preg_match($marker, $token)) {
                    if ($token[0] === "'") {
                        $token = str_replace("''", "'", substr($token, 1, -1));
                    } elseif ($token[0] === '`' || $token[0] === '"') {
                        throw new RuntimeException('A data value cannot be used as an SQL identifier.');
                    }
                    $name = ':value' . count($bindings);
                    $bindings[$name] = preg_replace_callback(
                        $marker, fn (array $match): string => $this->values[$match[0]], $token
                    );
                    return $name;
                }
                if (preg_match('/^NOW/i', $token)) {
                    return 'CURRENT_TIMESTAMP';
                }
                if (preg_match('/^RAND/i', $token)) {
                    return 'random()';
                }
                return $token;
            },
            $sql
        );

        // Translate MySQL's limited UPDATE/DELETE for SQLite builds which omit that syntax.
        if (preg_match('/^\s*(UPDATE|DELETE\s+FROM)\s+(`?\w+`?)/i', $sql, $mutation)
            && preg_match('/\s+LIMIT\s+(\d+)\s*;?\s*$/i', $sql, $limit, PREG_OFFSET_CAPTURE)
        ) {
            $wherePattern = <<<'REGEX'
~'(?:[^']|'')*'(*SKIP)(*F)|`[^`]*`(*SKIP)(*F)|"(?:""|[^"])*"(*SKIP)(*F)|\bWHERE\b~i
REGEX;
            $beforeLimit = substr($sql, 0, $limit[0][1]);
            if (!preg_match($wherePattern, $beforeLimit, $where, PREG_OFFSET_CAPTURE)) {
                throw new RuntimeException('A limited database change needs a WHERE clause.');
            }
            $table = $mutation[2];
            $condition = substr($beforeLimit, $where[0][1] + strlen($where[0][0]));
            $prefix = substr($beforeLimit, 0, $where[0][1]);
            $sql = "$prefix WHERE rowid IN (SELECT rowid FROM $table WHERE $condition LIMIT {$limit[1][0]})";
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);
        return $statement->columnCount() ? new ProjectRows($statement->fetchAll(PDO::FETCH_BOTH)) : true;
    }

    public function lastId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}

final class ProjectRows
{
    public int $offset = 0;

    public function __construct(public readonly array $rows) {}
}

function project_database(string $name): ProjectDatabase
{
    static $connections = [];
    if (isset($connections[$name])) {
        return $connections[$name];
    }
    if (!preg_match('/^[a-z-]+$/D', $name)) {
        throw new RuntimeException('Invalid database configuration name.');
    }

    [$config, $settings] = project_settings($name);
    $path = $settings['DB_DATABASE'] ?? '';
    if ($path !== '' && !preg_match('~^(?:/|[A-Za-z]:[/\\\\])~', $path)) {
        $path = dirname($config) . '/' . $path;
    }
    if (($settings['DB_CONNECTION'] ?? '') !== 'sqlite' || !is_file($path)) {
        throw new RuntimeException('Set DB_DATABASE to an existing SQLite file.');
    }
    return $connections[$name] = new ProjectDatabase($path);
}

function project_config(string $name, string $key): string
{
    [, $settings] = project_settings($name);
    return $settings[$key] ?? '';
}

function project_settings(string $name): array
{
    $dataDirectory = getenv('LEGACY_PROJECT_DATA') ?: dirname(__DIR__, 2) . '/private-data';
    $config = $dataDirectory . '/config/' . $name . '/.env';
    $settings = @parse_ini_file($config, false, INI_SCANNER_RAW);
    if ($settings === false) {
        throw new RuntimeException('The project database .env is missing or unreadable.');
    }
    return [$config, $settings];
}

function project_query(ProjectDatabase $db, string $sql): ProjectRows|bool
{
    return $db->query($sql);
}

function project_value(ProjectDatabase $db, mixed $value): string
{
    return $db->value($value);
}

function project_fetch_array(ProjectRows $result): ?array
{
    return $result->rows[$result->offset++] ?? null;
}

function project_fetch_assoc(ProjectRows $result): ?array
{
    $row = project_fetch_array($result);
    return $row === null ? null : array_filter($row, 'is_string', ARRAY_FILTER_USE_KEY);
}

function project_fetch_row(ProjectRows $result): ?array
{
    $row = project_fetch_array($result);
    return $row === null ? null : array_values(array_filter($row, 'is_int', ARRAY_FILTER_USE_KEY));
}

function project_num_rows(ProjectRows $result): int
{
    return count($result->rows);
}

function project_data_seek(ProjectRows $result, int $offset): bool
{
    if (!isset($result->rows[$offset])) {
        return false;
    }
    $result->offset = $offset;
    return true;
}

function project_insert_id(ProjectDatabase $db): int
{
    return $db->lastId();
}
