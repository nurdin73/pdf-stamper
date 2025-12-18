# PdfStamper

PdfStamper is a Laravel package for **stamping, watermarking, and securing PDFs** using TCPDF + FPDI.

Designed specifically for:

- Legal Documents
- Approval Workflows
- ERP / Internal Systems
- Secure Document Distribution

---

## ✨ Key Features

- **Stamping:**
  - Text (Plain)
  - HTML (`writeHTMLCell`)
  - Images (PNG/JPG)
  - Precise positioning (X, Y in mm)
  - Custom rotation & colors
- **Watermarking:**
  - Text or Image
  - Flexible positions: `center`, `top`, `bottom`, `left`, `right`
  - Layering: `under` (behind content) or `over` (on top of content)
  - Opacity control (transparency)
- **Security:**
  - PDF Password protection (Optional)
  - File-level encryption (Optional)
- **Developer Friendly:**
  - Fluent API
  - Safe for loops using `resetInstance()`
  - Centralized configuration via `applyConfig()`

---

## 🔧 Requirements

- PHP >= 8.0
- Laravel >= 8.x
- PHP Extensions: `mbstring`, `gd`

---

## 📦 Installation

Install the package via Composer:

```bash
composer require nurdin73/pdf-stamper
```

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=pdf-stamper-config
```

---

## ⚙️ Configuration

Configuration is available at `config/pdf-stamper.php`:

```php
return [
    'unit' => 'mm',
    'default_font' => 'helvetica',
    'default_font_size' => 12,
];
```

---

## 🚀 Basic Usage

### 1. Text Stamping

```php
use PdfStamper;

PdfStamper::resetInstance()
    ->fromFile($source)
    ->stampText('APPROVED', 100, 200, [
        'font_size' => 14,
        'rotate' => 30,
        'color' => '#008000', // Hex string or RGB array: [0, 128, 0]
    ])
    ->save($output);
```

### 2. HTML Stamping

```php
PdfStamper::resetInstance()
    ->fromFile($source)
    ->stampHtml('<b>PAID</b>', 80, 150)
    ->save($output);
```

### 3. Image Stamping

```php
PdfStamper::resetInstance()
    ->fromFile($source)
    ->stampImage(storage_path('logo.png'), 50, 50, [
        'width' => 40,
        'height' => 40,
    ])
    ->save($output);
```

### 4. Watermarking

```php
PdfStamper::resetInstance()
    ->fromFile($source)
    ->watermarkText('CONFIDENTIAL', [
        'position' => 'center',
        'rotate' => 45,
        'opacity' => 0.15,
        'color' => '#FF0000',
        'layer' => 'under',
    ])
    ->save($output);
```

### 5. Decrypt File (Optional)

```php
use PdfStamper;

PdfStamper::decryptFile(
    storage_path('secure/encrypted.pdf'),
    'my-secret-key',
    storage_path('temp/decrypted.pdf')
);
```

---

## 🔁 Single Source of Truth (`applyConfig`)

Use `applyConfig()` to synchronize data from frontend/database directly with the stamping process.

```php
$config = [
    'stamp' => [
        'type' => 'text',
        'value' => 'APPROVED',
        'x' => 120,
        'y' => 200,
        'page' => 1,
        'options' => [
            'font_size' => 14,
            'rotate' => 30,
            'color' => '#008000',
        ],
    ],
    'watermark' => [
        'text' => 'CONFIDENTIAL',
        'position' => 'center',
        'rotate' => 45,
        'opacity' => 0.15,
    ],
];

PdfStamper::resetInstance()
    ->fromFile($source)
    ->applyConfig($config)
    ->save($output);
```

---

## 🔐 Security (Optional)

### PDF Password

Restrict PDF access via opening password.

```php
->encryptPdf('viewer-password')
```

### Encrypt File (Optional)

```php
->encryptFileWithKey('my-secret-key')
```

---

## 🔍 Preview vs Final Flow

| Stage       | Description                                                               |
| :---------- | :------------------------------------------------------------------------ |
| **Preview** | Generates temporary PDF without encryption for visual validation.         |
| **Final**   | Regenerates from original source, applies security/encryption, immutable. |

> **Best Practice:** Always regenerate the file from the original source for the Final stage. Never use a Preview output as the base for a Final file.

---

## 📐 Coordinate Tips (Frontend to Backend)

Frontend typically uses Pixels (px), while PDF uses Millimeters (mm). Use the following formula for conversion:

**Formula:** `mm = px × 25.4 / 96`

**JavaScript Example:**

```javascript
function pxToMm(px) {
  return (px * 25.4) / 96;
}
```

---

## ⚠️ Important Notes

1. **Reset Instance:** Always call `resetInstance()` before starting a new stamping process.
2. **Looping:** Do not reuse the same instance inside loops or queues without resetting.
3. **Preview File Management:**
   - Use randomized filenames.
   - Set a short TTL (Time To Live).
   - Store in non-public storage if possible.
