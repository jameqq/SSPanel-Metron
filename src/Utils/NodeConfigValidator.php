<?php

namespace App\Utils;

use InvalidArgumentException;

final class NodeConfigValidator
{
    private const MAX_CUSTOM_CONFIG_BYTES = 60000;

    private const MODERN_PROTOCOL_SORTS = [16, 17, 18];

    public static function normalizeCustomConfig($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (strlen($value) > self::MAX_CUSTOM_CONFIG_BYTES) {
            throw new InvalidArgumentException('custom_config 不能超过 60000 字节');
        }

        $object = json_decode($value, false, 32);
        if (json_last_error() !== JSON_ERROR_NONE || !is_object($object)) {
            throw new InvalidArgumentException('custom_config 必须是有效的 JSON 对象');
        }
        $decoded = json_decode($value, true, 32);
        self::validateJsonValue($decoded, 0);

        return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function decodeCustomConfig($value): array
    {
        if ($value === null || trim((string) $value) === '') {
            return [];
        }
        try {
            $normalized = self::normalizeCustomConfig($value);
        } catch (InvalidArgumentException $exception) {
            return [];
        }

        return json_decode($normalized, true);
    }

    public static function validateServer(int $sort, $server): void
    {
        if (!in_array($sort, self::MODERN_PROTOCOL_SORTS, true)) {
            return;
        }

        $parts = explode(';', trim((string) $server), 2);
        $host = trim($parts[0]);
        if ($host === '' || preg_match('/[\x00-\x20\x7f]/', $host)) {
            throw new InvalidArgumentException('节点地址不能为空且不能包含空白或控制字符');
        }

        $options = isset($parts[1]) ? self::parseOptions($parts[1]) : [];
        self::validateAllowedOptions($sort, $options);
        self::validateIntegerOption($options, 'port', 1, 65535);
        self::validateIntegerOption($options, 'upmbps', 1, 100000);
        self::validateIntegerOption($options, 'downmbps', 1, 100000);

        foreach (['insecure', 'zero_rtt_handshake'] as $name) {
            if (isset($options[$name]) && !in_array(strtolower($options[$name]), ['0', '1', 'true', 'false'], true)) {
                throw new InvalidArgumentException($name . ' 只能是 0、1、true 或 false');
            }
        }
        if (isset($options['sni']) && !self::isValidServerName($options['sni'])) {
            throw new InvalidArgumentException('SNI 格式不正确');
        }
        if (isset($options['alpn'])) {
            foreach (explode(',', $options['alpn']) as $alpn) {
                if (!in_array(trim($alpn), ['h3', 'h2', 'http/1.1'], true)) {
                    throw new InvalidArgumentException('ALPN 仅支持 h3、h2、http/1.1');
                }
            }
        }
        if ($sort === 16 && isset($options['obfs']) && !in_array($options['obfs'], ['', 'salamander'], true)) {
            throw new InvalidArgumentException('Hysteria2 obfs 仅支持 salamander');
        }
        if ($sort === 17 && isset($options['congestion_control']) && !in_array($options['congestion_control'], ['bbr', 'cubic', 'new_reno'], true)) {
            throw new InvalidArgumentException('TUIC congestion_control 仅支持 bbr、cubic、new_reno');
        }
        if ($sort === 17 && isset($options['udp_relay_mode']) && !in_array($options['udp_relay_mode'], ['native', 'quic'], true)) {
            throw new InvalidArgumentException('TUIC udp_relay_mode 仅支持 native 或 quic');
        }
    }

    private static function parseOptions(string $value): array
    {
        $result = [];
        foreach (explode('|', $value) as $option) {
            $position = strpos($option, '=');
            if ($position === false) {
                throw new InvalidArgumentException('协议参数必须使用 key=value 格式');
            }
            $key = trim(substr($option, 0, $position));
            $item = trim(substr($option, $position + 1));
            if ($key === '' || isset($result[$key])) {
                throw new InvalidArgumentException('协议参数名称不能为空或重复');
            }
            $result[$key] = $item;
        }
        return $result;
    }

    private static function validateAllowedOptions(int $sort, array $options): void
    {
        $common = ['port', 'sni', 'alpn', 'insecure'];
        $allowed = [
            16 => array_merge($common, ['obfs', 'obfs_password', 'upmbps', 'downmbps']),
            17 => array_merge($common, ['congestion_control', 'udp_relay_mode', 'zero_rtt_handshake']),
            18 => $common,
        ][$sort];
        $unknown = array_diff(array_keys($options), $allowed);
        if ($unknown) {
            throw new InvalidArgumentException('不支持的协议参数：' . implode(', ', $unknown));
        }
    }

    private static function validateIntegerOption(array $options, string $name, int $min, int $max): void
    {
        if (!isset($options[$name])) {
            return;
        }
        $value = filter_var($options[$name], FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) {
            throw new InvalidArgumentException($name . ' 必须在 ' . $min . '-' . $max . ' 范围内');
        }
    }

    private static function isValidServerName(string $value): bool
    {
        return $value !== '' && strlen($value) <= 253 && !preg_match('/[\x00-\x20\x7f\/?#]/', $value);
    }

    private static function validateJsonValue($value, int $depth): void
    {
        if ($depth > 20) {
            throw new InvalidArgumentException('custom_config 嵌套层级过深');
        }
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                if (in_array(strtolower($key), ['__proto__', 'prototype', 'constructor'], true)) {
                    throw new InvalidArgumentException('custom_config 包含禁止字段：' . $key);
                }
                if (!preg_match('/^[A-Za-z0-9_.-]{1,128}$/', $key)) {
                    throw new InvalidArgumentException('custom_config 字段名格式不正确：' . $key);
                }
            }
            self::validateJsonValue($item, $depth + 1);
        }
    }

}
