<?php
abstract class Danbooru
{
    static public function http_get_streaming($source, array $options = [], $block = null)
    {
        $max_size = !empty($options['max_size']) ? $options['max_size'] : CONFIG()->max_image_size;
        if ((int)$max_size === 0) # unlimited
            $max_size = null;

        # PROJ-46 AC-7: Reject data: URLs to prevent SSRF bypass.
        if (preg_match('/^data:/i', $source)) {
            throw new Danbooru\Exception\RuntimeException('SocketError: data: URLs are not supported');
        }

        $redirections_limit = 4;
        $timeout = (int)CONFIG()->http_streaming_timeout;
        $file = '';
        
        $write_function = function($handle, $data) use (&$file, $max_size) {
            $file .= $data;
            if ($max_size && strlen($file) > $max_size)
                return 0;
            return strlen($data);
        };
        
        while (true) {
            $file = '';
            
            $url = parse_url($source);

            if (empty($url['scheme']) || $url['scheme'] != 'http' && $url['scheme'] != 'https')
                throw new Danbooru\Exception\RuntimeException('SocketError: URL must be HTTP or HTTPS');

            # check if the request uri is not percent-encoded
            // if url.request_uri.match "/[^!*'();:@&=+$,\/?#\[\]ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789\-_.~%]/"
                // url.path = Addressable::URI.encode(url.path)
                // url.query = Addressable::URI.encode(url.query)
            // end

            
            # Addressable doesn't fill in port data if not explicitly given.
            if (!isset($url['port']))
                $url['port'] = $url['scheme'] == 'https' ? 443 : 80;

            $opts = [];
            if ($timeout)
                $opts[CURLOPT_TIMEOUT] = $timeout;
            $opts[CURLOPT_RETURNTRANSFER] = true;
            $opts[CURLINFO_HEADER_OUT] = true;
            $opts[CURLOPT_WRITEFUNCTION] = $write_function;
            
            $opts[CURLOPT_HTTPHEADER] = [
                "User-Agent: " . CONFIG()->app_name . '/' . CONFIG()->version,
                "Referer: " . $source
            ];
            
            if (preg_match('/pixiv\.net/', $source)) {
                $opts[CURLOPT_HTTPHEADER][] = "Referer: http://www.pixiv.net";

                # Don't download the small version
                if (preg_match('~(/img/.+?/.+?)_m.+$~', $source, $m)) {
                    $match = $m[1];
                    $source = str_replace($match . "_m", $match);
                }
            }
            
            $ch = curl_init($source);
            curl_setopt_array($ch, $opts);
            $res = curl_exec($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            
            if ($timeout && $info['total_time'] === (float)$timeout) {
                throw new Danbooru\Exception\RuntimeException("Request timed out");
            } elseif ($info['redirect_url']) {
                if ($info['redirect_count'] > $redirections_limit)
                    throw new Danbooru\Exception\RuntimeException("Too many redirects");
                $source = $info['redirect_url'];
            } elseif ($info['http_code'] == 200) {
                if ($max_size) {
                    $len = $info['size_download'];
                    if ($len > $max_size)
                        throw new Danbooru\Exception\RuntimeException("File is too large ($len bytes)");
                }
                if (!$file)
                    throw new Danbooru\Exception\RuntimeException("Response is empty");
                return $file;
            } else {
                throw new Danbooru\Exception\RuntimeException("HTTP error code: " . $info['http_code']);
            }
        }
    }
}
