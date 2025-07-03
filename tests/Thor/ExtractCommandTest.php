<?php

namespace Cesurapp\ApiBundle\Tests\Thor;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ExtractCommandTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testCommand(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);
        $commandTester = new CommandTester($application->find('thor:extract'));
        $commandTester->execute(['path' => './var/api']);
        $commandTester->assertCommandIsSuccessful();
        $this->assertFileExists('./var/api/index.ts');
    }
}
