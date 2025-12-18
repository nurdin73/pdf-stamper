<?php

namespace Nurdin73\PdfStamper\Tests;

use Nurdin73\PdfStamper\Facades\PdfStamper;
use PHPUnit\Framework\Attributes\Test;

class PdfStamperTest extends TestCase
{
    protected string $sourcePdf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourcePdf = $this->getTestFixturePath('sample.pdf');

        // Pastikan sample PDF ada
        if (!file_exists($this->sourcePdf)) {
            $this->createSamplePdf($this->sourcePdf);
        }
    }

    protected function tearDown(): void
    {
        // Clean up output files after tests
        $outputDir = $this->getTestOutputPath('');
        if (is_dir($outputDir)) {
            $files = glob($outputDir . '*.pdf');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        parent::tearDown();
    }

    #[Test]
    public function it_can_stamp_text_and_generate_pdf(): void
    {
        $output = $this->getTestOutputPath('test-text.pdf');

        PdfStamper::resetInstance()
            ->fromFile($this->sourcePdf)
            ->stampText('TEST', 50, 50)
            ->save($output);

        $this->assertFileExists($output);
        $this->assertGreaterThan(1000, filesize($output));
    }

    #[Test]
    public function it_can_apply_watermark(): void
    {
        $output = $this->getTestOutputPath('test-watermark.pdf');

        PdfStamper::resetInstance()
            ->fromFile($this->sourcePdf)
            ->watermarkText('CONFIDENTIAL')
            ->save($output);

        $this->assertFileExists($output);
    }

    #[Test]
    public function it_can_apply_config(): void
    {
        $output = $this->getTestOutputPath('test-config.pdf');

        $config = [
            'stamp' => [
                'type' => 'text',
                'value' => 'APPROVED',
                'x' => 100,
                'y' => 150,
            ]
        ];

        PdfStamper::resetInstance()
            ->fromFile($this->sourcePdf)
            ->applyConfig($config)
            ->save($output);

        $this->assertFileExists($output);
    }

    #[Test]
    public function it_can_encrypt_pdf(): void
    {
        $output = $this->getTestOutputPath('test-encrypted.pdf');

        PdfStamper::resetInstance()
            ->fromFile($this->sourcePdf)
            ->encryptPdf('1234')
            ->save($output);

        $this->assertFileExists($output);
    }

    #[Test]
    public function reset_instance_clears_previous_state(): void
    {
        $output1 = $this->getTestOutputPath('test1.pdf');
        $output2 = $this->getTestOutputPath('test2.pdf');

        PdfStamper::resetInstance()
            ->fromFile($this->sourcePdf)
            ->stampText('FIRST', 50, 50)
            ->save($output1);

        PdfStamper::resetInstance()
            ->fromFile($this->sourcePdf)
            ->save($output2);

        $this->assertFileExists($output1);
        $this->assertFileExists($output2);
        $this->assertNotEquals(filesize($output1), filesize($output2));
    }

    #[Test]
    public function it_can_stamp_on_specific_pages(): void
    {
        $output = $this->getTestOutputPath('test-specific-page.pdf');

        PdfStamper::resetInstance()
            ->fromFile($this->sourcePdf)
            ->onlyOnPages([1])
            ->stampText('PAGE 1 ONLY', 50, 50)
            ->save($output);

        $this->assertFileExists($output);
    }

    /**
     * Create a sample PDF for testing
     */
    protected function createSamplePdf(string $path): void
    {
        $fixturesDir = dirname($path);
        if (!is_dir($fixturesDir)) {
            mkdir($fixturesDir, 0755, true);
        }

        // Create a simple PDF using TCPDF
        $pdf = new \TCPDF();
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Add first page
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Text(50, 50, 'Sample PDF Page 1');

        // Add second page
        $pdf->AddPage();
        $pdf->Text(50, 50, 'Sample PDF Page 2');

        $pdf->Output($path, 'F');
    }
}
