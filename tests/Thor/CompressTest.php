<?php

namespace Cesurapp\ApiBundle\Tests\Thor;

use Cesurapp\ApiBundle\Thor\Extractor\ThorExtractor;
use Cesurapp\ApiBundle\Thor\Generator\TypeScriptGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CompressTest extends KernelTestCase
{
    public function testCompressFile(): void
    {
        self::bootKernel();
        $extractor = self::getContainer()->get(ThorExtractor::class);
        $tsGenerator = new TypeScriptGenerator($extractor->extractData(true));
        $tsGenerator->generate()->compress('./var');
        $this->assertFileExists('./var/Api.tar.bz2');
    }
}
