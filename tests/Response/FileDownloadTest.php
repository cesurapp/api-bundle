<?php

namespace Cesurapp\ApiBundle\Tests\Response;

use Cesurapp\ApiBundle\Response\ApiResponse;
use PHPUnit\Framework\TestCase;

class FileDownloadTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'download');
        file_put_contents($this->file, str_repeat('x', 3000));
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testNonAsciiFileName(): void
    {
        $response = ApiResponse::downloadFileLarge($this->file, 'şubat_raporu.pdf');

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('filename=subat_raporu.pdf', $disposition);
        $this->assertStringContainsString("filename*=utf-8''%C5%9Fubat_raporu.pdf", $disposition);
        $this->assertSame('3000', $response->headers->get('Content-Length'));

        ob_start();
        $response->sendContent();
        $this->assertSame(3000, strlen((string) ob_get_clean()));
    }

    public function testBinaryDownloadWithNonAsciiFileName(): void
    {
        $response = ApiResponse::downloadFile($this->file, 'ağustos.csv');

        $this->assertStringContainsString("filename*=utf-8''a%C4%9Fustos.csv", $response->headers->get('Content-Disposition'));
    }
}
