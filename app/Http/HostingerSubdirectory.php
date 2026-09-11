<?php

namespace App\Http;

/**
 * Hostinger serve o projeto em /server, mas o front controller fica em
 * /server/public/index.php. O rewrite da raiz mantém REQUEST_URI=/server/login
 * e SCRIPT_NAME=/server/public/index.php — o Laravel então não encontra a rota.
 */
class HostingerSubdirectory
{
    public static function adjustServerVars(array $server): array
    {
        $scriptName = $server['SCRIPT_NAME'] ?? '';
        $requestUri = parse_url($server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        if (! str_ends_with($scriptName, '/public/index.php')) {
            return $server;
        }

        $publicPrefix = substr($scriptName, 0, -strlen('/index.php'));

        if (! str_starts_with($requestUri, $publicPrefix)) {
            $adjusted = substr($scriptName, 0, -strlen('/public/index.php')).'/index.php';
            $server['SCRIPT_NAME'] = $adjusted;
            $server['PHP_SELF'] = $adjusted;
        }

        return $server;
    }
}
