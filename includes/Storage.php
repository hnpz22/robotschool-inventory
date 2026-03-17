<?php
/**
 * includes/Storage.php
 * Cliente MinIO/S3 — singleton, sin SDK externo.
 * Autenticación: AWS Signature Version 4 via cURL.
 *
 * Uso:
 *   $url = Storage::getInstance()->upload('/tmp/foto.jpg', MINIO_BUCKET_ELEMENTOS, 'RS-ARD-001.jpg');
 *   $ok  = Storage::getInstance()->delete(MINIO_BUCKET_ELEMENTOS, 'RS-ARD-001.jpg');
 *   $url = Storage::getInstance()->url(MINIO_BUCKET_ELEMENTOS, 'RS-ARD-001.jpg');
 */
class Storage {

    private static ?Storage $instance = null;

    private string $endpoint;
    private string $publicUrl;
    private string $accessKey;
    private string $secretKey;
    private string $region = 'us-east-1'; // MinIO ignora la región pero Sig V4 la requiere

    private function __construct() {
        $this->endpoint  = MINIO_ENDPOINT;
        $this->publicUrl = MINIO_PUBLIC_URL;
        $this->accessKey = MINIO_ROOT_USER;
        $this->secretKey = MINIO_ROOT_PASSWORD;
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── API pública ────────────────────────────────────────────

    /**
     * Sube un archivo al bucket y retorna la URL pública.
     *
     * @param string $rutaLocal   Ruta absoluta del archivo temporal ($_FILES['x']['tmp_name'])
     * @param string $bucket      Nombre del bucket (ej: MINIO_BUCKET_ELEMENTOS)
     * @param string $destino     Nombre del archivo en MinIO (ej: 'img_abc123.jpg')
     * @return string             URL pública del archivo subido
     * @throws RuntimeException   Si la subida falla
     */
    public function upload(string $rutaLocal, string $bucket, string $destino): string {
        $contenido   = file_get_contents($rutaLocal);
        $contentType = $this->detectarMime($rutaLocal);
        $payloadHash = hash('sha256', $contenido);

        $path = '/' . rawurlencode($bucket) . '/' . $this->encodePath($destino);

        $headers = $this->firmar('PUT', $path, '', $payloadHash, [
            'Content-Type' => $contentType,
        ]);
        $headers['Content-Type']   = $contentType;
        $headers['Content-Length'] = (string) strlen($contenido);

        $ch = curl_init($this->endpoint . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $contenido,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $this->flattenHeaders($headers),
            CURLOPT_TIMEOUT        => 60,
        ]);

        $resp   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException("Storage::upload cURL error: $error");
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Storage::upload HTTP $status — $resp");
        }

        return $this->url($bucket, $destino);
    }

    /**
     * Elimina un archivo del bucket.
     *
     * @return bool true si se eliminó (204) o ya no existía (404)
     */
    public function delete(string $bucket, string $archivo): bool {
        $path        = '/' . rawurlencode($bucket) . '/' . $this->encodePath($archivo);
        $payloadHash = hash('sha256', '');

        $headers = $this->firmar('DELETE', $path, '', $payloadHash, []);

        $ch = curl_init($this->endpoint . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $this->flattenHeaders($headers),
            CURLOPT_TIMEOUT        => 30,
        ]);

        $resp   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException("Storage::delete cURL error: $error");
        }

        return $status === 204 || $status === 404;
    }

    /**
     * Retorna la URL pública de un archivo sin realizar ninguna petición HTTP.
     */
    public function url(string $bucket, string $archivo): string {
        $base = !empty($this->publicUrl) ? $this->publicUrl : $this->endpoint;
        return $base . '/' . $bucket . '/' . $archivo;
    }

    // ── AWS Signature V4 ───────────────────────────────────────

    /**
     * Genera los encabezados firmados (Authorization, x-amz-date, x-amz-content-sha256).
     *
     * @param string $method       GET | PUT | DELETE | HEAD
     * @param string $canonicalUri URI ya codificada, comienza con /
     * @param string $queryString  Query string ya ordenada y codificada
     * @param string $payloadHash  sha256 hex del cuerpo ('' → hash de cadena vacía)
     * @param array  $extraHeaders Encabezados adicionales a firmar (clave => valor)
     * @return array               Todos los encabezados a enviar (incluye Authorization)
     */
    private function firmar(
        string $method,
        string $canonicalUri,
        string $queryString,
        string $payloadHash,
        array  $extraHeaders
    ): array {
        $now      = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $amzDate  = $now->format('Ymd\THis\Z');  // 20260317T120000Z
        $dateStamp = $now->format('Ymd');          // 20260317

        $parsed  = parse_url($this->endpoint);
        $host    = $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');

        // Construir mapa de cabeceras a firmar (en minúsculas, ordenadas)
        $headersToSign = array_merge($extraHeaders, [
            'host'                 => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date'           => $amzDate,
        ]);
        ksort($headersToSign);

        $canonicalHeaders = '';
        $signedHeadersList = [];
        foreach ($headersToSign as $k => $v) {
            $k = strtolower($k);
            $canonicalHeaders   .= $k . ':' . trim($v) . "\n";
            $signedHeadersList[] = $k;
        }
        $signedHeaders = implode(';', $signedHeadersList);

        // Canonical request
        $canonicalRequest = implode("\n", [
            $method,
            $canonicalUri,
            $queryString,
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        // String to sign
        $credentialScope = "$dateStamp/{$this->region}/s3/aws4_request";
        $stringToSign    = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        // Signing key
        $signingKey = $this->hmac(
            $this->hmac(
                $this->hmac(
                    $this->hmac('AWS4' . $this->secretKey, $dateStamp, true),
                    $this->region, true
                ),
                's3', true
            ),
            'aws4_request', true
        );

        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorization = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s,SignedHeaders=%s,Signature=%s',
            $this->accessKey,
            $credentialScope,
            $signedHeaders,
            $signature
        );

        return array_merge($headersToSign, [
            'authorization' => $authorization,
        ]);
    }

    // ── Utilidades internas ────────────────────────────────────

    private function hmac(string $key, string $data, bool $raw = false): string {
        return hash_hmac('sha256', $data, $key, $raw);
    }

    /** Convierte ['key' => 'val'] a ['Key: val'] para CURLOPT_HTTPHEADER */
    private function flattenHeaders(array $headers): array {
        $out = [];
        foreach ($headers as $k => $v) {
            $out[] = $k . ': ' . $v;
        }
        return $out;
    }

    /** Codifica cada segmento del path pero preserva las barras */
    private function encodePath(string $nombre): string {
        return implode('/', array_map('rawurlencode', explode('/', $nombre)));
    }

    private function detectarMime(string $ruta): string {
        if (function_exists('finfo_open')) {
            $fi   = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($fi, $ruta);
            finfo_close($fi);
            if ($mime) return $mime;
        }
        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'pdf'         => 'application/pdf',
            'xlsx'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv'         => 'text/csv',
            default       => 'application/octet-stream',
        };
    }
}
