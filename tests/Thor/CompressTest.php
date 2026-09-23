<?php

namespace Cesurapp\ApiBundle\Tests\Thor;

use Cesurapp\ApiBundle\Thor\Extractor\ThorExtractor;
use Cesurapp\ApiBundle\Thor\Generator\TypeScriptGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CompressTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testCompressFile(): void
    {
        self::bootKernel();
        $extractor = self::getContainer()->get(ThorExtractor::class);
        $tsGenerator = new TypeScriptGenerator($extractor->extractData(true));
        @unlink('./var/Api.tar.gz');

        $file = $tsGenerator->generate()->compress('./var');

        $this->assertFileExists('./var/Api.tar.gz');
        $this->assertSame(realpath('./var/Api.tar.gz'), $file->getRealPath());

        $archive = new \PharData('./var/Api.tar.gz');
        $this->assertTrue(isset($archive['index.ts']));
        $this->assertTrue(isset($archive[TypeScriptGenerator::MARKER]));

        // No temporary tar left behind
        $this->assertSame([], glob('./var/thor_*'));
    }

    public function testTemporaryDirectoryIsRemoved(): void
    {
        self::bootKernel();
        $extractor = self::getContainer()->get(ThorExtractor::class);

        $tsGenerator = new TypeScriptGenerator($extractor->extractData(true));
        $path = $tsGenerator->generate()->getPath();
        $this->assertDirectoryExists($path);
        $this->assertStringStartsWith(rtrim(sys_get_temp_dir(), '/').'/thor_', $path);

        unset($tsGenerator);
        $this->assertDirectoryDoesNotExist($path);
    }
}
