<?php

namespace Nurdin73\PdfStamper;

use setasign\Fpdi\Tcpdf\Fpdi as TcpdfFpdi;

class PdfStamper
{
    protected static ?self $instance = null;

    protected TcpdfFpdi $pdf;
    protected string $sourceFile;

    protected array $onlyPages = [];
    protected ?array $fileEncryption = null;
    protected ?string $fileEncryptionKey = null;


    /** @var array<callable> Queue of stamp operations to apply after rendering */
    protected array $stampQueue = [];

    /* ==========================
     |  INSTANCE CONTROL
     ========================== */

    public static function resetInstance(): self
    {
        static::$instance = new self();
        return static::$instance;
    }

    public static function instance(): self
    {
        if (!static::$instance) {
            static::$instance = new self();
        }

        return static::$instance;
    }

    /* ==========================
     |  CORE SETUP
     ========================== */

    public function fromFile(string $path): self
    {
        $this->sourceFile = $path;

        $this->pdf = new TcpdfFpdi(
            config('pdf-stamper.unit', 'mm'),
            'mm',
            'A4',
            true,
            'UTF-8'
        );

        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->SetMargins(0, 0, 0);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);

        return $this;
    }

    public function encryptFileWithKey(?string $key): self
    {
        $this->fileEncryptionKey = $key;

        return $this;
    }


    /* ==========================
     |  PAGE FILTER
     ========================== */

    public function onlyOnPages(array $pages): self
    {
        $this->onlyPages = $pages;
        return $this;
    }

    protected function shouldStamp(int $page, array $onlyPages = []): bool
    {
        $pages = $onlyPages ?: $this->onlyPages;
        return empty($pages) || in_array($page, $pages);
    }

    /* ==========================
     |  STAMP METHODS
     ========================== */

    public function stampText(string $text, float $x, float $y, array $options = []): self
    {
        $onlyPages = $this->onlyPages;

        $this->stampQueue[] = function (int $page) use ($text, $x, $y, $options, $onlyPages) {
            if (!$this->shouldStamp($page, $onlyPages)) {
                return;
            }

            $this->applyRotation($options, $x, $y);

            $this->pdf->SetFont(
                config('pdf-stamper.default_font', 'helvetica'),
                '',
                $options['font_size'] ?? 12
            );

            if (!empty($options['color'])) {
                [$r, $g, $b] = $this->parseColor($options['color']);
                $this->pdf->SetTextColor($r, $g, $b);
            }

            $this->pdf->Text($x, $y, $text);
            $this->pdf->StopTransform();
        };

        return $this;
    }

    public function stampHtml(string $html, float $x, float $y, array $options = []): self
    {
        $onlyPages = $this->onlyPages;

        $this->stampQueue[] = function (int $page) use ($html, $x, $y, $options, $onlyPages) {
            if (!$this->shouldStamp($page, $onlyPages)) {
                return;
            }

            $this->applyRotation($options, $x, $y);

            $this->pdf->writeHTMLCell(
                $options['width'] ?? 0,
                $options['height'] ?? 0,
                $x,
                $y,
                $html
            );

            $this->pdf->StopTransform();
        };

        return $this;
    }

    public function stampImage(string $path, float $x, float $y, array $options = []): self
    {
        $onlyPages = $this->onlyPages;

        $this->stampQueue[] = function (int $page) use ($path, $x, $y, $options, $onlyPages) {
            if (!$this->shouldStamp($page, $onlyPages)) {
                return;
            }

            $this->applyRotation($options, $x, $y);

            $this->pdf->Image(
                $path,
                $x,
                $y,
                $options['width'] ?? 0,
                $options['height'] ?? 0
            );

            $this->pdf->StopTransform();
        };

        return $this;
    }

    /* ==========================
     |  WATERMARK
     ========================== */

    public function watermarkText(string $text, array $options = []): self
    {
        $options['opacity'] ??= 0.15;
        $options['rotate'] ??= 45;
        $options['position'] ??= 'center';

        $watermarkOnlyPages = $options['only_pages'] ?? $this->onlyPages;

        $this->stampQueue[] = function (int $page) use ($text, $options, $watermarkOnlyPages) {
            if (!$this->shouldStamp($page, $watermarkOnlyPages)) {
                return;
            }

            $this->applyLayer($options);

            $this->pdf->SetAlpha($options['opacity']);

            [$x, $y] = $this->resolvePosition($options);

            $this->applyRotation($options, $x, $y);

            if (!empty($options['color'])) {
                [$r, $g, $b] = $this->parseColor($options['color']);
                $this->pdf->SetTextColor($r, $g, $b);
            }

            $this->pdf->SetFont('helvetica', 'B', 40);
            $this->pdf->Text($x, $y, $text);

            $this->pdf->SetAlpha(1);
            $this->pdf->StopTransform();
        };

        return $this;
    }

    /* ==========================
     |  APPLY CONFIG
     ========================== */

    public function applyConfig(array $config): self
    {
        if (!empty($config['stamp'])) {
            $s = $config['stamp'];

            if (!empty($s['page'])) {
                $this->onlyOnPages([$s['page']]);
            }

            match ($s['type'] ?? 'text') {
                'html'  => $this->stampHtml($s['value'], $s['x'], $s['y'], $s['options'] ?? []),
                'image' => $this->stampImage($s['value'], $s['x'], $s['y'], $s['options'] ?? []),
                default => $this->stampText($s['value'], $s['x'], $s['y'], $s['options'] ?? []),
            };
        }

        if (!empty($config['watermark']['text'])) {
            $this->watermarkText($config['watermark']['text'], $config['watermark']);
        }

        return $this;
    }

    /* ==========================
     |  SECURITY
     ========================== */

    public function encryptPdf(string $password): self
    {
        $this->pdf->SetProtection(['print'], $password, null, 0);
        return $this;
    }

    protected function encryptFile(string $path): void
    {
        if (!$this->fileEncryptionKey) {
            return;
        }

        $data = file_get_contents($path);

        $key = hash('sha256', $this->fileEncryptionKey, true); // 32 bytes
        $iv  = random_bytes(12); // GCM standard
        $tag = '';

        $encrypted = openssl_encrypt(
            $data,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($encrypted === false) {
            throw new \RuntimeException('File encryption failed.');
        }

        $payload = $iv . $encrypted . $tag;

        file_put_contents($path, $payload);
    }


    /* ==========================
     |  SAVE
     ========================== */

    public function save(string $path): void
    {
        $this->renderPdf();
        $this->applyStamps();

        $this->pdf->Output($path, 'F');

        $this->encryptFile($path);
    }

    public static function decryptFile(
        string $encryptedPath,
        string $key,
        string $outputPath
    ): void {
        $payload = file_get_contents($encryptedPath);

        $iv  = substr($payload, 0, 12);
        $tag = substr($payload, -16);
        $enc = substr($payload, 12, -16);

        $key = hash('sha256', $key, true);

        $data = openssl_decrypt(
            $enc,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($data === false) {
            throw new \RuntimeException('Invalid encryption key or corrupted file.');
        }

        file_put_contents($outputPath, $data);
    }


    protected function renderPdf(): void
    {
        $pageCount = $this->pdf->setSourceFile($this->sourceFile);

        for ($i = 1; $i <= $pageCount; $i++) {
            $tpl = $this->pdf->importPage($i);
            $size = $this->pdf->getTemplateSize($tpl);

            $this->pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $this->pdf->useTemplate($tpl);
        }
    }

    /**
     * Apply all queued stamp operations to each page
     */
    protected function applyStamps(): void
    {
        $pageCount = $this->pdf->getNumPages();

        foreach ($this->stampQueue as $stampCallback) {
            for ($page = 1; $page <= $pageCount; $page++) {
                $this->pdf->setPage($page);
                $stampCallback($page);
            }
        }
    }

    /* ==========================
     |  HELPERS
     ========================== */

    protected function applyRotation(array $options, float $x, float $y): void
    {
        if (!empty($options['rotate'])) {
            $this->pdf->StartTransform();
            $this->pdf->Rotate($options['rotate'], $x, $y);
        }
    }

    protected function applyLayer(array $options): void
    {
        if (($options['layer'] ?? 'over') === 'under') {
            $this->pdf->setPageMark();
        }
    }

    protected function resolveEncryptionFile($file, string $key)
    {
        $key = hash('sha256', $key, true); // 32 bytes
        $iv  = random_bytes(12); // GCM standard
        $tag = '';

        $encrypted = openssl_encrypt(
            $file,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($encrypted === false) {
            throw new \RuntimeException('File encryption failed.');
        }

        $payload = $iv . $encrypted . $tag;

        return $payload;
    }

    protected function resolvePosition(array $options): array
    {
        $w = $this->pdf->getPageWidth();
        $h = $this->pdf->getPageHeight();

        return match ($options['position']) {
            'top' => [$w / 2 - 40, 20],
            'bottom' => [$w / 2 - 40, $h - 30],
            'left' => [10, $h / 2],
            'right' => [$w - 80, $h / 2],
            default => [$w / 2 - 40, $h / 2],
        };
    }

    /**
     * Parse color to RGB array
     * Supports: hex string ('#FF0000') or RGB array ([255, 0, 0])
     * 
     * @param string|array $color
     * @return array [R, G, B]
     */
    protected function parseColor(string|array $color): array
    {
        // If already an array, assume [R, G, B]
        if (is_array($color)) {
            return [
                (int) ($color[0] ?? 0),
                (int) ($color[1] ?? 0),
                (int) ($color[2] ?? 0),
            ];
        }

        // Parse hex string
        $hex = ltrim($color, '#');
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
