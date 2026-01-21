<?php

declare(strict_types=1);

namespace PHPLLM\Tests\Unit;

use PHPLLM\Core\Attachment;
use PHPLLM\Tests\TestCase;

class AttachmentTest extends TestCase
{
    private string $testImagePath;
    private string $testPdfPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary test files
        $this->testImagePath = sys_get_temp_dir() . '/test_image.png';
        $this->testPdfPath = sys_get_temp_dir() . '/test_doc.pdf';

        // Create minimal PNG (1x1 transparent pixel)
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        file_put_contents($this->testImagePath, $png);

        // Create minimal PDF
        $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R>>endobj\nxref\n0 4\n0000000000 65535 f\n0000000009 00000 n\n0000000052 00000 n\n0000000101 00000 n\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n178\n%%EOF";
        file_put_contents($this->testPdfPath, $pdf);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (file_exists($this->testImagePath)) {
            unlink($this->testImagePath);
        }
        if (file_exists($this->testPdfPath)) {
            unlink($this->testPdfPath);
        }
    }

    public function testFromPath(): void
    {
        $attachment = Attachment::fromPath($this->testImagePath);

        $this->assertEquals('image/png', $attachment->getMimeType());
        $this->assertTrue($attachment->isImage());
        $this->assertFalse($attachment->isUrl());
        $this->assertEquals('test_image.png', $attachment->getFilename());
    }

    public function testFromPathThrowsForMissingFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File not found');

        Attachment::fromPath('/nonexistent/file.png');
    }

    public function testFromUrl(): void
    {
        $attachment = Attachment::fromUrl('https://example.com/image.jpg');

        $this->assertTrue($attachment->isUrl());
        $this->assertEquals('https://example.com/image.jpg', $attachment->getUrl());
        $this->assertEquals('image/jpeg', $attachment->getMimeType());
    }

    public function testFromContent(): void
    {
        $content = 'Hello, World!';
        $attachment = Attachment::fromContent($content, 'text/plain', 'hello.txt');

        $this->assertEquals('text/plain', $attachment->getMimeType());
        $this->assertEquals('hello.txt', $attachment->getFilename());
        $this->assertEquals($content, $attachment->getContent());
    }

    public function testFromBase64(): void
    {
        $base64 = base64_encode('Test content');
        $attachment = Attachment::fromBase64($base64, 'text/plain', 'test.txt');

        $this->assertEquals('text/plain', $attachment->getMimeType());
        $this->assertEquals($base64, $attachment->getBase64());
    }

    // Explicit factory method tests

    public function testImageFactory(): void
    {
        $attachment = Attachment::image($this->testImagePath);

        $this->assertTrue($attachment->isImage());
        $this->assertEquals('image/png', $attachment->getMimeType());
    }

    public function testImageUrlFactory(): void
    {
        $attachment = Attachment::imageUrl('https://example.com/photo.png');

        $this->assertTrue($attachment->isUrl());
        $this->assertEquals('image/png', $attachment->getMimeType());
    }

    public function testPdfFactory(): void
    {
        $attachment = Attachment::pdf($this->testPdfPath);

        $this->assertTrue($attachment->isPdf());
        $this->assertEquals('application/pdf', $attachment->getMimeType());
    }

    public function testAudioFactory(): void
    {
        // Create a temporary audio file
        $audioPath = sys_get_temp_dir() . '/test_audio.mp3';
        file_put_contents($audioPath, 'fake mp3 content');

        try {
            $attachment = Attachment::audio($audioPath);
            $this->assertEquals('test_audio.mp3', $attachment->getFilename());
        } finally {
            unlink($audioPath);
        }
    }

    // Type detection tests

    public function testIsImage(): void
    {
        $png = Attachment::fromUrl('https://example.com/test.png');
        $jpg = Attachment::fromUrl('https://example.com/test.jpg');
        $gif = Attachment::fromUrl('https://example.com/test.gif');
        $webp = Attachment::fromUrl('https://example.com/test.webp');

        $this->assertTrue($png->isImage());
        $this->assertTrue($jpg->isImage());
        $this->assertTrue($gif->isImage());
        $this->assertTrue($webp->isImage());
    }

    public function testIsPdf(): void
    {
        $attachment = Attachment::fromUrl('https://example.com/doc.pdf');

        $this->assertTrue($attachment->isPdf());
        $this->assertFalse($attachment->isImage());
    }

    public function testIsAudio(): void
    {
        $mp3 = Attachment::fromUrl('https://example.com/audio.mp3');
        $wav = Attachment::fromUrl('https://example.com/audio.wav');

        $this->assertTrue($mp3->isAudio());
        $this->assertTrue($wav->isAudio());
    }

    public function testIsText(): void
    {
        $txt = Attachment::fromContent('text', 'text/plain');
        $json = Attachment::fromContent('{}', 'application/json');
        $js = Attachment::fromContent('', 'application/javascript');

        $this->assertTrue($txt->isText());
        $this->assertTrue($json->isText());
        $this->assertTrue($js->isText());
    }

    public function testGetBase64FromFile(): void
    {
        $attachment = Attachment::fromPath($this->testImagePath);

        $base64 = $attachment->getBase64();

        $this->assertNotEmpty($base64);
        $this->assertFalse(base64_decode($base64, true) === false);
    }

    public function testGetDataUrl(): void
    {
        $attachment = Attachment::fromPath($this->testImagePath);

        $dataUrl = $attachment->getDataUrl();

        $this->assertStringStartsWith('data:image/png;base64,', $dataUrl);
    }

    public function testGetBase64FromUrlThrows(): void
    {
        $attachment = Attachment::fromUrl('https://example.com/image.png');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot get base64 content for URL-based attachment');

        $attachment->getBase64();
    }
}
