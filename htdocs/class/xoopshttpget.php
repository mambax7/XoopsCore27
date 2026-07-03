<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * XoopsHttpGet - return response to a http get request
 *
 * @category  HttpGet
 * @package   Xoops
 * @author    Richard Griffith <richard@geekwright.com>
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 */
class XoopsHttpGet
{
    protected $useCurl = true;
    protected $url;
    protected $error;

    /**
     * XoopsHttpGet constructor.
     *
     * @param string $url the url to process
     *
     * @throws \RuntimeException if neither curl extension nor stream wrappers (allow_url_fopen) is available
     */
    public function __construct($url)
    {
        $this->url = $url;
        if (!function_exists('curl_init')) {
            $this->useCurl = false;
            $urlFopen = (int) ini_get('allow_url_fopen');
            if ($urlFopen === 0) {
                throw new \RuntimeException("CURL extension or allow_url_fopen ini setting is required.");
            }
        }
    }

    /**
     * Return the response from a GET to the specified URL.
     *
     * @return string|bool response or false on error
     */
    public function fetch()
    {
        if (!$this->isAllowedUrl((string) $this->url)) {
            $this->error = 'URL rejected: only public http(s) targets are allowed.';
            return false;
        }
        return ($this->useCurl) ? $this->fetchCurl() : $this->fetchFopen();
    }

    /**
     * SSRF guard (SECURITY.md M-5): allow only public http(s) targets. Returns the
     * validated host/port plus a safe resolved IP to pin the connection to, or null
     * if the URL must be refused. Blocks non-http schemes (file://, php://, …),
     * userinfo URLs, and hosts that resolve to private/loopback/link-local/reserved
     * addresses. Returning the resolved IP lets the caller pin the connection, which
     * also closes the DNS-rebinding window.
     *
     * @param string $url
     *
     * @return array{host:string,port:int,ip:string}|null
     */
    protected function resolveAllowed(string $url): ?array
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null; // userinfo form can mislead host parsing
        }
        $host = $parts['host'];
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);

        // IPv6 literals are intentionally unsupported: parse_url() keeps the [..] brackets,
        // which fail FILTER_VALIDATE_IP and DNS below, so every IPv6 literal (public or
        // private) is rejected. This is deliberate — the resolver is IPv4-only, and
        // reliably classifying private/reserved IPv6 (mapped/loopback/ULA) across the PHP
        // 8.2 floor is error-prone, so we fail closed rather than risk an SSRF gap.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            // @-suppressed: an unresolvable host emits a benign "host not found"
            // warning whose false return we handle immediately (fail closed, R-030).
            $ips = @gethostbynamel($host);
            if ($ips === false || $ips === []) {
                return null;
            }
        }
        $safeIp = null;
        foreach ($ips as $ip) {
            // Reject if ANY resolved address is private/reserved (defeats split-horizon DNS).
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
            if ($safeIp === null) {
                $safeIp = $ip;
            }
        }
        if ($safeIp === null) {
            return null;
        }

        return ['host' => $host, 'port' => $port, 'ip' => $safeIp];
    }

    /**
     * Thin boolean wrapper around {@see resolveAllowed()} (used by fetch() and tests).
     *
     * @param string $url
     *
     * @return bool
     */
    protected function isAllowedUrl(string $url): bool
    {
        return $this->resolveAllowed($url) !== null;
    }

    /**
     * Use curl to GET the specified URL. Redirects are followed manually so each hop
     * is re-validated against the SSRF allowlist and pinned to its resolved IP — an
     * attacker URL cannot 30x-redirect to 127.0.0.1 / 169.254.169.254 / RFC1918, and
     * the pin closes the DNS-rebinding window (SECURITY.md M-5).
     *
     * @return string|bool response or false on error
     */
    protected function fetchCurl()
    {
        $url       = (string) $this->url;
        $maxRedirs = 4;

        for ($hop = 0; $hop <= $maxRedirs; ++$hop) {
            $pin = $this->resolveAllowed($url);
            if ($pin === null) {
                $this->error = 'URL rejected: only public http(s) targets are allowed.';
                return false;
            }
            $curlHandle = curl_init($url);
            if (false === $curlHandle) {
                $this->error = 'curl_init failed';
                return false;
            }
            curl_setopt_array($curlHandle, [
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HEADER         => 0,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => 0, // follow manually so each hop is re-validated
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                // Pin the connection to the address we just validated (no DNS rebinding).
                CURLOPT_RESOLVE        => [$pin['host'] . ':' . $pin['port'] . ':' . $pin['ip']],
            ]);

            $response    = curl_exec($curlHandle);
            $httpcode    = (int) curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
            $redirectUrl = (string) curl_getinfo($curlHandle, CURLINFO_REDIRECT_URL);
            $curlError   = curl_error($curlHandle);
            curl_close($curlHandle);

            if (false === $response) {
                $this->error = $curlError;
                return false;
            }
            if ($httpcode >= 300 && $httpcode < 400 && $redirectUrl !== '') {
                $url = $redirectUrl; // re-validated + re-pinned at the top of the next iteration
                continue;
            }
            if (200 !== $httpcode) {
                $this->error = $response;
                return false;
            }
            return $response;
        }

        $this->error = 'Too many redirects';
        return false;
    }

    /**
     * Use stream wrapper to GET the specified URL. The request is sent to the IP that
     * resolveAllowed() just validated (host rewritten to the pinned IP, Host header and
     * TLS peer_name kept as the original host), so the stream wrapper cannot re-resolve
     * to an internal address (DNS rebinding). Redirects are disabled (SECURITY.md M-5).
     *
     * @return string|false response or false on error
     */
    protected function fetchFopen()
    {
        $url = (string) $this->url;
        $pin = $this->resolveAllowed($url);
        if ($pin === null) {
            $this->error = 'URL rejected: only public http(s) targets are allowed.';
            return false;
        }
        $parts  = parse_url($url) ?: [];
        $scheme = strtolower((string) ($parts['scheme'] ?? 'http'));
        $ipHost = str_contains($pin['ip'], ':') ? '[' . $pin['ip'] . ']' : $pin['ip'];
        $target = $scheme . '://' . $ipHost . ':' . $pin['port']
            . ($parts['path'] ?? '/')
            . (isset($parts['query']) ? '?' . $parts['query'] : '');

        // Include the port in the Host header when it is non-default (RFC 7230).
        $hostHeader = $pin['host'];
        if (('http' === $scheme && 80 !== $pin['port']) || ('https' === $scheme && 443 !== $pin['port'])) {
            $hostHeader .= ':' . $pin['port'];
        }

        $context  = stream_context_create([
            'http' => [
                'follow_location' => 0,
                'max_redirects'   => 0,
                'timeout'         => 10,
                'header'          => 'Host: ' . $hostHeader,
            ],
            'ssl' => [
                'peer_name'        => $pin['host'], // verify the cert against the real host, not the IP
                'verify_peer'      => true,
                'verify_peer_name' => true,
                'SNI_enabled'      => true,
            ],
        ]);
        $response = file_get_contents($target, false, $context);
        if (false === $response) {
            $this->error = 'file_get_contents() failed.';
        }
        return $response;
    }

    /**
     * Return any error set during processing of fetch()
     *
     * @return string|null
     */
    public function getError()
    {
        return $this->error;
    }
}
