<?php

class ExceptionLog extends Rails\ActiveRecord\Base
{
    protected function associations()
    {
        return [
            'belongs_to' => [
                'user' => ['foreign_key' => 'user_id'],
            ],
        ];
    }

    public static function capture(\Throwable $e, array $context = [])
    {
        try {
            $record = new self();
            $record->code = self::generateCode();
            $record->exception_class = get_class($e);
            $record->message = mb_substr($e->getMessage(), 0, 65535);
            $record->backtrace = $e->getTraceAsString();
            $record->request_uri = $context['request_uri'] ?? ($_SERVER['REQUEST_URI'] ?? null);
            $record->request_method = $context['request_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? null);
            $record->ip_address = $context['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? null);
            $record->user_id = $context['user_id'] ?? null;
            $record->version = self::appVersion();
            $record->created_at = date('Y-m-d H:i:s');

            // Capture SQL query for PDO exceptions
            if ($e instanceof \PDOException && isset($context['query'])) {
                $record->extra_data = json_encode([
                    'query' => $context['query'],
                    'binds' => $context['binds'] ?? [],
                ]);
            }

            $record->save();
            return $record->code;
        } catch (\Exception $ignored) {
            return null;
        }
    }

    public static function prune($days = 365)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return self::connection()->executeSql(
            "DELETE FROM exception_logs WHERE created_at < ? LIMIT 1000",
            $cutoff,
        );
    }

    public static function generateCode()
    {
        return bin2hex(random_bytes(16));
    }

    public static function appVersion()
    {
        static $version = null;
        if ($version === null) {
            try {
                $version = trim(shell_exec('git rev-parse --short HEAD 2>/dev/null') ?: 'unknown');
            } catch (\Exception $e) {
                $version = 'unknown';
            }
        }
        return $version;
    }

    public function parsed_extra_data()
    {
        if (!$this->extra_data) {
            return [];
        }
        $decoded = json_decode($this->extra_data, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function api_attributes()
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'exception_class' => $this->exception_class,
            'message' => $this->message,
            'created_at' => $this->created_at,
            'version' => $this->version,
        ];
    }
}
